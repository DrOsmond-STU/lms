<x-layouts.app :title="'Kelola '.$class->batch_name" :workspace="$workspace">
    <x-slot:back><a href="{{ $workspace === 'admin' ? route('admin.programs.show', $class->program) : route('trainer.classes') }}" class="hero-back">&larr; {{ $workspace === 'admin' ? $class->program->name : 'Kelas Saya' }}</a></x-slot:back>
    <x-slot:heading>{{ $class->program->name }} — {{ $class->batch_name }}</x-slot:heading>
    <x-slot:meta><span class="badge bg-slate-100 text-slate-700">{{ \App\Modules\Learning\Models\CourseClass::STATUSES[$class->status] ?? $class->status }}</span></x-slot:meta>
    <x-slot:subtitle>{{ $class->starts_on->translatedFormat('d M Y') }} – {{ $class->ends_on->translatedFormat('d M Y') }} · {{ \App\Modules\Catalog\Models\Program::MODES[$class->mode] ?? $class->mode }} · {{ $class->enrolled_count }}/{{ $class->quota }} peserta · Trainer: {{ $class->trainers->pluck('name')->implode(', ') ?: 'belum ada' }}</x-slot:subtitle>
    @include('classes._header')

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">
            @forelse ($class->modules as $module)
                <section class="card p-5" aria-labelledby="module-{{ $module->id }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 id="module-{{ $module->id }}" class="font-bold text-slate-800">Modul {{ $loop->iteration }}: {{ $module->title }}</h2>
                        @if ($canEditContent)
                            <div class="flex items-center gap-1">
                                @include('classes._move', ['action' => route('content.modules.move', [$class, $module]), 'label' => 'modul '.$module->title])
                                <form method="POST" action="{{ route('content.modules.destroy', [$class, $module]) }}">@csrf @method('DELETE')<button type="submit" class="btn-mini-danger">Hapus</button></form>
                            </div>
                        @endif
                    </div>
                    @if ($canEditContent)
                        <details class="mt-2 text-sm">
                            <summary class="cursor-pointer text-xs font-bold text-link">Ganti nama modul</summary>
                            <form method="POST" action="{{ route('content.modules.update', [$class, $module]) }}" class="mt-2 flex gap-2">@csrf @method('PUT')
                                <label for="mt-{{ $module->id }}" class="sr-only">Judul modul</label>
                                <input id="mt-{{ $module->id }}" name="title" value="{{ $module->title }}" maxlength="200" class="form-input py-1.5">
                                <button class="btn-secondary">Simpan</button>
                            </form>
                        </details>
                    @endif

                    @foreach ($module->chapters as $chapter)
                        <div class="mt-4 rounded-lg border border-slate-200">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50 px-4 py-2">
                                <h3 class="text-sm font-bold text-slate-700">Bab {{ $loop->iteration }}: {{ $chapter->title }}</h3>
                                @if ($canEditContent)
                                    <div class="flex items-center gap-1">
                                        @include('classes._move', ['action' => route('content.chapters.move', [$class, $chapter]), 'label' => 'bab '.$chapter->title])
                                        <form method="POST" action="{{ route('content.chapters.destroy', [$class, $chapter]) }}">@csrf @method('DELETE')<button type="submit" class="btn-mini-danger">Hapus</button></form>
                                    </div>
                                @endif
                            </div>
                            @if ($canEditContent)
                                <details class="border-b border-slate-100 px-4 py-2 text-sm">
                                    <summary class="cursor-pointer text-xs font-bold text-link">Ganti nama bab</summary>
                                    <form method="POST" action="{{ route('content.chapters.update', [$class, $chapter]) }}" class="mt-2 flex gap-2">@csrf @method('PUT')
                                        <label for="ct-{{ $chapter->id }}" class="sr-only">Judul bab</label>
                                        <input id="ct-{{ $chapter->id }}" name="title" value="{{ $chapter->title }}" maxlength="200" required class="form-input py-1.5">
                                        <button class="btn-secondary">Simpan</button>
                                    </form>
                                </details>
                            @endif
                            <ul class="divide-y divide-slate-100 text-sm">
                                @forelse ($chapter->lessons as $lesson)
                                    <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-2">
                                        <span>
                                            <span class="badge mr-1 bg-brand-50 text-link">{{ \App\Modules\Learning\Models\Lesson::TYPES[$lesson->type] }}</span>
                                            <span class="font-semibold">{{ $lesson->title }}</span>
                                            @unless ($lesson->is_required)<span class="text-xs text-slate-500">(opsional)</span>@endunless
                                            @if ($lesson->type === 'quiz' && $lesson->assessment)<span class="text-xs text-slate-500">→ {{ $lesson->assessment->title }}</span>@endif
                                            @if ($lesson->media && $lesson->media->scanner === 'none')<span class="text-xs text-amber-700">(belum dipindai antivirus)</span>@endif
                                        </span>
                                        @if ($canEditContent)
                                            <span class="flex items-center gap-1">
                                                @include('classes._move', ['action' => route('content.lessons.move', [$class, $lesson]), 'label' => 'lesson '.$lesson->title])
                                                <a href="{{ route('content.lessons.edit', [$class, $lesson]) }}" class="px-2 text-xs font-bold text-link hover:underline">Ubah</a>
                                                <form method="POST" action="{{ route('content.lessons.destroy', [$class, $lesson]) }}">@csrf @method('DELETE')<button type="submit" class="btn-mini-danger">Hapus</button></form>
                                            </span>
                                        @endif
                                    </li>
                                @empty
                                    <li class="px-4 py-2 text-slate-500">Belum ada lesson.</li>
                                @endforelse
                            </ul>
                            @if ($canEditContent)
                                <div class="flex flex-wrap gap-2 border-t border-slate-100 px-4 py-2 text-xs">
                                    <span class="font-bold text-slate-500">Tambah lesson:</span>
                                    @foreach (\App\Modules\Learning\Models\Lesson::TYPES as $type => $label)
                                        <a href="{{ route('content.lessons.create', [$class, $chapter, 'tipe' => $type]) }}" class="font-bold text-link hover:underline">{{ $label }}</a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach

                    @if ($canEditContent)
                        <form method="POST" action="{{ route('content.chapters.store', [$class, $module]) }}" class="mt-4 flex gap-2">@csrf
                            <label for="nc-{{ $module->id }}" class="sr-only">Judul bab baru</label>
                            <input id="nc-{{ $module->id }}" name="title" placeholder="Judul bab baru" maxlength="200" required class="form-input py-1.5">
                            <button class="btn-secondary whitespace-nowrap">Tambah Bab</button>
                        </form>
                    @endif
                </section>
            @empty
                <div class="card p-8 text-center text-sm text-slate-500">Belum ada modul. Mulai susun materi dengan menambahkan modul.</div>
            @endforelse

            @if ($canEditContent)
                <form method="POST" action="{{ route('content.modules.store', $class) }}" class="card flex gap-2 p-5">@csrf
                    <label for="new-module" class="sr-only">Judul modul baru</label>
                    <input id="new-module" name="title" placeholder="Judul modul baru" maxlength="200" required class="form-input">
                    <button class="btn-primary w-auto whitespace-nowrap">Tambah Modul</button>
                </form>
            @endif
        </div>

        <aside class="space-y-6">
            @if ($canEditContent && \App\Modules\Ai\Services\ClaudeClient::configured() && auth()->user()->can('ai.author'))
                <section class="card p-5" aria-labelledby="ai-curriculum">
                    <h2 id="ai-curriculum" class="font-bold text-slate-800">Rancang Kurikulum dengan AI</h2>
                    <p class="mt-1 text-xs text-slate-500">Membuat modul → bab → lesson teks berisi kerangka materi sebagai draf yang Anda sunting.</p>
                    <form method="POST" action="{{ route('ai.curriculum', $class) }}" class="mt-3 space-y-3" data-confirm="Tambahkan draf kurikulum AI ke kelas ini?">@csrf
                        <div><label for="ai-goals" class="form-label">Tujuan pembelajaran & fokus</label><textarea id="ai-goals" name="goals" rows="4" required minlength="10" maxlength="4000" class="form-input" placeholder="Contoh: peserta mampu menyusun prosedur keselamatan kerja sesuai ISO 45001, fokus praktik industri manufaktur">{{ old('goals') }}</textarea><x-form-error field="goals" /></div>
                        <div><label for="ai-modules" class="form-label">Jumlah modul</label><input id="ai-modules" name="modules" type="number" min="1" max="8" value="{{ old('modules', 4) }}" class="form-input"></div>
                        <button class="btn-secondary w-full">Buat draf kurikulum</button>
                    </form>
                </section>
            @endif
            @if ($canManageSettings)
                <section class="card p-5" aria-labelledby="status-heading">
                    <h2 id="status-heading" class="font-bold text-slate-800">Status Kelas</h2>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach (\App\Modules\Learning\Models\CourseClass::TRANSITIONS[$class->status] as $next)
                            <form method="POST" action="{{ route('admin.classes.status', $class) }}">@csrf
                                <input type="hidden" name="status" value="{{ $next }}">
                                <button class="btn-secondary">{{ ['open' => 'Buka Pendaftaran', 'running' => 'Mulai Kelas', 'closed' => 'Tutup Kelas', 'archived' => 'Arsipkan'][$next] }}</button>
                            </form>
                        @endforeach
                    </div>
                </section>

                <section class="card p-5" aria-labelledby="trainer-heading">
                    <h2 id="trainer-heading" class="font-bold text-slate-800">Trainer Pengampu</h2>
                    <ul class="mt-3 space-y-1 text-sm">
                        @forelse ($class->trainers as $trainer)
                            <li class="flex items-center justify-between gap-2">
                                <span>{{ $trainer->name }} <span class="text-xs text-slate-500">({{ $trainer->pivot->role === 'lead' ? 'utama' : 'asisten' }})</span></span>
                                <form method="POST" action="{{ route('admin.classes.trainers.destroy', [$class, $trainer]) }}">@csrf @method('DELETE')<button class="btn-mini-danger">Lepas</button></form>
                            </li>
                        @empty
                            <li class="text-slate-500">Belum ada trainer.</li>
                        @endforelse
                    </ul>
                    <form method="POST" action="{{ route('admin.classes.trainers.store', $class) }}" class="mt-3 space-y-2" novalidate>@csrf
                        <label for="trainer_email" class="form-label">Email trainer</label>
                        <input id="trainer_email" name="email" type="email" maxlength="254" required class="form-input">
                        <select name="role" class="form-select" aria-label="Peran trainer"><option value="lead">Trainer utama</option><option value="assistant">Asisten</option></select>
                        <x-form-error field="email" />
                        <button class="btn-secondary">Tambahkan</button>
                    </form>
                </section>
            @endif
            <section class="card p-5 text-sm text-slate-600">
                <h2 class="font-bold text-slate-800">Panduan</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>Video/audio: isi durasi agar progres tonton/dengar dapat dihitung.</li>
                    <li>PDF & dokumen kantor (PPT/Word/Excel) maks. 50 MB. Jenis berkas diperiksa dari isinya.</li>
                    <li>Drip content & prasyarat diatur per lesson; urutan wajib diatur di Pengaturan Kelas.</li>
                    <li>Lesson kuis ditautkan ke kuis di tab Asesmen.</li>
                    <li>Lesson yang sudah dipelajari tidak dapat dihapus.</li>
                </ul>
            </section>
        </aside>
    </div>
</x-layouts.app>
