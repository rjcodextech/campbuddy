<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moving an attendee's CampBuddy (Camp Card, discovery profile, saved
 * sessions, quests, people to meet…) from one device to another, with the
 * old device's approval (DeviceTransferController).
 *
 * The server only relays: the two devices agree a key between themselves
 * (ECDH), so `payload` is ciphertext the server can't read, and it's dropped
 * as soon as the new device has it. Nothing here names the attendee — the
 * public keys and statuses are all a row holds once a transfer is done, and
 * every row is deleted once `expires_at` passes (or with the profile).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discovery_profile_id')->constrained()->cascadeOnDelete();
            $table->string('transfer_id', 64)->unique();
            // pending → approved → completed, or declined / cancelled.
            $table->string('status', 16)->default('pending');
            // The new device: its public key, and the hash of the secret only it holds.
            $table->string('requester_key', 200);
            $table->string('requester_secret_hash', 64);
            $table->string('requester_label', 40)->nullable();
            // The old device, once it approves: its public key, the hash of its
            // own secret (to learn when the move is done), and the sealed data.
            $table->string('sender_key', 200)->nullable();
            $table->string('sender_secret_hash', 64)->nullable();
            $table->string('iv', 32)->nullable();
            $table->longText('payload')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->index(['discovery_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_transfers');
    }
};
