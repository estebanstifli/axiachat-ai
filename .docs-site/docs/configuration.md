---
id: configuration
title: Configuration
---

## Bots
- Provider and model: choose OpenAI/Claude model. GPT‑5* use the Responses API.
- Temperature & tokens: control creativity and maximum output length.
- Start sentence: first assistant line before user input.
- Appearance: color, avatar image, layout (floating/inline), position, width/height, draggable/minimizable.

## Context (RAG)
- Manage contexts under AI Chat → Contexts.
- Modes: auto (local or pinecone), page (current post only), none.
- Local mode: chunks stored in `wp_aichat_chunks` with OpenAI embeddings; cosine similarity at query time.
- Pinecone: enable endpoint and API key; only allowlisted hosts are used.

## Security & Limits
- Nonce and honeypot are enforced on AJAX.
- Moderation: content checks before provider call.
- Rate limit: burst & daily limits per user or global.
- GDPR: optional first message until consent cookie is set.
