<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak audit append-only berantai hash & security events
 * (keamanan/11 SEC-LOG-09..13).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('seq');
            $table->uuid('id')->unique();
            $table->timestampTz('occurred_at', 6); // presisi mikrodetik: nilai ter-hash harus identik saat dibaca
            $table->uuid('actor_id')->nullable()->index();
            $table->string('actor_type', 16);
            $table->string('actor_role', 64)->nullable();
            $table->uuid('organization_id')->nullable()->index();
            $table->string('action', 100)->index();
            $table->string('subject_type', 100)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->jsonb('changes')->nullable();
            $table->text('reason')->nullable();
            // varchar (bukan inet) agar nilai yang di-hash identik saat dibaca ulang.
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('request_id', 32)->nullable();
            $table->char('prev_hash', 64);
            $table->char('hash', 64)->unique();
            $table->index(['subject_type', 'subject_id']);
        });
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_actor_type_check CHECK (actor_type IN ('user','system','api_client'))");

        Schema::create('security_events', function (Blueprint $table) {
            $table->bigIncrements('seq');
            $table->uuid('id')->unique();
            $table->timestampTz('occurred_at')->index();
            $table->string('type', 64)->index();
            $table->string('severity', 16);
            $table->uuid('user_id')->nullable()->index();
            $table->ipAddress('ip')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('request_id', 32)->nullable();
            $table->jsonb('details')->nullable();
        });
        DB::statement("ALTER TABLE security_events ADD CONSTRAINT security_events_severity_check CHECK (severity IN ('info','warning','high','critical'))");

        // Append-only: tolak UPDATE/DELETE bahkan bila hak tabel keliru diberikan.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Tabel % bersifat append-only', TG_TABLE_NAME;
            END;
            $$;

            CREATE TRIGGER audit_logs_append_only BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION reject_mutation();
            CREATE TRIGGER audit_logs_no_truncate BEFORE TRUNCATE ON audit_logs
                FOR EACH STATEMENT EXECUTE FUNCTION reject_mutation();
            CREATE TRIGGER security_events_append_only BEFORE UPDATE OR DELETE ON security_events
                FOR EACH ROW EXECUTE FUNCTION reject_mutation();

            REVOKE UPDATE, DELETE, TRUNCATE ON audit_logs FROM PUBLIC;
            REVOKE UPDATE, DELETE, TRUNCATE ON security_events FROM PUBLIC;
        SQL);

        // Cabut hak ubah/hapus dari peran aplikasi bila peran tersebut ada.
        DB::unprepared(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'stu_app') THEN
                    REVOKE UPDATE, DELETE, TRUNCATE ON audit_logs, security_events FROM stu_app;
                END IF;
            END $$;
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_truncate ON audit_logs;');
        Schema::dropIfExists('audit_logs');
        DB::unprepared('DROP FUNCTION IF EXISTS reject_mutation();');
    }
};
