<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free Steals (Explore → Free Steals): a small, hand-picked list of
 * WordPress plugins and tools that are already free. One list for every
 * event — no event_id. Admin → Free Steals keeps a larger collection; the
 * first FreeSteal::SHOWN switched-on ones (by order) are what attendees see.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('free_steals', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('description', 300);
            $table->string('maker', 120);
            $table->string('category', 80);
            $table->string('url', 500);
            $table->string('cta_label', 40)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('free_steals');
    }
};
