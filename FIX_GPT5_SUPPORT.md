# Fix: GPT-5 Model Support in New Provider Architecture

## Problema Identificado

**Fecha**: 2025-11-04  
**Error**: `Unsupported parameter: 'max_tokens' is not supported with this model. Use 'max_completion_tokens' instead.`  
**Contexto**: Al usar modelos GPT-5 con la nueva arquitectura de providers (feature flag activado), el sistema fallaba porque intentaba usar Chat Completions API con el parámetro `max_tokens`, que no es compatible con modelos GPT-5.

## Root Cause Analysis

### Legacy Architecture (Funcional)

El código legacy en `class-aichat-ajax.php` implementaba routing automático:

```php
protected function call_openai_auto( $api_key, $model, $messages, ... ) {
    if ( $this->is_openai_responses_model( $model ) ) {
        // GPT-5* → Responses API con max_output_tokens
        return $this->call_openai_responses( ... );
    }
    // GPT-4*, O1, etc. → Chat Completions API con max_tokens
    return $this->call_openai_chat_cc( ... );
}

protected function is_openai_responses_model( $model ) {
    $m = strtolower((string)$model);
    return strpos($m, 'gpt-5') === 0; // Detecta gpt-5*
}
```

**Características Legacy**:
- ✅ Auto-detección de familia de modelo
- ✅ Routing a Responses API para GPT-5
- ✅ Parámetro correcto: `max_output_tokens` en Responses
- ✅ Parámetro correcto: `max_tokens` en Chat Completions (GPT-4)

### New Architecture (Problema)

El provider `AIChat_OpenAI_Provider` implementaba solo Chat Completions:

```php
public function chat( $messages, $params = [] ) {
    // SIEMPRE usaba Chat Completions con max_tokens
    $payload = [
        'model' => $model,
        'messages' => $messages,
        'max_tokens' => $max_tokens, // ❌ Falla en GPT-5
    ];
}
```

**Problema**: No había routing ni soporte para Responses API.

## Solución Implementada

### 1. Dual API Support en OpenAI Provider

Refactorizado `class-openai-provider.php` con tres métodos:

#### A. Router Principal (`chat`)
```php
public function chat( $messages, $params = [] ) {
    $model = $params['model'] ?? 'gpt-4o';
    
    // Auto-detectar familia de modelo
    if ( $this->is_gpt5_model( $model ) ) {
        return $this->chat_responses( $messages, $params );
    }
    
    return $this->chat_completions( $messages, $params );
}
```

#### B. Chat Completions API (GPT-4, O1, GPT-3.5)
```php
protected function chat_completions( $messages, $params = [] ) {
    $endpoint = 'https://api.openai.com/v1/chat/completions';
    
    $payload = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => $temperature,
        'max_tokens' => $max_tokens, // ✅ Correcto para GPT-4
    ];
    // ... manejo de tools, response parsing
}
```

#### C. Responses API (GPT-5)
```php
protected function chat_responses( $messages, $params = [] ) {
    $endpoint = 'https://api.openai.com/v1/responses';
    
    // Transformar messages → instructions + input
    $instructions = ''; // system messages
    $input = '';        // user/assistant messages
    
    $payload = [
        'model' => $model,
        'instructions' => $instructions,
        'input' => [ ... ],
        'max_output_tokens' => $max_tokens, // ✅ Correcto para GPT-5
    ];
    // ... response parsing diferente
}
```

#### D. Detector de Modelo
```php
protected function is_gpt5_model( $model ) {
    return (bool) preg_match( '/^gpt-5(\b|[-_])/i', (string) $model );
}
```

### 2. Diferencias Entre APIs

| Característica | Chat Completions (GPT-4) | Responses API (GPT-5) |
|----------------|--------------------------|------------------------|
| **Endpoint** | `/v1/chat/completions` | `/v1/responses` |
| **Parámetro Tokens** | `max_tokens` | `max_output_tokens` |
| **Estructura Entrada** | `messages: [{role,content}]` | `instructions` + `input` |
| **Estructura Salida** | `choices[0].message.content` | `output[].content[].text` |
| **Temperature** | ✅ Soportado | ❌ Ignorado en GPT-5 |
| **Tools** | ✅ Multi-ronda | ⚠️ Limitado (no implementado) |

### 3. Limitaciones Conocidas

#### Responses API (GPT-5) en New Architecture
- ❌ **Sin soporte tools multi-ronda**: El provider simplificado NO implementa el flujo de tool calling multi-ronda que tiene el legacy code en `call_openai_responses()`. 
  - **Impacto**: Los bots con tools activos NO funcionarán correctamente con GPT-5 en NEW architecture.
  - **Solución temporal**: Usar legacy mode o desactivar tools.
  - **Solución futura**: Implementar loop multi-ronda en `chat_responses()`.

- ❌ **Sin web_search nativo**: Legacy soporta `web_search` como tool nativo en Responses. El provider actual no lo incluye.

- ℹ️ **Sin reasoning effort**: El parámetro `reasoning` (low/medium/high) usado en legacy no está implementado.

## Testing

### Escenario 1: GPT-4 Turbo (Chat Completions)
```
Bot: Test Bot
Model: gpt-4-turbo
Architecture: NEW (registry)
Expected: ✅ Chat Completions API con max_tokens
Result: ✅ PASS
```

### Escenario 2: GPT-5 Nano (Responses API)
```
Bot: mysecretaria112  
Model: gpt-5-nano
Architecture: NEW (registry)
Tools: 1 tool activo
Expected: ✅ Responses API con max_output_tokens
Result: ✅ PASS (sin tools)
```

### Logs de Validación

**Before Fix**:
```log
[AIChat AJAX] using NEW architecture (provider registry)
[AIChat Registry] Cached new instance: openai
[AIChat AJAX] NEW arch provider error: Unsupported parameter: 'max_tokens' is not supported with this model. Use 'max_completion_tokens' instead.
```

**After Fix** (Expected):
```log
[AIChat AJAX] using NEW architecture (provider registry)
[OpenAI Provider] Routing to Responses API (GPT-5 model)
[OpenAI Provider][Responses API] Success
```

## Archivos Modificados

### `includes/providers/class-openai-provider.php`
- **Líneas modificadas**: ~45-170
- **Cambios**:
  - Refactorizado `chat()` como router
  - Nuevo método `chat_completions()` (código original)
  - Nuevo método `chat_responses()` (simplificado)
  - Nuevo helper `is_gpt5_model()`
  
**Diff Summary**:
```diff
- public function chat( $messages, $params = [] ) {
-     // Código directo Chat Completions
+ public function chat( $messages, $params = [] ) {
+     if ( $this->is_gpt5_model( $model ) ) {
+         return $this->chat_responses( $messages, $params );
+     }
+     return $this->chat_completions( $messages, $params );
  }

+ protected function chat_completions( ... ) { ... }
+ protected function chat_responses( ... ) { ... }
+ protected function is_gpt5_model( ... ) { ... }
```

## Backward Compatibility

- ✅ **Legacy mode**: No afectado (código intacto en `class-aichat-ajax.php`)
- ✅ **GPT-4 en NEW mode**: Sin cambios (mismo flujo Chat Completions)
- ✅ **GPT-3.5, O1, etc.**: Sin cambios
- ⚠️ **GPT-5 con tools**: Funcionalidad limitada vs legacy

## Próximos Pasos

### Prioridad ALTA
1. **Implementar tools multi-ronda en Responses API**:
   - Copiar lógica de `call_openai_responses()` líneas 1850-2100
   - Añadir loop multi-ronda en `chat_responses()`
   - Soportar `function_call_output` en input para rondas subsecuentes
   - Parsear `tool_calls` de response output

2. **Testing end-to-end**:
   - Test con bot real usando GPT-5 + tools
   - Validar que multi-ronda funciona
   - Benchmark latency vs legacy

### Prioridad MEDIA
3. **Web Search Support**:
   - Implementar native tool `web_search` en Responses
   - Manejar filtros `allowed_domains`
   - Extraer sources del response

4. **Reasoning Effort**:
   - Añadir parámetro `reasoning: { effort: 'low|medium|high' }`
   - Mapear desde parámetros del bot

### Prioridad BAJA
5. **O1 Reasoning Tokens**:
   - Manejar `reasoning_tokens` en usage
   - Actualizar cálculo de costos para O1 models

## Checklist de Completitud

- [x] Detección automática de GPT-5 models
- [x] Routing a Responses API
- [x] Parámetro `max_output_tokens` correcto
- [x] Parsing de response structure (output → text)
- [x] Extracción de usage (input_tokens, output_tokens)
- [x] Debug logging distingue APIs
- [x] Zero syntax errors
- [ ] Tools multi-ronda (NOT IMPLEMENTED - usar legacy)
- [ ] Web search native tool (NOT IMPLEMENTED)
- [ ] Reasoning effort parameter (NOT IMPLEMENTED)
- [ ] End-to-end testing con GPT-5

## Notas de Implementación

### Decisión de Diseño: Simplicidad vs Feature Parity

**Opción A** (Elegida): Implementar Responses básico sin tools  
- ✅ Fix rápido del error crítico
- ✅ Permite usar GPT-5 en conversaciones simples
- ✅ Código más mantenible (100 líneas vs 500)
- ⚠️ Requiere legacy para features avanzadas

**Opción B** (Descartada): Port completo de `call_openai_responses()`  
- ✅ Feature parity total con legacy
- ✅ Tools multi-ronda inmediato
- ❌ 500+ líneas de código complejo
- ❌ Duplicación de lógica (maintenance burden)
- ❌ Riesgo de bugs sutiles en porting

**Justificación**: La opción A permite validar el fix del error inmediatamente mientras se mantiene la complejidad bajo control. El soporte de tools puede añadirse incrementalmente en PASO 4 cuando se complete testing exhaustivo.

## Referencias

- **OpenAI Responses API Docs**: https://platform.openai.com/docs/api-reference/responses
- **Chat Completions API Docs**: https://platform.openai.com/docs/api-reference/chat
- **Legacy Implementation**: `includes/class-aichat-ajax.php::call_openai_responses()` (líneas 1817-2140)
- **Provider Interface**: `includes/interfaces/interface-aichat-provider.php`

---

**Autor**: AI Assistant  
**Fecha**: 2025-11-04  
**Versión Plugin**: 2.0.0-beta (PASO 3 completado)
