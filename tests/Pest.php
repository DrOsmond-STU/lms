<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;
use App\Modules\Access\Services\RoleAssigner;
use App\Modules\Assessment\Models\Assessment;
use App\Modules\Assessment\Models\ExamAttempt;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\QuestionBank;
use App\Modules\Assessment\Models\QuestionOption;
use App\Modules\Assessment\Services\AttemptService;
use App\Modules\Catalog\Models\Program;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\MfaService;
use App\Modules\Learning\Models\Chapter;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\Module;
use App\Modules\Organization\Models\Organization;
use App\Support\Security\Middleware\RequireRecentAuth;
use App\Support\Security\Totp;
use App\Support\Tenancy\TenantContext;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(DatabaseTransactions::class)->in('Feature', 'Security');
pest()->extend(TestCase::class)->in('Unit', 'Arch');

/*
| Helper persona (data sintetis — docs/10 §4).
*/

function asSystem(Closure $callback): mixed
{
    $tenant = app(TenantContext::class);
    $tenant->applySystem();
    try {
        return $callback();
    } finally {
        $tenant->clear();
    }
}

function makeOrganization(string $type = 'institution'): Organization
{
    return asSystem(fn () => Organization::factory()->state(['type' => $type])->create());
}

function makeUser(RoleCode $role = RoleCode::Participant, ?Organization $organization = null, array $attributes = []): User
{
    return asSystem(function () use ($role, $organization, $attributes): User {
        $organization ??= $role->isPlatform() ? null : Organization::factory()->create();
        $user = User::factory()->create(array_merge(['primary_organization_id' => $organization?->id], $attributes));
        app(RoleAssigner::class)->assign($user, $role, $organization?->id, null);

        if ($organization !== null) {
            DB::table('organization_members')->insert([
                'id' => (string) Str::uuid7(), 'organization_id' => $organization->id, 'user_id' => $user->id,
                'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $user->fresh();
    });
}

/** Mendaftarkan TOTP terkonfirmasi & mengembalikan secret-nya. */
function enrollTotp(User $user): string
{
    $secret = Totp::generateSecret();
    asSystem(fn () => app(MfaService::class)->confirmTotp($user, $secret, Totp::codeAt($secret, Totp::currentStep())));
    // Reset langkah terakhir agar kode saat ini dapat dipakai lagi pada uji login.
    DB::table('user_mfa_methods')->where('user_id', $user->id)->update(['last_totp_step' => Totp::currentStep() - 2]);

    return $secret;
}

/** Login penuh melalui HTTP (dengan MFA bila terdaftar). */
function loginAs(User $user, ?string $totpSecret = null): void
{
    test()->post('/masuk', ['email' => $user->email, 'password' => UserFactory::PASSWORD]);
    if ($totpSecret !== null) {
        test()->post('/masuk/mfa', ['code' => Totp::codeAt($totpSecret, Totp::currentStep())]);
    }
}

/**
 * Mensimulasikan request baru (seperti PHP-FPM): lupakan pengguna yang di-cache guard
 * sehingga data akun dimuat ulang dari basis data.
 */
function nextRequest(): void
{
    app('auth')->forgetGuards();
}

/** Login sebagai pengguna ber-peran (dengan MFA bila perannya wajib) lalu kembalikan pengguna. */
function signIn(RoleCode $role, ?Organization $organization = null): User
{
    $user = makeUser($role, $organization);
    loginAs($user, $role->requiresMfa() ? enrollTotp($user) : null);
    nextRequest();

    return $user;
}

/** Menandai sesi sudah re-autentikasi baru saja (middleware `reauth`). */
function confirmAccess(): void
{
    test()->withSession([RequireRecentAuth::SESSION_KEY => now()->getTimestamp()]);
}

/** Kata sandi uji yang dibangkitkan saat runtime (tanpa literal rahasia di repositori). */
function freshPassword(int $length = 24): string
{
    return 'Uji-'.Str::random($length);
}

/**
 * Program terbit + kelas dibuka + 2 lesson teks wajib + bank soal (pilihan tunggal, opsi
 * pertama benar) + ujian akhir (dan kuis opsional). Semua data sintetis.
 *
 * @param  array{price?: int, quota?: int, questions?: int, final_count?: int, quiz?: bool, final?: bool, max_attempts?: int, category?: string}  $options
 * @return array{program: Program, class: CourseClass, bank: QuestionBank, lessons: list<Lesson>, quiz: ?Assessment, final: ?Assessment}
 */
function makeCourse(array $options = []): array
{
    return asSystem(function () use ($options): array {
        $program = new Program;
        $code = 'T'.strtoupper(Str::random(6));
        $program->forceFill([
            'category' => $options['category'] ?? 'international', 'name' => 'Program Uji '.$code, 'slug' => strtolower($code),
            'provider_name' => 'Penyelenggara Uji', 'short_code' => $code, 'duration_hours' => 2, 'language' => 'id',
            'default_mode' => 'online', 'passing_score' => 70, 'certificate_validity_months' => 36, 'price' => $options['price'] ?? 0,
            'status' => 'published', 'published_at' => now(),
        ])->save();

        $class = new CourseClass;
        $class->forceFill([
            'program_id' => $program->id, 'batch_name' => 'Batch Uji', 'starts_on' => now()->toDateString(), 'ends_on' => now()->addMonth()->toDateString(),
            'quota' => $options['quota'] ?? 30, 'mode' => 'online', 'status' => 'open', 'completion_rules' => ['require_final_exam' => true],
        ])->save();

        $module = new Module;
        $module->forceFill(['course_class_id' => $class->id, 'title' => 'Modul Uji', 'position' => 1])->save();
        $chapter = new Chapter;
        $chapter->forceFill(['module_id' => $module->id, 'title' => 'Bab Uji', 'position' => 1])->save();
        $lessons = [];
        foreach ([1, 2] as $position) {
            $lesson = new Lesson;
            $lesson->forceFill(['chapter_id' => $chapter->id, 'type' => 'text', 'title' => 'Lesson '.$position, 'position' => $position,
                'is_required' => true, 'body_md' => 'Isi', 'body_html' => '<p>Isi</p>'])->save();
            $lessons[] = $lesson;
        }

        $bank = new QuestionBank;
        $bank->forceFill(['program_id' => $program->id, 'name' => 'Bank Uji'])->save();
        for ($i = 1; $i <= ($options['questions'] ?? 5); $i++) {
            $question = new Question;
            $question->forceFill(['question_bank_id' => $bank->id, 'type' => 'single_choice', 'stem_md' => 'Soal '.$i, 'stem_html' => '<p>Soal '.$i.'</p>', 'difficulty' => 3, 'points' => 1, 'competency_tags' => []])->save();
            foreach (['Benar', 'Salah A', 'Salah B'] as $position => $body) {
                $option = new QuestionOption;
                $option->forceFill(['question_id' => $question->id, 'body_html' => $body, 'is_correct' => $position === 0, 'position' => $position + 1])->save();
            }
        }

        $final = null;
        if ($options['final'] ?? true) {
            $final = new Assessment;
            $final->forceFill(['course_class_id' => $class->id, 'question_bank_id' => $bank->id, 'kind' => 'final_exam', 'title' => 'Ujian Akhir Uji',
                'duration_minutes' => 30, 'max_attempts' => $options['max_attempts'] ?? 2, 'question_count' => $options['final_count'] ?? 5, 'passing_score' => 70,
                'review_policy' => 'after_submit', 'is_required' => true, 'requires_prerequisites' => true])->save();
        }
        $quiz = null;
        if ($options['quiz'] ?? false) {
            $quiz = new Assessment;
            $quiz->forceFill(['course_class_id' => $class->id, 'question_bank_id' => $bank->id, 'kind' => 'quiz', 'title' => 'Kuis Uji',
                'duration_minutes' => 10, 'max_attempts' => 3, 'question_count' => 2, 'passing_score' => 50, 'review_policy' => 'after_submit',
                'is_required' => true, 'requires_prerequisites' => false])->save();
        }

        return ['program' => $program, 'class' => $class->fresh(), 'bank' => $bank, 'lessons' => $lessons, 'quiz' => $quiz, 'final' => $final];
    });
}

/** Daftarkan peserta (sudah login) ke kelas lewat HTTP dan kembalikan enrollment-nya. */
function enrollVia(CourseClass $class, User $participant): Enrollment
{
    test()->post(route('catalog.enroll', $class))->assertRedirect();

    return asSystem(fn () => Enrollment::query()->where('user_id', $participant->id)->where('course_class_id', $class->id)->firstOrFail());
}

/** Tandai semua lesson wajib selesai lewat HTTP. */
function completeLessons(Enrollment $enrollment, array $lessons): void
{
    foreach ($lessons as $lesson) {
        test()->get(route('learning.lesson', [$enrollment, $lesson]))->assertOk();
        test()->post(route('learning.complete', [$enrollment, $lesson]))->assertRedirect();
    }
}

/** Kerjakan attempt: jawab semua soal dengan opsi benar (atau salah) lalu kumpulkan. */
function answerAttempt(ExamAttempt $attempt, bool $correct = true): void
{
    $service = app(AttemptService::class);
    foreach ($attempt->question_order as $questionId) {
        $option = asSystem(fn () => QuestionOption::query()->where('question_id', $questionId)->where('is_correct', $correct)->first());
        test()->putJson(route('exams.answer', $attempt), [
            'question' => $service::alias($attempt, $questionId),
            'options' => [$service::alias($attempt, $option->id)],
        ])->assertOk();
    }
    test()->post(route('exams.submit', $attempt))->assertRedirect(route('exams.result', $attempt));
}
