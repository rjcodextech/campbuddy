<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quest extends Model
{
    use HasFactory;

    /**
     * The pre-trip checklist every event starts with, in display order.
     * Each event gets its own copies (Event::seedDefaultChecklist), so an
     * admin can rename, reorder, deactivate or delete them per event.
     *
     * None of these may reuse a title from quest.js's THINGS_TO_DO_META —
     * that lookup is what routes a quest to the rich "Things to do" cards
     * instead of this plain Checklist.
     */
    public const DEFAULT_CHECKLIST = [
        'Save venue directions',
        'Confirm your WordCamp ticket',
        'Register for Contributor Day if attending',
        'Create / check your WordPress.org account',
        'Join Make WordPress Slack',
        'Pack laptop + charger',
        'Add your details to Camp Card',
        "Pick a few sessions you don't want to miss",
        'Prepare a 15-second introduction',
    ];

    protected $fillable = [
        'event_id',
        'source',
        'title',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
