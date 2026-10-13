<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Push tokens of the native app (FCM). Separate from push_subscriptions
        // (Web Push) so the website push path is untouched.
        Schema::create('native_push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('device_id', 64);
            $table->string('platform', 10);
            $table->string('token', 512)->unique();
            $table->string('app_version', 20)->nullable();
            $table->timestamps();

            $table->index(['event_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('native_push_tokens');
    }
};
