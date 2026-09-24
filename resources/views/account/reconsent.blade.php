<x-layouts.guest title="Pembaruan Kebijakan">
    <h2 class="text-2xl font-extrabold text-slate-800">Pembaruan Kebijakan</h2>
    <p class="mt-1 mb-6 text-sm text-slate-600">Syarat &amp; Ketentuan atau Kebijakan Privasi telah diperbarui. Tinjau dan setujui untuk melanjutkan.</p>
    <form method="POST" action="{{ route('consent.store') }}" class="space-y-3" novalidate>@csrf
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="accept_terms" value="1" class="mt-0.5"> <span>Saya menyetujui <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener" class="font-bold text-brand-700 hover:underline">Syarat &amp; Ketentuan</a> versi {{ config('legal.terms_version') }}.</span></label>
        <x-form-error field="accept_terms" />
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="accept_privacy" value="1" class="mt-0.5"> <span>Saya menyetujui <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener" class="font-bold text-brand-700 hover:underline">Kebijakan Privasi</a> versi {{ config('legal.privacy_version') }}.</span></label>
        <x-form-error field="accept_privacy" />
        <button class="btn-primary">Setuju &amp; Lanjutkan</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="text-sm font-bold text-slate-600 hover:underline">Keluar</button></form>
</x-layouts.guest>
