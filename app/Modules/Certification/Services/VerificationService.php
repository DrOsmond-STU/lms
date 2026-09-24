<?php

declare(strict_types=1);

namespace App\Modules\Certification\Services;

use App\Modules\Certification\Models\Certificate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Verifikasi publik sertifikat (FR-CERT-007, FR-API-001, keamanan/07 §2.4): data minimal,
 * nama tersamar bila dicari via nomor, log dengan IP ter-hash harian (bukan IP mentah).
 */
final class VerificationService
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @return array{status: string, certificate: Certificate|null, name: string|null}
     */
    public function byCode(string $input, string $ip, ?string $userAgent, string $lookupType = 'code'): array
    {
        $code = VerificationCode::normalize($input);
        $certificate = VerificationCode::isWellFormed($code) ? $this->find('verification_code', $code) : null;

        return $this->result($certificate, fullName: true, lookupType: $lookupType, ip: $ip, userAgent: $userAgent);
    }

    /**
     * @return array{status: string, certificate: Certificate|null, name: string|null}
     */
    public function byNumber(string $number, ?string $fullName, string $ip, ?string $userAgent): array
    {
        $number = strtoupper(trim($number));
        $certificate = preg_match('#^[A-Z0-9/-]{8,80}$#', $number) === 1 ? $this->find('number', $number) : null;
        $nameMatches = $certificate !== null && $fullName !== null
            && hash_equals(self::normalizeName($certificate->holder_name), self::normalizeName($fullName));

        return $this->result($certificate, fullName: $nameMatches, lookupType: 'number', ip: $ip, userAgent: $userAgent);
    }

    private function find(string $column, string $value): ?Certificate
    {
        return $this->tenant->runAsSystem(fn (): ?Certificate => Certificate::query()->where($column, $value)
            ->whereNotIn('status', ['generating', 'generation_failed'])->first());
    }

    /**
     * @return array{status: string, certificate: Certificate|null, name: string|null}
     */
    private function result(?Certificate $certificate, bool $fullName, string $lookupType, string $ip, ?string $userAgent): array
    {
        $status = $certificate?->publicStatus() ?? 'not_found';

        DB::table('certificate_verification_logs')->insert([
            'id' => (string) Str::uuid7(),
            'certificate_id' => $certificate?->id,
            'lookup_type' => $lookupType,
            'result' => $status === 'generating' ? 'not_found' : $status,
            'ip_hash' => hash_hmac('sha256', $ip, now()->format('Y-m-d').'|'.config('app.key')),
            'user_agent_family' => self::agentFamily($userAgent),
            'created_at' => now(),
        ]);

        return [
            'status' => $status,
            'certificate' => $certificate,
            'name' => $certificate === null ? null : ($fullName ? $certificate->holder_name : $certificate->holder_name_masked),
        ];
    }

    private static function normalizeName(string $name): string
    {
        return preg_replace('/\s+/u', ' ', mb_strtolower(trim($name))) ?? '';
    }

    private static function agentFamily(?string $userAgent): string
    {
        $agent = (string) $userAgent;

        return match (true) {
            $agent === '' => 'unknown',
            str_contains($agent, 'Edg/') => 'edge',
            str_contains($agent, 'Chrome/') => 'chrome',
            str_contains($agent, 'Firefox/') => 'firefox',
            str_contains($agent, 'Safari/') => 'safari',
            str_contains(strtolower($agent), 'curl') || str_contains(strtolower($agent), 'python') => 'script',
            default => 'other',
        };
    }
}
