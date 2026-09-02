/**
 * STU LMS — Store (purwarupa)
 * Lapisan "database palsu" di atas data dummy (data.js), memakai localStorage
 * supaya aksi demo (daftar sertifikasi, isi kuis, approval admin, CRUD master
 * data) terasa hidup selama sesi browser. Tidak ada server/backend nyata.
 */
(function (global) {
  "use strict";

  var KEY = "stu_lms_v1";

  var KODE_SINGKAT = {
    "aws-cp": "AWS-CCP", "az-900": "AZ-900", "ccna": "CCNA", "capm": "CAPM",
    "jwd-bnsp": "JWD", "dm-bnsp": "DM", "k3-bnsp": "K3U", "jna-bnsp": "JNA"
  };

  function bacaOverlay() {
    try {
      var raw = localStorage.getItem(KEY);
      if (!raw) return kosong();
      var parsed = JSON.parse(raw);
      return Object.assign(kosong(), parsed);
    } catch (e) {
      return kosong();
    }
  }

  function kosong() {
    return {
      session: null,
      pendaftaranBaru: [],
      pendaftaranPatch: {},
      sertifikatBaru: [],
      universitasBaru: [],
      skemaBaru: [],
      kelasBaru: [],
      instrukturBaru: [],
      mahasiswaBaru: [],
      modulTambahan: {},
      deletedIds: [],
      // Modul tambahan (spesifikasi fitur lengkap)
      tugasBaru: [],
      pengumpulanTugasBaru: [],
      pengumpulanTugasPatch: {},
      presensiBaru: [],
      presensiPatch: {},
      liveClassBaru: [],
      notifikasiPesertaBaru: [],
      notifikasiPesertaPatch: {},
      kuponBaru: [],
      transaksiBaru: [],
      transaksiPatch: {},
      templateSertifikatBaru: [],
      templateSertifikatPatch: {},
      auditLogBaru: [],
      permintaanPrivasiBaru: [],
      permintaanPrivasiPatch: {},
      apiKeysBaru: [],
      apiKeysPatch: {},
      integrasiPatch: {},
      cmsKontenOverride: {},
      diskusiThreadBaru: [],
      diskusiThreadPatch: {},
      diskusiKomentarBaru: [],
      diskusiKomentarPatch: {},
      sesiPresensiBaru: []
    };
  }

  function simpanOverlay(ov) {
    localStorage.setItem(KEY, JSON.stringify(ov));
  }

  function genId(prefix) {
    return prefix + "-" + Date.now().toString(36) + Math.floor(Math.random() * 900 + 100);
  }

  function todayISO() {
    var d = new Date();
    return d.toISOString().slice(0, 10);
  }

  function tambahTahun(isoDate, n) {
    var d = new Date(isoDate);
    d.setFullYear(d.getFullYear() + n);
    return d.toISOString().slice(0, 10);
  }

  var Store = {
    KODE_SINGKAT: KODE_SINGKAT,

    // ---------------- Sesi / autentikasi (simulasi) ----------------
    getSession: function () {
      return bacaOverlay().session;
    },
    login: function (role) {
      var akun = LMS_DATA.akunDemo[role];
      if (!akun) return null;
      var ov = bacaOverlay();
      ov.session = { userId: akun.userId, role: akun.role, waktu: Date.now() };
      simpanOverlay(ov);
      var namaAktor = role === "admin" ? "Dewi Anggraini" : role === "trainer" ? (this.instrukturById(akun.userId) || {}).nama : (this.mahasiswaById(akun.userId) || {}).nama;
      this.catatAudit(namaAktor || "Pengguna", role === "admin" ? "Admin" : role === "trainer" ? "Trainer" : "Peserta", "login", "Sesi " + role, "Login berhasil (purwarupa demo).");
      return ov.session;
    },
    logout: function () {
      var ov = bacaOverlay();
      ov.session = null;
      simpanOverlay(ov);
    },

    // ---------------- Getter data gabungan (seed + overlay) ----------------
    _hidup: function (list) {
      var del = bacaOverlay().deletedIds;
      return list.filter(function (x) { return del.indexOf(x.id) === -1; });
    },
    getUniversitas: function () {
      return this._hidup(LMS_DATA.organisasi.concat(bacaOverlay().universitasBaru));
    },
    getSkema: function () {
      return this._hidup(LMS_DATA.pelatihan.concat(bacaOverlay().skemaBaru));
    },
    getKelas: function () {
      var ov = bacaOverlay();
      var gabung = LMS_DATA.kelas.concat(ov.kelasBaru).map(function (k) {
        var tambahan = ov.modulTambahan[k.id];
        return tambahan && tambahan.length ? Object.assign({}, k, { modul: k.modul.concat(tambahan) }) : k;
      });
      return this._hidup(gabung);
    },
    getInstruktur: function () {
      return this._hidup(LMS_DATA.trainer.concat(bacaOverlay().instrukturBaru));
    },
    getMahasiswa: function () {
      return this._hidup(LMS_DATA.peserta.concat(bacaOverlay().mahasiswaBaru));
    },
    getPendaftaran: function () {
      var ov = bacaOverlay();
      var gabung = LMS_DATA.pendaftaran.concat(ov.pendaftaranBaru).map(function (p) {
        var patch = ov.pendaftaranPatch[p.id];
        return patch ? Object.assign({}, p, patch) : p;
      });
      return this._hidup(gabung);
    },
    getSertifikat: function () {
      return this._hidup(LMS_DATA.sertifikat.concat(bacaOverlay().sertifikatBaru));
    },

    // ---------------- Lookup helper ----------------
    skemaById: function (id) { return this.getSkema().filter(function (s) { return s.id === id; })[0] || null; },
    kelasBySkemaId: function (id) { return this.getKelas().filter(function (k) { return k.skemaId === id; })[0] || null; },
    kelasById: function (id) { return this.getKelas().filter(function (k) { return k.id === id; })[0] || null; },
    universitasById: function (id) { return this.getUniversitas().filter(function (u) { return u.id === id; })[0] || null; },
    mahasiswaById: function (id) { return this.getMahasiswa().filter(function (m) { return m.id === id; })[0] || null; },
    instrukturById: function (id) { return this.getInstruktur().filter(function (i) { return i.id === id; })[0] || null; },

    pendaftaranMahasiswa: function (mahasiswaId) {
      return this.getPendaftaran().filter(function (p) { return p.mahasiswaId === mahasiswaId; });
    },
    pendaftaranSkema: function (mahasiswaId, skemaId) {
      return this.getPendaftaran().filter(function (p) { return p.mahasiswaId === mahasiswaId && p.skemaId === skemaId; })[0] || null;
    },
    sertifikatMahasiswa: function (mahasiswaId) {
      return this.getSertifikat().filter(function (c) { return c.mahasiswaId === mahasiswaId; });
    },
    pendaftaranByKelas: function (skemaId) {
      return this.getPendaftaran().filter(function (p) { return p.skemaId === skemaId; });
    },

    // ---------------- Aksi peserta ----------------
    daftarSertifikasi: function (mahasiswaId, skemaId) {
      var existing = this.pendaftaranSkema(mahasiswaId, skemaId);
      if (existing) return existing;
      var ov = bacaOverlay();
      var baru = {
        id: genId("pd"), mahasiswaId: mahasiswaId, skemaId: skemaId, status: "terdaftar",
        progres: 0, skorKuis: null, tanggalDaftar: todayISO(), tanggalSelesai: null, modulSelesai: []
      };
      ov.pendaftaranBaru.push(baru);
      simpanOverlay(ov);
      return baru;
    },

    tandaiModulSelesai: function (pendaftaranId, modulId, totalModulBukanKuis) {
      var ov = bacaOverlay();
      var current = this.getPendaftaran().filter(function (p) { return p.id === pendaftaranId; })[0];
      if (!current) return null;
      var modulSelesai = current.modulSelesai.indexOf(modulId) === -1
        ? current.modulSelesai.concat([modulId])
        : current.modulSelesai;
      var progres = Math.min(100, Math.round((modulSelesai.length / (totalModulBukanKuis + 1)) * 100));
      var patch = { modulSelesai: modulSelesai, progres: progres, status: current.status === "terdaftar" ? "berjalan" : current.status };
      ov.pendaftaranPatch[pendaftaranId] = Object.assign({}, ov.pendaftaranPatch[pendaftaranId], patch);
      simpanOverlay(ov);
      return Object.assign({}, current, patch);
    },

    submitKuis: function (pendaftaranId, skor, skorMinimal) {
      var ov = bacaOverlay();
      var current = this.getPendaftaran().filter(function (p) { return p.id === pendaftaranId; })[0];
      if (!current) return null;
      var lulusKuis = skor >= skorMinimal;
      var patch = {
        skorKuis: skor,
        progres: 100,
        status: lulusKuis ? "menunggu_approval" : "tidak_lulus",
        tanggalSelesai: todayISO()
      };
      ov.pendaftaranPatch[pendaftaranId] = Object.assign({}, ov.pendaftaranPatch[pendaftaranId], patch);
      simpanOverlay(ov);
      return Object.assign({}, current, patch);
    },

    ulangiKuis: function (pendaftaranId) {
      var ov = bacaOverlay();
      var patch = { status: "berjalan", skorKuis: null };
      ov.pendaftaranPatch[pendaftaranId] = Object.assign({}, ov.pendaftaranPatch[pendaftaranId], patch);
      simpanOverlay(ov);
    },

    // ---------------- Aksi admin: approval & penerbitan sertifikat ----------------
    setujuiSertifikat: function (pendaftaranId) {
      var ov = bacaOverlay();
      var p = this.getPendaftaran().filter(function (x) { return x.id === pendaftaranId; })[0];
      if (!p) return null;
      var mhs = this.mahasiswaById(p.mahasiswaId);
      var pelatihan = this.skemaById(p.skemaId);
      var univ = mhs ? this.universitasById(mhs.universitasId) : null;
      var kategoriCode = pelatihan.kategori === "internasional" ? "INTL" : "BNSP";
      var kodeShort = KODE_SINGKAT[pelatihan.id] || pelatihan.kodeSkema;
      var urut = this.getSertifikat().filter(function (c) { return c.skemaId === pelatihan.id; }).length + 1;
      var nomor = kategoriCode + "/" + kodeShort + "/" + (univ ? univ.singkatan : "STU") + "/" + LMS_DATA.tahunSekarang + "/" + String(urut).padStart(5, "0");
      var tglTerbit = todayISO();
      var cert = {
        id: genId("cert"), nomor: nomor, mahasiswaId: p.mahasiswaId, skemaId: p.skemaId,
        tanggalTerbit: tglTerbit, berlakuHingga: tambahTahun(tglTerbit, 3), status: "aktif"
      };
      ov.sertifikatBaru.push(cert);
      ov.pendaftaranPatch[pendaftaranId] = Object.assign({}, ov.pendaftaranPatch[pendaftaranId], { status: "lulus" });
      simpanOverlay(ov);
      this.catatAudit("Dewi Anggraini", "Admin", "sertifikat_terbit", nomor, "Menyetujui kelulusan " + (mhs ? mhs.nama : p.mahasiswaId) + " (" + (pelatihan ? pelatihan.nama : p.skemaId) + ").");
      return cert;
    },

    tolakKelulusan: function (pendaftaranId, catatan) {
      var ov = bacaOverlay();
      ov.pendaftaranPatch[pendaftaranId] = Object.assign({}, ov.pendaftaranPatch[pendaftaranId], { status: "tidak_lulus", catatanPenolakan: catatan || "" });
      simpanOverlay(ov);
    },

    cabutSertifikat: function (sertifikatId, catatan) {
      var ov = bacaOverlay();
      var idx = ov.sertifikatBaru.findIndex(function (c) { return c.id === sertifikatId; });
      if (idx > -1) {
        ov.sertifikatBaru[idx].status = "dicabut";
        ov.sertifikatBaru[idx].catatanPencabutan = catatan || "";
      } else {
        // sertifikat dari seed data.js: simpan sebagai patch lewat deletedIds+clone
        var seedCert = LMS_DATA.sertifikat.filter(function (c) { return c.id === sertifikatId; })[0];
        if (seedCert) {
          ov.deletedIds.push(sertifikatId);
          ov.sertifikatBaru.push(Object.assign({}, seedCert, { status: "dicabut", catatanPencabutan: catatan || "" }));
        }
      }
      simpanOverlay(ov);
      this.catatAudit("Dewi Anggraini", "Admin", "sertifikat_dicabut", sertifikatId, catatan || "Sertifikat dicabut.");
    },

    // ---------------- CRUD master data (admin) ----------------
    tambahUniversitas: function (obj) {
      var ov = bacaOverlay();
      var baru = Object.assign({ id: genId("univ") }, obj);
      ov.universitasBaru.push(baru);
      simpanOverlay(ov);
      return baru;
    },
    tambahSkema: function (obj) {
      var ov = bacaOverlay();
      var baru = Object.assign({ id: genId("skm"), biaya: 0 }, obj);
      ov.skemaBaru.push(baru);
      simpanOverlay(ov);
      return baru;
    },
    tambahKelas: function (obj) {
      var ov = bacaOverlay();
      var baru = Object.assign({ id: genId("kls"), modul: [], kuis: [] }, obj);
      ov.kelasBaru.push(baru);
      simpanOverlay(ov);
      return baru;
    },
    tambahModulKelas: function (kelasId, modulObj) {
      var ov = bacaOverlay();
      var baru = Object.assign({ id: genId("md") }, modulObj);
      if (!ov.modulTambahan[kelasId]) ov.modulTambahan[kelasId] = [];
      ov.modulTambahan[kelasId].push(baru);
      simpanOverlay(ov);
      return baru;
    },
    tambahInstruktur: function (obj) {
      var ov = bacaOverlay();
      var baru = Object.assign({ id: genId("ins"), mengampu: [] }, obj);
      ov.instrukturBaru.push(baru);
      simpanOverlay(ov);
      return baru;
    },
    tambahMahasiswa: function (obj) {
      var ov = bacaOverlay();
      var baru = Object.assign({ id: genId("mhs") }, obj);
      ov.mahasiswaBaru.push(baru);
      simpanOverlay(ov);
      return baru;
    },
    hapus: function (id) {
      var ov = bacaOverlay();
      if (ov.deletedIds.indexOf(id) === -1) ov.deletedIds.push(id);
      simpanOverlay(ov);
    },

    resetDemo: function () {
      localStorage.removeItem(KEY);
    },

    // =================================================================
    // Modul tambahan — spesifikasi fitur lengkap (LMS_Feature_Specification)
    // =================================================================

    catatAudit: function (aktor, peran, aksi, objek, detail) {
      var ov = bacaOverlay();
      var d = new Date();
      var waktu = d.toISOString().slice(0, 10) + " " + String(d.getHours()).padStart(2, "0") + ":" + String(d.getMinutes()).padStart(2, "0");
      ov.auditLogBaru.unshift({ id: genId("log"), waktu: waktu, aktor: aktor, peran: peran, aksi: aksi, objek: objek, detail: detail });
      simpanOverlay(ov);
    },
    getAuditLog: function () {
      return bacaOverlay().auditLogBaru.concat(LMS_DATA.auditLog);
    },

    // ---------------- Assignment (Tugas) ----------------
    getTugas: function () { return this._hidup(LMS_DATA.tugas.concat(bacaOverlay().tugasBaru)); },
    tugasByKelas: function (kelasId) { return this.getTugas().filter(function (t) { return t.kelasId === kelasId; }); },
    tambahTugas: function (kelasId, judul, deskripsi, deadline) {
      var ov = bacaOverlay();
      var baru = { id: genId("tugas"), kelasId: kelasId, judul: judul, deskripsi: deskripsi, deadline: deadline, lampiranContoh: null };
      ov.tugasBaru.push(baru);
      simpanOverlay(ov);
      return baru;
    },
    getPengumpulanTugas: function () {
      var ov = bacaOverlay();
      var gabung = LMS_DATA.pengumpulanTugas.concat(ov.pengumpulanTugasBaru).map(function (s) {
        var patch = ov.pengumpulanTugasPatch[s.id];
        return patch ? Object.assign({}, s, patch) : s;
      });
      return this._hidup(gabung);
    },
    pengumpulanByTugas: function (tugasId) { return this.getPengumpulanTugas().filter(function (s) { return s.tugasId === tugasId; }); },
    pengumpulanPeserta: function (tugasId, mahasiswaId) { return this.getPengumpulanTugas().filter(function (s) { return s.tugasId === tugasId && s.mahasiswaId === mahasiswaId; })[0] || null; },
    kumpulkanTugas: function (tugasId, mahasiswaId, fileNama) {
      var existing = this.pengumpulanPeserta(tugasId, mahasiswaId);
      var ov = bacaOverlay();
      if (existing) {
        ov.pengumpulanTugasPatch[existing.id] = Object.assign({}, ov.pengumpulanTugasPatch[existing.id], {
          fileNama: fileNama, tanggalKumpul: todayISO(), status: "submitted", nilai: null, feedback: null
        });
        simpanOverlay(ov);
        return Object.assign({}, existing, ov.pengumpulanTugasPatch[existing.id]);
      }
      var baru = { id: genId("sub"), tugasId: tugasId, mahasiswaId: mahasiswaId, fileNama: fileNama, tanggalKumpul: todayISO(), status: "submitted", nilai: null, feedback: null, riwayatRevisi: [] };
      ov.pengumpulanTugasBaru.push(baru);
      simpanOverlay(ov);
      return baru;
    },
    nilaiTugas: function (submisiId, nilai, feedback, status) {
      var ov = bacaOverlay();
      ov.pengumpulanTugasPatch[submisiId] = Object.assign({}, ov.pengumpulanTugasPatch[submisiId], { nilai: nilai, feedback: feedback, status: status || "approved" });
      simpanOverlay(ov);
    },

    // ---------------- Attendance (Presensi) ----------------
    getSesiPresensi: function () { return this._hidup(LMS_DATA.sesiPresensi.concat(bacaOverlay().sesiPresensiBaru)); },
    tambahSesiPresensi: function (kelasId, judul, tanggal, tipe) {
      var ov = bacaOverlay();
      var baru = { id: genId("sesi"), kelasId: kelasId, judul: judul, tanggal: tanggal, tipe: tipe };
      ov.sesiPresensiBaru.push(baru);
      simpanOverlay(ov);
      return baru;
    },
    sesiByKelas: function (kelasId) { return this.getSesiPresensi().filter(function (s) { return s.kelasId === kelasId; }); },
    getPresensi: function () {
      var ov = bacaOverlay();
      var gabung = LMS_DATA.presensi.concat(ov.presensiBaru).map(function (p) {
        var patch = ov.presensiPatch[p.id];
        return patch ? Object.assign({}, p, patch) : p;
      });
      return this._hidup(gabung);
    },
    presensiBySesi: function (sesiId) { return this.getPresensi().filter(function (p) { return p.sesiId === sesiId; }); },
    presensiPeserta: function (sesiId, mahasiswaId) { return this.getPresensi().filter(function (p) { return p.sesiId === sesiId && p.mahasiswaId === mahasiswaId; })[0] || null; },
    rekapKehadiran: function (kelasId, mahasiswaId) {
      var sesi = this.sesiByKelas(kelasId);
      var hadir = 0;
      sesi.forEach(function (s) {
        var rec = this.presensiPeserta(s.id, mahasiswaId);
        if (rec && (rec.status === "hadir" || rec.status === "terlambat")) hadir++;
      }, this);
      return { totalSesi: sesi.length, hadir: hadir, persen: sesi.length ? Math.round((hadir / sesi.length) * 100) : 0 };
    },
    checkInPresensi: function (sesiId, mahasiswaId, metode) {
      var existing = this.presensiPeserta(sesiId, mahasiswaId);
      var d = new Date();
      var jam = String(d.getHours()).padStart(2, "0") + ":" + String(d.getMinutes()).padStart(2, "0");
      var ov = bacaOverlay();
      if (existing) {
        ov.presensiPatch[existing.id] = { status: "hadir", waktuCheckIn: jam, metode: metode || "qr" };
        simpanOverlay(ov);
        return Object.assign({}, existing, ov.presensiPatch[existing.id]);
      }
      var baru = { id: genId("hdr"), sesiId: sesiId, mahasiswaId: mahasiswaId, status: "hadir", waktuCheckIn: jam, metode: metode || "qr" };
      ov.presensiBaru.push(baru);
      simpanOverlay(ov);
      return baru;
    },
    catatPresensiManual: function (sesiId, mahasiswaId, status) {
      var existing = this.presensiPeserta(sesiId, mahasiswaId);
      var ov = bacaOverlay();
      if (existing) {
        ov.presensiPatch[existing.id] = { status: status, waktuCheckIn: existing.waktuCheckIn || "-", metode: "manual" };
        simpanOverlay(ov);
        return;
      }
      ov.presensiBaru.push({ id: genId("hdr"), sesiId: sesiId, mahasiswaId: mahasiswaId, status: status, waktuCheckIn: status === "hadir" ? "-" : null, metode: "manual" });
      simpanOverlay(ov);
    },

    // ---------------- Live Class ----------------
    getLiveClass: function () { return this._hidup(LMS_DATA.liveClass.concat(bacaOverlay().liveClassBaru)); },
    liveClassByKelas: function (kelasId) { return this.getLiveClass().filter(function (l) { return l.kelasId === kelasId; }); },
    tambahLiveClass: function (obj) {
      var ov = bacaOverlay();
      var baru = Object.assign({ id: genId("live"), status: "akan_datang", rekaman: null }, obj);
      ov.liveClassBaru.push(baru);
      simpanOverlay(ov);
      return baru;
    },

    // ---------------- Gamification ----------------
    getLencana: function () { return LMS_DATA.lencana; },
    lencanaById: function (id) { return LMS_DATA.lencana.filter(function (b) { return b.id === id; })[0] || null; },
    pencapaianPeserta: function (mahasiswaId) {
      return LMS_DATA.pencapaianPeserta.filter(function (p) { return p.mahasiswaId === mahasiswaId; })[0] || { mahasiswaId: mahasiswaId, poin: 0, lencanaIds: [] };
    },
    getLeaderboard: function (limit) {
      var self = this;
      var list = LMS_DATA.pencapaianPeserta.map(function (p) {
        return Object.assign({ peserta: self.mahasiswaById(p.mahasiswaId) }, p);
      }).filter(function (p) { return p.peserta; }).sort(function (a, b) { return b.poin - a.poin; });
      return limit ? list.slice(0, limit) : list;
    },

    // ---------------- Notification Center ----------------
    getNotifikasiPeserta: function (mahasiswaId) {
      var ov = bacaOverlay();
      var gabung = LMS_DATA.notifikasiPeserta.concat(ov.notifikasiPesertaBaru).map(function (n) {
        var patch = ov.notifikasiPesertaPatch[n.id];
        return patch ? Object.assign({}, n, patch) : n;
      });
      return gabung.filter(function (n) { return n.mahasiswaId === mahasiswaId; });
    },
    tandaiNotifBaca: function (notifId) {
      var ov = bacaOverlay();
      ov.notifikasiPesertaPatch[notifId] = { dibaca: true };
      simpanOverlay(ov);
    },
    tandaiSemuaNotifBaca: function (mahasiswaId) {
      var ov = bacaOverlay();
      this.getNotifikasiPeserta(mahasiswaId).forEach(function (n) {
        ov.notifikasiPesertaPatch[n.id] = { dibaca: true };
      });
      simpanOverlay(ov);
    },

    // ---------------- Payment & E-Commerce ----------------
    getKupon: function () { return this._hidup(LMS_DATA.kupon.concat(bacaOverlay().kuponBaru)); },
    validasiKupon: function (kode) {
      var k = this.getKupon().filter(function (x) { return x.kode.toUpperCase() === (kode || "").toUpperCase().trim(); })[0];
      if (!k) return { valid: false, pesan: "Kode kupon tidak ditemukan." };
      if (k.kuotaSisa <= 0) return { valid: false, pesan: "Kuota kupon sudah habis." };
      if (k.berlakuHingga < todayISO()) return { valid: false, pesan: "Kupon sudah kedaluwarsa." };
      return { valid: true, kupon: k };
    },
    getTransaksi: function () {
      var ov = bacaOverlay();
      var gabung = LMS_DATA.transaksi.concat(ov.transaksiBaru).map(function (t) {
        var patch = ov.transaksiPatch[t.id];
        return patch ? Object.assign({}, t, patch) : t;
      });
      return this._hidup(gabung);
    },
    transaksiMahasiswa: function (mahasiswaId) { return this.getTransaksi().filter(function (t) { return t.mahasiswaId === mahasiswaId; }); },
    buatTransaksi: function (mahasiswaId, skemaId, metode, kodeKupon) {
      var pelatihan = this.skemaById(skemaId);
      var jumlah = pelatihan ? pelatihan.harga : 0;
      var kuponInfo = null;
      if (kodeKupon) {
        var cek = this.validasiKupon(kodeKupon);
        if (cek.valid) {
          kuponInfo = cek.kupon.kode;
          jumlah = cek.kupon.diskonPersen ? Math.round(jumlah * (1 - cek.kupon.diskonPersen / 100)) : Math.max(0, jumlah - cek.kupon.diskonNominal);
        }
      }
      var ov = bacaOverlay();
      var nomorUrut = this.getTransaksi().length + 1;
      var baru = {
        id: genId("tx"), nomorInvoice: "INV/" + LMS_DATA.tahunSekarang + "/" + String(nomorUrut).padStart(4, "0"),
        mahasiswaId: mahasiswaId, skemaId: skemaId, jumlah: jumlah, metode: metode, status: "lunas", tanggal: todayISO(), kuponDipakai: kuponInfo
      };
      ov.transaksiBaru.push(baru);
      simpanOverlay(ov);
      this.catatAudit("Sistem", "System", "pembayaran_lunas", baru.nomorInvoice, "Pembayaran " + (pelatihan ? pelatihan.nama : skemaId) + " melalui " + metode + ".");
      return baru;
    },
    updateStatusTransaksi: function (transaksiId, status, catatan) {
      var ov = bacaOverlay();
      ov.transaksiPatch[transaksiId] = Object.assign({}, ov.transaksiPatch[transaksiId], { status: status, catatanRefund: catatan || undefined });
      simpanOverlay(ov);
    },
    tambahKupon: function (obj) {
      var ov = bacaOverlay();
      var baru = Object.assign({ id: genId("kupon") }, obj);
      ov.kuponBaru.push(baru);
      simpanOverlay(ov);
      this.catatAudit("Admin", "Admin", "kupon_dibuat", baru.kode, "Membuat kupon baru.");
      return baru;
    },

    // ---------------- Certificate Template Management ----------------
    getTemplateSertifikat: function () {
      var ov = bacaOverlay();
      var gabung = LMS_DATA.templateSertifikat.concat(ov.templateSertifikatBaru).map(function (t) {
        var patch = ov.templateSertifikatPatch[t.id];
        return patch ? Object.assign({}, t, patch) : t;
      });
      return this._hidup(gabung);
    },
    toggleTemplateAktif: function (id) {
      var t = this.getTemplateSertifikat().filter(function (x) { return x.id === id; })[0];
      if (!t) return;
      var ov = bacaOverlay();
      ov.templateSertifikatPatch[id] = { statusAktif: !t.statusAktif };
      simpanOverlay(ov);
    },
    tambahTemplateSertifikat: function (obj) {
      var ov = bacaOverlay();
      var baru = Object.assign({ id: genId("tpl"), statusAktif: true }, obj);
      ov.templateSertifikatBaru.push(baru);
      simpanOverlay(ov);
      return baru;
    },

    // ---------------- Data Privacy ----------------
    getPermintaanPrivasi: function () {
      var ov = bacaOverlay();
      var gabung = LMS_DATA.permintaanPrivasi.concat(ov.permintaanPrivasiBaru).map(function (p) {
        var patch = ov.permintaanPrivasiPatch[p.id];
        return patch ? Object.assign({}, p, patch) : p;
      });
      return this._hidup(gabung);
    },
    ajukanPermintaanPrivasi: function (mahasiswaId, tipe) {
      var ov = bacaOverlay();
      var baru = { id: genId("priv"), mahasiswaId: mahasiswaId, tipe: tipe, tanggal: todayISO(), status: "menunggu" };
      ov.permintaanPrivasiBaru.push(baru);
      simpanOverlay(ov);
      this.catatAudit(this.mahasiswaById(mahasiswaId) ? this.mahasiswaById(mahasiswaId).nama : "Peserta", "Peserta", "permintaan_privasi", tipe, "Mengajukan permintaan " + (tipe === "ekspor_data" ? "ekspor data" : "penghapusan akun") + ".");
      return baru;
    },
    selesaikanPermintaanPrivasi: function (id) {
      var ov = bacaOverlay();
      ov.permintaanPrivasiPatch[id] = { status: "selesai" };
      simpanOverlay(ov);
    },

    // ---------------- API & Integration ----------------
    getApiKeys: function () {
      var ov = bacaOverlay();
      var gabung = LMS_DATA.apiKeys.concat(ov.apiKeysBaru).map(function (k) {
        var patch = ov.apiKeysPatch[k.id];
        return patch ? Object.assign({}, k, patch) : k;
      });
      return this._hidup(gabung);
    },
    toggleApiKey: function (id) {
      var k = this.getApiKeys().filter(function (x) { return x.id === id; })[0];
      if (!k) return;
      var ov = bacaOverlay();
      ov.apiKeysPatch[id] = { statusAktif: !k.statusAktif };
      simpanOverlay(ov);
    },
    tambahApiKey: function (label) {
      var ov = bacaOverlay();
      var acak = Math.random().toString(16).slice(2, 6) + "••••••••" + Math.random().toString(16).slice(2, 6);
      var baru = { id: genId("key"), label: label, keyMasked: "sk_live_" + acak, dibuat: todayISO(), statusAktif: true };
      ov.apiKeysBaru.push(baru);
      simpanOverlay(ov);
      return baru;
    },
    getIntegrasi: function () {
      var ov = bacaOverlay();
      return LMS_DATA.integrasi.map(function (i) {
        var patch = ov.integrasiPatch[i.id];
        return patch ? Object.assign({}, i, patch) : i;
      });
    },
    toggleIntegrasi: function (id) {
      var i = this.getIntegrasi().filter(function (x) { return x.id === id; })[0];
      if (!i) return;
      var ov = bacaOverlay();
      ov.integrasiPatch[id] = { aktif: !i.aktif };
      simpanOverlay(ov);
    },

    // ---------------- CMS Landing Page ----------------
    getCmsKonten: function () {
      var ov = bacaOverlay();
      return Object.assign({}, LMS_DATA.cmsKontenDefault, ov.cmsKontenOverride, {
        faq: (ov.cmsKontenOverride.faq && ov.cmsKontenOverride.faq.length) ? ov.cmsKontenOverride.faq : LMS_DATA.cmsKontenDefault.faq,
        testimoni: (ov.cmsKontenOverride.testimoni && ov.cmsKontenOverride.testimoni.length) ? ov.cmsKontenOverride.testimoni : LMS_DATA.cmsKontenDefault.testimoni
      });
    },
    simpanCmsKonten: function (patch) {
      var ov = bacaOverlay();
      ov.cmsKontenOverride = Object.assign({}, ov.cmsKontenOverride, patch);
      simpanOverlay(ov);
      this.catatAudit("Admin", "Admin", "cms_diubah", "Landing Page", "Memperbarui konten halaman beranda.");
    },
    resetCmsKonten: function () {
      var ov = bacaOverlay();
      ov.cmsKontenOverride = {};
      simpanOverlay(ov);
    },

    // ---------------- Discussion / Community ----------------
    getDiskusiThread: function () {
      var ov = bacaOverlay();
      var gabung = LMS_DATA.diskusiThread.concat(ov.diskusiThreadBaru).map(function (t) {
        var patch = ov.diskusiThreadPatch[t.id];
        return patch ? Object.assign({}, t, patch) : t;
      });
      return this._hidup(gabung);
    },
    diskusiByKelas: function (kelasId) { return this.getDiskusiThread().filter(function (t) { return t.kelasId === kelasId; }); },
    threadById: function (id) { return this.getDiskusiThread().filter(function (t) { return t.id === id; })[0] || null; },
    tambahThread: function (kelasId, penulisId, penulisPeran, judul) {
      var ov = bacaOverlay();
      var baru = { id: genId("thr"), kelasId: kelasId, judul: judul, penulisId: penulisId, penulisPeran: penulisPeran, waktu: "baru saja", dilaporkan: false };
      ov.diskusiThreadBaru.push(baru);
      simpanOverlay(ov);
      return baru;
    },
    getDiskusiKomentar: function () {
      var ov = bacaOverlay();
      var gabung = LMS_DATA.diskusiKomentar.concat(ov.diskusiKomentarBaru).map(function (k) {
        var patch = ov.diskusiKomentarPatch[k.id];
        return patch ? Object.assign({}, k, patch) : k;
      });
      return this._hidup(gabung);
    },
    komentarByThread: function (threadId) { return this.getDiskusiKomentar().filter(function (k) { return k.threadId === threadId; }); },
    tambahKomentar: function (threadId, penulisId, penulisPeran, isi) {
      var ov = bacaOverlay();
      var baru = { id: genId("kom"), threadId: threadId, penulisId: penulisId, penulisPeran: penulisPeran, isi: isi, waktu: "baru saja", suka: 0 };
      ov.diskusiKomentarBaru.push(baru);
      simpanOverlay(ov);
      return baru;
    },
    sukaKomentar: function (komentarId) {
      var k = this.getDiskusiKomentar().filter(function (x) { return x.id === komentarId; })[0];
      if (!k) return;
      var ov = bacaOverlay();
      ov.diskusiKomentarPatch[komentarId] = { suka: (k.suka || 0) + 1 };
      simpanOverlay(ov);
    },
    laporkanKomentar: function (komentarId) {
      var ov = bacaOverlay();
      ov.diskusiKomentarPatch[komentarId] = Object.assign({}, ov.diskusiKomentarPatch[komentarId], { dilaporkan: true });
      simpanOverlay(ov);
    },
    hapusKomentar: function (komentarId) {
      this.hapus(komentarId);
      this.catatAudit("Admin", "Admin", "moderasi_diskusi", komentarId, "Menghapus komentar yang dilaporkan.");
    }
  };

  global.Store = Store;
})(window);
