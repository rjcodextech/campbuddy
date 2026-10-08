<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Support\Facades\Cache;
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
 */
class RosterRoles
{
    /** The order badges are shown in. */
    public const ROLES = ['organizer', 'speaker', 'volunteer', 'microsponsor'];

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
        return Cache::remember("event:{$event->id}:roster-roles", 60, fn () => self::build($event));
    }

    /** @return array<int, array{roles: list<string>, talks: list<string>}> */
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

        $result = [];
        foreach ($found as $id => $roles) {
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

    private static function nameKey(string $name): string
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
