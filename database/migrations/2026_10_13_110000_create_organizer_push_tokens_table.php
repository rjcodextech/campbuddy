<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // FCM tokens of admins and event managers who signed in on the store app,
        // for alerts such as "someone reported a profile". Kept apart from the
        // attendees' native_push_tokens.
        Schema::create('organizer_push_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('role', 10);
            $table->unsignedBigInteger('user_id');
            $table->string('platform', 10);
            $table->string('token', 512)->unique();
            $table->string('app_version', 20)->nullable();
            $table->timestamps();

            $table->index(['role', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_push_tokens');
    }
};
