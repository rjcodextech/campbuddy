<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a discovery profile say who it belongs to: an attendee picks their own
 * name from the event's public attendee list, so the people they match with
 * see a real name, photo and links — and can find them in the room.
 *
 * Unique: one attendee-list entry can be claimed by one profile only, so
 * nobody can take over a name that's already been claimed. Null when the
 * attendee typed a name themselves or chose to stay anonymous.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discovery_profiles', function (Blueprint $table) {
            $table->foreignId('attendee_roster_id')->nullable()->after('event_id')
                ->unique()
                ->constrained('attendee_roster')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('discovery_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attendee_roster_id');
        });
    }
};
