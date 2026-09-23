<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Organisasi (tenant) & keanggotaan. organization_members adalah tabel ber-tenant
 * dengan Row-Level Security (docs/05 §5, keamanan/03 SEC-AUTHZ-11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 200);
            $table->string('code', 8)->unique();
            $table->string('type', 16);
            $table->string('city', 100)->nullable();
            $table->string('accreditation', 16)->nullable();
            $table->string('industry', 120)->nullable();
            $table->string('status', 16)->default('active');
            $table->jsonb('settings')->default('{}');
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE organizations ADD CONSTRAINT organizations_code_check CHECK (code ~ '^[A-Z]{2,8}$')");
        DB::statement("ALTER TABLE organizations ADD CONSTRAINT organizations_type_check CHECK (type IN ('institution','corporate'))");
        DB::statement("ALTER TABLE organizations ADD CONSTRAINT organizations_status_check CHECK (status IN ('active','inactive'))");

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('primary_organization_id')->references('id')->on('organizations')->nullOnDelete();
        });

        Schema::create('organization_domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('domain', 253)->unique();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE organization_domains ALTER COLUMN domain TYPE citext');

        Schema::create('organization_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('pending');
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();
            $table->unique(['organization_id', 'user_id']);
        });
        DB::statement("ALTER TABLE organization_members ADD CONSTRAINT organization_members_status_check CHECK (status IN ('pending','active','rejected','removed'))");

        // Fungsi bantu konteks tenant. Variabel yang tidak disetel → NULL → kebijakan menolak.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION app_is_platform_staff() RETURNS boolean
                LANGUAGE sql STABLE AS $$ SELECT coalesce(current_setting('app.is_platform_staff', true), '') = 'on' $$;

            CREATE OR REPLACE FUNCTION app_user_id() RETURNS uuid
                LANGUAGE sql STABLE AS $$ SELECT nullif(current_setting('app.user_id', true), '')::uuid $$;

            CREATE OR REPLACE FUNCTION app_org_ids() RETURNS uuid[]
                LANGUAGE sql STABLE AS $$
                    SELECT coalesce(string_to_array(nullif(current_setting('app.org_ids', true), ''), ',')::uuid[], '{}'::uuid[])
                $$;

            ALTER TABLE organization_members ENABLE ROW LEVEL SECURITY;
            ALTER TABLE organization_members FORCE ROW LEVEL SECURITY;

            CREATE POLICY tenant_isolation ON organization_members
                USING (app_is_platform_staff() OR organization_id = ANY (app_org_ids()) OR user_id = app_user_id())
                WITH CHECK (app_is_platform_staff() OR organization_id = ANY (app_org_ids()));
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_members');
        Schema::dropIfExists('organization_domains');
        Schema::table('users', fn (Blueprint $table) => $table->dropForeign(['primary_organization_id']));
        Schema::dropIfExists('organizations');
        DB::unprepared('DROP FUNCTION IF EXISTS app_org_ids(); DROP FUNCTION IF EXISTS app_user_id(); DROP FUNCTION IF EXISTS app_is_platform_staff();');
    }
};
