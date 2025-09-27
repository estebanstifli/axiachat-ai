## AI Chat Plugin – Focused Agent Guide
Purpose: rapid orientation for coding agents contributing to this multi‑bot WordPress AI chat plugin (OpenAI / Claude + optional RAG via local DB or Pinecone).

### Core Flow
Shortcode or global widget outputs container → `assets/js/aichat-frontend.js` reads data-* → AJAX `aichat_process_message` → server: validation (nonce `aichat_ajax`, honeypot `aichat_hp`, CAPTCHA filter, rate/spam/moderation) → resolve bot (table `wp_aichat_bots`) → gather context (`aichat_get_context_for_question` mode: auto→local|pinecone, page, none) → build messages (`aichat_build_messages` injects SECURITY & PRIVACY POLICY once + optional context + `[LINK]` marker hint) → provider call (OpenAI or Claude) → post-process (placeholder replacement, sanitize) → optional log row (`wp_aichat_conversations`).

### Key Files
`aichat.php` activation + custom tables + admin menus + rate/spam helpers.
`includes/class-aichat-ajax.php` request pipeline, usage limits, Claude/OpenAI routing, history trimming.
`includes/contexto-functions.php` embeddings (OpenAI `text-embedding-3-small`), similarity (cosine), context modes (local / pinecone / page), security policy & system prompt filters, `[LINK]` replacement.
`includes/shortcode.php` renders widget div + data attributes (bot slug, UI flags, avatar, layout/position, start sentence, send label, minimized states) and honeypot; sets flag to suppress global widget.
`assets/js/aichat-frontend.js` builds floating UI, GDPR consent message injection (object `AIChatGDPR` localized once), session management (UUID stored client), history fetch, voice (when type=voice_text), debug via `?aichat_debug=1`.

### Data Model (custom tables)
`wp_aichat_bots` (settings & UI + context linkage), `wp_aichat_conversations` (per turn; IP stored as VARBINARY only when limits enabled), `wp_aichat_contexts` (source meta + processing status), `wp_aichat_chunks` (UNIQUE(post_id,id_context) + embeddings JSON + scores computed at query time).

### Context / RAG
Call `aichat_get_context_for_question($question, ['context_id'=>X,'mode'=>'auto|page|none','limit'=>N,'page_id'=>ID])` → auto resolves local vs pinecone by `wp_aichat_contexts` row. Local: loads all chunks for context, computes cosine, sorts, slices. Pinecone: sanitized endpoint allowlist (`aichat_remote_endpoint_allowed_hosts`) then `/query`. Page mode: pulls current post content only. Results stored globally in `$GLOBALS['contexts']` for link replacement.

### Message Construction
`aichat_build_messages` prefixes a fixed SECURITY & PRIVACY POLICY (filter `aichat_security_policy`), then bot instructions or fallback minimal system; user message wraps concatenated CONTEXT plus the QUESTION and instructs model to place `[LINK]` marker. Replace later via `aichat_replace_link_placeholder` with top context permalink.

### Validation & Limits
Nonce `aichat_ajax`; honeypot field must stay empty; filters: `aichat_validate_captcha`, moderation via `aichat_run_moderation_checks` (see `includes/moderation.php`); burst/rate limiting `aichat_rate_limit_check`; spam heuristics (`aichat_spam_signature_check`); per-user/day or global usage limits checked before provider call (options: `aichat_usage_*`). Message length hard cap 4000 chars.

### Frontend Data Attributes (shortcode)
Examples: `data-bot`, `data-type` (text|voice_text), `data-title`, `data-placeholder`, `data-layout` (floating|inline), `data-position` (bottom-right etc.), `data-color`, `data-width`, `data-height`, avatar (`data-avatar-enabled`, `data-avatar-url`), window controls (`data-closable`, `data-minimizable`, `data-draggable`), state (`data-minimized-default`, `data-superminimized-default`), UX text (`data-start-sentence`, `data-button-send`). Ensure new fields added both in shortcode render + JS parser.

### Adding / Modifying Features
New bot setting: extend `aichat_bots_maybe_create` (dbDelta-safe), expose field in `assets/js/bots.js` (localized config), include in form save AJAX (`bots_ajax.php`), then surface via shortcode data attrs & frontend JS if UI related.
New context mode: branch inside `aichat_get_context_for_question` (normalize output keys: post_id,title,content,score,type) + adjust auto mode decision.
New provider: follow pattern in `process_message` (normalize model, build `$messages`, return uniform array with `'message'` or `'error'`).

### Security & Sanitization
Never echo raw model output—pass through existing sanitization (see where response is transformed + HTML link injection). Maintain allowlist when touching remote endpoints. Do not leak system prompt or internal details (policy enforces refusal—keep it first in system message).

### Debug / QA
Enable `define('AICHAT_DEBUG', true);` → verbose `aichat_log_debug` lines (prefixed `[AIChat]`). Use `?aichat_debug=1` for JS console. Preview specific bot: `/?aichat_preview=1&bot=slug`. Inspect context selection by POSTing `debug=1` (logs top 3 scores). GDPR consent is inserted as first bot message until accepted cookie `aichat_gdpr_ok`.

### Common Pitfalls
Missing enqueue of `aichat-frontend` → widget inert. Forgetting honeypot check when crafting manual form → blocked. Not updating shortcode + JS for new UI field → attribute ignored. Pinecone context row without API key/endpoint → silent empty context. Changing bot slug without updating global option/shortcode → fallback picks first bot.

Feedback: If deeper detail needed (e.g., ingestion tooling, logs schema, moderation branching) request an expanded section.
