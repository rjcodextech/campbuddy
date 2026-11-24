<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

$html = Http::get('https://delhi.wordcamp.org/2026/sponsors/')->body();
$crawler = new Crawler($html);

echo "Sponsors Page Title: " . $crawler->filter('title')->text('') . "\n";
$images = $crawler->filter('.wcb-sponsor-grid img, .sponsors-list img, article.sponsor img, .wp-block-post-featured-image img, .entry-content img')->each(function (Crawler $node) {
    return [
        'src' => $node->attr('src'),
        'data-src' => $node->attr('data-src'),
        'data-orig-file' => $node->attr('data-orig-file'),
        'alt' => $node->attr('alt'),
    ];
});

echo "Found images: " . count($images) . "\n";
foreach (array_slice($images, 0, 8) as $img) {
    echo "  Alt: " . $img['alt'] . " | Src: " . ($img['data-orig-file'] ?? $img['src'] ?? '') . "\n";
}
