<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Performance index for attendee roster search and takedown
        Schema::table('attendee_roster', function (Blueprint $table) {
            $table->index(['event_id', 'is_suppressed', 'name'], 'attendee_roster_search_idx');
        });

        // 2. Performance index for discovery profile owner authentication
        Schema::table('discovery_profiles', function (Blueprint $table) {
            $table->index(['event_id', 'owner_token_hash'], 'discovery_profiles_auth_idx');
        });

        // 3. Performance index for session reminder dispatch
        Schema::table('session_bookmarks', function (Blueprint $table) {
            $table->index(['event_id', 'session_id', 'reminder_sent_at'], 'session_bookmarks_dispatch_idx');
        });

        // 4. DPDPA 2023 consent capture columns on offer leads
        Schema::table('offer_leads', function (Blueprint $table) {
            $table->timestamp('consented_at')->nullable()->after('choices');
            $table->string('consent_ip', 45)->nullable()->after('consented_at');
        });

        // 5. DPDPA 2023 audit log table for lawful consent compliance (Section 6)
        Schema::create('consent_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('subject_type', 64);
            $table->string('subject_identifier', 191);
            $table->string('purpose', 120);
            $table->string('notice_version', 32)->default('1.0');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('consented_at')->useCurrent();
            $table->timestamps();

            $table->index(['event_id', 'subject_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consent_logs');

        Schema::table('offer_leads', function (Blueprint $table) {
            $table->dropColumn(['consented_at', 'consent_ip']);
        });

        Schema::table('session_bookmarks', function (Blueprint $table) {
            $table->dropIndex('session_bookmarks_dispatch_idx');
        });

        Schema::table('discovery_profiles', function (Blueprint $table) {
            $table->dropIndex('discovery_profiles_auth_idx');
        });

        Schema::table('attendee_roster', function (Blueprint $table) {
            $table->dropIndex('attendee_roster_search_idx');
        });
    }
};
