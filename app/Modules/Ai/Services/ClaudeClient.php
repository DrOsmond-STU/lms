<?php

declare(strict_types=1);

namespace App\Modules\Ai\Services;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Klien Claude Messages API (https://api.anthropic.com/v1/messages). Kunci API disimpan
 * terenkripsi di Pengaturan → Integrasi. Setiap panggilan dicatat (token, durasi, status) dan
 * dibatasi per pengguna per hari. Konten pengguna dikirim seperlunya (materi, jawaban), tanpa
 * data identitas selain nama depan bila relevan.
 */
final class ClaudeClient
{
    public const MODELS = [
        'claude-sonnet-5' => 'Claude Sonnet 5 (seimbang)',
        'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (cepat & hemat)',
        'claude-opus-5-5' => 'Claude Opus 5.5 (paling mampu)',
    ];

    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    public static function configured(): bool
    {
        return (bool) setting('ai.enabled') && (string) setting('ai.api_key') !== '';
    }

    public static function model(): string
    {
        $model = (string) setting('ai.model');

        return array_key_exists($model, self::MODELS) ? $model : (string) array_key_first(self::MODELS);
    }

    /** Sisa kuota harian pengguna (0 bila habis). */
    public static function remainingToday(User $user): int
    {
        $limit = max(1, (int) setting('ai.daily_limit'));
        $used = DB::table('ai_usages')->where('user_id', $user->id)->where('created_at', '>=', now()->startOfDay())->count();

        return max(0, $limit - $used);
    }

    /**
     * Satu putaran percakapan; mengembalikan teks jawaban.
     *
     * @param  list<array{role: 'user'|'assistant', content: string}>  $messages
     *
     * @throws AiUnavailableException
     */
    public function complete(User $user, string $feature, string $system, array $messages, int $maxTokens = 1024, float $temperature = 0.3): string
    {
        if (! self::configured()) {
            throw new AiUnavailableException('Asisten AI belum diaktifkan admin.');
        }
        if (self::remainingToday($user) <= 0) {
            throw new AiUnavailableException('Kuota asisten AI harian Anda habis. Coba lagi besok.');
        }
        $model = self::model();
        $started = hrtime(true);
        try {
            $response = Http::timeout(90)->withHeaders([
                'x-api-key' => (string) setting('ai.api_key'),
                'anthropic-version' => '2023-06-01',
            ])->post(self::ENDPOINT, [
                'model' => $model, 'max_tokens' => $maxTokens, 'temperature' => $temperature,
                'system' => $system, 'messages' => $messages,
            ]);
        } catch (\Throwable $e) {
            $this->log($user, $feature, $model, 0, 0, $started, 'error', $e->getMessage());
            throw new AiUnavailableException('Asisten AI tidak dapat dihubungi. Coba lagi nanti.');
        }
        if (! $response->successful()) {
            $error = (string) ($response->json('error.message') ?? ('HTTP '.$response->status()));
            $this->log($user, $feature, $model, 0, 0, $started, 'error', $error);
            throw new AiUnavailableException('Asisten AI menolak permintaan ('.Str::limit($error, 120).').');
        }
        $content = $response->json('content');
        $texts = [];
        foreach (is_array($content) ? $content : [] as $part) {
            if (is_array($part) && ($part['type'] ?? null) === 'text' && is_string($part['text'] ?? null)) {
                $texts[] = $part['text'];
            }
        }
        $text = implode("\n", $texts);
        $this->log($user, $feature, $model, (int) $response->json('usage.input_tokens', 0), (int) $response->json('usage.output_tokens', 0), $started, 'ok', null);

        return trim($text);
    }

    /**
     * Minta keluaran JSON dan uraikan (pagar kode dibuang).
     *
     * @param  list<array{role: 'user'|'assistant', content: string}>  $messages
     * @return array<mixed>
     */
    public function json(User $user, string $feature, string $system, array $messages, int $maxTokens = 4096): array
    {
        $text = $this->complete($user, $feature, $system."\nJawab HANYA dengan JSON valid tanpa teks lain dan tanpa pagar kode.", $messages, $maxTokens, 0.2);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $text) ?? $text;
        $start = strpos($text, '{');
        $startArr = strpos($text, '[');
        if ($start === false || ($startArr !== false && $startArr < $start)) {
            $start = $startArr;
        }
        $decoded = $start === false ? null : json_decode(substr($text, $start), true);
        if (! is_array($decoded)) {
            throw new AiUnavailableException('Jawaban AI tidak dapat diuraikan. Coba lagi.');
        }

        return $decoded;
    }

    private function log(User $user, string $feature, string $model, int $in, int $out, int $started, string $status, ?string $error): void
    {
        DB::table('ai_usages')->insert([
            'id' => (string) Str::uuid7(), 'user_id' => $user->id, 'feature' => $feature, 'model' => $model,
            'input_tokens' => $in, 'output_tokens' => $out, 'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            'status' => $status, 'error' => $error !== null ? mb_substr($error, 0, 300) : null, 'created_at' => now(),
        ]);
    }
}
