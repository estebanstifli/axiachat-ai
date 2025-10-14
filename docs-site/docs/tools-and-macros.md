---
id: tools-and-macros
title: Tools & Macros
---

Where to register:
- Atomic tools live in `includes/add-ons/ai-tools/tools-sample.php`.
- Macros group tools and are assigned per bot.

Execution flow:
- Chat Completions: multi‑round function‑calling loop, tool outputs appended, then final message.
- OpenAI Responses (gpt‑5*): server maps function tools to `{type:'function', name, description, parameters}` and passes native `web_search`.

Web search:
- Enable via macro `openai_web_search`.
- Optionally restrict sources with an allowed domains list (per bot).
- When present, the model is asked to include sources in outputs for transparency.

Safety gates:
- Email tools require policy gates and rate limits; admin email only by default.
- System policies are injected into the system prompt before provider calls.
