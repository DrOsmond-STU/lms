<?php

declare(strict_types=1);

namespace App\Modules\Security\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Security\Models\Backup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Backup otomatis: dump basis data (pg_dump format custom; fallback ekspor JSONL per tabel
 * bila pg_dump tidak tersedia di hosting) dan arsip media (storage/app/private). Berkas
 * disimpan di storage/app/backups, opsional dienkripsi (libsodium secretstream) dengan
 * BACKUP_ENCRYPTION_KEY, dan dipangkas sesuai masa simpan.
 */
final class BackupService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public static function directory(): string
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        return $dir;
    }

    public static function encryptionKey(): ?string
    {
        $raw = (string) config('security.backup_encryption_key', '');
        if ($raw === '') {
            return null;
        }
        $key = base64_decode($raw, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY harus base64 dari 32 byte acak.');
        }

        return $key;
    }

    public function database(?User $actor = null): Backup
    {
        $backup = $this->start('db', 'db-'.now()->format('Ymd-His'), $actor);
        try {
            $plain = self::directory().'/'.$backup->filename.'.tmp';
            $method = $this->pgDump($plain) ? 'pg_dump' : $this->phpDump($plain);
            $this->finish($backup, $plain, $method);
        } catch (\Throwable $e) {
            $backup->forceFill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500), 'finished_at' => now()])->save();
        }
        $this->audit->record('backup.database', $actor, 'backup', $backup->id, ['status' => $backup->status, 'method' => $backup->method]);

        return $backup;
    }

    public function media(?User $actor = null): Backup
    {
        $backup = $this->start('media', 'media-'.now()->format('Ymd-His'), $actor);
        try {
            $plain = self::directory().'/'.$backup->filename.'.tmp';
            $source = storage_path('app/private');
            $tar = (new ExecutableFinder)->find('tar');
            if ($tar !== null && is_dir($source)) {
                $process = new Process([$tar, '-czf', $plain, '-C', dirname($source), basename($source)]);
                $process->setTimeout(3600)->run();
                if (! $process->isSuccessful()) {
                    throw new RuntimeException('tar gagal: '.Str::limit($process->getErrorOutput(), 300));
                }
                $method = 'tar';
            } else {
                $this->zipDirectory($source, $plain);
                $method = 'zip';
            }
            $this->finish($backup, $plain, $method);
        } catch (\Throwable $e) {
            $backup->forceFill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500), 'finished_at' => now()])->save();
        }
        $this->audit->record('backup.media', $actor, 'backup', $backup->id, ['status' => $backup->status]);

        return $backup;
    }

    /** Hapus berkas & catatan yang lebih tua dari masa simpan (hari). */
    public function prune(int $keepDays): int
    {
        $count = 0;
        Backup::query()->where('started_at', '<', now()->subDays($keepDays))->get()->each(function (Backup $backup) use (&$count): void {
            $path = self::directory().'/'.$backup->filename;
            if (is_file($path)) {
                unlink($path);
            }
            $backup->delete();
            $count++;
        });

        return $count;
    }

    public static function path(Backup $backup): string
    {
        return self::directory().'/'.$backup->filename;
    }

    private function start(string $kind, string $base, ?User $actor): Backup
    {
        $backup = new Backup;
        $extension = $kind === 'db' ? '.dump' : '.tar.gz';
        $backup->forceFill(['kind' => $kind, 'filename' => $base.$extension.(self::encryptionKey() !== null ? '.enc' : ''), 'status' => 'running', 'started_at' => now(), 'created_by' => $actor?->id, 'encrypted' => self::encryptionKey() !== null])->save();

        return $backup;
    }

    private function finish(Backup $backup, string $plain, string $method): void
    {
        $final = self::path($backup);
        $key = self::encryptionKey();
        if ($key !== null) {
            $this->encryptFile($plain, $final, $key);
            unlink($plain);
        } else {
            rename($plain, $final);
        }
        chmod($final, 0600);
        $backup->forceFill(['status' => 'ok', 'method' => $method, 'size_bytes' => (int) filesize($final), 'checksum' => hash_file('sha256', $final), 'finished_at' => now()])->save();
    }

    /** pg_dump format custom (terkompresi); false bila biner tidak tersedia. */
    private function pgDump(string $target): bool
    {
        $binary = (new ExecutableFinder)->find('pg_dump');
        if ($binary === null) {
            return false;
        }
        /** @var array{host: string, port: string|int, database: string, username: string, password: string, sslmode?: string} $db */
        $db = config('database.connections.pgsql_migrator');
        // Tabel ber-RLS (FORCE) juga membatasi pemiliknya: sesi dump diberi konteks staf platform
        // (semua baris terlihat) dan pg_dump diminta menghormati RLS alih-alih gagal.
        $process = new Process([$binary, '--format=custom', '--no-owner', '--no-privileges', '--enable-row-security', '--file='.$target, '--host='.$db['host'], '--port='.(string) $db['port'], '--username='.$db['username'], $db['database']],
            null, ['PGPASSWORD' => $db['password'], 'PGSSLMODE' => $db['sslmode'] ?? 'prefer', 'PGOPTIONS' => '-c app.is_platform_staff=on']);
        $process->setTimeout(1800)->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('pg_dump gagal: '.Str::limit($process->getErrorOutput(), 300));
        }

        return true;
    }

    /** Fallback tanpa pg_dump: setiap tabel diekspor sebagai JSON Lines ke dalam ZIP (+ manifest). */
    private function phpDump(string $target): string
    {
        $zip = new ZipArchive;
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak dapat membuat arsip backup.');
        }
        $connection = DB::connection('pgsql_migrator');
        $connection->statement("select set_config('app.is_platform_staff', 'on', false)");
        $tables = $connection->select("select tablename from pg_tables where schemaname = 'public' order by tablename");
        $manifest = ['generated_at' => now()->toIso8601String(), 'database' => $connection->getDatabaseName(), 'tables' => []];
        foreach ($tables as $row) {
            $table = (string) $row->tablename;
            $tmp = tempnam(sys_get_temp_dir(), 'bk');
            if ($tmp === false) {
                throw new RuntimeException('Tidak dapat membuat berkas sementara.');
            }
            $handle = fopen($tmp, 'w');
            if ($handle === false) {
                throw new RuntimeException('Tidak dapat menulis berkas sementara.');
            }
            $count = 0;
            foreach ($connection->table($table)->lazy(1000) as $record) {
                fwrite($handle, json_encode($record, JSON_UNESCAPED_UNICODE)."\n");
                $count++;
            }
            fclose($handle);
            $zip->addFile($tmp, 'tables/'.$table.'.jsonl');
            $manifest['tables'][$table] = $count;
        }
        $zip->addFromString('manifest.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));
        $zip->close();

        return 'php-jsonl';
    }

    private function zipDirectory(string $source, string $target): void
    {
        $zip = new ZipArchive;
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak dapat membuat arsip media.');
        }
        if (is_dir($source)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile()) {
                    $zip->addFile($file->getPathname(), substr($file->getPathname(), strlen($source) + 1));
                }
            }
        }
        $zip->close();
    }

    /** Enkripsi streaming libsodium (XChaCha20-Poly1305 secretstream), header 24 byte di awal berkas. */
    private function encryptFile(string $source, string $target, string $key): void
    {
        $in = fopen($source, 'rb');
        $out = fopen($target, 'wb');
        if ($in === false || $out === false) {
            throw new RuntimeException('Tidak dapat membuka berkas untuk enkripsi.');
        }
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        fwrite($out, $header);
        while (! feof($in)) {
            $chunk = fread($in, 1024 * 1024);
            if ($chunk === false) {
                break;
            }
            $tag = feof($in) ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
            fwrite($out, sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag));
        }
        fclose($in);
        fclose($out);
    }

    /** Dekripsi (untuk pemulihan/pengujian). */
    public static function decryptFile(string $source, string $target, string $key): void
    {
        $in = fopen($source, 'rb');
        $out = fopen($target, 'wb');
        if ($in === false || $out === false) {
            throw new RuntimeException('Tidak dapat membuka berkas untuk dekripsi.');
        }
        $header = fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        if ($header === false) {
            throw new RuntimeException('Header tidak terbaca.');
        }
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
        while (! feof($in)) {
            $chunk = fread($in, 1024 * 1024 + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $chunk);
            if ($result === false) {
                throw new RuntimeException('Berkas backup rusak atau kunci salah.');
            }
            fwrite($out, $result[0]);
        }
        fclose($in);
        fclose($out);
    }
}
