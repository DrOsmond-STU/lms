<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;
use App\Modules\Enrollment\Console\SimulateJourneyCommand;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Storage;

/*
 * Penjelajah halaman: mulai dari dasbor setiap peran, ikuti semua tautan internal (GET)
 * dan pastikan tidak ada yang error. Menangkap kelas bug yang lolos dari uji per-aksi —
 * mis. formulir "Tambah" yang 500 karena mass assignment pada mode strict.
 */

/**
 * @param  list<string>  $start
 * @return array{visited: int, failures: list<string>}
 */
function crawl(array $start, int $limit = 400): array
{
    $base = rtrim((string) config('app.url'), '/');
    $queue = $start;
    $seen = array_fill_keys($start, true);
    $failures = [];
    $visited = 0;

    while ($queue !== [] && $visited < $limit) {
        $path = array_shift($queue);
        $response = test()->get($path);
        $visited++;
        $status = $response->getStatusCode();

        if ($status >= 300 && $status < 400) {
            $target = (string) $response->headers->get('Location');
            $target = str_starts_with($target, $base) ? substr($target, strlen($base)) : $target;
            if (str_starts_with($target, '/') && ! isset($seen[$target])) {
                $seen[$target] = true;
                $queue[] = $target;
            }

            continue;
        }
        if ($status !== 200) {
            $failures[] = "{$status} {$path}";

            continue;
        }

        $html = (string) $response->getContent();
        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            continue;
        }
        preg_match_all('/<a\s[^>]*href="([^"#]+)"/i', $html, $matches);
        foreach ($matches[1] as $href) {
            $href = html_entity_decode($href);
            if (str_starts_with($href, $base)) {
                $href = substr($href, strlen($base)) ?: '/';
            }
            if (! str_starts_with($href, '/') || str_starts_with($href, '//') || str_starts_with($href, '/build/')) {
                continue;
            }
            if (! isset($seen[$href])) {
                $seen[$href] = true;
                $queue[] = $href;
            }
        }
    }

    return ['visited' => $visited, 'failures' => $failures];
}

beforeEach(function () {
    Storage::fake('local');
    $this->artisan('stu:certificate-defaults')->assertSuccessful();
    $this->artisan('stu:landing-defaults')->assertSuccessful();
    $this->artisan('stu:demo-content')->assertSuccessful();
    $this->artisan('stu:demo-landing')->assertSuccessful();
    $this->artisan('stu:simulate')->assertSuccessful();
});

it('opens every page reachable by a super admin without errors', function () {
    signIn(RoleCode::SuperAdmin);
    confirmAccess();

    $result = crawl(['/admin', '/admin/pengaturan', '/admin/kelas', '/admin/pembayaran', '/akun/keamanan', '/notifikasi']);

    expect($result['failures'])->toBe([])->and($result['visited'])->toBeGreaterThan(60);
})->group('CRUD', 'SMOKE');

it('opens every page reachable by a certified participant without errors', function () {
    $participant = User::query()->where('email', 'rina.kartika@'.SimulateJourneyCommand::EMAIL_DOMAIN)->firstOrFail();
    $this->actingAs($participant);

    $result = crawl(['/peserta', '/peserta/program', '/peserta/pembelajaran', '/peserta/sertifikat', '/peserta/transaksi', '/notifikasi', '/akun/profil']);

    expect($result['failures'])->toBe([])->and($result['visited'])->toBeGreaterThan(15);
})->group('CRUD', 'SMOKE');

it('opens every public page without errors', function () {
    $result = crawl(['/', '/verifikasi', '/syarat-ketentuan', '/kebijakan-privasi', '/masuk', '/daftar']);

    expect($result['failures'])->toBe([]);
})->group('SMOKE');
