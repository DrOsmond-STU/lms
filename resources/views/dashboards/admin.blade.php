@php
    $months = collect($enrollmentsPerMonth);
    $thisMonth = (int) ($months->last()['total'] ?? 0);
    $lastMonth = (int) ($months->slice(-2, 1)->first()['total'] ?? 0);
    $diff = $thisMonth - $lastMonth;
    $actions = array_filter([
        ($approvalQueue ?? 0) > 0 ? ['critical', 'Approval sertifikat', $approvalQueue.' peserta lulus menunggu penerbitan sertifikat', route('admin.approvals.index')] : null,
        ($paymentsToReview ?? 0) > 0 ? ['high', 'Verifikasi pembayaran', $paymentsToReview.' bukti transfer menunggu verifikasi', route('admin.payments.index', ['tinjau' => 1])] : null,
        $secondApprovals > 0 ? ['high', 'Persetujuan kedua', $secondApprovals.' aksi berdampak tinggi menunggu admin kedua', route('admin.second-approvals.index')] : null,
        ($programsInReview ?? 0) > 0 ? ['medium', 'Review program', $programsInReview.' program menunggu diterbitkan', route('admin.programs.index', ['status' => 'in_review'])] : null,
    ]);
@endphp
<x-layouts.app title="Dashboard Administrator" workspace="admin" :eyebrow="'Administrator · '.now()->timezone(display_tz())->translatedFormat('F Y')">
    <x-slot:heading>Kondisi platform hari ini</x-slot:heading>
    <x-slot:subtitle>Selamat datang, {{ $user->name }}. Ringkasan peserta, kelas, dan sertifikasi di seluruh organisasi.</x-slot:subtitle>
    <x-slot:aside>
        <div class="num">{{ $passRate === null ? '—' : str_replace('.', ',', (string) $passRate).'%' }}</div>
        <div class="lbl">Tingkat kelulusan</div>
    </x-slot:aside>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @include('dashboards._tile', ['label' => 'Peserta', 'value' => number_format($participants, 0, ',', '.'), 'icon' => 'users', 'trend' => ($diff >= 0 ? '+' : '').$diff.' pendaftaran vs bulan lalu', 'tone' => $diff > 0 ? 'good' : ($diff < 0 ? 'bad' : 'flat'), 'note' => 'Akun dengan peran peserta', 'link' => $user->can('user.view_any') ? route('admin.users.index') : null])
        @include('dashboards._tile', ['label' => 'Organisasi aktif', 'value' => $organizations, 'icon' => 'building', 'note' => 'Institusi & korporat mitra', 'link' => $user->can('organization.view_any') ? route('admin.organizations.index') : null])
        @include('dashboards._tile', ['label' => 'Kelas berjalan', 'value' => $classes, 'icon' => 'calendar', 'trend' => $programs.' program terbit', 'note' => 'Kelas berstatus dibuka atau berjalan', 'link' => $user->can('course_class.view_any') ? route('admin.classes.index') : null])
        @include('dashboards._tile', ['label' => 'Sertifikat aktif', 'value' => number_format($certificates, 0, ',', '.'), 'icon' => 'cert', 'note' => 'Dapat diverifikasi publik', 'link' => $user->can('certificate.view_any') ? route('admin.certificates.index') : null])
    </div>

    <section class="mt-8 card p-5">
        <h2 class="card-title">Tren Pendaftaran 6 Bulan</h2>
        <p class="card-sub">Enrollment baru per bulan · {{ $months->first()['label'] ?? '' }} – {{ $months->last()['label'] ?? '' }}</p>
        <x-line-chart :series="$months->map(fn ($m) => ['label' => \Illuminate\Support\Str::before($m['label'], ' '), 'value' => $m['total']])->all()" label="Grafik jumlah enrollment baru enam bulan terakhir" />
        <table class="sr-only"><caption>Enrollment baru per bulan</caption><tbody>@foreach ($months as $row)<tr><th scope="row">{{ $row['label'] }}</th><td>{{ $row['total'] }}</td></tr>@endforeach</tbody></table>
    </section>

    <section class="mt-8 grid gap-4 lg:grid-cols-2">
        <div class="card p-5">
            <h2 class="card-title">Perhatian Segera</h2>
            <p class="card-sub">Antrean yang menunggu keputusan Anda</p>
            <div>
                @forelse ($actions as [$tone, $label, $text, $href])
                    <a href="{{ $href }}" class="feed-item group">
                        <span class="min-w-0">
                            <span class="chip chip-{{ $tone }}">{{ $label }}</span>
                            <span class="feed-title mt-1.5 group-hover:text-link">{{ $text }}</span>
                        </span>
                    </a>
                @empty
                    <p class="py-6 text-center text-sm text-slate-500">Tidak ada antrean. Semua beres.</p>
                @endforelse
            </div>
        </div>
        <div class="card p-5">
            <h2 class="card-title">Aktivitas Terbaru</h2>
            <p class="card-sub">Pendaftaran pelatihan terakhir</p>
            <div>
                @forelse ($recentEnrollments as $enrollment)
                    <div class="feed-item">
                        <span class="feed-icon bg-brand-100 text-brand-700"><x-icon name="book" class="h-4 w-4" /></span>
                        <span class="min-w-0">
                            <span class="feed-title truncate">{{ $enrollment->user?->name }}</span>
                            <span class="feed-meta truncate">{{ $enrollment->program?->name }} · <span class="chip chip-{{ \App\Modules\Enrollment\Models\Enrollment::STATUS_TONES[$enrollment->status] ?? 'neutral' }} px-1.5 py-0.5 text-[10px]">{{ $enrollment->statusLabel() }}</span></span>
                        </span>
                        <span class="feed-when">{{ $enrollment->created_at->timezone(display_tz())->translatedFormat('d M H:i') }}</span>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-slate-500">Belum ada pendaftaran.</p>
                @endforelse
            </div>
        </div>
    </section>

    @include('dashboards._announcements')
    @include('dashboards._shortcuts', ['workspace' => 'admin'])
</x-layouts.app>
