<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog program, kelas/batch, trainer pengampu, struktur konten, dan aset media
 * (docs/05 §4.3, FR-CAT-001..004, FR-CLS-001..002, FR-CNT-001..004).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('category', 16);
            $table->string('name', 200);
            $table->string('slug', 220)->unique();
            $table->string('provider_name', 200);
            $table->string('scheme_code', 60)->nullable();
            $table->string('short_code', 16)->unique();
            $table->string('level', 32)->nullable();
            $table->smallInteger('duration_hours')->default(0);
            $table->string('language', 8)->default('id');
            $table->string('default_mode', 16)->default('online');
            $table->text('description_md')->nullable();
            $table->text('description_html')->nullable();
            $table->decimal('passing_score', 5, 2)->default(70);
            $table->smallInteger('certificate_validity_months')->default(36);
            $table->bigInteger('price')->default(0);
            $table->string('status', 16)->default('draft');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
            $table->index('status');
        });
        DB::statement("ALTER TABLE programs ADD CONSTRAINT programs_category_check CHECK (category IN ('international','bnsp'))");
        DB::statement("ALTER TABLE programs ADD CONSTRAINT programs_status_check CHECK (status IN ('draft','in_review','published','archived'))");
        DB::statement("ALTER TABLE programs ADD CONSTRAINT programs_mode_check CHECK (default_mode IN ('online','offline','hybrid'))");
        DB::statement('ALTER TABLE programs ADD CONSTRAINT programs_passing_score_check CHECK (passing_score BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE programs ADD CONSTRAINT programs_price_check CHECK (price >= 0)');
        DB::statement('ALTER TABLE programs ADD CONSTRAINT programs_validity_check CHECK (certificate_validity_months BETWEEN 0 AND 240)');
        DB::statement("ALTER TABLE programs ADD CONSTRAINT programs_short_code_check CHECK (short_code ~ '^[A-Z0-9][A-Z0-9-]{1,15}$')");
        // Pemeriksa (reviewer) harus berbeda dari pengaju (FR-CAT-002).
        DB::statement('ALTER TABLE programs ADD CONSTRAINT programs_review_sod_check CHECK (reviewed_by IS NULL OR submitted_by IS NULL OR reviewed_by <> submitted_by)');

        Schema::create('program_tags', function (Blueprint $table) {
            $table->foreignUuid('program_id')->constrained()->cascadeOnDelete();
            $table->string('tag', 40);
            $table->primary(['program_id', 'tag']);
        });

        Schema::create('course_classes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->constrained()->restrictOnDelete();
            $table->string('batch_name', 120);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestampTz('enroll_opens_at')->nullable();
            $table->timestampTz('enroll_closes_at')->nullable();
            $table->integer('quota');
            $table->integer('enrolled_count')->default(0);
            $table->string('mode', 16)->default('online');
            $table->string('location', 200)->nullable();
            $table->foreignUuid('restricted_organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->jsonb('completion_rules')->default('{}');
            $table->string('status', 16)->default('draft');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['program_id', 'status']);
        });
        DB::statement('ALTER TABLE course_classes ADD CONSTRAINT course_classes_quota_check CHECK (quota > 0)');
        // Kuota atomik: basis data menolak enrolled_count melebihi kuota (FR-CLS-002).
        DB::statement('ALTER TABLE course_classes ADD CONSTRAINT course_classes_enrolled_count_check CHECK (enrolled_count >= 0 AND enrolled_count <= quota)');
        DB::statement('ALTER TABLE course_classes ADD CONSTRAINT course_classes_period_check CHECK (ends_on >= starts_on)');
        DB::statement("ALTER TABLE course_classes ADD CONSTRAINT course_classes_mode_check CHECK (mode IN ('online','offline','hybrid'))");
        DB::statement("ALTER TABLE course_classes ADD CONSTRAINT course_classes_status_check CHECK (status IN ('draft','open','running','closed','archived'))");

        Schema::create('class_trainers', function (Blueprint $table) {
            $table->foreignUuid('course_class_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('lead');
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['course_class_id', 'user_id']);
        });
        DB::statement("ALTER TABLE class_trainers ADD CONSTRAINT class_trainers_role_check CHECK (role IN ('lead','assistant'))");

        Schema::create('modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_class_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->integer('position');
            $table->timestampsTz();
            $table->index(['course_class_id', 'position']);
        });

        Schema::create('chapters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('module_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->integer('position');
            $table->timestampsTz();
            $table->index(['module_id', 'position']);
        });

        Schema::create('media_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('organization_id')->nullable();
            $table->string('kind', 16);
            $table->string('storage_key', 255);
            $table->string('original_filename', 200);
            $table->string('mime_type', 100);
            $table->bigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('scan_status', 16)->default('pending');
            $table->string('scanner', 32)->nullable();
            $table->timestampTz('scanned_at')->nullable();
            $table->string('processing_status', 16)->default('ready');
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE media_assets ADD CONSTRAINT media_assets_kind_check CHECK (kind IN ('video','pdf','image','attachment'))");
        DB::statement("ALTER TABLE media_assets ADD CONSTRAINT media_assets_scan_check CHECK (scan_status IN ('pending','clean','infected','error'))");
        DB::statement('ALTER TABLE media_assets ADD CONSTRAINT media_assets_size_check CHECK (size_bytes > 0)');

        Schema::create('lessons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('chapter_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('title', 200);
            $table->integer('position');
            $table->boolean('is_required')->default(true);
            $table->foreignUuid('media_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('assessment_id')->nullable();
            $table->text('body_md')->nullable();
            $table->text('body_html')->nullable();
            $table->string('external_url', 500)->nullable();
            $table->boolean('allow_download')->default(false);
            $table->integer('duration_seconds')->nullable();
            $table->integer('version')->default(1);
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
            $table->index(['chapter_id', 'position']);
        });
        DB::statement("ALTER TABLE lessons ADD CONSTRAINT lessons_type_check CHECK (type IN ('video','pdf','text','link','quiz'))");
        DB::statement('ALTER TABLE lessons ADD CONSTRAINT lessons_duration_check CHECK (duration_seconds IS NULL OR duration_seconds > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('chapters');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('class_trainers');
        Schema::dropIfExists('course_classes');
        Schema::dropIfExists('program_tags');
        Schema::dropIfExists('programs');
    }
};
