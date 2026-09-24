<?php

declare(strict_types=1);

namespace App\Modules\Certification\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Sumber kunci & sertifikat penandatangan PDF (FR-CERT-005, keamanan/07 §2.2).
 *
 * Staging memakai sertifikat uji (self-signed) yang dibangkitkan `stu:signing-key`; kunci
 * privat disimpan TERENKRIPSI (APP_KEY) di disk privat. Produksi mengganti implementasi ini
 * dengan penandatangan KMS/HSM + sertifikat PSrE tanpa mengubah domain.
 */
final class CertificateSigner
{
    public const CERT_PATH = 'signing/signer.crt';

    public const KEY_PATH = 'signing/signer.key.enc';

    public function isConfigured(): bool
    {
        return Storage::disk('local')->exists(self::CERT_PATH) && Storage::disk('local')->exists(self::KEY_PATH);
    }

    public function certificatePem(): string
    {
        return (string) Storage::disk('local')->get(self::CERT_PATH);
    }

    public function privateKeyPem(): string
    {
        $encrypted = Storage::disk('local')->get(self::KEY_PATH);
        if ($encrypted === null) {
            throw new RuntimeException('Kunci penandatangan belum dibuat. Jalankan php artisan stu:signing-key.');
        }

        return Crypt::decryptString($encrypted);
    }

    public function fingerprint(): string
    {
        $fingerprint = openssl_x509_fingerprint($this->certificatePem(), 'sha256');

        return is_string($fingerprint) ? $fingerprint : '';
    }

    /** Membuat pasangan kunci RSA-3072 + sertifikat uji berlaku 3 tahun. */
    public function generate(string $commonName, string $organization): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($key === false) {
            throw new RuntimeException('Gagal membuat kunci.');
        }
        $csr = openssl_csr_new(['commonName' => $commonName, 'organizationName' => $organization, 'countryName' => 'ID'], $key, ['digest_alg' => 'sha256']);
        if (! $csr instanceof \OpenSSLCertificateSigningRequest) {
            throw new RuntimeException('Gagal membuat CSR.');
        }
        $certificate = openssl_csr_sign($csr, null, $key, 1095, ['digest_alg' => 'sha256'], random_int(1, PHP_INT_MAX));
        if ($certificate === false || ! openssl_x509_export($certificate, $certPem) || ! openssl_pkey_export($key, $keyPem)) {
            throw new RuntimeException('Gagal menandatangani sertifikat uji.');
        }

        Storage::disk('local')->put(self::CERT_PATH, $certPem);
        Storage::disk('local')->put(self::KEY_PATH, Crypt::encryptString($keyPem));
    }
}
