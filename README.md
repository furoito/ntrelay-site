# NT Relay business website

Japanese-language one-page business website for NT Relay.

Production: https://ntrelay.com/ (ConoHa shared hosting, `public_html/ntrelay.com/`).
Source control: this repository. Production deployment is performed manually by copying `index.html` and `robots.txt` to the hosting directory.

The hosting directory also contains **separate OAuth and MCP endpoints and `.well-known` files**. Never delete or overwrite them when deploying this website.

The contact form uses FormSubmit and may require re-activation when the origin hostname changes. Keep DNS MX, SPF, DKIM and the Cloudflare Pages portfolio subdomain intact.
