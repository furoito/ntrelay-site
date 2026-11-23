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

## AI購買・エージェンティックコマース SEOページ（2026-11-23）

- `agentic-commerce/index.html` — EC向けAI購買対応支援LP（サービス意図）
- `chatgpt-wallet-ec/index.html` — ChatGPT WalletのEC対応解説（情報収集意図）
- `chatgpt-product-feed/index.html` — Business Hub・商品フィード登録手順（実装意図）
- `agentic-commerce/assets/site.css` — 3ページ共通の独立CSS。**パスを維持して配置すること**

**公開順序:** 上記3つのページディレクトリを既存のConoHa `public_html/ntrelay.com/` 配下へ追加し、HTTP 200とCSSを確認。次に `index.html` と `sitemap.xml` のみ差分を反映する。既存のOAuth、MCP、`.well-known`、PHP、関係のないファイルを絶対に削除・上書きしない。`robots.txt` は変更不要。

公開後に確認するもの：3つのURL・CSSのHTTP 200、meta canonical、内部リンク、モバイル画面、`https://ntrelay.com/sitemap.xml`、Search Consoleへのサイトマップ提出・URL検査。掲載・順位・問い合わせは公開だけでは保証されない。

**掲載内容の注意:** ChatGPT Walletは2026-11-23時点で米国対象者向けの段階提供。OpenAIの公式認定・提携を示唆しない。商品フィード登録は推薦保証ではない。3ページの公式仕様確認日は、仕様更新時に追従する。
