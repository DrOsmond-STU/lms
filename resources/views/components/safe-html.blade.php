{{-- Merender HTML yang SUDAH disanitasi saat disimpan (App\Support\Content\RichText).
     Satu-satunya titik output HTML mentah; disanitasi ulang saat render sebagai lapisan kedua. --}}
{{ new \Illuminate\Support\HtmlString(\App\Support\Content\RichText::sanitize((string) $html)) }}
