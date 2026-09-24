<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <section class="card p-6">
            <div class="mb-3 flex flex-wrap gap-2">
                <span class="badge bg-brand-50 text-link">{{ \App\Modules\Catalog\Models\Program::CATEGORIES[$program->category] }}</span>
                @if ($program->scheme_code)<span class="badge bg-slate-100 text-slate-700">Skema {{ $program->scheme_code }}</span>@endif
                <span class="badge bg-slate-100 text-slate-700">{{ $program->duration_hours }} jam</span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-800">{{ $program->name }}</h1>
            <p class="mt-1 text-sm text-slate-600">Penyelenggara: {{ $program->provider_name }}</p>
            @if ($program->description_html)
                <div class="prose-content mt-4 text-sm">@include('components.safe-html', ['html' => $program->description_html])</div>
            @endif
        </section>
        <section class="card p-6" aria-labelledby="syllabus-heading">
            <h2 id="syllabus-heading" class="font-bold text-slate-800">Silabus</h2>
            <ol class="mt-3 space-y-3 text-sm">
                @forelse ($syllabus as $module)
                    <li><span class="font-bold">{{ $loop->iteration }}. {{ $module->title }}</span>
                        <ul class="mt-1 list-disc pl-6 text-slate-600">@foreach ($module->chapters as $chapter)<li>{{ $chapter->title }}</li>@endforeach</ul>
                    </li>
                @empty
                    <li class="text-slate-500">Silabus akan tersedia saat kelas dibuka.</li>
                @endforelse
            </ol>
        </section>
    </div>
    <aside class="space-y-6">
        <section class="card p-6">
            <p class="text-2xl font-extrabold text-slate-800">{{ $program->priceLabel() }}</p>
            <dl class="mt-3 space-y-1 text-sm text-slate-600">
                <div>Skor minimal lulus: <span class="font-bold">{{ rtrim(rtrim($program->passing_score, '0'), '.') }}</span></div>
                <div>Sertifikat berlaku: <span class="font-bold">{{ $program->certificate_validity_months > 0 ? $program->certificate_validity_months.' bulan' : 'tanpa kedaluwarsa' }}</span></div>
                <div>Mode: <span class="font-bold">{{ \App\Modules\Catalog\Models\Program::MODES[$program->default_mode] }}</span></div>
            </dl>
        </section>
        {{ $slot ?? '' }}
    </aside>
</div>
