<?php

namespace App\Jobs;

use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Models\FetchLog;
use App\Support\EventData;
use App\Support\RosterRoles;
use App\Support\SafeUrl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Finds a real photo for everyone on an event's attendee list (organizers,
 * speakers, volunteers, table leads, microsponsors, attendees — one list),
 * once a day while the event is live (routes/console.php). Most entries carry
 * a Gravatar address that may only be the grey "no photo" silhouette; this
 * looks, in order, for:
 *   1. the WordCamp site's own photo of them, when they are a speaker or an
 *      organizer there (the organizers chose it for this WordCamp);
 *   2. their own Gravatar, when it is a real picture (asked with d=404);
 *   3. the Gravatar the site has for them as a speaker or organizer;
 *   4. their WordPress.org profile picture (the profile link on the list).
 * What it found goes in photo_url (photo_source says where from). An entry is
 * looked at again after a week (three days when nothing was found), so a
 * daily run only checks what is new or stale, and each run is capped.
 */
class FillMissingPhotosJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    /** Entries looked at in one run. */
    public const PER_RUN = 400;

    /** WordPress.org profile pages read in one run (one request each, so kept low). */
    public const PROFILES_PER_RUN = 40;

    public const RECHECK_DAYS = 7;

    public const RECHECK_NONE_DAYS = 3;

    public function __construct(public readonly Event $event) {}

    public function handle(): void
    {
        if (! Schema::hasColumn('attendee_roster', 'photo_url')) {
            return;
        }

        Cache::lock("photos:{$this->event->id}", 300)->get(fn () => $this->run());
    }

    private function run(): void
    {
        $entries = AttendeeRoster::where('event_id', $this->event->id)
            ->where('is_suppressed', false)
            ->where(fn ($q) => $q->whereNull('photo_checked_at')
                ->orWhere(fn ($q) => $q->where('photo_source', 'none')->where('photo_checked_at', '<', now()->subDays(self::RECHECK_NONE_DAYS)))
                ->orWhere('photo_checked_at', '<', now()->subDays(self::RECHECK_DAYS)))
            ->orderByRaw('photo_checked_at IS NOT NULL')
            ->orderBy('photo_checked_at')
            ->limit(self::PER_RUN)
            ->get();

        if ($entries->isEmpty()) {
            return;
        }

        $site = $this->sitePeople();
        $found = ['site' => 0, 'gravatar' => 0, 'wporg' => 0, 'none' => 0];
        $results = [];
        $needGravatar = [];

        // 1. The site's photo of a speaker or organizer.
        foreach ($entries as $entry) {
            $person = $this->match($entry, $site);
            if ($person && ($person['photo'] ?? null) && RosterRoles::avatarHash($person['photo']) === null) {
                $results[$entry->id] = [$person['photo'], 'site'];
            } else {
                $needGravatar[$entry->id] = array_values(array_filter([$entry->gravatar_url, $person['gravatar'] ?? null]));
            }
        }

        // 2–3. A real Gravatar: theirs, else the one the site has for them.
        $real = $this->realGravatars(array_merge(...array_values($needGravatar ?: [[]])));
        foreach ($needGravatar as $id => $urls) {
            foreach ($urls as $url) {
                $hash = RosterRoles::avatarHash($url);
                if ($hash && ($real[$hash] ?? false)) {
                    $results[$id] = [self::gravatar($hash), 'gravatar'];
                    break;
                }
            }
        }

        // 4. Their WordPress.org profile picture.
        $profiles = 0;
        foreach ($entries as $entry) {
            if (isset($results[$entry->id]) || $profiles >= self::PROFILES_PER_RUN) {
                continue;
            }
            $user = $this->wporgUser($entry, $site);
            if ($user === null) {
                continue;
            }
            $profiles++;
            $hash = $this->profileGravatar($user);
            if ($hash !== null && ($real[$hash] ?? $this->realGravatars([self::gravatar($hash)])[$hash] ?? false)) {
                $results[$entry->id] = [self::gravatar($hash), 'wporg'];
            }
        }

        $now = now();
        foreach ($entries as $entry) {
            [$url, $source] = $results[$entry->id] ?? [null, 'none'];
            $found[$source]++;
            $entry->forceFill(['photo_url' => $url, 'photo_source' => $source, 'photo_checked_at' => $now])->saveQuietly();
        }

        if ($found['site'] + $found['gravatar'] + $found['wporg'] > 0) {
            // The roster pages and the social kit read photos from here.
            \App\Http\Controllers\Api\RosterController::forget($this->event);
        }

        FetchLog::create([
            'event_id' => $this->event->id,
            'source' => 'photos',
            'job_type' => 'photos',
            'status' => 'ok',
            'message' => sprintf('%d people checked: %d site photos, %d Gravatars, %d WordPress.org pictures, %d without a photo', $entries->count(), $found['site'], $found['gravatar'], $found['wporg'], $found['none']),
            'fetched_at' => $now,
        ]);
    }

    /**
     * The site's speakers and organizers: name key and Gravatar hash => their photo, Gravatar and username.
     *
     * @return array{byName: array<string, array>, byHash: array<string, array>}
     */
    private function sitePeople(): array
    {
        $byName = [];
        $byHash = [];
        foreach ([...(EventData::get($this->event->id, 'organizers') ?? []), ...(EventData::get($this->event->id, 'speakers') ?? [])] as $p) {
            $person = [
                'photo' => SafeUrl::web($p['avatar_url'] ?? null),
                'gravatar' => SafeUrl::web($p['gravatar_url'] ?? null),
                'user' => $p['wporg_username'] ?? null,
            ];
            $key = RosterRoles::nameKey((string) ($p['name'] ?? ''));
            if ($key !== '') {
                // The same name twice on the site can't tell two people apart: neither is used by name.
                $byName[$key] = isset($byName[$key]) ? false : $person;
            }
            if ($hash = RosterRoles::avatarHash($person['gravatar'])) {
                $byHash[$hash] = $person;
            }
        }

        return ['byName' => array_filter($byName), 'byHash' => $byHash];
    }

    private function match(AttendeeRoster $entry, array $site): ?array
    {
        $hash = RosterRoles::avatarHash($entry->gravatar_url);

        return ($hash ? ($site['byHash'][$hash] ?? null) : null) ?? ($site['byName'][RosterRoles::nameKey($entry->name)] ?? null);
    }

    /** Their WordPress.org username: from the profile link on the attendee list, else from the site. */
    private function wporgUser(AttendeeRoster $entry, array $site): ?string
    {
        foreach ((array) $entry->links as $link) {
            if (preg_match('~^https?://profiles\.wordpress\.org/([A-Za-z0-9._@-]{1,60})/?$~', (string) ($link['url'] ?? ''), $m)) {
                return $m[1];
            }
        }

        return $this->match($entry, $site)['user'] ?? null;
    }

    /** The Gravatar hash on a WordPress.org profile page, or null. */
    private function profileGravatar(string $user): ?string
    {
        try {
            $html = Http::timeout(8)->accept('text/html')->withUserAgent('CampBuddy (+https://campbuddy.club)')
                ->get('https://profiles.wordpress.org/'.rawurlencode($user).'/')->throw()->body();
        } catch (Throwable) {
            return null;
        }

        return preg_match('~gravatar\.com/avatar/([0-9a-f]{32,64})~i', $html, $m) ? strtolower($m[1]) : null;
    }

    /**
     * Which of these Gravatars are real pictures: asked with d=404, ten at a time.
     *
     * @param  list<string>  $urls
     * @return array<string, bool> hash => real
     */
    private function realGravatars(array $urls): array
    {
        $hashes = array_values(array_unique(array_filter(array_map(fn ($u) => RosterRoles::avatarHash($u), $urls))));
        $real = [];

        foreach (array_chunk($hashes, 10) as $chunk) {
            try {
                $responses = Http::pool(fn (Pool $pool) => array_map(
                    fn ($hash) => $pool->as($hash)->timeout(8)->head("https://secure.gravatar.com/avatar/{$hash}", ['s' => 80, 'd' => '404']),
                    $chunk
                ));
                foreach ($chunk as $hash) {
                    $r = $responses[$hash] ?? null;
                    $real[$hash] = $r instanceof \Illuminate\Http\Client\Response && $r->successful();
                }
            } catch (Throwable $e) {
                Log::info('Gravatar check failed', ['event_id' => $this->event->id, 'error' => $e->getMessage()]);
            }
        }

        return $real;
    }

    /** A Gravatar at the size the cards use, with no silhouette fallback (initials show instead). */
    public static function gravatar(string $hash): string
    {
        return "https://secure.gravatar.com/avatar/{$hash}?s=600&d=404";
    }
}
