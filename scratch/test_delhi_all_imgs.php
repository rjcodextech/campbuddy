<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Http;

$res = Http::get('https://delhi.wordcamp.org/2026/sponsors/');
$crawler = new Crawler($res->body());
$crawler->filter('img')->each(function (Crawler $node) {
    $src = $node->attr('data-orig-file') ?: $node->attr('src');
    $parentLink = $node->closest('a');
    $href = $parentLink ? $parentLink->attr('href') : 'NO_LINK';
    $alt = $node->attr('alt') ?? 'NO_ALT';
    echo "href: $href | alt: $alt | src: $src\n";
});
