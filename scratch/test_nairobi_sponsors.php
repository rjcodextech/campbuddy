<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

$res = Http::get('https://nairobi.wordcamp.org/2026/sponsors/');
echo "HTTP Status: " . $res->status() . "\n";
if ($res->successful()) {
    $crawler = new Crawler($res->body());
    echo "Title: " . $crawler->filter('title')->text('') . "\n";
    $crawler->filter('img')->each(function (Crawler $node) {
        $src = $node->attr('data-orig-file') ?: $node->attr('src');
        $alt = $node->attr('alt');
        if ($src && !str_contains($src, 'cropped-') && !str_contains($src, 'Logo-1')) {
            echo "Img: $alt -> $src\n";
        }
    });
}
