<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

$urls = [
    'https://nairobi.wordcamp.org/2026/program/',
    'https://nairobi.wordcamp.org/2026/schedule/',
    'https://nairobi.wordcamp.org/2026/speakers/',
];

foreach ($urls as $url) {
    $res = Http::get($url);
    echo "URL: {$url} -> Status: " . $res->status() . "\n";
    if ($res->ok()) {
        $crawler = new Crawler($res->body());
        echo "  Title: " . $crawler->filter('title')->text('') . "\n";
        echo "  H2/H3 count: " . $crawler->filter('h2, h3')->count() . "\n";
        $crawler->filter('h2, h3')->slice(0, 5)->each(function (Crawler $h) {
            echo "    H: " . trim($h->text('')) . "\n";
        });
    }
}
