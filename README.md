# UTTC Website & AI Platform — Moulvibazar

Bengali-first public website and AI-assisted institutional information platform for **Upazila Technical Training Centre (UTTC), Moulvibazar, Bangladesh**.

This repository is maintained on an integration/release branch. A GitHub commit or passing CI run does not mean the site is deployed or production-ready.

## Institution and design

- Institution: Upazila Technical Training Centre (UTTC), Moulvibazar, Bangladesh
- BTEB registration/approval code: **62065**
- Primary language: Bengali; use English for course and technical terms where useful
- Design palette: Navy #142D4E, institutional green #237A57, white #FFFFFF, light grey #F3F5F8
- Intended public domain: https://uttc.edu.bd/
- Existing external admission portal: https://www.uttcoffice.edu.bd/id_create.php
- Contact number currently present in site/AI fallback: 01727-007417. Confirm before production if it has changed.

## Public website

The repository contains static Bengali public pages for Home, About, Driving, Women's Driving, Computer Office Application, Freelancing, Graphic Design, Facilities, Gallery, Notices, FAQ, Contact, Privacy Policy and 404, plus sitemap and robots files.

The website keeps admission on the existing external admission portal. This project does not replace that portal with a new student database or admission workflow.

## AI Knowledge Base Dashboard

The AI Knowledge Base Dashboard is available at `admin/index.php`. It includes:
- Password-hash-based administrator login (requires server-side `UTTC_ADMIN_PASSWORD_HASH`)
- CSRF token checks and session-based authorization
- Login attempt throttling
- Knowledge entry create/edit as Draft, publish, list and delete
- PDF/DOCX/TXT/Markdown extraction into Draft entries (server dependencies apply)
- Knowledge update-history summaries
- AI retrieval preview against website index and published admin knowledge

## Website CMS

The Website CMS is available at `admin/cms.php` and reuses the existing admin session and CSRF protection. It supports:
- Course, Notice, Gallery, Page, Contact and Navigation Menu records
- Draft, edit, publish and delete actions
- SEO title, meta description and canonical HTTPS URL fields for Page records
- HTTPS image URLs (direct file upload is not implemented)
- A read-only public endpoint at `api/cms-public.php` that returns Published records only

The shared `assets/js/cms-public.js` integration is loaded by the public HTML pages. It:
- Renders Published course records only on the explicitly assigned course page (Driving, Women's Driving, Computer, Freelancing or Graphic Design); each course record must have a matching Page assignment
- Renders published Notice and Gallery feeds on their respective pages
- Applies Page SEO metadata when a Published Page record's slug matches the page's `data-page-key`
- Adds Published Navigation Menu records to the page navigation where a header navigation element exists
- Uses DOM text nodes rather than injecting CMS text as HTML, and only accepts HTTPS image/link URLs

CMS records are additive: they do not rewrite or remove the existing hard-coded page content. Menu records may appear only on pages with a compatible `header nav` element. SEO metadata is applied client-side after page load; it is not a server-rendered SEO solution and should not be treated as a substitute for static HTML metadata.

## AI assistant backend

- `api/assistant.php`: public AI question endpoint using OpenAI Responses API
- `api/retrieval.php`: BM25 lexical retrieval
- `api/semantic-retrieval.php`: optional embedding-based retrieval with BM25 fallback
- `api/sync-knowledge.php`: command-line sitemap sync; do not expose as a public write endpoint
- `api/document-extract.php`: document extraction helpers

## Hostinger deployment requirements

See [api/README.md](api/README.md) for detailed backend setup. Before production:
1. Upload the website and `api/` directory to the intended Hostinger document root.
2. Configure `OPENAI_API_KEY`, `OPENAI_MODEL`, `UTTC_SITE_URL`, `UTTC_ALLOWED_ORIGINS`, and `UTTC_ADMIN_PASSWORD_HASH` as server environment variables. Configure `OPENAI_EMBEDDING_MODEL` only if desired and tested.
3. Confirm PHP version/extensions and writable data/runtime directories.
4. Verify private JSON storage cannot be downloaded directly. Apache `.htaccess` rules must be tested on the actual Hostinger server; move private data outside the document root if access denial cannot be confirmed.
5. Confirm HTTPS, Secure/HttpOnly/SameSite session cookies, admin login, CSRF, rate limits, upload limits and server logs.
6. Run the CLI sitemap sync using the correct absolute path.
7. Test the public website, CMS Draft/Publish, AI assistant and admission redirect on the live domain.

Never commit API keys, password hashes, private knowledge files, session data, logs or uploaded source documents.

## CI checks

GitHub Actions workflow `.github/workflows/php-lint.yml` runs PHP 8.3 syntax checks, retrieval regression tests, assistant HTTP/CORS tests and document extraction smoke tests. Client-side JavaScript syntax and CMS page wiring are also checked by the workflow. CI does not test a live Hostinger account, production browser session, actual OpenAI billing/API access or server filesystem protections.

## Release status

Current status is **integration candidate, not production-final**. Review [docs/RELEASE-STATUS.md](docs/RELEASE-STATUS.md) for completed work, limitations and release gates. Do not deploy to Hostinger until the production checks are completed and deployment is separately approved.

## Private Knowledge Base backup

A CLI-only backup utility is provided at `scripts/backup-knowledge-base.php`. From the project root, run `php scripts/backup-knowledge-base.php`. It snapshots validated Knowledge Base data, revision history and uploaded source files under the access-denied `api/data/backups/` directory, with a SHA-256 manifest and restrictive permissions. Retention defaults to 30 snapshots; `UTTC_BACKUP_RETENTION` accepts 1–365. A separate read-only verifier is available: `php scripts/verify-knowledge-base-backup.php <snapshot-id>`. It checks the manifest, listed files, SHA-256 hashes, sizes and JSON structure; it does not restore data or change live records. Configure cron only after verifying the PHP CLI path and storage permissions on the actual host, and maintain an independent off-server backup.

## Explicit Knowledge Base restore

Restore is a separate, destructive operator action and is never run automatically. First list the directory names under `api/data/backups/`, then run `php scripts/restore-knowledge-base-backup.php <snapshot-id>` from the project root. The script verifies the selected snapshot, creates and verifies a fresh pre-restore safety snapshot, acquires the exclusive data lock, and checks that live JSON did not change while the safety snapshot was prepared. It restores only `knowledge-base.json` and, when present in the selected snapshot, `knowledge-history.json`. Uploaded source documents are not replaced. If replacement fails, it attempts to roll back the original JSON files. Review the command output and verify the resulting data before returning the service to use.
