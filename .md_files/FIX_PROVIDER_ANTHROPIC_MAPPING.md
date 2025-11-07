# Fix: Provider 'anthropic' → 'claude' Mapping

## ❌ PROBLEMA

Usuario reportó: *"le he preguntado por el tiempo en londres, ejecuta la tool y muestra en el mensaje pero luego no se si para dar la respuesta o que da Continuation error: error."*

### Debug Log
```
[04-Nov-2025 11:12:58 UTC] bot provider=anthropic
[04-Nov-2025 11:12:58 UTC] NEW arch tool continuation | {"provider":"anthropic",...}
```

**Error**: El provider se llama `anthropic` en la base de datos, pero:
- Registry lo registra como `'claude'` (línea 138 `axiachat-ai.php`)
- Código continuation solo verificaba `$provider === 'claude'`

**Resultado**:
1. `$registry->get('anthropic')` → devuelve `null` (provider no encontrado)
2. Condición `if ($provider === 'claude')` → `false`
3. No se ejecuta `continue_from_tool_pending()`
4. No hay respuesta final → error silencioso

---

## ✅ SOLUCIÓN

### Cambio 1: Mapear provider en `process_message` (línea ~533)

**ANTES**:
```php
$registry = AIChat_Provider_Registry::instance();
$provider_instance = $registry->get( $provider, $provider_config );
```

**DESPUÉS**:
```php
// Mapear provider legacy 'anthropic' a 'claude' para registry
$registry_provider = ( $provider === 'anthropic' ) ? 'claude' : $provider;

$registry = AIChat_Provider_Registry::instance();
$provider_instance = $registry->get( $registry_provider, $provider_config );
```

---

### Cambio 2: Mapear provider en `process_tool_continuation` (línea ~957)

**ANTES**:
```php
$registry = AIChat_Provider_Registry::instance();
$provider_instance = $registry->get( $provider );

// Claude usa continue_from_tool_pending
if ( $provider === 'claude' ) {
```

**DESPUÉS**:
```php
// Mapear provider legacy 'anthropic' a 'claude' para registry
$registry_provider = ( $provider === 'anthropic' ) ? 'claude' : $provider;

$registry = AIChat_Provider_Registry::instance();
$provider_instance = $registry->get( $registry_provider );

// Claude/Anthropic usa continue_from_tool_pending
if ( $provider === 'claude' || $provider === 'anthropic' ) {
```

---

### Cambio 3: Actualizar debug log para traceabilidad

**AÑADIDO**:
```php
aichat_log_debug("[AIChat AJAX][$uid] NEW arch tool continuation", [
    'provider' => $provider,              // 'anthropic' (original)
    'registry_provider' => $registry_provider,  // 'claude' (mapeado)
    'response_id' => $response_id,
    'tool_count' => count($tool_calls),
], true);
```

---

## 🔄 FLUJO CORREGIDO

### Usuario: "dame el tiempo de Londres"

**Round 1: Initial Request**
```
Bot provider DB: 'anthropic'
→ Mapeo: registry_provider = 'claude'
→ $registry->get('claude') ✅ devuelve AIChat_Claude_Provider
→ chat_with_tools() detecta tool_use
→ Devuelve tool_pending con activity_label
```

**Round 2: Tool Continuation**
```
Bot provider DB: 'anthropic'
→ Mapeo: registry_provider = 'claude'
→ $registry->get('claude') ✅ devuelve AIChat_Claude_Provider
→ Condición: 'anthropic' === 'claude' || 'anthropic' === 'anthropic' ✅ TRUE
→ continue_from_tool_pending() se ejecuta
→ Tool ejecutada → respuesta final ✅
```

**Log Esperado**:
```
[AIChat AJAX] NEW arch tool continuation | provider=anthropic, registry_provider=claude
[Claude Provider] Continuing from tool_pending | response_id=xxx
[Claude Provider] Executing tool | name=get_current_weather, location=London
[Claude Provider] Continuation round 2/5
[Claude Provider] Continuation final response | answer_len=X
```

---

## 📊 COMPATIBILIDAD

### Bots con `provider='anthropic'` (Legacy)
- ✅ Mapeado automáticamente a `'claude'`
- ✅ Registry encuentra provider
- ✅ Continuation funciona

### Bots con `provider='claude'` (Nueva nomenclatura)
- ✅ Pasa directo sin mapeo
- ✅ Registry encuentra provider
- ✅ Continuation funciona

### Bots con `provider='openai'`
- ✅ No afectado por mapeo
- ✅ Sigue path legacy/nuevo según flag

---

## 🎯 ARCHIVOS MODIFICADOS

**`includes/class-aichat-ajax.php`**:
- Línea ~535: Agregado mapeo en `process_message`
- Línea ~549: Usar `$registry_provider` para get()
- Línea ~960: Agregado mapeo en `process_tool_continuation`
- Línea ~967: Usar `$registry_provider` para get()
- Línea ~980: Condición actualizada a `claude || anthropic`
- Línea ~974: Debug log con ambos valores

---

## ✅ VALIDACIÓN

**Syntax Check**: 0 errores
**Linter Warning**: 1 (esperado - método dinámico)

**Test Case**:
1. Bot con `provider='anthropic'`
2. Mensaje: "¿Qué tiempo hace en Londres?"
3. **Esperado**:
   - Widget muestra "Ejecutando get_current_weather"
   - Tool se ejecuta correctamente
   - Respuesta final con temperatura real
   
**Log Debug**:
```
provider=anthropic, registry_provider=claude  ← Mapeo visible
Continuing from tool_pending  ← Método llamado
Continuation final response  ← Respuesta generada
```

---

## 🔮 MEJORA FUTURA

Considerar migrar bots legacy:
```sql
UPDATE wp_aichat_bots 
SET provider = 'claude' 
WHERE provider = 'anthropic';
```

**Ventaja**: Eliminar necesidad de mapeo en runtime
**Desventaja**: Requiere migración de DB en actualizaciones

**Decisión**: Mantener mapeo por backward compatibility (no breaking change)

---

**Status**: ✅ **FIX APLICADO - Listo para testing**
