<?php

use App\Support\DefaultDeals;
use Illuminate\Database\Migrations\Migration;

/**
 * Puts the default deals for WordCamps in India in place (DefaultDeals):
 * Ariham's free website health report, Hostinger, Automattic and Knit Pay.
 * Runs with the deploy's `migrate` / `campbuddy:doctor`; never adds a deal
 * twice, and never touches one an admin has since edited.
 *
 * Skipped in the test suite, whose tests start from an empty deal list.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        DefaultDeals::install();
    }

    public function down(): void
    {
        // Left in place: by now they may carry leads and an admin's edits.
    }
};
