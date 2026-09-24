<form method="GET" class="card mb-6 flex flex-wrap items-end gap-3 p-4" role="search">
    <div class="min-w-48 flex-1">
        <label for="q" class="form-label">Cari program</label>
        <input id="q" name="q" type="search" value="{{ $search }}" maxlength="100" class="form-input">
    </div>
    <div>
        <label for="kategori" class="form-label">Kategori</label>
        <select id="kategori" name="kategori" class="form-select">
            <option value="">Semua</option>
            @foreach (\App\Modules\Catalog\Models\Program::CATEGORIES as $value => $label)<option value="{{ $value }}" @selected($category === $value)>{{ $label }}</option>@endforeach
        </select>
    </div>
    <div>
        <label for="level" class="form-label">Level</label>
        <select id="level" name="level" class="form-select">
            <option value="">Semua</option>
            @foreach (\App\Modules\Catalog\Models\Program::LEVELS as $value => $label)<option value="{{ $value }}" @selected($level === $value)>{{ $label }}</option>@endforeach
        </select>
    </div>
    <div>
        <label for="mode" class="form-label">Mode</label>
        <select id="mode" name="mode" class="form-select">
            <option value="">Semua</option>
            @foreach (\App\Modules\Catalog\Models\Program::MODES as $value => $label)<option value="{{ $value }}" @selected($mode === $value)>{{ $label }}</option>@endforeach
        </select>
    </div>
    <div>
        <label for="harga" class="form-label">Harga</label>
        <select id="harga" name="harga" class="form-select">
            <option value="">Semua</option>
            <option value="gratis" @selected($price === 'gratis')>Gratis</option>
            <option value="berbayar" @selected($price === 'berbayar')>Berbayar</option>
        </select>
    </div>
    <button type="submit" class="btn-secondary">Terapkan</button>
</form>
