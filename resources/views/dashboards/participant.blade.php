<x-layouts.app title="Dashboard Peserta" workspace="participant">
    <h1 class="text-xl font-extrabold text-slate-800">Halo, {{ $user->name }}</h1>
    <p class="mt-0.5 mb-6 text-sm text-slate-600">Lanjutkan pelatihan Anda.</p>
    @include('dashboards._mfa')
    <div class="mb-6 grid gap-5 sm:grid-cols-3">
        @include('dashboards._stat', ['label' => 'Pelatihan Aktif', 'value' => $active->count(), 'link' => route('learning.index'), 'linkLabel' => 'Pembelajaran Saya'])
        @include('dashboards._stat', ['label' => 'Lulus', 'value' => $passed])
        @include('dashboards._stat', ['label' => 'Sertifikat Aktif', 'value' => $certificates, 'link' => route('certificates.mine'), 'linkLabel' => 'Sertifikat Saya'])
    </div>
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card p-6 lg:col-span-2" aria-labelledby="active-heading">
            <h2 id="active-heading" class="font-bold text-slate-800">Sedang Dipelajari</h2>
            <ul class="mt-3 space-y-4">
                @forelse ($active as $enrollment)
                    <li>
                        <a href="{{ route('learning.classroom', $enrollment) }}" class="font-bold text-brand-700 hover:underline">{{ $enrollment->program->name }}</a>
                        <span class="block text-xs text-slate-500">{{ $enrollment->courseClass->batch_name }} · {{ $enrollment->statusLabel() }}</span>
                        <div class="mt-1 flex items-center gap-2"><div class="h-2 flex-1 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-accent-500 progress-{{ (int) (floor($enrollment->progress_percent / 5) * 5) }}"></div></div><span class="text-xs font-bold">{{ $enrollment->progress_percent }}%</span></div>
                    </li>
                @empty
                    <li class="text-sm text-slate-500">Belum ada pelatihan aktif. <a href="{{ route('catalog.participant') }}" class="font-bold text-brand-700 hover:underline">Pilih pelatihan</a>.</li>
                @endforelse
            </ul>
        </section>
        <section class="card p-6" aria-labelledby="notif-heading">
            <h2 id="notif-heading" class="font-bold text-slate-800">Notifikasi <span class="text-xs font-normal text-slate-500">({{ $unread }} belum dibaca)</span></h2>
            <ul class="mt-3 space-y-3 text-sm">
                @forelse ($notifications as $item)
                    <li><span class="font-bold">{{ $item->title }}</span><span class="block text-xs text-slate-500">{{ $item->created_at->timezone('Asia/Jakarta')->translatedFormat('d M H:i') }}</span></li>
                @empty
                    <li class="text-slate-500">Belum ada notifikasi.</li>
                @endforelse
            </ul>
            <a href="{{ route('notifications.index') }}" class="mt-3 inline-block text-xs font-bold text-brand-700 hover:underline">Semua notifikasi</a>
        </section>
    </div>
</x-layouts.app>
