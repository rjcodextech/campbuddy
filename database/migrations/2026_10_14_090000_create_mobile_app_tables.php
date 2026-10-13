<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The native app's server-driven settings (App\Support\MobileAppConfig): one
        // draft at a time, any number of published versions (the newest is live).
        Schema::create('mobile_app_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->nullable()->unique();
            $table->string('status', 10)->index(); // draft | published | archived
            $table->longText('json');
            $table->unsignedSmallInteger('schema')->default(1);
            $table->string('note', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mobile_app_config_audit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20); // draft | publish | rollback | discard
            $table->unsignedInteger('version')->nullable();
            $table->string('section', 30)->nullable();
            $table->json('diff')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Sign-in tokens for admins and event managers using the native app.
        // Only a hash is stored; a token is shown once, at sign-in.
        Schema::create('app_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('role', 10); // admin | manager
            $table->unsignedBigInteger('user_id');
            $table->string('device_name', 80)->nullable();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['role', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_api_tokens');
        Schema::dropIfExists('mobile_app_config_audit');
        Schema::dropIfExists('mobile_app_configs');
    }
};
