<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roles an admin or event manager set by hand on the attendee list
 * (RosterMarks): "add" a role (media partner, sponsor, table lead…) or
 * "remove" one found automatically by mistake. Kept by the person's Gravatar
 * hash or name — not the list row's id — so they survive the nightly
 * re-import, which can replace rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('roster_marks')) {
            return;
        }

        Schema::create('roster_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('person_key', 100);
            $table->string('role', 30);
            $table->string('action', 10);
            $table->string('set_by', 120)->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'person_key', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roster_marks');
    }
};
