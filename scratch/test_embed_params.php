<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$test1 = Http::get('https://delhi.wordcamp.org/2026/wp-json/wp/v2/sponsors?_embed=wp:featuredmedia')->json();
echo "Test 1 (_embed=wp:featuredmedia) has _embedded: " . (isset($test1[0]['_embedded']) ? 'YES' : 'NO') . "\n";
if (isset($test1[0]['_embedded'])) {
    echo "  Embedded keys: " . json_encode(array_keys($test1[0]['_embedded'])) . "\n";
}

$test2 = Http::get('https://delhi.wordcamp.org/2026/wp-json/wp/v2/sponsors?_embed=1')->json();
echo "Test 2 (_embed=1) has _embedded: " . (isset($test2[0]['_embedded']) ? 'YES' : 'NO') . "\n";
if (isset($test2[0]['_embedded'])) {
    echo "  Embedded keys: " . json_encode(array_keys($test2[0]['_embedded'])) . "\n";
    if (isset($test2[0]['_embedded']['wp:featuredmedia'])) {
        $fm = $test2[0]['_embedded']['wp:featuredmedia'][0] ?? [];
        echo "  Featured media source_url: " . ($fm['source_url'] ?? 'NONE') . "\n";
    }
}

$test3 = Http::get('https://delhi.wordcamp.org/2026/wp-json/wp/v2/media/3503')->json();
echo "Test 3 (direct media/3503): " . ($test3['source_url'] ?? 'NONE') . "\n";
