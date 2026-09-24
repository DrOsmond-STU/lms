<x-layouts.app title="Dashboard Organisasi" workspace="organization">
    <h1 class="text-xl font-extrabold text-slate-800">Dashboard Organisasi</h1>
    <p class="mt-0.5 mb-6 text-sm text-slate-600">{{ $organizations->pluck('name')->implode(', ') }}</p>
    <div class="mb-6 grid gap-5 sm:grid-cols-4">
        @include('dashboards._stat', ['label' => 'Anggota Aktif', 'value' => (int) ($members->active ?? 0), 'link' => route('org.members'), 'linkLabel' => 'Kelola anggota'])
        @include('dashboards._stat', ['label' => 'Menunggu Persetujuan', 'value' => (int) ($members->pending ?? 0), 'link' => route('org.members', ['status' => 'pending']), 'linkLabel' => 'Tinjau'])
        @include('dashboards._stat', ['label' => 'Sedang Belajar', 'value' => (int) (($enrollments['enrolled'] ?? 0) + ($enrollments['in_progress'] ?? 0))])
        @include('dashboards._stat', ['label' => 'Sertifikat Aktif', 'value' => $certificates])
    </div>
    <section class="card p-6">
        <h2 class="font-bold text-slate-800">Enrollment Anggota per Status</h2>
        <ul class="mt-3 grid gap-2 text-sm sm:grid-cols-3">
            @foreach (\App\Modules\Enrollment\Models\Enrollment::STATUSES as $status => $label)
                <li class="flex justify-between rounded-lg bg-slate-50 px-3 py-2"><span>{{ $label }}</span><span class="font-bold">{{ $enrollments[$status] ?? 0 }}</span></li>
            @endforeach
        </ul>
        <a href="{{ route('org.enrollments') }}" class="mt-4 inline-block text-sm font-bold text-brand-700 hover:underline">Lihat progres anggota</a>
    </section>
</x-layouts.app>
