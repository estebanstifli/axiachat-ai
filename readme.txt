=== AxiaChat AI ===
Contributors: estebandezafra
Tags: chatbot, ai, openai, chat, assistant
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Short Description: Flexible AI chatbot with multiple bots, OpenAI & Claude, contextual embeddings (local or Pinecone), PDF ingestion, usage limits & GDPR tools.

== Description ==
AxiaChat AI lets you add one or more AI‑powered chatbots to your WordPress site. Each bot can have its own model settings, instructions, UI colors, avatar and placement. It uses the OpenAI API (you must provide your own API key) and can augment answers with contextual data (documents, posts or imported PDF content) using a basic Retrieval Augmented Generation workflow.

Conversation logs are stored locally (can be disabled) so you can review usage (see GDPR notes). All AI processing happens via direct calls from your server to OpenAI—no external SaaS proxy.

Professional setup offer: Because this plugin is new, I'm happy to personally help you configure prompts, context (embeddings), and capabilities so your bot performs reliably for your use case. If you'd like assistance, please reach out: https://wpbotwriter.com/log-a-support-ticket/

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
* AI Tools: per‑bot capabilities (macros and atomic tools) including provider‑native web search, optional rules, and admin logs for tool calls
* Action hooks: `aichat_after_response`, `aichat_conversation_saved` (extend for analytics, etc.)
* Security: nonces + capability checks, prepared queries, escaping
* Translation ready (text domain: axiachat-ai – formerly ai-chat) – Spanish included
* Clean uninstall (removes options; keeps conversation tables unless you delete them manually)
* Local vendor assets (Bootstrap / Icons) so no external CDN dependency

Need a capability that's not listed? Tell me what your bot should do — I love building useful integrations for the community. I'll gladly add it for free if it helps others too. Request a capability → https://wpbotwriter.com/log-a-support-ticket/

== Installation ==
1. Upload the `axiachat-ai` folder to `/wp-content/plugins/` (or install via Plugins > Add New).
2. Activate the plugin through the 'Plugins' screen.
3. Go to AxiaChat AI > Settings and add your OpenAI API key.
4. Create or edit a Bot in AxiaChat AI > Bots (set model, appearance, flags).
5. (Optional) Add contextual content in AxiaChat AI > Context (ingest PDFs or posts for embeddings).
6. Place a bot:
   * Inline: add `[aichat id="your-bot-slug"]` in a post or page.
   * Floating global widget: enable Global widget in Settings and select a bot.

== Usage ==
After installation, create at least one bot and (optionally) ingest context. Use the shortcode or enable the global widget. Adjust window control flags (closable, minimizable, draggable) in the bot settings. If you plan to let the assistant perform actions (search the web, send notifications, etc.), enable and configure AI Tools for that bot.

== AI Tools (Capabilities, Macros & Rules) ==
AxiaChat AI includes an optional “AI Tools” layer that lets the assistant perform controlled actions. You can enable prebuilt macros (groups of atomic tools) per bot and optionally restrict or authorize behavior.

Highlights:
* Provider‑native Web Search (OpenAI Responses): enable the `openai_web_search` macro to allow live web lookups. Optionally restrict to an Allowed Domains list per bot. When enabled, responses include cited sources when available.
* Email notifications: safe defaults that send to site admin only, with policy gates and rate limits. Client emails require explicit server‑side authorization via a filter.
* Rules (optional): define simple conditions to trigger automatic actions (e.g., ask follow‑up, speak a message, call a tool).
* Tools Logs: review every tool call (duration, output excerpt, error) under the admin logs page.

Notes:
* On OpenAI GPT‑5* (Responses API), AxiaChat normalizes function tools to the required schema and passes the native `web_search` tool when enabled.
* Tools are sandboxed by design and gated by policies; never expose secrets to models.

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
1. Bot configuration
2. Context ingestion & indexing
3. Usage / Costs

== Changelog ==
= 1.1.9 =
* Added: Tools Test for debug
* Added: Compatibility with the Simply Schedule Appointments booking plugin

= 1.1.8 =
* Added: new avatars to choose

= 1.1.7 =
* Added: Spanish (Spain) translations (es_ES).

= 1.1.6 =
* AI Tools: per‑bot capabilities & macros, including OpenAI native web search with optional domain allowlist
* Normalized Tools schema for OpenAI Responses models (fixes missing `tools[0].name` errors)
* AI Tools logs now stored in site‑local timezone for consistency
* Admin UI/documentation improvements; coding standards cleanups

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

== Support ==
Use the WordPress.org support forum. Provide WP version, PHP version, logging status, and reproduction steps.

== Contributing ==
Feature suggestions and PRs welcome. Follow WP coding standards and supply clear commit messages.

== License ==
GPLv2 or later. See LICENSE.

== Disclaimer ==
AI output may be inaccurate. Do not rely on responses for legal, medical or financial decisions without human review.