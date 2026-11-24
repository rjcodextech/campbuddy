<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

$html = Http::get('https://delhi.wordcamp.org/2026/sponsors/')->body();
$crawler = new Crawler($html);

$map = [];
$crawler->filter('img')->each(function (Crawler $node) use (&$map) {
    $src = $node->attr('data-orig-file') ?: $node->attr('src');
    if (! $src) return;
    
    // Ignore site logos and icons
    if (str_contains($src, 'Logo-1') || str_contains($src, 'Light-Logo') || str_contains($src, 'cropped-')) return;
    
    $parentLink = $node->closest('a');
    $href = $parentLink ? $parentLink->attr('href') : null;
    $alt = trim($node->attr('alt') ?? '');
    
    // Extract slug from href if it points to /sponsor/<slug>/
    if ($href && preg_match('#/sponsor/([^/]+)/?#i', $href, $m)) {
        $slug = $m[1];
        $map[$slug] = $src;
    }
    
    // Also map by name if alt contains " logo"
    if ($alt) {
        $cleanName = strtolower(trim(preg_replace('/\s+logo$/i', '', $alt)));
        $map[$cleanName] = $src;
    }
});

echo "Total sponsor logos mapped from page: " . count($map) . "\n";
print_r($map);
