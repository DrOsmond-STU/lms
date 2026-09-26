<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Fitur AI (Fase G): log pemakaian, percakapan tutor, rangkuman materi, saran penilaian esai. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature', 32);
            $table->string('model', 64);
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->integer('duration_ms')->default(0);
            $table->string('status', 16); // ok | error
            $table->string('error', 300)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
            $table->index(['feature', 'created_at']);
        });

        Schema::create('ai_conversations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->jsonb('messages')->default('[]');
            $table->timestampsTz();
            $table->unique(['user_id', 'enrollment_id', 'lesson_id']);
        });

        Schema::create('lesson_summaries', function (Blueprint $table): void {
            $table->foreignUuid('lesson_id')->primary()->constrained()->cascadeOnDelete();
            $table->text('summary_md');
            $table->text('summary_html');
            $table->string('model', 64);
            $table->timestampTz('generated_at');
        });

        Schema::create('ai_insights', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('kind', 32); // recommendation | learning_path | class_insight | curriculum_draft
            $table->string('subject_id', 64); // enrollment/user/class id
            $table->text('body_md');
            $table->text('body_html');
            $table->jsonb('data')->nullable();
            $table->timestampTz('generated_at');
            $table->unique(['kind', 'subject_id']);
        });

        Schema::table('attempt_answers', function (Blueprint $table): void {
            $table->jsonb('ai_suggestion')->nullable();
        });
        DB::statement("ALTER TABLE ai_usages ADD CONSTRAINT ai_usages_status_check CHECK (status IN ('ok','error'))");
    }

    public function down(): void
    {
        Schema::table('attempt_answers', fn (Blueprint $table) => $table->dropColumn('ai_suggestion'));
        Schema::dropIfExists('ai_insights');
        Schema::dropIfExists('lesson_summaries');
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('ai_usages');
    }
};
