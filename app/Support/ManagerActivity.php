<?php

namespace App\Support;

use App\Models\Event;
use App\Models\EventManager;
use App\Models\EventManagerChange;
use App\Models\Quest;
use Illuminate\Support\Str;
use Throwable;

/**
 * The record of what event managers change, and the words for it.
 *
 * Writing an entry must never stop the change it describes: if the table isn't
 * there yet (code uploaded before the migration) or the write fails, the error
 * is reported and the manager's save still goes through — the Errors page
 * already flags the pending migration.
 */
class ManagerActivity
{
    /** How long entries are kept; older ones are removed as new ones are written. */
    public const KEEP_DAYS = 90;

    /** Labels for the fields a manager can change on the details page. */
    private const DETAIL_LABELS = [
        'display_name' => 'Display name',
        'slug' => 'URL slug',
        'short_name' => 'Short name',
        'source_site_url' => 'Source site URL',
        'starts_on' => 'Start date',
        'ends_on' => 'End date',
        'timezone' => 'Time zone',
        'visibility' => 'Visibility',
    ];

    private const INFO_LABELS = [
        'venue' => 'Venue',
        'wifi' => 'Wifi',
        'contributor_day_location' => 'Contributor Day location',
        'code_of_conduct_url' => 'Code of conduct URL',
        'registration_info' => 'Registration info',
        'emergency_contact' => 'Emergency contact',
        'social_event_info' => 'Social event info',
        'nearby_venue_info' => 'Nearby venues',
        'important_links' => 'Important links',
    ];

    public static function record(EventManager $manager, Event $event, string $section, string $action, string $summary): void
    {
        try {
            EventManagerChange::create([
                'event_manager_id' => $manager->id,
                'manager_name' => $manager->name,
                'event_id' => $event->id,
                'section' => $section,
                'action' => $action,
                'summary' => Str::limit($summary, 480),
            ]);

            // Housekeeping rides on the writes themselves: no extra scheduled job to forget.
            if (random_int(1, 50) === 1) {
                EventManagerChange::where('created_at', '<', now()->subDays(self::KEEP_DAYS))->delete();
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The details fields as they are right now, for comparing before and after a save.
     *
     * @return array<string, ?string>
     */
    public static function detailsSnapshot(Event $event): array
    {
        return [
            'display_name' => $event->display_name,
            'slug' => $event->slug,
            'short_name' => $event->short_name,
            'source_site_url' => $event->source_site_url,
            'starts_on' => $event->starts_on?->toDateString(),
            'ends_on' => $event->ends_on?->toDateString(),
            // A zone that is only "read from the site" isn't the manager's to have changed.
            'timezone' => $event->timezone_locked ? $event->timezone : null,
            'visibility' => $event->is_visible ? 'visible' : 'hidden',
        ];
    }

    /**
     * "URL slug: “a” → “b”; Visibility: “visible” → “hidden”" — null when nothing changed.
     *
     * @param  array<string, ?string>  $before
     * @param  array<string, ?string>  $after
     */
    public static function detailsChange(array $before, array $after): ?string
    {
        $parts = [];

        foreach (self::DETAIL_LABELS as $field => $label) {
            if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                $parts[] = $label.': '.self::quote($before[$field] ?? null).' → '.self::quote($after[$field] ?? null);
            }
        }

        return $parts === [] ? null : implode('; ', $parts);
    }

    /**
     * Which information fields changed — names only, never the text (it can be long, and it is public anyway).
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public static function infoChange(array $before, array $after): ?string
    {
        $updated = [];
        $cleared = [];

        foreach (self::INFO_LABELS as $field => $label) {
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;

            if ($old === $new) {
                continue;
            }

            if (blank($new)) {
                $cleared[] = $label;
            } else {
                $updated[] = $label;
            }
        }

        $parts = array_filter([
            $updated ? 'Updated: '.implode(', ', $updated) : null,
            $cleared ? 'Cleared: '.implode(', ', $cleared) : null,
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /** What changed on a checklist item — null when nothing did. */
    public static function questChange(Quest $before, Quest $after): ?string
    {
        $parts = [];

        if ($before->title !== $after->title) {
            $parts[] = 'Renamed '.self::quote($before->title).' → '.self::quote($after->title);
        }
        if ((string) $before->description !== (string) $after->description) {
            $parts[] = 'Changed the description of '.self::quote($after->title);
        }
        if ((int) $before->sort_order !== (int) $after->sort_order) {
            $parts[] = 'Moved '.self::quote($after->title).' (order '.(int) $before->sort_order.' → '.(int) $after->sort_order.')';
        }
        if ($before->is_active !== $after->is_active) {
            $parts[] = ($after->is_active ? 'Showed ' : 'Hid ').self::quote($after->title);
        }

        return $parts === [] ? null : implode('; ', $parts);
    }

    public static function quote(?string $value): string
    {
        return '“'.(filled($value) ? Str::limit($value, 70) : '—').'”';
    }
}
