<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Event Information: venue, wifi, registration
            // info, code of conduct, emergency contact, etc. — a flexible
            // set of optional organizer-supplied text fields, each simply
            // omitted from display when empty, so a JSON blob
            // fits better than a wide table of mostly-null columns.
            $table->json('info')->nullable()->after('favicon_path');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('info');
        });
    }
};
