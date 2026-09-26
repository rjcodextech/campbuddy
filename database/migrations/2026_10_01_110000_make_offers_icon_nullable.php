<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A deal's emoji is only the fallback for a deal with no logo — so choosing a
 * logo and clearing the emoji is normal. The column was NOT NULL, so saving
 * that (the form sends an empty emoji, which the framework turns into NULL)
 * failed with "Column 'icon' cannot be null". Now it may be empty: the card
 * shows the logo, or nothing but its title and description.
 *
 * The default stays the tag emoji for a deal created without one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->string('icon', 10)->nullable()->default('🏷')->change();
        });
    }

    public function down(): void
    {
        // Back to "always has an emoji": the empty ones get the default first.
        DB::table('offers')->whereNull('icon')->update(['icon' => '🏷']);

        Schema::table('offers', function (Blueprint $table) {
            $table->string('icon', 10)->nullable(false)->default('🏷')->change();
        });
    }
};
