<?php

declare(strict_types=1);

namespace App\Modules\Notification\Services;

use Illuminate\Support\Facades\Http;

/**
 * Gateway WhatsApp generik (HTTP POST ke endpoint yang dikonfigurasi di Pengaturan → Integrasi).
 * Nomor dalam format E.164 tanpa tanda plus (mis. 62812xxxx) — format yang lazim di gateway.
 */
final class WhatsAppGateway
{
    public static function configured(): bool
    {
        return (bool) setting('whatsapp.enabled') && (string) setting('whatsapp.endpoint') !== '' && (string) setting('whatsapp.token') !== '';
    }

    /** @return array{ok: bool, status: int, error: string|null} */
    public function send(string $phoneE164, string $message): array
    {
        $to = ltrim($phoneE164, '+');
        $fields = array_filter(['to' => $to, 'message' => $message, 'sender' => (string) setting('whatsapp.sender')], fn ($v) => $v !== '');
        $request = Http::timeout(15)->withToken((string) setting('whatsapp.token'))->acceptJson();

        try {
            $response = setting('whatsapp.payload') === 'form' ? $request->asForm()->post((string) setting('whatsapp.endpoint'), $fields) : $request->post((string) setting('whatsapp.endpoint'), $fields);
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 0, 'error' => $e->getMessage()];
        }

        return ['ok' => $response->successful(), 'status' => $response->status(), 'error' => $response->successful() ? null : 'HTTP '.$response->status().' '.mb_substr($response->body(), 0, 200)];
    }

    public static function mask(string $phone): string
    {
        return strlen($phone) > 6 ? substr($phone, 0, 5).str_repeat('•', strlen($phone) - 8).substr($phone, -3) : '•••';
    }
}
