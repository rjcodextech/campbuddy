<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // A real gap in the original table list: the
            // roster-purge policy needs "the event's end" to compute
            // an expiry against, but no date field existed anywhere on
            // events. Nullable because a just-created draft event may not
            // have its dates set yet.
            $table->date('starts_on')->nullable()->after('source_site_url');
            $table->date('ends_on')->nullable()->after('starts_on');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['starts_on', 'ends_on']);
        });
    }
};
