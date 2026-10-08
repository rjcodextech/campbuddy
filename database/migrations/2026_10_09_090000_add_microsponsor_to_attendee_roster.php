<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Some Attendees pages list their microsponsors (individuals who bought a
 * sponsor ticket) in a separate block; the roster scraper marks them, and
 * the attendee list shows a Microsponsor badge (RosterRoles).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('attendee_roster', 'is_microsponsor')) {
            Schema::table('attendee_roster', function (Blueprint $table) {
                $table->boolean('is_microsponsor')->default(false)->after('links');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('attendee_roster', 'is_microsponsor')) {
            Schema::table('attendee_roster', function (Blueprint $table) {
                $table->dropColumn('is_microsponsor');
            });
        }
    }
};
