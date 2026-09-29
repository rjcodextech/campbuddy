<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The shared media library other models pick images from: deals (Offers)
 * and Free Steals. Only an image nothing uses can be deleted (Admin →
 * Media Library), so a picker never surfaces a broken link.
 */
class MediaAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'disk',
        'path',
        'filename',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Deals using it as their logo: an event's own and the default ones. */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    /** Free Steals using it as their logo. */
    public function freeSteals(): HasMany
    {
        return $this->hasMany(FreeSteal::class);
    }

    public function scopeUsed(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->has('offers')->orWhereHas('freeSteals'));
    }

    public function scopeUnused(Builder $query): Builder
    {
        return $query->doesntHave('offers')->doesntHave('freeSteals');
    }

    public function isUsed(): bool
    {
        return $this->offers()->exists() || $this->freeSteals()->exists();
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public static function store(UploadedFile $file, ?int $uploadedBy, string $disk = 'public'): self
    {
        // Extension/MIME are derived from the file's actual sniffed
        // content, not the client-supplied originals — those are
        // trivially spoofable.
        $path = $file->storeAs(
            'media-library/'.now()->format('Y/m'),
            Str::uuid().'.'.($file->guessExtension() ?? 'bin'),
            $disk
        );

        if ($path === false) {
            throw new RuntimeException("Failed to store uploaded file \"{$file->getClientOriginalName()}\".");
        }

        return static::create([
            'disk' => $disk,
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $uploadedBy,
        ]);
    }
}
