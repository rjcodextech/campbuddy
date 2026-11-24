<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

$res = Http::get('https://nairobi.wordcamp.org/2026/speakers/');
$crawler = new Crawler($res->body());

$crawler->filter('h2, h3')->each(function (Crawler $title) {
    if (str_contains($title->text(''), 'Patrick Lumumba')) {
        $parent = $title->closest('.wp-block-columns');
        if ($parent->count() > 0) {
            echo "Columns HTML:\n" . substr($parent->html(), 0, 800) . "\n";
            $imgs = $parent->filter('img');
            echo "Imgs in columns: " . $imgs->count() . "\n";
            if ($imgs->count() > 0) {
                echo "Img src: " . ($imgs->attr('data-orig-file') ?: $imgs->attr('src')) . "\n";
            }
        }
    }
});
