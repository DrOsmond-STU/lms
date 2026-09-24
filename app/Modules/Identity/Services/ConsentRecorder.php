<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mencatat persetujuan S&K dan Kebijakan Privasi beserta versinya (docs/08 PUB-03,
 * keamanan/12 — bukti persetujuan UU PDP).
 */
final class ConsentRecorder
{
    public function record(User $user, string $channel, Request $request): void
    {
        $rows = [];
        foreach (['terms' => 'legal.terms_version', 'privacy' => 'legal.privacy_version'] as $document => $key) {
            $rows[] = [
                'id' => (string) Str::uuid7(),
                'user_id' => $user->id,
                'document' => $document,
                'version' => (string) config($key),
                'accepted_at' => now(),
                'channel' => $channel,
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            ];
        }

        DB::table('consents')->insert($rows);
    }
}
