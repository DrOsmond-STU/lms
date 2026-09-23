<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel pengguna (docs/05-desain-database.md §4.1). Dijalankan oleh peran stu_migrator.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS citext');

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('email', 254)->unique();
            $table->timestampTz('email_verified_at')->nullable();
            $table->text('phone_encrypted')->nullable();
            $table->string('phone_bidx', 64)->nullable()->unique();
            $table->string('password')->nullable();
            $table->timestampTz('password_changed_at')->nullable();
            $table->string('status', 32)->default('pending_verification');
            $table->uuid('primary_organization_id')->nullable()->index();
            $table->string('locale', 8)->default('id');
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->timestampTz('last_login_at')->nullable();
            $table->ipAddress('last_login_ip')->nullable();
            // Dinaikkan untuk mencabut SEMUA sesi pengguna (keamanan/02 SEC-AUTH-19).
            $table->unsignedInteger('session_version')->default(1);
            $table->rememberToken();
            $table->timestampsTz();
            $table->timestampTz('deactivated_at')->nullable();
        });

        DB::statement('ALTER TABLE users ALTER COLUMN email TYPE citext');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('pending_verification','active','suspended','deactivated','anonymized'))");

        // Token reset kata sandi disimpan ter-hash oleh broker Laravel (keamanan/02 SEC-AUTH-21).
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 254)->primary();
            $table->string('token');
            $table->timestampTz('created_at')->nullable();
        });
        DB::statement('ALTER TABLE password_reset_tokens ALTER COLUMN email TYPE citext');
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
