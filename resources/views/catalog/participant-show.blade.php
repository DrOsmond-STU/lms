<x-layouts.app :title="$program->name" workspace="participant">
    <a href="{{ route('catalog.participant') }}" class="text-sm font-bold text-brand-700 hover:underline">&larr; Pilih Pelatihan</a>
    <div class="mt-3">
        @component('catalog._detail', ['program' => $program, 'syllabus' => $syllabus])
            <section class="card p-6" aria-labelledby="classes-heading">
                <h2 id="classes-heading" class="font-bold text-slate-800">Kelas / Batch</h2>
                <x-form-error field="class" />
                @if ($activeEnrollment)
                    <p class="mt-3 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">Anda sudah terdaftar pada program ini.</p>
                    <a href="{{ route('learning.classroom', $activeEnrollment) }}" class="btn-primary mt-3">Lanjutkan Belajar</a>
                @else
                    <ul class="mt-3 space-y-4 text-sm">
                        @forelse ($classes as $class)
                            <li class="rounded-lg border border-slate-200 p-3">
                                <span class="font-bold">{{ $class->batch_name }}</span>
                                <span class="block text-xs text-slate-500">{{ $class->starts_on->translatedFormat('d M Y') }} – {{ $class->ends_on->translatedFormat('d M Y') }} · {{ \App\Modules\Catalog\Models\Program::MODES[$class->mode] }} · sisa {{ $class->seatsLeft() }} kursi</span>
                                @if ($class->trainers->isNotEmpty())<span class="block text-xs text-slate-500">Trainer: {{ $class->trainers->pluck('name')->implode(', ') }}</span>@endif
                                @if ($class->isEnrollmentOpen() && $class->seatsLeft() > 0)
                                    @if ($program->isFree())
                                        <form method="POST" action="{{ route('catalog.enroll', $class) }}" class="mt-2">@csrf<button class="btn-primary">Daftar</button></form>
                                    @else
                                        <p class="mt-2 text-xs text-amber-700">Pembayaran online belum tersedia. Hubungi admin atau organisasi Anda untuk didaftarkan.</p>
                                    @endif
                                @else
                                    <p class="mt-2 text-xs text-slate-500">Pendaftaran tidak dibuka.</p>
                                @endif
                            </li>
                        @empty
                            <li class="text-slate-500">Belum ada kelas dibuka.</li>
                        @endforelse
                    </ul>
                @endif
            </section>
        @endcomponent
    </div>
</x-layouts.app>
