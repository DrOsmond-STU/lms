{{-- Logo mitra, atau wordmark (monogram + nama) bila belum ada logo. --}}
@if ($partner->logo_path)
    <img src="{{ route('landing.image.partner', ['partner' => $partner, 'v' => $partner->updated_at?->timestamp]) }}" alt="{{ $partner->name }}" class="h-10 w-auto max-w-[160px] object-contain" loading="lazy">
@else
    <span class="partner-wordmark"><span class="partner-monogram" aria-hidden="true">{{ $partner->monogram() }}</span><span>{{ $partner->name }}</span></span>
@endif
