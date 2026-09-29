<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free Steals, part two:
 *
 *  - free_steals.maker_links: the maker's own addresses (WordPress.org
 *    profile, GitHub, X, LinkedIn, website), one per line. When one of them
 *    is on an event's Attendees page, that event's card says "Made by
 *    someone at this WordCamp" and moves to the top (FreeSteal::forEvent).
 *  - free_steal_suggestions: "Suggest a Free Steal" from the app. Nothing
 *    is published from here; an admin adds it as a Free Steal or dismisses it.
 */
return new class extends Migration
{
    public function up(): void
    {
        // New installs get the column from create_free_steals_table; this adds it
        // where that migration ran before the column was part of it.
        if (! Schema::hasColumn('free_steals', 'maker_links')) {
            Schema::table('free_steals', function (Blueprint $table) {
                $table->text('maker_links')->nullable()->after('maker');
            });
        }

        Schema::create('free_steal_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('url', 500);
            $table->string('maker', 120)->nullable();
            $table->string('why', 300)->nullable();
            $table->string('email', 191)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('free_steal_suggestions');

        // maker_links stays: create_free_steals_table's down() drops the table.
    }
};
