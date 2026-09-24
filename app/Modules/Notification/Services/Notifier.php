<?php

declare(strict_types=1);

namespace App\Modules\Notification\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Notification\Mail\PlainNotificationMail;
use App\Modules\Notification\Models\InAppNotification;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * Pengirim notifikasi (FR-NTF-001/002/005): selalu in-app, email opsional. Isi email
 * minimal — tanpa skor rinci/token — dan tautan menuju aplikasi (login wajib).
 */
final class Notifier
{
    public const CATEGORIES = [
        'registration' => 'Registrasi',
        'enrollment' => 'Enrollment',
        'content' => 'Jadwal & Materi',
        'assessment' => 'Ujian & Kuis',
        'grading' => 'Hasil Penilaian',
        'certificate' => 'Sertifikat',
        'security' => 'Keamanan Akun',
        'system' => 'Sistem',
    ];

    public function send(User $user, string $category, string $title, string $body, ?string $actionPath = null, bool $email = false): void
    {
        if (! array_key_exists($category, self::CATEGORIES)) {
            throw new InvalidArgumentException("Kategori notifikasi tidak dikenal: {$category}");
        }
        if ($actionPath !== null && preg_match('#^/[^/]#', $actionPath) !== 1) {
            throw new InvalidArgumentException('action_url harus path internal.');
        }

        $notification = new InAppNotification;
        $notification->forceFill([
            'user_id' => $user->id,
            'category' => $category,
            'title' => mb_substr($title, 0, 160),
            'body' => mb_substr($body, 0, 500),
            'action_url' => $actionPath,
        ])->save();

        if ($email && $user->isActive()) {
            Mail::to($user->email)->queue(new PlainNotificationMail($title, $body, $actionPath));
        }
    }

    public static function unreadCount(User $user): int
    {
        return InAppNotification::query()->where('user_id', $user->id)->whereNull('read_at')->count();
    }
}
