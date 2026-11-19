# UTTC Release Branch — Review Status

Date: 2026-11-19
Branch: release/uttc-complete-platform-2026

## Work completed in this iteration

- Added an authenticated Website CMS at admin/cms.php using the existing admin session and CSRF token.
- Added api/cms.php for Draft, Publish, edit, delete, listing and non-secret AI operations status; record writes use file locking and atomic replacement, and text length validation has an mbstring fallback.
- Extended Website CMS controls for Navigation Menu records and SEO title, description and canonical HTTPS URL fields.
- Added api/cms-public.php as a read-only endpoint that returns Published records only.
- Added assets/js/cms-public.js. It renders text using DOM text nodes, not raw HTML, and accepts only HTTPS outbound/image URLs.
- Connected the Notices and Gallery pages to published CMS records.
- Added published CMS course records to the Driving, Women's Driving, Computer, Freelancing and Graphic Design pages.
- Added a link from the AI Knowledge Base dashboard to the Website CMS.
- Updated README and API setup documentation.

## Current admin modules

1. AI Knowledge Base: existing knowledge-entry Draft/Publish, document extraction, history summary and retrieval preview at admin/index.php.
2. Website CMS: Course, Notice, Gallery, Page and Contact records at admin/cms.php.
3. AI Operations status: non-secret configured/not-configured flags, indexed-page count, published knowledge count, PHP version and CMS update time shown in the CMS dashboard.

## Remaining scope / limitations

- The CMS manages structured records and now includes fields for page SEO metadata and Navigation Menu records. Public metadata/menu rendering is implemented in the shared feed script, but page-by-page activation and end-to-end verification remain required. Image file upload is not implemented; use HTTPS image URLs only.
- CMS image content must use an HTTPS image URL. Direct media upload is not implemented.
- AI Operations is a basic status panel, not full analytics, alerting, cost reporting or request-log exploration.
- The CMS public feed is connected to notices, gallery and the five course pages. Other page/contact fields are stored but do not automatically replace all hard-coded public content.
- No live browser session has been exercised against an actual PHP server; CI syntax and regression tests cannot establish end-to-end login/session correctness.
- Hostinger storage access rules, HTTPS cookies, PHP extensions, file permissions, upload parser dependencies and real OpenAI requests remain unverified.
- Visual browser QA, mobile QA, accessibility review, SEO crawl, live links and institutional content verification remain required.

## Security observations

- CMS endpoints reuse the existing UTTC_KB_ADMIN session and require the session admin flag and CSRF token for writes.
- The public endpoint returns Published entries only.
- Public rendering uses textContent/createTextNode and restricts links/images to HTTPS.
- Private JSON data relies on api/data/.htaccess on compatible Apache configurations. On Hostinger, verify denial directly or move data outside the document root.
- Never commit API keys or password hashes. Keep them in server environment variables.
- Confirm the admin password hash, HTTPS and cookie Secure behavior on the actual server before production.

## CI and release decision

GitHub Actions is configured to lint all PHP files and run retrieval, assistant HTTP/CORS and document extraction tests. The latest workflow runs triggered by this series of commits must be checked for completion before release.

**Release status: integration candidate, not production-final.** Complete remaining CMS scope, run authenticated browser tests and security/accessibility/SEO QA, then open a reviewed PR for the agreed target branch. Do not upload to Hostinger until separately approved.
