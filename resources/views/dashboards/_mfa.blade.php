@unless ($user->hasConfirmedMfa())
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 shadow-[var(--shadow-float-sm)]">
        <span class="flex items-center gap-2"><x-icon name="lock" class="h-4 w-4" />Lindungi akun Anda dengan autentikasi dua faktor.</span>
        <a href="{{ route('mfa.setup') }}" class="btn-mini">Aktifkan sekarang</a>
    </div>
@endunless
