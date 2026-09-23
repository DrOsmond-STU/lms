<?php

declare(strict_types=1);

namespace App\Modules\Audit\Services;

use App\Modules\Identity\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * Jejak audit append-only berantai hash (keamanan/11 SEC-LOG-09..11).
 *
 * hash = SHA256(prev_hash || canonical_json(entri)). Penulisan diserialkan dengan
 * advisory lock transaksi agar rantai tidak bercabang pada penulisan paralel.
 */
final class AuditLogger
{
    public const GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    private const LOCK_KEY = 7_425_110_001;

    /** Kunci yang nilainya selalu disamarkan di kolom `changes` (SEC-LOG-05). */
    private const REDACTED_KEYS = [
        'password', 'password_confirmation', 'current_password', 'remember_token', 'token', 'secret',
        'secret_encrypted', 'code', 'otp', 'api_key', 'phone_encrypted', 'recovery_codes',
    ];

    public function __construct(
        private readonly DatabaseManager $db,
        private readonly Request $request,
    ) {}

    /**
     * @param  array<string, mixed>|null  $changes
     */
    public function record(
        string $action,
        ?User $actor = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?array $changes = null,
        ?string $reason = null,
        ?string $organizationId = null,
    ): string {
        $entry = [
            'id' => (string) Str::uuid7(),
            'occurred_at' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'actor_id' => $actor?->id,
            'actor_type' => $actor !== null ? 'user' : 'system',
            'actor_role' => $actor?->roles->pluck('code')->sort()->implode(','),
            'organization_id' => $organizationId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'changes' => $changes === null ? null : self::redact($changes),
            'reason' => $reason,
            'ip' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 500, ''),
            'request_id' => Context::get('request_id'),
        ];

        $connection = $this->db->connection();

        $connection->transaction(function () use ($connection, &$entry): void {
            $connection->select('select pg_advisory_xact_lock(?)', [self::LOCK_KEY]);
            $previous = $connection->table('audit_logs')->orderByDesc('seq')->value('hash');
            $entry['prev_hash'] = is_string($previous) ? $previous : self::GENESIS_HASH;
            $entry['hash'] = self::computeHash($entry['prev_hash'], $entry);

            $row = $entry;
            $row['changes'] = $entry['changes'] === null ? null : json_encode($entry['changes'], JSON_THROW_ON_ERROR);
            $connection->table('audit_logs')->insert($row);
        });

        return $entry['id'];
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    public static function computeHash(string $previousHash, array $entry): string
    {
        $fields = Arr::only($entry, [
            'id', 'occurred_at', 'actor_id', 'actor_type', 'actor_role', 'organization_id', 'action',
            'subject_type', 'subject_id', 'changes', 'reason', 'ip', 'user_agent', 'request_id',
        ]);
        ksort($fields);

        return hash('sha256', $previousHash.json_encode(self::canonical($fields), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACTED_KEYS, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = self::redact($value);
            }
        }

        return $data;
    }

    private static function canonical(mixed $value): mixed
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map(self::canonical(...), $value);
        }

        return $value;
    }
}
