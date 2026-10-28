<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Event;
use App\Support\EventData;
use App\Support\EventCountry;

$events = Event::where('status', 'active')->where('is_visible', true)->get();
$spotlights = [];

foreach ($events as $e) {
    $country = EventCountry::code($e) ?: 'ALL';
    $sessions = EventData::get($e->id, 'sessions') ?? [];
    $speakers = EventData::get($e->id, 'speakers') ?? [];
    $sponsors = EventData::get($e->id, 'sponsors') ?? [];
    $organizers = EventData::get($e->id, 'organizers') ?? [];

    // 1. Meaningful sessions
    foreach ($sessions as $s) {
        $title = trim($s['title'] ?? '');
        if (strlen($title) > 10 && !preg_match('/(lunch|coffee|tea|registration|break|closing)/i', $title)) {
            $speakerName = null;
            if (!empty($s['speaker_ids']) && !empty($speakers)) {
                $sp = collect($speakers)->firstWhere('id', $s['speaker_ids'][0]);
                $speakerName = $sp['name'] ?? null;
            }
            $spotlights[] = [
                'event_id' => $e->id,
                'event_name' => $e->display_name,
                'event_slug' => $e->slug,
                'country' => $country,
                'type' => 'session',
                'badge' => 'Featured Session',
                'title' => $title,
                'subtitle' => $speakerName ? "Speaker: {$speakerName}" : 'Live on schedule',
                'meta' => ($s['track_names'][0] ?? 'Main Track') . ($s['starts_at'] ? ' · ' . substr($s['starts_at'], 11, 5) : ''),
                'icon' => 'calendar',
            ];
            if (count($spotlights) >= 20) break;
        }
    }

    // 2. Speakers
    foreach (array_slice($speakers, 0, 3) as $sp) {
        $spotlights[] = [
            'event_id' => $e->id,
            'event_name' => $e->display_name,
            'event_slug' => $e->slug,
            'country' => $country,
            'type' => 'speaker',
            'badge' => 'Speaker Spotlight',
            'title' => $sp['name'],
            'subtitle' => $e->display_name,
            'meta' => 'Sharing knowledge with the WordPress community',
            'icon' => 'users',
        ];
    }

    // 3. Organizers / Volunteers
    foreach (array_slice($organizers, 0, 2) as $org) {
        $spotlights[] = [
            'event_id' => $e->id,
            'event_name' => $e->display_name,
            'event_slug' => $e->slug,
            'country' => $country,
            'type' => 'organizer',
            'badge' => 'Volunteer & Organizer Team',
            'title' => $org['name'] ?? 'Local Organizing Team',
            'subtitle' => 'Building the community at ' . $e->display_name,
            'meta' => 'Volunteer driven · Community powered',
            'icon' => 'heart',
        ];
    }

    // 4. Sponsors / Micro-sponsors
    foreach (array_slice($sponsors, 0, 2) as $spons) {
        $spotlights[] = [
            'event_id' => $e->id,
            'event_name' => $e->display_name,
            'event_slug' => $e->slug,
            'country' => $country,
            'type' => 'sponsor',
            'badge' => 'Community Sponsor',
            'title' => $spons['name'] ?? 'Supporting Sponsor',
            'subtitle' => 'Supporting ' . $e->display_name,
            'meta' => 'WordPress Community Supporter',
            'icon' => 'sparkles',
        ];
    }
}

echo "Total spotlights generated: " . count($spotlights) . "\n";
echo "Sample 3 spotlights:\n";
print_r(array_slice($spotlights, 0, 3));
