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
        Cache::forget(self::cacheKey($event).':grouped-unlisted');
    }

    private static function cacheKey(Event $event): string
    {
        return "event:{$event->id}:roster-roles";
    }

    /** @return array{marked: array<int, array{roles: list<string>, talks: list<string>}>, auto: array<int, array{roles: list<string>, talks: list<string>}>, unlisted: list<array{role: string, person: array<string, mixed>, talks: list<string>, ids: list<int>}>} */
    private static function build(Event $event): array
    {
        EventData::prime($event->id);
        $query = fn (array $columns) => $event->attendeeRoster()->where('is_suppressed', false)->get($columns);
        try {
            $entries = $query(['id', 'name', 'links', 'gravatar_url', 'is_microsponsor']);
        } catch (QueryException) {
            try {
                // Before `php artisan migrate` has added the column: everything but the microsponsor mark.
                $entries = $query(['id', 'name', 'gravatar_url', 'is_microsponsor']);
            } catch (QueryException) {
                $entries = $query(['id', 'name', 'gravatar_url']);
            }
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
            $site[] = ['role' => 'volunteer', 'person' => $person, 'talks' => [], 'ids' => $mark(is_array($person) ? $person : ['name' => (string) $person], 'volunteer')];
        }

        foreach (EventData::get($event->id, 'media_partners') ?? [] as $partner) {
            if (is_array($partner) && filled($partner['name'] ?? null)) {
                $mark(['name' => $partner['name']], 'media_partner');
            }
        }

        foreach (EventData::get($event->id, 'sponsors') ?? [] as $sponsor) {
            if (! is_array($sponsor) || empty($sponsor['name'])) {
                continue;
            }
            $tiers = strtolower(implode(' ', $sponsor['tier_names'] ?? []));
            if (preg_match('/micro[\s_-]*sponsor/i', $tiers)) {
                $mark(['name' => $sponsor['name']], 'microsponsor');
            }
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
     * Deduplicated public event lineup (organizers, speakers, volunteers) whose
     * role is not already represented on the public attendee list and who did
     * not suppress their name from the list. Merges multiple roles (e.g. a
     * person who is both organizer and speaker) into one card.
     *
     * @return list<array<string, mixed>>
     */
    public static function groupedUnlisted(Event $event): array
    {
        return Cache::remember(self::cacheKey($event).':grouped-unlisted', 60, fn () => self::buildGroupedUnlisted($event));
    }

    /** @return list<array<string, mixed>> */
    private static function buildGroupedUnlisted(Event $event): array
    {
        $byKey = [];
        foreach (self::unlisted($event) as $item) {
            $person = $item['person'] ?? [];
            $name = trim((string) ($person['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $hash = self::avatarHash($person['gravatar_url'] ?? $person['avatar_url'] ?? null);
            $key = $hash !== null ? 'h:'.$hash : 'n:'.PersonName::loose($name);

            if (! isset($byKey[$key])) {
                $links = [];
                if ($siteUrl = SafeUrl::web($person['link'] ?? null)) {
                    $links[] = ['type' => 'website', 'url' => $siteUrl];
                }
                foreach ($person['social_links'] ?? [] as $social) {
                    if (is_array($social) && ($url = SafeUrl::web($social['url'] ?? null))) {
                        $links[] = ['type' => (string) ($social['type'] ?? 'website'), 'url' => $url];
                    }
                }
                if (! empty($person['wporg_username'])) {
                    $wporgUrl = SafeUrl::web('https://profiles.wordpress.org/'.rawurlencode((string) $person['wporg_username']).'/');
                    if ($wporgUrl) {
                        $links[] = ['type' => 'website', 'url' => $wporgUrl];
                    }
                }

                $photo = SafeUrl::web($person['avatar_url'] ?? $person['gravatar_url'] ?? null);
                $gravatar = SafeUrl::web($person['gravatar_url'] ?? null);

                $byKey[$key] = [
                    'id' => 'team-'.substr(md5($key), 0, 10),
                    'name' => $name,
                    'photo_url' => $photo,
                    'gravatar_url' => $gravatar ?? $photo,
                    'links' => collect($links)->unique('url')->values()->all(),
                    'roles' => [],
                    'talks' => [],
                    'open_to_meet' => false,
                    'is_team_lineup' => true,
                ];
            }

            $role = $item['role'];
            if (! in_array($role, $byKey[$key]['roles'], true)) {
                $byKey[$key]['roles'][] = $role;
            }
            foreach ($item['talks'] ?? [] as $talk) {
                if (is_string($talk) && $talk !== '' && ! in_array($talk, $byKey[$key]['talks'], true)) {
                    $byKey[$key]['talks'][] = $talk;
                }
            }
        }

        return array_values(array_map(function (array $entry) {
            $entry['roles'] = array_values(array_filter(self::ROLES, fn ($r) => in_array($r, $entry['roles'], true)));
            $entry['talks'] = array_slice($entry['talks'], 0, self::MAX_TALKS);

            return $entry;
        }, $byKey));
    }

    /**
     * The list's entries by Gravatar hash, social handle, exact name, loose
     * name, and disambiguated variants (parenthetical nicknames, trailing
     * digits, spaceless compounds, first+last, doubled-letter collapse).
     *
     * @param  iterable<object>  $entries  with id, name, gravatar_url and optionally links
     * @return array<string, mixed>
     */
    private static function index(iterable $entries): array
    {
        $index = [
            'hash' => [],
            'hashOf' => [],
            'wporg' => [],
            'handle' => [],
            'nameOf' => [],
            'name' => [],
            'loose' => [],
            'stripped' => [],
            'compact' => [],
            'first_last' => [],
            'folded' => [],
        ];

        foreach ($entries as $entry) {
            if ($hash = self::avatarHash($entry->gravatar_url)) {
                $index['hash'][$hash][] = $entry->id;
                $index['hashOf'][$entry->id] = $hash;
            }

            $variants = PersonName::variantKeys((string) $entry->name);
            $index['nameOf'][$entry->id] = $variants['stripped'];
            $index['name'][self::nameKey((string) $entry->name)][] = $entry->id;
            $index['loose'][PersonName::loose((string) $entry->name)][] = $entry->id;

            foreach (['stripped', 'compact', 'first_last', 'folded'] as $kind) {
                if (! empty($variants[$kind])) {
                    $index[$kind][$variants[$kind]][] = $entry->id;
                }
            }

            foreach ($entry->links ?? [] as $link) {
                $url = is_array($link) ? ($link['url'] ?? '') : '';
                if (! is_string($url) || $url === '') {
                    continue;
                }
                foreach (self::extractHandles($url) as $pair) {
                    if ($pair['wporg']) {
                        $index['wporg'][$pair['handle']][] = $entry->id;
                    }
                    $index['handle'][$pair['handle']][] = $entry->id;
                }
            }
        }

        return $index;
    }

    /**
     * Who on the list a site person is: the same Gravatar, else their wp.org /
     * social handle, else a name that resolves to one person on the list.
     *
     * @param  array<string, mixed>  $person
     * @param  array<string, mixed>  $index
     * @param  bool  $once  false: everyone the name could be (to keep clear of people who left the list)
     * @return list<int>
     */
    private static function match(array $person, array $index, bool $once = true): array
    {
        // The Gravatar (not the site's photo) is what the attendee list shows too.
        $hash = self::avatarHash($person['gravatar_url'] ?? $person['avatar_url'] ?? null);
        $ids = $hash !== null ? ($index['hash'][$hash] ?? []) : [];
        $name = (string) ($person['name'] ?? '');
        $variants = trim($name) !== '' ? PersonName::variantKeys($name) : ['stripped' => '', 'compact' => null, 'first_last' => null, 'folded' => null];
        $siteTokens = array_values(array_filter(explode(' ', $variants['stripped']), fn ($t) => mb_strlen($t) >= 3));

        $isSinglePerson = fn (array $named): bool => count($named) === 1
            || ($named !== [] && count(array_unique(array_map(fn ($id) => $index['hashOf'][$id] ?? "none:{$id}", $named))) === 1);

        // WordPress.org handle match: profiles.wordpress.org/<user> directly, or same social handle with a shared name token.
        if ($ids === []) {
            $wporg = strtolower(trim((string) ($person['wporg_username'] ?? '')));
            if ($wporg !== '' && ($matchedWporg = $index['wporg'][$wporg] ?? []) !== []) {
                if (! $once || $isSinglePerson($matchedWporg)) {
                    $ids = $matchedWporg;
                }
            }

            if ($ids === []) {
                $candidateHandles = [];
                if ($wporg !== '' && strlen($wporg) >= 4) {
                    $candidateHandles[] = $wporg;
                }
                foreach ($person['social_links'] ?? [] as $social) {
                    $url = is_array($social) ? ($social['url'] ?? '') : '';
                    if (is_string($url) && $url !== '') {
                        foreach (self::extractHandles($url) as $pair) {
                            $candidateHandles[] = $pair['handle'];
                        }
                    }
                }
                foreach (array_unique($candidateHandles) as $handle) {
                    $matched = $index['handle'][$handle] ?? [];
                    if ($matched === []) {
                        continue;
                    }
                    if (! $once) {
                        $ids = $matched;
                        break;
                    }
                    if ($isSinglePerson($matched) && $siteTokens !== []) {
                        $entryTokens = explode(' ', (string) ($index['nameOf'][$matched[0]] ?? ''));
                        if (array_intersect($siteTokens, $entryTokens) !== []) {
                            $ids = $matched;
                            break;
                        }
                    }
                }
            }
        }

        if ($ids === [] && trim($name) !== '') {
            $buckets = [
                $index['name'][self::nameKey($name)] ?? [],
                $index['loose'][PersonName::loose($name)] ?? [],
                $variants['stripped'] !== '' ? ($index['stripped'][$variants['stripped']] ?? []) : [],
                $variants['compact'] !== null ? ($index['compact'][$variants['compact']] ?? []) : [],
                $variants['first_last'] !== null ? ($index['first_last'][$variants['first_last']] ?? []) : [],
                $variants['folded'] !== null ? ($index['folded'][$variants['folded']] ?? []) : [],
            ];

            foreach ($buckets as $named) {
                $onePerson = $isSinglePerson($named);
                $ids = ! $once || $onePerson ? $named : [];
                if ($named !== []) {
                    break;
                }
            }
        }

        return $ids;
    }

    /** @return list<array{handle: string, wporg: bool}> */
    private static function extractHandles(string $url): array
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || empty($parts['host']) || empty($parts['path'])) {
            return [];
        }

        $host = strtolower(preg_replace('/^www\./', '', $parts['host']) ?? '');
        $path = trim($parts['path'], '/');
        if ($path === '') {
            return [];
        }

        $segments = explode('/', $path);
        if ($host === 'profiles.wordpress.org' && isset($segments[0]) && preg_match('/^[a-z0-9._-]{3,}$/i', $segments[0])) {
            return [['handle' => strtolower($segments[0]), 'wporg' => true]];
        }
        if ($host === 'linkedin.com' && ($segments[0] ?? '') === 'in' && isset($segments[1]) && preg_match('/^[a-z0-9._-]{3,}$/i', $segments[1])) {
            return [['handle' => strtolower($segments[1]), 'wporg' => false]];
        }
        if (in_array($host, ['x.com', 'twitter.com', 'github.com', 'instagram.com'], true) && isset($segments[0]) && preg_match('/^[a-z0-9._-]{3,}$/i', ltrim($segments[0], '@'))) {
            return [['handle' => strtolower(ltrim($segments[0], '@')), 'wporg' => false]];
        }

        return [];
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

        try {
            $removedEntries = $event->attendeeRoster()->where('is_suppressed', true)->get(['id', 'name', 'links', 'gravatar_url']);
        } catch (QueryException) {
            $removedEntries = $event->attendeeRoster()->where('is_suppressed', true)->get(['id', 'name', 'gravatar_url']);
        }

        $removed = self::index($removedEntries);

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
