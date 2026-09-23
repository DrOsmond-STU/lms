<?php

declare(strict_types=1);

namespace App\Modules\Audit\Services;

use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pencatat event keamanan (keamanan/11 §3). Ditulis ke tabel `security_events` dan
 * channel log `security` (diteruskan ke SIEM).
 */
final class SecurityEventLogger
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly Request $request,
    ) {}

    /**
     * @param  'info'|'warning'|'high'|'critical'  $severity
     * @param  array<string, mixed>  $details
     */
    public function log(string $type, string $severity = 'info', ?string $userId = null, array $details = []): void
    {
        $details = AuditLogger::redact($details);
        $row = [
            'id' => (string) Str::uuid7(),
            'occurred_at' => now(),
            'type' => $type,
            'severity' => $severity,
            'user_id' => $userId,
            'ip' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 500, ''),
            'request_id' => Context::get('request_id'),
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
        ];

        $this->db->connection()->table('security_events')->insert($row);

        Log::channel('security')->log(
            match ($severity) {
                'critical' => 'critical',
                'high' => 'error',
                'warning' => 'warning',
                default => 'info',
            },
            $type,
            ['user_id' => $userId, 'ip' => $row['ip'], 'details' => $details],
        );
    }
}
