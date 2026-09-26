<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase konten & jadwal: lesson audio/dokumen, prasyarat & drip content, urutan wajib per kelas,
 * sesi kelas (tatap muka / live class) dengan presensi, kalender akademik, dan durasi belajar.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE media_assets DROP CONSTRAINT IF EXISTS media_assets_kind_check');
        DB::statement("ALTER TABLE media_assets ADD CONSTRAINT media_assets_kind_check CHECK (kind IN ('video','pdf','image','attachment','audio','document','submission'))");
        DB::statement('ALTER TABLE lessons DROP CONSTRAINT IF EXISTS lessons_type_check');
        DB::statement("ALTER TABLE lessons ADD CONSTRAINT lessons_type_check CHECK (type IN ('video','pdf','text','link','quiz','audio','document'))");

        Schema::table('lessons', function (Blueprint $table) {
            $table->timestampTz('unlock_at')->nullable()->after('published_at');
            $table->smallInteger('unlock_after_days')->nullable()->after('unlock_at');
            $table->foreignUuid('prerequisite_lesson_id')->nullable()->after('unlock_after_days')->constrained('lessons')->nullOnDelete();
        });
        DB::statement('ALTER TABLE lessons ADD CONSTRAINT lessons_prerequisite_self_check CHECK (prerequisite_lesson_id IS NULL OR prerequisite_lesson_id <> id)');
        DB::statement('ALTER TABLE lessons ADD CONSTRAINT lessons_unlock_days_check CHECK (unlock_after_days IS NULL OR unlock_after_days BETWEEN 0 AND 3650)');

        Schema::table('course_classes', function (Blueprint $table) {
            $table->boolean('is_sequential')->default(false)->after('completion_rules');
            $table->boolean('requires_approval')->default(false)->after('is_sequential');
            $table->boolean('discussion_enabled')->default(true)->after('requires_approval');
            $table->boolean('chat_enabled')->default(true)->after('discussion_enabled');
        });

        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->integer('time_spent_seconds')->default(0)->after('max_position_seconds');
        });

        Schema::create('class_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_class_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('type', 16); // online | offline
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('meeting_url', 500)->nullable();
            $table->string('location', 200)->nullable();
            $table->string('attendance_mode', 16)->default('self'); // none | self | manual
            $table->string('checkin_code', 8)->nullable();
            $table->smallInteger('checkin_opens_before')->default(15);
            $table->smallInteger('checkin_closes_after')->default(30);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['course_class_id', 'starts_at']);
        });
        DB::statement("ALTER TABLE class_sessions ADD CONSTRAINT class_sessions_type_check CHECK (type IN ('online','offline'))");
        DB::statement("ALTER TABLE class_sessions ADD CONSTRAINT class_sessions_attendance_check CHECK (attendance_mode IN ('none','self','manual'))");
        DB::statement('ALTER TABLE class_sessions ADD CONSTRAINT class_sessions_time_check CHECK (ends_at > starts_at)');

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->uuid('course_class_id');
            $table->foreignUuid('class_session_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16); // present | late | absent | excused
            $table->string('method', 16); // self | manual
            $table->timestampTz('checked_in_at')->nullable();
            $table->string('note', 300)->nullable();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['class_session_id', 'enrollment_id']);
            $table->index(['course_class_id', 'user_id']);
        });
        DB::statement("ALTER TABLE attendance_records ADD CONSTRAINT attendance_records_status_check CHECK (status IN ('present','late','absent','excused'))");
        DB::statement("ALTER TABLE attendance_records ADD CONSTRAINT attendance_records_method_check CHECK (method IN ('self','manual'))");
        DB::unprepared(<<<'SQL'
            ALTER TABLE attendance_records ENABLE ROW LEVEL SECURITY;
            ALTER TABLE attendance_records FORCE ROW LEVEL SECURITY;
            CREATE POLICY tenant_isolation ON attendance_records
                USING (app_is_platform_staff() OR user_id = app_user_id() OR organization_id = ANY (app_org_ids()) OR course_class_id = ANY (app_trainer_class_ids()))
                WITH CHECK (app_is_platform_staff() OR user_id = app_user_id() OR course_class_id = ANY (app_trainer_class_ids()));
        SQL);

        Schema::create('academic_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 200);
            $table->string('description', 1000)->nullable();
            $table->string('kind', 16); // holiday | exam | registration | event | other
            $table->string('scope', 16); // platform | organization | class
            $table->foreignUuid('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('course_class_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['starts_on', 'ends_on']);
        });
        DB::statement("ALTER TABLE academic_events ADD CONSTRAINT academic_events_kind_check CHECK (kind IN ('holiday','exam','registration','event','other'))");
        DB::statement("ALTER TABLE academic_events ADD CONSTRAINT academic_events_scope_check CHECK (scope IN ('platform','organization','class'))");
        DB::statement('ALTER TABLE academic_events ADD CONSTRAINT academic_events_range_check CHECK (ends_on >= starts_on)');

    }

    public function down(): void
    {
        Schema::dropIfExists('academic_events');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('class_sessions');
        Schema::table('lesson_progress', fn (Blueprint $table) => $table->dropColumn('time_spent_seconds'));
        Schema::table('course_classes', fn (Blueprint $table) => $table->dropColumn(['is_sequential', 'requires_approval', 'discussion_enabled', 'chat_enabled']));
        DB::statement('ALTER TABLE lessons DROP CONSTRAINT IF EXISTS lessons_prerequisite_self_check');
        DB::statement('ALTER TABLE lessons DROP CONSTRAINT IF EXISTS lessons_unlock_days_check');
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prerequisite_lesson_id');
            $table->dropColumn(['unlock_at', 'unlock_after_days']);
        });
    }
};
