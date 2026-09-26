<?php

declare(strict_types=1);

namespace App\Modules\Discussion\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Balasan dalam utas.
 *
 * @property string $id
 * @property string $thread_id
 * @property string $author_id
 * @property string $body
 * @property string $body_html
 * @property bool $is_answer
 * @property bool $is_hidden
 * @property string|null $hidden_reason
 * @property Carbon $created_at
 * @property-read User $author
 * @property-read DiscussionThread $thread
 */
final class DiscussionPost extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['is_answer' => 'boolean', 'is_hidden' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<DiscussionThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(DiscussionThread::class, 'thread_id');
    }
}
