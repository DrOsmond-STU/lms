<div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($programs as $program)
        <a href="{{ route($detailRoute, $program->slug) }}" class="card flex flex-col p-5 transition hover:border-brand-300 hover:shadow">
            <div class="mb-3 flex flex-wrap gap-2">
                <span @class(['badge', 'bg-brand-50 text-brand-700' => $program->category === 'international', 'bg-accent-50 text-accent-800' => $program->category === 'bnsp'])>{{ \App\Modules\Catalog\Models\Program::CATEGORIES[$program->category] }}</span>
                @if ($program->level)<span class="badge bg-slate-100 text-slate-700">{{ \App\Modules\Catalog\Models\Program::LEVELS[$program->level] ?? $program->level }}</span>@endif
            </div>
            <h2 class="font-extrabold text-slate-800">{{ $program->name }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ $program->provider_name }}</p>
            <div class="mt-auto flex items-center justify-between pt-4 text-sm">
                <span class="font-bold text-slate-800">{{ $program->priceLabel() }}</span>
                <span class="text-xs text-slate-500">{{ $program->open_classes_count > 0 ? $program->open_classes_count.' kelas dibuka' : 'Belum ada kelas dibuka' }}</span>
            </div>
        </a>
    @empty
        <p class="text-sm text-slate-500 sm:col-span-2 lg:col-span-3">Tidak ada program yang cocok.</p>
    @endforelse
</div>
<div class="mt-6">{{ $programs->links() }}</div>
