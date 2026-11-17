# UTTC AI Assistant — Hostinger PHP backend

## Features
- PHP JSON endpoint using the OpenAI Responses API.
- Local retrieval from the published pages in the UTTC sitemap.
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
   - `UTTC_SITE_URL=https://uttc.online`
   - `UTTC_ALLOWED_ORIGINS=https://uttc.online,https://www.uttc.online`
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
