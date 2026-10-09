<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Running late": delays an admin or the event's managers set while the event
 * is on (App\Support\ScheduleDelay) — minutes, the whole event or one track,
 * from a time on that day.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('events', 'schedule_delays')) {
            Schema::table('events', function (Blueprint $table) {
                $table->json('schedule_delays')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('events', 'schedule_delays')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('schedule_delays');
            });
        }
    }
};
