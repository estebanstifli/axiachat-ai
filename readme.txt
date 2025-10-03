=== AxiaChat AI ===
Contributors: estebandezafra
Tags: chatbot, ai, openai, chat, assistant
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Flexible AI chatbot: multiple bots, OpenAI / Claude support, shortcode or floating widget, optional RAG with local or Pinecone embeddings.

== Description ==
AI Chat lets you add one or more AI‑powered chatbots to your WordPress site. Each bot can have its own model settings, instructions, UI colors, avatar and placement. It uses the OpenAI API (you must provide your own API key) and can augment answers with contextual data (documents, posts or imported PDF content) using a basic Retrieval Augmented Generation workflow.

Conversation logs are stored locally (can be disabled) so you can review usage (see GDPR notes). All AI processing happens via direct calls from your server to OpenAI—no external SaaS proxy.

== Features ==
* Multiple bots with individual configuration (model, temperature, context mode, UI appearance)
* Floating global widget OR inline embedding via shortcode
* Context modes: embeddings / page / none
* PDF & content ingestion to build embeddings (RAG‑style answers)
* Conversation logging ON/OFF toggle (user/session/IP linkage; IP only if limits enabled)
* Daily usage limits (per user/IP & global) with behaviors: disable inputs or hide widget
* GDPR consent bubble inside the chat stream (blocks input until accepted)
* Customizable UI: color, position, avatars, placeholder, start sentence, button label, draggable / minimizable panel (minimized by default optional)
* Shortcode attribute overrides for quick per‑page customization
* Action hooks: `aichat_after_response`, `aichat_conversation_saved` (extend for analytics, etc.)
* Security: nonces + capability checks, prepared queries, escaping
* Translation ready (text domain: axiachat-ai – formerly ai-chat) – Spanish included
* Clean uninstall (removes options; keeps conversation tables unless you delete them manually)
* Local vendor assets (Bootstrap / Icons) so no external CDN dependency

== Installation ==
1. Upload the `aichat` folder to `/wp-content/plugins/` (or install via Plugins > Add New).
2. Activate the plugin through the 'Plugins' screen.
3. Go to AI Chat > Settings and add your OpenAI API key.
4. Create or edit a Bot in AI Chat > Bots (set model, appearance, flags).
5. (Optional) Add contextual content in AI Chat > Context (ingest PDFs or posts for embeddings).
6. Place a bot:
   * Inline: add `[aichat id="your-bot-slug"]` in a post or page.
   * Floating global widget: enable Global widget in Settings and select a bot.

== Usage ==
After installation, create at least one bot and (optionally) ingest context. Use the shortcode or enable the global widget. Adjust window control flags (closable, minimizable, draggable) in the bot settings.

== Easy Config Wizard ==
Version 1.1.2 introduces an optional "Easy Config" wizard that appears after initial activation (or until completed). It performs:
1. Site scan: collects up to the most recent 200 published posts, pages (and products if WooCommerce detected).
2. Context creation: creates a local embeddings context record.
3. Batch indexing: chunks are created by generating embeddings for discovered items (10 per batch via AJAX) using your OpenAI API key.
4. Bot linking: links the default bot (slug "default") to the new context with context mode = embeddings.

Smart Mode (experimental):
If available the wizard now uses a smart discovery mode prioritizing:
* Homepage and its internal links
* Legal / FAQ / About / Policy pages (slug heuristics)
* WooCommerce top categories and a sample of recent products (if WooCommerce active)
* Fallback to a few recent posts/pages if the set is too small
Legacy behavior is still used internally as fallback.

Real Chunking:
From 1.1.2+ the indexing process splits long content into multiple overlapping chunks (~1000 words, ~180 overlap) each embedded separately, improving retrieval precision. Existing single-row entries will be transparently replaced on re‑index.

Selective Indexing:
The wizard lets you deselect discovered pages/products before indexing to reduce token usage and noise. At least one item must remain selected.

Notes:
* The wizard hides automatically after completion (flag stored in option `aichat_easy_config_completed`).
* You can abort and later ingest additional or different content via the Context screens.
* If you already manually configured a bot/context you can ignore the wizard and mark it complete by finishing or deleting the option.
* Current limit (for safety) is 200 items; extend manually if needed.

Troubleshooting:
* If embeddings fail, check that your OpenAI API key is valid and that the server can reach api.openai.com.
* Browser console (when AICHAT_DEBUG true) will include extra debug logs.

== Shortcode Reference ==
Basic:
`[aichat id="bot-slug"]`

Aliases: also `bot="bot-slug"`.

Optional overrides (if supported):
* title="Custom Title"
* placeholder="Ask me anything..."
* layout="floating|inline"
* position="br|bl|tr|tl|bottom-right|bottom-left|top-right|top-left"
* class="extra-css-class"

Example:
`[aichat id="support-bot" title="Support Assistant" layout="floating" position="bottom-left"]`

== Data Storage ==
Custom tables:
* wp_aichat_conversations – message, response, timestamps, page_id, session_id, bot slug, optional user_id, IP (binary) if limits active
* wp_aichat_contexts – context definitions
* wp_aichat_chunks – content chunks & embeddings
* wp_aichat_bots – bot configuration

Options store API key, limits, GDPR, widget settings. Tables are not auto‑dropped on uninstall (safety).

== Privacy ==
User prompts (and selected context snippets) are sent to OpenAI. Content may contain personal data if users type it. Inform users and obtain consent where required. Logging can be disabled; if enabled, data stays on your server.

== GDPR / Compliance Notes ==
* Data Processor: OpenAI
* Lawful basis: legitimate interest or consent (consult legal counsel)
* IP Storage: Binary IP kept only when enforcing per-user/IP limits
* Right to erasure: Delete rows manually (future export/anonymize tool planned)
* Consent: Optional in‑stream consent bubble blocks input until accepted
* Recommendation: Add a privacy note near the chat input

== Security ==
* Nonces on AJAX (`aichat_ajax`, delete actions, etc.)
* Capability checks (`manage_options`) for admin screens
* Prepared statements / sanitization for user input
* Escaped output in admin & front‑end templates
* API key stored in an option (not exposed publicly)

== Performance ==
* Assets only enqueued when needed; versioned with filemtime
* Embedding/PDF ingestion can be heavy—schedule during low traffic
* Lightweight front‑end footprint otherwise

=== AxiaChat AI ===
Contributors: estebandezafra
Tags: chatbot, ai, openai, chat, assistant, embeddings, rag
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
AxiaChat AI is a flexible AI chatbot for WordPress: multiple bots, contextual retrieval (embeddings), PDF ingestion, usage limits, logging, and a guided setup wizard.

== Description ==
AxiaChat AI lets you add one or more AI‑powered chatbots to your site. Each bot defines model settings, instructions, UI style and context strategy. Enhance replies with your own content (posts, pages, products, PDFs) via local embeddings for Retrieval Augmented Generation (RAG) answers.

All calls go directly from your server to OpenAI (bring your own API key). No external proxy layer. Conversation logging is optional and can be disabled for privacy.

== Key Features ==
* Multiple independent bots (model, temperature, context mode, UI)
* Floating global widget or inline shortcode
* Context modes: embeddings / page / none
* PDF & content ingestion → embeddings (local RAG)
* Easy Config wizard (scan, create context, index, link bot)
* Conversation logging toggle (store or disable)
* Daily usage limits (per user/IP + global) with hide/disable behaviors
* GDPR consent bubble (blocks input until accepted)
* Customizable UI (color, position, avatars, placeholder, start sentence, window controls)
* Shortcode attribute overrides per page
* Action hooks for extension (e.g. `aichat_conversation_saved`)
* Security: nonces, capability checks, prepared SQL, escaping
* Local vendor assets (Bootstrap / Icons) – no CDN reliance
* Translation ready (text domain: axiachat-ai) – Spanish included
* Clean uninstall (options removed; data tables preserved unless manually deleted)

== Installation ==
1. Upload the `aichat` folder to `/wp-content/plugins/` (or install via Plugins > Add New).
2. Activate the plugin.
3. Go to AxiaChat AI > Settings and add your OpenAI API key.
4. Create or edit a Bot (model, UI, behavior).
5. (Optional) Ingest content under AxiaChat AI > Context to build embeddings.
6. Embed:
   * Inline: `[aichat id="your-bot-slug"]`
   * Global floating widget: enable it in Settings and choose a bot.

== Usage Basics ==
Create a bot, optionally index content, then place it inline or via the global widget. Adjust window flags (closable/minimizable/draggable) and placeholders from the bot configuration.

== Easy Config Wizard ==
The wizard streamlines first‑time setup:
1. Scan recent site content (posts/pages/products)
2. Create a local context
3. Batch embedding (chunking large content, 10 items per AJAX batch)
4. Link the default bot to the context (embeddings mode)

Smart discovery prioritizes homepage links, legal/FAQ/about pages, key WooCommerce categories/products, and falls back to recent posts if needed.

Chunking splits long content into overlapping segments for better semantic retrieval. You can deselect items before indexing to limit token usage.

== Shortcode Reference ==
Basic: `[aichat id="bot-slug"]`
Optional attributes:
* title="Custom Title"
* placeholder="Ask me anything..."
* layout="floating|inline"
* position="br|bl|tr|tl|bottom-right|bottom-left|top-right|top-left"
* class="extra-css-class"

Example: `[aichat id="support-bot" title="Support Assistant" layout="floating" position="bottom-left"]`

== Data Storage ==
Custom tables:
* wp_aichat_conversations (messages, responses, meta)
* wp_aichat_contexts (context definitions & progress)
* wp_aichat_chunks (embedded content chunks)
* wp_aichat_bots (bot configuration)

Options store API key, limits, GDPR, widget settings. Tables persist on uninstall for safety.

== Privacy ==
User prompts and selected context snippets are sent to OpenAI. If users may enter personal data, disclose this and obtain consent where required. Logging can be disabled; if enabled, data remains on your server.

== GDPR / Compliance Notes ==
* Processor: OpenAI
* Basis: legitimate interest or consent (consult legal counsel)
* IP storage: only when per‑user/IP limits active (binary format)
* Erasure: delete conversation rows manually (export/anonymize planned)
* Consent: optional in‑stream bubble (cookie `aichat_gdpr_ok`)
* Recommendation: add a privacy notice near the chat widget

== Security ==
* Nonces on all AJAX endpoints
* `manage_options` capability gate for admin actions
* Prepared / parameterized DB queries
* Sanitization + escaped output
* Key stored in an option (not exposed on front end)
 * Reinforced input handling: centralized helpers (session id clamp, bounded ints), validated JSON patch size (20KB cap), hardened file upload MIME/size checks, sanitized captcha payload

== Performance ==
* Front‑end loads only essential JS/CSS when needed
* Chunk indexing can be resource intensive—run during low traffic
* Lightweight runtime after initial context build

== Bundled Libraries ==
* Bootstrap (local)
* Bootstrap Icons (local)
* smalot/pdfparser (LGPLv3) for PDF extraction

== Translation ==
* Text Domain: `axiachat-ai`
* Domain Path: `/languages`

== External Services / Data Disclosure ==
This plugin can connect to the following third‑party APIs depending on which features you enable. You (the site owner) must supply the API keys. No keys are bundled and no traffic is proxied through a vendor server controlled by this plugin author.

=== 1. OpenAI ===
Used for: chat completions / responses, embeddings (context indexing), moderation (safety checks).

Endpoints used (HTTPS):
* https://api.openai.com/v1/chat/completions (legacy Chat Completions)
* https://api.openai.com/v1/responses (Responses API – new unified endpoint if configured)
* https://api.openai.com/v1/embeddings (document/post/PDF embedding + wizard indexing)
* https://api.openai.com/v1/moderations (content moderation)

Data Sent:
* User prompt text (per message) and limited conversation history (trimmed for token control)
* System / policy instructions (security + privacy policy + bot instructions)
* Optional retrieved context snippets (only the selected top‑N chunks or page excerpt – never the full original document)
* Embedding requests: raw chunk text produced from your site’s content or uploaded PDFs
* Moderation: only the user prompt text (not the entire history)

Data Retention (Your Server):
* Conversation log rows (if logging enabled) including user prompt, model reply, timestamps, bot slug, session id, optional user id. IP (binary) only stored when per‑IP limits are turned on. Disable logging to stop storing new rows.
* Embeddings table stores numeric vectors generated from your content (not reversible plaintext) plus the original chunk text for retrieval.

Recommendations:
* Update your privacy policy to disclose sending user prompts and limited site content to OpenAI for processing.
* Disable logging or periodically purge if you process personal data.

Legal / Docs:
* Terms: https://openai.com/policies/terms-of-use
* Privacy: https://openai.com/policies/privacy-policy
* Usage Policies: https://openai.com/policies/usage-policies

Opt‑Out / Control:
* Remove the OpenAI API key in Settings to stop all OpenAI calls (bots will refuse to answer).
* Disable conversation logging.
* Limit context ingestion to non‑sensitive pages.

=== 2. Anthropic (Claude) ===
Used for: alternative chat completions via Claude models (messages API) when a bot provider is set to Anthropic/Claude.

Endpoint used (HTTPS):
* https://api.anthropic.com/v1/messages

Data Sent:
* A rewritten message array: system instructions + user prompt + condensed prior turns (trimmed) + optional retrieved context snippets.
* Model identifier, max tokens / temperature style parameters.

Headers:
* `x-api-key` (your key) and `anthropic-version` (currently 2023-06-01 set in code).

Retention (Your Server):
* Same as OpenAI notes for conversation logging (the provider choice does not change local storage schema).

Legal / Docs:
* Terms: https://www.anthropic.com/legal/terms-of-service
* Privacy: https://www.anthropic.com/legal/privacy
* Usage Policy: https://www.anthropic.com/legal/aup

Opt‑Out / Control:
* Leave the Claude API key blank; those bots will fallback/refuse if provider requires it.
* Switch provider per bot back to OpenAI.

=== 3. Pinecone (Optional Remote Vector Store) ===
Only used if you explicitly create a Context with remote type "Pinecone". Local context mode (default) never contacts Pinecone.

Endpoint Pattern:
* Region/index specific HTTPS endpoints you enter (example placeholder: https://controller.pinecone.io and index query/upsert endpoints under *.pinecone.io). The plugin validates host against an allowlist containing pinecone.io (filter extendable).

Used For:
* Upserting embeddings (during context indexing / syncing)
* Querying similar vectors when answering a question in that context.

Data Sent:
* Vectors (embedding arrays) and associated metadata (post/page IDs, titles, short chunk text) for upsert.
* Query: the embedding vector of the user question + top‑K request.

Retention:
* Stored inside your Pinecone project (not on this plugin server). Local DB still keeps minimal reference metadata if you mix modes.

Legal / Docs:
* Terms: https://www.pinecone.io/terms/
* Privacy: https://www.pinecone.io/privacy/
* Security: https://www.pinecone.io/security/

Opt‑Out / Control:
* Do not configure a remote Pinecone context; use local mode or page mode instead.
* Delete the remote context to stop future upserts/queries.

=== 4. Embedded Loader (Your Site’s Frontend) ===
The public embed script served from your own domain loads the chat UI. It calls only your WordPress `admin-ajax.php` endpoint (dynamic URL) – no third‑party directly from the browser.

Data Flow (Browser → Your Server → Provider):
1. Browser sends user message + bot slug + nonce to your server.
2. Server validates (nonce, honeypot, optional moderation) and selects provider.
3. Server sends sanitized payload to OpenAI/Anthropic (and optionally Pinecone for retrieval).
4. Response sanitized and returned to browser.

No external JS/CDN calls are required; Bootstrap & Icons are bundled locally.

== Roadmap ==
== Roadmap ==
* Conversation export / anonymize tooling
* Expanded analytics dashboard
* Additional AI providers (Azure OpenAI, Anthropic, etc.)
* Retention policies (auto prune)
* Rules/Actions engine for conditional bot behavior (planned)

== FAQ ==
**Do I need an OpenAI API key?**
Yes. Provide your own key in Settings.

**Can I disable storing conversations?**
Yes. Toggle logging off; existing rows remain until manually deleted.

**How do usage limits work?**
Set per-user/IP and global caps; widget can hide or disable input when exceeded.

**How is consent handled?**
Optional consent bubble blocks input until accepted.

**Any third‑party calls besides OpenAI?**
Yes, optionally Claude (Anthropic) and Pinecone if you configure those keys/contexts. See the "External Services / Data Disclosure" section for full details.

**Change floating widget position?**
Use bot/global settings or shortcode position attribute.

**Is it translation ready?**
Yes (includes Spanish).

**Will more AI providers be supported?**
Planned.

== Screenshots ==
1. Floating chat widget
2. Bot configuration
3. Context ingestion & indexing

== Changelog ==
= 1.1.3 =
* AutoSync system (diff detection: modified/new/orphans) + queue merging
* Manual “Run AutoSync Now” modal (modified / modified+new / full)
* Browse Chunks tab (pagination, filters, excerpts)
* UI refinements and progress refresh improvements
* Normalized autosync scope markers (ALL_* vs LIMITED)
* Updated internationalization strings

= 1.1.2 =
* Easy Config wizard (guided context + default bot linking)
* Instruction template selector UI improvements
* Added support instruction templates
* Minor bots list loading fix

= 1.1.0 =
* Logs admin screens (list & detail) + delete with nonce
* Logging toggle / IP capture for limits
* Daily usage limits (per user/IP + global)
* GDPR consent bubble
* UI enhancements (avatars, window controls, draggable panel)
* Security/escaping pass & local vendor assets

= 1.0.0 =
* Initial release

== Upgrade Notice ==
= 1.1.0 =
Review new logging & limits settings; update privacy policy if enabling IP-based limits.

== Support ==
Use the WordPress.org support forum. Provide WP version, PHP version, logging status, and reproduction steps.

== Contributing ==
Feature suggestions and PRs welcome. Follow WP coding standards and supply clear commit messages.

== License ==
GPLv2 or later. See LICENSE.

== Disclaimer ==
AI output may be inaccurate. Do not rely on responses for legal, medical or financial decisions without human review.