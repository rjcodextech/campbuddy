<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * A Free Steal on Explore → Free Steals: a WordPress plugin or tool that is
 * already free, picked by the CampBuddy team. The same list at every event.
 *
 * "Featured" is the team's own pick, never paid placement.
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
