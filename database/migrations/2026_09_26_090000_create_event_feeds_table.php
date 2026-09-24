<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An event's fetched lists — sessions, speakers, sponsors, organizers — kept
 * in the database for good, not only in the cache. The cache expires (14
 * days without a successful fetch) and can be flushed; this can't, so an
 * event never goes blank because the cron stopped or a cache was cleared.
 * The cache stays in front of it as the fast path (App\Support\EventData).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_feeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->longText('payload');
            $table->unsignedInteger('item_count')->default(0);
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['event_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_feeds');
    }
};
