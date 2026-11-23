<?php
declare(strict_types=1);

/** NT Relay AI commerce URL check. Public HTML signals only. */
function ac_trim_text(string $value, int $max = 180): string {
    $value = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function ac_normalize_url(string $input): string {
    $input = trim($input);
    if ($input === '' || strlen($input) > 2048 || preg_match('/[\x00-\x20\x7f]/', $input)) {
        throw new InvalidArgumentException('URLを確認してください。空白や制御文字は使用できません。');
    }
    if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $input)) $input = 'https://' . $input;
    $parts = parse_url($input);
    if (!is_array($parts)) throw new InvalidArgumentException('URLを解析できません。');
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) throw new InvalidArgumentException('HTTPSのWebページのみ診断できます。');
    if (isset($parts['user']) || isset($parts['pass'])) throw new InvalidArgumentException('認証情報を含むURLは使用できません。');
    if (isset($parts['port']) && (int)$parts['port'] !== 443 && !($scheme === 'http' && (int)$parts['port'] === 80)) {
        throw new InvalidArgumentException('標準ポートのWebページのみ診断できます。');
    }
    $host = strtolower((string)($parts['host'] ?? ''));
    if (function_exists('idn_to_ascii') && preg_match('/[^\x00-\x7f]/', $host)) {
        $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if ($ascii === false) throw new InvalidArgumentException('ドメイン名を解析できません。');
        $host = strtolower($ascii);
    }
    if (strlen($host) > 253 || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $host)) {
        throw new InvalidArgumentException('公開ドメイン名を入力してください。IPアドレスやローカル名は使用できません。');
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) throw new InvalidArgumentException('IPアドレスは診断できません。');
    if (preg_match('/\.(?:local|localhost|internal|test|invalid|example)$/', $host)) {
        throw new InvalidArgumentException('公開Webサイトのドメインを入力してください。');
    }
    $path = $parts['path'] ?? '/';
    if ($path === '') $path = '/';
    if ($path[0] !== '/') $path = '/' . $path;
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    $url = 'https://' . $host . $path . $query;
    if (!filter_var($url, FILTER_VALIDATE_URL)) throw new InvalidArgumentException('有効なURLを入力してください。');
    return $url;
}

function ac_ip_is_public(string $ip): bool {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) return false;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $a = array_map('intval', explode('.', $ip));
        if ($a[0] === 0 || $a[0] === 10 || $a[0] === 127 || $a[0] >= 224) return false;
        if ($a[0] === 100 && $a[1] >= 64 && $a[1] <= 127) return false;
        if ($a[0] === 169 && $a[1] === 254) return false;
        if ($a[0] === 172 && $a[1] >= 16 && $a[1] <= 31) return false;
        if ($a[0] === 192 && $a[1] === 168) return false;
        if ($a[0] === 192 && $a[1] === 0 && ($a[2] === 0 || $a[2] === 2)) return false;
        if ($a[0] === 198 && ($a[1] === 18 || $a[1] === 19)) return false;
        if ($a[0] === 198 && $a[1] === 51 && $a[2] === 100) return false;
        if ($a[0] === 203 && $a[1] === 0 && $a[2] === 113) return false;
        return true;
    }
    $packed = inet_pton($ip);
    if ($packed === false) return false;
    if (substr($packed, 0, 12) === str_repeat("\x00", 10) . "\xff\xff") return false;
    if ((ord($packed[0]) & 0xfe) === 0xfc) return false;
    if (ord($packed[0]) === 0xfe && (ord($packed[1]) & 0xc0) === 0x80) return false;
    return true;
}

function ac_public_dns_ip(string $host): string {
    $records = dns_get_record($host, DNS_A | DNS_AAAA);
    if (!is_array($records) || !$records) throw new RuntimeException('ドメインのDNSを確認できませんでした。');
    $ips = [];
    foreach ($records as $record) {
        $ip = $record['ip'] ?? $record['ipv6'] ?? null;
        if ($ip === null) continue;
        if (!ac_ip_is_public($ip)) throw new RuntimeException('安全でないネットワークへの接続を拒否しました。');
        $ips[] = $ip;
    }
    if (!$ips) throw new RuntimeException('接続可能な公開IPアドレスが見つかりません。');
    return $ips[0];
}

/** Bounded HTTPS GET pinned to public DNS; redirects validated at every hop. */
function ac_fetch_https(string $url, int $limitBytes = 1536000, int $redirects = 2): array {
    if (!function_exists('curl_init')) throw new RuntimeException('サーバーのHTTP取得機能が利用できません。');
    for ($attempt = 0; $attempt <= $redirects; $attempt++) {
        $url = ac_normalize_url($url);
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        $ip = ac_public_dns_ip($host);
        $data = '';
        $tooLarge = false;
        $headers = [];
        $ch = curl_init($url);
        if ($ch === false) throw new RuntimeException('接続を初期化できません。');
        $opts = [
            CURLOPT_RETURNTRANSFER => false, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*', CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'NTRelay-SiteCheck/1.0 (+https://ntrelay.com/ai-commerce-check/)',
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,text/plain,application/xml;q=0.8,*/*;q=0.3'],
            CURLOPT_RESOLVE => [$host . ':443:' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)],
            CURLOPT_HEADERFUNCTION => static function($ch, string $line) use (&$headers): int {
                if (str_starts_with($line, 'HTTP/')) $headers = [];
                $idx = strpos($line, ':');
                if ($idx !== false) $headers[strtolower(trim(substr($line, 0, $idx)))] = trim(substr($line, $idx + 1));
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function($ch, string $part) use (&$data, &$tooLarge, $limitBytes): int {
                if (strlen($data) + strlen($part) > $limitBytes) { $tooLarge = true; return 0; }
                $data .= $part;
                return strlen($part);
            },
        ];
        if (defined('CURLOPT_PROTOCOLS_STR')) $opts[CURLOPT_PROTOCOLS_STR] = 'https';
        else $opts[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        curl_setopt_array($ch, $opts);
        $success = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($tooLarge) throw new RuntimeException('ページが診断用の取得上限を超えました。');
        if ($success === false) throw new RuntimeException('ページを取得できませんでした。ネットワーク設定やアクセス制限を確認してください。');
        if ($status >= 300 && $status < 400 && isset($headers['location'])) {
            if ($attempt === $redirects) throw new RuntimeException('リダイレクトが多すぎます。最終ページURLを入力してください。');
            $location = $headers['location'];
            if (str_starts_with($location, '//')) $location = 'https:' . $location;
            elseif (str_starts_with($location, '/')) $location = 'https://' . $host . $location;
            elseif (!preg_match('~^https?://~i', $location)) {
                $basePath = (string)parse_url($url, PHP_URL_PATH);
                $directory = substr($basePath, 0, (strrpos($basePath, '/') ?: 0) + 1);
                $location = 'https://' . $host . $directory . $location;
            }
            $url = ac_normalize_url($location);
            continue;
        }
        return ['status' => $status, 'html' => $data, 'url' => $url, 'headers' => $headers];
    }
    throw new RuntimeException('リダイレクトを処理できませんでした。');
}

function ac_html_attr(string $tag, string $attribute): ?string {
    $name = preg_quote($attribute, '~');
    if (preg_match('~(?<![\w-])' . $name . '\s*=\s*(["\'])(.*?)\1~si', $tag, $m)) return html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (preg_match('~(?<![\w-])' . $name . '\s*=\s*([^\s>]+)~si', $tag, $m)) return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return null;
}
function ac_meta_content(string $html, string $name): ?string {
    preg_match_all('~<meta\b[^>]*>~is', $html, $tags);
    foreach ($tags[0] as $tag) {
        $metaName = ac_html_attr($tag, 'name') ?? ac_html_attr($tag, 'property');
        if (strcasecmp(trim((string)$metaName), $name) === 0) return ac_html_attr($tag, 'content');
    }
    return null;
}
function ac_find_link(string $html, string $rel): ?string {
    preg_match_all('~<link\b[^>]*>~is', $html, $tags);
    foreach ($tags[0] as $tag) {
        $value = ac_html_attr($tag, 'rel');
        if ($value !== null && in_array(strtolower($rel), preg_split('/\s+/', strtolower($value)), true)) return ac_html_attr($tag, 'href');
    }
    return null;
}
function ac_find_product_nodes(string $html): array {
    $result = [];
    preg_match_all('~<script\b[^>]*>.*?</script\s*>~is', $html, $scripts);
    foreach (array_slice($scripts[0], 0, 120) as $script) {
        if (!preg_match('~^<script\b[^>]*>~i', $script, $opening)) continue;
        if (strtolower(trim((string)ac_html_attr($opening[0], 'type'))) !== 'application/ld+json') continue;
        $json = preg_replace('~^<script\b[^>]*>|</script\s*>$~i', '', $script);
        if ($json === null) continue;
        $value = json_decode(trim($json), true);
        if (!is_array($value)) continue;
        ac_walk_jsonld($value, $result, 0);
    }
    return $result;
}
function ac_walk_jsonld(array $node, array &$result, int $depth): void {
    if ($depth > 16 || count($result) >= 100) return;
    $type = $node['@type'] ?? null;
    $types = is_array($type) ? $type : [$type];
    foreach ($types as $t) {
        if (!is_string($t)) continue;
        if (strtolower(preg_replace('~^.*[/#]~', '', $t)) === 'product') { $result[] = $node; break; }
    }
    foreach ($node as $val) {
        if (!is_array($val)) continue;
        if (array_is_list($val)) { foreach ($val as $item) if (is_array($item)) ac_walk_jsonld($item, $result, $depth + 1); }
        else ac_walk_jsonld($val, $result, $depth + 1);
    }
}
function ac_get_offer_details(array $products): array {
    foreach ($products as $product) {
        $offers = $product['offers'] ?? null;
        if (!is_array($offers)) continue;
        if (!array_is_list($offers)) $offers = [$offers];
        foreach ($offers as $offer) {
            if (!is_array($offer)) continue;
            $price = $offer['price'] ?? ($offer['priceSpecification']['price'] ?? ($offer['lowPrice'] ?? null));
            $currency = $offer['priceCurrency'] ?? ($offer['priceSpecification']['priceCurrency'] ?? null);
            $availability = $offer['availability'] ?? null;
            if (is_array($price) || is_array($currency) || is_array($availability)) continue;
            return ['price' => $price, 'currency' => $currency, 'availability' => $availability];
        }
    }
    return ['price' => null, 'currency' => null, 'availability' => null];
}
function ac_check(string $id, string $label, string $status, string $detail, string $action = ''): array {
    return compact('id', 'label', 'status', 'detail', 'action');
}
function ac_analyze_html(string $html, string $url, array $headers = []): array {
    $checks = [];
    $title = null;
    if (preg_match('~<title\b[^>]*>(.*?)</title\s*>~is', $html, $m)) $title = ac_trim_text($m[1]);
    $checks[] = ac_check('title', 'ページタイトル', $title ? 'ok' : 'attention', $title ? 'HTMLのtitleを検出しました。' : 'HTMLからtitleが見つかりません。', '検索・比較時に商品内容が伝わる固有のタイトルを設定してください。');
    $desc = ac_meta_content($html, 'description');
    $checks[] = ac_check('description', 'メタディスクリプション', $desc ? 'ok' : 'attention', $desc ? '説明文を検出しました。' : '説明文を検出できませんでした。', '商品や購入条件がわかる説明文の追加を検討してください。');
    $canonical = ac_find_link($html, 'canonical');
    $checks[] = ac_check('canonical', '正規URL（canonical）', $canonical ? 'ok' : 'attention', $canonical ? 'canonicalを検出しました。' : 'canonicalが見つかりません。', '商品詳細の正規URLが適切か確認してください。');
    $robotsMeta = (string)(ac_meta_content($html, 'robots') ?? '');
    $xRobots = (string)($headers['x-robots-tag'] ?? '');
    $noindex = preg_match('/(?:^|[,\s])noindex(?:[,\s]|$)/i', $robotsMeta . ',' . $xRobots) === 1;
    $checks[] = ac_check('index', 'noindex指定', $noindex ? 'attention' : 'ok', $noindex ? 'noindexを検出しました。検索での露出を意図しているか確認してください。' : '検査できたHTMLとHTTPヘッダーにはnoindexが見つかりません。', '意図せずnoindexになっていないか確認してください。');
    $products = ac_find_product_nodes($html);
    $hasProduct = count($products) > 0;
    $checks[] = ac_check('product', 'Product構造化データ', $hasProduct ? 'ok' : 'attention', $hasProduct ? 'HTML内のJSON-LDからProductを検出しました。' : '取得したHTMLのJSON-LDからProductを検出できませんでした。', '商品詳細ページにProductのJSON-LDを出力できるか確認してください。JavaScriptで後から追加される場合、本診断では検出できません。');
    $offer = ac_get_offer_details($products);
    $hasPrice = $hasProduct && $offer['price'] !== null && $offer['price'] !== '';
    $hasCurrency = $hasProduct && is_string($offer['currency']) && preg_match('/^[A-Z]{3}$/', $offer['currency']) === 1;
    $hasAvailability = $hasProduct && is_string($offer['availability']) && $offer['availability'] !== '';
    $checks[] = ac_check('price', '販売価格の構造化データ', $hasPrice ? 'ok' : ($hasProduct ? 'attention' : 'unknown'), $hasPrice ? 'Offerから価格情報を検出しました。' : '価格情報を検出できませんでした。', 'Productのoffersに販売価格を含めてください。');
    $checks[] = ac_check('currency', '通貨の構造化データ', $hasCurrency ? 'ok' : ($hasProduct ? 'attention' : 'unknown'), $hasCurrency ? 'ISO 4217形式の通貨コードを検出しました。' : '通貨コードを検出できませんでした。', 'OfferにpriceCurrency（例：JPY）を設定してください。');
    $checks[] = ac_check('stock', '在庫の構造化データ', $hasAvailability ? 'ok' : ($hasProduct ? 'attention' : 'unknown'), $hasAvailability ? 'Offerから在庫状態を検出しました。' : '在庫状態を検出できませんでした。', 'Offerのavailabilityと実際の在庫表示を一致させてください。');
    return ['checks' => $checks, 'pageTitle' => $title, 'productDetected' => $hasProduct, 'productCount' => count($products), 'canonical' => $canonical];
}
function ac_robots_check(?array $response): array {
    if ($response === null) return ac_check('robots', 'robots.txtの取得', 'unknown', 'robots.txtの取得結果を確認できませんでした。', 'robots.txtの記述とOAI-SearchBotのポリシーを確認してください。');
    $status = $response['status'];
    if ($status === 200) return ac_check('robots', 'robots.txtの取得', 'ok', 'robots.txtを取得できました。内容がAIクローラーを許可しているかは別途確認が必要です。', 'OAI-SearchBotの制御とChatGPTのブラウザアクセスは別物です。');
    if ($status === 404) return ac_check('robots', 'robots.txtの取得', 'unknown', 'robots.txtが見つかりませんでした。ファイルがないこと自体はAIによる拒否を意味しません。', 'クロール方針が必要な場合はrobots.txtを設置してください。');
    return ac_check('robots', 'robots.txtの取得', 'unknown', 'robots.txtへの応答はHTTP ' . $status . 'でした。', 'アクセス制御を確認してください。');
}
function ac_sitemap_check(?array $response): array {
    if ($response === null) return ac_check('sitemap', 'sitemap.xmlの取得', 'unknown', 'サイトマップを確認できませんでした。', 'サイトマップの場所と公開範囲を確認してください。');
    if ($response['status'] === 200 && preg_match('/<\s*(urlset|sitemapindex)\b/i', $response['html'])) return ac_check('sitemap', 'sitemap.xmlの取得', 'ok', 'ルートのXMLサイトマップを検出しました。URLの全件・正確性は未確認です。');
    return ac_check('sitemap', 'sitemap.xmlの取得', 'unknown', 'ルートにサイトマップを確認できませんでした。別URLの場合もあります。', 'robots.txtから別のSitemap URLが指定されていないか確認してください。');
}
function ac_rate_limit(string $ip): bool {
    // Only IP hashes and timestamps are stored; no scanned URLs.
    $file = sys_get_temp_dir() . '/ntrelay-ai-commerce-check-usage-v1.json';
    $oldMask = umask(0077);
    $f = @fopen($file, 'c+');
    umask($oldMask);
    if (!$f) return false;
    try {
        if (!flock($f, LOCK_EX)) return false;
        $raw = stream_get_contents($f);
        $state = json_decode($raw ?: '{}', true);
        if (!is_array($state)) $state = [];
        $now = time();
        foreach ($state as $key => $times) {
            if (!is_array($times)) { unset($state[$key]); continue; }
            $times = array_values(array_filter($times, static fn($t) => is_int($t) && $t > $now - 3600));
            if (!$times) unset($state[$key]);
            else $state[$key] = $times;
        }
        $key = hash('sha256', 'ec-v1|' . $ip);
        $global = array_sum(array_map('count', $state));
        if (count($state[$key] ?? []) >= 8 || $global >= 120) return false;
        $state[$key][] = $now;
        rewind($f);
        if (!ftruncate($f, 0)) return false;
        $n = fwrite($f, json_encode($state, JSON_UNESCAPED_SLASHES));
        fflush($f);
        return $n !== false;
    } finally {
        flock($f, LOCK_UN);
        fclose($f);
    }
}
