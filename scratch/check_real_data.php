<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Event;

$events = Event::all();
echo "Total Events in DB: " . $events->count() . PHP_EOL;

foreach ($events as $e) {
    $sessions = is_array($e->sessions) ? count($e->sessions) : 0;
    $speakers = is_array($e->speakers) ? count($e->speakers) : 0;
    $sponsors = is_array($e->sponsors) ? count($e->sponsors) : 0;
    $organizers = is_array($e->organizers) ? count($e->organizers) : 0;
    $hasLogo = !empty($e->logo);
    $hasVenue = !empty($e->venue);
    $startsOn = $e->starts_on ? $e->starts_on->toDateString() : 'N/A';
    
    echo sprintf(
        "[%d] %-30s | Status: %-8s | Starts: %-10s | Sessions: %3d | Speakers: %3d | Sponsors: %3d | Orgs: %3d | Logo: %3s | Venue: %s\n",
        $e->id,
        substr($e->display_name ?? $e->slug, 0, 30),
        $e->status,
        $startsOn,
        $sessions,
        $speakers,
        $sponsors,
        $organizers,
        $hasLogo ? 'YES' : 'NO',
        $hasVenue ? substr($e->venue, 0, 25) : 'NONE'
    );
}
