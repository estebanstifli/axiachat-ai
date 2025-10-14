---
id: rag
title: Context / RAG
---

Entry point: `aichat_get_context_for_question($question, $opts)`
- Input: question string, options like `context_id`, `mode` (auto|page|none), `limit`, `page_id`.
- Output: array of results with `post_id`, `title`, `content`, `score`, `type`.

Modes:
- Auto: resolves to local or Pinecone depending on context row.
- Local: loads chunks from `wp_aichat_chunks`, computes cosine similarity, sorts and slices.
- Pinecone: calls allowlisted endpoint and runs `/query`.
- Page: uses current post/page content only.

Link replacement:
- Messages include a `[LINK]` placeholder that is replaced server‑side with the top context permalink.
