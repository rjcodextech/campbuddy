<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Support\Facades\Cache;
use App\Models\ContributorTable;
use Illuminate\Database\QueryException;

/**
 * Who on the attendee list is also an organizer, speaker, volunteer or
 * microsponsor of the event — so the list can mark them.
 *
 * Organizers and speakers come from the event site's REST data, with a
 * Gravatar: the same picture hash on the Attendees page means the same e-mail,
 * so the same person. Without a match there (or for volunteers, who have no
 * picture), an exact name match counts — only when that name appears once on
 * the list, never guessing between two people. Microsponsors are marked by the
 * roster scraper itself (their own block on the Attendees page).
 *
 * On top of that, an admin or the event's managers can mark anyone by hand
 * (RosterMarks: media partner, sponsor, table lead… or take a wrong automatic
 * mark off). A hand mark is kept by the person's Gravatar hash or name, so it
 * survives the nightly re-import of the list.
 */
class RosterRoles
{
    /** The order badges are shown in. */
    public const ROLES = ['organizer', 'speaker', 'volunteer', 'microsponsor', 'sponsor', 'media_partner', 'table_lead'];

    /** Names for the admin and manager pages. */
    public const LABELS = [
        'organizer' => 'Organizer',
        'speaker' => 'Speaker',
        'volunteer' => 'Volunteer',
        'microsponsor' => 'Microsponsor',
        'sponsor' => 'Sponsor',
        'media_partner' => 'Media Partner',
        'table_lead' => 'Table Lead',
    ];

    /** Talk titles shown per speaker, at most. */
    private const MAX_TALKS = 3;

    /**
     * Roster id => its roles (in ROLES order) and, for a speaker, their talks.
     * Built at most once a minute per event; the roster API pages share it.
     *
     * @return array<int, array{roles: list<string>, talks: list<string>}>
     */
    public static function forEvent(Event $event): array
    {
        return Cache::remember(self::cacheKey($event), 60, fn () => self::build($event)['marked']);
    }

    /**
     * Both answers, freshly worked out in one pass: with the hand marks, and
     * only what was found automatically — the admin and manager pages show
     * the second as "auto" next to a role.
     *
     * @return array{marked: array<int, array{roles: list<string>, talks: list<string>}>, auto: array<int, array{roles: list<string>, talks: list<string>}>}
     */
    public static function withAutomatic(Event $event): array
    {
        return self::build($event);
    }

    /** After a hand mark or a Contributor table changes: the next request rebuilds. */
    public static function forget(Event $event): void
    {
        Cache::forget(self::cacheKey($event));
    }

    private static function cacheKey(Event $event): string
    {
        return "event:{$event->id}:roster-roles";
    }

    /** @return array{marked: array<int, array{roles: list<string>, talks: list<string>}>, auto: array<int, array{roles: list<string>, talks: list<string>}>} */
    private static function build(Event $event): array
    {
        $query = fn (array $columns) => $event->attendeeRoster()->where('is_suppressed', false)->get($columns);
        try {
            $entries = $query(['id', 'name', 'gravatar_url', 'is_microsponsor']);
        } catch (QueryException) {
            // Before `php artisan migrate` has added the column: everything but the microsponsor mark.
            $entries = $query(['id', 'name', 'gravatar_url']);
        }

        $byHash = [];
        $byName = [];
        foreach ($entries as $entry) {
            if ($hash = self::avatarHash($entry->gravatar_url)) {
                $byHash[$hash][] = $entry->id;
            }
            $byName[self::nameKey($entry->name)][] = $entry->id;
        }

        $found = [];
        $mark = function (array $person, string $role) use (&$found, $byHash, $byName): array {
            $hash = self::avatarHash($person['avatar_url'] ?? null);
            $ids = $hash !== null ? ($byHash[$hash] ?? []) : [];

            if ($ids === []) {
                $named = $byName[self::nameKey((string) ($person['name'] ?? ''))] ?? [];
                $ids = count($named) === 1 ? $named : [];
            }

            foreach ($ids as $id) {
                $found[$id][$role] = true;
            }

            return $ids;
        };

        foreach (EventData::get($event->id, 'organizers') ?? [] as $person) {
            $mark($person, 'organizer');
        }

        $speakers = EventData::get($event->id, 'speakers') ?? [];
        $talksBySpeaker = $speakers === [] ? [] : self::talksBySpeaker(EventData::get($event->id, 'sessions') ?? []);
        $talks = [];
        foreach ($speakers as $person) {
            foreach ($mark($person, 'speaker') as $id) {
                $talks[$id] = array_values(array_unique([...($talks[$id] ?? []), ...($talksBySpeaker[$person['id'] ?? 0] ?? [])]));
            }
        }

        foreach (EventData::get($event->id, 'volunteers') ?? [] as $person) {
            $mark(['name' => $person['name'] ?? ''], 'volunteer');
        }

        foreach ($entries as $entry) {
            if ($entry->is_microsponsor ?? false) {
                $found[$entry->id]['microsponsor'] = true;
            }
        }

        // Contributor Day table leads (ContributorTable): picked from the list, or typed names on it once.
        $leads = ContributorTable::leadsOf($event);
        foreach ($leads['ids'] as $id) {
            if ($entries->contains('id', $id)) {
                $found[$id]['table_lead'] = true;
            }
        }
        foreach ($leads['names'] as $name) {
            $mark(['name' => $name], 'table_lead');
        }

        $auto = self::shape($found, $talks);

        $byKey = [];
        foreach ($entries as $entry) {
            $byKey[RosterMarks::keyFor($entry)][] = $entry->id;
        }

        foreach (RosterMarks::forEvent($event) as $mark) {
            foreach ($byKey[$mark->person_key] ?? [] as $id) {
                if ($mark->action === 'add') {
                    $found[$id][$mark->role] = true;
                } else {
                    unset($found[$id][$mark->role]);
                }
            }
        }

        return ['marked' => self::shape($found, $talks), 'auto' => $auto];
    }

    /**
     * @param  array<int, array<string, true>>  $found
     * @param  array<int, list<string>>  $talks
     * @return array<int, array{roles: list<string>, talks: list<string>}>
     */
    private static function shape(array $found, array $talks): array
    {
        $result = [];
        foreach ($found as $id => $roles) {
            if ($roles === []) {
                continue;
            }
            $result[$id] = [
                'roles' => array_values(array_filter(self::ROLES, fn ($role) => isset($roles[$role]))),
                'talks' => array_slice($talks[$id] ?? [], 0, self::MAX_TALKS),
            ];
        }

        return $result;
    }

    /** The Gravatar hash (md5 or sha256) in a picture URL, if it is one. */
    public static function avatarHash(?string $url): ?string
    {
        return $url && preg_match('~gravatar\.com/avatar/([0-9a-f]{32,64})~i', $url, $m) ? strtolower($m[1]) : null;
    }

    public static function nameKey(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($name, ENT_QUOTES | ENT_HTML5))));
    }

    /**
     * @param  array<int, mixed>  $sessions
     * @return array<int, list<string>> speaker id => titles, in schedule order
     */
    private static function talksBySpeaker(array $sessions): array
    {
        usort($sessions, fn ($a, $b) => strcmp((string) ($a['starts_at'] ?? '~'), (string) ($b['starts_at'] ?? '~')));

        $talks = [];
        foreach ($sessions as $session) {
            foreach ($session['speaker_ids'] ?? [] as $speakerId) {
                if (($session['title'] ?? '') !== '') {
                    $talks[$speakerId][] = $session['title'];
                }
            }
        }

        return $talks;
    }
}
