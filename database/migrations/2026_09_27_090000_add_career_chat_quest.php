<?php

use Database\Seeders\DefaultQuestSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds the "Career Chat" quest (for students and career-switchers above
 * all) to installs that already have the default quests. The seeder is
 * idempotent — it only fills in what's missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new DefaultQuestSeeder)->run();
    }

    public function down(): void
    {
        // Data only — left in place.
    }
};
