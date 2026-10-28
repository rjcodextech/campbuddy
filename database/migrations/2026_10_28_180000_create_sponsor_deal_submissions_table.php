<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsor_deal_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('sponsor_name', 191);
            $table->string('brand', 120)->nullable();
            $table->string('website', 120)->nullable();
            $table->string('title', 120);
            $table->string('highlight', 40)->nullable();
            $table->text('description');
            $table->string('terms', 255)->nullable();
            $table->string('coupon_code', 60)->nullable();
            $table->string('url', 500);
            $table->string('cta_label', 40)->nullable();
            $table->string('icon', 10)->nullable()->default('🏷');
            $table->string('logo_url', 500)->nullable();

            // Submitter & authenticity fields
            $table->string('submitter_name', 191);
            $table->string('submitter_email', 191);
            $table->string('submitter_phone', 60)->nullable();
            $table->text('notes')->nullable();

            // Verification & status
            $table->string('status', 20)->default('pending');
            $table->foreignId('approved_offer_id')->nullable()->constrained('offers')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsor_deal_submissions');
    }
};
