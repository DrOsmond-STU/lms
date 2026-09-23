/**
 * STU LMS — util UI kecil: ikon, format, badge, toast.
 */
(function (global) {
  "use strict";

  var BULAN = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];

  function formatTanggal(iso) {
    if (!iso) return "-";
    var d = new Date(iso + "T00:00:00");
    return d.getDate() + " " + BULAN[d.getMonth()] + " " + d.getFullYear();
  }

  function formatTanggalSingkat(iso) {
    if (!iso) return "-";
    var d = new Date(iso + "T00:00:00");
    return String(d.getDate()).padStart(2, "0") + "/" + String(d.getMonth() + 1).padStart(2, "0") + "/" + d.getFullYear();
  }

  function inisial(nama) {
    if (!nama) return "??";
    var parts = nama.replace(/[,.].*$/, "").trim().split(/\s+/);
    var s = parts[0][0] + (parts.length > 1 ? parts[parts.length - 1][0] : "");
    return s.toUpperCase();
  }

  // Ikon garis tipis (turunan gaya heroicons-outline), dipakai sebagai string SVG inline
  var Icon = {
    dashboard: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="3.5" width="7" height="7" rx="1.4"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.4"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.4"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.4"/></svg>',
    cert: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8.5" r="5"/><path d="M8.5 12.8 7 21l5-2.5 5 2.5-1.5-8.2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    book: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5v-15Z" stroke-linejoin="round"/><path d="M4 20.5A2.5 2.5 0 0 1 6.5 18H20" stroke-linejoin="round"/></svg>',
    layers: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m12 3 8.5 4.5L12 12 3.5 7.5 12 3Z" stroke-linejoin="round"/><path d="m3.5 12 8.5 4.5 8.5-4.5" stroke-linejoin="round"/><path d="m3.5 16.5 8.5 4.5 8.5-4.5" stroke-linejoin="round"/></svg>',
    user: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c1.4-3.6 4.3-5.5 7.5-5.5s6.1 1.9 7.5 5.5" stroke-linecap="round"/></svg>',
    users: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3.2"/><path d="M2.8 19.5c1.2-3.2 3.6-4.8 6.2-4.8s5 1.6 6.2 4.8" stroke-linecap="round"/><circle cx="17" cy="8.5" r="2.6"/><path d="M15.7 14.9c2.2.4 3.8 1.9 4.8 4.6" stroke-linecap="round"/></svg>',
    check: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4.5 12.5 5 5 10-11" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    chart: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20V10M11 20V4M18 20v-6" stroke-linecap="round"/></svg>',
    building: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="3" width="11" height="18" rx="1"/><path d="M15 8h5v13h-5M7.5 7h1M7.5 10.5h1M7.5 14h1M11.5 7h1M11.5 10.5h1M11.5 14h1"/></svg>',
    doc: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h8l5 5v13H6Z" stroke-linejoin="round"/><path d="M14 3v5h5"/></svg>',
    shield: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3.5 4.5 6v6c0 5 3.2 7.7 7.5 8.9 4.3-1.2 7.5-3.9 7.5-8.9V6L12 3.5Z" stroke-linejoin="round"/><path d="m8.7 12 2.3 2.3 4.3-4.3" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    logout: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 4H6a1.5 1.5 0 0 0-1.5 1.5v13A1.5 1.5 0 0 0 6 20h3" stroke-linecap="round" stroke-linejoin="round"/><path d="M13.5 16.5 18 12l-4.5-4.5M18 12H9" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    bell: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-5 h-5"><path d="M6 9a6 6 0 1 1 12 0c0 4 1.2 5.4 1.7 6H4.3C4.8 14.4 6 13 6 9Z" stroke-linejoin="round"/><path d="M10 19.5a2 2 0 0 0 4 0" stroke-linecap="round"/></svg>',
    search: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-4 h-4"><circle cx="10.5" cy="10.5" r="6"/><path d="m20 20-4.3-4.3" stroke-linecap="round"/></svg>',
    chevronDown: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    download: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-4 h-4"><path d="M12 3v13m0 0-4.5-4.5M12 16l4.5-4.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M4.5 19.5h15" stroke-linecap="round"/></svg>',
    plus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>',
    trash: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-4 h-4"><path d="M5 7h14M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m3 0-.8 12.2A2 2 0 0 1 15.2 21H8.8a2 2 0 0 1-2-1.8L6 7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    edit: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-4 h-4"><path d="M4 20h4L18.5 9.5a2 2 0 0 0-4-4L4 16v4Z" stroke-linejoin="round"/></svg>',
    qr: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-4 h-4"><rect x="3.5" y="3.5" width="6" height="6" rx=".5"/><rect x="14.5" y="3.5" width="6" height="6" rx=".5"/><rect x="3.5" y="14.5" width="6" height="6" rx=".5"/><path d="M14.5 14.5h3v3h-3zM20.5 14.5v.01M17.5 20.5h3"/></svg>',
    arrowRight: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M4.5 12h15m0 0-6-6m6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    menu: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><path d="M4 6.5h16M4 12h16M4 17.5h16" stroke-linecap="round"/></svg>',
    x: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="m6 6 12 12M18 6 6 18" stroke-linecap="round"/></svg>',
    play: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-4 h-4"><circle cx="12" cy="12" r="9"/><path d="M10 8.5v7l6-3.5-6-3.5Z" fill="currentColor" stroke="none"/></svg>',
    clock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-4 h-4"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2" stroke-linecap="round"/></svg>',
    clipboard: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="4.5" width="14" height="17" rx="1.5"/><rect x="8.5" y="3" width="7" height="3" rx="1"/><path d="M8.5 12h7M8.5 16h7" stroke-linecap="round"/></svg>',
    fingerprint: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 4a8 8 0 0 1 8 8v3" stroke-linecap="round"/><path d="M4 15v-3a8 8 0 0 1 4-6.9" stroke-linecap="round"/><path d="M8 21c-1-1.5-1.5-3-1.5-6v-3a5.5 5.5 0 0 1 11 0v4" stroke-linecap="round"/><path d="M12 21c-1.2-1.5-1.8-3.2-1.8-6v-3a1.8 1.8 0 0 1 3.6 0v3c0 1.4.3 2.4.7 3.2" stroke-linecap="round"/></svg>',
    video: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="13" height="12" rx="1.5"/><path d="m16 10.5 5-3v9l-5-3Z" stroke-linejoin="round"/></svg>',
    trophy: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 4h10v5a5 5 0 0 1-10 0V4Z" stroke-linejoin="round"/><path d="M7 5.5H4a3 3 0 0 0 3 5M17 5.5h3a3 3 0 0 1-3 5" stroke-linecap="round"/><path d="M12 14v3M9 21h6M10 17h4v4h-4z" stroke-linecap="round"/></svg>',
    creditCard: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5.5" width="18" height="13" rx="1.6"/><path d="M3 10h18M6.5 15h4" stroke-linecap="round"/></svg>',
    briefcase: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="7.5" width="18" height="12" rx="1.5"/><path d="M8.5 7.5V5.8A1.8 1.8 0 0 1 10.3 4h3.4a1.8 1.8 0 0 1 1.8 1.8V7.5M3 12.5h18" stroke-linecap="round"/></svg>',
    palette: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3.5a8.5 8.5 0 1 0 0 17c1 0 1.7-.8 1.7-1.7 0-.5-.2-.9-.5-1.2-.3-.3-.5-.7-.5-1.2 0-.9.7-1.7 1.7-1.7H16a4 4 0 0 0 4-4c0-4.3-3.6-7.2-8-7.2Z" stroke-linejoin="round"/><circle cx="7.5" cy="11" r="1"/><circle cx="9.5" cy="7.5" r="1"/><circle cx="14.5" cy="7.5" r="1"/></svg>',
    history: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 12a8 8 0 1 0 2.4-5.7" stroke-linecap="round"/><path d="M4 4v4h4M12 8v4l3 2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    lock: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="10.5" width="14" height="10" rx="1.6"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3" stroke-linecap="round"/><circle cx="12" cy="15" r="1.3"/></svg>',
    code: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m8.5 8-4 4 4 4M15.5 8l4 4-4 4M13.5 5.5l-3 13" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    settings: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 13.5a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.9 2.9l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.9-2.9l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.6-1h-.2a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 4.5 8.5a1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.9-2.9l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.6v-.2a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.9 2.9l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.6 1h.2a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.6 1Z" stroke-linejoin="round"/></svg>',
    mail: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="1.6"/><path d="m3.5 6 8.5 7 8.5-7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    upload: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-4 h-4"><path d="M12 16V3m0 0-4.5 4.5M12 3l4.5 4.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M4.5 16.5v3a1.5 1.5 0 0 0 1.5 1.5h12a1.5 1.5 0 0 0 1.5-1.5v-3" stroke-linecap="round"/></svg>',
    star: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m12 3.5 2.6 5.4 5.9.8-4.3 4.2 1 5.9-5.2-2.8-5.2 2.8 1-5.9-4.3-4.2 5.9-.8L12 3.5Z" stroke-linejoin="round"/></svg>',
    calendar: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="5" width="17" height="16" rx="1.6"/><path d="M8 3v4M16 3v4M3.5 10h17" stroke-linecap="round"/></svg>',
    chat: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5.5h16v11H9l-4 3.5v-3.5H4v-11Z" stroke-linejoin="round"/></svg>',
    bell2: '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 9a6 6 0 1 1 12 0c0 4 1.2 5.4 1.7 6H4.3C4.8 14.4 6 13 6 9Z" stroke-linejoin="round"/><path d="M10 19.5a2 2 0 0 0 4 0" stroke-linecap="round"/></svg>'
  };

  var STATUS_PENDAFTARAN = {
    terdaftar: { label: "Terdaftar", cls: "badge-abu" },
    berjalan: { label: "Sedang Berjalan", cls: "badge-biru" },
    menunggu_approval: { label: "Menunggu Approval", cls: "badge-kuning" },
    lulus: { label: "Lulus", cls: "badge-hijau" },
    tidak_lulus: { label: "Belum Lulus", cls: "badge-merah" }
  };

  var STATUS_SERTIFIKAT = {
    aktif: { label: "Berlaku", cls: "badge-hijau" },
    kedaluwarsa: { label: "Kedaluwarsa", cls: "badge-kuning" },
    dicabut: { label: "Dicabut", cls: "badge-merah" }
  };

  var STATUS_TUGAS = {
    submitted: { label: "Terkumpul", cls: "badge-biru" },
    reviewed: { label: "Ditinjau", cls: "badge-ungu" },
    revision: { label: "Perlu Revisi", cls: "badge-kuning" },
    approved: { label: "Disetujui", cls: "badge-hijau" }
  };

  var STATUS_PRESENSI = {
    hadir: { label: "Hadir", cls: "badge-hijau" },
    terlambat: { label: "Terlambat", cls: "badge-kuning" },
    tidak_hadir: { label: "Tidak Hadir", cls: "badge-merah" }
  };

  var STATUS_TRANSAKSI = {
    lunas: { label: "Lunas", cls: "badge-hijau" },
    menunggu: { label: "Menunggu Pembayaran", cls: "badge-kuning" },
    gagal: { label: "Gagal", cls: "badge-merah" },
    refund: { label: "Refund", cls: "badge-abu" }
  };

  var STATUS_PELATIHAN = {
    draft: { label: "Draft", cls: "badge-abu" },
    review: { label: "Menunggu Review", cls: "badge-kuning" },
    published: { label: "Terpublikasi", cls: "badge-hijau" },
    archived: { label: "Diarsipkan", cls: "badge-abu" }
  };

  var STATUS_PRIVASI = {
    menunggu: { label: "Menunggu Diproses", cls: "badge-kuning" },
    selesai: { label: "Selesai", cls: "badge-hijau" }
  };

  var STATUS_LIVE = {
    akan_datang: { label: "Akan Datang", cls: "badge-biru" },
    selesai: { label: "Selesai", cls: "badge-abu" }
  };

  function badgeHtml(map, key) {
    var m = map[key] || { label: key, cls: "badge-abu" };
    return '<span class="badge ' + m.cls + '"><span class="badge-dot"></span>' + m.label + "</span>";
  }

  function kategoriTagHtml(kategori) {
    if (kategori === "internasional") {
      return '<span class="tag-internasional text-[11px] font-bold px-2 py-0.5 rounded-full">INTERNASIONAL</span>';
    }
    return '<span class="tag-bnsp text-[11px] font-bold px-2 py-0.5 rounded-full">BNSP</span>';
  }

  function toast(pesan, tipe) {
    var wrap = document.getElementById("stu-toast-wrap");
    if (!wrap) {
      wrap = document.createElement("div");
      wrap.id = "stu-toast-wrap";
      wrap.className = "fixed top-4 right-4 z-[100] flex flex-col gap-2 items-end";
      document.body.appendChild(wrap);
    }
    var colors = {
      sukses: "bg-emerald-600", info: "bg-brand-700", error: "bg-rose-600", peringatan: "bg-amber-500"
    };
    var el = document.createElement("div");
    el.className = "fade-in text-white text-sm font-medium px-4 py-3 rounded-xl shadow-lg " + (colors[tipe] || colors.info);
    el.textContent = pesan;
    wrap.appendChild(el);
    setTimeout(function () {
      el.style.transition = "opacity .3s ease";
      el.style.opacity = "0";
      setTimeout(function () { el.remove(); }, 300);
    }, 2600);
  }

  function qs(name) {
    return new URLSearchParams(window.location.search).get(name);
  }

  global.UI = {
    Icon: Icon,
    formatTanggal: formatTanggal,
    formatTanggalSingkat: formatTanggalSingkat,
    inisial: inisial,
    badgePendaftaran: function (key) { return badgeHtml(STATUS_PENDAFTARAN, key); },
    badgeSertifikat: function (key) { return badgeHtml(STATUS_SERTIFIKAT, key); },
    badgeTugas: function (key) { return badgeHtml(STATUS_TUGAS, key); },
    badgePresensi: function (key) { return badgeHtml(STATUS_PRESENSI, key); },
    badgeTransaksi: function (key) { return badgeHtml(STATUS_TRANSAKSI, key); },
    badgePelatihan: function (key) { return badgeHtml(STATUS_PELATIHAN, key); },
    badgePrivasi: function (key) { return badgeHtml(STATUS_PRIVASI, key); },
    badgeLive: function (key) { return badgeHtml(STATUS_LIVE, key); },
    labelStatusPendaftaran: function (key) { return (STATUS_PENDAFTARAN[key] || {}).label || key; },
    kategoriTag: kategoriTagHtml,
    formatRupiah: function (n) { return n ? "Rp" + Number(n).toLocaleString("id-ID") : "Gratis"; },
    toast: toast,
    qs: qs
  };
})(window);
