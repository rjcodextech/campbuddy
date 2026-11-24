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
        $node = $title->getNode(0);
        $curr = $node->parentNode;
        while ($curr) {
            $class = $curr->getAttribute('class') ?? '';
            $tag = $curr->nodeName;
            echo "Parent: <{$tag} class=\"{$class}\">\n";
            // Check for img inside
            $subCrawler = new Crawler($curr);
            $imgs = $subCrawler->filter('img');
            if ($imgs->count() > 0) {
                echo "  Found " . $imgs->count() . " img(s) in this ancestor! First src: " . ($imgs->attr('data-orig-file') ?: $imgs->attr('src')) . "\n";
                break;
            }
            $curr = $curr->parentNode;
        }
    }
});
