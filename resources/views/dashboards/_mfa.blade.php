@unless ($user->hasConfirmedMfa())
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <span>Lindungi akun Anda dengan autentikasi dua faktor.</span>
        <a href="{{ route('mfa.setup') }}" class="font-bold hover:underline">Aktifkan sekarang</a>
    </div>
@endunless
