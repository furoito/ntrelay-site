# NT Relay business website

Japanese-language one-page business website for NT Relay.

Production: https://ntrelay.com/ (ConoHa shared hosting, `public_html/ntrelay.com/`).
Source control: this repository. Production deployment is performed manually by copying `index.html` and `robots.txt` to the hosting directory.

The hosting directory also contains **separate OAuth and MCP endpoints and `.well-known` files**. Never delete or overwrite them when deploying this website.

The contact form uses FormSubmit and may require re-activation when the origin hostname changes. Keep DNS MX, SPF, DKIM and the Cloudflare Pages portfolio subdomain intact.

## SEO pages (2026-11)

- `excel-automation/index.html` — Excel/CSV automation service, pricing, consultation
- `excel-multiple-files/index.html` — Power Query tutorial and related service CTA
- `sitemap.xml` — root and the two SEO pages
- `robots.txt` — sitemap discovery

**Deployment remains manual:** the GitHub repository does not publish to ConoHa automatically. To publish these changes, copy the two page directories and `sitemap.xml` into `public_html/ntrelay.com/`, and update **only** `index.html` and `robots.txt` in that directory. Do not remove or overwrite unrelated OAuth, MCP, `.well-known`, PHP, or other live hosting files. Verify HTTP 200, canonical URLs, navigation links, and the sitemap after upload.
