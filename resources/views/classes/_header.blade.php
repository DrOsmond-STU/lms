{{-- Tab kelola kelas (judul kelas ada di slot hero view). $class, $tab, $canManageSettings --}}
<nav class="tabs mb-6" aria-label="Bagian kelas">
    @foreach (['content' => ['classes.manage', 'Konten'], 'assessments' => ['classes.assessments', 'Asesmen'], 'assignments' => ['classes.assignments', 'Tugas'], 'sessions' => ['classes.sessions', 'Sesi & Presensi'], 'participants' => ['classes.participants', 'Peserta']] as $key => [$routeName, $label])
        <a href="{{ route($routeName, $class) }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
    @if ($canManageSettings)
        <a href="{{ route('admin.classes.edit', $class) }}">Pengaturan</a>
    @endif
</nav>
<x-form-error field="content" />
<x-form-error field="status" />
