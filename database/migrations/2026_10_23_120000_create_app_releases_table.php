<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Android APK builds uploaded in Admin → Mobile app → App updates. The APK
        // app (not the Play build) checks the newest live one and updates itself.
        Schema::create('app_releases', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 10)->default('android');
            $table->string('package', 150);
            $table->unsignedInteger('version_code');
            $table->string('version_name', 40);
            $table->unsignedSmallInteger('min_sdk')->nullable();
            $table->string('file', 255);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->char('cert_sha256', 64);
            $table->text('notes_en')->nullable();
            $table->text('notes_hi')->nullable();
            // Phones older than a required release must update before they can use the app.
            $table->boolean('required')->default(false);
            $table->string('status', 12)->default('draft'); // draft | live | withdrawn
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('pushed_to')->nullable();
            $table->timestamps();

            $table->unique(['package', 'version_code']);
            $table->index(['package', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_releases');
    }
};
