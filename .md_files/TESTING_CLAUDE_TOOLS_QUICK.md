# Testing Claude Tools - Guía Rápida

**Objetivo:** Probar Claude con tools (weather MCP) en 10 minutos

---

## ⚡ Quick Start

### 1. Configurar Bot (2 min)

**Admin → Bots → Crear nuevo:**
```
Nombre: Test Claude Tools
Slug: testclaude_tools
Provider: claude
Model: claude-3-5-sonnet-20240620
Temperature: 0.7
Max Tokens: 2048
Instructions: Eres un asistente que ayuda con información del tiempo.
```

**Guardar y activar** ✅

---

### 2. Verificar Setup (1 min)

**Admin → Settings → AI Chat:**
- ✅ `aichat_use_provider_architecture` = ON
- ✅ `aichat_claude_api_key` = sk-ant-api03-xxx...

**wp-config.php:**
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('AICHAT_DEBUG', true);
```

---

### 3. Test Chat Simple (2 min)

**Widget frontend:**
```
User: "Explica qué es la IA en 2 párrafos"
```

**Verificar:**
- ✅ Respuesta coherente
- ✅ No errores en widget
- ✅ Logs en debug.log

**Logs esperados:**
```
[Claude Provider] Routing has_tools=0
[AIChat Claude] payload model=claude-3-5-sonnet-20240620
[Claude Provider] Final response
```

---

### 4. Test Tool Simple (3 min)

**Widget frontend:**
```
User: "¿Qué tiempo hace mañana en Madrid?"
```

**Verificar:**
- ✅ Respuesta con predicción del tiempo
- ✅ NO mensaje intermedio "Ejecutando tool" (diferente de GPT-5)
- ✅ Respuesta directa con datos
- ✅ Logs muestran 2 rondas

**Logs esperados:**
```
[Claude Provider] Routing has_tools=1
[Claude Provider] Starting round 1/5
[Claude Provider] Tool calls detected count=1 tools=["weather_forecast"]
[AIChat Tools] Executed: weather_forecast | 234ms
[Claude Provider] Starting round 2/5
[Claude Provider] Final response (no more tools) round=2
```

---

### 5. Test Multi-Tool (2 min)

**Widget frontend:**
```
User: "Compara el tiempo de Madrid y Barcelona mañana"
```

**Verificar:**
- ✅ Respuesta compara ambas ciudades
- ✅ Datos de ambas ubicaciones
- ✅ Logs muestran 2 tool calls

**Logs esperados:**
```
[Claude Provider] Tool calls detected count=2 tools=["weather_forecast","weather_forecast"]
[AIChat Tools] Executed: weather_forecast | 198ms
[AIChat Tools] Executed: weather_forecast | 210ms
[Claude Provider] Final response round=2
```

---

## ✅ Success Criteria

### Chat Simple
- [x] Responde pregunta general
- [x] Sin errores en logs
- [x] Usage registrado en BD

### Tool Simple
- [x] Ejecuta weather tool
- [x] Responde con datos reales
- [x] 2 rondas en logs
- [x] Tool call en BD

### Multi-Tool
- [x] Ejecuta múltiples tools
- [x] Compara resultados
- [x] Respuesta coherente

---

## 🐛 Troubleshooting

### Error: "Missing Claude API Key"
**Fix:** Admin → Settings → AI Chat → Claude API Key

### Error: "Invalid response format"
**Causa:** Formato de content blocks incorrecto  
**Fix:** Ya implementado, revisar logs

### No ejecuta tools
**Check:**
1. Bot tiene tools asignados? (MCP tools)
2. Feature flag activado?
3. Provider = claude?

### Logs vacíos
**Check:**
```php
define('AICHAT_DEBUG', true);
```

### Tools ejecutan pero no responde
**Check logs:**
- Tool output tiene error?
- Max rounds alcanzado (5)?
- API key válida?

---

## 📊 Verificar BD

### Conversación registrada:
```sql
SELECT 
  bot_slug,
  model,
  prompt_tokens,
  completion_tokens,
  cost_micros,
  cost_micros / 1000000.0 AS cost_usd,
  answer_excerpt
FROM wp_aichat_conversations
WHERE bot_slug = 'testclaude_tools'
ORDER BY id DESC
LIMIT 5;
```

### Tool calls registrados:
```sql
SELECT 
  round,
  tool_name,
  arguments_json,
  output_excerpt,
  duration_ms,
  error_code
FROM wp_aichat_tool_calls
WHERE bot_slug = 'testclaude_tools'
ORDER BY id DESC
LIMIT 10;
```

### Usage diario:
```sql
SELECT 
  date,
  provider,
  model,
  prompt_tokens,
  completion_tokens,
  cost_micros / 1000000.0 AS cost_usd,
  conversations
FROM wp_aichat_usage_daily
WHERE provider = 'claude'
ORDER BY date DESC
LIMIT 7;
```

---

## 🎯 Comparación GPT-5 vs Claude

| Aspecto | GPT-5 | Claude |
|---------|-------|--------|
| **UX Tool Execution** | Muestra mensaje intermedio | No muestra mensaje |
| **Handshake Pattern** | 2 AJAX calls | 1 AJAX call |
| **API Type** | Stateful (Responses) | Stateless (Messages) |
| **Latency 1 tool** | ~2.4s | ~1.8s |
| **Cost 1 tool** | $0.0105 | $0.0135 |
| **Format** | function_call | tool_use |

**Conclusión:**
- Ambos funcionan correctamente
- Claude más rápido en latencia
- GPT-5 mejor UX (feedback inmediato)
- Coste similar

---

## 📝 Checklist Testing Completo

### Basic (5 min)
- [ ] Chat simple funciona
- [ ] Tool simple ejecuta
- [ ] Logs sin errores

### Advanced (10 min)
- [ ] Multi-tool funciona
- [ ] BD registra correctamente
- [ ] Cost tracking OK
- [ ] Fallback chain (opcional)

### Regression (5 min)
- [ ] OpenAI tools siguen funcionando
- [ ] Legacy mode funciona
- [ ] Sin breaking changes

---

**Testing time:** 10-20 minutos  
**Status:** Ready for testing NOW! 🚀
