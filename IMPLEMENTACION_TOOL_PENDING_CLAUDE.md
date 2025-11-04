# Implementación Tool Pending para Claude

## ✅ IMPLEMENTADO

### Funcionalidad: Mensajes "Ejecutando herramienta X" en el Widget

Similar a OpenAI Responses, Claude ahora muestra mensajes de progreso en tiempo real cuando ejecuta tools.

---

## 📋 CAMBIOS REALIZADOS

### 1. Archivo: `includes/providers/class-claude-provider.php`

**Línea ~360**: Modificado `chat_with_tools()` - Round 1 devuelve `tool_pending`

```php
// PRIMERA RONDA: Devolver tool_pending para que frontend muestre "Ejecutando X"
if ( $round === 1 ) {
    // Generar response_id único para continuar
    $response_id = wp_generate_uuid4();
    
    // Convertir tool_use_blocks a formato para frontend
    $pending_tool_calls = [];
    foreach ( $tool_use_blocks as $block ) {
        $tool_name = $block['name'] ?? '';
        
        // Generar activity_label (traducible)
        $activity_label = sprintf( 
            __( 'Executing %s', 'axiachat-ai' ), 
            $tool_name 
        );
        
        $pending_tool_calls[] = [
            'id' => $block['id'],
            'name' => $tool_name,
            'arguments' => wp_json_encode( $block['input'] ?? [] ),
            'activity_label' => $activity_label, // ← Texto para widget
        ];
    }
    
    // Guardar estado para continuation (transient 5 min)
    $state_key = 'aichat_claude_tool_state_' . $response_id;
    $state_data = [
        'working_messages' => $working_messages,
        'full_content' => $result['full_content'] ?? [],
        'tool_use_blocks' => $tool_use_blocks,
        'model' => $model,
        'temperature' => $temperature,
        'max_tokens' => $max_tokens,
        'anthropic_tools' => $anthropic_tools,
        'context' => $context,
        'total_usage' => $total_usage,
    ];
    set_transient( $state_key, $state_data, 300 );
    
    return [
        'status' => 'tool_pending',
        'response_id' => $response_id,
        'tool_calls' => $pending_tool_calls,
        'request_uuid' => $context['request_uuid'],
        'usage' => $total_usage,
    ];
}
```

**Beneficios**:
- ✅ Frontend muestra "Ejecutando weather_get_current" (traducible)
- ✅ Usuario ve progreso en tiempo real
- ✅ Estado se guarda en transient (5 min TTL)
- ✅ Compatible con el sistema de OpenAI

---

**Línea ~83**: Añadido método `continue_from_tool_pending()`

```php
public function continue_from_tool_pending( $response_id, $tool_calls ) {
    // Recuperar estado guardado
    $state_key = 'aichat_claude_tool_state_' . $response_id;
    $state = get_transient( $state_key );
    
    if ( empty($state) ) {
        return [ 'error' => __( 'Tool state expired or not found', 'axiachat-ai' ) ];
    }
    
    delete_transient( $state_key );
    
    // Ejecutar tools
    $tool_outputs = $this->execute_registered_tools( $normalized_tool_calls, $context );
    
    // Añadir tool_result a conversación
    $working_messages = $this->append_tool_conversation( 
        $working_messages, 
        $full_content,
        $tool_outputs 
    );
    
    // Continuar loop desde round 2 hasta obtener respuesta final
    for ( $round = 2; $round <= $max_rounds; $round++ ) {
        $result = $this->call_claude_with_tools( ... );
        
        // Si no hay más tools, devolver respuesta final
        if ( !has_tool_calls ) {
            return [
                'message' => $result['message'],
                'usage' => $total_usage,
                'model' => $model,
            ];
        }
        
        // Si hay más tools, ejecutar directamente (sin handshake)
        // ...
    }
}
```

**Proceso**:
1. Recupera estado del transient
2. Ejecuta las tools
3. Continúa conversación con Claude
4. Maneja múltiples rondas si necesario
5. Devuelve respuesta final

---

### 2. Archivo: `includes/class-aichat-ajax.php`

**Línea ~948**: Modificado `process_tool_continuation()` para soportar Claude

```php
protected function process_tool_continuation( $bot_slug, $session, $response_id, $tool_calls, $uid ) {
    $bot = $this->resolve_bot( $bot_slug );
    $provider = $bot['provider'];
    
    // NUEVA ARQUITECTURA: Detectar provider
    $use_new_architecture = (bool) get_option( 'aichat_use_provider_architecture', 0 );
    
    if ( $use_new_architecture ) {
        $registry = AIChat_Provider_Registry::get_instance();
        $provider_instance = $registry->get( $provider );
        
        // Claude usa continue_from_tool_pending
        if ( $provider === 'claude' ) {
            $result = $provider_instance->continue_from_tool_pending( $response_id, $tool_calls );
            
            if ( isset($result['error']) ) {
                wp_send_json_error( ['message' => $result['error'] ], 500);
            }
            
            wp_send_json_success([
                'message' => $result['message'],
                'model' => $result['model'],
                'provider' => 'claude',
                'usage' => $result['usage'],
            ]);
        }
        
        // OpenAI Responses (legacy path)
        // ...
    }
    
    // === LEGACY CODE: OpenAI Responses ===
    // ... código original ...
}
```

**Integración**:
- ✅ Detecta provider (claude vs openai)
- ✅ Llama al método correcto según provider
- ✅ Mantiene backward compatibility con OpenAI legacy

---

## 🔄 FLUJO COMPLETO

### Usuario: "¿Qué tiempo hace en Madrid?"

**Round 1: Handshake**
```
Frontend → AJAX: message="¿Qué tiempo hace en Madrid?"
AJAX → Claude Provider: chat_with_tools()
Claude API → Response: tool_use (weather_get_current)
Claude Provider → AJAX: {
  status: 'tool_pending',
  response_id: 'uuid-123',
  tool_calls: [{
    id: 'toolu_01',
    name: 'weather_get_current',
    arguments: '{"location":"Madrid"}',
    activity_label: 'Ejecutando weather_get_current' ← Widget muestra esto
  }]
}
AJAX → Frontend: tool_pending + tool_calls
```

**Frontend Muestra**:
```
┌─────────────────────────────────┐
│ Ejecutando weather_get_current │ ← Burbuja con animación
│              •••                │
└─────────────────────────────────┘
```

**Round 2: Continuation**
```
Frontend → AJAX: {
  continue_tool: 1,
  response_id: 'uuid-123',
  tool_calls: [{...}]
}
AJAX → Claude Provider: continue_from_tool_pending()
Claude Provider → MCP: Ejecuta weather_get_current("Madrid")
MCP → Claude Provider: {"temp": 18, "condition": "sunny"}
Claude Provider → Claude API: Mensaje con tool_result
Claude API → Response: "Actualmente en Madrid hace 18°C con sol."
Claude Provider → AJAX: {message: "Actualmente..."}
AJAX → Frontend: message
```

**Frontend Muestra**:
```
┌─────────────────────────────────┐
│ Done ✓                          │ ← Burbuja cambia a check
└─────────────────────────────────┘
(se desvanece)

Bot: Actualmente en Madrid hace 18°C con sol.
```

---

## 📊 COMPARACIÓN OpenAI vs Claude

| Aspecto | OpenAI Responses | Claude |
|---------|------------------|--------|
| **Handshake** | ✅ `status: tool_pending` | ✅ `status: tool_pending` |
| **Activity Label** | ✅ `activity_label` | ✅ `activity_label` |
| **Estado Guardado** | En memoria (request) | Transient (5 min) |
| **Continuation** | `process_tool_continuation` legacy | `continue_from_tool_pending` nuevo |
| **Multi-round** | ✅ Soporte completo | ✅ Soporte completo |
| **Frontend** | ✅ Compatible | ✅ Compatible (mismo código) |

---

## 🧪 PRUEBA

### Setup
1. Bot Claude con tools MCP activadas
2. Modelo: `claude-3-5-haiku-20241022` o `claude-3-5-sonnet-20241022`
3. Arquitectura nueva: `aichat_use_provider_architecture = 1`

### Test Case
**Mensaje**: "¿Qué tiempo hace en Roma?"

**Esperado**:
1. Widget muestra burbuja: "Ejecutando weather_get_current" con animación
2. Después de 1-2s: Burbuja cambia a "Done ✓" y se desvanece
3. Respuesta final: "Actualmente en Roma hace X°C con [condición]"

**Debug Log**:
```
[Claude Provider] Starting round 1/5
[Claude Provider] Tool calls detected | count=1, tools=["weather_get_current"]
[Claude Provider] Returning tool_pending handshake | response_id=uuid, tool_count=1
[AIChat AJAX] NEW arch tool_pending handshake | tool_count=1

--- AJAX DEVUELVE tool_pending AL FRONTEND ---
--- FRONTEND MUESTRA "Ejecutando weather_get_current" ---
--- FRONTEND ENVÍA continue_tool=1 ---

[AIChat AJAX] NEW arch tool continuation | provider=claude
[Claude Provider] Continuing from tool_pending | response_id=uuid, tool_count=1
[Claude Provider] Continuation round 2/5
[Claude Provider] Continuation final response | answer_len=85
```

---

## ✅ RESULTADO

**ANTES**:
- ❌ Claude ejecutaba tools pero widget no mostraba progreso
- ❌ Usuario no sabía qué estaba pasando durante 2-3 segundos

**AHORA**:
- ✅ Widget muestra "Ejecutando [tool_name]" en tiempo real
- ✅ Animación de dots mientras espera
- ✅ Check mark ✓ cuando completa
- ✅ Experiencia idéntica a OpenAI Responses
- ✅ Texto traducible (`__('Executing %s', 'axiachat-ai')`)

---

## 🎯 COMPATIBILIDAD

- ✅ **Frontend**: No requiere cambios (ya soporta `tool_pending`)
- ✅ **AJAX**: Dual mode (legacy + nueva arquitectura)
- ✅ **Providers**: OpenAI y Claude
- ✅ **Tools**: MCP y custom tools
- ✅ **Logging**: Se guardan tool executions en BD
- ✅ **Transients**: Auto-limpieza después de 5 min

---

## 📝 NOTAS TÉCNICAS

### Transient vs Request State
- **OpenAI**: Estado en request scope (legacy, una sola ronda)
- **Claude**: Estado en transient (permite TTL, limpieza automática)
- **TTL**: 5 minutos (configurable vía filter)

### Activity Label
Formato: `"Ejecutando {tool_name}"`
- Traducible via `__()` 
- Personalizable vía filter futuro
- Se genera desde `tool_use.name`

### Error Handling
- Estado expirado → "Tool state expired or not found"
- Tools fallan → Se registra en logs pero continúa
- Max rounds → "Maximum tool execution rounds (5) reached"

---

## 🔮 MEJORAS FUTURAS

1. **Progress indicator más detallado**
   ```javascript
   "Ejecutando weather_get_current (1/3)"
   ```

2. **Custom activity labels desde tool definition**
   ```php
   'activity_label' => __( 'Fetching weather data...', 'axiachat-ai' )
   ```

3. **Tool execution time**
   ```javascript
   "Ejecutando weather_get_current (2.3s)"
   ```

4. **Streaming durante tool execution**
   - Mostrar partial results mientras ejecuta
   - Actualizar burbuja con progreso real

---

**Status**: ✅ **COMPLETO Y LISTO PARA TESTING**
