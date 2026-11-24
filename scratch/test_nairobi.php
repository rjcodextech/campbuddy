<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

$res = Http::get('https://nairobi.wordcamp.org/2026/speakers/');
echo "HTTP Status: " . $res->status() . "\n";
$html = $res->body();
$crawler = new Crawler($html);
echo "Page title: " . $crawler->filter('title')->text('') . "\n";

$crawler->filter('div.kanairo-spk-card')->each(function (Crawler $node) {
    $name = trim($node->filter('.kanairo-spk-name, h3, h4')->text(''));
    $title = trim($node->filter('.kanairo-spk-title, p')->text(''));
    $img = $node->filter('img')->attr('data-orig-file') ?: $node->filter('img')->attr('src');
    echo "Speaker: $name ($title) -> $img\n";
});
