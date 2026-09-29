<?php

namespace App\Support;

use App\Models\FreeSteal;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Installs the Free Steals CampBuddy ships with (Admin → Free Steals). The
 * steals themselves — every field, maker links included — are written out
 * in the migration that installs them (2026_10_05_090100_install_free_steals).
 * After that they are the admin's to edit, switch off or remove: installing
 * again never overwrites or re-creates one (it is matched on its link).
 *
 * Logos ship in public/media/free-steals/ and are copied into the Media
 * Library (logos()), so an admin can swap them like any other logo.
 */
class FreeSteals
{
    /**
     * Adds the given steals that aren't there yet, in the order given (after
     * any already there). Returns how many were added.
     *
     * @param  list<array<string, mixed>>  $steals  free_steals rows
     */
    public static function install(array $steals): int
    {
        $added = 0;
        $order = (int) (FreeSteal::max('sort_order') ?? 0);

        foreach ($steals as $steal) {
            if (FreeSteal::where('url', $steal['url'])->exists()) {
                continue;
            }

            FreeSteal::create($steal + ['is_active' => true, 'is_featured' => false, 'sort_order' => $order += 10]);
            $added++;
        }

        return $added;
    }

    /**
     * Gives each steal found by its link the shipped logo, when it has none
     * yet: a logo an admin picked stays. Returns how many got one.
     *
     * @param  array<string, string>  $logos  link => file in public/media/free-steals/
     */
    public static function logos(array $logos): int
    {
        $set = 0;

        foreach ($logos as $url => $file) {
            $steal = FreeSteal::where('url', $url)->whereNull('media_asset_id')->first();
            if ($steal && ($asset = self::logo($file))) {
                $steal->update(['media_asset_id' => $asset->id]);
                $set++;
            }
        }

        return $set;
    }

    /** The shipped logo, copied into the Media Library once (null if the file isn't there). */
    private static function logo(string $file): ?MediaAsset
    {
        $source = public_path('media/free-steals/'.$file);
        $path = 'media-library/free-steals/'.$file;

        if ($asset = MediaAsset::where('disk', 'public')->where('path', $path)->first()) {
            return $asset;
        }

        if (! File::exists($source)) {
            return null;
        }

        Storage::disk('public')->put($path, File::get($source));

        return MediaAsset::create([
            'disk' => 'public',
            'path' => $path,
            'filename' => $file,
            'mime_type' => File::mimeType($source) ?: 'image/png',
            'size' => File::size($source),
            'uploaded_by' => null,
        ]);
    }
}
