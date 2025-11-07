=== AxiaChat AI – Free AI Chatbot (Answers Customers Automatically) ===
Contributors: estebandezafra
Tags: chatbot, ai, openai, chat, assistant
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free AI chatbot with multiple bots, OpenAI & Claude & Gemini, contextual embeddings (local or Pinecone), PDF ingestion, usage limits & GDPR tools. RAG

== Description ==

**Transform your WordPress site into a 24/7 AI-powered customer service hub.** AxiaChat AI is a cutting-edge chatbot plugin that delivers instant, intelligent responses to your visitors—day or night—using the latest AI technology from OpenAI (ChatGPT), Anthropic Claude, and Google Gemini.

#### 🚀 Why Choose AxiaChat AI?

**Never Miss a Customer Query Again**
Your AI assistant works around the clock, answering questions, capturing leads, and guiding visitors—even while you sleep. Reduce response times from hours to seconds and watch your conversion rates soar.

**Train Your Bot on YOUR Content**
Unlike generic chatbots, AxiaChat AI learns from your actual website content, PDFs, and documentation using advanced Retrieval Augmented Generation (RAG). Your bot provides accurate, contextual answers based on your products, services, and knowledge base.

**Multiple AI Providers, One Plugin**
Choose between OpenAI (GPT-4, GPT-5), Anthropic Claude, or Google Gemini. Switch providers per bot or use different models for different purposes. Full support for the latest AI capabilities including native web search.

**MCP Server Integration (Model Context Protocol)**
Connect external tools and data sources to your chatbot through MCP servers. Extend your bot's capabilities beyond simple Q&A to perform real actions, fetch live data, and integrate with your business systems.

**Enterprise-Grade Features at Your Fingertips**
✅ Multiple independent bots with unique personalities and purposes
✅ Smart context-aware responses using embeddings (local or Pinecone)
✅ PDF content ingestion for knowledge base creation
✅ Advanced AI tools and capabilities (web search, email notifications, custom actions)
✅ Comprehensive usage limits and cost controls
✅ GDPR-compliant with built-in consent management
✅ Full conversation logging and analytics
✅ Complete UI customization (colors, avatars, positioning)
✅ Floating widget or inline embedding via shortcode
✅ No external dependencies—your data stays on your server

#### 💼 Perfect For Every Business

Whether you run an e-commerce store, service business, SaaS platform, educational site, or corporate website—AxiaChat AI adapts to your needs. Deploy multiple specialized bots: one for sales, one for support, one for FAQs, each trained on relevant content.

#### 🎯 Real Results

- **Reduce support workload** by handling common questions automatically
- **Capture leads 24/7** even when your team is offline
- **Improve user engagement** with instant, helpful responses
- **Lower bounce rates** by helping visitors find what they need
- **Scale your support** without scaling your team

**Professional Setup Assistance Available**
New to AI chatbots? We're here to help! Get personalized assistance configuring prompts, training your bot, and optimizing performance for your specific use case: https://wpbotwriter.com/log-a-support-ticket/

== Tutorial: Create Your First Bot with AxiaChat AI ==

Learn how to install and set up your **first AI chatbot in just 5 minutes** 
[youtube https://youtu.be/Th41gGUH7Es]

In this quick video, we cover:
- Create & customize your bot (text + voice)
- Train it with your site pages, PDFs, and FAQs
- Full appearance control + moderation


== Features ==

#### 🤖 AI & Model Support
* **Multiple AI Providers**: OpenAI (GPT-4, GPT-5), Anthropic Claude, Google Gemini
* **MCP Server Integration**: Connect Model Context Protocol servers for extended capabilities
* **Provider-native Web Search**: Enable live internet lookups with cited sources (OpenAI, Claude, Gemini)
* **Multiple independent bots** with unique configurations per bot (model, temperature, instructions, UI)
* **Advanced AI Tools**: per-bot capabilities including web search, email notifications, custom actions with admin logs

#### 🎓 Smart Training & Context
* **Retrieval Augmented Generation (RAG)**: Train your bot on your actual content
* **Context modes**: embeddings (local or Pinecone) / page-specific / none
* **PDF ingestion**: Upload and process PDF documents into your knowledge base
* **Auto-sync**: Automatically detect and update changed content
* **Smart chunking**: Overlapping content chunks for better retrieval precision
* **Easy Config Wizard**: Guided setup scans your site and creates optimized context automatically

#### 🎨 Customization & Deployment
* **Floating global widget** OR **inline embedding** via shortcode
* **Full UI control**: custom colors, avatars, positioning, window controls
* **Draggable, minimizable, closable** chat panel with state persistence
* **Multiple deployment options**: per-page shortcodes with attribute overrides
* **9 avatar designs** included + custom avatar upload
* **Responsive design**: works perfectly on desktop and mobile

#### 📊 Usage Control & Analytics
* **Conversation logging** with ON/OFF toggle (user/session tracking; IP only if limits enabled)
* **Daily usage limits**: per-user/IP and global caps
* **Flexible limit behaviors**: disable input or hide widget when exceeded
* **Cost tracking**: monitor token usage and API costs
* **Detailed conversation logs**: review all interactions with filtering and search

#### 🔒 Security & Compliance
* **GDPR-compliant**: built-in consent management with in-stream consent bubble
* **Privacy controls**: optional conversation logging, IP anonymization
* **WordPress security best practices**: nonces, capability checks, prepared queries, output escaping
* **Encrypted API keys**: secure storage of sensitive credentials
* **Data ownership**: all processing on your server, no external SaaS proxy
* **Local vendor assets**: Bootstrap & Icons bundled—no CDN dependencies

#### 🛠️ Developer-Friendly
* **Action hooks**: `aichat_after_response`, `aichat_conversation_saved` for custom integrations
* **Extensible architecture**: provider system for adding new AI services
* **Translation ready**: text domain `axiachat-ai` (Spanish included)
* **Clean uninstall**: removes options safely (conversation data preserved)
* **Well-documented code**: follows WordPress coding standards

#### 🌐 Language & Accessibility
* **Works with any language** (customizable responses)
* **RTL support** for right-to-left languages
* **Spanish translation** included (es_ES)
* **Translation ready** with .pot file for easy localization

**Need a custom capability?** Tell us what your bot should do! We love building useful integrations for the community and will gladly add features that help others. [Request a capability →](https://wpbotwriter.com/log-a-support-ticket/)

== Installation ==

#### Quick Start (5 minutes)

1. **Install the Plugin**
   - Upload the `axiachat-ai` folder to `/wp-content/plugins/`
   - OR install directly via **Plugins > Add New** in WordPress

2. **Activate**
   - Go to **Plugins** and activate AxiaChat AI

3. **Add Your API Key**
   - Navigate to **AxiaChat AI > Settings**
   - Enter your OpenAI API key (required)
   - Optionally add Claude or Gemini keys for those providers

4. **Run Easy Config Wizard** (Recommended for First-Time Users)
   - The wizard appears automatically after activation
   - It will scan your site content (posts, pages, products)
   - Create a context with embeddings automatically
   - Link your default bot to the new context
   - OR skip and configure manually

5. **Create or Customize Your Bot**
   - Go to **AxiaChat AI > Bots**
   - Edit the default bot or create a new one
   - Choose AI provider and model (GPT-4, Claude, Gemini)
   - Set bot instructions, UI appearance, and behavior
   - Enable AI capabilities (web search, tools) if needed

6. **Optional: Add More Context**
   - Visit **AxiaChat AI > Context**
   - Ingest PDFs or select specific posts/pages for embeddings
   - Your bot will use this content to provide accurate, contextual answers

7. **Deploy Your Bot**
   
   **Option A – Floating Global Widget:**
   - Enable **Global widget** in **AxiaChat AI > Settings**
   - Select which bot to display
   - Widget appears on all pages (unless excluded)
   
   **Option B – Inline Shortcode:**
   - Add `[aichat id="your-bot-slug"]` to any post or page
   - Customizable per-page with shortcode attributes

8. **Test & Optimize**
   - Visit your site and interact with the chatbot
   - Review conversation logs under **AxiaChat AI > Logs**
   - Adjust bot instructions and context as needed

**Need Help?** We offer free personalized setup assistance: https://wpbotwriter.com/log-a-support-ticket/

== Usage ==

#### Basic Usage

After installation, your chatbot is ready to start helping your visitors! Here's how to make the most of AxiaChat AI:

**For Site Owners:**
- Create specialized bots for different purposes (sales, support, FAQs)
- Train bots with relevant content using the Context manager
- Monitor conversations and improve responses based on real user interactions
- Set usage limits to control API costs
- Enable/disable logging as needed for privacy compliance

**For Visitors:**
- Click the floating chat icon to start a conversation
- Ask questions in natural language—the AI understands context
- Receive instant, accurate answers based on your site's content
- Get cited sources when web search is enabled
- Accept GDPR consent to start chatting (if enabled)

#### Advanced Configuration

**AI Tools & Capabilities:**
Enable advanced features per bot in the Tools section:
- **Web Search**: Let the bot search the internet for current information
- **Email Notifications**: Send notifications to admins or authorized recipients
- **Custom Actions**: Use MCP servers to extend functionality
- **Domain Restrictions**: Limit web search to trusted domains only

**Window Controls:**
Customize the chat experience:
- **Closable**: Users can dismiss the chat window
- **Minimizable**: Compact to icon when not in use
- **Draggable**: Let users move the window around the screen
- **Start Minimized**: Begin in compact mode

**Usage Limits:**
Protect your API budget:
- Set per-user or per-IP daily message limits
- Configure global daily limits across all users
- Choose behavior when limits are reached (disable input or hide widget)

**Context Modes:**
Choose how the bot accesses information:
- **Embeddings**: Uses RAG with your trained content (most powerful)
- **Page**: Answers using only the current page content
- **None**: Pure AI responses without site-specific context

**Need help with setup?** Our team provides free configuration assistance to ensure your bot works perfectly for your use case: https://wpbotwriter.com/log-a-support-ticket/

== AI Tools (Capabilities, Macros & Rules) ==

#### Supercharge Your Chatbot with AI Tools

AxiaChat AI goes beyond simple Q&A. The optional AI Tools layer allows your assistant to **take actions**, **access live data**, and **integrate with external services**—all while maintaining strict security controls.

#### 🌐 Provider-Native Web Search

Enable real-time internet lookups so your bot can answer questions about current events, latest information, or topics beyond your site content.

**Supported Providers:**
- **OpenAI**: GPT-5* models with native `web_search` capability
- **Claude**: 4.x models with `web_search_20250305` tool
- **Gemini**: `google_search` integration (Live API only)

**Features:**
- Cited sources in responses
- Optional domain allowlist per bot (restrict to trusted sites)
- Automatic integration—no complex configuration needed

**When to Use:**
- News sites needing current information
- Product comparison queries
- Research and fact-checking
- Supplement your knowledge base with web data

#### 📧 Email Notifications

Send intelligent email notifications triggered by conversations.

**Safety Features:**
- Sends to site admin by default
- Rate limiting to prevent spam
- Policy gates for security
- Client emails require explicit server-side authorization via filter

**Use Cases:**
- Notify team when high-priority leads are captured
- Send follow-up emails based on conversation topics
- Alert staff to urgent support requests

#### 🔌 MCP Server Integration

**Model Context Protocol (MCP)** integration enables your chatbot to connect with external tools and data sources.

**What You Can Do:**
- Fetch live data from APIs
- Query databases
- Integrate with business systems
- Create custom workflows
- Extend capabilities without coding

**How It Works:**
1. Add MCP server configuration in the addon settings
2. Enable desired tools per bot
3. The AI automatically calls tools when appropriate
4. Results are seamlessly integrated into responses

#### 📋 Rules Engine (Optional)

Define simple conditions to trigger automatic actions:
- Ask follow-up questions based on user responses
- Speak a message at specific points
- Call tools automatically when certain conditions are met

#### 📊 Tools Logging

Every tool call is logged for transparency and debugging:
- Execution duration
- Output excerpts
- Error tracking
- Searchable admin interface

**Access logs:** AxiaChat AI > Logs > Tools

#### 🔒 Security by Design

- **Sandboxed execution**: Tools run in isolated contexts
- **Policy gates**: System-level restrictions prevent abuse
- **No secrets exposed**: API keys and sensitive data never sent to AI models
- **Admin authorization**: All tool capabilities require explicit enablement

**Note:** On OpenAI GPT-5* (Responses API), AxiaChat normalizes function tools to the required schema and passes the native `web_search` tool when enabled.

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

== Changelog ==

= 1.2.5 =
* Added: Support for Google Gemini as a new AI provider.

= 1.2.4 =
* Added: MCP Servers Addon, allowing the addition of MCP servers for enhanced functionality.
* Improved: Encryption of AI model API keys for better security.



= 1.2.3 =
* Added: YouTube tutorial video for quick setup walkthrough
* Enhanced: Flexible widget sizing for better responsive design
* Improved: Larger avatar display for better visual prominence

* GDPR: Redesigned the consent message as a full-width in-stream block with a darker background and a centered button for better visibility and UX. Inputs remain disabled until accepted.

= 1.2.2 =
* Admin: Improved Bots accordion behavior — clicking an open section now closes it (allowing zero-open state) while keeping “at most one open” when opening another.
* Admin: Reduced console noise; detailed accordion debug logs are now disabled by default and can be re-enabled with ?aichat_debug=1.
* Preview: Stabilized homepage preview and suppressed duplicate global widget when previewing a specific bot.


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

**Q: Why choose AxiaChat AI over other WordPress chatbot plugins?**
A: AxiaChat AI gives you complete control with support for multiple AI providers (OpenAI, Claude, Gemini), advanced RAG capabilities, MCP server integration, and enterprise features—all while keeping your data on your server. No monthly SaaS fees beyond your chosen AI provider's API costs.

**Q: Do I need an OpenAI API key?**
A: You need at least one AI provider API key (OpenAI, Claude, or Gemini). OpenAI is recommended for getting started. All keys are yours—we never charge markup or routing fees.

**Q: How much does it cost to run?**
A: The plugin is free. You only pay your AI provider's API usage (typically $0.001-0.01 per conversation). Usage limits help you control costs. A typical e-commerce site handles hundreds of conversations for under $10/month.

**Q: Can the bot learn from my website content?**
A: Yes! The Easy Config Wizard automatically scans and indexes your site content. You can also upload PDFs, select specific pages, or train it on your knowledge base. The bot uses Retrieval Augmented Generation (RAG) to provide accurate, contextual answers.

**Q: Will this work with my WooCommerce store?**
A: Absolutely. The wizard auto-detects WooCommerce and indexes your products. The bot can answer product questions, help with comparisons, and guide customers through your catalog.

**Q: Can I have multiple bots for different purposes?**
A: Yes! Create unlimited bots—each with unique training, personality, appearance, and capabilities. Use one for sales, another for support, and a third for FAQs, all running simultaneously.

**Q: How do I control API costs?**
A: Set daily usage limits (per-user, per-IP, or global). Choose what happens when limits are reached: disable input or hide the widget. Monitor usage in real-time via the built-in logs and cost tracking.

**Q: Is conversation data stored? Can I disable logging?**
A: Conversation logging is optional and fully controllable. Toggle it ON to review interactions and improve your bot, or OFF for maximum privacy. When enabled, data stays on YOUR server—never sent to us.

**Q: How is GDPR compliance handled?**
A: Built-in GDPR consent management with an in-stream consent bubble that blocks chat input until accepted. IP addresses are only stored if usage limits are enabled. You maintain full data control and can delete logs anytime.

**Q: What is MCP Server Integration?**
A: Model Context Protocol (MCP) lets your bot connect to external tools, databases, and APIs. Extend functionality beyond Q&A: fetch live data, integrate with your CRM, query inventory systems, and more—all without custom coding.

**Q: Does it support web search?**
A: Yes! Enable provider-native web search so your bot can access current information from the internet. Works with OpenAI GPT-5*, Claude 4.x, and Gemini. Optional domain allowlists let you restrict sources to trusted sites only.

**Q: Can I customize the appearance?**
A: Complete customization: colors, avatars (9 included + custom upload), position, size, window controls (draggable, minimizable, closable), button labels, and more. Match your brand perfectly.

**Q: Where can I deploy the chatbot?**
A: Two ways: (1) Floating global widget visible across your entire site, or (2) inline embedding via shortcode on specific pages/posts. Mix and match different bots for different pages.

**Q: What languages are supported?**
A: The AI models support 100+ languages naturally. The plugin UI is translation-ready (Spanish included) and works with RTL languages. Customize all bot responses for any language.

**Q: Do you offer setup assistance?**
A: Yes! We provide FREE personalized setup help. We'll configure your bot, optimize prompts, train it on your content, and ensure it works perfectly for your use case. [Get help →](https://wpbotwriter.com/log-a-support-ticket/)

**Q: Can visitors use voice input?**
A: Yes, voice input is supported for compatible bots. Users can speak their questions instead of typing.

**Q: Will more AI providers be added?**
A: Yes! Azure OpenAI, additional models, and more providers are on the roadmap. The plugin's extensible architecture makes adding new providers straightforward.

**Q: What if I need a custom capability not listed?**
A: Tell us! We love building useful features for the community. If your requested capability helps others, we'll add it for free. [Request a feature →](https://wpbotwriter.com/log-a-support-ticket/)

**Q: Is support available?**
A: Yes. Use the WordPress.org support forum or contact us directly for priority assistance. We're committed to your success.

== Screenshots ==
1. Bot configuration
2. Context ingestion & indexing
3. Usage / Costs

== Changelog ==
= 1.2.0 =
* Added: Tokens and tools in system instructions
* Fix: In some installations, the MySQL foreign key was failing

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