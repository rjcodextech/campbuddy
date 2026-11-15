<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds high-frequency query indexes to events, shared camp cards, contributor tables,
     * free steal suggestions, media assets, and fetch logs.
     */
    public function up(): void
    {
        // 1. Events: Indexes for sorting, filtering, and retention window lookups
        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                if (Schema::hasColumn('events', 'starts_on') && Schema::hasColumn('events', 'ends_on')) {
                    $table->index(['starts_on', 'ends_on'], 'events_dates_idx');
                    $table->index(['status', 'is_visible', 'starts_on'], 'events_status_visibility_starts_idx');
                }
            });
        }

        // 2. Shared Camp Cards: Index for event-scoped alive queries
        if (Schema::hasTable('shared_camp_cards')) {
            Schema::table('shared_camp_cards', function (Blueprint $table) {
                if (Schema::hasColumn('shared_camp_cards', 'event_id') && Schema::hasColumn('shared_camp_cards', 'expires_at')) {
                    $table->index(['event_id', 'expires_at'], 'shared_camp_cards_event_expires_idx');
                }
            });
        }

        // 3. Contributor Tables: Index for event table ordering
        if (Schema::hasTable('contributor_tables')) {
            Schema::table('contributor_tables', function (Blueprint $table) {
                if (Schema::hasColumn('contributor_tables', 'event_id') && Schema::hasColumn('contributor_tables', 'sort_order')) {
                    $table->index(['event_id', 'sort_order'], 'contributor_tables_event_sort_idx');
                }
            });
        }

        // 4. Free Steal Suggestions: Index for event-scoped recent review
        if (Schema::hasTable('free_steal_suggestions')) {
            Schema::table('free_steal_suggestions', function (Blueprint $table) {
                if (Schema::hasColumn('free_steal_suggestions', 'event_id') && Schema::hasColumn('free_steal_suggestions', 'created_at')) {
                    $table->index(['event_id', 'created_at'], 'free_steal_suggestions_event_created_idx');
                }
            });
        }

        // 5. Media Assets: Index for uploader foreign key
        if (Schema::hasTable('media_assets')) {
            Schema::table('media_assets', function (Blueprint $table) {
                if (Schema::hasColumn('media_assets', 'uploaded_by')) {
                    $table->index('uploaded_by', 'media_assets_uploaded_by_idx');
                }
            });
        }

        // 6. Fetch Log: Composite index for max(id) subqueries in admin event index
        if (Schema::hasTable('fetch_log')) {
            Schema::table('fetch_log', function (Blueprint $table) {
                if (Schema::hasColumn('fetch_log', 'event_id') && Schema::hasColumn('fetch_log', 'job_type')) {
                    $table->index(['event_id', 'job_type', 'id'], 'fetch_log_event_job_id_idx');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('fetch_log')) {
            Schema::table('fetch_log', function (Blueprint $table) {
                $table->dropIndex('fetch_log_event_job_id_idx');
            });
        }

        if (Schema::hasTable('media_assets')) {
            Schema::table('media_assets', function (Blueprint $table) {
                $table->dropIndex('media_assets_uploaded_by_idx');
            });
        }

        if (Schema::hasTable('free_steal_suggestions')) {
            Schema::table('free_steal_suggestions', function (Blueprint $table) {
                $table->dropIndex('free_steal_suggestions_event_created_idx');
            });
        }

        if (Schema::hasTable('contributor_tables')) {
            Schema::table('contributor_tables', function (Blueprint $table) {
                $table->dropIndex('contributor_tables_event_sort_idx');
            });
        }

        if (Schema::hasTable('shared_camp_cards')) {
            Schema::table('shared_camp_cards', function (Blueprint $table) {
                $table->dropIndex('shared_camp_cards_event_expires_idx');
            });
        }

        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropIndex('events_dates_idx');
                $table->dropIndex('events_status_visibility_starts_idx');
            });
        }
    }
};
