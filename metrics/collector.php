<?php
declare(strict_types=1);
/**
 * NT Relay outreach attribution collector, private library.
 * No cookies, analytics SDK, raw IP, user agent, or full URL stored.
 * c is an opaque internal company prospect number signed with a secret.
 */
const NTR_METRICS_DIR = '/home/c8035322/ntrelay_metrics';
const NTR_METRICS_EXPIRY_DAYS = 90;
function ntr_valid(int $cid, string $sig): bool {
    if ($cid < 1 || $cid > 9999999 || !preg_match('/\A[a-f0-9]{24}\z/D', $sig)) {
        return false;
    }
    $key = @file_get_contents(NTR_METRICS_DIR . '/secret');
    if ($key === false || strlen(trim($key)) < 32) {
        return false;
    }
    $expected = substr(hash_hmac('sha256', (string)$cid, trim($key)), 0, 24);
    return hash_equals($expected, $sig);
}
function ntr_record(int $cid, string $type): void {
    if (!in_array($type, ['link_request', 'page_visible', 'demo_interaction'], true)) {
        return;
    }
    $dir = NTR_METRICS_DIR . '/events';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        error_log('NT Relay metrics directory unavailable');
        return;
    }
    $path = $dir . '/' . gmdate('Y-m-d') . '.jsonl';
    $row = json_encode([
        'at_utc' => gmdate('c'),
        'company_id' => $cid,
        'type' => $type
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    $fp = @fopen($path, 'ab');
    if ($fp === false) {
        error_log('NT Relay metrics file unavailable');
        return;
    }
    if (flock($fp, LOCK_EX)) {
        fwrite($fp, $row);
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    // Low frequency cleaning to enforce 90-day data retention.
    if (random_int(1, 100) === 1) {
        $olderThan = gmdate('Y-m-d', time() - NTR_METRICS_EXPIRY_DAYS * 86400);
        foreach (glob($dir . '/????-??-??.jsonl') ?: [] as $f) {
            if (basename($f) < $olderThan . '.jsonl') {
                @unlink($f);
            }
        }
    }
}
