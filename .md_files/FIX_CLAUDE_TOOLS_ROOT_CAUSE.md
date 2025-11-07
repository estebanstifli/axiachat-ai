# Fix Claude Tools - Root Cause Found

## 🔴 PROBLEMA ENCONTRADO

**Causa raíz**: La construcción del array `$active_tools` SOLO se ejecutaba para OpenAI.

### Línea problemática (class-aichat-ajax.php:453)

```php
// ❌ ANTES (INCORRECTO)
if ( $provider === 'openai' && ! empty( $bot['tools_json'] ) ) {
    // ... todo el código de construcción de tools ...
}
```

**Resultado**: Para Claude, `$active_tools` SIEMPRE estaba vacío (`tools(0)`), independientemente de si el bot tenía tools configuradas.

---

## ✅ SOLUCIÓN APLICADA

### Fix 1: Construcción de Tools (CRÍTICO)

**Archivo**: `includes/class-aichat-ajax.php`  
**Línea**: ~453

```php
// ✅ DESPUÉS (CORRECTO)
// FIXED: Support tools for ALL providers (OpenAI + Claude)
if ( ! empty( $bot['tools_json'] ) ) {
    $raw_selected = json_decode( (string)$bot['tools_json'], true );
    if ( is_array( $raw_selected ) ) {
        // DEBUG: Log raw selected
        if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
            aichat_log_debug('[AIChat Tools] Raw selected from bot', [
                'bot_slug' => $bot_slug_r,
                'provider' => $provider,  // ← Añadido para debug
                'raw_selected' => $raw_selected,
                'count' => count($raw_selected),
            ], true);
        }
        
        // ... resto del código de expansión y construcción ...
    }
}
```

**Cambios**:
1. ❌ Eliminado: `$provider === 'openai' &&`
2. ✅ Añadido: `'provider' => $provider` en logs (para debug)

---

### Fix 2: Pasar Tools al Provider (YA APLICADO ANTES)

**Archivo**: `includes/class-aichat-ajax.php`  
**Línea**: ~567

```php
// ✅ YA ESTABA CORRECTO (del fix anterior)
if ( ! empty( $active_tools ) ) {
    $call_params['tools'] = $active_tools;
    $call_params['request_uuid'] = $request_uuid;
    $call_params['session_id'] = $session;
    $call_params['bot_slug'] = $bot_slug_r;
}
```

---

## 📊 FLUJO CORREGIDO

### ANTES (Broken)
```
Bot Claude + Tools configuradas
    ↓
Línea 453: if ($provider === 'openai' && ...) → FALSE
    ↓
$active_tools = [] (vacío)
    ↓
Línea 567: if (!empty($active_tools)) → FALSE
    ↓
$call_params SIN 'tools'
    ↓
Claude recibe: has_tools=false, tools(0)
```

### DESPUÉS (Fixed)
```
Bot Claude + Tools configuradas
    ↓
Línea 453: if (!empty($bot['tools_json'])) → TRUE
    ↓
Expansión de macros → tools atómicas
    ↓
Construcción de $active_tools (formato OpenAI)
    ↓
Línea 567: if (!empty($active_tools)) → TRUE
    ↓
$call_params['tools'] = $active_tools
    ↓
Claude Provider:
  - build_anthropic_tools() → convierte a formato Anthropic
  - chat_with_tools() → multi-round con tool_use/tool_result
```

---

## 🔍 PROBLEMA SECUNDARIO: Modelos 404

El log también muestra:

```
[Claude Provider] Attempt 1 | model=claude-3-opus-20240229 → 404 not_found_error
[Claude Provider] Attempt 2 | model=claude-3-5-sonnet-20240620 → 404 not_found_error
[Claude Provider] Attempt 3 | model=claude-3-sonnet-20240229 → 404 not_found_error
[Claude Provider] Attempt 4 | model=claude-3-haiku-20240307 → 200 SUCCESS
```

**Posibles causas**:
1. **API Key sin acceso** a modelos Opus/Sonnet
2. **Cuota agotada** para modelos premium
3. **Región** (algunos modelos no disponibles en todas las regiones)

**Modelos válidos Anthropic (Marzo 2024)**:
- ✅ `claude-3-5-sonnet-20241022` (más reciente)
- ✅ `claude-3-5-sonnet-20240620`
- ✅ `claude-3-opus-20240229`
- ✅ `claude-3-sonnet-20240229`
- ✅ `claude-3-haiku-20240307`

**Solución**: Verificar en consola Anthropic:
- Límites de la API key
- Modelos disponibles
- Usar Haiku por defecto si solo testing

---

## 🧪 PRUEBA

1. **Recargar página** (F5)
2. **Enviar mensaje** al bot Claude con tools
3. **Revisar log** `debug_ia.log`

**Esperado**:
```
[AIChat Tools] Raw selected from bot | {"provider":"claude","count":8,...}
[AIChat Tools] Expanded atomic tools | {"count":8,...}
[AIChat Tools] Final active_tools for API | {"count":8,...}
[Claude Provider] Routing | {"has_tools":true,...}
[Claude Provider] build_anthropic_tools | {"count":8,...}
[Claude Provider] chat_with_tools START | {"model":"claude-3-haiku-20240307",...}
```

---

## 📝 CHECKLIST

- [x] Fix 1: Línea 453 - Eliminar restricción `$provider === 'openai'`
- [x] Fix 2: Línea 567 - Pass tools a todos los providers (YA HECHO)
- [x] Añadir `'provider'` a logs de debug
- [ ] **PROBAR** - Enviar mensaje y verificar log
- [ ] Verificar modelos disponibles en cuenta Anthropic
- [ ] Ajustar modelo por defecto si necesario

---

## 🎯 RESUMEN

**2 bugs encontrados y corregidos**:

1. **Bug crítico** (línea 453): Tools NO se construían para Claude
   - Fix: Eliminar `$provider === 'openai' &&`
   
2. **Bug menor** (línea 567): Tools NO se pasaban a Claude (YA CORREGIDO)
   - Fix: Eliminar `$provider === 'openai' &&`

**1 problema secundario identificado**:
- Modelos Opus/Sonnet devuelven 404 → Verificar API key/límites

---

**Status**: ✅ **READY TO TEST**

Recarga la página y prueba el bot. Debería ver `tools(8)` en el log.
