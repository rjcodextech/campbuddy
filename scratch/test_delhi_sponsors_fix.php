<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Http;

$c = new \App\Services\WordCampRestClient('https://delhi.wordcamp.org/2026');
$sponsors = $c->fetchSponsors();

$res = Http::get('https://delhi.wordcamp.org/2026/sponsors/');
$crawler = new Crawler($res->body());
$pageMap = [];
$crawler->filter('img')->each(function (Crawler $node) use (&$pageMap) {
    $src = $node->attr('data-orig-file') ?: $node->attr('src');
    if (! $src) return;
    if (str_contains($src, 'Logo-1') || str_contains($src, 'Light-Logo') || str_contains($src, 'cropped-')) return;
    $parentLink = $node->closest('a');
    $href = $parentLink ? $parentLink->attr('href') : null;
    $alt = trim($node->attr('alt') ?? '');
    if ($href && preg_match('#/sponsor/([^/]+)/?#i', $href, $m)) {
        $slug = strtolower($m[1]);
        $pageMap[$slug] = $src;
        $pageMap[preg_replace('/[^a-z0-9]/', '', $slug)] = $src;
    }
    if ($alt) {
        $clean = strtolower(trim(preg_replace('/\s+logo$/i', '', $alt)));
        $pageMap[$clean] = $src;
        $pageMap[preg_replace('/[^a-z0-9]/', '', $clean)] = $src;
    }
});

foreach ($sponsors as $s) {
    $slug = strtolower($s['slug'] ?? '');
    $name = strtolower(trim($s['title']['rendered'] ?? ''));
    $logo = $pageMap[$slug] 
        ?? $pageMap[preg_replace('/[^a-z0-9]/', '', $slug)]
        ?? $pageMap[$name]
        ?? $pageMap[preg_replace('/[^a-z0-9]/', '', $name)]
        ?? 'STILL NULL';
    echo ($s['title']['rendered'] ?? '') . " -> $logo\n";
}
