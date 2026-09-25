<?php

declare(strict_types=1);

namespace App\Modules\Referral\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * Kode referral & rekening pencairan komisi milik seorang pengguna.
 *
 * @property string $user_id
 * @property string $code
 * @property string|null $bank_name
 * @property string|null $bank_account_encrypted
 * @property string|null $bank_account_name
 * @property int $visits
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
final class ReferralProfile extends Model
{
    protected $primaryKey = 'user_id';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['visits' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bankAccount(): ?string
    {
        return $this->bank_account_encrypted === null ? null : Crypt::decryptString($this->bank_account_encrypted);
    }

    /** Nomor rekening tersamar untuk tampilan (4 digit terakhir). */
    public function bankAccountMasked(): ?string
    {
        $account = $this->bankAccount();
        if ($account === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $account) ?? '';

        return str_repeat('•', max(0, strlen($digits) - 4)).substr($digits, -4);
    }

    public function hasPayoutAccount(): bool
    {
        return $this->bank_name !== null && $this->bank_account_encrypted !== null && $this->bank_account_name !== null;
    }
}
