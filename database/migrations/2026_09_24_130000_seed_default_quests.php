<?php

use Database\Seeders\DefaultQuestSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * The cross-event "Things to do" quests (DefaultQuestSeeder) were only
 * created by running that seeder by hand — a step no install doc mentioned,
 * so a fresh install showed an empty Quest tab. Seeding them as part of
 * `migrate` means every install has them. The seeder is idempotent (it
 * upserts by title), so running it again later is harmless.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new DefaultQuestSeeder)->run();
    }

    public function down(): void
    {
        // Data only — left in place; admins may have come to rely on them.
    }
};
