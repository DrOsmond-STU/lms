@php($categories = \App\Modules\Catalog\Models\Program::CATEGORIES)
<x-layouts.app :title="$program->name" workspace="admin">
    <x-slot:back><a href="{{ route('admin.programs.index') }}" class="hero-back">&larr; Program Pelatihan</a></x-slot:back>
    <x-slot:heading>{{ $program->name }}</x-slot:heading>
    <x-slot:meta>
        <span class="badge bg-brand-50 font-mono text-link">{{ $program->short_code }}</span>
        @include('admin.programs._status', ['status' => $program->status])
    </x-slot:meta>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card p-6" aria-labelledby="detail-heading">
                <div class="mb-4 flex items-center justify-between">
                    <h2 id="detail-heading" class="font-bold text-slate-800">Detail Program</h2>
                    @if (in_array($program->status, ['draft', 'published'], true))
                        @can('program.update')
                            <a href="{{ route('admin.programs.edit', $program) }}" class="btn-secondary">Ubah</a>
                        @endcan
                    @endif
                </div>
                <dl class="grid gap-3 text-sm sm:grid-cols-3">
                    <div><dt class="text-slate-500">Kategori</dt><dd class="font-bold">{{ $categories[$program->category] ?? $program->category }}</dd></div>
                    <div><dt class="text-slate-500">Penyelenggara</dt><dd class="font-bold">{{ $program->provider_name }}</dd></div>
                    <div><dt class="text-slate-500">Kode skema</dt><dd class="font-bold">{{ $program->scheme_code ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Harga</dt><dd class="font-bold">{{ $program->priceLabel() }}</dd></div>
                    <div><dt class="text-slate-500">Skor minimal</dt><dd class="font-bold">{{ fmt_score($program->passing_score) }}</dd></div>
                    <div><dt class="text-slate-500">Masa berlaku sertifikat</dt><dd class="font-bold">{{ $program->certificate_validity_months > 0 ? $program->certificate_validity_months.' bulan' : 'Tanpa kedaluwarsa' }}</dd></div>
                    <div><dt class="text-slate-500">Durasi</dt><dd class="font-bold">{{ $program->duration_hours }} jam</dd></div>
                    <div><dt class="text-slate-500">Mode</dt><dd class="font-bold">{{ \App\Modules\Catalog\Models\Program::MODES[$program->default_mode] ?? $program->default_mode }}</dd></div>
                    <div><dt class="text-slate-500">Tag</dt><dd class="font-bold">{{ $tags === [] ? '—' : implode(', ', $tags) }}</dd></div>
                </dl>
                @if ($program->description_html)
                    <div class="prose-content mt-5 border-t border-slate-100 pt-5 text-sm">@include('components.safe-html', ['html' => $program->description_html])</div>
                @endif
            </section>

            <section class="card p-6" aria-labelledby="classes-heading">
                <div class="mb-4 flex items-center justify-between">
                    <h2 id="classes-heading" class="font-bold text-slate-800">Kelas / Batch</h2>
                    @can('course_class.create')
                        <a href="{{ route('admin.classes.create', $program) }}" class="btn-secondary">Tambah Kelas</a>
                    @endcan
                </div>
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($classes as $class)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                            <span><a href="{{ route('classes.manage', $class) }}" class="font-bold text-link hover:underline">{{ $class->batch_name }}</a>
                                <span class="block text-xs text-slate-500">{{ $class->starts_on->translatedFormat('d M Y') }} – {{ $class->ends_on->translatedFormat('d M Y') }} · {{ $class->enrolled_count }}/{{ $class->quota }} peserta</span></span>
                            <span class="badge bg-slate-100 text-slate-700">{{ \App\Modules\Learning\Models\CourseClass::STATUSES[$class->status] ?? $class->status }}</span>
                        </li>
                    @empty
                        <li class="py-3 text-slate-500">Belum ada kelas.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        <div class="space-y-6">
            <section class="card p-6" aria-labelledby="lifecycle-heading">
                <h2 id="lifecycle-heading" class="font-bold text-slate-800">Status &amp; Review</h2>
                <dl class="mt-3 space-y-1 text-xs text-slate-600">
                    <div>Dibuat oleh: <span class="font-bold">{{ $people[$program->created_by] ?? '—' }}</span></div>
                    <div>Diajukan oleh: <span class="font-bold">{{ $people[$program->submitted_by] ?? '—' }}</span></div>
                    <div>Diterbitkan oleh: <span class="font-bold">{{ $people[$program->reviewed_by] ?? '—' }}</span></div>
                </dl>
                <x-form-error field="status" />
                <div class="mt-4 space-y-3">
                    @if ($program->status === 'draft')
                        @can('program.submit_review')
                            <form method="POST" action="{{ route('admin.programs.submit', $program) }}">@csrf<button type="submit" class="btn-primary">Ajukan Review</button></form>
                        @endcan
                    @elseif ($program->status === 'in_review')
                        @can('program.publish')
                            @if ($program->submitted_by !== auth()->id())
                                <form method="POST" action="{{ route('admin.programs.publish', $program) }}">@csrf<button type="submit" class="btn-primary">Setujui &amp; Terbitkan</button></form>
                                <form method="POST" action="{{ route('admin.programs.reject', $program) }}" class="space-y-2" novalidate>
                                    @csrf
                                    <label for="reject_reason" class="form-label">Alasan dikembalikan</label>
                                    <input id="reject_reason" name="reason" type="text" required minlength="5" maxlength="500" class="form-input">
                                    <x-form-error field="reason" />
                                    <button type="submit" class="btn-secondary">Kembalikan ke Draf</button>
                                </form>
                            @else
                                <p class="rounded-lg bg-amber-50 p-3 text-xs text-amber-800">Anda pengaju review ini. Penerbitan harus dilakukan admin lain.</p>
                            @endif
                        @endcan
                    @elseif ($program->status === 'published')
                        @can('program.archive')
                            <form method="POST" action="{{ route('admin.programs.archive', $program) }}" class="space-y-2" novalidate>
                                @csrf
                                <label for="archive_reason" class="form-label">Alasan arsip</label>
                                <input id="archive_reason" name="reason" type="text" required minlength="5" maxlength="500" class="form-input">
                                <x-form-error field="reason" />
                                <button type="submit" class="btn-danger">Arsipkan</button>
                            </form>
                        @endcan
                    @endif
                </div>
            </section>

            <section class="card p-6" aria-labelledby="bank-heading">
                <h2 id="bank-heading" class="font-bold text-slate-800">Bank Soal</h2>
                <ul class="mt-3 space-y-1 text-sm">
                    @forelse ($banks as $bank)
                        <li><a href="{{ route('banks.show', $bank->id) }}" class="font-bold text-link hover:underline">{{ $bank->name }}</a></li>
                    @empty
                        <li class="text-slate-500">Belum ada bank soal.</li>
                    @endforelse
                </ul>
                <a href="{{ route('banks.index', $program) }}" class="mt-3 inline-block text-sm font-bold text-link hover:underline">Kelola bank soal</a>
            </section>
        </div>
    </div>
</x-layouts.app>
