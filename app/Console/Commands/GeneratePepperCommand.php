<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Mengisi SECURITY_PEPPER di .env lokal (setara key:generate). Produksi mengambil
 * pepper dari secret manager, bukan dari berkas .env (keamanan/06 SEC-CRYPTO-12).
 */
final class GeneratePepperCommand extends Command
{
    protected $signature = 'stu:pepper {--force : Timpa pepper yang sudah ada}';

    protected $description = 'Membangkitkan SECURITY_PEPPER acak untuk berkas .env lokal';

    public function handle(): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('Di produksi, pepper dikelola melalui secret manager.');

            return self::FAILURE;
        }

        $path = $this->laravel->environmentFilePath();
        $contents = is_file($path) ? (string) file_get_contents($path) : '';

        if (preg_match('/^SECURITY_PEPPER=(.+)$/m', $contents) === 1 && ! $this->option('force')) {
            $this->warn('SECURITY_PEPPER sudah terisi. Gunakan --force untuk mengganti (token/OTP lama menjadi tidak valid).');

            return self::SUCCESS;
        }

        $line = 'SECURITY_PEPPER='.base64_encode(random_bytes(32));
        $contents = preg_match('/^SECURITY_PEPPER=.*$/m', $contents) === 1
            ? (string) preg_replace('/^SECURITY_PEPPER=.*$/m', $line, $contents)
            : rtrim($contents).PHP_EOL.$line.PHP_EOL;

        file_put_contents($path, $contents);
        $this->info('SECURITY_PEPPER berhasil dibuat.');

        return self::SUCCESS;
    }
}
