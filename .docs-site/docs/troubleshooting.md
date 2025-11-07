---
id: troubleshooting
title: Troubleshooting
---

Chat doesn’t respond
- Check provider API keys and model availability.
- Verify daily/burst limits under Settings aren’t blocking.

Tool execution fails
- Open AI Tools Logs page and inspect the last entries (duration, error).
- Confirm the tool is registered and visible under Capabilities.

Responses tools error “tools[0].name”
- Ensure you’re on a version that normalizes tools for OpenAI Responses.

Timestamps look off
- The plugin stores times in the site’s local timezone. If migrating, standardize storage and rendering.
