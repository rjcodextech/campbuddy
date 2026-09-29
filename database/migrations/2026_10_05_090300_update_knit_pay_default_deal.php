<?php

use App\Models\Event;
use App\Models\Offer;
use App\Support\DataVersion;
use Illuminate\Database\Migrations\Migration;

/**
 * The Knit Pay default deal's new wording (owner, 29 Sep 2026): now "Knit Pay
 * Pro", with its three benefits on their own lines. A deal installed by
 * 2026_10_04_090100 before this is updated here — but only while it still
 * has the first wording, so an admin's own edit is never overwritten.
 * Its link, form, leads and highlight are left as they are.
 *
 * The values are written out here, not read from DefaultDeals, so this
 * migration does the same thing whenever it runs.
 */
return new class extends Migration
{
    private const URL = 'https://www.knitpay.org/';

    private const FIRST_DESCRIPTION = 'Take payments on your WordPress site through Indian payment gateways and UPI with Knit Pay. Fill in the short form and the Knit Pay team sets up your special plan.';

    public function up(): void
    {
        $updated = Offer::whereNull('event_id')
            ->where('url', self::URL)
            ->where('description', self::FIRST_DESCRIPTION)
            ->get()
            ->each(fn (Offer $deal) => $deal->update([
                'brand' => 'Knit Pay Pro',
                'title' => '100 free transactions every month for 6 months',
                'description' => "Take payments on your WordPress site through 500+ payment gateways and UPI with Knit Pay Pro and Knit Pay UPI. Fill in the short form, and the Knit Pay team will invite you to the special plan.\n\n"
                    ."1. Unlimited free transactions in the Knit Pay plugin for lifetime.\n"
                    ."2. 100 free transactions every month in the Knit Pay Pro plugin for 6 months.\n"
                    .'3. 200 free UPI payment requests every month in the Knit Pay UPI plugin for 6 months.',
                'terms' => 'Use the email of your RapidAPI account.',
            ]));

        // Open apps pick the new wording up at their next check.
        if ($updated->isNotEmpty()) {
            Event::whereIn('status', ['approved', 'active'])->pluck('id')->each(fn (int $id) => DataVersion::forget($id));
        }
    }

    public function down(): void
    {
        // Left as it is: the new wording is the right one.
    }
};
