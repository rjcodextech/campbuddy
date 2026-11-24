<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

$html = Http::get('https://delhi.wordcamp.org/2026/sponsors/')->body();
$crawler = new Crawler($html);

$crawler->filter('div[class*="sponsor"], article[class*="sponsor"], .wp-block-group')->each(function (Crawler $node) {
    $img = $node->filter('img');
    $link = $node->filter('a');
    $title = $node->filter('h2, h3, h4, .sponsor-title');
    if ($img->count() > 0 && ($title->count() > 0 || $link->count() > 0)) {
        $t = $title->count() > 0 ? $title->text() : $img->attr('alt');
        $src = $img->attr('data-orig-file') ?: $img->attr('src');
        $href = $link->count() > 0 ? $link->attr('href') : '';
        if ($t && $src) {
            echo "Title: {$t} | Link: {$href} | Img: {$src}\n";
        }
    }
});
