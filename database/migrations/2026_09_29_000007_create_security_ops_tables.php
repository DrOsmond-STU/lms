<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Operasi keamanan (Fase H): catatan backup dan permintaan privasi (UU PDP). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('kind', 16); // db | media
            $table->string('filename', 200);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->char('checksum', 64)->nullable();
            $table->string('method', 32)->nullable(); // pg_dump | php-jsonl | tar | zip
            $table->boolean('encrypted')->default(false);
            $table->string('status', 16); // running | ok | failed
            $table->string('error', 500)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->index(['kind', 'started_at']);
        });
        DB::statement("ALTER TABLE backups ADD CONSTRAINT backups_kind_check CHECK (kind IN ('db','media'))");
        DB::statement("ALTER TABLE backups ADD CONSTRAINT backups_status_check CHECK (status IN ('running','ok','failed'))");

        Schema::create('privacy_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16); // export | delete
            $table->string('status', 16)->default('pending'); // pending | processed | rejected
            $table->string('note', 500)->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->foreignUuid('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['status', 'created_at']);
        });
        DB::statement("ALTER TABLE privacy_requests ADD CONSTRAINT privacy_requests_kind_check CHECK (kind IN ('export','delete'))");
        DB::statement("ALTER TABLE privacy_requests ADD CONSTRAINT privacy_requests_status_check CHECK (status IN ('pending','processed','rejected'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_requests');
        Schema::dropIfExists('backups');
    }
};
