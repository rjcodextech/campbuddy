<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Event;
use App\Support\EventData;
use App\Support\EventCountry;

$events = Event::where('status', 'active')->where('is_visible', true)->get();
echo "Active & Visible Events: " . $events->count() . "\n";
foreach ($events as $e) {
    $country = EventCountry::code($e);
    $sessions = EventData::get($e->id, 'sessions') ?? [];
    $speakers = EventData::get($e->id, 'speakers') ?? [];
    $sponsors = EventData::get($e->id, 'sponsors') ?? [];
    $organizers = EventData::get($e->id, 'organizers') ?? [];
    $quests = $e->quests()->count();
    $offers = $e->offers()->count();
    echo "ID {$e->id}: {$e->display_name} [{$e->slug}] (Country: {$country})\n";
    echo "   Starts: {$e->starts_on}, Ends: {$e->ends_on}, Venue: {$e->venue}\n";
    echo "   Sessions: " . count($sessions) . ", Speakers: " . count($speakers) . ", Sponsors: " . count($sponsors) . ", Organizers: " . count($organizers) . ", Quests: {$quests}, Deals: {$offers}\n";
    if (count($sessions) > 0) {
        $firstSession = $sessions[0];
        echo "   Sample Session: " . ($firstSession['title'] ?? 'N/A') . " (Track: " . ($firstSession['track_names'][0] ?? 'Main') . ")\n";
    }
}
