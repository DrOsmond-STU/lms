<?php

declare(strict_types=1);

namespace App\Modules\Cms\Console;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Cms\Models\LandingSlide;
use App\Modules\Cms\Models\SiteProfile;
use App\Modules\Cms\Services\LandingImages;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Data awal beranda: profil pemilik situs dan tiga slide awal (gambar ilustrasi ikut aplikasi
 * di resources/landing-defaults). Hanya sekali — setelah itu semuanya milik admin
 * (Pengaturan Sistem → Profil Pemilik / Beranda) dan tidak ditimpa saat deploy berikutnya.
 */
final class LandingDefaultsCommand extends Command
{
    private const MARKER = 'internal.landing_defaults_seeded';

    protected $signature = 'stu:landing-defaults';

    protected $description = 'Isi profil pemilik & slide awal beranda (sekali, dapat diubah admin)';

    public function handle(LandingImages $images, AuditLogger $audit): int
    {
        if (! DB::table('site_profile')->where('id', 1)->exists()) {
            SiteProfile::current()->save();
            $this->info('Profil pemilik situs awal dibuat (ubah di Pengaturan Sistem → Profil Pemilik).');
        }

        if (DB::table('system_settings')->where('key', self::MARKER)->exists()) {
            $this->info('Slide awal sudah pernah dibuat.');

            return self::SUCCESS;
        }

        $slides = [
            ['Pelatihan & Sertifikasi', 'Tingkatkan kompetensi Anda bersama kami', 'Program pelatihan bersertifikat Internasional & BNSP — belajar fleksibel, ujian yang adil, dan sertifikat digital yang dapat diverifikasi publik.', 'Lihat Pelatihan', '/#pelatihan', 'slide-1.webp'],
            ['Asesmen daring', 'Ujian terukur, hasil langsung terlihat', 'Waktu ujian dijaga server, soal diacak untuk setiap peserta, dan penilaian otomatis yang transparan.', 'Mulai Belajar', '/daftar', 'slide-2.webp'],
            ['Sertifikat terverifikasi', 'Sertifikat digital yang dapat dibuktikan keasliannya', 'Setiap sertifikat bertanda tangan digital dengan QR & kode unik — perusahaan dan kampus dapat memverifikasinya kapan saja.', 'Verifikasi Sertifikat', '/verifikasi', 'slide-3.webp'],
        ];
        DB::transaction(function () use ($slides, $images, $audit): void {
            if (! LandingSlide::query()->exists()) {
                foreach ($slides as $i => [$eyebrow, $title, $subtitle, $ctaLabel, $ctaUrl, $file]) {
                    $slide = new LandingSlide;
                    $slide->forceFill([
                        'eyebrow' => $eyebrow, 'title' => $title, 'subtitle' => $subtitle, 'cta_label' => $ctaLabel, 'cta_url' => $ctaUrl,
                        'image_path' => $images->storeFromPath(resource_path('landing-defaults/'.$file), 'slide'),
                        'position' => $i + 1, 'is_active' => true,
                    ])->save();
                }
                $audit->record('cms.slide.bootstrapped', null, 'landing_slide', null, ['count' => count($slides)]);
            }
            DB::table('system_settings')->insert(['key' => self::MARKER, 'value' => json_encode(true), 'updated_at' => now()]);
        });
        $this->info('Slide awal beranda dibuat (ubah di Pengaturan Sistem → Beranda → Slide).');

        return self::SUCCESS;
    }
}
