<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/ntrelay_metrics/collector.php';
header('Cache-Control: no-store, max-age=0');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Robots-Tag: noindex, nofollow');
$cid = filter_input(INPUT_GET, 'c', FILTER_VALIDATE_INT);
$sig = (string)($_GET['s'] ?? '');
if (!is_int($cid) || !ntr_valid($cid, $sig)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not found";
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    ntr_record($cid, 'link_request');
} elseif ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    http_response_code(405);
    exit;
}
$url = '/?utm_source=outreach_email&utm_medium=email&utm_campaign=ntrelay&cid=' . $cid . '&sig=' . $sig . '#demo';
header('Location: ' . $url, true, 302);
exit;
