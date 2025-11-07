# Implementación Multi-Ronda Responses API (GPT-5)

**Fecha:** 2024
**Objetivo:** Soporte completo de tool calling multi-ronda para la API de Responses (GPT-5) en el provider OpenAI.

---

## 📋 Contexto

La API de **Responses** de OpenAI es utilizada por los modelos GPT-5 y tiene un patrón de interacción **stateful** diferente al de Chat Completions. Mientras Chat Completions requiere reenviar todo el historial de mensajes en cada ronda, Responses API usa un identificador de sesión (`previous_response_id`) para mantener el contexto.

### Diferencias Clave: Responses API vs Chat Completions

| Aspecto | Chat Completions | Responses API |
|---------|------------------|---------------|
| **Estado** | Stateless (reenvía mensajes) | Stateful (`previous_response_id`) |
| **Endpoint** | `/v1/chat/completions` | `/v1/responses` |
| **Parámetro max tokens** | `max_tokens` | `max_output_tokens` |
| **Parámetro temperature** | ✅ Soportado | ❌ **NO soportado** |
| **System message** | En array `messages[]` | Campo `instructions` separado |
| **User input** | En array `messages[]` | Campo `input[]` con estructura específica |
| **Tool calls** | `choices[].message.tool_calls[]` | `output[].type='function_call'` |
| **Tool results** | Mensaje `role=tool` | `input[].type='function_call_output'` |

> **⚠️ IMPORTANTE:** Los modelos GPT-5 en Responses API **NO soportan** los siguientes parámetros:
> - `temperature` - Genera error: "Unsupported parameter: 'temperature' is not supported with this model"
> - Estos parámetros deben ser omitidos del payload completamente

---

## 🎯 Implementación

### 1. Router Actualizado

**Archivo:** `includes/providers/class-openai-provider.php`  
**Método:** `chat()`

```php
// Detectar si es modelo GPT-5 (Responses API)
if ( $this->is_gpt5_model( $model ) ) {
    if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
        aichat_log_debug('[OpenAI Provider] Routing to Responses API (GPT-5 model)', [
            'model' => $model, 
            'has_tools' => $has_tools
        ], true);
    }
    
    if ( $has_tools ) {
        // Multi-ronda con tools en Responses API
        return $this->chat_responses_with_tools( $messages, $params );
    }
    
    return $this->chat_responses( $messages, $params );
}
```

**Cambios:**
- ✅ Eliminado el WARNING temporal sobre "tools not yet implemented"
- ✅ Llamada directa a `chat_responses_with_tools()` cuando hay tools

---

### 2. Método Principal: `chat_responses_with_tools()`

**Propósito:** Implementar el loop multi-ronda con soporte de tools para Responses API.

#### Patrón Stateful

```php
protected function chat_responses_with_tools( $messages, $params ) {
    // 1. INICIALIZACIÓN
    $model = $params['model'] ?? 'gpt-5-turbo';
    $max_tokens = $params['max_output_tokens'] ?? 2048;
    $tools = isset( $params['tools'] ) && is_array( $params['tools'] ) ? $params['tools'] : [];
    
    // NOTA: GPT-5 Responses API NO soporta 'temperature'
    
    // Si no hay tools, usar método simple
    if ( empty( $tools ) ) {
        return $this->chat_responses( $messages, $params );
    }
    
    // 2. CONVERTIR MENSAJES
    list( $instructions, $input_text ) = $this->convert_messages_to_responses_format( $messages );
    
    // 3. NORMALIZAR TOOLS
    $normalized_tools = $this->normalize_tools_for_responses( $tools );
    
    // 4. VARIABLES DEL LOOP
    $max_rounds = $this->get_max_rounds( $params );  // Del trait
    $round = 1;
    $response_id = null;  // Clave: comienza en null
    $pending_tool_outputs = [];
    $final_text = '';
    
    // 5. LOOP MULTI-RONDA
    while ( $round <= $max_rounds ) {
        // PRIMERA RONDA vs SUBSECUENTES
        if ( $response_id === null ) {
            // PRIMERA RONDA: enviar todo
            $payload = [
                'model'              => $model,
                'instructions'       => $instructions,
                'input'              => [
                    [
                        'role'    => 'user',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => $input_text
                            ]
                        ]
                    ]
                ],
                'max_output_tokens'  => $max_tokens,
                // NO incluir 'temperature' - GPT-5 no lo soporta
                'tools'              => $normalized_tools,
                'tool_choice'        => 'auto'
            ];
        } else {
            // RONDAS SUBSECUENTES: solo previous_response_id + outputs
            $fco_items = [];
            foreach ( $pending_tool_outputs as $to ) {
                $fco_items[] = [
                    'type'    => 'function_call_output',
                    'call_id' => $to['tool_call_id'],
                    'output'  => $to['output']
                ];
            }
            
            $payload = [
                'model'              => $model,
                'previous_response_id' => $response_id,  // STATEFUL
                'input'              => $fco_items,
                'max_output_tokens'  => $max_tokens
            ];
        }
        
        // 6. LLAMADA API
        $res = wp_remote_post(
            'https://api.openai.com/v1/responses',
            [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $this->config['api_key']
                ],
                'body'    => wp_json_encode( $payload ),
                'timeout' => 60
            ]
        );
        
        // 7. MANEJO DE ERRORES
        if ( is_wp_error( $res ) ) {
            return [ 'error' => $res->get_error_message() ];
        }
        
        $code = wp_remote_retrieve_response_code( $res );
        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        
        if ( $code >= 400 ) {
            $msg = isset( $body['error']['message'] ) 
                ? $body['error']['message'] 
                : __( 'OpenAI Responses API error.', 'axiachat-ai' );
            return [ 'error' => $msg ];
        }
        
        // 8. GUARDAR RESPONSE_ID
        $response_id = $body['id'] ?? null;  // Para siguiente ronda
        
        // 9. PARSEAR OUTPUT
        $text = '';
        $tool_calls = [];
        
        if ( isset( $body['output'] ) && is_array( $body['output'] ) ) {
            foreach ( $body['output'] as $output_item ) {
                $type = $output_item['type'] ?? '';
                
                if ( $type === 'message' ) {
                    // Extraer texto
                    if ( isset( $output_item['content'] ) && is_array( $output_item['content'] ) ) {
                        foreach ( $output_item['content'] as $content_item ) {
                            if ( isset( $content_item['type'] ) && $content_item['type'] === 'output_text' ) {
                                $text .= $content_item['text'] ?? '';
                            }
                        }
                    }
                } elseif ( $type === 'function_call' ) {
                    // Extraer tool call
                    $tool_calls[] = [
                        'id'        => $output_item['call_id'] ?? '',
                        'name'      => $output_item['name'] ?? '',
                        'arguments' => $output_item['arguments'] ?? ''
                    ];
                }
            }
        }
        
        // 10. ACUMULAR TEXTO
        if ( $text !== '' ) {
            $final_text .= $text;
        }
        
        // 11. SI NO HAY TOOL_CALLS, TERMINAR
        if ( empty( $tool_calls ) ) {
            break;
        }
        
        // 12. EJECUTAR TOOLS (usando trait)
        $context = [
            'bot_id'          => $params['bot_id'] ?? 0,
            'conversation_id' => $params['conversation_id'] ?? 0,
            'provider'        => 'openai'
        ];
        
        $outputs = $this->execute_registered_tools( $tool_calls, $context );
        
        // 13. LOG
        $this->log_tool_executions( $tool_calls, $outputs, $round, $context );
        
        // 14. PREPARAR PENDING_TOOL_OUTPUTS
        $pending_tool_outputs = [];
        foreach ( $outputs as $idx => $output ) {
            $pending_tool_outputs[] = [
                'tool_call_id' => $tool_calls[$idx]['id'],
                'output'       => $output
            ];
        }
        
        $round++;
    }
    
    // 15. RESULTADO FINAL
    if ( $final_text === '' ) {
        return [ 'error' => __( 'Empty response from OpenAI Responses API after tool execution.', 'axiachat-ai' ) ];
    }
    
    return [
        'message' => $final_text,
        'model'   => $body['model'] ?? $model
    ];
}
```

#### Líneas de Código: ~200

---

### 3. Métodos Auxiliares

#### 3.1 `normalize_tools_for_responses()`

Convierte tools de formato Chat Completions a formato Responses API.

```php
protected function normalize_tools_for_responses( $tools ) {
    $normalized = [];
    
    foreach ( $tools as $tool ) {
        $type = $tool['type'] ?? 'function';
        
        if ( $type === 'function' ) {
            $func = $tool['function'] ?? [];
            $normalized[] = [
                'type'        => 'function',
                'name'        => $func['name'] ?? '',
                'description' => $func['description'] ?? '',
                'parameters'  => $func['parameters'] ?? (object)[]
            ];
        } elseif ( $type === 'web_search' ) {
            // Web search tool (si existe)
            $normalized[] = [
                'type'    => 'web_search',
                'filters' => $tool['filters'] ?? []
            ];
        }
    }
    
    return $normalized;
}
```

**Transformación:**

Chat Completions format:
```json
{
  "type": "function",
  "function": {
    "name": "get_weather",
    "description": "Get weather data",
    "parameters": {...}
  }
}
```

Responses API format:
```json
{
  "type": "function",
  "name": "get_weather",
  "description": "Get weather data",
  "parameters": {...}
}
```

**Diferencia:** Nivel `function` eliminado, propiedades elevadas al root.

---

#### 3.2 `convert_messages_to_responses_format()`

Convierte array de mensajes a formato `instructions` + `input_text`.

```php
protected function convert_messages_to_responses_format( $messages ) {
    $instructions = '';
    $conv_parts = [];
    
    foreach ( $messages as $m ) {
        if ( ! is_array( $m ) ) {
            continue;
        }
        
        $role = $m['role'] ?? '';
        $content = is_string( $m['content'] ?? '' ) ? (string) $m['content'] : '';
        
        if ( $content === '' ) {
            continue;
        }
        
        if ( $role === 'system' ) {
            $instructions .= ( $instructions ? "\n\n" : '' ) . $content;
        } else {
            $conv_parts[] = strtoupper( $role ) . ": " . $content;
        }
    }
    
    if ( $instructions === '' ) {
        $instructions = 'You are a helpful assistant.';
    }
    
    $input_text = trim( implode( "\n\n", $conv_parts ) );
    if ( $input_text === '' ) {
        $input_text = 'Hello';
    }
    
    return [ $instructions, $input_text ];
}
```

**Transformación:**

Input:
```php
[
  ['role' => 'system', 'content' => 'You are a bot'],
  ['role' => 'user', 'content' => 'Hello'],
  ['role' => 'assistant', 'content' => 'Hi there'],
  ['role' => 'user', 'content' => 'What weather?']
]
```

Output:
```php
[
  'You are a bot',  // instructions
  "USER: Hello\n\nASSISTANT: Hi there\n\nUSER: What weather?"  // input_text
]
```

---

## 🔄 Flujo Completo de Multi-Ronda

### Primera Ronda

**Request:**
```json
{
  "model": "gpt-5-turbo",
  "instructions": "You are a weather bot",
  "input": [
    {
      "role": "user",
      "content": [
        {"type": "input_text", "text": "What's the weather in Madrid?"}
      ]
    }
  ],
  "tools": [
    {
      "type": "function",
      "name": "get_weather",
      "description": "Get weather for a city",
      "parameters": {...}
    }
  ],
  "tool_choice": "auto",
  "max_output_tokens": 2048
}
```

**Response:**
```json
{
  "id": "resp_abc123",
  "model": "gpt-5-turbo",
  "output": [
    {
      "type": "function_call",
      "call_id": "call_xyz789",
      "name": "get_weather",
      "arguments": "{\"city\":\"Madrid\"}"
    }
  ],
  "status": "in_progress"
}
```

**Acción:**
1. Parsear `output[].type='function_call'`
2. Ejecutar tool via `execute_registered_tools()` → `{"temp": 22, "condition": "sunny"}`
3. Preparar `pending_tool_outputs`
4. Guardar `response_id = "resp_abc123"`
5. Continuar ronda 2

---

### Segunda Ronda

**Request:**
```json
{
  "model": "gpt-5-turbo",
  "previous_response_id": "resp_abc123",
  "input": [
    {
      "type": "function_call_output",
      "call_id": "call_xyz789",
      "output": "{\"temp\": 22, \"condition\": \"sunny\"}"
    }
  ],
  "max_output_tokens": 2048
}
```

**Response:**
```json
{
  "id": "resp_def456",
  "model": "gpt-5-turbo",
  "output": [
    {
      "type": "message",
      "content": [
        {
          "type": "output_text",
          "text": "The weather in Madrid is sunny with a temperature of 22°C."
        }
      ]
    }
  ],
  "status": "completed"
}
```

**Acción:**
1. Parsear `output[].type='message'` → texto final
2. No hay `function_call` → break loop
3. Retornar `final_text`

---

## 🧩 Integración con Trait

El método `chat_responses_with_tools()` usa el trait `AIChat_Tool_Execution`:

```php
use AIChat_Tool_Execution;
```

**Métodos del trait utilizados:**

1. **`execute_registered_tools($tool_calls, $context)`**
   - Ejecuta callbacks registrados
   - Maneja errores (try/catch)
   - Trunca outputs largos (>4000 chars)
   - Retorna array de outputs

2. **`log_tool_executions($tool_calls, $outputs, $round, $context)`**
   - Registra en tabla `wp_aichat_tool_calls`
   - Guarda: tool_name, arguments, output, round, timestamp, bot_id, conversation_id

3. **`get_max_rounds($params)`**
   - Lee configuración vía filtro
   - Default: 5 rondas
   - Filtro: `apply_filters('aichat_tools_max_rounds', 5)`

**Ventajas:**
- ✅ Código reutilizable entre providers
- ✅ Logging centralizado
- ✅ Configuración unificada
- ✅ Manejo de errores consistente

---

## 📊 Comparativa de Implementaciones

| Aspecto | Chat Completions Multi-Round | Responses API Multi-Round |
|---------|------------------------------|---------------------------|
| **Método** | `chat_completions_with_tools()` | `chat_responses_with_tools()` |
| **Estado** | Stateless (reenvía `$messages`) | Stateful (`previous_response_id`) |
| **Loop** | Acumula mensajes cada ronda | Solo envía outputs de tools |
| **Tool calls** | `choices[].message.tool_calls[]` | `output[].type='function_call'` |
| **Tool results** | `append_openai_tool_messages()` | `function_call_output` en `input[]` |
| **Payload size** | Crece cada ronda | Constante (solo outputs) |
| **Complejidad** | Media (acumulación) | Alta (stateful IDs) |
| **Líneas** | ~170 | ~200 |

**Ventaja Responses:** Menos datos transmitidos (stateful).  
**Ventaja Chat Completions:** Más simple de debuggear (todo el contexto visible).

---

## 🧪 Testing

### Casos de Prueba

#### Test 1: GPT-5 sin tools
```php
$params = [
    'model' => 'gpt-5-turbo',
    'tools' => []
];
// Expected: Llamada a chat_responses() (método simple)
```

#### Test 2: GPT-5 con 1 tool, sin ejecución
```php
$params = [
    'model' => 'gpt-5-turbo',
    'tools' => [
        ['type' => 'function', 'function' => ['name' => 'get_time', ...]]
    ]
];
// Expected: 1 ronda, respuesta directa sin tool_calls
```

#### Test 3: GPT-5 con 1 tool, 1 ejecución
```php
$params = [
    'model' => 'gpt-5-turbo',
    'tools' => [
        ['type' => 'function', 'function' => ['name' => 'get_weather', ...]]
    ]
];
// User: "What's weather in Madrid?"
// Expected: 
//   Ronda 1: function_call
//   Tool execution: {"temp": 22, "condition": "sunny"}
//   Ronda 2: message con respuesta final
```

#### Test 4: GPT-5 con múltiples tools, cadena
```php
$params = [
    'model' => 'gpt-5-turbo',
    'tools' => [
        ['type' => 'function', 'function' => ['name' => 'get_location', ...]],
        ['type' => 'function', 'function' => ['name' => 'get_weather', ...]]
    ]
];
// User: "What's weather where I am?"
// Expected:
//   Ronda 1: call get_location
//   Ronda 2: call get_weather(location from round 1)
//   Ronda 3: message final
```

#### Test 5: Límite de rondas
```php
$params = [
    'model' => 'gpt-5-turbo',
    'tools' => [...],
    'max_rounds' => 3
];
// Simular loop infinito (mock tool que siempre pide más)
// Expected: Stop después de ronda 3, retornar último texto acumulado
```

---

## 🐛 Debug

### Logs Generados

Con `define('AICHAT_DEBUG', true);`:

```
[AIChat] [OpenAI Provider] Routing to Responses API (GPT-5 model)
[AIChat] [OpenAI Provider] Responses multi-round START
    model: gpt-5-turbo
    max_rounds: 5
    tools_count: 2

[AIChat] [OpenAI Provider] Responses round 1
    has_pending_outputs: false
    
[AIChat] [Tool Execution] Executing tool: get_weather
    arguments: {"city":"Madrid"}
    output: {"temp":22,"condition":"sunny"}
    
[AIChat] [OpenAI Provider] Responses round 2
    has_pending_outputs: true
    
[AIChat] [OpenAI Provider] Responses multi-round END (no tool_calls)
    round: 2
    final_text_length: 58
```

### Puntos de Verificación

1. **Router:** ¿Se detecta GPT-5 correctamente?
2. **Normalización:** ¿Tools convertidos al formato correcto?
3. **Primera ronda:** ¿Payload incluye `tools` y `tool_choice`?
4. **Response_id:** ¿Se guarda y reutiliza en ronda 2?
5. **Parsing:** ¿Se extraen correctamente `function_call` y `message`?
6. **Ejecución:** ¿Trait ejecuta tools sin errores?
7. **Loop:** ¿Termina cuando no hay tool_calls?
8. **BD:** ¿Se registran logs en `wp_aichat_tool_calls`?

---

## 📝 Checklist de Implementación

- [x] Router actualizado (`chat()`)
- [x] Método `chat_responses_with_tools()` implementado
- [x] Método `normalize_tools_for_responses()` implementado
- [x] Método `convert_messages_to_responses_format()` implementado
- [x] Integración con trait `AIChat_Tool_Execution`
- [x] Manejo de errores HTTP
- [x] Parsing de `output[]` para `function_call` y `message`
- [x] Loop stateful con `previous_response_id`
- [x] Acumulación de texto entre rondas
- [x] Logs de debug
- [ ] Testing con bot real + tools
- [ ] Testing con múltiples rondas
- [ ] Verificación de logs en BD
- [ ] Documentación de usuario

---

## 🚀 Próximos Pasos

1. **Testing Manual:**
   - Crear bot GPT-5 con tool `get_weather`
   - Probar pregunta: "¿Qué tiempo hace en Madrid?"
   - Verificar logs en `wp_aichat_tool_calls`

2. **Optimizaciones:**
   - Parallel tool execution (si API lo soporta)
   - Streaming support para Responses API
   - Cache de `response_id` para retry

3. **Claude Provider:**
   - Aplicar mismo patrón trait
   - Implementar `chat_with_tools()` para Claude
   - Adaptar formato de tool_calls de Claude

4. **Documentación:**
   - Actualizar README con ejemplos GPT-5
   - Crear guía de migración legacy → nuevo
   - Documentar diferencias Responses vs Chat Completions

---

## 📚 Referencias

- [OpenAI Responses API Docs](https://platform.openai.com/docs/api-reference/responses)
- [Chat Completions vs Responses](https://platform.openai.com/docs/guides/responses)
- Legacy Implementation: `includes/class-aichat-ajax.php` lines 1850-2140
- Trait Implementation: `includes/traits/trait-aichat-tool-execution.php`

---

**Status:** ✅ IMPLEMENTACIÓN COMPLETA  
**Testing:** ⏳ PENDIENTE  
**Próxima acción:** Testing con bot real GPT-5 + tools
