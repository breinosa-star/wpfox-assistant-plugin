# Prompt Changelog

All notable prompt changes are documented here.
Format: `## [date] — file(s) changed — brief description`

---

## [2026-04-03] — chat-system.txt — Initial extraction from class-grayfox-llm.php

- Extracted full chat system prompt from PHP into standalone file
- Added `{{KB_SECTION}}` placeholder for runtime KB mode injection
- Sections: IDENTITY AND TONE, CONVERSATION APPROACH (4 steps), CUSTOMER NAME, EMAIL CAPTURE, FORMATTING

## [2026-04-03] — chat-kb-tool.txt — Initial extraction

- Tool-mode KB section: instructs LLM to always call search_knowledge_base before answering business questions

## [2026-04-03] — chat-kb-prefetch.txt — Initial extraction

- Pre-fetch KB section (legacy/site-builder mode): injects knowledge base JSON directly into context
- Uses `{{KNOWLEDGE_JSON}}` placeholder substituted at runtime

## [2026-04-03] — classifier.txt — Initial extraction from class-grayfox-security.php

- Security classifier prompt with `{{KB_CONTEXT}}` and `{{HISTORY_CONTEXT}}` placeholders
- Three labels: safe / injection / offtopic
- Fail-open: ambiguous → safe

## [2026-04-03] — rag-summarize.txt — Initial extraction from class-grayfox-rag.php

- Latent-style KB summarization prompt
- Produces structured JSON with topic keys + _aliases field for synonym retrieval

## [2026-04-03] — rag-retrieve.txt — Initial extraction from class-grayfox-rag.php

- LLM-as-retriever prompt: selects relevant topic keys from the KB index for a given query
