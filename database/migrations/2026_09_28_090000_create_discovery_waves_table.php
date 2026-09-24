<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Wave" at a discovery match. A wave on its own reveals nothing; when both
 * people have waved at each other, each sees the other's name (the one they
 * gave with their wave — an anonymous profile stays anonymous to everyone
 * else) and optional "where to meet" message.
 *
 * Deleted with either profile, so leaving discovery (or its expiry) takes
 * every wave with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discovery_waves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_profile_id')->constrained('discovery_profiles')->cascadeOnDelete();
            $table->foreignId('to_profile_id')->constrained('discovery_profiles')->cascadeOnDelete();
            // Shown to the other person only if they wave back.
            $table->string('reveal_name', 60)->nullable();
            $table->string('message', 140)->nullable();
            $table->timestamps();

            $table->unique(['from_profile_id', 'to_profile_id']);
            $table->index('to_profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discovery_waves');
    }
};
