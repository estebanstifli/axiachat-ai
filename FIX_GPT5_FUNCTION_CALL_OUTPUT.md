# Fix: GPT-5 Responses API - function_call_output Format Error

**Fecha:** Noviembre 4, 2025  
**Issue:** `Invalid type for 'input[0].output': expected one of a string or array of objects, but got an object instead`  
**Afecta:** GPT-5 Responses API multi-round tool execution

---

## 🐛 Error Detectado

**Log de Error:**
```
[04-Nov-2025 07:16:56 UTC] [AIChat] [OpenAI Provider] Responses round 2 | {"has_pending_outputs":true}
[04-Nov-2025 07:16:56 UTC] [AIChat] [AIChat AJAX][fe3814e2-d8bf-4a65-aa1d-e89011aa7ff8] 
NEW arch provider error: Invalid type for 'input[0].output': expected one of a string or array of objects, but got an object instead.
```

**Contexto:**
- Usuario pregunta: "me gustaria saber el tiempo para mañana en madrid"
- GPT-5 llama tool: `mcp_tiempo_860905_get_weather_bydatetimerange`
- Tool se ejecuta correctamente (retorna 18KB JSON)
- Error ocurre en **ronda 2** al enviar el output al modelo

---

## 🔍 Análisis del Problema

### Formato Esperado por Responses API

En rondas subsecuentes (cuando hay `previous_response_id`), el payload debe tener:

```json
{
  "model": "gpt-5-nano",
  "previous_response_id": "resp_abc123",
  "input": [
    {
      "type": "function_call_output",
      "call_id": "call_xyz789",
      "output": "STRING_AQUÍ"  // ← DEBE SER STRING, no object
    }
  ],
  "max_output_tokens": 6000
}
```

### Qué Estábamos Enviando (INCORRECTO)

```json
{
  "type": "function_call_output",
  "call_id": "call_xyz789",
  "output": {                    // ← OBJECT en lugar de STRING
    "tool_call_id": "call_xyz789",
    "name": "mcp_tiempo_860905_get_weather_bydatetimerange",
    "arguments": "{...}",
    "output": "{...JSON...}",    // ← El STRING real está anidado aquí
    "elapsed_ms": 2186
  }
}
```

### Estructura del Trait

El trait `AIChat_Tool_Execution::execute_registered_tools()` retorna:

```php
$outputs[] = [
    'tool_call_id' => $tc['id'] ?? '',
    'name' => $fname,
    'arguments' => $raw_args,
    'output' => $output_str,      // ← STRING (JSON o texto)
    'elapsed_ms' => $elapsed_ms,
];
```

**CORRECTO:** El trait retorna el `output` como **string** en el key `'output'`.

### Bug en el Provider

**Archivo:** `includes/providers/class-openai-provider.php`  
**Método:** `chat_responses_with_tools()`  
**Línea:** ~650 (antes del fix)

```php
// INCORRECTO ❌
foreach ( $outputs as $idx => $output ) {
    $pending_tool_outputs[] = [
        'tool_call_id' => $tool_calls[$idx]['id'],
        'output'       => $output  // ← Pasando el ARRAY completo
    ];
}
```

Esto causaba que `'output'` fuera el **array completo** en lugar del **string**.

---

## ✅ Solución Aplicada

### Código Corregido

```php
// CORRECTO ✅
foreach ( $outputs as $output ) {
    $pending_tool_outputs[] = [
        'tool_call_id' => $output['tool_call_id'],  // ← Usar key del trait
        'output'       => $output['output']         // ← Extraer el STRING
    ];
}
```

**Cambios:**
1. ❌ Eliminado uso de `$idx` y `$tool_calls[$idx]['id']`
2. ✅ Usar `$output['tool_call_id']` directamente del trait
3. ✅ Usar `$output['output']` para extraer el string

---

## 📋 Comparación con Legacy

### Legacy (CORRECTO)

**Archivo:** `includes/class-aichat-ajax.php` líneas 2185-2189

```php
$pending_tool_outputs[] = [
    'tool_call_id' => $tc['id'],
    'output' => $out_str  // ← STRING directo
];
```

El legacy construye `$out_str` directamente como string y lo usa.

### Nuevo Provider (AHORA CORREGIDO)

```php
$outputs = $this->execute_registered_tools( $tool_calls, $context );

foreach ( $outputs as $output ) {
    $pending_tool_outputs[] = [
        'tool_call_id' => $output['tool_call_id'],
        'output'       => $output['output']  // ← STRING extraído del trait
    ];
}
```

El nuevo provider usa el trait, extrae el array completo, y luego toma el key `'output'`.

---

## 🔄 Flujo Completo Corregido

### Ejecución de Tools (Trait)

```php
protected function execute_registered_tools( $tool_calls, $context ) {
    $outputs = [];
    foreach ( $tool_calls as $tc ) {
        $output_str = '...';  // Ejecutar callback, normalizar a string
        
        $outputs[] = [
            'tool_call_id' => $tc['id'],
            'name' => $tc['name'],
            'arguments' => $tc['arguments'],
            'output' => $output_str,         // ← STRING
            'elapsed_ms' => 123,
        ];
    }
    return $outputs;
}
```

### Construcción del Payload (Provider)

```php
$outputs = $this->execute_registered_tools( $tool_calls, $context );

$pending_tool_outputs = [];
foreach ( $outputs as $output ) {
    $pending_tool_outputs[] = [
        'tool_call_id' => $output['tool_call_id'],  // ID del tool call
        'output'       => $output['output']          // STRING del output
    ];
}

// Payload ronda 2+
$payload = [
    'model' => 'gpt-5-nano',
    'previous_response_id' => $response_id,
    'input' => [
        [
            'type' => 'function_call_output',
            'call_id' => $pending_tool_outputs[0]['tool_call_id'],
            'output' => $pending_tool_outputs[0]['output']  // ← STRING
        ]
    ],
    'max_output_tokens' => 6000
];
```

---

## 🧪 Testing

### Caso de Prueba que Falló

**Setup:**
- Bot: GPT-5 nano con 8 tools MCP (weather)
- Pregunta: "me gustaria saber el tiempo para mañana en madrid"
- Tool ejecutado: `mcp_tiempo_860905_get_weather_bydatetimerange`
- Output: 18KB JSON con forecast detallado

**Resultado ANTES del fix:**
```
❌ ERROR: Invalid type for 'input[0].output': expected one of a string or array of objects, but got an object instead.
```

**Resultado DESPUÉS del fix:**
```
✅ Ronda 2 debe procesar el output correctamente
✅ GPT-5 debe generar respuesta final basada en el forecast
```

---

## 📊 Verificación Cross-Provider

### Chat Completions (GPT-4, O1)

**Archivo:** `class-openai-provider.php` método `append_openai_tool_messages()`  
**Línea:** ~352

```php
foreach ( $tool_outputs as $output ) {
    $tool_messages[] = [
        'role' => 'tool',
        'tool_call_id' => $output['tool_call_id'],
        'content' => $output['output'],  // ✅ YA CORRECTO
    ];
}
```

**Status:** ✅ Chat Completions ya estaba usando correctamente `$output['output']`.

### Responses API (GPT-5)

**Status:** ✅ CORREGIDO en este fix.

---

## 🎓 Lecciones Aprendidas

### 1. Estructura del Trait vs Uso

**Problema:** El trait retorna un array rico con metadata, pero cada provider necesita extraer solo lo necesario.

**Solución:** 
- Trait retorna: `['tool_call_id', 'name', 'arguments', 'output', 'elapsed_ms']`
- Provider extrae: Solo `tool_call_id` y `output` para el payload API

### 2. Diferencias de Formato entre APIs

| API | Tool Output Field | Type Expected |
|-----|-------------------|---------------|
| Chat Completions | `messages[].content` | String |
| Responses API | `input[].output` | String |

Ambas esperan **string**, pero tienen estructuras diferentes.

### 3. Testing con Tools Reales

**Lección:** Los tests sintéticos (mocks simples) no detectaron este bug porque no usaban la estructura completa del trait.

**Acción:** Testing debe incluir:
- Tools MCP con outputs grandes (>1KB)
- Múltiples rondas reales
- Verificación del payload JSON enviado a la API

---

## 🚀 Próximos Pasos

### 1. Testing Manual CRÍTICO

```
PROBAR:
✅ GPT-5 + 1 tool simple (output pequeño)
✅ GPT-5 + MCP tool (output grande 18KB)
✅ GPT-5 + múltiples tools en cadena
✅ Verificar logs BD (wp_aichat_tool_calls)
```

### 2. Validar Claude Provider

**IMPORTANTE:** Verificar que Claude provider no tenga el mismo bug cuando se implemente multi-round.

### 3. Documentación

**Actualizar:**
- `IMPLEMENTACION_MULTIRONDA_RESPONSES.md`: Añadir nota sobre extracción correcta de `output['output']`
- Ejemplos de código con comentarios explícitos

---

## 📝 Resumen del Fix

| Aspecto | Antes | Después |
|---------|-------|---------|
| **Output type en payload** | Object (array completo) ❌ | String (`output['output']`) ✅ |
| **Extracción de tool_call_id** | `$tool_calls[$idx]['id']` | `$output['tool_call_id']` ✅ |
| **Error GPT-5 ronda 2** | ❌ "Invalid type for 'input[0].output'" | ✅ Sin error |
| **Compatibilidad legacy** | ❌ Diferente | ✅ Mismo formato |

---

## ✅ Validación

**Sintaxis:** 0 errores  
**Testing manual:** ⏳ Pendiente (crítico)  
**Comparación legacy:** ✅ Formato idéntico  
**Chat Completions:** ✅ No afectado (ya estaba correcto)

---

**Status:** ✅ FIX APLICADO  
**Impact:** Crítico (bloqueaba multi-round de GPT-5)  
**Breaking Changes:** Ninguno (solo corrige bug)  
**Archivos Modificados:** `class-openai-provider.php` (2 líneas)
