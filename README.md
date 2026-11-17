# UTTC Website — Moulvibazar

Premium, responsive course-marketing website for **Upazila Technical Training Center (UTTC), Moulvibazar**.

- Production domain target: https://uttc.edu.bd/
- Primary language: Bengali (with English course and technical terms)
- Design palette: Navy `#142D4E`, institutional green `#237A57`, white, light grey
- Contact: 01727-007417
- Admission: redirects to the existing external portal at https://www.uttcoffice.edu.bd/id_create.php

## Pages

- Home: `index.html`
- About: `about-uttc-moulvibazar.html`
- Driving: `driving-training-moulvibazar.html` (three learning levels)
- Women’s Driving: `women-driving-training-moulvibazar.html`
- Computer Office Application: `computer-office-application-moulvibazar.html` (33-class learning outline)
- Freelancing: `freelancing-course-moulvibazar.html`
- Graphic Design: `graphic-design-course-moulvibazar.html`
- Facilities, Gallery, Notices, FAQ, Contact, Privacy Policy and 404 page

## Technical structure

- Static HTML, CSS and browser JavaScript for the public website.
- PHP 8.1+ backend in `api/` for the optional UTTC AI Assistant.
- `sitemap.xml` and `robots.txt` use the target domain.
- No internal student database, student login, or admin dashboard is included. Student admission and result links remain on the existing external portal.

## Hostinger deployment checklist

1. Back up the current target site's document root before replacing files.
2. Upload the contents of this repository branch to the `public_html` folder for `uttc.edu.bd` (not the separate `uttcoffice.edu.bd` site).
3. Enable HTTPS and verify both the apex and www domain behavior.
4. Confirm PHP 8.1+ and the `curl`, `dom`, `SimpleXML`, and `mbstring` extensions if enabling the AI endpoint.
5. Configure server environment variables in Hostinger; never place secrets in HTML, JavaScript, or Git:
   - `OPENAI_API_KEY` — secret API key
   - `OPENAI_MODEL` — model enabled for the API project
   - `UTTC_SITE_URL=https://uttc.edu.bd`
   - `UTTC_ALLOWED_ORIGINS=https://uttc.edu.bd,https://www.uttc.edu.bd`
6. Run `php /home/ACCOUNT/public_html/api/sync-knowledge.php` once, then schedule it periodically. This creates the private local `api/knowledge.json` index from the published sitemap.
7. Test the AI endpoint with a POST JSON request to `/api/assistant.php`. The AI assistant will return a setup/knowledge message until the server key, model and knowledge index are configured.
8. Test all navigation, forms/links, WhatsApp, click-to-call, mobile layout, sitemap and Google Search Console after deployment.

## Important

- This repository branch is prepared for deployment but is not proof that the live domain has been updated.
- Do not commit API keys, generated knowledge files, server logs, or other secrets.
- Confirm course fees, schedules, duration, eligibility and current notices with the office before publishing specific values; this site avoids inventing them.
