<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Kanal notifikasi (Fase F): preferensi, langganan push web, log pesan keluar, jejak pengingat. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->boolean('email_enabled')->default(true);
            $table->boolean('push_enabled')->default(true);
            $table->boolean('whatsapp_enabled')->default(false);
            $table->jsonb('muted_categories')->default('[]');
            $table->timestampsTz();
        });

        Schema::create('push_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->string('p256dh', 255);
            $table->string('auth', 64);
            $table->string('content_encoding', 16)->default('aes128gcm');
            $table->string('user_agent', 300)->nullable();
            $table->smallInteger('failures')->default(0);
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampsTz();
            $table->index('user_id');
        });

        Schema::create('outbound_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 16); // push | whatsapp
            $table->string('target', 120); // host endpoint / nomor tersamar
            $table->string('title', 160);
            $table->string('status', 16)->default('queued'); // queued | sent | failed
            $table->string('error', 500)->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['channel', 'status', 'created_at']);
        });
        DB::statement("ALTER TABLE outbound_messages ADD CONSTRAINT outbound_messages_channel_check CHECK (channel IN ('push','whatsapp'))");
        DB::statement("ALTER TABLE outbound_messages ADD CONSTRAINT outbound_messages_status_check CHECK (status IN ('queued','sent','failed'))");

        Schema::create('notification_reminders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('reference_id', 80);
            $table->timestampTz('sent_at')->useCurrent();
            $table->unique(['user_id', 'kind', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_reminders');
        Schema::dropIfExists('outbound_messages');
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('notification_preferences');
    }
};
