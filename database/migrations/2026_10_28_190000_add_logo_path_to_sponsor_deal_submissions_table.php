<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsor_deal_submissions', function (Blueprint $table) {
            $table->string('logo_path', 500)->nullable()->after('logo_url');
        });
    }

    public function down(): void
    {
        Schema::table('sponsor_deal_submissions', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
