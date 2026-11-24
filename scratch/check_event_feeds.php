<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Event;
use App\Support\EventData;
use Illuminate\Support\Facades\DB;

$rows = DB::table('event_feeds')->select('event_id', 'kind', DB::raw('LENGTH(payload) as len'), 'fetched_at')->get();
echo "Total rows in event_feeds: " . $rows->count() . PHP_EOL;

foreach ($rows as $r) {
    echo "Event ID: {$r->event_id} | Kind: {$r->kind} | Payload Bytes: {$r->len} | Fetched: {$r->fetched_at}\n";
}

$events = Event::where('status', 'active')->get();
echo PHP_EOL . "--- Checking EventData::get for Active Events ---" . PHP_EOL;
foreach ($events as $e) {
    $sessions = EventData::get($e->id, 'sessions') ?? [];
    $speakers = EventData::get($e->id, 'speakers') ?? [];
    $sponsors = EventData::get($e->id, 'sponsors') ?? [];
    $organizers = EventData::get($e->id, 'organizers') ?? [];
    $rosterCount = $e->attendeeRoster()->count();
    $logoUrl = $e->logoUrl();
    
    echo sprintf(
        "[%2d] %-28s | Sess: %2d | Spk: %2d | Spon: %2d | Org: %2d | Rost: %3d | Logo: %s\n",
        $e->id,
        $e->slug,
        count($sessions),
        count($speakers),
        count($sponsors),
        count($organizers),
        $rosterCount,
        $logoUrl ? 'YES' : 'NO'
    );
}
