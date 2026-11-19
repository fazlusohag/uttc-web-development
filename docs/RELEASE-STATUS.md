# UTTC Release Branch — Review Status

Date: 2026-11-19
Branch: release/uttc-complete-platform-2026

## Verified in repository

- Public website pages, sitemap, robots file and UTTC logo assets are present.
- admin/index.php is a Knowledge Base Dashboard. It is not a full page/content CMS.
- api/admin.php includes password hash verification, CSRF token validation, session authorization, login throttling, Draft/Publish operations and document upload validation.
- api/assistant.php uses server environment variables for the OpenAI API key/model, published admin knowledge only, retrieval, request limits and a fixed institutional fallback when no reliable match is found.
- api/data/.htaccess denies direct access to the data directory on compatible Apache configurations. api/.htaccess blocks runtime and selected sensitive files on compatible Apache configurations.
- Automated workflow completed successfully on this branch: https://github.com/fazlusohag/uttc-web-development/actions/runs/44500626230

## Review findings / production risks

1. **Website CMS gap:** no separate interface/API currently manages public pages, course content, notices, gallery, menus or contact information. Build this before calling the requested main Website Admin Panel complete.
2. **No separate AI operations dashboard:** current admin page manages the knowledge base and retrieval preview. It is not a separate analytics/monitoring dashboard.
3. **HTTPS dependency:** admin session cookie Secure flag depends on the HTTPS server variable. Enforce HTTPS at the hosting layer and verify the cookie flags on the live domain.
4. **Storage protection is server-specific:** .htaccess protections must be tested on the actual Hostinger web server. Prefer storing private data outside the public document root where the hosting plan permits.
5. **Upload and parser dependencies:** confirm fileinfo, ZipArchive, DOM, mbstring, cURL, and pdftotext availability and limits on Hostinger.
6. **Operational verification outstanding:** no live OpenAI API, real authenticated browser workflow, Hostinger configuration, mobile browser, accessibility or production penetration test has been completed.
7. **Institutional content verification:** verify the current phone number, admission URL, course details, fees, schedules and notices with UTTC before public launch.

## Automated test result

The workflow run completed with conclusion success. This validates only the test steps in GitHub Actions; it is not a production security or deployment certification.

## Release decision

**Not production-final yet.** Keep this branch as the integration candidate. Complete the missing Website CMS, verify the AI/admin flows, conduct visual and security QA, then create a reviewed pull request for the agreed target branch. Do not upload to Hostinger until the release is explicitly approved.
