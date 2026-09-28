<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The country a WordCamp is in (ISO 3166-1 alpha-2, "IN"), for the picker's
 * country filter (App\Support\EventCountry). Nullable: an event with no
 * known country is still listed, and EventCountry falls back to the time
 * zone / venue line on its own, so an empty column never hides anything.
 *
 * Existing events are filled from their time zone here (offline, no
 * network); `php artisan campbuddy:countries` fills the rest from
 * central.wordcamp.org's venue country.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('country_code', 2)->nullable()->after('timezone');
        });

        DB::table('events')->whereNull('country_code')->whereNotNull('timezone')->orderBy('id')
            ->each(function ($event) {
                if (! str_contains((string) $event->timezone, '/')) {
                    return;
                }

                try {
                    $code = (new DateTimeZone($event->timezone))->getLocation()['country_code'] ?? null;
                } catch (Throwable) {
                    return;
                }

                if (is_string($code) && preg_match('/^[A-Z]{2}$/', $code)) {
                    DB::table('events')->where('id', $event->id)->update(['country_code' => $code]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('country_code');
        });
    }
};
