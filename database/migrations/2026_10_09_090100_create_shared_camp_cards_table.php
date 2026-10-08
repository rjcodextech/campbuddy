<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Show my Camp Card on the attendee list" — an attendee's own choice to put
 * the fields shown on their Camp Card next to their name on the event's
 * attendee list. One card per list entry; only the owner token (kept on their
 * phone, hashed here) can change or remove it. Goes with the entry when the
 * entry leaves the list (cascade), and is hidden after the retention window.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shared_camp_cards')) {
            return;
        }

        Schema::create('shared_camp_cards', function (Blueprint $table) {
            $table->id();
            $table->string('share_id', 64)->unique();
            $table->char('owner_token_hash', 64);
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendee_roster_id')->unique()->constrained('attendee_roster')->cascadeOnDelete();
            $table->json('fields');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_camp_cards');
    }
};
