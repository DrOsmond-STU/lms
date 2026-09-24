<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enrollment, progres lesson, bank soal, asesmen & attempt (docs/05 §4.4–4.5,
 * keamanan/08). Tabel ber-tenant memakai RLS: baris terlihat oleh pemiliknya, admin
 * organisasinya, trainer pengampu kelasnya, dan staf platform.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('course_class_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('program_id')->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->string('source', 16);
            $table->smallInteger('progress_percent')->default(0);
            $table->decimal('final_score', 5, 2)->nullable();
            $table->timestampTz('enrolled_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestampsTz();
            $table->index(['course_class_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['organization_id', 'status']);
        });
        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_status_check CHECK (status IN ('awaiting_payment','enrolled','in_progress','pending_approval','passed','failed','cancelled'))");
        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_source_check CHECK (source IN ('self','admin','bulk','api','payment'))");
        DB::statement('ALTER TABLE enrollments ADD CONSTRAINT enrollments_progress_check CHECK (progress_percent BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE enrollments ADD CONSTRAINT enrollments_score_check CHECK (final_score IS NULL OR final_score BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE enrollments ADD CONSTRAINT enrollments_approver_check CHECK (approved_by IS NULL OR approved_by <> user_id)');
        // Satu enrollment aktif per program per peserta (FR-ENR-002).
        DB::statement("CREATE UNIQUE INDEX enrollments_one_active_per_program ON enrollments (user_id, program_id) WHERE status NOT IN ('failed','cancelled')");

        Schema::create('enrollment_status_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('enrollment_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->index('enrollment_id');
        });

        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('course_class_id');
            $table->foreignUuid('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('started');
            $table->integer('watched_seconds')->default(0);
            $table->integer('max_position_seconds')->default(0);
            $table->timestampTz('first_opened_at')->nullable();
            $table->timestampTz('last_heartbeat_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->jsonb('integrity_flags')->nullable();
            $table->timestampsTz();
            $table->unique(['enrollment_id', 'lesson_id']);
        });
        DB::statement("ALTER TABLE lesson_progress ADD CONSTRAINT lesson_progress_status_check CHECK (status IN ('started','completed'))");

        Schema::create('question_banks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('question_bank_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->text('stem_md');
            $table->text('stem_html');
            $table->text('explanation_html')->nullable();
            $table->smallInteger('difficulty')->default(3);
            $table->jsonb('competency_tags')->default('[]');
            $table->decimal('points', 6, 2)->default(1);
            // Kunci isian singkat terenkripsi (K4).
            $table->text('accepted_answers_encrypted')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('version')->default(1);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['question_bank_id', 'is_active']);
        });
        DB::statement("ALTER TABLE questions ADD CONSTRAINT questions_type_check CHECK (type IN ('single_choice','multiple_choice','true_false','short_answer','essay'))");
        DB::statement('ALTER TABLE questions ADD CONSTRAINT questions_difficulty_check CHECK (difficulty BETWEEN 1 AND 5)');
        DB::statement('ALTER TABLE questions ADD CONSTRAINT questions_points_check CHECK (points > 0)');

        Schema::create('question_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('question_id')->constrained()->cascadeOnDelete();
            $table->text('body_html');
            $table->boolean('is_correct')->default(false);
            $table->smallInteger('position');
        });

        Schema::create('assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_class_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('question_bank_id')->constrained()->restrictOnDelete();
            $table->string('kind', 16);
            $table->string('title', 200);
            $table->integer('duration_minutes');
            $table->smallInteger('max_attempts');
            $table->integer('cooldown_minutes')->default(0);
            $table->timestampTz('opens_at')->nullable();
            $table->timestampTz('closes_at')->nullable();
            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('shuffle_options')->default(true);
            $table->smallInteger('question_count');
            $table->jsonb('selection_rules')->nullable();
            $table->decimal('passing_score', 5, 2);
            $table->string('review_policy', 16);
            $table->boolean('is_required')->default(true);
            $table->boolean('requires_prerequisites')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE assessments ADD CONSTRAINT assessments_kind_check CHECK (kind IN ('quiz','final_exam'))");
        DB::statement("ALTER TABLE assessments ADD CONSTRAINT assessments_review_check CHECK (review_policy IN ('never','after_submit','after_close'))");
        DB::statement('ALTER TABLE assessments ADD CONSTRAINT assessments_numbers_check CHECK (duration_minutes BETWEEN 1 AND 600 AND max_attempts BETWEEN 1 AND 20 AND question_count BETWEEN 1 AND 200 AND cooldown_minutes >= 0)');
        DB::statement('ALTER TABLE assessments ADD CONSTRAINT assessments_passing_check CHECK (passing_score BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE assessments ADD CONSTRAINT assessments_window_check CHECK (opens_at IS NULL OR closes_at IS NULL OR closes_at > opens_at)');
        // Satu ujian akhir per kelas.
        DB::statement("CREATE UNIQUE INDEX assessments_one_final_per_class ON assessments (course_class_id) WHERE kind = 'final_exam'");

        Schema::table('lessons', function (Blueprint $table) {
            $table->foreign('assessment_id')->references('id')->on('assessments')->nullOnDelete();
        });

        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->uuid('course_class_id');
            $table->foreignUuid('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('attempt_no');
            $table->string('status', 16);
            $table->timestampTz('started_at', 6);
            $table->timestampTz('deadline_at', 6);
            $table->timestampTz('submitted_at', 6)->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->boolean('needs_manual_grading')->default(false);
            $table->jsonb('question_order');
            $table->jsonb('option_order');
            $table->jsonb('question_versions');
            $table->string('ip', 45)->nullable();
            $table->jsonb('integrity_flags')->nullable();
            $table->string('voided_reason', 500)->nullable();
            $table->timestampsTz();
            $table->unique(['assessment_id', 'enrollment_id', 'attempt_no']);
            $table->index(['status', 'deadline_at']);
        });
        DB::statement("ALTER TABLE exam_attempts ADD CONSTRAINT exam_attempts_status_check CHECK (status IN ('in_progress','submitted','auto_submitted','graded','voided'))");
        // Maksimal satu attempt aktif per (asesmen, enrollment) — SEC-EXAM-06.
        DB::statement("CREATE UNIQUE INDEX exam_attempts_one_active ON exam_attempts (assessment_id, enrollment_id) WHERE status = 'in_progress'");

        Schema::create('attempt_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('question_id')->constrained()->restrictOnDelete();
            $table->jsonb('selected_option_ids')->nullable();
            $table->text('text_answer')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('points_awarded', 6, 2)->nullable();
            $table->foreignUuid('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('graded_at')->nullable();
            $table->timestampTz('answered_at', 6)->nullable();
            $table->unique(['exam_attempt_id', 'question_id']);
        });

        Schema::create('attempt_grants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('enrollment_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('extra_attempts');
            $table->string('reason', 500);
            $table->foreignUuid('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
        });
        DB::statement('ALTER TABLE attempt_grants ADD CONSTRAINT attempt_grants_extra_check CHECK (extra_attempts BETWEEN 1 AND 5)');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION app_trainer_class_ids() RETURNS uuid[]
                LANGUAGE sql STABLE AS $$
                    SELECT coalesce(string_to_array(nullif(current_setting('app.trainer_class_ids', true), ''), ',')::uuid[], '{}'::uuid[])
                $$;

            ALTER TABLE enrollments ENABLE ROW LEVEL SECURITY;
            ALTER TABLE enrollments FORCE ROW LEVEL SECURITY;
            CREATE POLICY tenant_isolation ON enrollments
                USING (app_is_platform_staff() OR user_id = app_user_id() OR organization_id = ANY (app_org_ids()) OR course_class_id = ANY (app_trainer_class_ids()))
                WITH CHECK (app_is_platform_staff() OR user_id = app_user_id() OR organization_id = ANY (app_org_ids()) OR course_class_id = ANY (app_trainer_class_ids()));

            ALTER TABLE lesson_progress ENABLE ROW LEVEL SECURITY;
            ALTER TABLE lesson_progress FORCE ROW LEVEL SECURITY;
            CREATE POLICY tenant_isolation ON lesson_progress
                USING (app_is_platform_staff() OR user_id = app_user_id() OR organization_id = ANY (app_org_ids()) OR course_class_id = ANY (app_trainer_class_ids()))
                WITH CHECK (app_is_platform_staff() OR user_id = app_user_id());

            ALTER TABLE exam_attempts ENABLE ROW LEVEL SECURITY;
            ALTER TABLE exam_attempts FORCE ROW LEVEL SECURITY;
            CREATE POLICY tenant_isolation ON exam_attempts
                USING (app_is_platform_staff() OR user_id = app_user_id() OR organization_id = ANY (app_org_ids()) OR course_class_id = ANY (app_trainer_class_ids()))
                WITH CHECK (app_is_platform_staff() OR user_id = app_user_id() OR course_class_id = ANY (app_trainer_class_ids()));
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_grants');
        Schema::dropIfExists('attempt_answers');
        Schema::dropIfExists('exam_attempts');
        Schema::table('lessons', fn (Blueprint $table) => $table->dropForeign(['assessment_id']));
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('question_banks');
        Schema::dropIfExists('lesson_progress');
        Schema::dropIfExists('enrollment_status_histories');
        Schema::dropIfExists('enrollments');
        DB::unprepared('DROP FUNCTION IF EXISTS app_trainer_class_ids();');
    }
};
