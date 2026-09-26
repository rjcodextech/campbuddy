<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Event managers: people an admin lets edit a few sections of specific events.
 * They are a separate kind of account on purpose — not rows in `users` (every
 * user is an admin) — so a manager can never pass an admin check, whatever a
 * route or policy assumes. An admin creates them; nobody registers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_managers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 32);
            $table->string('password');
            // Off = can't sign in and is signed out on the next request; nothing is deleted.
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // Which events a manager may edit. Deleting either side removes the link.
        Schema::create('event_event_manager', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_manager_id')->constrained('event_managers')->cascadeOnDelete();
            $table->primary(['event_id', 'event_manager_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_event_manager');
        Schema::dropIfExists('event_managers');
    }
};
