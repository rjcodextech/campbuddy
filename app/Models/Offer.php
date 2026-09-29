<?php

namespace App\Models;

use App\Support\DealForm;
use App\Support\EventCountry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A deal on Explore → Deals. Either one event's own (event_id set), or a
 * **default deal** (event_id null) shown at every event in its countries —
 * now and in future — unless that event hides it (hiddenAtEvents).
 */
class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'brand',
        'website',
        'title',
        'highlight',
        'description',
        'terms',
        'coupon_code',
        'url',
        'cta_label',
        'opens_in_app',
        'countries',
        'icon',
        'media_asset_id',
        'sort_order',
        'is_active',
        'capture_leads',
        'lead_form',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capture_leads' => 'boolean',
        'opens_in_app' => 'boolean',
        'countries' => 'array',
        'lead_form' => 'array',
    ];

    protected $attributes = [
        'opens_in_app' => true,
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(OfferLead::class);
    }

    /** Events that chose not to show this default deal. */
    public function hiddenAtEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'offer_event_hidden')->withTimestamps();
    }

    public function scopeDefaults(Builder $query): Builder
    {
        return $query->whereNull('event_id');
    }

    public function isDefault(): bool
    {
        return $this->event_id === null;
    }

    /** Whether a default deal's countries include this event's (no countries = everywhere). */
    public function coversCountryOf(Event $event): bool
    {
        $countries = array_filter((array) $this->countries);

        return $countries === [] || in_array(EventCountry::code($event), $countries, true);
    }

    /**
     * Whether attendees of this event may see (and send a lead for) this deal:
     * the event's own, or a default deal for its country that it hasn't hidden.
     */
    public function isShownAt(Event $event): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (! $this->isDefault()) {
            return $this->event_id === $event->id;
        }

        return $this->coversCountryOf($event)
            && ! $this->hiddenAtEvents()->whereKey($event->id)->exists();
    }

    /**
     * Every deal attendees of this event see: its own first (they are the
     * event's sponsors), then the default deals for its country, each in
     * its own order.
     *
     * @return Collection<int, Offer>
     */
    public static function shownAt(Event $event): Collection
    {
        $own = $event->offers()->with('mediaAsset')->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();

        $defaults = static::defaults()
            ->with('mediaAsset')
            ->where('is_active', true)
            ->whereDoesntHave('hiddenAtEvents', fn ($q) => $q->whereKey($event->id))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (Offer $offer) => $offer->coversCountryOf($event));

        return $own->concat($defaults)->values();
    }

    /** The default deals that cover this event's country, hidden or not (for the admin). */
    public static function defaultsFor(Event $event): Collection
    {
        return static::defaults()
            ->with('mediaAsset')
            ->withExists(['hiddenAtEvents as hidden_here' => fn ($q) => $q->whereKey($event->id)])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (Offer $offer) => $offer->coversCountryOf($event))
            ->values();
    }

    /** The contact form, every gap filled (DealForm). */
    public function leadForm(): array
    {
        return DealForm::normalize($this->lead_form);
    }

    /** The name the card leads with: the company, or (older deals) the title. */
    public function displayName(): string
    {
        return $this->brand ?: $this->title;
    }

    /** The website shown under the name: as typed, or the link's own domain. */
    public function displayWebsite(): ?string
    {
        $site = $this->website ?: parse_url((string) $this->url, PHP_URL_HOST);

        return $site ? preg_replace('/^(https?:\/\/)?(www\.)?/i', '', rtrim($site, '/')) : null;
    }

    /** "IN, BD" for the admin; "All countries" when none. */
    public function countriesLabel(): string
    {
        $countries = array_filter((array) $this->countries);

        return $countries === [] ? 'All countries' : implode(', ', $countries);
    }
}
