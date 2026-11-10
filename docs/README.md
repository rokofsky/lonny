# Visual preview only

These files are a **static screenshot-like export** of the V2 PHP website. They are intended for GitHub Pages and do not provide a working CMS, visitor forms, moderation, or bookings. The Monthly Experiment remains interactive in the browser.

To publish: go to repository **Settings → Pages → Build and deployment → Deploy from a branch → main → /docs → Save**.

Preview URL, once enabled: https://rokofsky.github.io/lonny/

To regenerate after changes to PHP/CSS: `pip install beautifulsoup4`, then `python scripts/build_preview.py`. Always review the generated HTML before making it public.

**Never use this static preview as Lonny's production website.** Deploy the PHP application separately with a properly protected CMS and complete privacy notice.