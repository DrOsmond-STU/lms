<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas interaktif: forum diskusi & tanya jawab per kelas, komentar pada materi, laporan
 * konten, polling, dan obrolan kelas. Akses dibatasi pada anggota kelas (peserta aktif,
 * trainer pengampu, staf platform) di lapisan aplikasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussion_threads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_class_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 16); // discussion | question | comment
            $table->string('title', 200)->nullable();
            $table->text('body');
            $table->text('body_html');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_resolved')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->unsignedInteger('replies_count')->default(0);
            $table->timestampTz('last_post_at')->nullable();
            $table->timestampsTz();
            $table->index(['course_class_id', 'kind', 'is_pinned', 'last_post_at']);
            $table->index(['lesson_id', 'created_at']);
        });
        DB::statement("ALTER TABLE discussion_threads ADD CONSTRAINT discussion_threads_kind_check CHECK (kind IN ('discussion','question','comment'))");

        Schema::create('discussion_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('thread_id')->constrained('discussion_threads')->cascadeOnDelete();
            $table->foreignUuid('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->text('body_html');
            $table->boolean('is_answer')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->foreignUuid('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('hidden_reason', 300)->nullable();
            $table->timestampsTz();
            $table->index(['thread_id', 'created_at']);
        });

        Schema::create('discussion_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_class_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('thread_id')->constrained('discussion_threads')->cascadeOnDelete();
            $table->foreignUuid('post_id')->nullable()->constrained('discussion_posts')->cascadeOnDelete();
            $table->foreignUuid('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 500);
            $table->string('status', 16)->default('open'); // open | resolved
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
            $table->index(['course_class_id', 'status']);
        });
        DB::statement("ALTER TABLE discussion_reports ADD CONSTRAINT discussion_reports_status_check CHECK (status IN ('open','resolved'))");

        Schema::create('polls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_class_id')->constrained()->cascadeOnDelete();
            $table->string('question', 300);
            $table->jsonb('options'); // list<string>
            $table->boolean('is_anonymous')->default(true);
            $table->boolean('multiple')->default(false);
            $table->timestampTz('closes_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['course_class_id', 'created_at']);
        });

        Schema::create('poll_votes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('poll_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->jsonb('option_indexes'); // list<int>
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['poll_id', 'user_id']);
        });

        Schema::create('class_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_class_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('body', 1000);
            $table->boolean('is_hidden')->default(false);
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['course_class_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_messages');
        Schema::dropIfExists('poll_votes');
        Schema::dropIfExists('polls');
        Schema::dropIfExists('discussion_reports');
        Schema::dropIfExists('discussion_posts');
        Schema::dropIfExists('discussion_threads');
    }
};
