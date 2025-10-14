---
id: installation
title: Installation
---

1) Upload the plugin
- WP Admin → Plugins → Add New → Upload → select `aichat.zip` (or place the folder under `wp-content/plugins`).

2) Activate
- Activate the plugin and go to Settings → "AI Chat".

3) API keys
- Enter your provider key(s): OpenAI and/or Anthropic.

4) Create a page with a shortcode
- Add `[aichat bot="default"]` to render the chat inline.

5) Global floating widget (optional)
- Enable the floating widget in Settings to show the assistant site‑wide.

Verification checklist:
- The chat container appears and loads (no 404 for assets/js).
- A first message is shown (GDPR consent if enabled).
- Ask a short question and confirm a response arrives.
