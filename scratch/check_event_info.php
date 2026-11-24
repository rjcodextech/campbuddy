<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Event;

$events = Event::whereIn('id', [46, 44, 36, 37, 45, 43, 38, 39, 40, 41])->get();

foreach ($events as $e) {
    echo "=== [{$e->id}] {$e->slug} ===\n";
    echo "Info: " . json_encode($e->info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}
