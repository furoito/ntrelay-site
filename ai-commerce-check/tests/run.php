<?php
declare(strict_types=1);
require __DIR__ . '/../lib.php';
$count=0;
function check(bool $ok,string $label): void {
    global $count;
    $count++;
    if (!$ok) { fwrite(STDERR,"FAILED: ".$label."\n"); exit(1); }
    echo "PASS ".$label."\n";
}
function rejects(string $url): bool {
    try { ac_normalize_url($url); return false; }
    catch (InvalidArgumentException $e) { return true; }
}
check(ac_normalize_url('shop.example.jp/item#part') === 'https://shop.example.jp/item','URL normalized');
check(ac_normalize_url('http://shop.example.jp/item') === 'https://shop.example.jp/item','HTTP upgrade');
foreach (['localhost','127.0.0.1','ftp://shop.example.jp/','https://user:pass@shop.example.jp/','https://shop.example.jp:8080/','https://metadata.internal/latest',"https://shop.example.jp/a\nfoo"] as $invalid) {
    check(rejects($invalid),'Unsafe URL rejected');
}
foreach (['127.0.0.1','10.0.0.1','169.254.169.254','192.168.1.2','172.16.0.1','100.64.0.1','192.0.2.1','198.51.100.1','203.0.113.1','::1','fc00::1','fe80::1','::ffff:127.0.0.1'] as $ip) {
    check(!ac_ip_is_public($ip),'Private/reserved IP rejected');
}
check(ac_ip_is_public('1.1.1.1'),'Public IPv4 accepted');
check(ac_ip_is_public('2606:4700:4700::1111'),'Public IPv6 accepted');

$html = <<<'HTML'
<html><head>
<title>Demo Product</title><meta content="Example product" name="description">
<link rel="canonical" href="https://shop.example.jp/item">
<script type="application/ld+json">
{"@context":"https://schema.org","@graph":[{"@type":"BreadcrumbList"},{"@type":"Product","name":"Demo","offers":{"@type":"Offer","price":1200,"priceCurrency":"JPY","availability":"https://schema.org/InStock"}}]}
</script>
</head><body>Example</body></html>
HTML;
$a=ac_analyze_html($html,'https://shop.example.jp/item');
$statuses=array_column($a['checks'],'status','id');
check($a['productDetected'],'Product JSON-LD detected');
check($statuses['price']==='ok' && $statuses['currency']==='ok' && $statuses['stock']==='ok','Offer details detected');
check($statuses['canonical']==='ok' && $statuses['description']==='ok','Meta and canonical detected');
$b=ac_analyze_html('<html><head><meta name="robots" content="noindex"></head></html>','https://shop.example.jp/item');
$other=array_column($b['checks'],'status','id');
check($other['product']==='attention' && $other['price']==='unknown','Missing structured data is not certified failure');
check($other['index']==='attention','Noindex detected');
check(ac_robots_check(['status'=>404])['status']==='unknown','Missing robots file is not bot blockage');
check(ac_sitemap_check(['status'=>200,'html'=>'<urlset></urlset>'])['status']==='ok','Sitemap detected');
echo "ALL ".$count." TESTS PASSED\n";
