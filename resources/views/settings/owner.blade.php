<x-layouts.app title="Pengaturan — Profil Pemilik" workspace="admin">
    <x-slot:heading>Pengaturan Sistem</x-slot:heading>
    <x-slot:subtitle>Informasi pemilik situs: tampil di beranda ("Tentang Kami", kontak, kaki halaman), sebagai penerbit pada PDF sertifikat, dan di halaman verifikasi. Kolom kosong tidak ditampilkan.</x-slot:subtitle>
    @include('settings._tabs', ['tab' => 'pemilik'])
    <form method="POST" action="{{ route('admin.settings.owner.update') }}" class="card max-w-3xl space-y-4 p-6" novalidate>
        @csrf
        @method('PUT')
        <fieldset @disabled(! auth()->user()->can('cms.update')) class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label for="company_name" class="form-label">Nama pemilik / perusahaan</label><input id="company_name" name="company_name" value="{{ old('company_name', $profile->company_name) }}" maxlength="160" required class="form-input"><x-form-error field="company_name" /></div>
                <div><label for="tagline" class="form-label">Tagline</label><input id="tagline" name="tagline" value="{{ old('tagline', $profile->tagline) }}" maxlength="200" class="form-input"><x-form-error field="tagline" /></div>
            </div>
            <div><label for="about" class="form-label">Tentang kami</label><textarea id="about" name="about" maxlength="1200" rows="4" class="form-input">{{ old('about', $profile->about) }}</textarea><x-form-error field="about" /></div>
            <div><label for="address" class="form-label">Alamat</label><textarea id="address" name="address" maxlength="300" rows="2" class="form-input">{{ old('address', $profile->address) }}</textarea><x-form-error field="address" /></div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div><label for="email" class="form-label">Email</label><input id="email" name="email" type="email" value="{{ old('email', $profile->email) }}" maxlength="254" class="form-input"><x-form-error field="email" /></div>
                <div><label for="phone" class="form-label">Telepon</label><input id="phone" name="phone" type="tel" value="{{ old('phone', $profile->phone) }}" maxlength="30" class="form-input"><x-form-error field="phone" /></div>
                <div><label for="whatsapp" class="form-label">WhatsApp</label><input id="whatsapp" name="whatsapp" type="tel" value="{{ old('whatsapp', $profile->whatsapp) }}" maxlength="30" class="form-input" placeholder="08…"><x-form-error field="whatsapp" /></div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label for="business_hours" class="form-label">Jam layanan</label><input id="business_hours" name="business_hours" value="{{ old('business_hours', $profile->business_hours) }}" maxlength="120" class="form-input" placeholder="Senin–Jumat, 08.00–17.00 WIB"><x-form-error field="business_hours" /></div>
                <div><label for="website_url" class="form-label">Situs web</label><input id="website_url" name="website_url" value="{{ old('website_url', $profile->website_url) }}" maxlength="300" class="form-input" placeholder="https://"><x-form-error field="website_url" /></div>
                <div><label for="linkedin_url" class="form-label">LinkedIn</label><input id="linkedin_url" name="linkedin_url" value="{{ old('linkedin_url', $profile->linkedin_url) }}" maxlength="300" class="form-input" placeholder="https://"><x-form-error field="linkedin_url" /></div>
                <div><label for="instagram_url" class="form-label">Instagram</label><input id="instagram_url" name="instagram_url" value="{{ old('instagram_url', $profile->instagram_url) }}" maxlength="300" class="form-input" placeholder="https://"><x-form-error field="instagram_url" /></div>
            </div>
            @can('cms.update')<button type="submit" class="btn-primary w-auto">Simpan</button>@endcan
        </fieldset>
    </form>
</x-layouts.app>
