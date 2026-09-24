<x-layouts.app title="Dashboard Trainer" workspace="trainer">
    <h1 class="text-xl font-extrabold text-slate-800">Dashboard Trainer</h1>
    <p class="mt-0.5 mb-6 text-sm text-slate-600">Selamat datang, {{ $user->name }}.</p>
    <div class="mb-6 grid gap-5 sm:grid-cols-3">
        @include('dashboards._stat', ['label' => 'Kelas Diampu', 'value' => $classCount, 'link' => route('trainer.classes'), 'linkLabel' => 'Kelas Saya'])
        @include('dashboards._stat', ['label' => 'Peserta', 'value' => $participants])
        @include('dashboards._stat', ['label' => 'Menunggu Penilaian', 'value' => $pendingGrading])
    </div>
    <section class="card p-6" aria-labelledby="classes-heading">
        <h2 id="classes-heading" class="font-bold text-slate-800">Kelas Aktif</h2>
        <ul class="mt-3 divide-y divide-slate-100 text-sm">
            @forelse ($classes as $class)
                <li class="flex items-center justify-between py-2"><a href="{{ route('classes.manage', $class->id) }}" class="font-bold text-brand-700 hover:underline">{{ $class->program_name }} — {{ $class->batch_name }}</a><span class="text-xs text-slate-500">{{ $class->enrolled_count }} peserta · {{ \App\Modules\Learning\Models\CourseClass::STATUSES[$class->status] }}</span></li>
            @empty
                <li class="py-2 text-slate-500">Belum ada kelas aktif.</li>
            @endforelse
        </ul>
    </section>
</x-layouts.app>
