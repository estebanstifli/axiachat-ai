# Análisis Error "Overloaded" - Claude Tools

## 🔍 DIAGNÓSTICO

### Error Encontrado en el Log
```
[AIChat] [Claude Provider] API call | {"model":"claude-3-5-haiku-20241022","tools_count":8,"messages_count":23,"payload_size":11734}
[AIChat] [AIChat AJAX][d58dd982-831c-4187-8665-47579c40a699] NEW arch provider error: Overloaded
```

---

## ❌ PROBLEMA: Error "Overloaded" 

### ¿Qué significa?

**"Overloaded" = Sobrecarga del servidor de Anthropic**

Códigos HTTP relacionados:
- **529**: Service Temporarily Overloaded (sobrecarga temporal)
- **503**: Service Unavailable

**NO ES**:
- ❌ Problema de permisos de API
- ❌ Problema de implementación de tools
- ❌ Problema de cuenta/límites

**ES**:
- ✅ Anthropic recibiendo demasiado tráfico
- ✅ Error temporal del servidor
- ✅ Necesita retry con backoff

---

## 📊 EVIDENCIA DE QUE TOOLS FUNCIONAN

### Del log anterior (primera prueba):
```
[AIChat Tools] Final active_tools for API | {"count":8,...}
[Claude Provider] Routing | {"has_tools":true,"model":"claude-3-5-haiku-20241022"}
[Claude Provider] Starting round 1/5 | {"messages_count":22}
[Claude Provider] API call | {"model":"claude-3-5-haiku-20241022","tools_count":8,"messages_count":21,"payload_size":11249}
[Claude Provider] Final response (no more tools) | {"round":1,"answer_len":320}
```

**Respuesta de Claude**:
> "Sí, tengo acceso a herramientas para consultar información meteorológica..."

**Conclusión**: 
- ✅ Tools se están enviando correctamente (`tools_count":8`)
- ✅ Claude recibió las tools
- ✅ Claude respondió que tiene acceso a las tools
- ❌ Claude NO ejecutó las tools (respondió con texto normal)

---

## 🤔 ¿POR QUÉ CLAUDE NO USÓ LAS TOOLS?

### Posibles Razones:

1. **La pregunta no fue lo suficientemente específica**
   - Usuario: "tienes acceso a tools del tiempo?"
   - Claude interpretó como pregunta de confirmación, no como petición
   
2. **Claude decidió no usarlas**
   - Con `tool_choice: "auto"` (default), Claude DECIDE si usar tools
   - En este caso: pregunta era sobre SI tiene acceso, no QUÉ tiempo hace
   
3. **Segunda petición "para el finde en sevilla"**
   - Esta SÍ debería haber activado tools
   - Pero aquí vino el error "Overloaded"
   - **Claude nunca llegó a procesarla**

---

## 🔧 SOLUCIONES

### 1. Implementar Retry con Exponential Backoff

**Anthropic recomienda**:
- Retry automático con delays exponenciales
- Esperar 1s, luego 2s, luego 4s, etc.
- Máximo 3-5 reintentos

**Código necesario** (en `call_claude_with_tools`):

```php
protected function call_claude_with_tools( $messages, $params ) {
    $max_retries = 3;
    $base_delay = 1; // segundos
    
    for ( $attempt = 1; $attempt <= $max_retries; $attempt++ ) {
        $res = wp_remote_post($endpoint, [...]);
        
        if ( is_wp_error($res) ) {
            return [ 'error' => $res->get_error_message() ];
        }
        
        $code = wp_remote_retrieve_response_code($res);
        
        // Si overloaded (529) o unavailable (503), retry
        if ( $code === 529 || $code === 503 ) {
            if ( $attempt < $max_retries ) {
                $delay = $base_delay * pow(2, $attempt - 1); // 1s, 2s, 4s
                
                if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                    aichat_log_debug( "[Claude Provider] Overloaded, retrying", [
                        'attempt' => $attempt,
                        'max_retries' => $max_retries,
                        'delay_seconds' => $delay,
                    ], true );
                }
                
                sleep($delay);
                continue; // Reintentar
            }
            
            // Último intento falló
            return [ 'error' => 'Service temporarily overloaded. Please try again.' ];
        }
        
        // Otros errores 4xx/5xx
        if ( $code >= 400 ) {
            $data = json_decode($raw, true);
            $err = $data['error']['message'] ?? 'HTTP ' . $code;
            return [ 'error' => $err ];
        }
        
        // Success
        break;
    }
    
    // ... resto del código de parsing ...
}
```

---

### 2. Forzar Uso de Tools (Opcional)

Si quieres que Claude SIEMPRE use tools cuando están disponibles:

```php
$payload = [
    'model' => $model,
    'max_tokens' => (int)$max_tokens,
    'messages' => $claude_msgs,
    'tool_choice' => ['type' => 'auto'], // Default: Claude decide
    // 'tool_choice' => ['type' => 'any'],  // Forzar: DEBE usar alguna tool
    // 'tool_choice' => ['type' => 'tool', 'name' => 'weather_get_current'], // Forzar tool específica
];
```

**Opciones**:
- `auto` (default): Claude decide
- `any`: Claude DEBE usar al menos una tool
- `tool`: Claude DEBE usar tool específica
- `none`: Claude NO puede usar tools

---

### 3. Prompt Más Directo

En lugar de preguntas ambiguas:

❌ Ambiguo:
```
Usuario: "tienes acceso a tools del tiempo?"
```

✅ Directo:
```
Usuario: "¿Qué tiempo hace ahora en Sevilla?"
Usuario: "Busca el pronóstico para el fin de semana en Sevilla"
```

---

## ✅ VERIFICACIÓN

### Según Documentación Oficial

**Formato correcto de tool_result** (nuestro código):
```json
{
  "role": "user",
  "content": [
    {
      "type": "tool_result",
      "tool_use_id": "toolu_01...",
      "content": "{\"temperature\": 25, ...}"
    }
  ]
}
```

**Nuestro código** (`append_tool_conversation`):
```php
$tool_result_blocks[] = [
    'type' => 'tool_result',
    'tool_use_id' => $output['tool_call_id'], // ✅ Correcto
    'content' => $output['output'],            // ✅ String JSON
];

$messages[] = [
    'role' => 'user',
    'content' => $tool_result_blocks, // ✅ Array de tool_result blocks
];
```

**Estado**: ✅ **FORMATO CORRECTO**

---

## 📝 RESUMEN

| Aspecto | Estado | Notas |
|---------|--------|-------|
| Tools enviadas | ✅ FUNCIONANDO | Log muestra `tools_count":8` |
| Tools recibidas por Claude | ✅ FUNCIONANDO | Claude confirmó acceso |
| Formato tool_result | ✅ CORRECTO | Según docs Anthropic |
| Error "Overloaded" | ⚠️ TEMPORAL | Error 529 del servidor |
| Retry logic | ❌ FALTA | Necesita implementar backoff |
| Permisos API | ✅ OK | No es problema de permisos |

---

## 🎯 ACCIÓN REQUERIDA

### Prioridad ALTA
1. **Implementar retry con exponential backoff** para error 529/503
2. **Probar con pregunta más directa**: "¿Qué tiempo hace en Sevilla?"

### Prioridad MEDIA
3. Opcional: Añadir `tool_choice` si quieres forzar uso
4. Añadir rate limiting local (evitar spam a API)

### Verificación
5. Monitorear logs para confirmar:
   - Retry funcionando
   - Tools ejecutándose
   - Respuestas con datos reales

---

## 🧪 PRUEBA SUGERIDA

```
Tú: "¿Qué temperatura hace ahora en Roma?"
```

**Esperado** (con retry implementado):
```
[Claude Provider] Starting round 1/5
[Claude Provider] API call | tools_count=8
[Claude Provider] Tool calls detected | count=1, tools=["weather_get_current"]
[Claude Provider] Executing tool | name=weather_get_current, location=Rome
[Claude Provider] Round 2 | with tool results
[Claude Provider] Final response
```

**Respuesta de Claude**:
> "Actualmente en Roma la temperatura es de 18°C con cielo parcialmente nublado."

---

## 📚 REFERENCIAS

- [Anthropic Tool Use Docs](https://docs.anthropic.com/en/docs/build-with-claude/tool-use)
- [Error Handling](https://docs.anthropic.com/en/api/errors)
- [Rate Limits](https://docs.anthropic.com/en/api/rate-limits)

**Conclusión**: El código está bien implementado. Solo falta manejo de errores temporales (retry logic).
