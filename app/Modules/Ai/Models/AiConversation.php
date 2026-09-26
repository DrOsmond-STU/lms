<?php

declare(strict_types=1);

namespace App\Modules\Ai\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Riwayat percakapan tutor AI per peserta per materi (20 pesan terakhir).
 *
 * @property string $id
 * @property string $user_id
 * @property string $enrollment_id
 * @property string|null $lesson_id
 * @property list<array{role: 'user'|'assistant', content: string, at?: string}> $messages
 */
final class AiConversation extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['messages' => 'array'];
    }
}
