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
 * the list, never guessing between two people — and then the same name spelled
 * a little differently (PersonName::loose: "Stéphane" = "Stephane", a middle
 * initial or "Md." dropped), again only when it appears once. Microsponsors are
 * marked by the roster scraper itself (their own block on the Attendees page).
 *
 * The site's organizers, speakers and volunteers who aren't on the list (no
 * ticket yet, another name there) are kept apart (unlisted): the social media
 * studio still makes them a card.
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

    /**
     * The site's organizers, speakers and volunteers no one on the attendee
     * list carries the role for (not on it, more than one with that name, or
     * an automatic match taken off by hand) — minus anyone who asked to be
     * taken off the list. Each with the list entries they matched, if any.
     *
     * @return list<array{role: string, person: array<string, mixed>, talks: list<string>, ids: list<int>}>
     */
    public static function unlisted(Event $event): array
    {
        return Cache::remember(self::cacheKey($event).':unlisted', 60, fn () => self::build($event)['unlisted']);
    }

    /** After a hand mark or a Contributor table changes: the next request rebuilds. */
    public static function forget(Event $event): void
    {
        Cache::forget(self::cacheKey($event));
        Cache::forget(self::cacheKey($event).':unlisted');
    }

    private static function cacheKey(Event $event): string
    {
        return "event:{$event->id}:roster-roles";
    }

    /** @return array{marked: array<int, array{roles: list<string>, talks: list<string>}>, auto: array<int, array{roles: list<string>, talks: list<string>}>, unlisted: list<array{role: string, person: array<string, mixed>, talks: list<string>, ids: list<int>}>} */
    private static function build(Event $event): array
    {
        $query = fn (array $columns) => $event->attendeeRoster()->where('is_suppressed', false)->get($columns);
        try {
            $entries = $query(['id', 'name', 'gravatar_url', 'is_microsponsor']);
        } catch (QueryException) {
            // Before `php artisan migrate` has added the column: everything but the microsponsor mark.
            $entries = $query(['id', 'name', 'gravatar_url']);
        }

        $index = self::index($entries);

        $found = [];
        $mark = function (array $person, string $role) use (&$found, $index): array {
            $ids = self::match($person, $index);

            foreach ($ids as $id) {
                $found[$id][$role] = true;
            }

            return $ids;
        };

        // The site's own lists, each person with whom they matched (for `unlisted`).
        $site = [];
        foreach (EventData::get($event->id, 'organizers') ?? [] as $person) {
            $site[] = ['role' => 'organizer', 'person' => $person, 'talks' => [], 'ids' => $mark($person, 'organizer')];
        }

        $speakers = EventData::get($event->id, 'speakers') ?? [];
        $talksBySpeaker = $speakers === [] ? [] : self::talksBySpeaker(EventData::get($event->id, 'sessions') ?? []);
        $talks = [];
        foreach ($speakers as $person) {
            $own = $talksBySpeaker[$person['id'] ?? 0] ?? [];
            $ids = $mark($person, 'speaker');
            foreach ($ids as $id) {
                $talks[$id] = array_values(array_unique([...($talks[$id] ?? []), ...$own]));
            }
            $site[] = ['role' => 'speaker', 'person' => $person, 'talks' => array_slice($own, 0, self::MAX_TALKS), 'ids' => $ids];
        }

        foreach (EventData::get($event->id, 'volunteers') ?? [] as $person) {
            $site[] = ['role' => 'volunteer', 'person' => $person, 'talks' => [], 'ids' => $mark(['name' => $person['name'] ?? ''], 'volunteer')];
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

        return ['marked' => self::shape($found, $talks), 'auto' => $auto, 'unlisted' => self::withoutRole($event, $site, $found)];
    }

    /**
     * The list's entries by Gravatar hash, exact name and loose name.
     *
     * @param  iterable<object>  $entries  with id, name and gravatar_url
     * @return array{hash: array<string, list<int>>, hashOf: array<int, string>, name: array<string, list<int>>, loose: array<string, list<int>>}
     */
    private static function index(iterable $entries): array
    {
        $index = ['hash' => [], 'hashOf' => [], 'name' => [], 'loose' => []];
        foreach ($entries as $entry) {
            if ($hash = self::avatarHash($entry->gravatar_url)) {
                $index['hash'][$hash][] = $entry->id;
                $index['hashOf'][$entry->id] = $hash;
            }
            $index['name'][self::nameKey((string) $entry->name)][] = $entry->id;
            $index['loose'][PersonName::loose((string) $entry->name)][] = $entry->id;
        }

        return $index;
    }

    /**
     * Who on the list a site person is: the same Gravatar, else a name that
     * is one person there, else a loosely equal name that is one person there.
     * One person: one entry, or several with the same Gravatar (two tickets,
     * one e-mail address).
     *
     * @param  array<string, mixed>  $person
     * @param  array{hash: array<string, list<int>>, hashOf: array<int, string>, name: array<string, list<int>>, loose: array<string, list<int>>}  $index
     * @param  bool  $once  false: everyone the name could be (to keep clear of people who left the list)
     * @return list<int>
     */
    private static function match(array $person, array $index, bool $once = true): array
    {
        // The Gravatar (not the site's photo) is what the attendee list shows too.
        $hash = self::avatarHash($person['gravatar_url'] ?? $person['avatar_url'] ?? null);
        $ids = $hash !== null ? ($index['hash'][$hash] ?? []) : [];
        $name = (string) ($person['name'] ?? '');

        if ($ids === [] && trim($name) !== '') {
            foreach ([$index['name'][self::nameKey($name)] ?? [], $index['loose'][PersonName::loose($name)] ?? []] as $named) {
                $onePerson = count($named) === 1 || ($named !== [] && count(array_unique(array_map(fn ($id) => $index['hashOf'][$id] ?? "none:{$id}", $named))) === 1);
                $ids = ! $once || $onePerson ? $named : [];
                if ($named !== []) {
                    break;
                }
            }
        }

        return $ids;
    }

    /**
     * Of the site's people, those no entry ended up with the role for —
     * leaving out anyone who matches an entry taken off the list (suppressed).
     *
     * @param  list<array{role: string, person: array<string, mixed>, talks: list<string>, ids: list<int>}>  $site
     * @param  array<int, array<string, true>>  $found
     * @return list<array{role: string, person: array<string, mixed>, talks: list<string>, ids: list<int>}>
     */
    private static function withoutRole(Event $event, array $site, array $found): array
    {
        $site = array_values(array_filter($site, function (array $one) use ($found) {
            foreach ($one['ids'] as $id) {
                if (isset($found[$id][$one['role']])) {
                    return false;
                }
            }

            return trim((string) ($one['person']['name'] ?? '')) !== '';
        }));

        if ($site === []) {
            return [];
        }

        $removed = self::index($event->attendeeRoster()->where('is_suppressed', true)->get(['id', 'name', 'gravatar_url']));

        return array_values(array_filter($site, fn (array $one) => self::match($one['person'], $removed, false) === []));
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
