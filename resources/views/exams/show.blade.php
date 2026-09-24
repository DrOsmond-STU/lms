<x-layouts.app :title="$assessment->title" workspace="participant">
    <a href="{{ route('learning.classroom', $enrollment) }}" class="text-sm font-bold text-brand-700 hover:underline">&larr; {{ $enrollment->program->name }}</a>
    <h1 class="mt-2 mb-6 text-xl font-extrabold text-slate-800">{{ $assessment->title }}</h1>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card p-6 lg:col-span-2" aria-labelledby="rules-heading">
            <h2 id="rules-heading" class="font-bold text-slate-800">Ketentuan</h2>
            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">Jenis</dt><dd class="font-bold">{{ \App\Modules\Assessment\Models\Assessment::KINDS[$assessment->kind] }}</dd></div>
                <div><dt class="text-slate-500">Jumlah soal</dt><dd class="font-bold">{{ $assessment->question_count }}</dd></div>
                <div><dt class="text-slate-500">Durasi</dt><dd class="font-bold">{{ $assessment->duration_minutes }} menit</dd></div>
                <div><dt class="text-slate-500">Skor minimal</dt><dd class="font-bold">{{ rtrim(rtrim($assessment->passing_score, '0'), '.') }}</dd></div>
                <div><dt class="text-slate-500">Sisa kesempatan</dt><dd class="font-bold">{{ max(0, $remaining) }}</dd></div>
                <div><dt class="text-slate-500">Jendela</dt><dd class="font-bold">{{ $assessment->opens_at?->timezone('Asia/Jakarta')->format('d M H:i') ?? 'kapan saja' }} – {{ $assessment->closes_at?->timezone('Asia/Jakarta')->format('d M H:i') ?? 'tanpa batas' }}</dd></div>
            </dl>
            <div class="mt-5 rounded-lg bg-slate-50 p-4 text-xs text-slate-600">
                <p class="font-bold text-slate-700">Transparansi pengawasan</p>
                <p class="mt-1">Selama mengerjakan, sistem mencatat waktu mulai/kumpul, alamat IP, serta jumlah perpindahan tab dan salin/tempel sebagai <em>indikator</em> yang ditinjau manusia — bukan dasar keputusan otomatis. Tidak ada perekaman kamera, mikrofon, atau layar. Timer berjalan di server: menutup halaman tidak menghentikan waktu, dan jawaban dikumpulkan otomatis saat waktu habis.</p>
            </div>
            <x-form-error field="assessment" />
            <div class="mt-5">
                @if ($active)
                    <a href="{{ route('exams.take', $active) }}" class="btn-primary w-auto">Lanjutkan Attempt #{{ $active->attempt_no }}</a>
                @elseif ($blockReason)
                    <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">{{ $blockReason }}</p>
                @else
                    <form method="POST" action="{{ route('exams.start', [$enrollment, $assessment]) }}" data-confirm="Mulai sekarang? Timer {{ $assessment->duration_minutes }} menit langsung berjalan.">@csrf
                        <button class="btn-primary w-auto">Mulai Mengerjakan</button>
                    </form>
                @endif
            </div>
        </section>
        <section class="card p-6" aria-labelledby="history-heading">
            <h2 id="history-heading" class="font-bold text-slate-800">Riwayat</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($history as $attempt)
                    <li class="flex items-center justify-between gap-2">
                        <a href="{{ $attempt->isInProgress() ? route('exams.take', $attempt) : route('exams.result', $attempt) }}" class="font-bold text-brand-700 hover:underline">Attempt #{{ $attempt->attempt_no }}</a>
                        <span class="text-xs">{{ \App\Modules\Assessment\Models\ExamAttempt::STATUSES[$attempt->status] }} {{ $attempt->score !== null ? '· '.rtrim(rtrim($attempt->score, '0'), '.') : '' }} {{ $attempt->passed ? '✓' : '' }}</span>
                    </li>
                @empty
                    <li class="text-slate-500">Belum pernah dikerjakan.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-layouts.app>
