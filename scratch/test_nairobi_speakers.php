<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

$res = Http::get('https://nairobi.wordcamp.org/2026/speakers/');
$crawler = new Crawler($res->body());

$speakers = [];
$crawler->filter('h2, h3')->each(function (Crawler $title) use (&$speakers) {
    $name = trim($title->text(''));
    if ($name && !in_array($name, ['Conference Tracks', 'Two Distinct Days', 'Share this:', 'Get Involved: Lets Work Together', 'Speakers', 'Search'], true)) {
        $parent = $title->closest('div');
        $img = $parent ? $parent->filter('img') : null;
        $photo = ($img && $img->count() > 0) ? ($img->attr('data-orig-file') ?: $img->attr('src')) : null;
        $speakers[] = [
            'name' => $name,
            'avatar_url' => $photo,
        ];
    }
});

echo "Extracted Nairobi speakers: " . count($speakers) . "\n";
foreach (array_slice($speakers, 0, 8) as $s) {
    echo "  Name: {$s['name']} | Avatar: " . ($s['avatar_url'] ?? 'NONE') . "\n";
}
