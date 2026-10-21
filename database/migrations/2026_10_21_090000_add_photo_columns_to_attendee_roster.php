<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The best photo found for an attendee-list entry (App\Jobs\FillMissingPhotosJob):
 * their real Gravatar, the WordCamp site's photo of them as a speaker or
 * organizer, or their WordPress.org profile picture — and when it was last
 * looked for, so the daily job only checks what is new or stale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendee_roster', function (Blueprint $table) {
            $table->string('photo_url', 500)->nullable()->after('gravatar_url');
            $table->string('photo_source', 20)->nullable()->after('photo_url');
            $table->timestamp('photo_checked_at')->nullable()->after('photo_source');
        });
    }

    public function down(): void
    {
        Schema::table('attendee_roster', function (Blueprint $table) {
            $table->dropColumn(['photo_url', 'photo_source', 'photo_checked_at']);
        });
    }
};
