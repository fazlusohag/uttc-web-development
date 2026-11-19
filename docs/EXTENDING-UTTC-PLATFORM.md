# UTTC Platform — Extension and Module Development Guide

This branch is an integration candidate. Keep future work additive, modular and backward-compatible.

## 1. Branch and release policy
- Build and test on `release/uttc-complete-platform-2026` or a short-lived feature branch created from it.
- Do not develop directly on `main`; merge only through a reviewed pull request after release gates pass.
- Keep changes scoped: prefer one feature/module per pull request and document migration, configuration and rollback needs.
- Never force-push shared release history. Do not deploy to Hostinger until separately approved.

## 2. Current module boundaries
| Area | Location | Contract |
|---|---|---|
| Public pages | Root `*.html` | Static pages remain usable without CMS JavaScript |
| Public CMS renderer | `assets/js/cms-public.js` | Fetches Published records and renders user text as DOM text |
| CMS admin UI | `admin/cms.php` | Authenticated UI; shares Knowledge Base session |
| CMS write API | `api/cms.php` | Admin session + CSRF required; Draft-first writes |
| CMS public API | `api/cms-public.php` | Read-only; Published records only |
| AI Knowledge Base | `admin/index.php`, `api/admin.php` | Separate Draft/Publish workflow |
| Retrieval | `api/retrieval.php`, `api/semantic-retrieval.php` | BM25 baseline with optional semantic retrieval |
| Private storage | `api/data/` | Must remain non-public and backed up securely |

Keep public rendering, admin writes, and AI retrieval as separate responsibilities. A new module must not directly modify another module's private JSON files.

## 3. Adding a CMS record type
1. Define purpose, required fields, limits, visibility and lifecycle.
2. Add the type to `api/cms.php` and, only if public, `api/cms-public.php`.
3. Add a dedicated UI section in `admin/cms.php`; avoid coupling unrelated forms.
4. Add a renderer in `assets/js/cms-public.js` or a dedicated module. Render untrusted text with DOM nodes/`textContent`, never raw HTML.
5. Test invalid types, Draft exclusion, Published visibility, filtering, validation and safe URL handling.
6. Document fields and backward compatibility in this guide and the API README.

Use explicit public-field allowlists. Never expose private types or admin-only fields.

## 4. API and storage rules
- Keep the JSON response envelope (`ok` plus a named result collection) stable unless introducing a versioned endpoint.
- New optional fields need safe defaults so older stored records still load.
- For breaking changes, use a versioned endpoint or explicit migration; never silently reinterpret existing records.
- Validate type, length, URL scheme and identifiers server-side.
- Require the existing admin session and CSRF token for every state-changing admin action.
- Keep private files outside public web access. Verify denial on the real host; `.htaccess` alone is not host verification.
- Use atomic file replacement and locking. For higher concurrency or multi-instance hosting, migrate behind the same API contract to a database/repository layer.

## 5. Front-end and accessibility
- Preserve static page fallback; API failure must not break public pages.
- Keep modules responsive, keyboard-operable, labelled and compatible with reduced motion.
- Namespace module CSS; avoid broad global selectors.
- Keep JavaScript dependency-light and load only what a feature needs.
- Keep page keys, canonical URLs and metadata consistent with the production domain.

## 6. Configuration and secrets
- Add documented server environment variables with safe defaults.
- Never commit API keys, password hashes, credentials, sessions, private knowledge data, logs or real student records.
- Expose only boolean configuration state or non-sensitive operational counts.
- Document PHP extensions, permissions, cron jobs, limits and rollback steps.

## 7. Definition of done
- PHP lint and JavaScript syntax checks pass.
- Tests cover success, validation failure, unauthorized access and Draft/Published behavior as applicable.
- Existing regression tests pass.
- Public pages remain usable if the module is unavailable.
- Mobile, keyboard, accessibility, SEO and security checks are recorded.
- README/API docs and release notes are updated.
- Hostinger behavior remains marked unverified until tested on the actual host.

## 4. Knowledge Base backups
- Run `php scripts/backup-knowledge-base.php` from the project root using the CLI; web requests are rejected.
- The script acquires a shared Knowledge Base lock, validates the JSON data and revision history, and snapshots those files plus uploaded source documents when present.
- Snapshots are written under the private `api/data/backups/` directory with restrictive permissions and a SHA-256 manifest. The data directory's deny-all access rule must remain in place.
- Retention defaults to the latest 30 snapshots. Set `UTTC_BACKUP_RETENTION` to an integer from 1 to 365 to change it.
- The script refuses invalid source JSON, symbolic links and snapshots larger than 100 MB. It does not restore files or alter live Knowledge Base data.
- Configure a Hostinger cron job only after validating the PHP CLI path and private storage permissions on the actual hosting account. Keep an independent off-server backup as well; this local snapshot is not disaster recovery by itself.

- Verify a completed snapshot without changing data: `php scripts/verify-knowledge-base-backup.php <snapshot-id>`. The verifier checks the manifest, file sizes, SHA-256 hashes, safe relative paths, symbolic links and the Knowledge Base/History JSON structures. It requires the Knowledge Base JSON file and never writes to the live data directory.
- Restore is intentionally not part of this verifier. A future restore workflow must require an explicit operator action, verified snapshot, pre-restore snapshot and atomic rollback plan.

## 5. Explicit Knowledge Base restore
- Restore is never automatic. An operator must explicitly run `php scripts/restore-knowledge-base-backup.php <snapshot-id>` from the project root.
- The selected snapshot is verified first. The script then creates and verifies a fresh pre-restore backup before acquiring the exclusive Knowledge Base lock.
- It compares the live JSON hashes again under lock and aborts if data changed during safety-backup preparation.
- Only `knowledge-base.json` and the optional `knowledge-history.json` are restored. Uploaded source documents are not replaced.
- JSON files are staged with restrictive permissions and replaced using same-filesystem renames. If replacement fails, the script attempts to restore the previous files from its protected staging area.
- After restore, verify the service and retain both the selected snapshot and the pre-restore safety snapshot. This is a guarded application-level restore, not a substitute for a full hosting/filesystem disaster-recovery backup.
