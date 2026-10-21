<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live announcements from the people running a WordCamp ("Opening remarks
 * are starting", "Tea break", "Lunch is served"…), App\Support\EventBroadcasts:
 *   - event_device_visits: which devices (the app, the PWA, the website) had
 *     the event open, and when last — an announcement goes to the ones seen
 *     since three days before the event. Only the anonymous device id.
 *   - event_broadcasts: what was sent, by whom, to how many; the newest one
 *     also shows as a banner to anyone who opens the event soon after.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_device_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('device_id', 64);
            $table->string('platform', 10)->default('web');
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->unique(['event_id', 'device_id']);
            $table->index(['event_id', 'last_seen_at']);
        });

        Schema::create('event_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('title', 80);
            $table->string('body', 240);
            $table->string('tab', 20)->nullable();
            $table->string('sent_by', 120);
            $table->unsignedInteger('recipients')->default(0);
            $table->timestamp('show_until');
            $table->timestamps();
            $table->index(['event_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_broadcasts');
        Schema::dropIfExists('event_device_visits');
    }
};
