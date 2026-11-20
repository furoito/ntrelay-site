<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/ntrelay_metrics/collector.php';
header('Cache-Control: no-store, max-age=0');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);exit;
}
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '' && $origin !== 'https://ntrelay.com') {
    http_response_code(403);exit;
}
$raw = file_get_contents('php://input', false, null, 0, 1024);
$json = json_decode($raw ?: '', true);
if (!is_array($json)) {
    http_response_code(400);exit;
}
$cid = filter_var($json['c'] ?? null, FILTER_VALIDATE_INT);
$sig = is_string($json['s'] ?? null) ? $json['s'] : '';
$event = is_string($json['event'] ?? null) ? $json['event'] : '';
if (!is_int($cid) || !ntr_valid($cid,$sig) ||
    !in_array($event, ['page_visible', 'demo_interaction'],true)) {
    http_response_code(404);exit;
}
ntr_record($cid, $event);
http_response_code(204);
exit;
