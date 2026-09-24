<?php

declare(strict_types=1);

namespace App\Support\Media;

/**
 * Klien daemon ClamAV via soket (protokol INSTREAM) — tanpa menjalankan proses eksternal.
 */
final class ClamdScanner implements MalwareScanner
{
    private const CHUNK = 8192;

    public function __construct(private readonly string $address, private readonly int $timeoutSeconds = 30) {}

    public function scan(string $absolutePath): ScanResult
    {
        $socket = @stream_socket_client($this->address, $errno, $error, $this->timeoutSeconds);
        $file = @fopen($absolutePath, 'rb');
        if ($socket === false || $file === false) {
            return new ScanResult('error', 'clamd');
        }

        stream_set_timeout($socket, $this->timeoutSeconds);
        fwrite($socket, "zINSTREAM\0");
        while (! feof($file)) {
            $chunk = (string) fread($file, self::CHUNK);
            if ($chunk === '') {
                break;
            }
            fwrite($socket, pack('N', strlen($chunk)).$chunk);
        }
        fwrite($socket, pack('N', 0));
        fclose($file);

        $reply = trim((string) stream_get_contents($socket), "\0\n ");
        fclose($socket);

        if (str_ends_with($reply, 'OK')) {
            return new ScanResult('clean', 'clamd');
        }
        if (preg_match('/:\s*(.+)\s+FOUND$/', $reply, $match) === 1) {
            return new ScanResult('infected', 'clamd', mb_substr($match[1], 0, 120));
        }

        return new ScanResult('error', 'clamd');
    }
}
