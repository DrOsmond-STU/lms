<x-layouts.app title="Basis Data Sertifikat" workspace="admin">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-extrabold text-slate-800">Basis Data Sertifikat</h1>
            <p class="mt-0.5 text-sm text-slate-600">Ekspor tercatat di jejak audit.</p>
        </div>
        @can('report.export')
            <a href="{{ route('admin.certificates.export', request()->query()) }}" class="btn-secondary">Ekspor CSV</a>
        @endcan
    </div>
    <form method="GET" class="card mb-5 flex flex-wrap items-end gap-3 p-4" role="search">
        <div class="min-w-48 flex-1"><label for="q" class="form-label">Nomor / nama / kode</label><input id="q" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" class="form-input"></div>
        <div><label for="status" class="form-label">Status</label>
            <select id="status" name="status" class="form-select">
                <option value="">Semua</option>
                @foreach (['valid' => 'Valid', 'expired' => 'Kedaluwarsa', 'revoked' => 'Dicabut', 'processing' => 'Diproses'] as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach
            </select></div>
        <div><label for="program" class="form-label">Program</label>
            <select id="program" name="program" class="form-select"><option value="">Semua</option>@foreach ($programs as $program)<option value="{{ $program->id }}" @selected(($filters['program'] ?? '') === $program->id)>{{ $program->name }}</option>@endforeach</select></div>
        <div><label for="organisasi" class="form-label">Organisasi</label>
            <select id="organisasi" name="organisasi" class="form-select"><option value="">Semua</option>@foreach ($organizations as $organization)<option value="{{ $organization->id }}" @selected(($filters['organisasi'] ?? '') === $organization->id)>{{ $organization->code }}</option>@endforeach</select></div>
        <div><label for="tahun" class="form-label">Tahun</label><input id="tahun" name="tahun" type="number" min="2000" max="2100" value="{{ $filters['tahun'] ?? '' }}" class="form-input w-28"></div>
        <button class="btn-secondary">Terapkan</button>
    </form>
    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th scope="col">Nomor</th><th scope="col">Pemegang</th><th scope="col">Program</th><th scope="col">Terbit</th><th scope="col">Status</th></tr></thead>
            <tbody>
                @forelse ($certificates as $certificate)
                    <tr>
                        <td><a href="{{ route('admin.certificates.show', $certificate) }}" class="font-mono text-xs font-bold text-brand-700 hover:underline">{{ $certificate->number }}</a></td>
                        <td>{{ $certificate->holder_name }}</td>
                        <td>{{ $certificate->program_name }}</td>
                        <td class="text-xs">{{ $certificate->issued_at->format('d M Y') }}</td>
                        <td><span class="badge bg-slate-100 text-slate-700">{{ \App\Modules\Certification\Models\Certificate::statusLabel($certificate->publicStatus()) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">Tidak ada sertifikat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $certificates->links() }}</div>
</x-layouts.app>
