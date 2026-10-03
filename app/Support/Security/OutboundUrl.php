<?php

declare(strict_types=1);

namespace App\Support\Security;

/**
 * Pemeriksaan URL tujuan permintaan keluar (anti-SSRF): hanya https, tanpa userinfo, port 443,
 * dan host harus berada pada daftar host tepercaya atau menyelesaikan ke alamat IP publik
 * (bukan rentang privat/loopback/link-local/reserved).
 */
final class OutboundUrl
{
    /** @param list<string> $trustedHosts host (atau akhiran domain) yang selalu diizinkan */
    public static function isPublicHttps(string $url, array $trustedHosts = []): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || isset($parts['user']) || isset($parts['pass']) || ! isset($parts['host'])) {
            return false;
        }
        if (isset($parts['port']) && $parts['port'] !== 443) {
            return false;
        }
        $host = strtolower(rtrim($parts['host'], '.'));
        /** @var list<string> $configured */
        $configured = (array) config('security.outbound_allow_hosts', []);
        foreach (array_merge($trustedHosts, $configured) as $trusted) {
            if ($host === $trusted || str_ends_with($host, '.'.$trusted)) {
                return true;
            }
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::isPublicIp($host);
        }
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (! is_array($records) || $records === []) {
            return false;
        }
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (! is_string($ip) || ! self::isPublicIp($ip)) {
                return false; // satu alamat privat saja → tolak (anti DNS rebinding sederhana)
            }
        }

        return true;
    }

    public static function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
