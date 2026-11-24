<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$response = Http::get('https://delhi.wordcamp.org/2026/wp-json/wp/v2/sponsors');
$sponsors = $response->json();

echo "Raw sponsors fetched from Delhi: " . count($sponsors) . "\n";
foreach (array_slice($sponsors, 0, 3) as $sp) {
    echo "ID: " . $sp['id'] . " | Title: " . $sp['title']['rendered'] . "\n";
    echo "Featured Media: " . ($sp['featured_media'] ?? 'NONE') . "\n";
    echo "_embedded: " . (isset($sp['_embedded']) ? 'YES' : 'NO') . "\n";
    echo "Content: " . substr(strip_tags($sp['content']['rendered'] ?? ''), 0, 80) . "\n";
    // Check if there are images in content
    preg_match_all('/<img[^>]+src=[\'"]([^\'"]+)[\'"]/i', $sp['content']['rendered'] ?? '', $matches);
    echo "Images in content: " . json_encode($matches[1] ?? []) . "\n";
    echo "----------------------------------------\n";
}
