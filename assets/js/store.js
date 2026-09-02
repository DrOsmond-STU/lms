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
      deletedIds: []
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
      return this._hidup(LMS_DATA.universitas.concat(bacaOverlay().universitasBaru));
    },
    getSkema: function () {
      return this._hidup(LMS_DATA.skema.concat(bacaOverlay().skemaBaru));
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
      return this._hidup(LMS_DATA.instruktur.concat(bacaOverlay().instrukturBaru));
    },
    getMahasiswa: function () {
      return this._hidup(LMS_DATA.mahasiswa.concat(bacaOverlay().mahasiswaBaru));
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

    // ---------------- Aksi mahasiswa ----------------
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
      var skema = this.skemaById(p.skemaId);
      var univ = mhs ? this.universitasById(mhs.universitasId) : null;
      var kategoriCode = skema.kategori === "internasional" ? "INTL" : "BNSP";
      var kodeShort = KODE_SINGKAT[skema.id] || skema.kodeSkema;
      var urut = this.getSertifikat().filter(function (c) { return c.skemaId === skema.id; }).length + 1;
      var nomor = kategoriCode + "/" + kodeShort + "/" + (univ ? univ.singkatan : "STU") + "/" + LMS_DATA.tahunSekarang + "/" + String(urut).padStart(5, "0");
      var tglTerbit = todayISO();
      var cert = {
        id: genId("cert"), nomor: nomor, mahasiswaId: p.mahasiswaId, skemaId: p.skemaId,
        tanggalTerbit: tglTerbit, berlakuHingga: tambahTahun(tglTerbit, 3), status: "aktif"
      };
      ov.sertifikatBaru.push(cert);
      ov.pendaftaranPatch[pendaftaranId] = Object.assign({}, ov.pendaftaranPatch[pendaftaranId], { status: "lulus" });
      simpanOverlay(ov);
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
    }
  };

  global.Store = Store;
})(window);
