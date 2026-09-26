<?php

declare(strict_types=1);

namespace App\Modules\Notification\Services;

use App\Modules\Cms\Models\SiteProfile;
use App\Modules\Notification\Models\PushSubscription;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Web Push tanpa pustaka pihak ketiga: VAPID (RFC 8292, JWT ES256) + enkripsi payload
 * aes128gcm (RFC 8291/8188) memakai OpenSSL bawaan PHP. Kunci VAPID disimpan di Pengaturan
 * Sistem → Integrasi (privat terenkripsi).
 */
final class WebPush
{
    private const CURVE_SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    public static function configured(): bool
    {
        return (bool) setting('push.enabled') && self::publicKey() !== '' && (string) setting('push.vapid_private') !== '';
    }

    public static function publicKey(): string
    {
        return (string) setting('push.vapid_public');
    }

    /**
     * Bangkitkan pasangan kunci VAPID P-256 (base64url: publik 65 byte tak terkompresi, privat 32 byte).
     *
     * @return array{public: string, private: string}
     */
    public static function generateKeys(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $details = $key === false ? false : openssl_pkey_get_details($key);
        if ($details === false || ! isset($details['ec']['x'], $details['ec']['y'], $details['ec']['d'])) {
            throw new RuntimeException('OpenSSL tidak dapat membangkitkan kunci P-256.');
        }

        return [
            'public' => self::b64("\x04".str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT)),
            'private' => self::b64(str_pad($details['ec']['d'], 32, "\0", STR_PAD_LEFT)),
        ];
    }

    /**
     * Kirim satu pesan terenkripsi ke satu langganan.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, status: int, gone: bool, error: string|null}
     */
    public function send(PushSubscription $subscription, array $payload, int $ttl = 86400): array
    {
        $endpoint = $subscription->endpoint;
        $origin = parse_url($endpoint, PHP_URL_SCHEME).'://'.parse_url($endpoint, PHP_URL_HOST);
        $body = self::encrypt((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $subscription->p256dh, $subscription->auth);
        $jwt = self::vapidToken($origin);

        try {
            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'vapid t='.$jwt.', k='.self::publicKey(),
                'Content-Type' => 'application/octet-stream',
                'Content-Encoding' => 'aes128gcm',
                'TTL' => (string) $ttl,
                'Urgency' => 'normal',
            ])->withBody($body, 'application/octet-stream')->post($endpoint);
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 0, 'gone' => false, 'error' => $e->getMessage()];
        }
        $status = $response->status();

        return ['ok' => $response->successful(), 'status' => $status, 'gone' => in_array($status, [404, 410], true), 'error' => $response->successful() ? null : 'HTTP '.$status];
    }

    /** Token VAPID (JWT ES256) untuk satu origin layanan push, berlaku 12 jam. */
    public static function vapidToken(string $audience, ?string $privateKey = null, ?string $publicKey = null): string
    {
        $subject = (string) setting('push.subject');
        if ($subject === '') {
            $email = SiteProfile::current()->email;
            $subject = $email !== null && $email !== '' ? 'mailto:'.$email : 'https://'.(string) parse_url((string) config('app.url'), PHP_URL_HOST);
        }
        $header = self::b64((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::b64((string) json_encode(['aud' => $audience, 'exp' => time() + 12 * 3600, 'sub' => $subject]));
        $signingInput = $header.'.'.$claims;
        $pem = self::privateKeyPem($privateKey ?? (string) setting('push.vapid_private'), $publicKey ?? self::publicKey());
        $key = openssl_pkey_get_private($pem);
        if ($key === false || ! openssl_sign($signingInput, $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Gagal menandatangani token VAPID.');
        }

        return $signingInput.'.'.self::b64(self::derToRaw($der));
    }

    /** Enkripsi payload aes128gcm (RFC 8291) untuk kunci peramban p256dh/auth (base64url). */
    public static function encrypt(string $plaintext, string $p256dh, string $auth, ?string $salt = null, ?\OpenSSLAsymmetricKey $ephemeral = null): string
    {
        $uaPublic = self::unb64($p256dh);
        $authSecret = self::unb64($auth);
        if (strlen($uaPublic) !== 65 || strlen($authSecret) !== 16) {
            throw new RuntimeException('Kunci langganan push tidak valid.');
        }
        $local = $ephemeral ?? openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $details = $local === false ? false : openssl_pkey_get_details($local);
        if ($local === false || $details === false || ! isset($details['ec']['x'], $details['ec']['y'])) {
            throw new RuntimeException('OpenSSL tidak dapat membangkitkan kunci sementara.');
        }
        $asPublic = "\x04".str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
        $peer = openssl_pkey_get_public(self::publicKeyPem($uaPublic));
        $shared = $peer === false ? false : openssl_pkey_derive($peer, $local, 32);
        if ($shared === false) {
            throw new RuntimeException('ECDH gagal.');
        }
        $salt ??= random_bytes(16);
        $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0".$uaPublic.$asPublic, $authSecret);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $tag = '';
        $cipher = openssl_encrypt($plaintext."\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            throw new RuntimeException('Enkripsi payload gagal.');
        }

        return $salt.pack('N', 4096).chr(65).$asPublic.$cipher.$tag;
    }

    /** Dekripsi (untuk pengujian) memakai kunci privat penerima. */
    public static function decrypt(string $body, \OpenSSLAsymmetricKey $recipient, string $auth): string
    {
        $salt = substr($body, 0, 16);
        $asPublic = substr($body, 21, 65);
        $cipherAndTag = substr($body, 86);
        $details = openssl_pkey_get_details($recipient);
        if ($details === false || ! isset($details['ec']['x'], $details['ec']['y'])) {
            throw new RuntimeException('Kunci penerima tidak valid.');
        }
        $uaPublic = "\x04".str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
        $peer = openssl_pkey_get_public(self::publicKeyPem($asPublic));
        $shared = $peer === false ? false : openssl_pkey_derive($peer, $recipient, 32);
        if ($shared === false) {
            throw new RuntimeException('ECDH gagal.');
        }
        $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0".$uaPublic.$asPublic, self::unb64($auth));
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $plain = openssl_decrypt(substr($cipherAndTag, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($cipherAndTag, -16));
        if ($plain === false) {
            throw new RuntimeException('Dekripsi gagal.');
        }

        return rtrim(rtrim($plain, "\0"), "\x02");
    }

    public static function b64(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    public static function unb64(string $text): string
    {
        return (string) base64_decode(strtr($text, '-_', '+/').str_repeat('=', (4 - strlen($text) % 4) % 4), true);
    }

    public static function publicKeyPem(string $uncompressedPoint): string
    {
        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode(hex2bin(self::CURVE_SPKI_PREFIX).$uncompressedPoint), 64, "\n").'-----END PUBLIC KEY-----';
    }

    private static function privateKeyPem(string $privateB64, string $publicB64): string
    {
        $d = self::unb64($privateB64);
        $pub = self::unb64($publicB64);
        if (strlen($d) !== 32 || strlen($pub) !== 65) {
            throw new RuntimeException('Kunci VAPID belum dikonfigurasi dengan benar.');
        }
        $der = hex2bin('30770201010420').$d.hex2bin('a00a06082a8648ce3d030107a144034200').$pub;

        return "-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode((string) $der), 64, "\n").'-----END EC PRIVATE KEY-----';
    }

    /** Tanda tangan DER (SEQUENCE{r,s}) → r||s 64 byte (format JWS). */
    private static function derToRaw(string $der): string
    {
        $offset = 2;
        if (ord($der[1]) & 0x80) {
            $offset += ord($der[1]) & 0x7F;
        }
        $raw = '';
        for ($i = 0; $i < 2; $i++) {
            $length = ord($der[$offset + 1]);
            $value = substr($der, $offset + 2, $length);
            $value = ltrim($value, "\0");
            $raw .= str_pad($value, 32, "\0", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return $raw;
    }
}
