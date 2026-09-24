<x-layouts.app title="Undang Pengguna" workspace="admin">
    <x-slot:back><a href="{{ route('admin.users.index') }}" class="hero-back">&larr; Pengguna</a></x-slot:back>
    <x-slot:heading>Undang Pengguna</x-slot:heading>
    <x-slot:subtitle>Pengguna menerima email berisi tautan untuk mengatur kata sandi sendiri (berlaku {{ config('security.invitation.ttl_hours') }} jam). Peran admin &amp; trainer wajib mengaktifkan MFA saat masuk pertama.</x-slot:subtitle>

    <form method="POST" action="{{ route('admin.users.store') }}" class="card max-w-2xl space-y-4 p-6" novalidate>
        @csrf
        <div>
            <label for="name" class="form-label">Nama Lengkap</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="120" class="form-input">
            <x-form-error field="name" />
        </div>
        <div>
            <label for="email" class="form-label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="254" class="form-input">
            <x-form-error field="email" />
        </div>
        @include('admin.users._role-fields')
        <button type="submit" class="btn-primary w-auto">Kirim Undangan</button>
    </form>
</x-layouts.app>
