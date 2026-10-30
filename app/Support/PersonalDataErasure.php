<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Actually deletes what an attendee shared with CampBuddy once the purpose is served (DPDPA Act 2023, s.8(7)).
 *
 * Until RETENTION_DAYS after an event's last day nothing is touched (docs: 23 Data retention). After that the rows
 * are only hidden by "expired", so this removes them for good ERASE_AFTER_DAYS later (60: about 90 days after the
 * event, the same moment the event leaves the picker). It removes only things people chose to share: discovery
 * profiles with their waves and messages, shared Camp Cards, push addresses, server-side reminder bookmarks, deal-form
 * leads, discovery reports and which devices opened the event (for live announcements). Public attendee lists, ratings and the event itself are not personal uploads and stay.
 */
class PersonalDataErasure
{
    public const ERASE_AFTER_DAYS = 60;

    /** Tables (all keyed by event_id) that hold what attendees shared, in the order they are emptied. */
    private const TABLES = [
        'discovery_messages',
        'discovery_waves',
        'device_transfers',
        'discovery_reports',
        'discovery_profiles',
        'shared_camp_cards',
        'push_subscriptions',
        'native_push_tokens',
        'session_bookmarks',
        'offer_leads',
        // A suggested Free Steal (the suggester's name and email) and a sponsor's deal form (the sponsor
        // contact's name, email, phone, address). An approved deal lives on as an Offer, which stays.
        'free_steal_suggestions',
        'sponsor_deal_submissions',
        'consent_logs',
        'event_device_visits',
    ];

    /** The moment this event's shared data is erased, or null when it has no dates yet. */
    public static function eraseAt(Event $event): ?\DateTimeInterface
    {
        return EventTime::retentionEnd($event)?->addDays(self::ERASE_AFTER_DAYS);
    }

    public static function due(Event $event, ?\DateTimeInterface $now = null): bool
    {
        $at = self::eraseAt($event);

        return $at !== null && ($now ?? now()) >= $at;
    }

    /**
     * @return array<string, int> rows removed per table, for events whose time has come
     */
    public static function run(bool $dryRun = false, ?\DateTimeInterface $now = null): array
    {
        $removed = array_fill_keys(self::TABLES, 0);

        Event::query()->get()->filter(fn (Event $event) => self::due($event, $now))->each(function (Event $event) use (&$removed, $dryRun) {
            foreach (self::TABLES as $table) {
                $query = DB::table($table)->where('event_id', $event->id);
                if ($table === 'sponsor_deal_submissions' && ! $dryRun) {
                    self::deleteUnusedLogos((clone $query)->pluck('logo_path')->filter()->all());
                }
                $removed[$table] += $dryRun ? $query->count() : $query->delete();
            }
        });

        return $removed;
    }

    /**
     * A sponsor's uploaded logo goes with the form — unless an approved deal shows it (it then belongs to
     * the media library, media_assets).
     *
     * @param  array<int, string>  $paths
     */
    public static function deleteUnusedLogos(array $paths): void
    {
        if ($paths === []) {
            return;
        }

        $used = DB::table('media_assets')->whereIn('path', $paths)->pluck('path')->all();

        foreach (array_diff($paths, $used) as $path) {
            Storage::disk('public')->delete($path);
        }
    }
}
