{{-- Banner lingkungan non-produksi (keamanan/13 SEC-INFRA-34). --}}
@unless (app()->isProduction() || app()->isLocal() || app()->runningUnitTests())
    <div class="bg-amber-400 px-4 py-1.5 text-center text-xs font-bold text-amber-950" role="note">
        {{ strtoupper(app()->environment()) }} — lingkungan uji coba. Jangan memasukkan data pribadi sungguhan.
    </div>
@endunless
