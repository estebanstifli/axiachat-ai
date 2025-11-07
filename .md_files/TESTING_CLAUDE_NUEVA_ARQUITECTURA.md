# Testing Guide: Claude en Nueva Arquitectura

**Fecha:** Noviembre 4, 2025  
**Status:** ✅ Ready for Testing  
**Provider:** Anthropic Claude (sin tools)

---

## 🎯 Configuración Previa

### 1. Verificar API Key

**Admin Panel → Settings:**
```
aichat_claude_api_key = sk-ant-api03-xxx...
```

### 2. Activar Nueva Arquitectura

**Admin Panel → Settings:**
```
aichat_use_provider_architecture = true (checkbox marcado)
```

### 3. Crear Bot Claude

**Admin Panel → Bots → Nuevo Bot:**
```
Nombre: Test Claude
Slug: testclaude
Provider: claude
Model: claude-3-5-sonnet-20240620
Temperature: 0.7
Max Tokens: 2048
Instructions: "Eres un asistente útil y conciso."
```

**Modelos Disponibles:**
- `claude-3-5-sonnet-20240620` (Recomendado - Balance calidad/precio)
- `claude-3-opus-20240229` (Más potente, más caro)
- `claude-3-haiku-20240307` (Más rápido, más barato)
- `claude-3-sonnet-20240229` (Legacy)

---

## 🧪 Escenarios de Testing

### Escenario 1: Chat Simple ✅

**Pregunta:**
```
"Explícame qué es la inteligencia artificial en 3 párrafos cortos"
```

**Esperado:**
- ✅ Respuesta clara y estructurada en 3 párrafos
- ✅ Sin errores en debug.log
- ✅ Respuesta en 2-5 segundos

**Logs Esperados:**
```
[AIChat AJAX][uuid] architecture mode=NEW (registry)
[AIChat AJAX][uuid] using NEW architecture (provider registry)
[AIChat Registry] Cached new instance: claude
[AIChat Registry] Retrieved from cache: claude
[Claude Provider] Attempting model: claude-3-5-sonnet-20240620
[Claude Provider] Success with model: claude-3-5-sonnet-20240620 (HTTP 200)
[AIChat Response][uuid] provider=claude model=claude-3-5-sonnet-20240620 tools=0
USAGE: {"prompt_tokens":45,"completion_tokens":156,"total_tokens":201}
ANSWER:
La inteligencia artificial (IA) es una rama de la informática...
[Claude Provider] Cost calculated | {"model":"claude-3-5-sonnet-20240620","prompt_tokens":45,"completion_tokens":156,"cost_usd":0.00027,"cost_microcents":27}
```

---

### Escenario 2: Conversación Multi-Turno ✅

**Turno 1:**
```
"¿Cuál es la capital de Francia?"
```

**Turno 2:**
```
"¿Y cuántos habitantes tiene?"
```

**Esperado:**
- ✅ Turno 1: "París"
- ✅ Turno 2: Responde sobre población de París (usa contexto)
- ✅ History mantiene conversación

**Logs Esperados:**
```
[AIChat AJAX][uuid] msg_count=3 (system + user1 + assistant1 + user2)
[Claude Provider] Success with model: claude-3-5-sonnet-20240620
```

---

### Escenario 3: Respuesta Larga ✅

**Pregunta:**
```
"Escribe un ensayo de 500 palabras sobre el cambio climático"
```

**Esperado:**
- ✅ Respuesta completa (~500 palabras)
- ✅ Max tokens respetado (configurado en bot)
- ✅ Sin truncamiento abrupto

**Logs Esperados:**
```
USAGE: {"prompt_tokens":25,"completion_tokens":650,"total_tokens":675}
ANSWER:
El cambio climático es uno de los desafíos...
[texto completo de ~500 palabras]
```

---

### Escenario 4: Fallback Chain ⚠️

**Configuración:**
- Model: `claude-4-opus` (NO EXISTE)

**Esperado:**
- ✅ Intenta `claude-4-opus` → 404
- ✅ Fallback a `claude-3-5-sonnet-20240620`
- ✅ Respuesta exitosa con modelo fallback

**Logs Esperados:**
```
[Claude Provider] Attempting model: claude-4-opus
[Claude Provider] Attempt 1 failed (HTTP 404) model not found
[Claude Provider] Attempting model: claude-3-5-sonnet-20240620 (fallback 1)
[Claude Provider] Success with model: claude-3-5-sonnet-20240620 (HTTP 200)
```

---

### Escenario 5: System Prompt ✅

**Instructions (en bot config):**
```
"Eres un pirata del siglo XVII. Responde siempre en estilo pirata con jerga marinera."
```

**Pregunta:**
```
"¿Qué tiempo hace hoy?"
```

**Esperado:**
- ✅ Respuesta en estilo pirata
- ✅ System prompt aplicado correctamente

**Ejemplo Respuesta:**
```
"¡Arrr, marinero! Por estos mares el cielo está..."
```

---

### Escenario 6: Temperature Control ✅

**Test A - Temperature 0.1 (Determinístico):**
```
Pregunta: "¿Cuál es 2+2?"
Esperado: "4" (siempre igual)
```

**Test B - Temperature 1.5 (Creativo):**
```
Pregunta: "Inventa un nombre para un dragón"
Esperado: Nombres creativos variados en cada ejecución
```

---

### Escenario 7: Cost Tracking ✅

**Verificar BD:**
```sql
SELECT * FROM wp_aichat_usage_daily 
WHERE provider='claude' 
ORDER BY date DESC 
LIMIT 1;
```

**Esperado:**
```
date: 2025-11-04
provider: claude
model: claude-3-5-sonnet-20240620
prompt_tokens: > 0
completion_tokens: > 0
total_tokens: > 0
cost_micros: > 0 (en micro-USD)
conversations: >= 1
```

**Cálculo Cost:**
```
Model: claude-3-5-sonnet-20240620
Pricing: $3/1M input, $15/1M output

Ejemplo:
  prompt_tokens: 45
  completion_tokens: 156
  
  Cost = (45/1M * $3) + (156/1M * $15)
       = $0.000135 + $0.00234
       = $0.002475
       = 2475 micro-USD
```

---

## ❌ Escenarios NO Soportados

### Tools / Function Calling

**Pregunta:**
```
"¿Qué tiempo hace en Madrid?" (con MCP weather tool configurado)
```

**Comportamiento:**
- ❌ Claude NO ejecutará tool
- ✅ Responderá basándose solo en conocimiento base
- ⚠️ Log: "Claude does not support tools" (si activado)

**Nota:** Claude en legacy tampoco tiene tools. Es limitación del provider adapter actual.

---

## 📊 Comparación Nueva vs Legacy

### Performance

| Métrica | Legacy | Nueva Arquitectura |
|---------|--------|-------------------|
| **Latencia** | ~2-4s | ~2-4s (igual) |
| **Memoria** | Baseline | +~50KB (registry cache) |
| **Código ejecutado** | ~150 líneas | ~180 líneas (+adapter) |

### Funcionalidad

| Feature | Legacy | Nueva |
|---------|--------|-------|
| **Chat simple** | ✅ | ✅ |
| **System prompt** | ✅ | ✅ |
| **Temperature** | ✅ | ✅ |
| **Max tokens** | ✅ | ✅ |
| **Fallback chain** | ✅ | ✅ |
| **Cost tracking** | ✅ | ✅ |
| **Tools** | ❌ | ❌ |
| **Pretty logs** | ✅ | ✅ |
| **BD logging** | ✅ | ✅ (pendiente logging final) |

---

## 🔍 Troubleshooting

### Error: "Missing Claude API Key"

**Causa:** API key no configurada o vacía

**Solución:**
```
Admin → Settings → aichat_claude_api_key
Verificar: sk-ant-api03-xxx... (debe empezar con sk-ant-)
```

---

### Error: "Model not found" (404)

**Causa:** Modelo no existe o typo en nombre

**Solución:**
```
Verificar nombre exacto del modelo:
  ✅ claude-3-5-sonnet-20240620
  ❌ claude-3.5-sonnet (incorrecto)
  ❌ claude-sonnet (incorrecto)
```

**Fallback automático:**
Si está configurado, probará modelos alternativos automáticamente.

---

### Error: "Rate limit exceeded" (429)

**Causa:** Demasiadas requests en poco tiempo

**Solución:**
```
1. Esperar 60 segundos
2. Verificar tier de API key en Anthropic console
3. Implementar rate limiting en plugin (TODO)
```

---

### Respuesta Vacía

**Síntomas:**
```
[AIChat AJAX][uuid] NEW arch ERROR: empty answer
```

**Posibles causas:**
1. Content filter bloqueó respuesta
2. Max tokens demasiado bajo
3. Error en parsing de respuesta

**Debug:**
```php
// En class-claude-provider.php línea ~180
aichat_log_debug('[Claude Provider] Raw response body', [
    'body' => $raw_body
], true);
```

---

### Logs No Aparecen

**Verificar:**
```php
// En wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('AICHAT_DEBUG', true);
```

**Archivo log:**
```
/wp-content/debug.log
```

---

## ✅ Checklist de Testing

### Pre-Testing
- [ ] API key configurada
- [ ] Nueva arquitectura activada (`aichat_use_provider_architecture = true`)
- [ ] Bot Claude creado
- [ ] Debug habilitado

### Tests Básicos
- [ ] Chat simple funciona
- [ ] Respuesta correcta y coherente
- [ ] Sin errores en log
- [ ] Pretty logs aparecen

### Tests Avanzados
- [ ] Multi-turno mantiene contexto
- [ ] System prompt se aplica
- [ ] Temperature afecta respuestas
- [ ] Fallback chain funciona
- [ ] Cost tracking en BD

### Tests de Regresión
- [ ] Legacy sigue funcionando (si desactivas nueva arch)
- [ ] OpenAI no afectado
- [ ] GPT-5 con tools sigue OK

---

## 📝 Resultados Esperados

### Logs Completos de Sesión Exitosa

```
[04-Nov-2025 08:00:00 UTC] [AIChat] [AIChat AJAX][abc-123] architecture mode=NEW (registry)
[04-Nov-2025 08:00:00 UTC] [AIChat] [AIChat AJAX][abc-123] bot id=5 slug=testclaude provider=claude model=claude-3-5-sonnet-20240620
[04-Nov-2025 08:00:00 UTC] [AIChat] [AIChat AJAX][abc-123] using NEW architecture (provider registry)
[04-Nov-2025 08:00:00 UTC] [AIChat] [AIChat Registry] Cached new instance: claude
[04-Nov-2025 08:00:00 UTC] [AIChat] [Claude Provider] Attempting model: claude-3-5-sonnet-20240620
[04-Nov-2025 08:00:02 UTC] [AIChat] [Claude Provider] Success with model: claude-3-5-sonnet-20240620 (HTTP 200)
[04-Nov-2025 08:00:02 UTC] [AIChat] [AIChat Response][abc-123] provider=claude model=claude-3-5-sonnet-20240620 tools=0
USAGE: {"prompt_tokens":45,"completion_tokens":156,"total_tokens":201}
ANSWER:
La inteligencia artificial es una rama de la informática que se centra en crear sistemas capaces de realizar tareas que normalmente requieren inteligencia humana...
[04-Nov-2025 08:00:02 UTC] [AIChat] [Claude Provider] Cost calculated | {"model":"claude-3-5-sonnet-20240620","prompt_tokens":45,"completion_tokens":156,"cost_usd":0.00027,"cost_microcents":27}
```

---

## 🚀 Próximos Pasos

Una vez completado testing manual:

1. **Marcar PASO 3 completo** ✅
2. **Iniciar PASO 4:** Testing & Documentation
3. **Migration guide** para otros providers
4. **Performance benchmarks**
5. **Unit tests suite**

---

**Fin de la guía. ¡Listo para testing!** 🎉
