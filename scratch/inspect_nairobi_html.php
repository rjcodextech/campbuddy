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
    if (str_contains($title->text(''), 'Patrick')) {
        $parent = $title->closest('.wp-block-columns, .wp-block-group, section, article, div');
        echo "HTML around Patrick:\n" . substr($parent->html(), 0, 600) . "\n";
    }
});
