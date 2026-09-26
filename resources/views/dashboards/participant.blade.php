@php($firstName = \Illuminate\Support\Str::of($user->name)->before(',')->trim()->explode(' ')->first())
<x-layouts.app title="Dashboard Peserta" workspace="participant" :eyebrow="'Peserta · '.now()->timezone(display_tz())->translatedFormat('l, d F Y')">
    <x-slot:heading>Halo, {{ $firstName }}</x-slot:heading>
    <x-slot:subtitle>Lanjutkan pelatihan Anda — progres tersimpan otomatis dan sertifikat terbit setelah Anda lulus.</x-slot:subtitle>
    <x-slot:aside>
        <div class="num">{{ $certificates }}</div>
        <div class="lbl">Sertifikat aktif</div>
    </x-slot:aside>

    @include('dashboards._mfa')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @include('dashboards._tile', ['label' => 'Pelatihan aktif', 'value' => $active->count(), 'icon' => 'book', 'note' => 'Terdaftar atau sedang belajar', 'link' => route('learning.index')])
        @include('dashboards._tile', ['label' => 'Lulus', 'value' => $passed, 'icon' => 'trophy', 'trend' => $passed > 0 ? 'Selamat atas kelulusan Anda' : 'Belum ada kelulusan', 'tone' => $passed > 0 ? 'good' : 'flat', 'note' => 'Program yang telah Anda selesaikan'])
        @include('dashboards._tile', ['label' => 'Sertifikat aktif', 'value' => $certificates, 'icon' => 'cert', 'note' => 'Dapat diverifikasi publik', 'link' => route('certificates.mine')])
        @include('dashboards._tile', ['label' => 'Notifikasi baru', 'value' => $unread, 'icon' => 'bell', 'trend' => $unread > 0 ? 'Perlu dibaca' : 'Semua sudah dibaca', 'tone' => $unread > 0 ? 'bad' : 'good', 'note' => 'Enrollment, ujian & sertifikat', 'link' => route('notifications.index')])
    </div>

    <section class="mt-8 grid gap-4 lg:grid-cols-3">
        <div class="card p-5 lg:col-span-3 xl:col-span-1">
            <h2 class="card-title">Sedang Dipelajari</h2>
            <p class="card-sub">Pelatihan terakhir yang Anda buka</p>
            <div>
                @forelse ($active as $enrollment)
                    <a href="{{ route('learning.classroom', $enrollment) }}" class="feed-item group items-center">
                        <span class="feed-icon bg-brand-100 text-brand-700"><x-icon name="play" class="h-4 w-4" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="feed-title truncate group-hover:text-link">{{ $enrollment->program->name }}</span>
                            <span class="feed-meta">{{ $enrollment->courseClass->batch_name }} · {{ $enrollment->statusLabel() }}</span>
                            <span class="mt-2 flex items-center gap-3"><span class="bar flex-1"><span class="progress-{{ (int) (floor($enrollment->progress_percent / 5) * 5) }}"></span></span><span class="font-mono text-xs font-semibold text-slate-800">{{ $enrollment->progress_percent }}%</span></span>
                        </span>
                    </a>
                @empty
                    <div class="py-8 text-center">
                        <p class="text-sm text-slate-500">Belum ada pelatihan aktif.</p>
                        @can('program.view_any')<a href="{{ route('catalog.participant') }}" class="btn-primary mt-4 w-auto">Pilih Pelatihan</a>@endcan
                    </div>
                @endforelse
            </div>
        </div>
        <div class="card p-5">
            <h2 class="card-title">Tenggat &amp; Jadwal Terdekat</h2>
            <p class="card-sub">14 hari ke depan</p>
            <div>
                @forelse ($deadlines as $item)
                    <a href="{{ $item['url'] }}" class="feed-item group">
                        <span class="feed-icon bg-amber-50 text-amber-700"><x-icon name="calendar" class="h-4 w-4" /></span>
                        <span class="min-w-0"><span class="feed-title group-hover:text-link">{{ $item['kind'] }}: {{ $item['title'] }}</span><span class="feed-meta">{{ $item['at']->timezone(display_tz())->translatedFormat('D, d M H:i') }} {{ tz_label() }}</span></span>
                    </a>
                @empty
                    <p class="py-6 text-center text-sm text-slate-500">Tidak ada tenggat atau sesi dalam 14 hari.</p>
                @endforelse
            </div>
            <a href="{{ route('schedule.participant') }}" class="btn-secondary mt-4 w-full">Jadwal lengkap</a>
        </div>
        <div class="card p-5">
            <h2 class="card-title">Notifikasi</h2>
            <p class="card-sub">{{ $unread }} belum dibaca</p>
            <div>
                @forelse ($notifications as $item)
                    <div class="feed-item">
                        <span @class(['mt-1.5 h-2.5 w-2.5 flex-none rounded-full', 'bg-brand-500' => $item->read_at === null, 'bg-slate-300' => $item->read_at !== null])></span>
                        <span class="min-w-0"><span class="feed-title">{{ $item->title }}</span><span class="feed-meta">{{ $item->created_at->timezone(display_tz())->translatedFormat('d M H:i') }}</span></span>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-slate-500">Belum ada notifikasi.</p>
                @endforelse
            </div>
            <a href="{{ route('notifications.index') }}" class="btn-secondary mt-4 w-full">Semua notifikasi</a>
        </div>
    </section>

    @include('dashboards._announcements')
    @include('dashboards._shortcuts', ['workspace' => 'participant'])
</x-layouts.app>
