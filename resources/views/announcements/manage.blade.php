@php($canEdit = $canEdit ?? true)
<x-layouts.app :title="$class ? 'Pengumuman '.$class->batch_name : 'Pengumuman'" :workspace="$workspace">
    @if ($mode === 'class')
        <x-slot:back><a href="{{ $workspace === 'admin' ? route('admin.programs.show', $class->program) : route('trainer.classes') }}" class="hero-back">&larr; {{ $workspace === 'admin' ? $class->program->name : 'Kelas Saya' }}</a></x-slot:back>
        <x-slot:heading>{{ $class->program->name }} — {{ $class->batch_name }}</x-slot:heading>
        <x-slot:meta><span class="badge bg-slate-100 text-slate-700">{{ \App\Modules\Learning\Models\CourseClass::STATUSES[$class->status] ?? $class->status }}</span></x-slot:meta>
        <x-slot:subtitle>Pengumuman untuk peserta kelas ini. Tampil di dashboard dan menu Pengumuman peserta.</x-slot:subtitle>
        @include('classes._header')
    @else
        <x-slot:heading>{{ $mode === 'admin' ? 'Kelola Pengumuman' : 'Pengumuman Organisasi' }}</x-slot:heading>
        <x-slot:subtitle>{{ $mode === 'admin' ? 'Pengumuman untuk seluruh platform, organisasi tertentu, atau kelas tertentu.' : 'Pengumuman untuk anggota organisasi Anda. Tampil di dashboard dan menu Pengumuman mereka.' }}</x-slot:subtitle>
    @endif
    <x-form-error field="title" />

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-3 xl:col-span-2">
            @forelse ($announcements as $item)
                <article class="card p-5">
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        @if ($item->is_pinned)<span class="badge bg-amber-50 text-amber-800">Disematkan</span>@endif
                        <span @class(['badge', 'bg-emerald-50 text-emerald-700' => $item->isLive(), 'bg-slate-100 text-slate-600' => ! $item->isLive()])>{{ $item->isLive() ? 'Tayang' : ($item->publish_at->isFuture() ? 'Terjadwal' : 'Berakhir') }}</span>
                        @if ($mode === 'admin')<span class="badge bg-slate-100 text-slate-700">{{ $item->scopeLabel() }}{{ $item->organization ? ': '.$item->organization->name : ($item->courseClass ? ': '.$item->courseClass->batch_name : '') }}</span>@endif
                        <span class="text-slate-500">{{ $item->publish_at->timezone(display_tz())->translatedFormat('d M Y H:i') }}@if ($item->author) · {{ $item->author->name }}@endif</span>
                    </div>
                    <h2 class="mt-2 font-extrabold text-slate-800">{{ $item->title }}</h2>
                    <div class="prose-content mt-2 text-sm">@include('components.safe-html', ['html' => $item->body_html])</div>
                    @if ($canEdit)
                        <div class="mt-3 flex flex-wrap items-center gap-3 text-xs">
                            <details class="text-left"><summary class="cursor-pointer font-bold text-link hover:underline">Ubah</summary>
                                <div class="mt-2 max-w-xl rounded-lg border border-slate-200 bg-surface p-3 shadow">@include('announcements._form', ['action' => route(($mode === 'admin' ? 'admin' : ($mode === 'organization' ? 'org' : 'classes')).'.announcements.update', $item), 'method' => 'PUT', 'item' => $item])</div>
                            </details>
                            <form method="POST" action="{{ route(($mode === 'admin' ? 'admin' : ($mode === 'organization' ? 'org' : 'classes')).'.announcements.destroy', $item) }}" data-confirm="Hapus pengumuman ini?">@csrf @method('DELETE')<button class="btn-mini-danger">Hapus</button></form>
                        </div>
                    @endif
                </article>
            @empty
                <div class="card p-8 text-center text-sm text-slate-500">Belum ada pengumuman.</div>
            @endforelse
            <div>{{ $announcements->links() }}</div>
        </div>
        @if ($canEdit)
            <aside>
                <section class="card p-5" aria-labelledby="new-ann">
                    <h2 id="new-ann" class="font-bold text-slate-800">Pengumuman Baru</h2>
                    <div class="mt-3">@include('announcements._form', ['action' => $action, 'method' => 'POST', 'item' => null])</div>
                </section>
            </aside>
        @endif
    </div>
</x-layouts.app>
