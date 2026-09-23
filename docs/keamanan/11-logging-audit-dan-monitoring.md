# 11 — Logging, Jejak Audit & Monitoring Keamanan

> Status: **Disetujui untuk development** · Versi 1.0 · Pemilik: Security Lead + DevOps Lead
>
> Acuan: ASVS v5.0 V16 (Security Logging & Error Handling), OWASP Logging Cheat Sheet,
> OWASP Logging Vocabulary. Implementasi pipeline log & dashboard di
> [`../11-devops-dan-deployment.md`](../11-devops-dan-deployment.md).

## 1. Tiga Jenis Catatan

| Jenis | Tujuan | Penyimpanan | Retensi | Dapat diubah? |
|---|---|---|---|---|
| **Log aplikasi** | Debug & operasional | stdout JSON → log store | 90 hari | Tidak (log store append-only) |
| **Security events** (`security_events` + SIEM) | Deteksi serangan & respons insiden | DB (30 hari) + SIEM | 1 tahun online, 2 tahun arsip | Tidak |
| **Jejak audit** (`audit_logs`) | Akuntabilitas & forensik tindakan bisnis | DB berantai hash + salinan WORM | 2 tahun (5 tahun untuk sertifikat & keuangan) | **Tidak pernah** |

## 2. Kebutuhan Log Aplikasi

| ID | Kebutuhan |
|---|---|
| SEC-LOG-01 | Format **JSON terstruktur** (Monolog JSON formatter) dengan field standar: `timestamp` (UTC, ISO 8601, milidetik), `level`, `message`, `request_id`, `trace_id`, `user_id` (UUID, bukan email), `organization_id`, `route`, `method`, `status`, `duration_ms`, `ip` (lengkap hanya di security events; di log aplikasi dipotong/di-hash), `env`, `app_version`. |
| SEC-LOG-02 | `request_id` (ULID) dibuat di edge atau middleware pertama, diteruskan ke job antrian, panggilan keluar, dan dikembalikan di header `X-Request-Id` & halaman error. |
| SEC-LOG-03 | Waktu semua server disinkronkan NTP (penyimpangan < 1 detik) — penting untuk korelasi forensik & TOTP. |
| SEC-LOG-04 | Level log produksi: `info` (tanpa query SQL/bindings), `debug` dilarang di produksi; `error` untuk exception dengan stack trace (hanya di log, bukan ke pengguna). |
| SEC-LOG-05 | **Redaksi otomatis** (Monolog processor + daftar kunci): `password`, `password_confirmation`, `current_password`, `token`, `otp`, `code`, `secret`, `api_key`, `authorization`, `cookie`, `set-cookie`, `signature`, `server_key`, `card*`, `nik`, `npwp`, `phone`, `email` (disamarkan), `answers`, isi berkas. Query string URL bertanda tangan (`signature=`) disamarkan. |
| SEC-LOG-06 | Tidak ada rahasia/PII sensitif di log — diverifikasi uji otomatis (menjalankan alur login/reset/pembayaran lalu memindai log uji untuk pola rahasia) dan pemindaian sampel log produksi bulanan. |
| SEC-LOG-07 | **Error tracking** (Sentry atau setara, lokasi data sesuai penilaian [12](12-privasi-data-dan-kepatuhan-uu-pdp.md)): `send_default_pii = false`, *data scrubbing* aktif untuk field di SEC-LOG-05, tidak mengirim body request/cookie, *breadcrumbs* SQL tanpa bindings, IP tidak disimpan. |
| SEC-LOG-18 | **Anti log injection**: nilai dari pengguna masuk ke field terstruktur (bukan disambung ke `message`), CR/LF di-escape oleh formatter JSON; log viewer tidak merender HTML. |

## 3. Security Events (Wajib Dicatat)

| Kategori | Event (`type`) | Severity default |
|---|---|---|
| Autentikasi | `authn_login_success`, `authn_login_fail`, `authn_login_lockout`, `authn_mfa_fail`, `authn_mfa_recovery_used`, `authn_password_reset_requested`, `authn_password_changed`, `authn_new_device`, `authn_impossible_travel`, `authn_session_revoked` | info / warn |
| Otorisasi | `authz_denied` (403/404 karena scope pada objek yang ada), `authz_suspicious_parameter`, `authz_livewire_tamper`, `authz_tenant_violation_blocked_by_rls` | warn / **crit** untuk RLS |
| Input | `input_validation_anomaly` (payload serangan terdeteksi, mis. pola XSS/SQLi), `csp_violation` (agregat), `upload_malware_detected`, `upload_rejected` | warn / crit |
| Rate limit | `rate_limit_exceeded` (login, OTP, verifikasi, API) | info / warn |
| Sertifikat | `cert_issue_anomaly`, `cert_verification_enumeration`, `cert_kms_sign_mismatch` | warn / crit |
| Pembayaran | `payment_webhook_signature_invalid`, `payment_amount_mismatch`, `payment_state_violation` | crit |
| API | `api_key_invalid`, `api_key_in_query`, `api_ip_not_allowed`, `api_usage_spike` | warn |
| Audit | `audit_chain_broken` | **crit** |
| Sistem | `config_insecure_boot_blocked`, `secret_access_break_glass`, `admin_ip_allowlist_bypass` | crit |

| ID | Kebutuhan |
|---|---|
| SEC-LOG-08 | Semua event di atas dicatat dengan konteks (`user_id`, `ip`, `user_agent`, `request_id`, detail terstruktur) dan diteruskan ke SIEM ≤ 1 menit. |

## 4. Jejak Audit (Audit Trail)

### 4.1 Event Audit Wajib

| Modul | Aksi (`action`) |
|---|---|
| Identity/Access | `user.created`, `user.updated` (field berubah), `user.deactivated`, `user.role_assigned`, `user.role_revoked`, `user.mfa_enabled`, `user.mfa_reset_by_admin`, `user.email_changed`, `session.revoked_all` |
| Organization | `organization.created/updated/archived`, `organization.member_approved/rejected/removed`, `organization.domain_verified` |
| Catalog/Learning | `program.created/updated/submitted/published/archived`, `class.created/updated/trainer_assigned`, `content.published/deleted` |
| Assessment | `question_bank.viewed`, `question_bank.exported`, `question.updated`, `assessment.settings_changed`, `attempt.reset`, `attempt.voided`, `attempt.regraded`, `deadline.extended_bulk` |
| Enrollment | `enrollment.created/cancelled/status_overridden`, `enrollment.bulk_created` |
| Assignment/Attendance | `submission.reviewed`, `submission.score_changed`, `attendance.manual_recorded`, `attendance.changed_after_close` |
| Certification | `certificate.approved`, `certificate.rejected`, `certificate.issued`, `certificate.revocation_requested`, `certificate.revoked`, `certificate.reissued`, `certificate.downloaded_by_admin`, `certificate_template.activated` |
| Payment | `payment.settled`, `payment.manual_mark_paid`, `refund.requested/approved/executed`, `coupon.created/updated/deactivated` |
| Privacy | `privacy_request.created/verified/completed/rejected`, `data.exported_personal`, `user.anonymized`, `consent.granted/withdrawn` |
| Data access | `report.exported`, `pii.bulk_viewed` (daftar > 100 baris berisi PII), `audit_log.exported` |
| Integration/Settings | `api_key.created/revoked`, `webhook_endpoint.created/updated`, `integration.updated`, `system_setting.updated`, `cms.published` |
| Approval | `approval_request.created/approved/rejected/expired` |

### 4.2 Kebutuhan Integritas Audit

| ID | Kebutuhan |
|---|---|
| SEC-LOG-09 | Entri audit memuat: `occurred_at`, `actor_id`, `actor_type`, `actor_role`, `organization_id`, `action`, `subject_type`, `subject_id`, `changes` (before/after dengan redaksi K4), `reason` (bila ada), `ip`, `user_agent`, `request_id`, `approval_request_id` (bila ada). Aktor selalu dari konteks sesi/job — **tidak pernah** dari input. |
| SEC-LOG-10 | **Append-only**: peran DB `stu_app` hanya `INSERT, SELECT`; trigger `BEFORE UPDATE OR DELETE` → `RAISE EXCEPTION`; partisi lama dipindahkan ke arsip oleh proses terpisah dengan kredensial berbeda. |
| SEC-LOG-11 | **Rantai hash**: `hash = SHA256(prev_hash ‖ canonical_json(entri tanpa hash))`, ditulis secara serial (advisory lock atau urutan per partisi) sehingga penghapusan/pengubahan/penyisipan terdeteksi. |
| SEC-LOG-12 | **Salinan eksternal WORM**: entri audit & security events diekspor (≤ 5 menit) ke log store/bucket dengan object lock (akun terpisah) — insider dengan akses DB tidak dapat menghapus semua jejak. *Anchor* hash harian (hash entri terakhir) disimpan juga di lokasi terpisah. |
| SEC-LOG-13 | **Verifikasi harian** rantai hash & pencocokan jumlah entri DB vs salinan eksternal; kegagalan → `audit_chain_broken` (crit) + insiden. |
| SEC-LOG-14 | Akses baca audit sesuai scope peran ([`../07-rbac-dan-multi-tenant.md`](../07-rbac-dan-multi-tenant.md)); **membaca & mengekspor audit juga diaudit**. Tidak ada UI untuk menghapus/mengubah audit. |
| SEC-LOG-15 | Retensi sesuai tabel §1; penghapusan setelah retensi dilakukan per partisi oleh job terjadwal dengan pencatatan. Untuk permintaan hapus akun, entri audit **tidak dihapus** tetapi identitas aktor/subjek dipseudonimkan bila dasar hukum retensi sudah berakhir. |

## 5. Deteksi & Alert (SIEM Use Cases)

| ID | Aturan deteksi | Ambang (awal, dikalibrasi) | Severity | Respons |
|---|---|---|---|---|
| DET-01 | Credential stuffing | > 50 `authn_login_fail` untuk > 20 akun berbeda dari satu IP/ASN dalam 10 menit | High | Blokir di WAF, aktifkan CAPTCHA global |
| DET-02 | Brute force akun admin | ≥ 5 gagal login/MFA akun admin dalam 15 menit | High | Kunci sementara, hubungi pemilik |
| DET-03 | Login admin dari negara/ASN baru atau *impossible travel* | 1 kejadian | High | Verifikasi dengan pemilik, cabut sesi bila tidak dikenal |
| DET-04 | Penolakan otorisasi beruntun | > 20 `authz_denied` dari satu pengguna dalam 5 menit | Medium | Tinjau (indikasi IDOR scanning) |
| DET-05 | RLS memblokir akses lintas tenant | ≥ 1 | **Critical** | Insiden: indikasi bug scope aplikasi |
| DET-06 | Webhook pembayaran tanda tangan salah / jumlah beda | > 5/jam atau ≥ 1 amount mismatch | High | Tinjau keuangan & WAF |
| DET-07 | Lonjakan penerbitan sertifikat | > 50/jam per admin atau di luar jam kerja | High | Verifikasi dengan admin; tahan penerbitan |
| DET-08 | Tanda tangan KMS ≠ sertifikat terbit | selisih ≠ 0 (harian) | **Critical** | Insiden kunci |
| DET-09 | Enumerasi verifikasi sertifikat | > 30 `not_found` berurutan dari satu IP / pola nomor berurutan | Medium | Blokir IP sementara |
| DET-10 | Malware terdeteksi di unggahan | ≥ 1 | Medium | Karantina, tinjau akun |
| DET-11 | Ekspor data massal tidak wajar | > 5 ekspor PII/jam per pengguna atau > 10.000 baris | High | Verifikasi, tangguhkan bila perlu |
| DET-12 | Perubahan peran ke admin / pembuatan API key | setiap kejadian | Info → notifikasi Security Lead | Tinjau |
| DET-13 | Rantai audit putus | ≥ 1 | **Critical** | Insiden |
| DET-14 | Konfigurasi tidak aman / break-glass dipakai | setiap kejadian | High | Tinjau |
| DET-15 | Lonjakan pelanggaran CSP dari halaman tertentu | > 100/jam | Medium | Investigasi XSS |
| DET-16 | Lonjakan error 5xx / latensi | error rate > 2% 5 menit | Medium (ops) | On-call |

| ID | Kebutuhan |
|---|---|
| SEC-LOG-16 | Aturan deteksi di atas diimplementasikan di SIEM/log platform sebelum go-live dan diuji (simulasi) — hasil uji menjadi bagian checklist rilis. |
| SEC-LOG-17 | Routing alert: Critical → on-call (telepon/push) + Security Lead ≤ 15 menit, 24/7; High → on-call jam kerja + kanal keamanan; Medium → tiket harian. Jadwal on-call & eskalasi di [15](15-respons-insiden-dan-kontinuitas.md). |
| SEC-LOG-19 | Aktivitas admin platform (semua aksi `/admin`) dapat ditinjau dalam laporan mingguan otomatis ke Security Lead (ringkasan per admin). |
| SEC-LOG-20 | Akses ke log store/SIEM dibatasi (least privilege, MFA), dan akses itu sendiri tercatat. |
