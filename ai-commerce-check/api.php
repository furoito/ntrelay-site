<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

function ac_json_response(int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    ac_json_response(405, ['error' => 'URLの入力フォームから診断してください。']);
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && !in_array($origin, ['https://ntrelay.com', 'https://www.ntrelay.com'], true)) {
    ac_json_response(403, ['error' => 'このサイトのフォームから利用してください。']);
}
$size = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($size > 4096) ac_json_response(413, ['error' => '入力データが大きすぎます。']);
if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
    ac_json_response(415, ['error' => '対応していないリクエスト形式です。']);
}
$request = json_decode((string)file_get_contents('php://input', false, null, 0, 4097), true);
if (!is_array($request) || !isset($request['url']) || !is_string($request['url'])) {
    ac_json_response(400, ['error' => '商品詳細ページのURLを入力してください。']);
}
try {
    $url = ac_normalize_url($request['url']);
} catch (InvalidArgumentException $e) {
    ac_json_response(422, ['error' => $e->getMessage()]);
}
if (!function_exists('curl_init')) {
    ac_json_response(503, ['error' => 'サーバー側の取得機能が未設定です。運営者にお問い合わせください。']);
}
if (!ac_rate_limit($_SERVER['REMOTE_ADDR'] ?? 'unknown')) {
    header('Retry-After: 3600');
    ac_json_response(429, ['error' => '診断の利用上限に達しました。時間を空けてお試しください。']);
}
try {
    $page = ac_fetch_https($url);
    if ($page['status'] !== 200) {
        ac_json_response(422, ['error' => 'ページがHTTP ' . $page['status'] . 'を返しました。公開されている商品ページURLを指定してください。']);
    }
    $ctype = strtolower((string)($page['headers']['content-type'] ?? ''));
    if ($ctype !== '' && !str_contains($ctype, 'text/html') && !str_contains($ctype, 'application/xhtml+xml')) {
        ac_json_response(422, ['error' => 'HTMLページではないため診断できません。商品詳細ページを指定してください。']);
    }
    $result = ac_analyze_html($page['html'], $page['url'], $page['headers']);
    $host = (string)parse_url($page['url'], PHP_URL_HOST);
    $robots = null;
    $sitemap = null;
    try { $robots = ac_fetch_https('https://' . $host . '/robots.txt', 150000, 1); } catch (Throwable $e) { /* unknown */ }
    try { $sitemap = ac_fetch_https('https://' . $host . '/sitemap.xml', 150000, 1); } catch (Throwable $e) { /* unknown */ }
    $result['checks'][] = ac_robots_check($robots);
    $result['checks'][] = ac_sitemap_check($sitemap);
    $counts = ['ok' => 0, 'attention' => 0, 'unknown' => 0];
    foreach ($result['checks'] as $check) $counts[$check['status']]++;
    ac_json_response(200, [
        'url' => $page['url'],
        'checkedAt' => gmdate('Y-m-d\TH:i:s\Z'),
        'status' => $page['status'],
        'counts' => $counts,
        'checks' => $result['checks'],
        'pageTitle' => $result['pageTitle'],
        'productDetected' => $result['productDetected'],
        'limitations' => [
            '本診断はHTMLと公開ファイルの静的な一次確認です。JavaScript実行後の内容は検査できません。',
            '商品フィードのOpenAI登録状況、署名付きクラウドブラウザのアクセス、カート操作・決済は検査していません。',
            'ChatGPTでの推薦や掲載順位、Wallet対応認定、売上成果を保証するものではありません。'
        ]
    ]);
} catch (Throwable $e) {
    ac_json_response(422, ['error' => 'ページの取得または解析に失敗しました。URL・公開状態・Bot対策を確認してください。']);
}
