<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each WordCamp's own time zone: what its session times mean, what "a day"
 * of the event is, and when the discovery chat opens and closes. Read from
 * the WordCamp site itself; an admin can set it (timezone_locked), and a
 * fetch then never overwrites it.
 *
 * An IANA name ("Asia/Kolkata") where the site has one, or a fixed offset
 * ("+05:30") for a site configured as "UTC+5.5".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('timezone', 64)->nullable()->after('ends_on');
            $table->boolean('timezone_locked')->default(false)->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'timezone_locked']);
        });
    }
};
