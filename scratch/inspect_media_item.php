<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$res = Http::get('https://delhi.wordcamp.org/2026/wp-json/wp/v2/media/3503');
echo "Status: " . $res->status() . "\n";
$data = $res->json();
echo "Data keys: " . json_encode(array_keys($data ?? [])) . "\n";
if (isset($data['source_url'])) {
    echo "source_url: " . $data['source_url'] . "\n";
}
if (isset($data['guid'])) {
    echo "guid: " . json_encode($data['guid']) . "\n";
}
if (isset($data['media_details'])) {
    echo "media_details sizes: " . json_encode(array_keys($data['media_details']['sizes'] ?? [])) . "\n";
}
