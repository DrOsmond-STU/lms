<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pembayaran manual tercatat (FR-PAY, tahap A): transaksi per enrollment berbayar,
 * bukti transfer yang diunggah peserta, verifikasi Admin Keuangan (maker–checker di atas
 * ambang), invoice bernomor. Skema mengikuti docs/05 §4.8 agar gateway (Midtrans) dapat
 * ditambahkan tanpa migrasi ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('program_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('course_class_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('enrollment_id')->unique()->constrained()->restrictOnDelete();
            $table->string('order_id', 32)->unique();
            $table->string('invoice_number', 32)->nullable()->unique();
            $table->bigInteger('list_price');
            $table->bigInteger('discount_amount')->default(0);
            $table->bigInteger('gross_amount');
            $table->char('currency', 3)->default('IDR');
            $table->string('status', 20);
            $table->string('payment_method', 32)->default('manual_transfer');
            $table->string('gateway', 16)->default('manual');
            $table->boolean('needs_review')->default(false);
            $table->foreignUuid('proof_media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('proof_note', 300)->nullable();
            $table->timestampTz('proof_submitted_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->foreignUuid('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('settled_at')->nullable();
            $table->uuid('approval_request_id')->nullable();
            $table->string('billing_name', 160)->nullable();
            $table->text('billing_tax_id_encrypted')->nullable();
            $table->text('billing_address_encrypted')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampsTz();
            $table->index(['status', 'expires_at']);
            $table->index(['user_id', 'status']);
            $table->index(['needs_review', 'status']);
        });
        DB::statement("ALTER TABLE payment_transactions ADD CONSTRAINT payment_transactions_status_check CHECK (status IN ('pending','settled','failed','expired','refund_pending','refunded'))");
        DB::statement('ALTER TABLE payment_transactions ADD CONSTRAINT payment_transactions_amount_check CHECK (list_price >= 0 AND discount_amount >= 0 AND gross_amount >= 0 AND gross_amount = list_price - discount_amount)');
        DB::statement("ALTER TABLE payment_transactions ADD CONSTRAINT payment_transactions_currency_check CHECK (currency = 'IDR')");
        // Pemisahan tugas: yang mengonfirmasi lunas bukan peserta yang membayar.
        DB::statement('ALTER TABLE payment_transactions ADD CONSTRAINT payment_transactions_settler_check CHECK (settled_by IS NULL OR settled_by <> user_id)');
        DB::statement("ALTER TABLE payment_transactions ADD CONSTRAINT payment_transactions_settled_check CHECK (status <> 'settled' OR (settled_at IS NOT NULL AND invoice_number IS NOT NULL))");

        DB::unprepared(<<<'SQL'
            ALTER TABLE payment_transactions ENABLE ROW LEVEL SECURITY;
            ALTER TABLE payment_transactions FORCE ROW LEVEL SECURITY;
            CREATE POLICY tenant_isolation ON payment_transactions
                USING (app_is_platform_staff() OR user_id = app_user_id() OR organization_id = ANY (app_org_ids()))
                WITH CHECK (app_is_platform_staff() OR user_id = app_user_id());
        SQL);

        // Jejak kejadian transaksi (append-only; pelengkap audit_logs untuk rekonsiliasi).
        Schema::create('payment_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_transaction_id')->constrained()->cascadeOnDelete();
            $table->string('source', 16);
            $table->string('event', 40);
            $table->jsonb('payload');
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->index('payment_transaction_id');
        });
        DB::statement("ALTER TABLE payment_events ADD CONSTRAINT payment_events_source_check CHECK (source IN ('participant','admin','system','approval'))");

        // Nomor invoice INV/TAHUN/BULAN/URUT5 (FR-PAY-004), diambil atomik seperti nomor sertifikat.
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->smallInteger('year');
            $table->smallInteger('month');
            $table->integer('last_value')->default(0);
            $table->primary(['year', 'month']);
        });

        DB::statement('ALTER TABLE payment_events ADD CONSTRAINT payment_events_append_only CHECK (true)');
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION payment_events_no_update() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'payment_events bersifat append-only';
            END;
            $$;
            CREATE TRIGGER payment_events_immutable BEFORE UPDATE OR DELETE ON payment_events
                FOR EACH ROW EXECUTE FUNCTION payment_events_no_update();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS payment_events_immutable ON payment_events; DROP FUNCTION IF EXISTS payment_events_no_update();');
        Schema::dropIfExists('invoice_sequences');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_transactions');
    }
};
