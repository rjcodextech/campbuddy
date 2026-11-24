<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

$html = Http::get('https://delhi.wordcamp.org/2026/sponsor/elementor-ltd/')->body();
$crawler = new Crawler($html);

echo "Title: " . $crawler->filter('title')->text('') . "\n";
$crawler->filter('img')->each(function (Crawler $img) {
    echo "Img Alt: " . $img->attr('alt') . " | Src: " . ($img->attr('data-orig-file') ?: $img->attr('src')) . "\n";
});
