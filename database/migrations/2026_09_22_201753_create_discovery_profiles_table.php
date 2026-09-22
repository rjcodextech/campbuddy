<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discovery_profiles', function (Blueprint $table) {
            $table->id();
            // Public, non-guessable identifier (§3.4 M2) — safe to show to
            // every attendee, authorizes nothing by itself.
            $table->char('discovery_id', 64)->unique();
            // Only the hash is stored (§8.6) — the raw owner token is
            // returned once at creation and never persisted server-side.
            $table->char('owner_token_hash', 64);
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->json('fields');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('event_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discovery_profiles');
    }
};
