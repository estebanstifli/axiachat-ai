---
id: logs-and-usage
title: Logs & Usage
---

Tables:
- Conversations: `wp_aichat_conversations` (timestamps stored in site‑local time).
- AI Tools: `wp_aichat_tool_calls` (timestamps stored in site‑local time).

Admin pages:
- Conversations list and details.
- AI Tools logs (duration, output excerpt, errors).

Notes:
- If you switch to UTC storage in the future, render with `wp_date()` to localize at display time.
