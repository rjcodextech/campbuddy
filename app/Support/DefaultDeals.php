<?php

namespace App\Support;

use App\Models\MediaAsset;
use App\Models\Offer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * The default deals CampBuddy ships with: shown at every WordCamp in India
 * (Admin → Default deals). Installed once by a migration, so a deploy's
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
    /** @return list<array<string, mixed>> */
    public static function catalogue(): array
    {
        return [
            [
                'logo' => 'ariham.png',
                'brand' => 'Ariham Technologies',
                'website' => 'ariham.com',
                'title' => 'Free website health report',
                'highlight' => 'FREE',
                'description' => 'Scan your website for performance, SEO, security, mobile, accessibility and more, and get a report with what to fix. Results in about 60 seconds.',
                'terms' => 'No signup needed.',
                'url' => 'https://ariham.com/site-scan/',
                'cta_label' => 'Scan my website',
                'opens_in_app' => false,
                'icon' => '🩺',
            ],
            [
                'logo' => 'hostinger.png',
                'brand' => 'Hostinger',
                'website' => 'hostinger.com',
                'title' => '20% off your first plan',
                'highlight' => '20% OFF',
                'description' => 'Web hosting, WordPress hosting and domains. Open the deal through this link and the discount is already applied when you pick a plan.',
                'terms' => 'On your first plan bought through this link. Prices are shown without GST.',
                'url' => 'https://www.hostinger.com/in?REFERRALCODE=1RJCODEX35',
                'cta_label' => 'Claim 20% off',
                'opens_in_app' => false,
                'icon' => '🌐',
            ],
            [
                'logo' => 'wordpress-com.png',
                'brand' => 'Automattic',
                'website' => 'wordpress.com',
                'title' => '69% off select WordPress.com plans',
                'highlight' => '69% OFF',
                'description' => 'Fast, secure WordPress.com hosting with free expert site migration. From Automattic, the company behind WordPress.com, Pressable, WooCommerce and Jetpack.',
                'terms' => 'On select plans. The current offer and prices are shown on the next page.',
                'url' => 'https://automattic.pxf.io/gOzQ4B',
                'cta_label' => 'See the offer',
                'opens_in_app' => false,
                'icon' => '🏷',
            ],
            [
                'logo' => 'knit-pay.png',
                'brand' => 'Knit Pay',
                'website' => 'knitpay.org',
                'title' => '100 free transactions a month for 6 months',
                'highlight' => '100 FREE / MONTH',
                'description' => 'Take payments on your WordPress site through Indian payment gateways and UPI with Knit Pay. Fill in the short form and the Knit Pay team sets up your special plan.',
                'terms' => '100 transactions free every month for the next 6 months. Use the email of your RapidAPI account.',
                'url' => 'https://www.knitpay.org/',
                'cta_label' => 'Claim the plan',
                'opens_in_app' => true,
                'icon' => '💳',
                'capture_leads' => true,
                'lead_form' => [
                    'intro' => 'A few details so Knit Pay can set up your plan. They go only to Knit Pay.',
                    'fields' => [
                        'name' => ['mode' => 'optional', 'label' => 'Name'],
                        'company' => ['mode' => 'optional', 'label' => 'Company name'],
                        'email' => ['mode' => 'required', 'label' => 'Registered email at RapidAPI', 'hint' => 'The email of your RapidAPI account, so the plan reaches the right account.'],
                        'mobile' => ['mode' => 'optional', 'label' => 'Phone number', 'hint' => 'Recommended, so the Knit Pay team can reach you quickly.'],
                    ],
                    'choices' => [
                        'mode' => 'required',
                        'label' => 'Need a special plan for',
                        'multiple' => true,
                        'options' => ['Knit Pay - Pro', 'Knit Pay - UPI'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Adds the catalogue's deals that aren't there yet, as default deals for
     * India. Returns how many were added.
     */
    public static function install(): int
    {
        $added = 0;
        $order = (int) (Offer::defaults()->max('sort_order') ?? 0);

        foreach (self::catalogue() as $deal) {
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

        self::switchOffOlderCopies();

        return $added;
    }

    /**
     * An event that already had its own deal for one of these companies
     * (WordCamp Rajasthan had a plain "Hostinger, 20% off hosting" link)
     * would now show it twice. Its own copy is switched off, not deleted, and
     * only while it has no leads — an admin can switch it back on.
     */
    private static function switchOffOlderCopies(): int
    {
        $host = fn (?string $url) => preg_replace('/^www\./', '', strtolower((string) parse_url((string) $url, PHP_URL_HOST)));
        $defaults = Offer::defaults()->where('is_active', true)->get()
            ->filter(fn (Offer $deal) => in_array($deal->url, array_column(self::catalogue(), 'url'), true));
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
