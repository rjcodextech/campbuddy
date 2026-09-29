<?php

namespace App\Support;

use App\Models\MediaAsset;
use App\Models\Offer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Installs the default deals CampBuddy ships with (Admin → Default deals).
 * The deals themselves — every field — are written out in the migration that
 * installs them (2026_10_04_090100_install_default_india_deals), so a deploy's
 * `migrate` (or `campbuddy:doctor`) puts them in place; after that they are
 * the admin's to edit, switch off or remove — installing again never
 * overwrites or re-creates one (it is matched on its link).
 *
 * Logos ship in public/media/deals/ and are copied into the Media Library,
 * so an admin can swap them like any other logo. A missing logo file only
 * means that deal starts with its emoji.
 */
class DefaultDeals
{
    /**
     * Adds the given deals that aren't there yet, as default deals. Each is an
     * offers row plus `logo` (a file in public/media/deals/). Returns how many
     * were added.
     *
     * @param  list<array<string, mixed>>  $deals
     */
    public static function install(array $deals): int
    {
        $added = 0;
        $order = (int) (Offer::defaults()->max('sort_order') ?? 0);

        foreach ($deals as $deal) {
            if (Offer::defaults()->where('url', $deal['url'])->exists()) {
                continue;
            }

            $logo = $deal['logo'];
            unset($deal['logo']);
            if (isset($deal['lead_form'])) {
                $deal['lead_form'] = DealForm::normalize($deal['lead_form']);
            }

            Offer::create($deal + [
                'event_id' => null,
                'countries' => ['IN'],
                'media_asset_id' => self::logo($logo)?->id,
                'capture_leads' => false,
                'is_active' => true,
                'sort_order' => $order += 10,
            ]);
            $added++;
        }

        self::switchOffOlderCopies(array_column($deals, 'url'));

        return $added;
    }

    /**
     * An event that already had its own deal for one of these companies
     * (WordCamp Rajasthan had a plain "Hostinger, 20% off hosting" link)
     * would now show it twice. Its own copy is switched off, not deleted, and
     * only while it has no leads — an admin can switch it back on.
     */
    /** @param  list<string>  $urls  the installed deals' links */
    private static function switchOffOlderCopies(array $urls): int
    {
        $host = fn (?string $url) => preg_replace('/^www\./', '', strtolower((string) parse_url((string) $url, PHP_URL_HOST)));
        $defaults = Offer::defaults()->where('is_active', true)->get()
            ->filter(fn (Offer $deal) => in_array($deal->url, $urls, true));
        $switched = 0;

        foreach (Offer::whereNotNull('event_id')->where('is_active', true)->doesntHave('leads')->with('event')->get() as $own) {
            $sameCompany = $defaults->first(fn (Offer $deal) => $deal->event_id === null
                && $own->event
                && $deal->coversCountryOf($own->event)
                && ($host($deal->url) === $host($own->url) || $host('https://'.$deal->website) === $host($own->url)));

            if ($sameCompany) {
                $own->update(['is_active' => false]);
                $switched++;
            }
        }

        return $switched;
    }

    /** The shipped logo, copied into the Media Library once (null if the file isn't there). */
    private static function logo(string $file): ?MediaAsset
    {
        $source = public_path('media/deals/'.$file);
        $path = 'media-library/deals/'.$file;

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
