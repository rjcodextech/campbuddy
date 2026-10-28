<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class HeroSpotlights
{
    /**
     * Build or return cached array of rich community spotlights
     * across active WordCamps (sessions, speakers, organizers, sponsors).
     *
     * @param Collection<int, Event> $events
     * @return array<int, array<string, mixed>>
     */
    public static function forEvents(Collection $events): array
    {
        if ($events->isEmpty()) {
            return [];
        }

        return Cache::remember('home:hero-spotlights:v3', now()->addMinutes(30), function () use ($events) {
            $spotlights = [];

            foreach ($events as $event) {
                $country = EventCountry::code($event) ?: 'ALL';
                $eventLogo = $event->markUrl() ?? '/media/icons/icon-192.png';
                $sessions = EventData::get($event->id, 'sessions') ?? [];
                $speakers = EventData::get($event->id, 'speakers') ?? [];
                $sponsors = EventData::get($event->id, 'sponsors') ?? [];
                $organizers = EventData::get($event->id, 'organizers') ?? [];

                // 1. Meaningful Sessions
                $sessionCount = 0;
                foreach ($sessions as $s) {
                    $title = trim($s['title'] ?? '');
                    if (strlen($title) > 10 && !preg_match('/(lunch|coffee|tea|registration|break|closing)/i', $title)) {
                        $speakerName = null;
                        if (!empty($s['speaker_ids']) && !empty($speakers)) {
                            $sp = collect($speakers)->firstWhere('id', $s['speaker_ids'][0]);
                            $speakerName = $sp['name'] ?? null;
                        }
                        $spotlights[] = [
                            'event_id' => $event->id,
                            'event_name' => $event->display_name,
                            'event_slug' => $event->slug,
                            'event_logo' => $eventLogo,
                            'country' => $country,
                            'type' => 'session',
                            'badge' => 'Featured Session',
                            'title' => $title,
                            'meta' => $speakerName ? "Speaker: {$speakerName}" . ($s['starts_at'] ? ' · ' . substr($s['starts_at'], 11, 5) : '') : (($s['track_names'][0] ?? 'Main Track') . ($s['starts_at'] ? ' · ' . substr($s['starts_at'], 11, 5) : '')),
                            'icon' => 'calendar',
                            'url' => route('event.home', $event),
                        ];
                        $sessionCount++;
                        if ($sessionCount >= 4) break;
                    }
                }

                // 2. Speakers
                foreach (array_slice($speakers, 0, 3) as $sp) {
                    if (empty($sp['name'])) continue;
                    $spotlights[] = [
                        'event_id' => $event->id,
                        'event_name' => $event->display_name,
                        'event_slug' => $event->slug,
                        'event_logo' => $eventLogo,
                        'country' => $country,
                        'type' => 'speaker',
                        'badge' => 'Speaker Spotlight',
                        'title' => $sp['name'],
                        'meta' => 'Sharing knowledge with the WordPress community',
                        'icon' => 'users',
                        'url' => route('event.home', $event),
                    ];
                }

                // 3. Organizers & Volunteers
                foreach (array_slice($organizers, 0, 2) as $org) {
                    if (empty($org['name'])) continue;
                    $spotlights[] = [
                        'event_id' => $event->id,
                        'event_name' => $event->display_name,
                        'event_slug' => $event->slug,
                        'event_logo' => $eventLogo,
                        'country' => $country,
                        'type' => 'organizer',
                        'badge' => 'Community Organizer',
                        'title' => $org['name'],
                        'meta' => 'Volunteer driven · Community powered',
                        'icon' => 'heart',
                        'url' => route('event.home', $event),
                    ];
                }

                // 4. Community Sponsors / Micro-sponsors
                foreach (array_slice($sponsors, 0, 2) as $spons) {
                    if (empty($spons['name'])) continue;
                    $spotlights[] = [
                        'event_id' => $event->id,
                        'event_name' => $event->display_name,
                        'event_slug' => $event->slug,
                        'event_logo' => $eventLogo,
                        'country' => $country,
                        'type' => 'sponsor',
                        'badge' => 'Community Sponsor',
                        'title' => $spons['name'],
                        'meta' => 'Supporting the WordPress community',
                        'icon' => 'sparkles',
                        'url' => route('event.home', $event),
                    ];
                }
            }

            return $spotlights;
        });
    }
}
