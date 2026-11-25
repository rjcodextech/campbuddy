<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_transfers', function (Blueprint $table) {
            // Active 6-digit PIN generated only on request; nullable unique guarantees
            // zero two live transfers across any users can ever share the same code.
            // Cleared back to null as soon as the transfer leaves "pending".
            $table->string('transfer_code', 6)->nullable()->unique()->after('transfer_id');
            $table->string('code_hash', 64)->nullable()->after('transfer_code');
            $table->unsignedTinyInteger('failed_code_attempts')->default(0)->after('code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('device_transfers', function (Blueprint $table) {
            $table->dropUnique(['transfer_code']);
            $table->dropColumn(['transfer_code', 'code_hash', 'failed_code_attempts']);
        });
    }
};
