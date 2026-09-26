<?php

declare(strict_types=1);

namespace App\Modules\Notification\Console;

use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Services\Notifier;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pengingat otomatis (dijadwalkan tiap jam): tenggat tugas & sesi yang akan datang, peserta
 * tidak aktif, dan program baru terbit. Setiap pengingat dicatat di `notification_reminders`
 * agar tidak terkirim dua kali. Kanal mengikuti preferensi pengguna (Notifier).
 */
final class RemindersCommand extends Command
{
    protected $signature = 'stu:reminders';

    protected $description = 'Kirim pengingat tenggat, peserta tidak aktif, dan program baru';

    public function handle(Notifier $notifier, TenantContext $tenant): int
    {
        $sent = $tenant->runAsSystem(fn (): array => [
            'tenggat tugas' => $this->assignmentDeadlines($notifier),
            'sesi' => $this->sessions($notifier),
            'tidak aktif' => $this->inactive($notifier),
            'program baru' => $this->newPrograms($notifier),
        ]);
        foreach ($sent as $label => $count) {
            $this->info(ucfirst($label).': '.$count);
        }

        return self::SUCCESS;
    }

    private function assignmentDeadlines(Notifier $notifier): int
    {
        $hours = (int) setting('reminder.deadline_hours');
        $assignments = DB::table('assignments')->whereNotNull('due_at')->whereBetween('due_at', [now(), now()->addHours($hours)])->get(['id', 'course_class_id', 'title', 'due_at']);
        $count = 0;
        foreach ($assignments as $assignment) {
            $enrollments = Enrollment::query()->with('user')->where('course_class_id', $assignment->course_class_id)->whereIn('status', Enrollment::ACTIVE)
                ->whereNotIn('id', DB::table('assignment_submissions')->where('assignment_id', $assignment->id)->select('enrollment_id'))->get();
            foreach ($enrollments as $enrollment) {
                if (! $this->claim($enrollment->user_id, 'assignment_due', (string) $assignment->id)) {
                    continue;
                }
                $due = Carbon::parse((string) $assignment->due_at)->timezone(display_tz());
                $notifier->send($enrollment->user, 'content', 'Tenggat tugas mendekat', 'Tugas "'.$assignment->title.'" harus dikumpulkan sebelum '.$due->translatedFormat('D, d M H:i').' '.tz_label().'.', '/peserta/kelas/'.$enrollment->id.'/tugas/'.$assignment->id, email: true);
                $count++;
            }
        }

        return $count;
    }

    private function sessions(Notifier $notifier): int
    {
        $hours = min(24, (int) setting('reminder.deadline_hours'));
        $sessions = DB::table('class_sessions')->whereBetween('starts_at', [now(), now()->addHours($hours)])->get(['id', 'course_class_id', 'title', 'starts_at', 'type']);
        $count = 0;
        foreach ($sessions as $session) {
            $enrollments = Enrollment::query()->with('user')->where('course_class_id', $session->course_class_id)->whereIn('status', Enrollment::ACTIVE)->get();
            foreach ($enrollments as $enrollment) {
                if (! $this->claim($enrollment->user_id, 'session_soon', (string) $session->id)) {
                    continue;
                }
                $at = Carbon::parse((string) $session->starts_at)->timezone(display_tz());
                $notifier->send($enrollment->user, 'content', ($session->type === 'online' ? 'Live class' : 'Sesi').' segera dimulai', '"'.$session->title.'" dimulai '.$at->translatedFormat('D, d M H:i').' '.tz_label().'.', '/peserta/kelas/'.$enrollment->id, email: true);
                $count++;
            }
        }

        return $count;
    }

    private function inactive(Notifier $notifier): int
    {
        $days = (int) setting('reminder.inactive_days');
        $cutoff = now()->subDays($days);
        $week = now()->format('o-W');
        $lastProgress = DB::table('lesson_progress')->selectRaw('enrollment_id, max(updated_at) as last_at')->groupBy('enrollment_id');
        $lastAttempt = DB::table('exam_attempts')->selectRaw('enrollment_id, max(coalesce(submitted_at, started_at)) as last_at')->groupBy('enrollment_id');
        $rows = Enrollment::query()->with('user', 'program:id,name')
            ->leftJoinSub($lastProgress, 'lp', 'lp.enrollment_id', '=', 'enrollments.id')
            ->leftJoinSub($lastAttempt, 'la', 'la.enrollment_id', '=', 'enrollments.id')
            ->whereIn('enrollments.status', Enrollment::ACTIVE)->where('enrollments.enrolled_at', '<', $cutoff)
            ->whereRaw('greatest(coalesce(lp.last_at, enrollments.enrolled_at), coalesce(la.last_at, enrollments.enrolled_at)) < ?', [$cutoff])
            ->limit(2000)->get(['enrollments.*']);
        $count = 0;
        foreach ($rows as $enrollment) {
            if (! $this->claim($enrollment->user_id, 'inactive', $enrollment->id.':'.$week)) {
                continue;
            }
            $notifier->send($enrollment->user, 'content', 'Lanjutkan belajar Anda', 'Sudah lebih dari '.$days.' hari Anda tidak membuka '.$enrollment->program->name.'. Progres Anda '.$enrollment->progress_percent.'% — lanjutkan agar tepat waktu.', '/peserta/kelas/'.$enrollment->id, email: true);
            $count++;
        }

        return $count;
    }

    private function newPrograms(Notifier $notifier): int
    {
        if (! setting('reminder.new_program')) {
            return 0;
        }
        $programs = DB::table('programs')->where('status', 'published')->where('published_at', '>=', now()->subDay())->get(['id', 'name', 'slug']);
        if ($programs->isEmpty()) {
            return 0;
        }
        $participantRole = DB::table('roles')->where('code', 'participant')->value('id');
        $count = 0;
        User::query()->where('status', 'active')->whereIn('id', DB::table('role_user')->where('role_id', $participantRole)->select('user_id'))
            ->orderBy('id')->chunkById(500, function ($users) use ($programs, $notifier, &$count): void {
                foreach ($users as $user) {
                    foreach ($programs as $program) {
                        if (! $this->claim($user->id, 'new_program', (string) $program->id)) {
                            continue;
                        }
                        $notifier->send($user, 'content', 'Program pelatihan baru', $program->name.' kini tersedia di katalog.', '/peserta/katalog/'.$program->slug);
                        $count++;
                    }
                }
            });

        return $count;
    }

    /** Catat pengingat; false bila sudah pernah dikirim untuk kombinasi yang sama. */
    private function claim(string $userId, string $kind, string $reference): bool
    {
        return DB::table('notification_reminders')->insertOrIgnore([
            'id' => (string) Str::uuid7(), 'user_id' => $userId, 'kind' => $kind, 'reference_id' => $reference, 'sent_at' => now(),
        ]) === 1;
    }
}
