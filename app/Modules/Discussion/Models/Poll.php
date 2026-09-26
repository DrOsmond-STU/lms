<?php

declare(strict_types=1);

namespace App\Modules\Discussion\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Polling kelas.
 *
 * @property string $id
 * @property string $course_class_id
 * @property string $question
 * @property list<string> $options
 * @property bool $is_anonymous
 * @property bool $multiple
 * @property Carbon|null $closes_at
 * @property bool $is_closed
 * @property string|null $created_by
 * @property Carbon $created_at
 */
final class Poll extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['options' => 'array', 'is_anonymous' => 'boolean', 'multiple' => 'boolean', 'closes_at' => 'datetime', 'is_closed' => 'boolean'];
    }

    /** @return HasMany<PollVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    public function isOpen(): bool
    {
        return ! $this->is_closed && ($this->closes_at === null || $this->closes_at->isFuture());
    }

    /**
     * Jumlah suara per opsi (indeks → jumlah).
     *
     * @return array<int, int>
     */
    public function tally(): array
    {
        $counts = array_fill(0, count($this->options), 0);
        foreach ($this->votes as $vote) {
            foreach ($vote->option_indexes as $index) {
                if (isset($counts[$index])) {
                    $counts[$index]++;
                }
            }
        }

        return $counts;
    }
}
