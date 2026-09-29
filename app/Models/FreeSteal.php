<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A Free Steal on Explore → Free Steals: a WordPress plugin or tool that is
 * already free, picked by the CampBuddy team. The same list at every event.
 *
 * "Featured" is the team's own pick, never paid placement. A steal whose
 * maker is on an event's Attendees page (maker_links) is "made here" at
 * that event: it gets a badge and moves to the top (forEvent).
 */
class FreeSteal extends Model
{
    /** How many attendees see at a time: a curated shelf, not a directory. */
    public const SHOWN = 12;

    public const DEFAULT_CTA = 'Get it free';

    /** First category word → line icon (App\Support\LineIcons). */
    private const ICONS = [
        'ai' => 'sparkles',
        'developer tools' => 'wrench',
        'utilities' => 'wrench',
        'gutenberg' => 'columns',
        'security' => 'id-card',
        'media' => 'camera',
        'local development' => 'laptop',
        'content' => 'pen-tool',
        'publishing' => 'pen-tool',
        'woocommerce' => 'tag',
        'performance' => 'zap',
    ];

    protected $fillable = [
        'name',
        'description',
        'maker',
        'maker_links',
        'category',
        'url',
        'cta_label',
        'is_featured',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** @return Collection<int, self> What Explore shows right now. */
    public static function shown(): Collection
    {
        return self::where('is_active', true)->ordered()->limit(self::SHOWN)->get();
    }

    /**
     * What an event's Explore shows: shown(), with `made_here` set on each
     * and the made-here ones first. Which ones are made here is worked out
     * from the roster at most every 10 minutes, not on every page view.
     *
     * @return Collection<int, self>
     */
    public static function forEvent(Event $event): Collection
    {
        $steals = self::shown();
        $here = self::madeHereIds($event, $steals);

        return $steals
            ->each(fn (self $steal) => $steal->setAttribute('made_here', in_array($steal->id, $here, true)))
            ->sortBy(fn (self $steal) => $steal->made_here ? 0 : 1)
            ->values();
    }

    /**
     * @param  Collection<int, self>  $steals
     * @return list<int>
     */
    private static function madeHereIds(Event $event, Collection $steals): array
    {
        $withLinks = $steals->filter(fn (self $steal) => $steal->makerLinks() !== []);
        if ($withLinks->isEmpty()) {
            return [];
        }

        // Keyed on the steals themselves too, so an admin's edit counts at once.
        $key = 'free-steals-here:'.$event->id.':'.md5($withLinks->map(fn (self $s) => $s->id.'@'.$s->updated_at)->implode(','));

        return Cache::remember($key, now()->addMinutes(10), function () use ($event, $withLinks) {
            $attending = [];
            AttendeeRoster::where('event_id', $event->id)->where('is_suppressed', false)->whereNotNull('links')
                ->pluck('links')
                ->each(function ($links) use (&$attending) {
                    foreach ((array) $links as $link) {
                        if ($normal = self::normalizeLink((string) ($link['url'] ?? ''))) {
                            $attending[$normal] = true;
                        }
                    }
                });

            return $withLinks
                ->filter(fn (self $steal) => collect($steal->makerLinks())->contains(fn (string $link) => isset($attending[$link])))
                ->pluck('id')->values()->all();
        });
    }

    /** @return list<string> The maker's addresses, normalised (normalizeLink). */
    public function makerLinks(): array
    {
        return collect(preg_split('/\s+/', (string) $this->maker_links) ?: [])
            ->map(fn (string $link) => self::normalizeLink($link))
            ->filter()->unique()->values()->all();
    }

    /**
     * One address in a form two spellings of it share:
     * "https://www.X.com/Gaurav/" and "twitter.com/gaurav" → "twitter.com/gaurav".
     */
    public static function normalizeLink(string $url): ?string
    {
        $url = mb_strtolower(trim($url));
        if ($url === '') {
            return null;
        }
        if (! preg_match('#^[a-z][a-z0-9+.-]*://#', $url)) {
            $url = 'https://'.$url;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || ! str_contains($host, '.')) {
            return null;
        }

        $host = preg_replace('/^(www|m|mobile)\./', '', $host);
        $host = $host === 'x.com' ? 'twitter.com' : $host;

        return $host.rtrim((string) parse_url($url, PHP_URL_PATH), '/');
    }

    public function ctaLabel(): string
    {
        return $this->cta_label ?: self::DEFAULT_CTA;
    }

    /** "github.com", "wordpress.org" — where the button goes. */
    public function linkHost(): string
    {
        return preg_replace('/^www\./', '', (string) parse_url($this->url, PHP_URL_HOST));
    }

    public function icon(): string
    {
        foreach (explode('/', strtolower($this->category)) as $part) {
            if ($icon = self::ICONS[trim($part)] ?? null) {
                return $icon;
            }
        }

        return 'sparkles';
    }
}
