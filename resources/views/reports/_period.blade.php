<form method="GET" action="{{ $action }}" class="card mb-6 grid gap-3 p-4 sm:grid-cols-5" role="search">
    <div><label for="dari" class="form-label">Dari</label><input id="dari" name="dari" type="date" value="{{ $period['from']->toDateString() }}" class="form-input"></div>
    <div><label for="sampai" class="form-label">Sampai</label><input id="sampai" name="sampai" type="date" value="{{ $period['to']->toDateString() }}" class="form-input"></div>
    <div class="sm:col-span-2"><label for="q" class="form-label">Cari</label><input id="q" name="q" type="search" value="{{ $search ?? '' }}" placeholder="{{ $placeholder ?? 'Nama / kode' }}" class="form-input"></div>
    <div class="flex items-end gap-2">
        <button type="submit" class="btn-secondary w-auto">Terapkan</button>
        @can('report.export')
            @foreach (['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'] as $format => $label)
                <a href="{{ $exportRoute }}?{{ http_build_query(['dari' => $period['from']->toDateString(), 'sampai' => $period['to']->toDateString(), 'q' => $search ?? '', 'format' => $format]) }}" class="{{ $format === 'csv' ? 'btn-primary' : 'btn-secondary' }} w-auto">{{ $label }}</a>
            @endforeach
        @endcan
    </div>
</form>
