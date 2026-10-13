<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Report" on a discovery profile (App Store 1.2 / Play UGC policy). One
        // report per reporting device and profile; the profile's public text is
        // copied in so the admin can still judge it after the profile is removed.
        Schema::create('discovery_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('discovery_id', 64);
            $table->string('reporter_device_id', 64);
            $table->string('reason', 20);
            $table->string('note', 300)->nullable();
            $table->json('snapshot')->nullable();
            $table->string('status', 12)->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['discovery_id', 'reporter_device_id']);
            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discovery_reports');
    }
};
