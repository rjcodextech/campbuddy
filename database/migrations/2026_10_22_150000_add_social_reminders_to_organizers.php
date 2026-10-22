<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * "Remind me on my phone when a post is due" (Social media studio): each admin and
 * event manager chooses it for themselves; off until they turn it on.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'event_managers'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'social_reminders')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->boolean('social_reminders')->default(false);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['users', 'event_managers'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'social_reminders')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('social_reminders');
                });
            }
        }
    }
};
