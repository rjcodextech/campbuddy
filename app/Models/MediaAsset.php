<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A shared, permanently-retained media library other models (Offers,
 * and future ones) pick images from. Deliberately has no delete path —
 * once uploaded, a file stays even if nothing currently references it,
 * so a picker never surfaces a broken link.
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
