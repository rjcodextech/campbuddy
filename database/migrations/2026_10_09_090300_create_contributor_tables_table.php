<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contributor Day tables an admin or the event's managers enter: which team,
 * where (track / room, floor, table number) and who leads it. Shown on the
 * attendee app's Contribute tab; leads picked from the attendee list get the
 * Table Lead badge there (RosterRoles).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contributor_tables')) {
            return;
        }

        Schema::create('contributor_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('team', 40);
            $table->string('title', 80)->nullable();
            $table->string('track', 80)->nullable();
            $table->string('floor', 40)->nullable();
            $table->string('table_no', 20)->nullable();
            $table->json('leads')->nullable();
            $table->string('note', 300)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributor_tables');
    }
};
