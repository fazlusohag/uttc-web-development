# UTTC Release Branch — Review Status

Date: 2026-11-19
Branch: `release/uttc-complete-platform-2026`

## Work completed

- Added the authenticated Website CMS at `admin/cms.php`, reusing the existing admin session and CSRF protection.
- Added `api/cms.php` for listing, Draft creation/update, Publish and delete actions, plus non-secret AI operations status.
- CMS writes use file locking, atomic file replacement and bounded text validation with an mbstring fallback.
- CMS audit events are appended only after the corresponding content write succeeds; the authenticated lifecycle test checks Create and Publish audit entries.
- Knowledge-base source uploads are deleted only after the knowledge-base record deletion is saved successfully; stored filenames are reduced to basenames before filesystem deletion.
- AI Assistant, CMS and Knowledge Base admin JSON endpoints reject request bodies larger than 64 KB before JSON decoding; assistant and CMS integration tests assert the 413 response.
- CMS authenticated lifecycle coverage includes session/CSRF enforcement, Draft isolation, Publish visibility, Delete removal from the public feed, audit events, and the five-failure login throttle.
- Admin login throttling clears the caller's failure bucket after successful authentication, expires old IP buckets, and caps stored buckets at 1,000 to prevent unbounded growth.
- Admin HTML pages send no-store caching, MIME-sniffing protection, clickjacking protection, a strict referrer policy, and disable unused camera/microphone/geolocation permissions. The HTTP regression test checks these response headers.
- PDF text extraction caps parser output at 2 MB and limits captured parser diagnostics.
- Failed knowledge-base upload saves remove the moved upload through a shutdown cleanup guard, avoiding orphaned private documents.
- CLI knowledge sync accepts only same-host HTTPS sitemap URLs, parses XML with network access disabled, and uses a lock plus unique temporary file for atomic index replacement. Sitemap and page downloads are capped at 2 MB each, and a single run indexes at most 300 unique pages.
- Added CMS record types: Course, Notice, Gallery, Page, Contact and Navigation Menu.
- Added SEO title, meta description and canonical HTTPS URL fields for Page records. CMS URL validation now rejects embedded username/password credentials in HTTPS links, image URLs and canonical URLs, matching the public renderer’s URL policy. Only one CMS Page record can target each existing public-page slug; the API rejects duplicate targets with HTTP 409 so administrators update the existing record instead of creating ambiguous metadata/content.
- The public CMS script memoizes in-flight and completed feed requests by content type, reducing duplicate requests when metadata and page content need the same Page feed; failed requests are evicted so a later call can retry.
- CMS Publish now revalidates legacy records at the point of publication: allowed record type, non-empty title/body, course page assignment, Page slug, duplicate published Page target and credential-free HTTPS URLs. CMS reads also discard malformed non-array entries before admin list sorting/rendering. Authenticated integration tests include legacy records with invalid types, empty bodies, invalid page assignments/slugs and credential-bearing URLs.
- Added `api/cms-public.php`, a read-only endpoint that returns Published records only.
- Added `assets/js/cms-public.js`; CMS text is rendered with DOM text nodes, not raw HTML, and outbound/image URLs are restricted to HTTPS.
- Connected Published Notice and Gallery feeds to their public pages.
- Connected Published Course feeds to Driving, Women's Driving, Computer Office Application, Freelancing and Graphic Design pages, with explicit page-key assignment to prevent cross-course content appearing on unrelated pages.
- Added `data-page-key` and the shared CMS integration script to all 14 public HTML pages so Published Page metadata and Navigation Menu records can be applied where compatible markup exists.
- Prevented duplicate CMS navigation links when the same URL or label already exists in the page navigation.
- Added CI checks for JavaScript syntax, static CMS page wiring, public CMS API behavior (Published-only visibility, public-type allowlisting, type filtering, Page SEO field exposure and invalid-type rejection), and authenticated CMS lifecycle (shared admin session, CSRF, Draft isolation, explicit Publish, audit events, duplicate Page prevention and rejection of credential-bearing URLs).
- Added `docs/EXTENDING-UTTC-PLATFORM.md` to define module boundaries, extension rules, API compatibility, storage/security rules and the definition of done for future features.
- Updated the project README to describe the current implementation and its limitations.

## Current admin modules

1. **AI Knowledge Base:** existing knowledge-entry Draft/Publish, document extraction, update-history summaries and retrieval preview at `admin/index.php`.
2. **Website CMS:** structured content and SEO/navigation fields at `admin/cms.php`.
3. **AI Operations status:** non-secret configuration flags, indexed-page count, published knowledge count, PHP version and CMS update time. This is a basic status panel, not full analytics or alerting.

## Known limitations

- CMS records are additive. They do not edit or replace all hard-coded content in the existing static HTML pages.
- The single-record-per-slug rule prevents conflicting Page metadata/content, but administrators must edit the existing record to update a page rather than create a second record.
- Page SEO metadata is applied client-side after the shared script loads. It is not server-rendered metadata and should not be considered a complete SEO implementation for crawlers that do not execute JavaScript. Existing static title/description/canonical tags remain the fallback.
- Navigation Menu records are appended only where a compatible `header nav` element exists. Pages without that structure will not show CMS menu items.
- Image file upload is not implemented; CMS images must be provided as HTTPS URLs.
- Contact records are stored but do not automatically replace the hard-coded contact details throughout the website.
- AI Operations does not provide usage analytics, request logs, cost reporting, alerts or live health checks.
- The CMS and AI Knowledge Base have not been exercised in an authenticated browser against a live PHP server.
- Hostinger storage access rules, HTTPS cookies, PHP extensions, file permissions, environment-variable support, upload parser dependencies and actual OpenAI API calls remain unverified.
- GitHub status and workflow-run lookups returned no check records for the latest release commit; therefore the newest changes have not been independently confirmed by CI yet.
- Visual browser QA, mobile QA, accessibility review, SEO crawl, live links and institutional content verification remain release gates.

## Security observations and deployment checks

- CMS write actions require the existing session admin flag and CSRF token.
- Public CMS API filters to Published records and an explicit allowlist of public record types; internal/unknown record types are never exposed.
- Public rendering avoids HTML injection and restricts external content URLs to HTTPS. CMS writes also reject embedded URL credentials, including for canonical URLs.
- Private CMS JSON data relies on `api/data/.htaccess`. Verify direct access denial on the actual Hostinger server; if the host does not enforce it, move private data outside the document root.
- Never commit API keys, password hashes, private knowledge files, session data, logs or uploaded source documents.
- Confirm the admin password hash is configured, HTTPS is active, and Secure/HttpOnly/SameSite cookie behavior works on the actual server.

## CI

The GitHub Actions workflow runs PHP 8.3 syntax checks, retrieval regression tests, assistant HTTP/CORS/request-size tests, document extraction smoke tests, Node syntax validation for the CMS JavaScript and static checks that all public pages load the CMS integration. The latest branch commit's Actions result has not yet been confirmed through the available GitHub status interface. CI does not prove live Hostinger security configuration, real OpenAI API access or browser rendering.

## Release decision

**Status: integration candidate, not production-final.**

Complete authenticated browser tests, Hostinger-specific security checks, visual/accessibility/SEO QA and final content verification. Review and merge through a reviewed pull request only after those gates pass. Do not upload to Hostinger until separately approved.

- Fixed CMS navigation injection to target the site's actual `.nav-links` list and append valid `<li><a>` items; added static regression checks for the selector, list structure, and text-only rendering. Previously the integration looked only for `header nav`, which does not exist in the main page markup, so published CMS menu items were silently omitted.

- CMS-managed Page metadata now synchronizes the browser title, standard meta description, Open Graph title/description and an optional validated HTTPS canonical URL. The shared URL validator also rejects HTTPS URLs containing embedded username/password credentials. Static metadata remains the initial HTML fallback; dynamic metadata still requires JavaScript and is not a substitute for server-rendered SEO.

- Added an accessible native-dialog Preview action to the Website CMS record list. Admins can inspect the current Draft or Published title, body, image and related link before publishing; preview text is assigned with `textContent`, and preview URLs are restricted to HTTPS without embedded credentials. This is a content-level preview, not a pixel-perfect rendering of the final public page. Static regression checks cover the dialog, action, safe text rendering and URL validation.

- Improved the primary mobile navigation: the toggle now exposes `aria-controls`, a Bengali accessible label that changes with open/closed state, Escape closes the menu and returns focus to the toggle, selecting a navigation link closes it, and clicking outside closes it. Added static regression checks for these behaviors.
- Public CMS image cards now remove an image element if the remote image fails to load, avoiding a broken-image placeholder. Added a static regression check for the fallback handler.

- Added a Bengali keyboard-accessible “মূল কনটেন্টে যান” skip link to all 14 public pages, with a visible focus state and a stable `main-content` target. Extended the static integration test to require the link, target and focus styling on every page. This improves keyboard navigation; it does not replace a full screen-reader and browser accessibility audit.

- All public pages now respect the operating-system `prefers-reduced-motion` setting by disabling smooth scrolling and minimizing CSS animation/transition duration for visitors who request reduced motion. The CMS static integration test now checks this support on every listed public page.

- Added a required public-page assignment for CMS Course records. The CMS editor offers only the five existing course-page keys, the write API validates the assignment, and the public renderer filters by the current page key. Legacy course records without a valid assignment are intentionally not shown until an administrator edits and assigns them; this prevents accidental cross-page content leakage.

- Hardened CMS JSON replacement against partial disk writes: temporary files are created exclusively, writes loop until the complete JSON payload is written, buffered data is flushed (and synced where PHP supports it), and the final atomic rename is attempted only after successful completion. On failure the temporary file is removed and the prior CMS file is left in place. Static regression assertions cover the write loop, flush/sync and rename gate.


- CMS storage now fails closed when the JSON file cannot be read or its JSON/top-level `items` structure is invalid. The authenticated admin API returns HTTP 500 instead of treating corrupted data as an empty list, so a later save cannot silently overwrite the damaged store. The public feed also returns HTTP 500 rather than reporting a misleading empty feed. Integration tests cover corrupt-file reads, blocked writes, byte-for-byte preservation and public API failure. Restore from a verified backup before resuming CMS edits; this behavior intentionally does not attempt automatic repair.

- The AI Knowledge Base admin API now fails closed when `knowledge-base.json` is unreadable, invalid JSON, or missing its `entries` array. It no longer presents corrupt content as an empty knowledge base. Before saving a new revision, the API also validates an existing `knowledge-history.json`; a damaged history blocks the write rather than silently resetting the revision history. Static regression assertions cover both protections. This change does not automatically repair or restore damaged files.

- Added a read-only CLI backup verifier. It validates the snapshot manifest, safe relative paths, symlink-free files, byte counts, SHA-256 checksums and the Knowledge Base/History JSON structures. It requires the primary Knowledge Base file and never writes live data.
- Added an explicit CLI-only Knowledge Base restore utility. It verifies the selected snapshot, creates and verifies a pre-restore safety snapshot, acquires the exclusive data lock and aborts if live JSON changed during safety-backup preparation. It stages validated JSON files and uses same-filesystem renames with a rollback attempt on failure. Restore scope is limited to `knowledge-base.json` and optional `knowledge-history.json`; uploaded source documents are intentionally untouched.
- Static regression assertions cover the restore guardrails. Actual execution against a disposable filesystem fixture and the GitHub Actions result remain unverified; do not treat the restore utility as production-validated until those checks are run. No Hostinger deployment or live restore has been performed.

- Added an isolated end-to-end PHP fixture test for backup creation, snapshot verification, successful Knowledge Base and revision-history restoration, preservation of uploads created after the snapshot, verification of the pre-restore safety snapshot, and rejection of a tampered snapshot without changing live JSON. The test is wired into the PHP 8.3 GitHub Actions workflow.
- Hardened rollback bookkeeping so cleanup removes only files that this restore invocation successfully installed; it does not delete an unexpected filesystem entry encountered during rollback.
- The new end-to-end test has been added to CI but has not yet been observed running on GitHub Actions. A passing workflow is still required before considering the restore path validated.

- Extended the isolated restore integration fixture to cover snapshots created when revision history is absent: a stale live history file must be removed when restoring such a snapshot. The fixture also checks that uploads remain unchanged and that a tampered snapshot is rejected without modifying the live Knowledge Base JSON.
