<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Whether an event's logo is light on see-through (a white wordmark), so the
 * box it sits in on the attendee pages can be dark instead of cream — where
 * it would all but vanish. Read once per file (cached by its path and time);
 * an SVG or a logo GD can't read counts as not light (nothing changes).
 */
class LogoTone
{
    /** The logo the event's hero shows (logo, else favicon) is light on see-through. */
    public static function heroIsLight(Event $event): bool
    {
        return self::isLight($event->logo_path ?: $event->favicon_path);
    }

    public static function isLight(?string $path): bool
    {
        if (! $path || ! function_exists('imagecreatefromstring')) {
            return false;
        }

        try {
            $disk = Storage::disk('public');
            if (! $disk->exists($path)) {
                return false;
            }
            $key = 'logo-tone:'.md5($path.'|'.$disk->lastModified($path));

            return Cache::rememberForever($key, fn () => self::measure((string) $disk->get($path)));
        } catch (Throwable) {
            return false;
        }
    }

    /** Light = mostly see-through, and what is drawn is near white. Pure, for tests. */
    public static function measure(string $bytes): bool
    {
        // Only what GD reads (PNG, WebP, GIF, JPEG): an SVG or anything else counts as not light.
        $known = str_starts_with($bytes, "PNG") || str_starts_with($bytes, "ÿØ") || str_starts_with($bytes, 'GIF8')
            || (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP');
        $src = $known ? @imagecreatefromstring($bytes) : false;
        if (! $src) {
            return false;
        }
        $size = 48;
        $img = imagecreatetruecolor($size, $size);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagecopyresampled($img, $src, 0, 0, 0, 0, $size, $size, imagesx($src), imagesy($src));

        $clear = 0;
        $drawn = 0;
        $lum = 0.0;
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $c = imagecolorat($img, $x, $y);
                $alpha = ($c >> 24) & 0x7F; // 0 opaque … 127 clear
                if ($alpha > 100) {
                    $clear++;

                    continue;
                }
                $drawn++;
                $lum += (0.2126 * (($c >> 16) & 0xFF) + 0.7152 * (($c >> 8) & 0xFF) + 0.0722 * ($c & 0xFF)) / 255;
            }
        }

        // A logo on its own white square isn't "light on see-through": a dark box would only frame the square.
        return $drawn > 0 && $clear / ($size * $size) > 0.15 && $lum / $drawn > 0.82;
    }
}
