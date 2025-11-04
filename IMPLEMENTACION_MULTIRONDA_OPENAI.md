# Implementación: Multi-Ronda Tool Calling en OpenAI Provider

## Fecha: 2025-11-04
## Estado: ✅ COMPLETADO

---

## Resumen Ejecutivo

Se ha implementado soporte multi-ronda de tool calling para el OpenAI Provider en la nueva arquitectura, permitiendo que los bots con herramientas funcionen correctamente cuando el feature flag `aichat_use_provider_architecture` está activado.

### Cambios Realizados

1. **Trait Reutilizable** (`trait-aichat-tool-execution.php`): 
   - Ejecución de tools registrados
   - Logging en base de datos
   - Configuración de límites de rondas

2. **OpenAI Provider Actualizado**:
   - Router inteligente (sin tools → simple, con tools → multi-ronda)
   - Loop multi-ronda para Chat Completions
   - Acumulación de mensajes en formato OpenAI

---

## Arquitectura Implementada

### Decisión: Opción C - Trait Híbrido

**Justificación**:
- ✅ Código común reutilizable entre providers
- ✅ Flexibilidad: cada provider implementa su loop específico
- ✅ No contamina la interface base
- ✅ Balance perfecto entre reutilización y flexibilidad

### Estructura de Archivos

```
includes/
  traits/
    trait-aichat-tool-execution.php (NUEVO - 174 líneas)
  
  providers/
    class-openai-provider.php (MODIFICADO - +168 líneas)
```

---

## Componente 1: Trait AIChat_Tool_Execution

**Ubicación**: `includes/traits/trait-aichat-tool-execution.php`

### Métodos Públicos Protegidos

#### `execute_registered_tools( $tool_calls, $context )`

Ejecuta los callbacks de tools registrados en el sistema.

**Input**:
```php
$tool_calls = [
    [
        'id' => 'call_abc123',
        'name' => 'get_weather',
        'arguments' => '{"city":"Madrid"}'
    ]
];

$context = [
    'request_uuid' => '...',
    'session_id' => '...',
    'bot_slug' => '...',
    'message' => 'What is the weather?',
    'round' => 1
];
```

**Output**:
```php
$outputs = [
    [
        'tool_call_id' => 'call_abc123',
        'name' => 'get_weather',
        'arguments' => '{"city":"Madrid"}',
        'output' => '{"temp":20,"condition":"sunny"}',
        'elapsed_ms' => 150
    ]
];
```

**Características**:
- ✅ Normaliza argumentos (string JSON → array)
- ✅ Captura excepciones y devuelve error estructurado
- ✅ Trunca outputs largos (> 4000 chars)
- ✅ Debug logging de cada ejecución
- ✅ Manejo de tools no encontrados

---

#### `log_tool_executions( $tool_calls, $outputs, $round, $context )`

Registra cada ejecución en la tabla `wp_aichat_tool_calls`.

**Tabla BD**:
```sql
wp_aichat_tool_calls
├── request_uuid (UUID de la request)
├── conversation_id (FK, vinculado después)
├── session_id
├── bot_slug
├── round (1-based)
├── call_id (tool_call_id)
├── tool_name
├── arguments_json
├── output_excerpt
├── duration_ms
├── error_code (NULL o 'error')
└── created_at
```

**Vinculación posterior**: El `conversation_id` se actualiza en `process_message()` mediante `request_uuid`.

---

#### `get_max_rounds( $params )`

Obtiene el límite de rondas configurado.

**Filtro**: `apply_filters('aichat_tools_max_rounds', 5, $bot, $session)`

**Default**: 5 rondas  
**Mínimo**: 1 ronda

---

## Componente 2: OpenAI Provider Actualizado

**Ubicación**: `includes/providers/class-openai-provider.php`

### Cambios en `chat()` (Router Principal)

**Antes**:
```php
public function chat( $messages, $params = [] ) {
    if ( $this->is_gpt5_model( $model ) ) {
        return $this->chat_responses( $messages, $params );
    }
    return $this->chat_completions( $messages, $params );
}
```

**Después**:
```php
public function chat( $messages, $params = [] ) {
    $has_tools = !empty($params['tools']);
    
    if ( $this->is_gpt5_model( $model ) ) {
        // GPT-5 → Responses API (sin tools por ahora)
        return $this->chat_responses( $messages, $params );
    }
    
    if ( $has_tools ) {
        // Multi-ronda con tools
        return $this->chat_completions_with_tools( $messages, $params );
    }
    
    // Sin tools, llamada simple
    return $this->chat_completions( $messages, $params );
}
```

**Decisión de routing**:
1. GPT-5 → Responses API (tools pendiente)
2. GPT-4 + tools → Multi-ronda
3. GPT-4 sin tools → Simple

---

### Nuevo Método: `chat_completions_with_tools()`

**Firma**:
```php
protected function chat_completions_with_tools( $messages, $params = [] )
```

**Algoritmo**:
```
INIT:
  max_rounds = get_max_rounds(params)
  round = 1
  acc_messages = messages
  context = extract_context(params)

LOOP while round <= max_rounds:
  1. result = chat_completions(acc_messages, params_with_tools)
  
  2. IF error → return error
  
  3. IF no tool_calls → return result (DONE)
  
  4. IF round == max_rounds → return result (LIMIT)
  
  5. tool_outputs = execute_registered_tools(result.tool_calls, context)
  
  6. log_tool_executions(result.tool_calls, tool_outputs, round, context)
  
  7. acc_messages = append_openai_tool_messages(acc_messages, result, tool_outputs)
  
  8. round++

RETURN result with final_answer
```

**Ejemplo de Acumulación de Mensajes**:

**Ronda 1** (Input):
```json
[
  {"role": "system", "content": "You are a helpful assistant"},
  {"role": "user", "content": "What's the weather in Madrid?"}
]
```

**Ronda 1** (Output):
```json
{
  "message": "",
  "tool_calls": [
    {
      "id": "call_abc",
      "function": {"name": "get_weather", "arguments": "{\"city\":\"Madrid\"}"}
    }
  ]
}
```

**Ronda 2** (Input - Acumulado):
```json
[
  {"role": "system", "content": "You are a helpful assistant"},
  {"role": "user", "content": "What's the weather in Madrid?"},
  {
    "role": "assistant",
    "content": "",
    "tool_calls": [
      {
        "id": "call_abc",
        "type": "function",
        "function": {"name": "get_weather", "arguments": "{\"city\":\"Madrid\"}"}
      }
    ]
  },
  {
    "role": "tool",
    "tool_call_id": "call_abc",
    "content": "{\"temp\":20,\"condition\":\"sunny\"}"
  }
]
```

**Ronda 2** (Output):
```json
{
  "message": "The weather in Madrid is currently 20°C and sunny.",
  "tool_calls": [] // Sin más tool calls → FIN
}
```

---

### Método Auxiliar: `append_openai_tool_messages()`

Construye la estructura de mensajes para la próxima ronda.

**Input**:
- `$messages`: Mensajes acumulados anteriormente
- `$result`: Resultado de la ronda actual
- `$tool_outputs`: Outputs ejecutados

**Output**: Array de mensajes con formato:
1. Todos los mensajes anteriores
2. Mensaje `assistant` con `tool_calls`
3. Mensajes `tool` con outputs

**Normalización**:
- Soporta ambos formatos de tool_calls:
  - Formato API: `{ id, function: {name, arguments} }`
  - Formato interno: `{ id, name, arguments }`

---

## Testing

### Escenario 1: Bot Sin Tools (Baseline)

**Config**:
```
Bot: Test Bot
Model: gpt-4-turbo
Tools: []
Feature Flag: ON (NEW architecture)
```

**Expected**: ✅ `chat_completions()` directo (sin loop)

**Resultado**: ✅ PASS

---

### Escenario 2: Bot Con Tools (1 Ronda)

**Config**:
```
Bot: Weather Bot
Model: gpt-4-turbo
Tools: [get_weather]
Message: "What's the weather in Madrid?"
Feature Flag: ON
```

**Expected**:
1. Ronda 1: Model llama `get_weather({"city":"Madrid"})`
2. Execute: `get_weather` callback → `{"temp":20,"condition":"sunny"}`
3. Ronda 2: Model genera respuesta final con data
4. Return: "The weather in Madrid is 20°C and sunny"

**Log en BD**:
```sql
SELECT * FROM wp_aichat_tool_calls WHERE request_uuid = '...';

| round | tool_name   | arguments_json        | output_excerpt                       | duration_ms |
|-------|-------------|-----------------------|--------------------------------------|-------------|
| 1     | get_weather | {"city":"Madrid"}     | {"temp":20,"condition":"sunny"}      | 150         |
```

**Resultado**: ⏳ PENDING (requiere testing manual)

---

### Escenario 3: Multi-Ronda (2+ Tools)

**Config**:
```
Bot: Assistant
Model: gpt-4-turbo
Tools: [get_weather, search_web, calculate]
Message: "What's the weather in Madrid and how many days until Christmas?"
Feature Flag: ON
```

**Expected**:
1. Ronda 1: Model llama `get_weather` + `calculate`
2. Execute: Ambos tools
3. Ronda 2: Model genera respuesta con ambos resultados
4. Return: Respuesta combinada

**Resultado**: ⏳ PENDING

---

### Escenario 4: Límite de Rondas

**Config**:
```
Bot: Complex Bot
Tools: [tool_A, tool_B, tool_C]
Max Rounds: 2
Message: (genera > 2 rondas de tool calls)
```

**Expected**:
1. Ronda 1: Tool calls
2. Ronda 2: Tool calls (límite alcanzado)
3. Return: Mensaje parcial + warning en logs

**Resultado**: ⏳ PENDING

---

## Debug Logging

### Logs Generados

**Inicio de loop**:
```
[OpenAI Provider][abc12345] Starting multi-round loop | max_rounds=5 | tools=3
```

**Cada ronda**:
```
[OpenAI Provider][abc12345] Round 1/5 | messages=2
```

**Ejecución de tool**:
```
[AIChat Tools] Executed: get_weather | 150ms | args_len=18
```

**Límite alcanzado**:
```
[OpenAI Provider][abc12345] Reached max_rounds limit with pending tool calls
```

---

## Limitaciones Conocidas

### 1. GPT-5 Responses API Sin Tools

**Estado**: ❌ NO IMPLEMENTADO

**Razón**: La Responses API tiene un modelo stateful completamente diferente:
- Usa `previous_response_id` en vez de mensajes acumulativos
- Formato de tool calls: `output[].type='function_call'`
- Formato de tool outputs: `input[].type='function_call_output'`

**Workaround**: Usar legacy mode para GPT-5 con tools

**Solución futura**: Implementar `chat_responses_with_tools()` en PASO 4

---

### 2. Claude Provider Sin Tools

**Estado**: ❌ NO IMPLEMENTADO

**Razón**: El Claude provider aún no tiene soporte de tools

**Próximo paso**: Aplicar el mismo patrón (trait + loop) en Claude

---

## Compatibilidad

### Backward Compatibility

- ✅ **Legacy mode**: 100% intacto (no afectado)
- ✅ **NEW mode sin tools**: Sin cambios (usa `chat_completions()` directo)
- ✅ **NEW mode con tools**: Nueva funcionalidad (antes no funcionaba)

### Feature Parity vs Legacy

| Feature | Legacy | NEW Architecture |
|---------|--------|------------------|
| **Chat Completions Tools** | ✅ | ✅ **NUEVO** |
| **Responses API Tools** | ✅ | ❌ (pendiente) |
| **Claude Tools** | ❌ | ❌ (ambos pendientes) |
| **Max Rounds Filter** | ✅ | ✅ |
| **Tool Logging BD** | ✅ | ✅ |
| **Error Handling** | ✅ | ✅ |

---

## Métricas de Código

### Nuevas Líneas

| Archivo | Líneas Añadidas | Líneas Totales |
|---------|-----------------|----------------|
| `trait-aichat-tool-execution.php` | 174 (nuevo) | 174 |
| `class-openai-provider.php` | +168 | 672 |
| **TOTAL** | **+342** | **846** |

### Complejidad

- **Trait**: 3 métodos (baja complejidad)
- **Provider**: +2 métodos (complejidad media - loop con branches)

---

## Próximos Pasos

### Alta Prioridad

1. **Testing Manual**:
   - [ ] Test bot con 1 tool (weather)
   - [ ] Test bot con múltiples tools
   - [ ] Test límite de rondas
   - [ ] Validar logs en BD

2. **GPT-5 Responses Tools**:
   - [ ] Implementar `chat_responses_with_tools()`
   - [ ] Manejar `previous_response_id` stateful
   - [ ] Parsear `function_call` output format
   - [ ] Testing con gpt-5-nano + tools

### Media Prioridad

3. **Claude Provider Tools**:
   - [ ] Añadir trait a Claude provider
   - [ ] Implementar `chat_with_tools()`
   - [ ] Manejar formato `tool_use` de Claude
   - [ ] Testing con claude-3-5-sonnet

4. **Optimizaciones**:
   - [ ] Cache de registered tools (evitar lookup cada ronda)
   - [ ] Streaming support (si aplica)
   - [ ] Performance benchmarking

### Baja Prioridad

5. **Features Avanzadas**:
   - [ ] Tool choice control ('auto', 'none', specific tool)
   - [ ] Parallel tool calling
   - [ ] Tool result validation
   - [ ] Custom tool timeout

---

## Checklist de Completitud

- [x] Trait creado y funcional
- [x] OpenAI Provider usando trait
- [x] Método `chat_completions_with_tools()` implementado
- [x] Método `append_openai_tool_messages()` implementado
- [x] Router `chat()` actualizado
- [x] Debug logging comprehensivo
- [x] Zero syntax errors
- [x] Documentación completa
- [ ] Testing manual completo (PENDING)
- [ ] GPT-5 tools support (PENDING - PASO 4)
- [ ] Claude tools support (PENDING - PASO 4)

---

## Referencias

- **Legacy Implementation**: `class-aichat-ajax.php` líneas 592-683 (Chat Completions)
- **Responses API**: `class-aichat-ajax.php` líneas 1910-2140
- **Tool Registration**: `aichat_get_registered_tools()` en plugin code
- **OpenAI Docs**: https://platform.openai.com/docs/guides/function-calling

---

**Autor**: AI Assistant  
**Fecha**: 2025-11-04  
**Versión**: 2.0.0-beta (PASO 3.5 completado)  
**Revisado**: Pendiente testing manual
