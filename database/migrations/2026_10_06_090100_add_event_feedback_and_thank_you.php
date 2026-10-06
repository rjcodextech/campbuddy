<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * After a WordCamp: the attendee's thank-you card asks for a 1–5 rating and an
 * optional comment (no name, one per device per event — the device ID is kept
 * only as a hash), and the day-3 thank-you push is sent once per event.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_feedback')) {
            Schema::create('event_feedback', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                $table->string('device_hash', 64);
                $table->unsignedTinyInteger('rating');
                $table->string('comment', 500)->nullable();
                $table->timestamps();

                $table->unique(['event_id', 'device_hash']);
            });
        }

        if (! Schema::hasColumn('events', 'thank_you_sent_at')) {
            Schema::table('events', function (Blueprint $table) {
                $table->timestamp('thank_you_sent_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_feedback');

        if (Schema::hasColumn('events', 'thank_you_sent_at')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('thank_you_sent_at');
            });
        }
    }
};
