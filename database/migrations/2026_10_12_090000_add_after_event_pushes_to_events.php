<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The after-event pushes (App\Jobs\SendAfterEventPushesJob): day 7 "save your
 * data" and day 15 "move or bring your data", each sent once per event; and a
 * log of every push sent about the event (when, how many phones) for the
 * admin's Notifications tab.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'save_data_sent_at')) {
                $table->timestamp('save_data_sent_at')->nullable();
            }
            if (! Schema::hasColumn('events', 'move_data_sent_at')) {
                $table->timestamp('move_data_sent_at')->nullable();
            }
            if (! Schema::hasColumn('events', 'push_log')) {
                $table->json('push_log')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            foreach (['save_data_sent_at', 'move_data_sent_at', 'push_log'] as $column) {
                if (Schema::hasColumn('events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
