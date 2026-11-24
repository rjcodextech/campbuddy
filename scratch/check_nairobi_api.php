<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$res = Http::get('https://nairobi.wordcamp.org/2026/wp-json/wp/v2/speakers');
echo "Status: " . $res->status() . "\n";
$data = $res->json();
echo "Count: " . (is_array($data) ? count($data) : 0) . "\n";
echo "Response sample: " . substr(json_encode($data), 0, 300) . "\n";
