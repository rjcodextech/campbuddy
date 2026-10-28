<?php

namespace App\Models;

use App\Support\EventData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A deal submitted by a sponsor via the unlisted submission form.
 * Stored in a temporary staging table for admin verification and review.
 * Approved deals are moved into the main offers table (as draft or active).
 */
class SponsorDealSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'sponsor_name',
        'brand',
        'website',
        'title',
        'highlight',
        'description',
        'terms',
        'coupon_code',
        'url',
        'cta_label',
        'icon',
        'logo_url',
        'logo_path',
        'submitter_name',
        'submitter_email',
        'submitter_phone',
        'notes',
        'status',
        'approved_offer_id',
        'ip_address',
        'user_agent',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function approvedOffer(): BelongsTo
    {
        return $this->belongsTo(Offer::class, 'approved_offer_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Checks if the submitter's email domain matches the sponsor's website or deal domain.
     * Useful security & authenticity heuristic for admins.
     */
    public function emailDomainMatches(): ?bool
    {
        $email = strtolower(trim((string) $this->submitter_email));
        if (! str_contains($email, '@')) {
            return null;
        }

        $emailDomain = substr(strrchr($email, '@'), 1);
        if (! $emailDomain) {
            return null;
        }

        // Generic public free email providers are never considered a domain match.
        $freeMailDomains = [
            'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com',
            'aol.com', 'mail.com', 'proton.me', 'protonmail.com', 'zoho.com',
        ];
        if (in_array($emailDomain, $freeMailDomains, true)) {
            return false;
        }

        $targetUrls = array_filter([$this->website, $this->url]);
        foreach ($targetUrls as $targetUrl) {
            $host = parse_url(Str::startsWith($targetUrl, ['http://', 'https://']) ? $targetUrl : "https://{$targetUrl}", PHP_URL_HOST);
            if ($host) {
                $host = preg_replace('/^www\./i', '', strtolower($host));
                if ($host === $emailDomain || Str::endsWith($emailDomain, ".{$host}") || Str::endsWith($host, ".{$emailDomain}")) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check whether this sponsor appears in the event's official ingested sponsor list.
     */
    public function isOfficialSponsor(): bool
    {
        if (! $this->event_id) {
            return false;
        }

        $sponsors = EventData::get($this->event_id, 'sponsors') ?? [];
        $submittedName = mb_strtolower(trim($this->sponsor_name));

        foreach ($sponsors as $sponsor) {
            $name = mb_strtolower(trim((string) ($sponsor['name'] ?? '')));
            if ($name !== '' && ($name === $submittedName || Str::contains($submittedName, $name) || Str::contains($name, $submittedName))) {
                return true;
            }
        }

        return false;
    }

    public function displayBrand(): string
    {
        return filled($this->brand) ? $this->brand : $this->sponsor_name;
    }
}
