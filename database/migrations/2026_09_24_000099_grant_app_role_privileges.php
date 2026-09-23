<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Memberi peran DB aplikasi (koneksi `pgsql`) hak minimum atas tabel milik peran migrasi
 * (docs/05 §6, ADR-003). Berguna di hosting yang tidak menjalankan
 * docker/postgres/init-roles.sql (mis. cPanel): peran dibuat panel, hak diberikan di sini.
 *
 * Migrasi ini harus tetap menjadi migrasi TERAKHIR yang menyentuh hak; tabel baru
 * sesudahnya tercakup oleh ALTER DEFAULT PRIVILEGES.
 */
return new class extends Migration
{
    public function up(): void
    {
        $appRole = (string) config('database.connections.pgsql.username');
        $current = (string) DB::selectOne('select current_user as name')->name;

        if ($appRole === '' || $appRole === $current) {
            // Satu peran untuk migrasi & runtime tidak didukung: RLS dapat dilewati pemilik tabel.
            throw new RuntimeException('Jalankan migrasi dengan peran pemilik skema (--database=pgsql_migrator) yang berbeda dari peran aplikasi.');
        }

        $role = DB::selectOne('select rolsuper, rolbypassrls from pg_roles where rolname = ?', [$appRole]);
        if ($role === null) {
            throw new RuntimeException("Peran aplikasi {$appRole} belum ada.");
        }
        if ($role->rolsuper || $role->rolbypassrls) {
            throw new RuntimeException("Peran aplikasi {$appRole} tidak boleh SUPERUSER/BYPASSRLS.");
        }

        // Nama peran dikutip oleh PostgreSQL (format %I) — tidak ada SQL yang dirangkai dari string.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION stu_grant_app_role(app_role text) RETURNS void
            LANGUAGE plpgsql AS $$
            BEGIN
                EXECUTE format('GRANT USAGE ON SCHEMA public TO %I', app_role);
                EXECUTE format('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO %I', app_role);
                EXECUTE format('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO %I', app_role);
                EXECUTE format('GRANT EXECUTE ON ALL FUNCTIONS IN SCHEMA public TO %I', app_role);
                EXECUTE format('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO %I', app_role);
                EXECUTE format('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO %I', app_role);
                EXECUTE format('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT EXECUTE ON FUNCTIONS TO %I', app_role);
                EXECUTE format('REVOKE UPDATE, DELETE, TRUNCATE ON audit_logs, security_events FROM %I', app_role);
                EXECUTE format('REVOKE ALL ON migrations FROM %I', app_role);
                EXECUTE format('GRANT SELECT ON migrations TO %I', app_role);
            END;
            $$;
        SQL);

        DB::select('select stu_grant_app_role(?)', [$appRole]);
        DB::unprepared('DROP FUNCTION stu_grant_app_role(text)');
    }

    public function down(): void
    {
        // Hak tidak dicabut saat rollback agar aplikasi tetap dapat berjalan pada skema sebelumnya.
    }
};
