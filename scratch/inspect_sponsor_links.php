<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$sponsors = Http::get('https://delhi.wordcamp.org/2026/wp-json/wp/v2/sponsors')->json();
$first = $sponsors[0] ?? [];

echo "First sponsor: " . ($first['title']['rendered'] ?? '') . "\n";
echo "Links: " . json_encode($first['_links'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
