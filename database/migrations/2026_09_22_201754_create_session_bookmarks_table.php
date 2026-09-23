<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            // Client-generated identifier (not an account ID) that
            // scopes this device's bookmarks and reminder schedule.
            $table->string('device_id', 64);
            // The WordCamp site's own wp/v2 session post ID.
            $table->unsignedBigInteger('session_id');
            $table->boolean('reminder_enabled')->default(false);
            $table->timestamps();

            $table->unique(['event_id', 'device_id', 'session_id'], 'session_bookmarks_unique');
            $table->index('device_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_bookmarks');
    }
};
