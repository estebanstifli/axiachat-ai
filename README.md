# AxiaChat AI – Free AI Chatbot for WordPress

<div align="center">

[![WordPress Plugin](https://img.shields.io/wordpress/plugin/v/axiachat-ai?style=flat-square)](https://wordpress.org/plugins/axiachat-ai/)
[![WordPress Plugin Active Installs](https://img.shields.io/wordpress/plugin/installs/axiachat-ai?style=flat-square)](https://wordpress.org/plugins/axiachat-ai/)
[![WordPress Plugin Rating](https://img.shields.io/wordpress/plugin/rating/axiachat-ai?style=flat-square)](https://wordpress.org/plugins/axiachat-ai/)
[![License](https://img.shields.io/badge/license-GPLv2-blue.svg?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.html)

**Transform your WordPress site into a 24/7 AI-powered customer service hub**

[Live Demo](https://wpbotwriter.com/) | [Documentation](https://wordpress.org/plugins/axiachat-ai/) | [Support](https://wpbotwriter.com/log-a-support-ticket/)

</div>

---

## 🚀 Overview

**AxiaChat AI** is a cutting-edge chatbot plugin that delivers instant, intelligent responses to your visitors—day or night—using the latest AI technology from **OpenAI (GPT-4, GPT-5)**, **Anthropic Claude**, and **Google Gemini**.

Unlike generic chatbots, AxiaChat AI learns from YOUR actual content using advanced **Retrieval Augmented Generation (RAG)**, providing accurate, contextual answers based on your products, services, and knowledge base.

### ✨ Key Highlights

- 🤖 **Multiple AI Providers** – OpenAI, Claude, Gemini with full model support
- 🎓 **Smart Training** – Train your bot on website pages, PDFs, and documentation
- 🔌 **MCP Integration** – Connect Model Context Protocol servers for extended capabilities
- 🌐 **Web Search** – Provider-native live internet lookups with cited sources
- 🎨 **Full Customization** – Colors, avatars, positioning, window controls
- 📊 **Usage Analytics** – Conversation logs, cost tracking, token monitoring
- 🔒 **GDPR Compliant** – Built-in consent management and privacy controls
- 🚫 **Spam Protection** – Rate limiting, moderation, and advanced security

---

## 💼 Perfect For

- **E-commerce stores** – Answer product questions instantly
- **Service businesses** – Qualify leads and book appointments 24/7
- **SaaS platforms** – Reduce support tickets with AI-powered help
- **Educational sites** – Provide instant course information
- **Corporate websites** – Scale support without scaling headcount

---

## 🎯 Real Results

✅ **Reduce support workload** by handling common questions automatically  
✅ **Capture leads 24/7** even when your team is offline  
✅ **Improve engagement** with instant, helpful responses  
✅ **Lower bounce rates** by helping visitors find what they need  
✅ **Scale support** without increasing team size  

---

## 📦 Features

### 🤖 AI & Model Support
- Multiple AI providers: OpenAI (GPT-4, GPT-5), Anthropic Claude, Google Gemini
- MCP Server Integration for extended capabilities
- Provider-native web search with cited sources
- Multiple independent bots with unique configurations
- Advanced AI tools: email notifications, custom actions

### 🎓 Smart Training & Context
- **RAG (Retrieval Augmented Generation)** – Train on your content
- Context modes: embeddings (local or Pinecone) / page-specific / none
- PDF ingestion for knowledge base creation
- Auto-sync to detect and update changed content
- Smart chunking with overlap for better retrieval
- Easy Config Wizard for guided setup

### 🎨 Customization & Deployment
- Floating global widget OR inline embedding via shortcode
- Full UI control: colors, avatars, positioning
- Draggable, minimizable, closable chat panel
- 9 avatar designs included + custom upload
- Responsive design for desktop and mobile

### 📊 Usage Control & Analytics
- Conversation logging with ON/OFF toggle
- Daily usage limits (per-user and global)
- Cost tracking and token monitoring
- Detailed conversation logs with filtering

### 🔒 Security & Compliance
- GDPR-compliant with consent management
- WordPress security best practices
- Encrypted API keys
- Data ownership – all on your server
- No external SaaS dependencies

---

## 🛠️ Installation

### Quick Start (5 minutes)

1. **Install the Plugin**
   ```bash
   # Via WordPress Admin
   Plugins > Add New > Search "AxiaChat AI" > Install > Activate
   
   # Or via WP-CLI
   wp plugin install axiachat-ai --activate
   ```

2. **Add Your API Key**
   - Navigate to **AxiaChat AI > Settings**
   - Enter your OpenAI, Claude, or Gemini API key

3. **Run Easy Config Wizard** (Recommended)
   - Automatically scans your site content
   - Creates optimized context with embeddings
   - Links your default bot

4. **Deploy Your Bot**
   
   **Option A – Global Widget:**
   ```
   Settings > Enable Global Widget > Select Bot
   ```
   
   **Option B – Shortcode:**
   ```
   [aichat id="your-bot-slug"]
   ```

---

## 📖 Usage Examples

### Basic Shortcode
```php
[aichat id="support-bot"]
```

### Customized Shortcode
```php
[aichat id="sales-bot" layout="inline" color="#ff6b6b" avatar="5"]
```

### PHP Integration
```php
<?php echo do_shortcode('[aichat id="my-bot"]'); ?>
```

---

## 🏗️ Architecture

```
axiachat-ai/
├── axiachat-ai.php           # Plugin bootstrap & activation
├── includes/
│   ├── class-aichat-core.php      # Core functionality
│   ├── class-aichat-ajax.php      # AJAX request handler
│   ├── contexto-functions.php     # RAG & embeddings
│   ├── bots.php                   # Bot management
│   ├── moderation.php             # Spam & security
│   └── add-ons/
│       ├── mcp/                   # MCP server integration
│       └── ai-tools/              # AI capabilities system
├── assets/
│   ├── js/
│   │   ├── aichat-frontend.js     # Frontend UI & logic
│   │   └── settings.js            # Admin settings
│   └── css/
│       ├── aichat-frontend.css    # Chat widget styles
│       └── aichat-admin.css       # Admin panel styles
└── languages/                     # i18n files (Spanish included)
```

### 📁 Database Schema

| Table | Purpose | Key Fields |
|-------|---------|------------|
| `wp_aichat_bots` | Bot configurations | slug, model, temperature, context_mode, UI settings |
| `wp_aichat_conversations` | Conversation history | session_id, user_id, bot_slug, message, response |
| `wp_aichat_contexts` | Content metadata | context_type, remote_endpoint, processing_status |
| `wp_aichat_chunks` | Embeddings & content | post_id, id_context, embedding, score |

---

## 🔧 Configuration

### Environment Variables

```php
// wp-config.php
define('AICHAT_DEBUG', true);              // Enable debug logging
define('AICHAT_DEBUG_SYS_MAXLEN', 1000);   // Max debug message length
```

### Bot Settings

Each bot can be configured with:
- **Provider & Model** – OpenAI, Claude, or Gemini
- **Temperature** – Control creativity (0.0 - 2.0)
- **Max Tokens** – Response length limit
- **Context Mode** – Embeddings, page-specific, or none
- **UI Customization** – Colors, avatars, positioning
- **Tools & Capabilities** – Web search, email, custom actions

---

## 🎨 Customization Examples

### Custom Colors & Avatar
```php
[aichat id="sales-bot" color="#ff6b6b" avatar="5" placeholder="How can I help you today?"]
```

### Inline Layout
```php
[aichat id="support-bot" layout="inline" width="600px" height="500px"]
```

### Minimized by Default
```php
[aichat id="my-bot" layout="floating" position="bottom-right" minimized="true"]
```

---

## 🔌 Developer Hooks & Filters

### Available Filters

```php
// Modify security policy
add_filter('aichat_security_policy', function($policy) {
    return $policy . "\nCustom security rule here.";
});

// Customize context results
add_filter('aichat_context_results', function($results, $question) {
    // Modify or filter context chunks
    return $results;
}, 10, 2);

// Validate custom CAPTCHA
add_filter('aichat_validate_captcha', function($valid) {
    // Add your CAPTCHA validation
    return $valid;
});
```

### Available Actions

```php
// After response is generated
add_action('aichat_after_response', function($response, $bot_slug) {
    // Log, notify, or process response
}, 10, 2);

// After conversation is saved
add_action('aichat_conversation_saved', function($conversation_id) {
    // Custom post-processing
}, 10, 1);
```

---

## 🧪 Testing & Debugging

### Enable Debug Mode
```php
// wp-config.php
define('AICHAT_DEBUG', true);
```

### Preview Bot
```
https://yoursite.com/?aichat_preview=1&bot=your-slug
```

### Debug JavaScript
```
https://yoursite.com/page/?aichat_debug=1
```

### Check Logs
- PHP: `wp-content/debug.log`
- AI Logs: `wp-content/debug_ia.log`
- Admin: **AxiaChat AI > Logs**

---

## 🌐 Internationalization

AxiaChat AI is translation-ready with full support for:
- Spanish (es_ES) – Included
- Custom translations via `.po/.pot` files
- RTL language support
- Text domain: `axiachat-ai`

### Creating Translations
```bash
# Generate .pot file
wp i18n make-pot . languages/axiachat-ai.pot

# Create translation
msgfmt languages/axiachat-ai-fr_FR.po -o languages/axiachat-ai-fr_FR.mo
```

---

## 🚀 Performance Optimization

### Best Practices

1. **Context Management**
   - Use specific contexts instead of "all content"
   - Limit context size to 50-100 chunks
   - Enable auto-sync for fresh content

2. **API Usage**
   - Set appropriate token limits per bot
   - Enable daily usage limits
   - Monitor costs in Usage dashboard

3. **Caching**
   - Enable object caching (Redis/Memcached)
   - Use page caching (excludes chat widget)

4. **Database**
   - Regularly clean old conversation logs
   - Index custom tables for large sites

---

## 🛡️ Security Features

### Built-in Protection

- ✅ **Nonce validation** on all AJAX requests
- ✅ **Honeypot field** for bot detection
- ✅ **Rate limiting** per IP/user
- ✅ **Spam detection** with heuristics
- ✅ **Content moderation** filters
- ✅ **CAPTCHA support** via filters
- ✅ **Encrypted API keys** in database
- ✅ **SQL injection prevention** with prepared statements
- ✅ **XSS protection** with output escaping
- ✅ **CSRF protection** with WordPress nonces

---

## 📊 Analytics & Monitoring

### Usage Dashboard

Track your chatbot performance:
- **Today's Stats** – Messages, tokens, costs
- **7-Day Trends** – Usage patterns
- **30-Day Overview** – Monthly analytics
- **Top Models** – Most used AI models
- **Cost Tracking** – API spending by provider

### Conversation Logs

Review all interactions:
- Filter by bot, date, user
- Search message content
- View conversation threads
- Export for analysis

---

## 🤝 Contributing

We welcome contributions! Here's how you can help:

1. **Report Bugs** – [Open an issue](https://github.com/estebanstifli/axiachat-ai/issues)
2. **Suggest Features** – Share your ideas
3. **Submit PRs** – Improvements welcome
4. **Translate** – Help localize the plugin

### Development Setup

```bash
# Clone repository
git clone https://github.com/estebanstifli/axiachat-ai.git

# Install in WordPress
cd wp-content/plugins/
ln -s /path/to/axiachat-ai axiachat-ai

# Activate plugin
wp plugin activate axiachat-ai
```

---

## 📝 Changelog

### Version 1.2.6 (Latest)
- ✨ Added MCP Server Integration
- ✨ Provider-native web search (OpenAI, Claude, Gemini)
- ✨ AI Tools system with email notifications
- 🐛 Fixed context selection for multi-bot setups
- 🔧 Improved GDPR consent flow
- 📚 Enhanced documentation

### Version 1.2.5
- ✨ Added Gemini provider support
- ✨ Usage analytics dashboard
- 🐛 Fixed PDF ingestion for large files
- 🔧 Performance improvements

[View Full Changelog](https://wordpress.org/plugins/axiachat-ai/#developers)

---

## 📄 License

This plugin is licensed under the **GPLv2 or later**.

```
Copyright (C) 2024 AxiaChat AI

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

---

## 🙏 Support & Resources

- 📖 [Documentation](https://wordpress.org/plugins/axiachat-ai/)
- 💬 [Support Forum](https://wordpress.org/support/plugin/axiachat-ai/)
- 🎫 [Premium Support](https://wpbotwriter.com/log-a-support-ticket/)
- 🌐 [Official Website](https://wpbotwriter.com/)
- 📺 [Video Tutorials](https://youtu.be/Th41gGUH7Es)

---

## ⭐ Show Your Support

If you find AxiaChat AI helpful, please:
- ⭐ [Rate it 5 stars](https://wordpress.org/support/plugin/axiachat-ai/reviews/#new-post) on WordPress.org
- 🐦 Share it on social media
- 💡 [Request features](https://wpbotwriter.com/log-a-support-ticket/) you'd like to see

---

<div align="center">

**Made with ❤️ for the WordPress community**

[WordPress.org](https://wordpress.org/plugins/axiachat-ai/) • [GitHub](https://github.com/estebanstifli/axiachat-ai) • [Support](https://wpbotwriter.com/log-a-support-ticket/)

</div>

### Añadir un Nuevo Proveedor (Resumen)
- Detectar proveedor en `process_message`.
- Normalizar `$messages` → llamada API → devolver array uniforme `[ 'message' => '...' ]` o `[ 'error' => '...' ]`.
- Respetar sanitización final y placeholders `[LINK]`.

---
## Rendimiento
- Cálculo de similitud local: O(N) sobre chunks del contexto (optimizable con pre-filtrado / ANN si escala).
- Considera paginar o limitar ingestión muy grande; uso de Pinecone recomendado para > algunos miles de chunks.

---
## Internacionalización (i18n)
- Text domain: `ai-chat`.
- Cargar traducciones en `init` prioridad 1.
- Archivos en `/languages` (`.pot`, `.po`, `.mo`).

---
## Resolución de Problemas
| Problema | Posible Causa | Solución |
|----------|---------------|----------|
| El widget no aparece | No se encoló JS/CSS o tema carece de `wp_footer()` | Ver consola / asegúrate de que shortcode no desactiva global |
| Respuestas vacías | API Key incorrecta o límite de tokens | Revisar ajustes y logs PHP (debug) |
| Contexto no se aplica | `context_mode` mal configurado o embeddings no procesados | Ver tabla `wp_aichat_chunks` y estado en `wp_aichat_contexts` |
| Rate limit inmediato | Muchas peticiones en ventana corta | Ajustar umbrales en `aichat_rate_limit_check` |
| Mensajes marcados spam | Heurística repeticiones o exceso enlaces | Reducir repetición / editar función `aichat_spam_signature_check` |

---
## Changelog (Breve)
- 1.1.3: Nuevo sistema AutoSync (cron + ejecución manual), pestaña Browse Chunks para contextos locales, separación botones Settings/Similarity, corrección rebuild en contextos LIMITED, mejoras UX (botón persistente y estados deshabilitados), nuevas claves i18n y fix de sincronización en mini-tabs.
- 1.1.2: Prefijo WhatsApp `wha` (compatibilidad `wha_`), wrapper externo teléfono, mejoras logs.
- 1.1.x: Gestión bots, RAG local/Pinecone, logs detallados, moderación, UI flotante.

(Completa este changelog conforme avances.)

---
## Roadmap Sugerido
- Filtro directamente en logs: "Solo WhatsApp".
- Métricas básicas (nº preguntas / bot / día).
- Cache embeddings en memoria transitoria.
- Añadir tests unitarios (WP-CLI + PHPUnit) para similitud y sanitización.
- Integración nativa Pinecone (si no finalizada) con configuración UI.

---
## Licencia
GPL-2.0+ (ver cabecera del archivo principal y `LICENSE`).

---
## Soporte / Contribuciones
Pull requests y sugerencias bienvenidas. Para cambios grandes abre primero un issue describiendo motivación y enfoque.

---
¡Disfruta construyendo experiencias conversacionales en tu WordPress! 🤖

---
## Embed Anywhere (Script Mode)
Además del iframe, ahora puedes insertar el widget directamente en sitios externos con un simple script.

### 1. Habilitar orígenes permitidos
Desde la página de ajustes (sección "Embed (External Sites)") añade cada origen permitido uno por línea.

Alternativamente vía código (ejemplo rápido):
```php
update_option('aichat_embed_allowed_origins', "https://externo1.com\nhttps://otraweb.net");
```
Solo esos dominios recibirán un `nonce` válido desde `/?aichat_embed_nonce=1`.

### 2. Snippet externo
```html
<div id="aichat-embed" data-bot="default"></div>
<script async src="https://TU-DOMINIO/wp-content/plugins/axiachat-ai/assets/js/aichat-embed-loader.js"></script>
```
Opciones:
- `data-bot="slugBot"`
- (En futuras mejoras) podrás añadir `data-color`, etc.

### 3. Cómo funciona internamente
1. El loader detecta contenedores `#aichat-embed` o `[data-aichat-embed]`.
2. Solicita un nonce a `/?aichat_embed_nonce=1` (valida `HTTP_ORIGIN`).
3. Crea un Shadow DOM para aislar estilos y añade `aichat-frontend.css`.
4. Inserta un `<div class="aichat-widget" data-bot="...">` dentro del shadow.
5. Empuja el nodo a `window.AIChatEmbedRoots`.
6. Carga el script estándar `aichat-frontend.js` (modificado mínimamente para soportar raíces externas) → inicializa igual que en el sitio principal.

### 4. Fallbacks
- Si el navegador no soporta Shadow DOM, el loader degrada usando el propio contenedor host.
- Si el origen no está permitido: muestra mensaje `Embed error (nonce)`.

### 5. Seguridad
- Doble validación: endpoint nonce + bloqueo temprano en AJAX si `HTTP_ORIGIN` no coincide con la lista.
- No se expone la API Key al cliente.
- Rate limiting existente sigue funcionando (IP del visitante externo).
 - CORS: el endpoint de nonce y las llamadas AJAX devuelven `Access-Control-Allow-Origin` solo para orígenes permitidos. Asegúrate de no incluir barras finales ni rutas; solo esquema + host (opcional puerto). Ej: `https://externo.com`.
 - Shadow DOM: el core ahora inicializa explícitamente widgets dentro de `window.AIChatEmbedRoots` aunque jQuery no los pueda seleccionar desde el `document` principal.

### 6. Iframe (alternativa rápida)
Sigue siendo compatible:
```html
<iframe src="https://TU-DOMINIO/?aichat_embed=1&bot=default" style="width:420px;height:580px;border:0;" allow="microphone"></iframe>
```

### 7. Próximos pasos sugeridos
- Panel de administración para gestionar `aichat_embed_allowed_origins`.
- Permitir overrides de color/posición vía atributos.
- Métrica específica de sesiones externas (prefijo session id distinto si deseas segmentar).
- Auto‑reintentos en la carga del core si el script principal tarda excesivamente o es bloqueado.
- Modo sin Shadow DOM opcional (`data-no-shadow="1"`) para temas con CSP restrictivo (pendiente de implementar).

### 8. Troubleshooting Embed (Script Mode)
| Síntoma | Causa Probable | Acción |
|---------|----------------|--------|
| `Embed error (nonce)` | Origen no listado o formato con espacios/barras | Revisar opción `aichat_embed_allowed_origins` (una línea por origen, sin `/` final) |
| No aparece el widget (contenedor vacío) | Core `aichat-frontend.js` no cargó (ruta bloqueada, caché) | Ver Network: cargar `.../assets/js/aichat-frontend.js` y limpiar caché CDN |
| Aparece `__AIChatCoreLoaded` pero 0 instancias | (Versión anterior) Shadow DOM no inicializado | Actualizar a versión con parche Shadow DOM (ya incluido aquí) |
| CSS sin aplicar | `aichat-frontend.css` bloqueado por CSP / AdBlock | Ver consola (violaciones CSP) y permitir ruta CSS |
| AJAX 200 pero sin respuesta del bot | Límite de uso alcanzado o moderación | Revisar respuesta JSON (`limit_type` / mensaje) |

Snippet rápido para inspección en consola externa (devtools):
```js
(() => {const r = window.AIChatEmbedRoots||[];console.log('AIChat roots', r.length, r);console.log('AIChatVars', window.AIChatVars);fetch('https://TU-DOMINIO/?aichat_embed_nonce=1').then(r=>r.json()).then(j=>console.log('Nonce check', j));})();
```

---
