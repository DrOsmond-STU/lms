## Ringkasan
<!-- Apa yang diubah dan mengapa. Tautkan tiket & kode kebutuhan (FR/NFR/SEC). -->

## Jenis perubahan
- [ ] feat  - [ ] fix  - [ ] sec  - [ ] refactor  - [ ] docs  - [ ] chore

## Cara menguji
<!-- Langkah verifikasi manual + uji otomatis yang ditambahkan. -->

## Checklist keamanan (docs/keamanan/17-checklist-keamanan.md §1)
- [ ] Rute/aksi baru memiliki middleware peran + otorisasi Policy; objek di luar scope → 404
- [ ] Input via FormRequest + `validated()`; tidak ada `$request->all()` ke model
- [ ] Tidak ada `{!! !!}`, `x-html`, SQL berinterpolasi, `withoutGlobalScope` tanpa anotasi
- [ ] Tabel ber-tenant baru punya `organization_id` + RLS + uji RLS
- [ ] Data sensitif terenkripsi, tidak dicatat di log, tidak dikirim ke klien tanpa perlu
- [ ] Aksi penting tercatat di jejak audit / security events
- [ ] Tidak ada rahasia di kode, konfigurasi, atau fixture
- [ ] Dependensi baru dijustifikasi (lisensi, pemeliharaan) — atau tidak ada
- [ ] Permukaan serangan baru? Model ancaman (`docs/keamanan/01`) diperbarui
