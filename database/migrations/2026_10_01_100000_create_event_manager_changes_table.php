<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What event managers changed, for the admin to read: who, which event, which
 * section, and a one-line summary (old → new for the few fields where that
 * matters). Kept for 90 days (App\Support\ManagerActivity prunes it as it
 * writes). The manager's name is copied so the entry still reads right after
 * the account is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_manager_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_manager_id')->nullable()->constrained('event_managers')->nullOnDelete();
            $table->string('manager_name');
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('section', 20);   // details | information | quests
            $table->string('action', 20);    // updated | added | removed
            $table->string('summary', 500);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event_id', 'created_at']);
            $table->index(['event_manager_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_manager_changes');
    }
};
