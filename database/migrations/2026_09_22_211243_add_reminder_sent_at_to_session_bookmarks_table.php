<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_bookmarks', function (Blueprint $table) {
            // Prevents the reminder job (§3.5 N1) from sending the same
            // push twice — set once a send succeeds for this bookmark.
            $table->timestamp('reminder_sent_at')->nullable()->after('reminder_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('session_bookmarks', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
