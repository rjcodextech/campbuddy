<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Event;
use App\Support\EventData;

$bandung = Event::where('slug', 'wordcamp-bandung-2026')->first();
$speakers = EventData::get($bandung->id, 'speakers') ?? [];
echo "Bandung speakers count: " . count($speakers) . "\n";
foreach (array_slice($speakers, 0, 5) as $s) {
    echo "ID: {$s['id']} | Name: {$s['name']} | Avatar: " . ($s['avatar_url'] ?? 'NONE') . "\n";
}
