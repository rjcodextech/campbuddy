<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'title',
        'description',
        'url',
        'icon',
        'media_asset_id',
        'sort_order',
        'is_active',
        'capture_leads',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capture_leads' => 'boolean',
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
}
