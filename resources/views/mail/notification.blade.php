<x-mail::message>
# {{ $headline }}

{{ $message }}

@if ($url)
<x-mail::button :url="$url">
Buka {{ config('app.name') }}
</x-mail::button>
@endif

Email ini dikirim otomatis. Kami tidak pernah meminta kata sandi atau kode OTP Anda.
</x-mail::message>
