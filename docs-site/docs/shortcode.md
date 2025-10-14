---
id: shortcode
title: Shortcode & Attributes
---

Use `[aichat]` in pages/posts. Common attributes:

- `bot`: Bot slug to use.
- `type`: `text` | `voice_text`.
- `layout`: `floating` | `inline`.
- `position`: `bottom-right`, `bottom-left`, etc. (floating only).
- `color`: brand color (hex/css var).
- `width`, `height`: widget size (floating).
- `avatar-enabled`, `avatar-url`.
- `closable`, `minimizable`, `draggable`.
- `minimized-default`, `superminimized-default`.
- `start-sentence`, `button-send`.

Examples:
- Inline bot: `[aichat bot="support" layout="inline"]`
- Floating bot bottom-right: `[aichat bot="default" layout="floating" position="bottom-right" minimized-default="1"]`

When adding new UI fields, update both `includes/shortcode.php` and the parser in `assets/js/aichat-frontend.js`.
