=== AI Chat ===
Contributors: estebandezafra
Tags: chatbot, ai, openai, chat, assistant
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
A customizable AI chatbot plugin for WordPress powered by OpenAI. Create multiple bots, embed them via shortcode or a global floating widget, and optionally enhance answers with your own contextual content (RAG).

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
* Translation ready (text domain: aichat) – Spanish included
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

== Bundled Libraries ==
* Bootstrap (local) – styling & layout
* Bootstrap Icons (local) – icons
* smalot/pdfparser (LGPLv3) – PDF text extraction fallback
All third‑party licenses included in `/vendor`.

== Translation ==
* Text Domain: `aichat`
* Domain Path: `/languages`

== Roadmap ==
* Export / anonymize conversation data tool
* Basic analytics dashboard (usage counts, limit hits)
* Additional AI providers (Azure OpenAI, Anthropic, etc.)
* Optional data retention controls (auto prune)

== Frequently Asked Questions ==
= Do I need my own OpenAI API key? =
Yes. The plugin does not include API access. Create a key at OpenAI, then paste it in AI Chat > Settings.

= Can I disable storing conversations? =
Yes. Uncheck the conversation logging option in Settings. Existing rows remain until you delete them.

= What about usage limits? =
Configure per-user/IP and global daily limits in Settings. After hitting them the widget either disables input (message shown) or hides itself (hidden mode).

= How is the GDPR consent handled? =
If enabled, a consent bubble appears inside the chat stream; inputs stay disabled until the user accepts (cookie `aichat_gdpr_ok`).

= Does this plugin send data to any third‑party besides OpenAI? =
No. Only OpenAI is contacted unless you modify the code or add extensions.

= How do I change the position of the floating widget? =
Use the global widget settings or override with shortcode `layout="floating"` and `position="bottom-left"` (or shorthand codes br|bl|tr|tl).

= Is it translation ready? =
Yes. Spanish translation included; more welcome.

= Will you support other AI providers? =
Planned (see roadmap).

== Screenshots ==
1. Frontend chat widget (floating mode)
2. Bot configuration screen
3. Context ingestion interface

== Changelog ==
= 1.1.3 =
* AutoSync system: background cron diff detection (modified/new/orphans) with queue merging.
* Manual "Run AutoSync Now" modal (modes: modified, modified + new, full rebuild) with progress & disabling while pending/in_progress.
* Browse Chunks tab (local contexts only): paginated, filtered listing (q, type, per page) to inspect stored chunks.
* Split previous combined Edit/Test button into separate Settings and Similarity buttons + direct tab navigation.
* LIMITED context full rebuild fix (rebuild only existing post IDs; no unintended additions).
* Improved UX: persistent Run AutoSync button across AJAX reloads, tooltips, immediate progress refresh, buttons disabled during processing.
* Internationalization: added keys for new buttons, modal labels, browse UI (loading, no chunks messages).
* Internal refactors: normalized autosync_post_types storage (ALL_* markers vs LIMITED), enforced mode restrictions when not ALL_*.
* Mini-tab context sync fix (Browse tab now updates when switching context via Settings/Similarity buttons).

= 1.1.2 =
* Added Easy Config wizard (activation redirect, guided context + default bot linking)
* Instruction template selector UI: fixed height with scrollbar + navigation arrows
* Added 3 customer service instruction templates (English)
* Settings: Embed Allowed Origins block moved before Save + example external snippet
* Minor bug fix (undefined function call prevented bots list from loading)

= 1.1.0 =
* Added Logs admin screen (list + detail + delete) with filters & nonce protection
* Logging ON/OFF toggle (no new inserts when disabled)
* IP capture (binary) for optional per-user/IP limits
* Daily usage limits (per user/IP & global) with hide/disable behaviors
* GDPR consent bubble inside chat stream
* UI improvements (Bootstrap cards, icons, window controls, draggable/minimizable defaults)
* Shortcode data attributes updated for window flags/placeholders
* Security/escaping pass on admin pages & logs
* Local vendor assets (Bootstrap / Icons) instead of remote

= 1.0.0 =
Initial release

== Upgrade Notice ==
= 1.1.0 =
Review new Settings (logging toggle, usage limits, GDPR consent). Update privacy policy to mention optional IP storage if limits enabled.



== Support ==
Use the WordPress.org support forum. Include WP version, PHP version, whether logging/limits are enabled, and (sanitized) steps to reproduce issues.

== Contributing ==
Pull requests and feature suggestions welcome. Follow WordPress coding standards and provide descriptive commit messages.

== License ==
This plugin is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 2 of the License, or (at your option) any later version.

Distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

== Disclaimer ==
AI responses may be inaccurate or outdated. Do not rely solely on AI output for legal, medical, or financial decisions. Always review critical information.