<x-layouts.app :title="'Obrolan '.$class->batch_name" :workspace="$member['workspace']">
    <x-slot:back><a href="{{ app(\App\Modules\Discussion\Services\ClassMembership::class)->backUrl($member['workspace'], $class, $member['enrollment']) }}" class="hero-back">&larr; {{ $class->program->name }}</a></x-slot:back>
    <x-slot:heading>Obrolan Kelas — {{ $class->batch_name }}</x-slot:heading>
    <x-slot:subtitle>Percakapan santai antar peserta dan trainer. Untuk pertanyaan materi, gunakan Tanya Jawab agar mudah ditemukan.</x-slot:subtitle>
    @include('discussion._nav', ['tab' => 'chat'])
    @if ($class->chat_enabled)
        <div class="max-w-3xl"><livewire:class-chat :class="$class" /></div>
    @else
        <p class="card p-6 text-sm text-slate-500">Obrolan kelas dinonaktifkan oleh admin.</p>
    @endif
</x-layouts.app>
