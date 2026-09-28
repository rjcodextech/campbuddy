<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dates someone typed by hand are theirs to keep (like timezone_locked): the
 * daily Event Information fetch then leaves them alone. Every other event
 * follows the dates its organizers register on central.wordcamp.org, so a
 * change there (or a discovered date that was a day off) reaches the app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('dates_locked')->default(false)->after('ends_on');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('dates_locked');
        });
    }
};
