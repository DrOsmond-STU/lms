<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Siklus hidup akun Fase 1 (docs/05 §4.1, docs/07 §7): token sekali pakai (OTP registrasi,
 * undangan), persetujuan S&K/Kebijakan Privasi berversi, dan metode verifikasi domain.
 * Hak peran aplikasi tercakup ALTER DEFAULT PRIVILEGES (migrasi 000099).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('one_time_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 32);
            // HMAC-SHA256 + pepper (K4) — nilai mentah token/OTP tidak pernah disimpan.
            $table->string('token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->smallInteger('attempts')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['user_id', 'purpose']);
        });
        DB::statement("ALTER TABLE one_time_tokens ADD CONSTRAINT one_time_tokens_purpose_check CHECK (purpose IN ('email_verification','invitation','email_change'))");
        DB::statement('ALTER TABLE one_time_tokens ADD CONSTRAINT one_time_tokens_attempts_check CHECK (attempts >= 0)');

        Schema::create('consents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('document', 32);
            $table->string('version', 32);
            $table->timestampTz('accepted_at', 6);
            $table->string('channel', 16);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->index(['user_id', 'document']);
        });
        DB::statement("ALTER TABLE consents ADD CONSTRAINT consents_document_check CHECK (document IN ('terms','privacy'))");
        DB::statement("ALTER TABLE consents ADD CONSTRAINT consents_channel_check CHECK (channel IN ('registration','invitation','reconsent'))");

        Schema::table('organization_domains', function (Blueprint $table) {
            $table->string('method', 16)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
        DB::statement("ALTER TABLE organization_domains ADD CONSTRAINT organization_domains_method_check CHECK (method IS NULL OR method IN ('admin','dns'))");
    }

    public function down(): void
    {
        Schema::table('organization_domains', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('method');
        });
        Schema::dropIfExists('consents');
        Schema::dropIfExists('one_time_tokens');
    }
};
