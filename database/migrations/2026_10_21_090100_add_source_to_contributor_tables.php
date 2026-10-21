<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a Contributor Day table came from: typed in by an admin or manager
 * ("manual"), or read from the WordCamp site's Contributor Day page ("site",
 * App\Jobs\FetchContributorTablesJob). Only "site" tables are ever replaced by
 * the daily read; editing one makes it "manual", so a hand change is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contributor_tables') || Schema::hasColumn('contributor_tables', 'source')) {
            return;
        }

        Schema::table('contributor_tables', function (Blueprint $table) {
            $table->string('source', 10)->default('manual')->after('sort_order');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('contributor_tables', 'source')) {
            Schema::table('contributor_tables', fn (Blueprint $table) => $table->dropColumn('source'));
        }
    }
};
