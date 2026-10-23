<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One uploaded Android APK build (App\Support\AppReleases): a draft until an
 * admin publishes it, then offered to every APK install older than it.
 */
class AppRelease extends Model
{
    public const DRAFT = 'draft';

    public const LIVE = 'live';

    public const WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'platform', 'package', 'version_code', 'version_name', 'min_sdk', 'file', 'size', 'sha256', 'cert_sha256',
        'notes_en', 'notes_hi', 'required', 'status', 'uploaded_by', 'published_by', 'published_at', 'pushed_to',
    ];

    protected function casts(): array
    {
        return [
            'version_code' => 'integer',
            'min_sdk' => 'integer',
            'size' => 'integer',
            'required' => 'boolean',
            'published_at' => 'datetime',
            'pushed_to' => 'integer',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** The public, versioned download address (never changes, so it may be cached for ever). */
    public function downloadUrl(): string
    {
        return route('api.app.releases.download', ['package' => $this->package, 'code' => $this->version_code]);
    }

    public function fileName(): string
    {
        return 'CampBuddy-'.$this->version_name.'.apk';
    }
}
