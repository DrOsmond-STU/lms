<x-layouts.app :title="'Bank Soal — '.$program->name" :workspace="$workspace">
    @if ($workspace === 'admin')
        <x-slot:back><a href="{{ route('admin.programs.show', $program) }}" class="hero-back">&larr; {{ $program->name }}</a></x-slot:back>
    @endif
    <x-slot:heading>Bank Soal</x-slot:heading>
    <x-slot:subtitle>{{ $program->name }}. Bank soal ujian akhir disarankan ≥ 3× jumlah soal per attempt. Setiap akses tercatat di jejak audit.</x-slot:subtitle>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card overflow-x-auto lg:col-span-2">
            <table class="data-table">
                <thead><tr><th scope="col">Bank</th><th scope="col">Soal aktif</th><th scope="col">Total</th></tr></thead>
                <tbody>
                    @forelse ($banks as $bank)
                        <tr><td><a href="{{ route('banks.show', $bank) }}" class="font-bold text-link hover:underline">{{ $bank->name }}</a></td><td>{{ $bank->active_questions_count }}</td><td>{{ $bank->questions_count }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="py-8 text-center text-slate-500">Belum ada bank soal.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <form method="POST" action="{{ route('banks.store', $program) }}" class="card space-y-3 p-5" novalidate>@csrf
            <h2 class="font-bold text-slate-800">Bank Soal Baru</h2>
            <div><label for="name" class="form-label">Nama</label><input id="name" name="name" maxlength="200" required class="form-input" placeholder="Ujian Akhir — Paket A"><x-form-error field="name" /></div>
            <button class="btn-primary w-auto">Buat</button>
        </form>
    </div>
</x-layouts.app>
