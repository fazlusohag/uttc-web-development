# UTTC AI Assistant — Hostinger PHP backend

## Features
- PHP JSON endpoint using the OpenAI Responses API.
- Hybrid retrieval: cached OpenAI embedding vectors with cosine-similarity ranking when configured, plus BM25 lexical fallback over published UTTC website pages and Published admin knowledge.
- Bangla/English answers, source links, refusal to invent missing institutional facts.
- Server-side API key only, origin allowlist, input validation, per-IP rate limit.
- CLI sitemap sync to refresh the local knowledge base.

## Requirements
PHP 8.1+, cURL, DOM, SimpleXML, mbstring, writable `api/` directory. Confirm extensions in Hostinger hPanel before deployment.

## Hostinger setup
1. Upload the `api/` folder to the same public_html directory as the website.
2. Set environment variables in the hosting environment (never in browser JavaScript or Git):
   - `OPENAI_API_KEY`: create/manage in your OpenAI API project.
   - `OPENAI_MODEL`: a model available to that API project.
   - `OPENAI_EMBEDDING_MODEL`: an embedding model available to that API project (for example, `text-embedding-3-small`). Set this only after confirming API project access and budget; without it the assistant uses BM25 fallback.
   - `UTTC_SITE_URL=https://uttc.edu.bd`
   - `UTTC_ALLOWED_ORIGINS=https://uttc.edu.bd,https://www.uttc.edu.bd`
3. Run once by SSH/cron: `php /home/ACCOUNT/public_html/api/sync-knowledge.php`
4. Schedule the same command daily or weekly after confirming the correct absolute path.
5. Confirm `api/knowledge.json` exists and is not directly downloadable; the included .htaccess denies direct access on Apache.
6. Test POST requests to `/api/assistant.php` with JSON: `{"message":"UTTC-তে কী কী কোর্স আছে?"}`.

## Website integration
The homepage calls `/api/assistant.php` with POST JSON. The API returns `{ok, answer, sources}`. The browser renders answer text safely and displays source links as links.

## Security / operations
- Do not commit or paste the real API key into GitHub or chat.
- Set a billing budget/usage alert in the OpenAI platform and monitor usage.
- Keep HTTPS enabled.
- Rate limiting is a basic per-IP local-file limit (18 requests per 10 minutes). For a high-traffic site, replace it with a shared cache/WAF rule.
- Do not enable public access to any admin sync URL; syncing is CLI-only.
- Keep PHP error display off in production; review server logs for diagnostics.
- Verify PHP extensions, writable permissions and environment-variable support with the actual Hostinger plan.
- The sync script only indexes URLs listed in the same-domain sitemap. Add new public pages to sitemap.xml before syncing.

## Private AI Knowledge Base admin

The feature branch also contains a private admin interface at `/admin/` and its JSON API at `/api/admin.php`.

### Admin configuration (required before use)
1. Keep the admin interface behind HTTPS. The admin login is not usable until the server environment variable `UTTC_ADMIN_PASSWORD_HASH` is configured.
2. Generate a password hash on the server (do not use or commit a plain-text password):
   `php -r 'echo password_hash("REPLACE_WITH_A_LONG_UNIQUE_PASSWORD", PASSWORD_DEFAULT), PHP_EOL;'`
3. Add the resulting hash as the Hostinger environment variable `UTTC_ADMIN_PASSWORD_HASH`. Never put the plain password or hash in GitHub, browser JavaScript, or chat.
4. Ensure PHP session support and the `mbstring` extension are enabled. The PHP process must be able to create/write `api/data/`.
5. The included `api/data/.htaccess` denies direct HTTP access to the JSON knowledge store and uploaded documents on Apache. Confirm this protection is active on the actual hosting configuration before uploading any real institutional documents.
6. Visit `/admin/`, sign in, add a question/answer or upload a supported document, review it, and only then press Publish.

### Admin retrieval preview
- The dashboard includes a private `AI Knowledge Retrieval Test` panel. It runs the same keyword-based ranking over the locally synced website index and Published admin entries.
- The preview returns matching source titles, source type, score and a short excerpt. It does not call the OpenAI API and does not validate the final generated answer.
- Draft entries are excluded. A zero-match result should be treated as an unanswered question and referred to the official contact number rather than guessed.

### Knowledge publishing and supported files
- New manual entries and extracted document contents are saved as Draft.
- Only entries with status `published` are appended to the assistant's retrieval corpus. Draft entries are excluded.
- Editing an entry returns it to Draft, requiring a fresh review and Publish.
- Supported uploads: PDF (requires an executable `pdftotext`), DOCX (requires PHP `ZipArchive`), TXT, and Markdown. Maximum upload size is 8 MB; extracted text is capped at 100,000 characters.
- If the server lacks the PDF/DOCX extraction dependency, the upload is rejected with a clear message; it is not silently indexed as empty text.
- Assistant and admin preview use the same retrieval choice: when `OPENAI_EMBEDDING_MODEL` and `OPENAI_API_KEY` are configured, the backend builds/caches embeddings for up to 500 approved corpus documents (each text is capped at 6,000 characters), then ranks questions with cosine similarity. The cache is fingerprinted to the corpus and embedding model and stored under `api/runtime/`, which the included Apache rewrite rule blocks from direct HTTP access. A corpus or model change rebuilds the index; the first request after a change can take longer and incur embedding API usage.
- If semantic indexing fails or its best cosine score is below 0.30, the assistant falls back to the shared BM25 retriever. BM25 uses term-frequency, inverse-document-frequency and title weighting. The 0.30 semantic threshold is an initial conservative setting and must be tuned against real UTTC questions before public launch. The Admin Retrieval Test displays whether it used Semantic Search, BM25 Fallback, or BM25 Keyword Search.
- This is retrieval-augmented generation (RAG): retrieved official excerpts are supplied to the Responses API. It is not model fine-tuning. The model must still be evaluated for grounded answers and refusal behavior before public launch.
- The browser retains up to 10 recent user/assistant messages in `sessionStorage` for the current tab session and sends them as dialogue context. The API validates and caps the history; only the latest prior user question is added to the retrieval query. Conversation history is never added to the approved institutional knowledge corpus and must not be treated as a source of institutional facts.
- Each successful Knowledge Base change preserves the previous version in `api/data/knowledge-history.json` (up to 30 versions). The admin dashboard can display timestamps, entry counts and titles. This history is private under `api/data/.htaccess`; it is not a full audit log of who made each change.
- CI runs `tests/retrieval-test.php` (including cosine-similarity math checks) and `tests/document-extract-test.php` after PHP syntax checks. CI installs `mbstring`, `dom`, `zip`, `fileinfo` and `poppler-utils`, then exercises TXT, DOCX and PDF extraction fixtures. These are regression/smoke tests, not end-to-end tests of the OpenAI API, authenticated browser uploads, or Hostinger deployment.

### Important deployment checks
- This repository change is only a code change. It does not configure Hostinger, create the admin password, upload files, or activate the dashboard.
- Before deployment, confirm the canonical UTTC domain and update `UTTC_SITE_URL`, `UTTC_ALLOWED_ORIGINS`, the front-end source-link allowlist, and any old hard-coded contact/source URLs consistently.
- Verify that `api/data/` is not web-accessible, the admin password hash is set, and HTTPS/session cookies work.
- Do not upload real student personal data, identity documents, passwords, payment details, or sensitive records into this knowledge base. Store only approved general institutional information.
- Back up the knowledge JSON and uploaded files securely. Restrict server file permissions and review admin access periodically.
