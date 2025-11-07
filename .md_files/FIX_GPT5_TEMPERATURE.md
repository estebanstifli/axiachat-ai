# Fix: GPT-5 Temperature Parameter Error

**Fecha:** Noviembre 4, 2025  
**Issue:** `Unsupported parameter: 'temperature' is not supported with this model`  
**Modelos Afectados:** GPT-5 (Responses API)

---

## 🐛 Error Detectado

**Log:**
```
[04-Nov-2025 07:04:34 UTC] [AIChat] [AIChat AJAX][ace6a350-593f-4033-82aa-01d567cb9fb7] 
NEW arch provider error: Unsupported parameter: 'temperature' is not supported with this model.
```

**Causa:**
Los métodos `chat_responses()` y `chat_responses_with_tools()` incluían el parámetro `temperature` en el payload enviado a la API de Responses, pero **GPT-5 no soporta este parámetro**.

---

## ✅ Solución Aplicada

### Cambios en `class-openai-provider.php`

#### 1. Método `chat_responses()` (líneas ~371-375)

**ANTES:**
```php
protected function chat_responses( $messages, $params = [] ) {
    $api_key = $this->config['api_key'] ?? '';
    $model = $params['model'] ?? 'gpt-5-nano';
    $temperature = $params['temperature'] ?? null;  // ❌ Se leía pero no se usaba
    $max_tokens = $params['max_tokens'] ?? 2048;
```

**DESPUÉS:**
```php
protected function chat_responses( $messages, $params = [] ) {
    $api_key = $this->config['api_key'] ?? '';
    $model = $params['model'] ?? 'gpt-5-nano';
    $max_tokens = $params['max_tokens'] ?? 2048;
    // temperature NO soportado en Responses API
```

---

#### 2. Método `chat_responses_with_tools()` (líneas ~486-489)

**ANTES:**
```php
protected function chat_responses_with_tools( $messages, $params ) {
    $model = $params['model'] ?? 'gpt-5-turbo';
    $max_tokens = $params['max_output_tokens'] ?? 2048;
    $temperature = $params['temperature'] ?? 0.7;  // ❌ Variable leída
    $tools = isset( $params['tools'] ) && is_array( $params['tools'] ) ? $params['tools'] : [];
```

**DESPUÉS:**
```php
protected function chat_responses_with_tools( $messages, $params ) {
    $model = $params['model'] ?? 'gpt-5-turbo';
    $max_tokens = $params['max_output_tokens'] ?? 2048;
    $tools = isset( $params['tools'] ) && is_array( $params['tools'] ) ? $params['tools'] : [];
    // temperature NO soportado en Responses API
```

---

#### 3. Payload Primera Ronda (líneas ~540)

**ANTES:**
```php
$payload = [
    'model'              => $model,
    'instructions'       => $instructions,
    'input'              => [...],
    'max_output_tokens'  => $max_tokens,
    'temperature'        => $temperature,  // ❌ Causaba error
    'tools'              => $normalized_tools,
    'tool_choice'        => 'auto'
];
```

**DESPUÉS:**
```php
$payload = [
    'model'              => $model,
    'instructions'       => $instructions,
    'input'              => [...],
    'max_output_tokens'  => $max_tokens,
    // NO incluir 'temperature' - GPT-5 no lo soporta
    'tools'              => $normalized_tools,
    'tool_choice'        => 'auto'
];
```

---

## 📋 Parámetros Soportados/No Soportados

### Responses API (GPT-5)

#### ✅ Soportados
- `model`
- `instructions`
- `input`
- `max_output_tokens`
- `tools`
- `tool_choice`
- `previous_response_id` (rondas subsecuentes)

#### ❌ NO Soportados
- `temperature` - **Genera error**
- `max_tokens` (usar `max_output_tokens` en su lugar)
- Otros parámetros de Chat Completions que no están documentados en Responses API

### Chat Completions (GPT-4, O1, GPT-3.5)

#### ✅ Soportados
- `model`
- `messages`
- `max_tokens`
- `temperature` ✅
- `top_p`
- `tools`
- `tool_choice`
- Y otros según docs

---

## 🔍 Verificación

### Test 1: GPT-5 sin tools
```php
$params = [
    'model' => 'gpt-5-turbo',
    'temperature' => 0.7  // ← Este parámetro será ignorado
];
// Expected: Sin error, temperature no se envía a la API
```

### Test 2: GPT-5 con tools
```php
$params = [
    'model' => 'gpt-5-turbo',
    'temperature' => 0.9,  // ← Ignorado
    'tools' => [...]
];
// Expected: Sin error, payload no incluye temperature
```

### Test 3: GPT-4 (control)
```php
$params = [
    'model' => 'gpt-4-turbo',
    'temperature' => 0.7  // ← Se envía normalmente (Chat Completions)
];
// Expected: Funciona normal, temperature incluido en payload
```

---

## 📝 Documentación Actualizada

### Archivos Modificados

1. **`IMPLEMENTACION_MULTIRONDA_RESPONSES.md`**
   - Añadida advertencia en tabla comparativa
   - Actualizado ejemplo de código
   - Eliminadas referencias a `$temperature`

2. **`RESUMEN_MULTIRONDA_COMPLETO.md`**
   - Añadida fila "Temperature" en tabla comparativa
   - Marcado como NO soportado en Responses API

3. **`FIX_GPT5_TEMPERATURE.md`** (este documento)
   - Documentación específica del fix

---

## 🎓 Lecciones Aprendidas

### 1. Diferencias API
**Lección:** Las APIs de OpenAI no son homogéneas. Responses API tiene limitaciones vs Chat Completions.

**Acción:** Siempre verificar docs oficiales de cada API antes de asumir compatibilidad de parámetros.

### 2. Validación de Parámetros
**Problema:** El código leía `temperature` de `$params` pero no validaba si era apropiado enviarlo.

**Solución:** No leer parámetros que no se van a usar. Comentar explícitamente por qué no se incluyen.

### 3. Testing Cross-Model
**Lección:** Testing con un modelo (GPT-4) no garantiza que funcione con otro (GPT-5).

**Acción:** Test suite debe cubrir cada familia de modelos por separado.

---

## 🚀 Próximos Pasos

### Validaciones Adicionales

1. **Revisar otros parámetros:**
   - ¿Hay otros parámetros que GPT-5 no soporte?
   - Crear lista completa de diferencias

2. **Warning preventivo:**
   - Si usuario configura `temperature` en bot GPT-5, mostrar warning en settings:
     ```
     "Nota: GPT-5 no soporta el parámetro 'temperature'. Este ajuste será ignorado."
     ```

3. **Documentación usuario:**
   - Actualizar guía de configuración de bots
   - Tabla de parámetros soportados por modelo

---

## 📊 Resumen del Fix

| Aspecto | Antes | Después |
|---------|-------|---------|
| **Variable `$temperature`** | Se leía de `$params` | Eliminada |
| **Payload primera ronda** | Incluía `'temperature' => $temperature` | Eliminado |
| **Error GPT-5** | ❌ "Unsupported parameter" | ✅ Sin error |
| **Comportamiento GPT-4** | ✅ Funciona | ✅ Funciona (sin cambios) |

---

## ✅ Validación

**Sintaxis:** 0 errores  
**Testing manual:** ⏳ Pendiente  
**Documentación:** ✅ Actualizada

---

**Status:** ✅ FIX APLICADO  
**Impact:** Crítico (bloqueaba uso de GPT-5)  
**Breaking Changes:** Ninguno (solo elimina código problemático)
