<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Event;
use App\Support\EventData;

$delhi = Event::where('slug', 'wordcamp-delhi-2026')->first();
$speakers = EventData::get($delhi->id, 'speakers') ?? [];
$sponsors = EventData::get($delhi->id, 'sponsors') ?? [];
$sessions = EventData::get($delhi->id, 'sessions') ?? [];

echo "=== Delhi Speakers (" . count($speakers) . ") ===\n";
foreach (array_slice($speakers, 0, 5) as $s) {
    echo "ID: " . ($s['id'] ?? '') . " | Name: " . ($s['name'] ?? '') . " | Avatar: " . ($s['avatar_url'] ?? $s['photo_url'] ?? 'NONE') . " | Gravatar: " . ($s['gravatar_hash'] ?? 'NONE') . "\n";
}

echo "\n=== Delhi Sponsors (" . count($sponsors) . ") ===\n";
foreach (array_slice($sponsors, 0, 5) as $sp) {
    echo "ID: " . ($sp['id'] ?? '') . " | Name: " . ($sp['name'] ?? '') . " | Logo: " . ($sp['logo_url'] ?? 'NONE') . " | Tier: " . json_encode($sp['tier_names'] ?? []) . "\n";
}

echo "\n=== Delhi Sessions (" . count($sessions) . ") ===\n";
foreach (array_slice($sessions, 0, 5) as $se) {
    echo "ID: " . ($se['id'] ?? '') . " | Title: " . ($se['title'] ?? '') . " | Track: " . ($se['track'] ?? 'NONE') . " | Speakers: " . json_encode($se['speaker_ids'] ?? []) . "\n";
}
