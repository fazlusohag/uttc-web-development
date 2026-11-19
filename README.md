# UTTC Website & AI Platform — Moulvibazar

A Bengali-first, responsive institutional website and AI-assisted information platform for **Upazila Technical Training Centre (UTTC), Moulvibazar**.

> This branch is the integration/release workspace. It preserves the existing website and AI implementation while they are reviewed and brought together. A GitHub commit is not proof of a live Hostinger deployment.

## Project identity

- Institution: Upazila Technical Training Centre (UTTC), Moulvibazar
- BTEB registration/approval code: **62065**
- Primary language: Bengali; English for course and technical terms where useful
- Visual system: Navy `#142D4E`, institutional green `#237A57`, white, light grey `#F3F5F8`
- Public website target: https://uttc.edu.bd/
- Existing admission portal: https://www.uttcoffice.edu.bd/id_create.php
- Public contact number currently used in the site: 01727-007417

## Platform scope

The intended platform has three connected parts:

1. **Public website** — Home, About, Courses, Facilities, Gallery, Notices, FAQ, Contact, Privacy Policy and 404.
2. **UTTC Admin Panel** — authorized staff manage approved website information and institutional content.
3. **AI Knowledge Base Dashboard** — authorized staff create and review question/answer entries, upload supported documents, keep entries as Draft, publish approved information, inspect update history and run retrieval previews.

The admin and AI dashboard are private tools. They must not expose private documents, credentials or unpublished drafts to public visitors.

## Existing public pages

- `index.html`
- `about-uttc-moulvibazar.html`
- `driving-training-moulvibazar.html`
- `women-driving-training-moulvibazar.html`
- `computer-office-application-moulvibazar.html`
- `freelancing-course-moulvibazar.html`
- `graphic-design-course-moulvibazar.html`
- `facilities-uttc-moulvibazar.html`
- `gallery-uttc-moulvibazar.html`
- `notices-uttc-moulvibazar.html`
- `faq-uttc-moulvibazar.html`
- `contact-uttc-moulvibazar.html`
- `privacy-policy.html`, `404.html`, `robots.txt`, `sitemap.xml`

## Current technical structure

- Public frontend: static HTML, CSS and browser JavaScript.
- Backend: PHP 8.1+ under `api/`.
- AI endpoint: `api/assistant.php`.
- Knowledge sync: `api/sync-knowledge.php` (CLI/cron only; never expose as a public write endpoint).
- Admin UI: `admin/index.php`.
- Admin API: `api/admin.php`.
- Retrieval: `api/retrieval.php`, `api/semantic-retrieval.php`.
- Document extraction: `api/document-extract.php`.
- Automated checks: `tests/` and `.github/workflows/`.

## Security requirements before production

- Keep `OPENAI_API_KEY`, `OPENAI_MODEL`, `OPENAI_EMBEDDING_MODEL` and `UTTC_ADMIN_PASSWORD_HASH` in Hostinger server environment variables only. Never commit secrets.
- Use HTTPS; verify secure, HttpOnly and SameSite session cookies, CSRF protection, login throttling and authorization checks.
- Keep `api/data/`, `api/runtime/`, uploaded documents, generated knowledge indexes and logs inaccessible over HTTP. Verify this on the actual Hostinger server; do not assume Apache rules apply to every hosting setup.
- Keep admin routes out of search indexing; use `noindex,nofollow`.
- Only approved/published knowledge may be used by the public AI assistant. Draft content and source documents remain private.
- Confirm upload limits and PDF/DOCX extraction dependencies on the actual server.
- Do not publish fabricated course fees, schedules, eligibility, notices or employment guarantees. Confirm changing institutional facts with UTTC.

## Local / CI validation

Run the checks available in this repository before release:

```bash
find . -type f -name '*.php' -print0 | while IFS= read -r -d '' file; do php -l "$file"; done
php tests/retrieval-test.php
bash tests/assistant-http-test.sh
php tests/document-extract-test.php
```

CI uses PHP 8.3 and installs the PDF text extraction utility for document smoke tests. A passing CI run validates only the tests it executes; it does not prove live OpenAI API access, Hostinger configuration, real-world security, visual quality or production availability.

## Hostinger release checklist (not yet performed)

1. Back up the correct site's document root before deployment. Do not confuse `uttc.edu.bd` with the separate `uttcoffice.edu.bd` admission portal.
2. Review and approve the final release branch and its changes.
3. Confirm the correct Hostinger plan, PHP version, required extensions, environment-variable support and writable private storage.
4. Upload only after explicit deployment approval.
5. Configure server secrets and the admin password hash securely.
6. Run the knowledge sync from CLI/cron; verify the generated index is private.
7. Test admin login, CSRF, throttling, draft/publish, document extraction, retrieval and AI responses with real approved UTTC questions.
8. Test every public page, navigation link, admission redirect, phone/WhatsApp links, responsive layouts, accessibility basics, canonical URLs, sitemap, robots and structured metadata.
9. Verify HTTPS, domain routing, logs, backups and Google Search Console after deployment.

## Branching and preservation policy

- Existing branches are retained as history and rollback points.
- This release branch is based on `feature/uttc-ai-knowledge-base`, which contains the integrated public-site and AI/admin work.
- Do not force-push, delete legacy branches, or merge into `main` until review and tests are complete.
- Keep each change traceable in a commit and document unresolved risks.

## Important status

This repository work does not itself publish or change the live website. Hostinger deployment, server configuration and production verification remain separate steps requiring explicit approval.
