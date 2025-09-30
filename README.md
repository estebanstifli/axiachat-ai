# AI Chat (WordPress Plugin)

> Update 1.1.3 (EN): Added AutoSync cron + manual trigger modal, Browse Chunks tab (local contexts), split Settings/Similarity buttons, LIMITED full rebuild fix, improved UX (persistent Run AutoSync button, disabled states), extra i18n keys, mini-tab context sync fix.
>
> Actualización 1.1.3 (ES): Nuevo sistema AutoSync (cron + ejecución manual), pestaña Browse Chunks para contextos locales, separación botones Settings/Similarity, corrección rebuild en contextos LIMITED, mejoras UX (botón persistente y estados deshabilitados), nuevas claves i18n y fix de sincronización en mini-tabs.

Un chatbot de IA personalizable para WordPress que utiliza modelos OpenAI (y soporte inicial para Claude) con recuperación opcional de contexto (RAG) mediante embeddings locales o Pinecone. Incluye interfaz flotante / inline vía shortcode, administración de múltiples bots, moderación, límites de uso, y logging de conversaciones. Integra además (de forma opcional) un flujo externo tipo WhatsApp reutilizando la misma tabla de conversaciones sin nuevos esquemas.

> Idioma: Este README está en Español con notas técnicas en Inglés cuando aporta claridad. El código ya incluye internacionalización (text‑domain `ai-chat`).

---
## Características Principales
- Múltiples bots con modelos, temperatura, tokens máximos y variaciones UI.
- Modos de contexto (RAG): embeddings locales, contenido de la página actual o ninguno.
- Ingesta de contenido (posts / PDFs) en tablas propias con embeddings (OpenAI `text-embedding-3-small`).
- Mensajes protegidos por política fija de seguridad/privacidad inyectada como primer system prompt.
- Moderación básica + heurísticas anti-spam + rate limiting adaptativo.
- Historial de conversación por `session_id` (usuario anónimo) o `user_id` (logueado) con recorte configurable (`max_messages`).
- Shortcode y widget flotante: colores, avatares, posiciones, estado minimizado, textos personalizados.
- Logs de conversaciones en el admin (listado + detalle) con borrado individual por sesión.
- Integración externa (ej. canal WhatsApp) mediante `session_id` determinístico (prefijo `wha` + dígitos) sin cambiar la base de datos.
- Localización lista (archivos `.po/.mo`).
- Modo debug (PHP y JS) para inspeccionar pipeline y contexto seleccionado.

---
## Estructura del Plugin
```
aichat.php                 // Bootstrap del plugin + activación/tablas + menús
includes/
  class-aichat-core.php     // Núcleo: helpers generales y hooks base
  class-aichat-ajax.php     // Flujo AJAX: validaciones, construcción de mensajes, llamada proveedor
  shortcode.php             // Render del contenedor y data-* attributes
  contexto-functions.php    // Embeddings, búsqueda de similitud, RAG modes, seguridad
  bots.php / bots_ajax.php  // Gestión CRUD de bots y UI admin
  logs.php / logs-detail.php// Listado y detalle de conversaciones
  moderation.php            // Moderación y spam checks
  contexto-*                // Pestañas de ingestión y PDF
assets/js/aichat-frontend.js// Frontend UI, historial, GDPR, eventos
assets/css/*.css            // Estilos admin + frontend
```

---
## Tablas Personalizadas
| Tabla | Propósito | Campos Clave |
|-------|-----------|--------------|
| `wp_aichat_bots` | Configuración de cada bot | slug, model, temperature, context_mode, límites UI |
| `wp_aichat_conversations` | Historial de turnos | session_id, user_id, bot_slug, message, response, page_id |
| `wp_aichat_contexts` | Metadatos de conjuntos de contenido | context_type, remote_endpoint, processing_status |
| `wp_aichat_chunks` | Chunks con embeddings y texto | post_id, id_context, embedding, updated_at |

Notas:
- IP se almacena en binario solo cuando se aplican límites (optimizable / puedes anonimizar más si deseas).
- No se ha creado tabla nueva para metadatos de canales externos: un prefijo en `session_id` distingue WhatsApp (`wha`) de sesiones web.

---
## Flujo Interno (Resumen)
1. Shortcode genera un `<div>` con `data-*`.
2. JS (`aichat-frontend.js`) captura input → AJAX `aichat_process_message`.
3. Servidor valida: nonce, honeypot, captcha (filtro), moderación, límites, longitud.
4. Selección de contexto (auto/local/page/none) → cálculo similitud (coseno) o consulta Pinecone.
5. Construcción de mensajes: política seguridad + instrucciones bot + bloque CONTEXT + pregunta usuario.
6. Llamada al proveedor (OpenAI / Claude) → respuesta bruta.
7. Post-proceso: sanitizar, reemplazar `[LINK]` por permalink top-chunk, guardar en `wp_aichat_conversations`.
8. JS recibe respuesta y la añade al hilo (persistencia simple por session UUID en localStorage).

---
## Instalación
1. Copiar carpeta dentro de `wp-content/plugins/` (o subir ZIP en el admin de WordPress).
2. Activar el plugin (creará tablas mediante `dbDelta`).
3. En Ajustes → AI Chat: introducir API Key de OpenAI (y/o futuro Claude / Pinecone si se habilita).
4. Crear / ajustar bots en la pestaña Bots.
5. Insertar el shortcode en una página o confiar en el widget flotante global.

### Requisitos
- WordPress ≥ 5.0
- PHP ≥ 7.4
- Extensiones: `mbstring`, `json`, `curl` (para llamadas API).

---
## Uso del Shortcode / Widget
Shortcode básico:
```
[aichat id="default"]
```
Atributo principal: `id` = slug del bot.

La mayoría de opciones UI vienen del bot (color, avatar, placeholder). Para cambios dinámicos adicionales se pueden añadir filtros PHP (ver sección Desarrollo). El widget flotante se habilita automáticamente salvo que un shortcode en la página lo suprima (bandera interna). 

---
## Configuración de Bots (Campos Clave)
- Modelo (`model`): por defecto `gpt-4o` (ajustar según provider).
- Temperatura (`temperature`): creatividad.
- `max_tokens`: recorte de respuesta (respeta límites proveedor).
- `reasoning` / `verbosity`: flags preparados para futuros modelos con capacidad de reasoning.
- `context_mode`: `embeddings` (local/Pinecone auto), `page` (contenido post actual) o `none`.
- `max_messages`: número máximo de turnos retenidos (los más antiguos se descartan). 

---
## Modo Contexto (RAG)
- Local embeddings: se cargan todos los chunks de un contexto y se calcula coseno en PHP; se ordenan y se limita a N top.
- Pinecone: si el contexto está marcado remoto (filtro de hosts permitidos) se hace query al índice.
- Page: se toma el contenido del post actual (sin embeddings) y se inyecta como bloque CONTEXT.

---
## Integración Externa (WhatsApp u otros canales)
Para reutilizar el motor sin duplicar tablas, se genera un `session_id` determinístico basado en el número de teléfono (solo dígitos) con prefijo `wha`.

Función auxiliar (ya incluida):
```php
$r = aichat_generate_bot_response_for_phone( $phone, $bot_slug, $question, [ 'page_id' => 0 ] );
// $r = [ 'ok' => bool, 'response' => string, 'error' => string|null ]
```
- Guarda turnos en `wp_aichat_conversations` con `session_id` = `wha` + dígitos.
- El admin muestra estos registros formateados como `WHA123456789`.
- Compatibilidad retro con el antiguo formato `wha_` mantenida en la vista.

Migración opcional (normalizar histórico antiguo con underscore):
```sql
UPDATE wp_aichat_conversations SET session_id = REPLACE(session_id,'wha_','wha') WHERE session_id LIKE 'wha_%';
```

Filtro rápido (si lo implementas) sugerido:
```php
// Ejemplo futuro: filtrar consultas de logs a solo WhatsApp
add_filter('aichat_logs_where', function($where){
  global $wpdb; return $where." AND session_id LIKE 'wha%'"; });
```

---
## Seguridad y Privacidad
- Nonce + honeypot + filtros de captcha/moderación.
- Política de seguridad inyectada como primer system message para evitar fuga de instrucciones internas.
- Sanitización del output antes de render (se evita HTML arbitrario).
- IP en VARBINARY (facilita anonimización posterior). Puedes truncar o hash si requieres más privacidad.

---
## Debug / QA
- Definir en `wp-config.php`: `define('AICHAT_DEBUG', true);` para activar logging PHP (`error_log`).
- Vista previa rápida: `/?aichat_preview=1&bot=slug` (solo administradores).
- JS debug: añadir `?aichat_debug=1` en la URL para mostrar trazas en consola.
- Contexto seleccionado: al enviar con POST `debug=1` se registran en logs los top scores (si debug activo).

---
## Extensibilidad (Hooks / Puntos de Entrada)
Filtros / acciones (principales) recomendados (si aún no los ves, puedes añadirlos fácilmente):
- `aichat_security_policy` para alterar la política fija.
- `aichat_validate_captcha` para conectar un captcha externo.
- `aichat_moderation_flags` (potencial) para añadir heurísticas.
- `aichat_context_results` para post-procesar chunks antes de construir mensajes.
- `aichat_provider_payload` para ajustar parámetros enviados a OpenAI/Claude.

(Consulta el código para nombres exactos presentes; añade nuevos siguiendo prefijo `aichat_`).

---
## Desarrollo Local
1. Clonar en carpeta plugins.
2. Activar el plugin dentro del admin.
3. (Opcional) Crear contenido de prueba y ejecutar ingestión PDF para poblar embeddings.
4. Revisar `includes/class-aichat-ajax.php` si deseas añadir proveedores.
5. Añadir hooks/filtros sin modificar core creando un pequeño mu-plugin o plugin complementario.

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
<script async src="https://TU-DOMINIO/wp-content/plugins/ai-chat/assets/js/aichat-embed-loader.js"></script>
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
