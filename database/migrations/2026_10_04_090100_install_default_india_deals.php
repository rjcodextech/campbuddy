<?php

use App\Support\DefaultDeals;
use Illuminate\Database\Migrations\Migration;

/**
 * The default deals for WordCamps in India, every field written out here:
 * Ariham Technologies, Hostinger, Automattic and Knit Pay Pro. Runs with the
 * deploy's `migrate` / `campbuddy:doctor` (DefaultDeals::install): never adds
 * a deal twice (matched on its link), never touches one an admin has since
 * edited, and switches off an event's own older deal for the same company
 * while it has no leads. Logos ship in public/media/deals/.
 *
 * Skipped in the test suite, whose tests start from an empty deal list
 * (tests read deals() from this file).
 */
return new class extends Migration
{
    /** @return list<array<string, mixed>> */
    public function deals(): array
    {
        return [
            [
                'logo' => 'ariham.png',
                'icon' => '🩺',
                'brand' => 'Ariham Technologies',
                'website' => 'ariham.com',
                'title' => 'Free website health report',
                'highlight' => 'FREE',
                'description' => 'Scan your website for performance, SEO, security, mobile, accessibility and more, and get a report with what to fix. Results in about 60 seconds.',
                'terms' => 'No signup needed.',
                'coupon_code' => null,
                'url' => 'https://ariham.com/site-scan/',
                'cta_label' => 'Scan my website',
                'opens_in_app' => false,
                'countries' => ['IN'],
                'is_active' => true,
                'capture_leads' => false,
            ],
            [
                'logo' => 'hostinger.png',
                'icon' => '🌐',
                'brand' => 'Hostinger',
                'website' => 'hostinger.com',
                'title' => '20% off your first plan',
                'highlight' => '20% OFF',
                'description' => 'Web hosting, WordPress hosting and domains. Open the deal through this link and the discount is already applied when you pick a plan.',
                'terms' => 'On your first plan bought through this link. Prices are shown without GST.',
                'coupon_code' => null,
                // A referral link: it opens in a new tab so the referral keeps its credit.
                'url' => 'https://www.hostinger.com/in?REFERRALCODE=1RJCODEX35',
                'cta_label' => 'Claim 20% off',
                'opens_in_app' => false,
                'countries' => ['IN'],
                'is_active' => true,
                'capture_leads' => false,
            ],
            [
                'logo' => 'wordpress-com.png',
                'icon' => '🏷',
                'brand' => 'Automattic',
                'website' => 'wordpress.com',
                'title' => '69% off select WordPress.com plans',
                'highlight' => '69% OFF',
                'description' => 'Fast, secure WordPress.com hosting with free expert site migration. From Automattic, the company behind WordPress.com, Pressable, WooCommerce and Jetpack.',
                'terms' => 'On select plans. The current offer and prices are shown on the next page.',
                'coupon_code' => null,
                // An affiliate link: new tab, like Hostinger.
                'url' => 'https://automattic.pxf.io/gOzQ4B',
                'cta_label' => 'See the offer',
                'opens_in_app' => false,
                'countries' => ['IN'],
                'is_active' => true,
                'capture_leads' => false,
            ],
            [
                'logo' => 'knit-pay.png',
                'icon' => '💳',
                'brand' => 'Knit Pay Pro',
                'website' => 'knitpay.org',
                'title' => '100 free transactions every month for 6 months',
                'highlight' => '100 FREE / MONTH',
                // Line breaks show on the card (.deal-card__desc is pre-line).
                'description' => "Take payments on your WordPress site through 500+ payment gateways and UPI with Knit Pay Pro and Knit Pay UPI. Fill in the short form, and the Knit Pay team will invite you to the special plan.\n\n"
                    ."1. Unlimited free transactions in the Knit Pay plugin for lifetime.\n"
                    ."2. 100 free transactions every month in the Knit Pay Pro plugin for 6 months.\n"
                    .'3. 200 free UPI payment requests every month in the Knit Pay UPI plugin for 6 months.',
                'terms' => 'Use the email of your RapidAPI account.',
                'coupon_code' => null,
                'url' => 'https://www.knitpay.org/',
                'cta_label' => 'Claim the plan',
                'opens_in_app' => true,
                'countries' => ['IN'],
                'is_active' => true,
                // The contact form before the deal opens (DealForm).
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

    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        DefaultDeals::install($this->deals());
    }

    public function down(): void
    {
        // Left in place: by now they may carry leads and an admin's edits.
    }
};
