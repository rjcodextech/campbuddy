<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetch_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20);
            // Distinguishes which §5.2 job wrote this row (§7).
            $table->string('job_type', 30);
            $table->string('status', 10);
            $table->string('message', 500)->nullable();
            $table->dateTime('fetched_at');

            $table->index(['source', 'fetched_at']);
            $table->index(['event_id', 'job_type', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetch_log');
    }
};
