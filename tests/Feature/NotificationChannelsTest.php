<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;
use App\Modules\Notification\Models\NotificationPreference;
use App\Modules\Notification\Models\PushSubscription;
use App\Modules\Notification\Services\Notifier;
use App\Modules\Notification\Services\WebPush;
use App\Modules\Settings\Services\SystemSettings;
use App\Support\Security\TokenHasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/*
 * Kanal notifikasi: preferensi, Web Push (VAPID + aes128gcm), WhatsApp gateway, pengingat otomatis.
 */

it('encrypts web push payloads that the recipient can decrypt and signs valid VAPID tokens', function () {
    $recipient = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $details = openssl_pkey_get_details($recipient);
    $p256dh = WebPush::b64("\x04".str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT));
    $auth = WebPush::b64(random_bytes(16));
    $body = WebPush::encrypt('{"title":"Halo"}', $p256dh, $auth);
    expect(strlen($body))->toBeGreaterThan(86 + 16)->and(substr($body, 16, 4))->toBe(pack('N', 4096))->and(ord($body[20]))->toBe(65);
    expect(WebPush::decrypt($body, $recipient, $auth))->toBe('{"title":"Halo"}');

    $keys = WebPush::generateKeys();
    expect(strlen(WebPush::unb64($keys['public'])))->toBe(65)->and(strlen(WebPush::unb64($keys['private'])))->toBe(32);
    $jwt = WebPush::vapidToken('https://push.example.test', $keys['private'], $keys['public']);
    [$header, $claims, $signature] = explode('.', $jwt);
    expect(json_decode(WebPush::unb64($claims), true))->toMatchArray(['aud' => 'https://push.example.test']);
    $raw = WebPush::unb64($signature);
    expect(strlen($raw))->toBe(64);
    // Verifikasi ES256: r||s → DER, cocokkan dengan kunci publik.
    $der = static function (string $part): string {
        $part = ltrim($part, "\0");
        if (ord($part[0]) & 0x80) {
            $part = "\0".$part;
        }

        return "\x02".chr(strlen($part)).$part;
    };
    $sequence = $der(substr($raw, 0, 32)).$der(substr($raw, 32));
    $verified = openssl_verify($header.'.'.$claims, "\x30".chr(strlen($sequence)).$sequence, openssl_pkey_get_public(WebPush::publicKeyPem(WebPush::unb64($keys['public']))), OPENSSL_ALGO_SHA256);
    expect($verified)->toBe(1);
});

it('routes notifications by preference to email, push and whatsapp and stores secrets encrypted', function () {
    Http::fake(['https://push.example.test/*' => Http::sequence()->push('', 201)->push('', 410), 'https://wa.example.test/*' => Http::response(['ok' => true], 200)]);
    $admin = signIn(RoleCode::SuperAdmin);
    confirmAccess();
    $this->post(route('admin.settings.vapid'))->assertRedirect();
    expect(WebPush::publicKey())->not->toBe('');
    $stored = asSystem(fn () => (string) DB::table('system_settings')->where('key', 'push.vapid_private')->value('value'));
    expect($stored)->not->toContain((string) setting('push.vapid_private'))->and(strlen((string) setting('push.vapid_private')))->toBeGreaterThan(40);

    $fields = fn (array $extra) => array_merge([
        SystemSettings::field('push.enabled') => '1', SystemSettings::field('push.subject') => 'mailto:admin@example.test',
        SystemSettings::field('push.vapid_public') => WebPush::publicKey(),
        SystemSettings::field('whatsapp.enabled') => '1', SystemSettings::field('whatsapp.endpoint') => 'https://wa.example.test/send',
        SystemSettings::field('whatsapp.token') => 'rahasia-gateway', SystemSettings::field('whatsapp.payload') => 'json',
        SystemSettings::field('reminder.deadline_hours') => '48', SystemSettings::field('reminder.inactive_days') => '7', SystemSettings::field('reminder.new_program') => '1',
    ], $extra);
    confirmAccess();
    $this->put(route('admin.settings.update', 'integrasi'), $fields([]))->assertSessionHasNoErrors();
    expect(setting('whatsapp.token'))->toBe('rahasia-gateway');
    expect(asSystem(fn () => (string) DB::table('system_settings')->where('key', 'whatsapp.token')->value('value')))->not->toContain('rahasia-gateway');
    // Kosong = pertahankan rahasia; halaman tidak membocorkan nilai.
    confirmAccess();
    $this->put(route('admin.settings.update', 'integrasi'), $fields([SystemSettings::field('whatsapp.token') => '']))->assertSessionHasNoErrors();
    expect(setting('whatsapp.token'))->toBe('rahasia-gateway');
    $this->get(route('admin.settings.tab', 'integrasi'))->assertOk()->assertDontSee('rahasia-gateway')->assertSee('Buat kunci VAPID');
    $this->post('/keluar');
    nextRequest();

    $participant = signIn(RoleCode::Participant);
    asSystem(fn () => $participant->forceFill(['phone_encrypted' => '+628123456789', 'phone_bidx' => TokenHasher::hash('+628123456789', 'phone-bidx')])->save());
    $this->get(route('notifications.preferences'))->assertOk()->assertSee('Aktifkan di perangkat ini');
    $recipient = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $details = openssl_pkey_get_details($recipient);
    $p256dh = WebPush::b64("\x04".str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT));
    $this->postJson(route('notifications.push.subscribe'), ['endpoint' => 'https://push.example.test/sub/abc', 'keys' => ['p256dh' => $p256dh, 'auth' => WebPush::b64(random_bytes(16))]])->assertOk();
    $this->postJson(route('notifications.push.subscribe'), ['endpoint' => 'http://insecure.example.test/x', 'keys' => ['p256dh' => $p256dh, 'auth' => 'abc']])->assertStatus(422);
    $this->put(route('notifications.preferences.update'), ['email_enabled' => '1', 'push_enabled' => '1', 'whatsapp_enabled' => '1', 'muted' => ['referral', 'security']])->assertSessionHasNoErrors();
    $preference = asSystem(fn () => NotificationPreference::for($participant->fresh()));
    expect($preference->muted_categories)->toBe(['referral'])->and($preference->whatsapp_enabled)->toBeTrue();

    asSystem(fn () => app(Notifier::class)->send($participant->fresh(), 'grading', 'Nilai terbit', 'Skor Anda 90.', '/peserta/nilai'));
    asSystem(fn () => app(Notifier::class)->send($participant->fresh(), 'referral', 'Komisi', 'Dibisukan.', '/peserta/referral'));
    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://push.example.test/') && str_starts_with((string) $request->header('Authorization')[0], 'vapid t=') && $request->hasHeader('Content-Encoding', 'aes128gcm'));
    Http::assertSent(fn ($request) => $request->url() === 'https://wa.example.test/send' && $request['to'] === '628123456789' && str_contains((string) $request['message'], 'Nilai terbit') && $request->hasHeader('Authorization', 'Bearer rahasia-gateway'));
    expect(asSystem(fn () => DB::table('outbound_messages')->where('status', 'sent')->count()))->toBe(2);
    expect(asSystem(fn () => PushSubscription::query()->where('user_id', $participant->id)->value('last_used_at')))->not->toBeNull();

    // Langganan mati (410, respons kedua dalam sequence) dihapus otomatis.
    asSystem(fn () => app(Notifier::class)->send($participant->fresh(), 'grading', 'Lagi', 'x', '/peserta/nilai'));
    expect(asSystem(fn () => PushSubscription::query()->where('user_id', $participant->id)->count()))->toBe(0);
    $this->post(route('notifications.preferences.test'))->assertRedirect();
});

it('sends deadline, inactivity and new program reminders once', function () {
    $course = makeCourse(['final' => false]);
    $class = $course['class'];
    $participant = signIn(RoleCode::Participant);
    $enrollment = enrollVia($class, $participant);
    $this->post('/keluar');
    nextRequest();

    asSystem(function () use ($class, $enrollment, $course): void {
        DB::table('assignments')->insert(['id' => (string) Str::uuid7(), 'course_class_id' => $class->id, 'title' => 'Laporan Akhir', 'instructions_md' => 'x', 'instructions_html' => '<p>x</p>', 'max_score' => 100, 'is_required' => true, 'allow_late' => false, 'allow_text' => true, 'allow_file' => false, 'position' => 1, 'due_at' => now()->addHours(20), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('class_sessions')->insert(['id' => (string) Str::uuid7(), 'course_class_id' => $class->id, 'title' => 'Live Class Penutup', 'type' => 'online', 'starts_at' => now()->addHours(5), 'ends_at' => now()->addHours(7), 'attendance_mode' => 'none', 'checkin_opens_before' => 15, 'checkin_closes_after' => 30, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('enrollments')->where('id', $enrollment->id)->update(['enrolled_at' => now()->subDays(10)]);
        DB::table('programs')->where('id', $course['program']->id)->update(['published_at' => now()->subHour()]);
    });

    $this->artisan('stu:reminders')->assertSuccessful();
    $this->artisan('stu:reminders')->assertSuccessful();
    $titles = asSystem(fn () => DB::table('notifications')->where('user_id', $participant->id)->pluck('title'));
    expect($titles->filter(fn ($t) => $t === 'Tenggat tugas mendekat')->count())->toBe(1)
        ->and($titles->filter(fn ($t) => $t === 'Live class segera dimulai')->count())->toBe(1)
        ->and($titles->filter(fn ($t) => $t === 'Lanjutkan belajar Anda')->count())->toBe(1)
        ->and($titles->filter(fn ($t) => $t === 'Program pelatihan baru')->count())->toBe(1);
    expect(asSystem(fn () => DB::table('notification_reminders')->where('user_id', $participant->id)->count()))->toBe(4);
});
