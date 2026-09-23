<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Faktor MFA & kode pemulihan (keamanan/02 SEC-AUTH-13, SEC-AUTH-14).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_mfa_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->text('secret_encrypted')->nullable();
            $table->string('label', 100)->nullable();
            $table->bigInteger('last_totp_step')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE user_mfa_methods ADD CONSTRAINT user_mfa_methods_type_check CHECK (type IN ('totp','webauthn'))");

        Schema::create('mfa_recovery_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash', 64)->unique();
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfa_recovery_codes');
        Schema::dropIfExists('user_mfa_methods');
    }
};
