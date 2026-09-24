<x-layouts.public :title="$program->name" heading="Detail Program" subtitle="Silabus, persyaratan, dan jadwal kelas yang membuka pendaftaran.">
    <a href="{{ route('catalog.public') }}" class="btn-mini mb-4">&larr; Semua program</a>
    <div>
        @component('catalog._detail', ['program' => $program, 'syllabus' => $syllabus])
            <section class="card p-6">
                <h2 class="font-bold text-slate-800">Kelas / Batch</h2>
                <ul class="mt-3 space-y-3 text-sm">
                    @forelse ($classes as $class)
                        <li><span class="font-bold">{{ $class->batch_name }}</span><span class="block text-xs text-slate-500">{{ $class->starts_on->translatedFormat('d M Y') }} – {{ $class->ends_on->translatedFormat('d M Y') }} · sisa {{ $class->seatsLeft() }} kursi</span></li>
                    @empty
                        <li class="text-slate-500">Belum ada kelas dibuka.</li>
                    @endforelse
                </ul>
                <a href="{{ route('login') }}" class="btn-primary mt-4">Masuk untuk mendaftar</a>
            </section>
        @endcomponent
    </div>
</x-layouts.public>
