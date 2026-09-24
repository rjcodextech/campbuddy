<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Short messages between two discovery matches — deliberately tiny: CampBuddy
 * is a guide, not a chat app. Each person can send at most three, taking
 * turns (you can't send again until the other replies), and after that they
 * swap real contact details (Camp Card) to keep talking. The rules live in
 * DiscoveryWaveController; this only stores what was sent.
 *
 * A wave's single "where to meet" message becomes the first message here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discovery_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_profile_id')->constrained('discovery_profiles')->cascadeOnDelete();
            $table->foreignId('to_profile_id')->constrained('discovery_profiles')->cascadeOnDelete();
            $table->string('body', 140);
            $table->timestamp('created_at')->nullable();

            $table->index(['from_profile_id', 'to_profile_id']);
            $table->index(['to_profile_id', 'from_profile_id']);
        });

        DB::table('discovery_waves')->whereNotNull('message')->orderBy('id')->each(function ($wave) {
            DB::table('discovery_messages')->insert([
                'event_id' => $wave->event_id,
                'from_profile_id' => $wave->from_profile_id,
                'to_profile_id' => $wave->to_profile_id,
                'body' => $wave->message,
                'created_at' => $wave->created_at,
            ]);
        });

        Schema::table('discovery_waves', function (Blueprint $table) {
            $table->dropColumn('message');
        });
    }

    public function down(): void
    {
        Schema::table('discovery_waves', function (Blueprint $table) {
            $table->string('message', 140)->nullable();
        });

        Schema::dropIfExists('discovery_messages');
    }
};
