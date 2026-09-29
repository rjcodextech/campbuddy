<?php

use App\Support\FreeSteals;
use Illuminate\Database\Migrations\Migration;

/**
 * Puts the first Free Steals in place (FreeSteals::catalogue, from
 * mockups/campbuddy-free-steals.csv). Runs with the deploy's `migrate` /
 * `campbuddy:doctor`; never adds one twice, and never touches one an admin
 * has since edited.
 *
 * Skipped in the test suite, whose tests start from an empty list.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        FreeSteals::install();
    }

    public function down(): void
    {
        // Left in place: by now an admin may have edited them.
    }
};
