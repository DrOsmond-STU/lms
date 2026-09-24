<?php

declare(strict_types=1);

namespace App\Modules\Cms\Console;

use App\Modules\Catalog\Models\Program;
use App\Modules\Cms\Models\LandingPartner;
use App\Modules\Cms\Models\LandingTestimonial;
use App\Modules\Learning\Models\CourseClass;
use App\Support\Content\RichText;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Konten beranda CONTOH untuk lokal/staging (UAT): beberapa program terbit dengan jadwal,
 * harga, durasi & topik berbeda (agar filter dapat diuji), testimoni fiktif berlabel
 * "Contoh", dan mitra fiktif bernama "… Contoh …". Ditolak di produksi; idempoten.
 */
final class DemoLandingCommand extends Command
{
    protected $signature = 'stu:demo-landing';

    protected $description = 'Buat program, testimoni & mitra contoh untuk beranda (hanya lokal/staging)';

    /** @var list<array{code: string, name: string, category: string, level: string, hours: int, price: int, mode: string, tags: list<string>, start: int|null, about: string}> */
    private const PROGRAMS = [
        ['code' => 'DEMO-JWD', 'name' => 'Contoh: Junior Web Developer (BNSP)', 'category' => 'bnsp', 'level' => 'menengah', 'hours' => 40, 'price' => 2_500_000, 'mode' => 'hybrid', 'tags' => ['pemrograman', 'web'], 'start' => 20, 'about' => 'Skema sertifikasi kompetensi pengembang web tingkat junior.'],
        ['code' => 'DEMO-PM', 'name' => 'Contoh: Manajemen Proyek Dasar', 'category' => 'international', 'level' => 'dasar', 'hours' => 16, 'price' => 750_000, 'mode' => 'online', 'tags' => ['manajemen'], 'start' => 40, 'about' => 'Perencanaan, penjadwalan, dan pengendalian proyek.'],
        ['code' => 'DEMO-DATA', 'name' => 'Contoh: Analisis Data dengan Spreadsheet', 'category' => 'international', 'level' => 'dasar', 'hours' => 6, 'price' => 0, 'mode' => 'online', 'tags' => ['data'], 'start' => 3, 'about' => 'Mengolah dan memvisualkan data untuk laporan kerja.'],
        ['code' => 'DEMO-K3', 'name' => 'Contoh: Operator K3 Umum (BNSP)', 'category' => 'bnsp', 'level' => 'menengah', 'hours' => 48, 'price' => 4_500_000, 'mode' => 'offline', 'tags' => ['k3'], 'start' => 65, 'about' => 'Keselamatan dan kesehatan kerja di lingkungan industri.'],
        ['code' => 'DEMO-DM', 'name' => 'Contoh: Digital Marketing Profesional', 'category' => 'international', 'level' => 'lanjut', 'hours' => 60, 'price' => 6_000_000, 'mode' => 'online', 'tags' => ['pemasaran'], 'start' => null, 'about' => 'Strategi pemasaran digital terukur.'],
    ];

    public function handle(TenantContext $tenant): int
    {
        if (app()->isProduction()) {
            $this->error('Konten contoh tidak boleh dibuat di produksi.');

            return self::FAILURE;
        }

        $tenant->runAsSystem(fn () => DB::transaction(function (): void {
            foreach (self::PROGRAMS as $item) {
                if (Program::query()->where('short_code', $item['code'])->exists()) {
                    continue;
                }
                $program = new Program;
                $program->forceFill([
                    'category' => $item['category'], 'name' => $item['name'], 'slug' => Str::slug($item['name']),
                    'provider_name' => 'Semesta Teknologi Utama', 'short_code' => $item['code'], 'level' => $item['level'],
                    'duration_hours' => $item['hours'], 'language' => 'id', 'default_mode' => $item['mode'], 'passing_score' => 70,
                    'certificate_validity_months' => 36, 'price' => $item['price'],
                    'description_md' => "Program **contoh** untuk uji penerimaan (UAT). {$item['about']}",
                    'status' => 'published', 'published_at' => now(),
                ]);
                $program->description_html = RichText::toHtml($program->description_md);
                $program->save();
                $program->syncTags($item['tags']);

                if ($item['start'] !== null) {
                    $start = now()->addDays($item['start']);
                    (new CourseClass)->forceFill([
                        'program_id' => $program->id, 'batch_name' => 'Batch Contoh '.$start->translatedFormat('M Y'),
                        'starts_on' => $start->toDateString(), 'ends_on' => $start->copy()->addMonths(2)->toDateString(),
                        'quota' => 50, 'mode' => $item['mode'], 'status' => 'open', 'completion_rules' => ['require_final_exam' => true],
                    ])->save();
                }
            }

            if (! LandingTestimonial::query()->where('is_sample', true)->exists()) {
                foreach ([
                    ['Rina Kartika', 'Mahasiswa Teknik Informatika', 'Universitas Contoh Nusantara', 'Contoh: Dasar Keamanan Informasi', 'Materinya ringkas dan langsung bisa dipraktikkan. Setelah lulus, sertifikatnya bisa langsung diverifikasi oleh perusahaan tempat saya magang.', 5],
                    ['Bagus Hartono', 'HR Development Manager', 'PT Contoh Industri Digital', 'Program pelatihan karyawan', 'Kami memantau progres puluhan karyawan dari satu dasbor. Laporan per status membuat evaluasi pelatihan jauh lebih cepat.', 5],
                    ['Sari Wulandari', 'Peserta Sertifikasi', null, 'Contoh: Junior Web Developer (BNSP)', 'Ujiannya terasa adil — waktu berjalan konsisten walau koneksi sempat putus, dan jawaban saya tersimpan otomatis.', 5],
                    ['Dimas Pratama', 'Dosen Pengampu', 'Politeknik Contoh Mandiri', null, 'Menyusun bank soal dan menilai esai jadi lebih rapi. Mahasiswa pun menerima hasil ujian lebih cepat.', 4],
                    ['Ayu Lestari', 'Lulusan Baru', null, 'Contoh: Analisis Data dengan Spreadsheet', 'Pendaftarannya mudah dan saya bisa belajar dari ponsel di sela kesibukan mencari kerja.', 5],
                    ['Hendra Wijaya', 'Asesor Kompetensi', 'LSP Contoh Informatika', null, 'Verifikasi sertifikat lewat QR memudahkan kami memastikan keaslian dokumen peserta.', 5],
                ] as $i => [$name, $role, $organization, $program, $quote, $rating]) {
                    (new LandingTestimonial)->forceFill([
                        'name' => $name, 'role_title' => $role, 'organization_name' => $organization, 'program_name' => $program,
                        'quote' => $quote, 'rating' => $rating, 'consent_confirmed' => true, 'is_published' => true,
                        'is_sample' => true, 'position' => $i + 1,
                    ])->save();
                }
            }

            if (! LandingPartner::query()->exists()) {
                foreach ([
                    ['Universitas Contoh Nusantara', 'university'], ['PT Contoh Industri Digital', 'company'],
                    ['Politeknik Contoh Mandiri', 'university'], ['LSP Contoh Informatika', 'association'],
                    ['PT Contoh Energi Hijau', 'company'], ['Institut Contoh Teknologi', 'university'],
                    ['Dinas Contoh Tenaga Kerja', 'government'], ['PT Contoh Logistik Andalan', 'company'],
                ] as $i => [$name, $type]) {
                    (new LandingPartner)->forceFill(['name' => $name, 'type' => $type, 'position' => $i + 1, 'is_active' => true])->save();
                }
            }
        }));

        $this->info('Konten beranda contoh siap (program, testimoni berlabel "Contoh", mitra fiktif).');

        return self::SUCCESS;
    }
}
