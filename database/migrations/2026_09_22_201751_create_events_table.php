<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 191)->unique();
            $table->string('display_name');
            $table->string('short_name', 60)->nullable();
            $table->string('source_site_url', 500);
            $table->string('primary_color', 7)->nullable();
            $table->string('accent_color', 7)->nullable();
            $table->string('logo_path', 500)->nullable();
            $table->string('favicon_path', 500)->nullable();
            $table->enum('status', ['draft', 'approved', 'active', 'archived'])->default('draft');
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['status', 'is_visible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
