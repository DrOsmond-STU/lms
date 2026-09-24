<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Soal (K4 bersama kuncinya). Kunci isian singkat disimpan terenkripsi.
 *
 * @property string $id
 * @property string $question_bank_id
 * @property string $type
 * @property string $stem_md
 * @property string $stem_html
 * @property string|null $explanation_html
 * @property int $difficulty
 * @property list<string> $competency_tags
 * @property string $points
 * @property list<string>|null $accepted_answers_encrypted
 * @property bool $is_active
 * @property int $version
 * @property-read QuestionBank $bank
 */
final class Question extends Model
{
    use HasUuids;

    public const TYPES = [
        'single_choice' => 'Pilihan ganda (1 jawaban)',
        'multiple_choice' => 'Pilihan ganda (multi jawaban)',
        'true_false' => 'Benar / Salah',
        'short_answer' => 'Isian singkat',
        'essay' => 'Esai (dinilai manual)',
    ];

    /** @var list<string> */
    protected $fillable = [];

    /** @var list<string> */
    protected $hidden = ['accepted_answers_encrypted'];

    protected function casts(): array
    {
        return [
            'difficulty' => 'integer',
            'competency_tags' => 'array',
            'accepted_answers_encrypted' => 'encrypted:array',
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<QuestionBank, $this> */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    /** @return HasMany<QuestionOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('position');
    }

    public function isChoice(): bool
    {
        return in_array($this->type, ['single_choice', 'multiple_choice', 'true_false'], true);
    }
}
