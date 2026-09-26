<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Administrasi (Fase D): pengumuman, kelompok kelas, approval pendaftaran, dan peran supervisor
 * (peran dimasukkan ke tabel `roles` oleh `stu:access-sync`).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pengumuman: lingkup platform / organisasi / kelas. Bukan data sensitif → tanpa RLS,
        // penyaringan audiens dilakukan AnnouncementFeed.
        Schema::create('announcements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('scope', 16);
            $table->foreignUuid('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('course_class_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('body');
            $table->text('body_html');
            $table->boolean('is_pinned')->default(false);
            $table->timestampTz('publish_at');
            $table->timestampTz('expires_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['scope', 'publish_at']);
            $table->index('organization_id');
            $table->index('course_class_id');
        });
        DB::statement("ALTER TABLE announcements ADD CONSTRAINT announcements_scope_check CHECK (scope IN ('platform','organization','class'))");

        // Kelompok belajar dalam kelas (mentor opsional dari trainer/peserta).
        Schema::create('class_groups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_class_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('description', 300)->nullable();
            $table->foreignUuid('mentor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['course_class_id', 'name']);
        });

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->foreignUuid('group_id')->nullable()->after('program_id')->constrained('class_groups')->nullOnDelete();
        });

        // Approval pendaftaran: status baru `applied` (menunggu persetujuan trainer/admin).
        DB::statement('ALTER TABLE enrollments DROP CONSTRAINT IF EXISTS enrollments_status_check');
        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_status_check CHECK (status IN ('applied','awaiting_payment','enrolled','in_progress','pending_approval','passed','failed','cancelled'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE enrollments DROP CONSTRAINT IF EXISTS enrollments_status_check');
        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_status_check CHECK (status IN ('awaiting_payment','enrolled','in_progress','pending_approval','passed','failed','cancelled'))");
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('group_id');
        });
        Schema::dropIfExists('class_groups');
        Schema::dropIfExists('announcements');
    }
};
