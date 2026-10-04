<?php

declare(strict_types=1);

namespace App\Support\Security;

/**
 * Pemeriksaan URL tujuan permintaan keluar (anti-SSRF): hanya https, tanpa userinfo, port 443,
 * dan host harus berada pada daftar host tepercaya atau menyelesaikan ke alamat IP publik
 * (bukan rentang privat/loopback/link-local/reserved/CGNAT/benchmark/NAT64).
 *
 * `resolve()` mengembalikan alamat IP yang lolos pemeriksaan agar pemanggil menyematkannya ke koneksi
 * (CURLOPT_RESOLVE); dengan begitu alamat yang dipakai saat terhubung sama dengan yang diperiksa (anti DNS rebinding).
 */
final class OutboundUrl
{
    /** Rentang non-publik yang tidak tercakup FILTER_FLAG_NO_PRIV_RANGE|NO_RES_RANGE. */
    private const EXTRA_BLOCKED_V4 = ['100.64.0.0/10', '192.0.0.0/24', '198.18.0.0/15', '192.0.2.0/24', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/3'];

    /** @param list<string> $trustedHosts host (atau akhiran domain) yang selalu diizinkan */
    public static function isPublicHttps(string $url, array $trustedHosts = []): bool
    {
        return self::resolve($url, $trustedHosts) !== null;
    }

    /**
     * @param  list<string>  $trustedHosts
     * @return array{host: string, ip: string|null}|null null bila ditolak; ip null bila host tepercaya (tanpa penyematan)
     */
    public static function resolve(string $url, array $trustedHosts = []): ?array
    {
        $parts = parse_url($url);
        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || isset($parts['user']) || isset($parts['pass']) || ! isset($parts['host'])) {
            return null;
        }
        if (isset($parts['port']) && $parts['port'] !== 443) {
            return null;
        }
        $host = strtolower(rtrim($parts['host'], '.'));
        /** @var list<string> $configured */
        $configured = (array) config('security.outbound_allow_hosts', []);
        foreach (array_merge($trustedHosts, $configured) as $trusted) {
            if ($host === $trusted || str_ends_with($host, '.'.$trusted)) {
                return ['host' => $host, 'ip' => null];
            }
        }
        $literal = trim($host, '[]');
        if (filter_var($literal, FILTER_VALIDATE_IP) !== false) {
            return self::isPublicIp($literal) ? ['host' => $host, 'ip' => $literal] : null;
        }
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (! is_array($records) || $records === []) {
            return null;
        }
        $chosen = null;
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (! is_string($ip) || ! self::isPublicIp($ip)) {
                return null; // satu alamat privat saja → tolak (anti DNS rebinding sederhana)
            }
            $chosen ??= $ip;
        }

        return ['host' => $host, 'ip' => $chosen];
    }

    /**
     * Opsi cURL untuk menyematkan alamat IP yang sudah diperiksa pada koneksi ke host tersebut.
     *
     * @param  array{host: string, ip: string|null}  $target
     * @return array<int, mixed>
     */
    public static function curlPin(array $target): array
    {
        if ($target['ip'] === null) {
            return [];
        }
        $ip = str_contains($target['ip'], ':') ? '['.$target['ip'].']' : $target['ip'];

        return [CURLOPT_RESOLVE => [$target['host'].':443:'.$ip]];
    }

    public static function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            foreach (self::EXTRA_BLOCKED_V4 as $cidr) {
                if (self::inCidr($ip, $cidr)) {
                    return false;
                }
            }

            return true;
        }
        $packed = inet_pton($ip);
        if ($packed === false) {
            return false;
        }
        // NAT64 (64:ff9b::/96) dan IPv4-mapped (::ffff:0:0/96): nilai sesuai alamat IPv4 di dalamnya.
        $prefix = bin2hex(substr($packed, 0, 12));
        if ($prefix === '0064ff9b0000000000000000' || $prefix === '00000000000000000000ffff') {
            return self::isPublicIp(inet_ntop(substr($packed, 12)) ?: '');
        }
        // Unique local (fc00::/7), link-local (fe80::/10), multicast (ff00::/8), documentation (2001:db8::/32).
        $first = ord($packed[0]);
        $second = ord($packed[1]);

        return ! (($first & 0xFE) === 0xFC || ($first === 0xFE && ($second & 0xC0) === 0x80) || $first === 0xFF || ($first === 0x20 && $second === 0x01 && ord($packed[2]) === 0x0D && ord($packed[3]) === 0xB8));
    }

    private static function inCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $mask = -1 << (32 - (int) $bits);

        return ((int) ip2long($ip) & $mask) === ((int) ip2long($subnet) & $mask);
    }
}
