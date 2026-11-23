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

## AI購買対応・無料URL診断（2026-11-23）

- `ai-commerce-check/index.html` — 検索流入と問い合わせ獲得を目的とした無料の公開URL診断ページ
- `ai-commerce-check/assets/check.css` — 自己完結のデザインCSS
- `ai-commerce-check/assets/check.js` — 診断結果表示（HTML組み立てはtextContent使用）
- `ai-commerce-check/api.php` — 診断API（POST/JSONのみ）
- `ai-commerce-check/lib.php` — HTML検査、HTTPS取得、DNS/SSRF対策、回数制限

**必要な本番環境**: PHP 8.1以上、cURL、DNS取得関数、書き込み可能な`sys_get_temp_dir()`。本番サーバーでエンドツーエンドの成功確認は未実施。公開前に代表的なEC商品URLで実通信を確認すること。

**重要**: 本APIは任意の公開URLへGETリクエストを行うため、DNS/IP検証、TLS検証、固定接続IP、HTTPSのみ、リダイレクト再検証、レスポンスサイズ上限、レート制限を外さないこと。robots.txtやsitemap.xmlは取得確認であり、AIクローラーが許可されている証明ではない。

**公開手順**: 既存のConoHa上の`public_html/ntrelay.com/`に`ai-commerce-check/`ディレクトリだけを追加し、別途`agentic-commerce/index.html`、ルート`index.html`、`sitemap.xml`の差分を確認して反映。OAuth/MCP/.well-known等の既存ファイルは変更しない。

**テスト**: `php -l`、`node --check`、不正URL拒否、正常なProduct JSON-LD、構造化データが欠落する場合を検証。商品ページの静的HTMLだけを確認するためWallet決済の成功やOpenAI推奨を判定したり、`Product` JSON-LDをOpenAIフィードの代替と扱ったりしない。
