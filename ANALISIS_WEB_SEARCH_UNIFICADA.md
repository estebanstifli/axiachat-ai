# Análisis: Generalización de Web Search para Todos los Proveedores

## Contexto

Actualmente existe la capacidad "OpenAI Websearch" que:
- Permite búsquedas en internet vía OpenAI Responses API
- Tiene configuración de `allowed_domains` (lista blanca de dominios)
- Está implementada como macro `openai_web_search`
- Está hard-coded para OpenAI

**Pregunta del usuario**: ¿Podría esta capacidad servir para todos los modelos de cualquier proveedor (si tienen capacidad de activar internet)?

## Respuesta: **SÍ, CON ARQUITECTURAS DISTINTAS**

### Estado Actual de Web Search por Proveedor

#### 1. OpenAI (Responses API - GPT-5*)

**Implementación actual**: ✅ Funcional

**Formato de tool**:
```json
{
  "type": "web_search",
  "filters": {
    "allowed_domains": ["example.com", "trusted.org"]
  }
}
```

**Características**:
- Native tool (server-side)
- Se declara en `tools[]` del request
- OpenAI ejecuta la búsqueda automáticamente
- Devuelve resultados con sources en metadata
- Soporte de `include: ['web_search_call.action.sources']`

**Código actual**: 
- `includes/add-ons/ai-tools/api.php` líneas 185-218
- `includes/providers/class-openai-provider.php` líneas 785-790
- Filtro `aichat_openai_responses_tools`

---

#### 2. Claude (Messages API - Sonnet/Opus/Haiku 4.x)

**Implementación actual**: ❌ No implementada

**Formato de tool** (según docs Anthropic):
```json
{
  "type": "web_search_20250305",
  "name": "web_search",
  "max_uses": 5,
  "allowed_domains": ["example.com", "trusted.org"],
  "blocked_domains": ["spam.com"],
  "user_location": {
    "type": "approximate",
    "city": "San Francisco",
    "region": "California",
    "country": "US",
    "timezone": "America/Los_Angeles"
  }
}
```

**Características**:
- Server-side tool (versioned: `web_search_20250305`)
- Claude ejecuta búsquedas automáticamente
- Devuelve resultados con citations inline
- Soporte de prompt caching
- Pricing: $10 / 1,000 búsquedas + tokens
- **Requiere habilitación en Console Anthropic** (a nivel organización)

**Modelos compatibles**:
- claude-sonnet-4-5
- claude-sonnet-4
- claude-haiku-4-5
- claude-opus-4-1
- claude-opus-4

**Respuesta incluye**:
- `server_tool_use` blocks con query
- `web_search_tool_result` con resultados
- Citations automáticas en text blocks
- `usage.server_tool_use.web_search_requests` para billing

**Diferencias vs OpenAI**:
1. **Versioned type**: `web_search_20250305` (no solo `web_search`)
2. **Más opciones**: `max_uses`, `blocked_domains`, `user_location`
3. **Citations inline**: Metadata en `text.citations[]`
4. **No requiere multi-ronda**: Respuesta final incluye búsquedas ya ejecutadas

---

### Comparación de Arquitecturas

| Aspecto | OpenAI Responses | Claude Messages |
|---------|------------------|-----------------|
| **Tipo de tool** | `web_search` | `web_search_20250305` |
| **Server-side** | ✅ Sí | ✅ Sí |
| **Allowed domains** | ✅ `filters.allowed_domains` | ✅ `allowed_domains` (root) |
| **Blocked domains** | ❌ No | ✅ `blocked_domains` |
| **Max uses** | ❌ No | ✅ `max_uses` |
| **Geolocalización** | ❌ No | ✅ `user_location` |
| **Citations** | Metadata `sources` | ✅ Inline `citations[]` |
| **Pricing** | Incluido en tokens | $10/1K búsquedas + tokens |
| **Console enable** | Auto | ✅ Requiere permiso org |

---

### Propuesta: Arquitectura Unificada

#### 1. Renombrar Macro y Tool

**De**:
```php
'name' => 'openai_web_search',
'label' => 'OpenAI: Web Search',
```

**A**:
```php
'name' => 'web_search',
'label' => 'Web Search',
'description' => 'Allows the assistant to search the internet for real-time information. Compatible with OpenAI (GPT-5*) and Claude (4.x models). Configure allowed domains to restrict sources.',
```

**Cambios necesarios**:
1. `includes/add-ons/ai-tools/tools-sample.php` líneas 45-57
2. `includes/add-ons/ai-tools/api.php` líneas 188-245 (reemplazar `openai_web_search` → `web_search`)
3. `assets/js/tools.js` línea 440 (UI de allowed domains)
4. Base de datos: migrar `tools_json` existentes (UPDATE bots)

---

#### 2. Adapter OpenAI (sin cambios funcionales)

**Código actual funciona**. Solo ajustar referencias:

```php
// includes/add-ons/ai-tools/api.php
add_filter('aichat_openai_responses_tools', function( $tools, $ctx ){
    // Detectar macro 'web_search' (antes 'openai_web_search')
    $has_web_search_macro = in_array('web_search', $selected, true);
    if ( ! $has_web_search_macro ) return $tools;
    
    // Build OpenAI native web_search tool
    $ws = [ 'type' => 'web_search' ];
    if ($domains) {
        $ws['filters'] = [ 'allowed_domains' => $domains ];
    }
    $tools[] = $ws;
    return $tools;
}, 10, 2);
```

---

#### 3. Adapter Claude (NUEVA IMPLEMENTACIÓN)

**Nuevo filtro**: `aichat_claude_messages_tools`

```php
// includes/add-ons/ai-tools/api.php (nueva función)
add_filter('aichat_claude_messages_tools', function( $tools, $ctx ){
    // $ctx: ['model'=>..., 'bot'=>bot_slug]
    $bot_slug = isset($ctx['bot']) ? sanitize_title($ctx['bot']) : '';
    if ($bot_slug === '') return $tools;
    
    // Load bot capabilities
    global $wpdb; $bots_table = $wpdb->prefix.'aichat_bots';
    $row = $wpdb->get_row( $wpdb->prepare("SELECT tools_json FROM {$bots_table} WHERE slug=%s", $bot_slug), ARRAY_A );
    $selected = [];
    if ($row && !empty($row['tools_json'])) {
        $tmp = json_decode((string)$row['tools_json'], true);
        if(is_array($tmp)) $selected = array_values(array_filter($tmp, 'is_string'));
    }
    
    // Check if web_search macro is selected
    $has_web_search_macro = in_array('web_search', $selected, true);
    if ( ! $has_web_search_macro ) return $tools;
    
    // Load capability settings (allowed_domains)
    $domains = [];
    if ( function_exists('aichat_get_capability_settings_for_bot') ) {
        $cap_settings = aichat_get_capability_settings_for_bot($bot_slug);
        if ( isset($cap_settings['web_search']['domains']) && is_array($cap_settings['web_search']['domains']) ) {
            $domains = array_values(array_filter(array_map('sanitize_text_field', $cap_settings['web_search']['domains'])));
        }
    }
    
    // Build Claude web_search tool (versioned type)
    $ws = [
        'type' => 'web_search_20250305',
        'name' => 'web_search'
    ];
    
    // Optional: max uses (default 5)
    $max_uses = 5; // Podrías hacerlo configurable
    if ($max_uses > 0) {
        $ws['max_uses'] = $max_uses;
    }
    
    // Optional: allowed domains
    if ($domains) {
        $ws['allowed_domains'] = $domains;
    }
    
    // Optional: user location (podrías detectar del usuario WP)
    // $ws['user_location'] = [...];
    
    // Ensure it's not duplicated
    $found = false;
    foreach($tools as $t) {
        if( isset($t['type']) && $t['type']==='web_search_20250305' ) {
            $found=true;
            break;
        }
    }
    if ( ! $found ) {
        $tools[] = $ws;
    }
    
    return $tools;
}, 10, 2);
```

---

#### 4. Aplicar Filtro en Claude Provider

**Modificar**: `includes/providers/class-claude-provider.php`

**Método**: `build_anthropic_tools()` o en `chat_with_tools()` antes de llamar API

```php
// Línea ~460 (donde se prepara el payload para Anthropic)
private function build_anthropic_tools( $tools, $bot_slug ) {
    $normalized = [];
    
    foreach ( $tools as $tool ) {
        $type = $tool['type'] ?? '';
        
        if ( $type === 'function' ) {
            // Client tools (las 10 herramientas WordPress)
            $func = $tool['function'] ?? [];
            $normalized[] = [
                'name'         => $func['name'] ?? '',
                'description'  => $func['description'] ?? '',
                'input_schema' => $func['parameters'] ?? (object)[]
            ];
        } elseif ( $type === 'web_search' ) {
            // Server-side tool: OpenAI format → Claude format
            // (Este caso no debería ocurrir si usamos filtro específico)
            continue; // Skip, será manejado por filtro
        }
    }
    
    // Apply provider-specific filter
    $ctx = ['model' => $this->config['model'] ?? '', 'bot' => $bot_slug];
    $normalized = apply_filters('aichat_claude_messages_tools', $normalized, $ctx);
    
    return $normalized;
}
```

**O más simple**: Aplicar filtro en `chat_with_tools()` ANTES de construir payload:

```php
// Línea ~625 (en chat_with_tools antes de call_claude_with_tools)
public function chat_with_tools( $messages, $params = [] ) {
    // ... código existente ...
    
    $tools = $params['tools'] ?? [];
    $bot_slug = $params['bot_slug'] ?? '';
    
    // Apply Claude-specific tools filter
    $ctx = ['model' => $model, 'bot' => $bot_slug];
    $tools = apply_filters('aichat_claude_messages_tools', $tools, $ctx);
    
    // Build Anthropic tools format
    $anthropic_tools = $this->build_anthropic_tools($tools, $bot_slug);
    
    // ... resto del código ...
}
```

---

#### 5. Parsing de Respuesta Claude con Web Search

**Modificar**: `parse_claude_response()` para detectar `server_tool_use`

```php
// Línea ~900 (donde se parsea la respuesta)
protected function parse_claude_response( $api_response ) {
    // ... código existente ...
    
    foreach ( $content as $block ) {
        $type = $block['type'] ?? '';
        
        if ( $type === 'text' ) {
            $text_parts[] = $block['text'] ?? '';
            
            // Citations from web search
            if ( isset($block['citations']) && is_array($block['citations']) ) {
                // Formatear citations como footnotes o inline links
                foreach ( $block['citations'] as $citation ) {
                    $url = $citation['url'] ?? '';
                    $title = $citation['title'] ?? '';
                    $cited_text = $citation['cited_text'] ?? '';
                    
                    // Opcional: agregar al final del mensaje
                    // "Source: [Title](URL)"
                }
            }
        } elseif ( $type === 'server_tool_use' ) {
            // Web search query executed by Claude
            $query = $block['input']['query'] ?? '';
            aichat_log_debug("[Claude] Web search executed: {$query}");
        } elseif ( $type === 'web_search_tool_result' ) {
            // Search results returned by Anthropic
            $results = $block['content'] ?? [];
            aichat_log_debug("[Claude] Web search results: " . count($results));
        } elseif ( $type === 'tool_use' ) {
            // Client tools (existing logic)
            // ... código actual ...
        }
    }
    
    // ... resto del código ...
}
```

---

### Configuración UI Unificada

**Estado actual** (`assets/js/tools.js` línea 439-450):
```javascript
// Optional domains allowlist for web search
if (capId === 'openai_web_search'){
  const $domRow = $('<div class="mb-3"/>').appendTo($settings);
  $('<label class="form-label fw-semibold"/>').text(window.aichat_tools_i18n?.domains || 'Allowed domains').appendTo($domRow);
  // ... render domain list ...
}
```

**Cambiar a**:
```javascript
// Optional domains allowlist for web search (OpenAI + Claude)
if (capId === 'web_search'){
  const $domRow = $('<div class="mb-3"/>').appendTo($settings);
  $('<label class="form-label fw-semibold"/>').text(window.aichat_tools_i18n?.domains || 'Allowed domains').appendTo($domRow);
  $('<small class="form-text text-muted"/>').text('Optional: Restrict web searches to these domains. Works with OpenAI (GPT-5) and Claude (4.x models).').appendTo($domRow);
  // ... render domain list ...
}
```

---

### Migración de Datos

**SQL para actualizar bots existentes**:
```sql
-- Reemplazar 'openai_web_search' por 'web_search' en tools_json
UPDATE wp_aichat_bots
SET tools_json = REPLACE(tools_json, '"openai_web_search"', '"web_search"')
WHERE tools_json LIKE '%openai_web_search%';
```

**PHP migration script** (ejecutar en activation hook o admin notice):
```php
function aichat_migrate_web_search_macro() {
    global $wpdb;
    $table = $wpdb->prefix . 'aichat_bots';
    
    // Find bots with old macro
    $bots = $wpdb->get_results("SELECT id, tools_json FROM {$table} WHERE tools_json LIKE '%openai_web_search%'", ARRAY_A);
    
    foreach ( $bots as $bot ) {
        $tools = json_decode($bot['tools_json'], true);
        if ( !is_array($tools) ) continue;
        
        // Replace old macro name
        $updated = array_map(function($tool) {
            return $tool === 'openai_web_search' ? 'web_search' : $tool;
        }, $tools);
        
        // Save
        $wpdb->update(
            $table,
            ['tools_json' => wp_json_encode($updated)],
            ['id' => $bot['id']],
            ['%s'],
            ['%d']
        );
    }
    
    aichat_log_debug("[Migration] Updated " . count($bots) . " bots: openai_web_search → web_search");
}
```

---

### Ventajas de la Unificación

✅ **Single source of truth**: Una macro `web_search` para todos los proveedores
✅ **UI consistente**: Misma configuración de `allowed_domains`
✅ **Extensible**: Fácil agregar otros proveedores (Gemini, etc.)
✅ **Backward compatible**: Migración automática vía SQL
✅ **Provider-aware**: Cada adapter traduce a su formato nativo
✅ **Transparent to user**: Admin no necesita saber qué provider usa

---

### Limitaciones y Consideraciones

#### 1. Requisitos por Proveedor

**OpenAI**:
- ✅ Auto-habilitado en Responses API (GPT-5*)
- ⚠️ Solo modelos GPT-5* (no GPT-4o/3.5)
- ✅ Sin límite de búsquedas (pricing por tokens)

**Claude**:
- ⚠️ Requiere habilitación en Anthropic Console (a nivel org)
- ⚠️ Solo modelos 4.x (no 3.5/3.0)
- ⚠️ Pricing adicional: $10/1K búsquedas
- ✅ Más control: `max_uses`, `blocked_domains`

#### 2. Detección de Soporte

**Lógica de validación**:
```php
function aichat_model_supports_web_search( $provider, $model ) {
    $provider = strtolower($provider);
    $model = strtolower($model);
    
    if ( $provider === 'openai' ) {
        // GPT-5 family via Responses API
        return stripos($model, 'gpt-5') === 0;
    }
    
    if ( $provider === 'claude' ) {
        // Claude 4.x family
        return (
            stripos($model, 'sonnet-4') !== false ||
            stripos($model, 'opus-4') !== false ||
            stripos($model, 'haiku-4') !== false
        );
    }
    
    return false;
}
```

**UI feedback**:
```javascript
// En tools.js cuando se selecciona web_search
if (capId === 'web_search') {
  // Detectar modelo actual del bot
  const modelId = $('#bot_model').val();
  const provider = detectProvider(modelId); // openai, claude
  
  if (!modelSupportsWebSearch(provider, modelId)) {
    $('<div class="alert alert-warning"/>')
      .text(`⚠️ Web Search may not be available for ${modelId}. Compatible models: OpenAI GPT-5*, Claude 4.x`)
      .appendTo($settings);
  }
}
```

#### 3. Pricing Transparency

**Mostrar en UI**:
```html
<small class="form-text text-muted">
  <strong>Pricing</strong>:
  - OpenAI: Included in token costs (GPT-5* models only)
  - Claude: $10 per 1,000 searches + token costs (4.x models)
</small>
```

---

### Plan de Implementación

#### PASO 1: Renombrar Macro (bajo impacto)
- [ ] `tools-sample.php`: cambiar `openai_web_search` → `web_search`
- [ ] `api.php`: actualizar filtros y referencias
- [ ] `tools.js`: UI de `allowed_domains`
- [ ] Migración SQL en activation hook

#### PASO 2: Adapter OpenAI (sin cambios)
- [ ] Validar que funciona con nuevo nombre
- [ ] Test con GPT-5-mini + allowed domains

#### PASO 3: Adapter Claude (nueva feature)
- [ ] Crear filtro `aichat_claude_messages_tools`
- [ ] Inyectar `web_search_20250305` tool
- [ ] Parsear `server_tool_use` + `web_search_tool_result`
- [ ] Formatear citations en respuesta
- [ ] Test con claude-sonnet-4-5

#### PASO 4: Validación y Docs
- [ ] Helper `aichat_model_supports_web_search()`
- [ ] UI warning para modelos incompatibles
- [ ] Pricing info en admin
- [ ] Actualizar README y docs

---

### Conclusión

**¿Es factible?** ✅ **SÍ, 100%**

**¿Es recomendable?** ✅ **SÍ**

**Razones**:
1. **Ambos proveedores soportan server-side web search**
2. **Arquitecturas similares** (declaran tool, API ejecuta, devuelve resultados)
3. **allowed_domains compatible** (mismo concepto en ambos)
4. **Abstracción vía filtros** (cada provider traduce a su formato)
5. **Experiencia unificada** (admin no ve diferencias)

**Única diferencia real**: Pricing model (OpenAI gratis, Claude $10/1K) y requisitos de Console.

**Recomendación**: Implementar PASO 1-3 ahora, PASO 4 después de testing.
