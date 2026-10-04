<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pemangkasan retensi event keamanan: trigger append-only tetap menolak UPDATE/DELETE untuk semua peran,
 * kecuali DELETE oleh pemilik tabel (peran migrator) di dalam transaksi yang menyetel GUC
 * `app.retention_prune = on` (lokal transaksi). Peran aplikasi tetap tanpa hak DELETE, sehingga
 * GUC yang disetel dari aplikasi tidak berpengaruh. Tanpa ini, retensi tidak pernah dapat berjalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE owner_name text;
            BEGIN
                IF TG_TABLE_NAME = 'security_events' AND TG_OP = 'DELETE'
                   AND current_setting('app.retention_prune', true) = 'on' THEN
                    SELECT tableowner INTO owner_name FROM pg_tables WHERE schemaname = TG_TABLE_SCHEMA AND tablename = TG_TABLE_NAME;
                    IF owner_name = current_user THEN
                        RETURN OLD;
                    END IF;
                END IF;
                RAISE EXCEPTION 'Tabel % bersifat append-only', TG_TABLE_NAME;
            END;
            $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Tabel % bersifat append-only', TG_TABLE_NAME;
            END;
            $$;
        SQL);
    }
};
