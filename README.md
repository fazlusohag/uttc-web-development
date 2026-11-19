# UTTC Website & AI Platform — Moulvibazar

Bengali-first public website and AI-assisted institutional information platform for **Upazila Technical Training Centre (UTTC), Moulvibazar**.

This is the integration/release workspace. Existing branches and commits are preserved. A GitHub commit or passing CI run does not mean the site is deployed or production-ready.

## Institution and design

- Institution: Upazila Technical Training Centre (UTTC), Moulvibazar, Bangladesh
- BTEB registration/approval code: **62065**
- Primary language: Bengali; use English for course and technical terms where useful
- Design palette: Navy #142D4E, institutional green #237A57, white #FFFFFF, light grey #F3F5F8
- Intended public domain: https://uttc.edu.bd/
- Existing external admission portal: https://www.uttcoffice.edu.bd/id_create.php
- Contact number currently present in site/AI fallback: 01727-007417. Confirm before production if it has changed.

## What is in this branch now

### Public website
The repository contains static Bengali public pages for Home, About, Driving, Women's Driving, Computer Office Application, Freelancing, Graphic Design, Facilities, Gallery, Notices, FAQ, Contact, Privacy Policy and 404, plus sitemap and robots files.

### AI Knowledge Base Dashboard
The current /admin/ implementation is an **AI Knowledge Base Dashboard**, not yet a full website CMS. It provides:
- Password-hash-based administrator login (requires server-side UTTC_ADMIN_PASSWORD_HASH)
- CSRF token checks and session-based authorization
- Login attempt throttling
- Knowledge entry create/edit as Draft, publish, list and delete
- PDF/DOCX/TXT/Markdown extraction into Draft entries (server dependencies apply)
- Knowledge update-history summaries
- AI retrieval preview against website index and published admin knowledge

### AI assistant backend
- api/assistant.php: public AI question endpoint using OpenAI Responses API
- api/retrieval.php: BM25 lexical retrieval
- api/semantic-retrieval.php: optional embedding-based retrieval with BM25 fallback
- api/sync-knowledge.php: command-line sitemap sync; do not expose as a public write endpoint
- api/document-extract.php: extraction helpers

## Not yet implemented / not yet verified

- A separate, full **Website Admin CMS** for editing all public pages, course information, notices, gallery items, menus and contact details is not present yet. The Knowledge Base Dashboard does not provide those CMS functions.
- The AI Knowledge Base Dashboard is the current admin interface; a separate AI operations/analytics dashboard is not yet implemented.
- No live Hostinger deployment or server-side configuration has been performed by this repository workflow.
- Real OpenAI API calls, production upload behavior, Hostinger filesystem protections, HTTPS/session behavior, visual browser QA, accessibility, SEO crawl and live domain behavior have not been verified here.
- CI does not test a live OpenAI account, Hostinger configuration, actual browser admin workflows or end-to-end production security.

## Important files

- Public homepage: index.html
- Admin Knowledge Base UI: admin/index.php
- Admin API: api/admin.php
- AI endpoint: api/assistant.php
- Server configuration example: api/config.example.php
- Backend setup notes: api/README.md
- Tests: tests/
- CI: .github/workflows/php-lint.yml

## Security requirements before production

- Store OPENAI_API_KEY, OPENAI_MODEL, optional OPENAI_EMBEDDING_MODEL and UTTC_ADMIN_PASSWORD_HASH only as server environment variables. Never commit credentials or password hashes.
- Enforce HTTPS and verify secure, HttpOnly, SameSite session cookies on the actual hosting server.
- Verify that api/data/, api/runtime/, uploaded documents, generated indexes and logs cannot be downloaded over HTTP. Do not assume Apache/LiteSpeed rules are effective until tested on Hostinger.
- Keep admin pages out of search indexing.
- Public AI must use only approved/published knowledge; Draft records and uploaded source files must remain private.
- Confirm PHP extensions, upload limits, storage permissions and PDF/DOCX extraction dependencies.
- Use only approved general institutional information. Do not upload student identity documents, passwords, payment details or sensitive personal records.
- Verify all changing institutional facts, including fees, schedules, eligibility, notices and contact details, with UTTC before publishing.

## CI checks

The GitHub Actions workflow runs PHP syntax checks and repository regression/smoke tests for retrieval, HTTP/CORS behavior and document extraction. A successful run means only that the configured automated checks passed in GitHub's runner.

## Hostinger release checklist — not performed

1. Confirm the exact target domain and document root. Do not confuse uttc.edu.bd with the separate uttcoffice.edu.bd admission portal.
2. Review and approve the release branch.
3. Back up the existing website and database/files as applicable.
4. Confirm PHP version, extensions, environment-variable support and private writable storage.
5. Deploy only after explicit deployment approval.
6. Set server secrets and admin password hash securely.
7. Run the sitemap sync through SSH/cron and verify private index storage.
8. Test login, CSRF, throttling, Draft/Publish, document extraction, retrieval and AI responses with approved UTTC questions.
9. Test all pages, links, admission redirect, phone/WhatsApp, mobile layouts, accessibility, canonical URLs, sitemap and robots.
10. Verify HTTPS, access restrictions, logs, backups, domain routing and search indexing.

## Branch safety

- Existing branches are retained as history and rollback points.
- This release branch is based on feature/uttc-ai-knowledge-base.
- Do not delete legacy branches, force-push, or merge into main before review and approval.
- Keep changes traceable and record unresolved risks in docs/RELEASE-STATUS.md.
