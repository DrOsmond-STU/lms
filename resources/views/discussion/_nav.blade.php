{{-- Navigasi ruang interaktif kelas. $class, $member, $tab --}}
<nav class="tabs mb-6" aria-label="Ruang kelas interaktif">
    <a href="{{ route('discussion.index', [$class, 'jenis' => 'discussion']) }}" @if ($tab === 'discussion') aria-current="page" @endif>Forum Diskusi</a>
    <a href="{{ route('discussion.index', [$class, 'jenis' => 'question']) }}" @if ($tab === 'question') aria-current="page" @endif>Tanya Jawab</a>
    <a href="{{ route('discussion.polls', $class) }}" @if ($tab === 'polls') aria-current="page" @endif>Polling</a>
    @if ($class->chat_enabled)<a href="{{ route('discussion.chat', $class) }}" @if ($tab === 'chat') aria-current="page" @endif>Obrolan</a>@endif
    @if ($member['role'] === 'moderator')<a href="{{ route('discussion.reports', $class) }}" @if ($tab === 'reports') aria-current="page" @endif>Laporan Konten</a>@endif
</nav>
