<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendee_roster', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('gravatar_url', 500)->nullable();
            $table->json('links')->nullable();
            // sha256 of name+links — the upsert key (§3.3 IN1), since the
            // Attendees page gives no stable upstream attendee ID.
            $table->char('content_hash', 64);
            $table->boolean('is_suppressed')->default(false);
            $table->timestamps();

            $table->unique(['event_id', 'content_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendee_roster');
    }
};
