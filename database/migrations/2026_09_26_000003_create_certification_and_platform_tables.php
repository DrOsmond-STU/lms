<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sertifikat & verifikasi (docs/05 §4.7, keamanan/07), persetujuan kedua generik
 * (maker–checker), notifikasi in-app, sesi & perangkat, profil peserta, pengaturan sistem,
 * dan persetujuan opsional (docs/05 §4.9–4.10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('category', 16);
            $table->foreignUuid('program_id')->nullable()->constrained()->cascadeOnDelete();
            $table->integer('version');
            $table->string('title_text', 120);
            $table->string('body_text', 500);
            $table->string('signatory_name', 120);
            $table->string('signatory_title', 120);
            $table->string('accent_color', 7)->default('#0e3a63');
            $table->boolean('is_active')->default(false);
            $table->timestampTz('used_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE certificate_templates ADD CONSTRAINT certificate_templates_category_check CHECK (category IN ('international','bnsp'))");
        DB::statement("ALTER TABLE certificate_templates ADD CONSTRAINT certificate_templates_color_check CHECK (accent_color ~ '^#[0-9a-f]{6}$')");
        DB::statement('CREATE UNIQUE INDEX certificate_templates_one_active ON certificate_templates (category, coalesce(program_id, \'00000000-0000-0000-0000-000000000000\'::uuid)) WHERE is_active');

        Schema::create('certificate_sequences', function (Blueprint $table) {
            $table->foreignUuid('program_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('year');
            $table->integer('last_value')->default(0);
            $table->primary(['program_id', 'year']);
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->foreignUuid('enrollment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('program_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('template_id')->constrained('certificate_templates')->restrictOnDelete();
            $table->string('number', 80)->unique();
            $table->char('verification_code', 12)->unique();
            $table->string('holder_name', 120);
            $table->string('holder_name_masked', 120);
            $table->string('program_name', 200);
            $table->string('provider_name', 200);
            $table->string('category', 16);
            $table->timestampTz('issued_at');
            $table->date('valid_until')->nullable();
            $table->string('status', 20);
            $table->string('pdf_storage_key', 255)->nullable();
            $table->char('pdf_sha256', 64)->nullable();
            $table->string('signature_cert_fingerprint', 64)->nullable();
            $table->timestampTz('signed_at')->nullable();
            $table->foreignUuid('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('expiry_reminded_at')->nullable();
            $table->timestampsTz();
            $table->index(['program_id', 'status']);
            $table->index(['organization_id', 'status']);
        });
        DB::statement("ALTER TABLE certificates ADD CONSTRAINT certificates_status_check CHECK (status IN ('generating','generation_failed','active','revoked','superseded'))");
        DB::statement('ALTER TABLE certificates ADD CONSTRAINT certificates_approver_check CHECK (approved_by <> user_id)');
        DB::statement("ALTER TABLE certificates ADD CONSTRAINT certificates_code_check CHECK (verification_code ~ '^[0-9A-HJKMNP-TV-Z]{12}$')");

        Schema::create('certificate_revocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('certificate_id')->constrained()->restrictOnDelete();
            $table->string('reason_code', 32);
            $table->string('reason_text', 500);
            $table->foreignUuid('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('revoked_at');
        });
        DB::statement("ALTER TABLE certificate_revocations ADD CONSTRAINT certificate_revocations_reason_check CHECK (reason_code IN ('integrity_violation','data_error','holder_request','other'))");
        DB::statement('ALTER TABLE certificate_revocations ADD CONSTRAINT certificate_revocations_sod_check CHECK (approved_by <> requested_by)');

        Schema::create('certificate_verification_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('certificate_id')->nullable();
            $table->string('lookup_type', 16);
            $table->string('result', 16);
            $table->string('ip_hash', 64);
            $table->string('user_agent_family', 40)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index('created_at');
        });
        DB::statement("ALTER TABLE certificate_verification_logs ADD CONSTRAINT cvl_lookup_check CHECK (lookup_type IN ('number','code','api','pdf_upload'))");
        DB::statement("ALTER TABLE certificate_verification_logs ADD CONSTRAINT cvl_result_check CHECK (result IN ('valid','expired','revoked','superseded','not_found'))");

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('action', 64);
            $table->string('subject_type', 64);
            $table->uuid('subject_id');
            $table->jsonb('payload');
            $table->string('reason', 500);
            $table->foreignUuid('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('requested_at');
            $table->foreignUuid('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->string('decision', 16)->nullable();
            $table->string('decision_reason', 500)->nullable();
            $table->timestampTz('expires_at');
            $table->index(['decision', 'action']);
        });
        DB::statement("ALTER TABLE approval_requests ADD CONSTRAINT approval_requests_decision_check CHECK (decision IS NULL OR decision IN ('approved','rejected'))");
        DB::statement('ALTER TABLE approval_requests ADD CONSTRAINT approval_requests_sod_check CHECK (decided_by IS NULL OR decided_by <> requested_by)');
        DB::statement('CREATE UNIQUE INDEX approval_requests_one_open ON approval_requests (action, subject_id) WHERE decision IS NULL');

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 32);
            $table->string('title', 160);
            $table->string('body', 500);
            $table->string('action_url', 255)->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['user_id', 'read_at']);
        });
        DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_action_url_check CHECK (action_url IS NULL OR action_url ~ '^/[^/]')");

        Schema::create('user_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_hash', 64)->unique();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('device_label', 120);
            $table->timestampTz('created_at');
            $table->timestampTz('last_activity_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->string('revoke_reason', 64)->nullable();
            $table->index(['user_id', 'revoked_at']);
        });

        Schema::create('participant_profiles', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('participant_number', 40)->nullable();
            $table->string('study_program', 120)->nullable();
            $table->smallInteger('semester')->nullable();
            $table->string('department', 120)->nullable();
            $table->string('source', 16)->default('self');
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE participant_profiles ADD CONSTRAINT participant_profiles_semester_check CHECK (semester IS NULL OR semester BETWEEN 1 AND 14)');
        DB::statement("ALTER TABLE participant_profiles ADD CONSTRAINT participant_profiles_source_check CHECK (source IN ('self','admin','import','sso','api'))");

        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->jsonb('value');
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('updated_at')->nullable();
        });

        // Persetujuan opsional (FR-PRV-001): dapat ditarik kapan saja.
        DB::statement('ALTER TABLE consents DROP CONSTRAINT consents_document_check');
        DB::statement("ALTER TABLE consents ADD CONSTRAINT consents_document_check CHECK (document IN ('terms','privacy','marketing','whatsapp','public_leaderboard'))");
        DB::statement('ALTER TABLE consents DROP CONSTRAINT consents_channel_check');
        DB::statement("ALTER TABLE consents ADD CONSTRAINT consents_channel_check CHECK (channel IN ('registration','invitation','reconsent','preferences'))");
        Schema::table('consents', function (Blueprint $table) {
            $table->timestampTz('withdrawn_at')->nullable();
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE certificates ENABLE ROW LEVEL SECURITY;
            ALTER TABLE certificates FORCE ROW LEVEL SECURITY;
            CREATE POLICY tenant_isolation ON certificates
                USING (app_is_platform_staff() OR user_id = app_user_id() OR organization_id = ANY (app_org_ids()))
                WITH CHECK (app_is_platform_staff());
        SQL);
    }

    public function down(): void
    {
        Schema::table('consents', fn (Blueprint $table) => $table->dropColumn('withdrawn_at'));
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('participant_profiles');
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('certificate_verification_logs');
        Schema::dropIfExists('certificate_revocations');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('certificate_sequences');
        Schema::dropIfExists('certificate_templates');
    }
};
