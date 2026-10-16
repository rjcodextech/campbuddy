<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalDocument extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'meta_description',
        'content',
    ];

    /**
     * Retrieve document by slug, falling back to default configuration metadata if not yet created.
     */
    public static function forSlug(string $slug): ?self
    {
        return self::where('slug', $slug)->first();
    }

    /**
     * Default definitions for legal documents before any custom edits.
     *
     * @return array{title: string, meta_description: string, updated: string}
     */
    public static function defaultMeta(string $slug): array
    {
        $updated = config('campbuddy.legal.updated', '15 October 2026');

        if ($slug === 'privacy') {
            return [
                'title' => 'Privacy notice',
                'meta_description' => 'What personal data CampBuddy keeps, why, for how long, and how to see, correct or erase it. Written under India\'s Digital Personal Data Protection Act, 2023.',
                'updated' => $updated,
            ];
        }

        return [
            'title' => 'Terms of use',
            'meta_description' => 'The rules for using CampBuddy, the WordCamp companion app and website.',
            'updated' => $updated,
        ];
    }
}
