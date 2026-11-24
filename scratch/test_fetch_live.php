<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Event;
use App\Services\WordCampRestClient;

$events = Event::whereIn('id', [46, 45, 44, 37, 36])->get();

foreach ($events as $e) {
    echo "=== Fetching from {$e->slug} ({$e->source_site_url}) ===\n";
    $client = new WordCampRestClient($e->source_site_url);
    $sessions = $client->fetchSessions();
    $speakers = $client->fetchSpeakers();
    $sponsors = $client->fetchSponsors();
    $organizers = $client->fetchOrganizers();
    
    echo sprintf(
        "Sess: %d | Spk: %d | Spon: %d | Org: %d\n",
        count($sessions),
        count($speakers),
        count($sponsors),
        count($organizers)
    );
    
    if (count($sessions) > 0) {
        $first = $sessions[0];
        $title = is_array($first['title']) ? ($first['title']['rendered'] ?? '') : ($first['title'] ?? '');
        echo "  First session: " . html_entity_decode($title) . "\n";
    }
    if (count($speakers) > 0) {
        $first = $speakers[0];
        $title = is_array($first['title']) ? ($first['title']['rendered'] ?? '') : ($first['title'] ?? '');
        echo "  First speaker: " . html_entity_decode($title) . "\n";
    }
}
