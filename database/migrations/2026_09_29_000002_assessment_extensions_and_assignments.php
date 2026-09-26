<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase asesmen: soal menjodohkan, rubrik esai, pre-test/post-test, tugas (assignment) dengan
 * pengumpulan teks/berkas, tenggat, rubrik, dan penilaian manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE questions DROP CONSTRAINT IF EXISTS questions_type_check');
        DB::statement("ALTER TABLE questions ADD CONSTRAINT questions_type_check CHECK (type IN ('single_choice','multiple_choice','true_false','short_answer','essay','matching'))");
        DB::statement('ALTER TABLE assessments DROP CONSTRAINT IF EXISTS assessments_kind_check');
        DB::statement("ALTER TABLE assessments ADD CONSTRAINT assessments_kind_check CHECK (kind IN ('quiz','final_exam','pretest','posttest'))");

        Schema::table('questions', fn (Blueprint $table) => $table->jsonb('rubric')->nullable()->after('accepted_answers_encrypted'));
        Schema::table('question_options', fn (Blueprint $table) => $table->string('match_text', 500)->nullable()->after('body_html'));
        Schema::table('attempt_answers', function (Blueprint $table) {
            $table->jsonb('match_pairs')->nullable()->after('selected_option_ids');
            $table->jsonb('rubric_scores')->nullable()->after('points_awarded');
            $table->text('feedback')->nullable()->after('rubric_scores');
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_class_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('instructions_md')->nullable();
            $table->text('instructions_html')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->decimal('max_score', 7, 2)->default(100);
            $table->decimal('passing_score', 7, 2)->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('allow_late')->default(false);
            $table->boolean('allow_text')->default(true);
            $table->boolean('allow_file')->default(true);
            $table->jsonb('rubric')->nullable();
            $table->integer('position')->default(1);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['course_class_id', 'due_at']);
        });
        DB::statement('ALTER TABLE assignments ADD CONSTRAINT assignments_score_check CHECK (max_score > 0 AND (passing_score IS NULL OR passing_score BETWEEN 0 AND max_score))');

        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->uuid('course_class_id');
            $table->foreignUuid('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->text('text_answer')->nullable();
            $table->timestampTz('submitted_at');
            $table->boolean('is_late')->default(false);
            $table->string('status', 16)->default('submitted');
            $table->decimal('score', 7, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->jsonb('rubric_scores')->nullable();
            $table->foreignUuid('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('graded_at')->nullable();
            $table->integer('version')->default(1);
            $table->timestampsTz();
            $table->unique(['assignment_id', 'enrollment_id']);
            $table->index(['course_class_id', 'status']);
        });
        DB::statement("ALTER TABLE assignment_submissions ADD CONSTRAINT assignment_submissions_status_check CHECK (status IN ('submitted','graded','returned'))");
        DB::statement('ALTER TABLE assignment_submissions ADD CONSTRAINT assignment_submissions_grader_check CHECK (graded_by IS NULL OR graded_by <> user_id)');
        DB::unprepared(<<<'SQL'
            ALTER TABLE assignment_submissions ENABLE ROW LEVEL SECURITY;
            ALTER TABLE assignment_submissions FORCE ROW LEVEL SECURITY;
            CREATE POLICY tenant_isolation ON assignment_submissions
                USING (app_is_platform_staff() OR user_id = app_user_id() OR organization_id = ANY (app_org_ids()) OR course_class_id = ANY (app_trainer_class_ids()))
                WITH CHECK (app_is_platform_staff() OR user_id = app_user_id() OR course_class_id = ANY (app_trainer_class_ids()));
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignments');
        Schema::table('attempt_answers', fn (Blueprint $table) => $table->dropColumn(['match_pairs', 'rubric_scores', 'feedback']));
        Schema::table('question_options', fn (Blueprint $table) => $table->dropColumn('match_text'));
        Schema::table('questions', fn (Blueprint $table) => $table->dropColumn('rubric'));
        DB::statement('ALTER TABLE assessments DROP CONSTRAINT IF EXISTS assessments_kind_check');
        DB::statement("ALTER TABLE assessments ADD CONSTRAINT assessments_kind_check CHECK (kind IN ('quiz','final_exam'))");
        DB::statement('ALTER TABLE questions DROP CONSTRAINT IF EXISTS questions_type_check');
        DB::statement("ALTER TABLE questions ADD CONSTRAINT questions_type_check CHECK (type IN ('single_choice','multiple_choice','true_false','short_answer','essay'))");
    }
};
