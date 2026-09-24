<x-layouts.app title="Dashboard Trainer" workspace="trainer" :eyebrow="'Trainer · '.now()->timezone('Asia/Jakarta')->translatedFormat('l, d F Y')">
    <x-slot:heading>Kelas yang Anda ampu</x-slot:heading>
    <x-slot:subtitle>Selamat datang, {{ $user->name }}. Kelola materi, asesmen, dan penilaian peserta.</x-slot:subtitle>
    <x-slot:aside>
        <div class="num">{{ $pendingGrading }}</div>
        <div class="lbl">Menunggu penilaian</div>
    </x-slot:aside>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @include('dashboards._tile', ['label' => 'Kelas diampu', 'value' => $classCount, 'icon' => 'layers', 'note' => 'Semua kelas yang ditugaskan', 'link' => route('trainer.classes')])
        @include('dashboards._tile', ['label' => 'Peserta', 'value' => $participants, 'icon' => 'users', 'note' => 'Enrollment aktif & selesai'])
        @include('dashboards._tile', ['label' => 'Menunggu penilaian', 'value' => $pendingGrading, 'icon' => 'clipboard', 'trend' => $pendingGrading > 0 ? 'Esai perlu dinilai' : 'Tidak ada antrean', 'tone' => $pendingGrading > 0 ? 'bad' : 'good', 'note' => 'Attempt dengan soal esai'])
        @include('dashboards._tile', ['label' => 'Notifikasi baru', 'value' => $unread, 'icon' => 'bell', 'note' => 'Informasi kelas & sistem', 'link' => route('notifications.index')])
    </div>

    <section class="card mt-8 overflow-hidden">
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 px-5 py-4">
            <h2 class="card-title mr-auto">Kelas Aktif</h2>
            @can('course_class.view_any')<a href="{{ route('trainer.classes') }}" class="btn-secondary">Semua kelas</a>@endcan
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th scope="col">Program / Batch</th><th scope="col">Status</th><th scope="col">Peserta</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
                <tbody>
                    @forelse ($classes as $class)
                        <tr>
                            <td><span class="font-semibold text-slate-800">{{ $class->program_name }}</span><span class="block text-xs text-slate-500">{{ $class->batch_name }}</span></td>
                            <td><span @class(['chip', 'chip-low' => $class->status === 'running', 'chip-info' => $class->status === 'open', 'chip-neutral' => $class->status === 'draft'])>{{ \App\Modules\Learning\Models\CourseClass::STATUSES[$class->status] }}</span></td>
                            <td class="font-mono">{{ $class->enrolled_count }}</td>
                            <td class="text-right"><a href="{{ route('classes.manage', $class->id) }}" class="btn-mini">Kelola</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-10 text-center text-slate-500">Belum ada kelas aktif.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @include('dashboards._shortcuts', ['workspace' => 'trainer'])
</x-layouts.app>
