{{-- Header & tab kelola kelas. $class, $tab, $canManageSettings --}}
<a href="{{ $workspace === 'admin' ? route('admin.programs.show', $class->program) : route('trainer.classes') }}" class="text-sm font-bold text-brand-700 hover:underline">&larr; {{ $workspace === 'admin' ? $class->program->name : 'Kelas Saya' }}</a>
<div class="mt-2 mb-1 flex flex-wrap items-center gap-3">
    <h1 class="text-xl font-extrabold text-slate-800">{{ $class->program->name }} — {{ $class->batch_name }}</h1>
    <span class="badge bg-slate-100 text-slate-700">{{ \App\Modules\Learning\Models\CourseClass::STATUSES[$class->status] ?? $class->status }}</span>
</div>
<p class="mb-5 text-sm text-slate-600">{{ $class->starts_on->translatedFormat('d M Y') }} – {{ $class->ends_on->translatedFormat('d M Y') }} · {{ \App\Modules\Catalog\Models\Program::MODES[$class->mode] ?? $class->mode }} · {{ $class->enrolled_count }}/{{ $class->quota }} peserta · Trainer: {{ $class->trainers->pluck('name')->implode(', ') ?: 'belum ada' }}</p>
<nav class="mb-6 flex flex-wrap gap-1 rounded-lg bg-slate-100 p-1 text-sm font-bold" aria-label="Bagian kelas">
    @foreach (['content' => ['classes.manage', 'Konten'], 'assessments' => ['classes.assessments', 'Asesmen'], 'participants' => ['classes.participants', 'Peserta']] as $key => [$routeName, $label])
        <a href="{{ route($routeName, $class) }}" @class(['rounded-md px-4 py-2', 'bg-white text-brand-800 shadow' => $tab === $key, 'text-slate-600 hover:text-slate-800' => $tab !== $key]) @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
    @if ($canManageSettings)
        <a href="{{ route('admin.classes.edit', $class) }}" class="rounded-md px-4 py-2 text-slate-600 hover:text-slate-800">Pengaturan</a>
    @endif
</nav>
<x-form-error field="content" />
<x-form-error field="status" />
