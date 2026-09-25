<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Program referral: setiap peserta punya kode; akun baru yang mendaftar dengan kode itu
 * dikaitkan (first-touch, permanen); komisi lahir hanya saat pembayaran akun tersebut
 * dikonfirmasi lunas; Admin Keuangan membayar komisi per batch (payout) dengan referensi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('referred_by')->nullable()->after('primary_organization_id')->constrained('users')->nullOnDelete();
            $table->timestampTz('referred_at')->nullable()->after('referred_by');
            $table->index('referred_by');
        });
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_referred_by_check CHECK (referred_by IS NULL OR referred_by <> id)');

        Schema::create('referral_profiles', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('code', 12)->unique();
            $table->string('bank_name', 60)->nullable();
            $table->text('bank_account_encrypted')->nullable();
            $table->string('bank_account_name', 100)->nullable();
            $table->unsignedInteger('visits')->default(0);
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE referral_profiles ADD CONSTRAINT referral_profiles_code_check CHECK (code ~ '^[A-Z2-9]{6,12}$')");
        DB::unprepared(<<<'SQL'
            ALTER TABLE referral_profiles ENABLE ROW LEVEL SECURITY;
            ALTER TABLE referral_profiles FORCE ROW LEVEL SECURITY;
            CREATE POLICY tenant_isolation ON referral_profiles
                USING (app_is_platform_staff() OR user_id = app_user_id())
                WITH CHECK (app_is_platform_staff() OR user_id = app_user_id());
        SQL);

        Schema::create('referral_payouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('referrer_id')->constrained('users')->restrictOnDelete();
            $table->bigInteger('amount');
            $table->unsignedInteger('commission_count');
            $table->string('reference', 120);
            $table->string('note', 300)->nullable();
            $table->foreignUuid('paid_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('paid_at');
            $table->timestampTz('created_at')->useCurrent();
            $table->index('referrer_id');
        });
        DB::statement('ALTER TABLE referral_payouts ADD CONSTRAINT referral_payouts_amount_check CHECK (amount > 0 AND commission_count > 0)');
        DB::statement('ALTER TABLE referral_payouts ADD CONSTRAINT referral_payouts_sod_check CHECK (paid_by <> referrer_id)');

        Schema::create('referral_commissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('referrer_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('referred_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('payment_transaction_id')->unique()->constrained()->restrictOnDelete();
            $table->bigInteger('base_amount');
            $table->smallInteger('rate_percent');
            $table->bigInteger('amount');
            $table->string('status', 16);
            $table->foreignUuid('payout_id')->nullable()->constrained('referral_payouts')->nullOnDelete();
            $table->string('void_reason', 500)->nullable();
            $table->foreignUuid('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('voided_at')->nullable();
            $table->timestampsTz();
            $table->index(['referrer_id', 'status']);
            $table->index('created_at');
        });
        DB::statement("ALTER TABLE referral_commissions ADD CONSTRAINT referral_commissions_status_check CHECK (status IN ('pending','paid','void'))");
        DB::statement('ALTER TABLE referral_commissions ADD CONSTRAINT referral_commissions_amount_check CHECK (amount >= 0 AND base_amount >= 0 AND rate_percent BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE referral_commissions ADD CONSTRAINT referral_commissions_self_check CHECK (referrer_id <> referred_user_id)');
        DB::statement("ALTER TABLE referral_commissions ADD CONSTRAINT referral_commissions_paid_check CHECK (status <> 'paid' OR payout_id IS NOT NULL)");
        DB::unprepared(<<<'SQL'
            ALTER TABLE referral_commissions ENABLE ROW LEVEL SECURITY;
            ALTER TABLE referral_commissions FORCE ROW LEVEL SECURITY;
            CREATE POLICY tenant_isolation ON referral_commissions
                USING (app_is_platform_staff() OR referrer_id = app_user_id())
                WITH CHECK (app_is_platform_staff());
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_commissions');
        Schema::dropIfExists('referral_payouts');
        Schema::dropIfExists('referral_profiles');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_referred_by_check');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by');
            $table->dropColumn('referred_at');
        });
    }
};
