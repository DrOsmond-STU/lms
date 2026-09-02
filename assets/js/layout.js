/**
 * STU LMS — pembangun topbar + sidebar (shell) per peran, dan penjaga sesi
 * (auth guard) sederhana untuk purwarupa ini.
 */
(function () {
  "use strict";

  var NAV = {
    peserta: [
      { group: null, items: [
        { href: "peserta/dashboard.html", icon: "dashboard", label: "Dashboard", key: "dashboard" },
        { href: "peserta/sertifikasi.html", icon: "cert", label: "Pilih Pelatihan", key: "sertifikasi" },
        { href: "peserta/pembelajaran.html", icon: "book", label: "Pembelajaran Saya", key: "pembelajaran" },
        { href: "peserta/jadwal.html", icon: "calendar", label: "Jadwal", key: "jadwal" },
        { href: "peserta/sertifikat-saya.html", icon: "shield", label: "Sertifikat Saya", key: "sertifikat" },
        { href: "peserta/pencapaian.html", icon: "trophy", label: "Pencapaian", key: "pencapaian" },
        { href: "peserta/notifikasi.html", icon: "bell2", label: "Notifikasi", key: "notifikasi" },
        { href: "peserta/profil.html", icon: "user", label: "Profil", key: "profil" }
      ]}
    ],
    trainer: [
      { group: null, items: [
        { href: "trainer/dashboard.html", icon: "dashboard", label: "Dashboard", key: "dashboard" },
        { href: "trainer/kelas.html", icon: "layers", label: "Kelas Saya", key: "kelas" },
        { href: "trainer/peserta.html", icon: "users", label: "Peserta & Nilai", key: "peserta" },
        { href: "trainer/diskusi.html", icon: "chat", label: "Diskusi", key: "diskusi" },
        { href: "trainer/laporan.html", icon: "chart", label: "Laporan", key: "laporan" }
      ]}
    ],
    admin: [
      { group: null, items: [
        { href: "admin/dashboard.html", icon: "dashboard", label: "Dashboard", key: "dashboard" },
        { href: "admin/approval-sertifikat.html", icon: "check", label: "Approval Sertifikat", key: "approval" }
      ]},
      { group: "Master Data", items: [
        { href: "admin/master-sertifikasi.html", icon: "cert", label: "Program Pelatihan", key: "master-sertifikasi" },
        { href: "admin/master-kelas.html", icon: "layers", label: "Kelas & Jadwal", key: "master-kelas" },
        { href: "admin/master-organisasi.html", icon: "building", label: "Organisasi", key: "master-organisasi" },
        { href: "admin/master-user.html", icon: "users", label: "Pengguna", key: "master-user" },
        { href: "admin/master-template-sertifikat.html", icon: "palette", label: "Template Sertifikat", key: "master-template" }
      ]},
      { group: "Operasional", items: [
        { href: "admin/enrollment.html", icon: "clipboard", label: "Enrollment", key: "enrollment" },
        { href: "admin/pembayaran.html", icon: "creditCard", label: "Pembayaran", key: "pembayaran" },
        { href: "admin/korporat.html", icon: "briefcase", label: "Corporate Training", key: "korporat" },
        { href: "admin/basis-data-sertifikat.html", icon: "doc", label: "Basis Data Sertifikat", key: "basis-data-sertifikat" }
      ]},
      { group: "Laporan", items: [
        { href: "admin/laporan-user.html", icon: "chart", label: "Pengguna per Organisasi", key: "laporan-user" },
        { href: "admin/laporan-pelatihan.html", icon: "doc", label: "Pelatihan per Organisasi", key: "laporan-pelatihan" },
        { href: "admin/laporan-operasional.html", icon: "history", label: "Laporan Operasional", key: "laporan-operasional" }
      ]},
      { group: "Pengaturan", items: [
        { href: "admin/pengaturan.html", icon: "settings", label: "Pengaturan Sistem", key: "pengaturan" }
      ]}
    ]
  };

  var ROLE_LABEL = { peserta: "Peserta", trainer: "Trainer", admin: "Administrator" };

  function currentUserDisplay(role, userId) {
    if (role === "peserta") {
      var m = Store.mahasiswaById(userId);
      var u = m ? Store.universitasById(m.universitasId) : null;
      return { nama: m ? m.nama : "Peserta", sub: (u ? u.singkatan : "") + (m ? " · " + m.prodi : "") };
    }
    if (role === "trainer") {
      var ins = Store.instrukturById(userId);
      var uni = ins ? Store.universitasById(ins.universitasId) : null;
      return { nama: ins ? ins.nama : "Trainer", sub: uni ? uni.singkatan + " · Trainer" : "Trainer" };
    }
    var adm = LMS_DATA.admin.filter(function (a) { return a.id === userId; })[0];
    return { nama: adm ? adm.nama : "Admin", sub: adm ? adm.jabatan : "Administrator" };
  }

  function buildSidebar(role, activeKey, base) {
    var groups = NAV[role] || [];
    var html = "";
    groups.forEach(function (g) {
      if (g.group) html += '<div class="nav-group-label">' + g.group + "</div>";
      g.items.forEach(function (item) {
        var isActive = item.key === activeKey;
        html += '<a href="' + base + item.href + '" class="nav-link' + (isActive ? " active" : "") + '">' +
          UI.Icon[item.icon] + "<span>" + item.label + "</span></a>";
      });
    });
    return html;
  }

  function buildNotif() {
    var iconByTipe = { sukses: "badge-hijau", info: "badge-biru", peringatan: "badge-kuning" };
    return LMS_DATA.notifikasi.map(function (n) {
      return '<div class="px-4 py-3 border-b border-slate-100 last:border-0 hover:bg-slate-50">' +
        '<div class="flex items-start gap-2"><span class="badge-dot mt-1.5 ' + (iconByTipe[n.tipe] ? "" : "") + '" style="width:.4rem;height:.4rem;border-radius:999px;background:' +
        (n.tipe === "sukses" ? "#22c55e" : n.tipe === "peringatan" ? "#f59e0b" : "#3b82f6") + '"></span>' +
        '<div><p class="text-sm text-slate-700 leading-snug">' + n.judul + '</p><p class="text-xs text-slate-400 mt-0.5">' + n.waktu + "</p></div></div></div>";
    }).join("");
  }

  function renderShell(shell) {
    var role = shell.getAttribute("data-role");
    var activeKey = shell.getAttribute("data-active") || "";
    var base = shell.getAttribute("data-base") || "./";
    var session = Store.getSession();

    if (role && (!session || session.role !== role)) {
      window.location.href = base + "login.html";
      return;
    }

    var user = session ? currentUserDisplay(role, session.userId) : null;

    var sidebarHtml =
      '<aside id="stu-sidebar" class="fixed inset-y-0 left-0 z-50 w-64 flex-col text-white hidden lg:flex" style="background:linear-gradient(180deg,var(--brand-900),var(--brand-800) 60%,#0a2440)">' +
        '<div class="h-16 flex items-center gap-2.5 px-5 border-b border-white/10">' +
          '<div class="w-9 h-9 rounded-lg bg-accent-500 text-brand-900 font-extrabold flex items-center justify-center text-sm" style="background:var(--accent-500)">STU</div>' +
          '<div class="leading-tight"><p class="font-extrabold text-sm tracking-wide">STU LMS</p><p class="text-[11px] text-white/50">Sertifikasi Peserta</p></div>' +
        "</div>" +
        '<nav class="flex-1 overflow-y-auto px-3 py-4 space-y-0.5">' + buildSidebar(role, activeKey, base) + "</nav>" +
        '<div class="px-3 py-4 border-t border-white/10">' +
          '<a href="' + base + 'cek-sertifikat.html" class="nav-link">' + UI.Icon.qr + "<span>Cek Sertifikat</span></a>" +
          '<button id="stu-logout" class="nav-link w-full text-left">' + UI.Icon.logout + "<span>Keluar</span></button>" +
        "</div>" +
      "</aside>" +
      '<div id="stu-sidebar-mobile" class="fixed inset-0 z-[60] hidden">' +
        '<div id="stu-sidebar-backdrop" class="absolute inset-0 bg-slate-900/50"></div>' +
        '<aside class="absolute inset-y-0 left-0 w-72 flex flex-col text-white" style="background:linear-gradient(180deg,var(--brand-900),var(--brand-800) 60%,#0a2440)">' +
          '<div class="h-16 flex items-center justify-between px-5 border-b border-white/10">' +
            '<div class="flex items-center gap-2.5"><div class="w-9 h-9 rounded-lg text-brand-900 font-extrabold flex items-center justify-center text-sm" style="background:var(--accent-500)">STU</div><p class="font-extrabold text-sm">STU LMS</p></div>' +
            '<button id="stu-sidebar-close" class="text-white/70">' + UI.Icon.x + "</button>" +
          "</div>" +
          '<nav class="flex-1 overflow-y-auto px-3 py-4 space-y-0.5">' + buildSidebar(role, activeKey, base) + "</nav>" +
          '<div class="px-3 py-4 border-t border-white/10">' +
            '<a href="' + base + 'cek-sertifikat.html" class="nav-link">' + UI.Icon.qr + "<span>Cek Sertifikat</span></a>" +
            '<button id="stu-logout-m" class="nav-link w-full text-left">' + UI.Icon.logout + "<span>Keluar</span></button>" +
          "</div>" +
        "</aside>" +
      "</div>";

    var topbarHtml =
      '<header class="fixed top-0 right-0 left-0 lg:left-64 h-16 bg-white border-b border-slate-200 z-40 flex items-center gap-3 px-4 lg:px-6">' +
        '<button id="stu-menu-btn" class="lg:hidden text-slate-600">' + UI.Icon.menu + "</button>" +
        '<div class="hidden md:flex items-center gap-2 bg-slate-100 rounded-lg px-3 py-2 flex-1 max-w-sm text-slate-400">' + UI.Icon.search + '<input type="text" placeholder="Cari kelas, sertifikasi, peserta..." class="bg-transparent outline-none text-sm text-slate-600 w-full"></div>' +
        '<div class="flex-1 md:hidden"></div>' +
        '<span class="hidden sm:inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full ' + (role === "admin" ? "bg-indigo-50 text-indigo-700" : role === "trainer" ? "bg-teal-50 text-teal-700" : "bg-blue-50 text-blue-700") + '">' + (ROLE_LABEL[role] || "") + "</span>" +
        '<div class="relative">' +
          '<button id="stu-notif-btn" class="relative text-slate-500 hover:text-slate-700 p-2 rounded-lg hover:bg-slate-100">' + UI.Icon.bell + '<span class="absolute top-1.5 right-1.5 w-2 h-2 bg-rose-500 rounded-full ring-2 ring-white"></span></button>' +
          '<div id="stu-notif-panel" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-xl border border-slate-100 overflow-hidden">' +
            '<div class="px-4 py-3 border-b border-slate-100 font-bold text-sm text-slate-700">Notifikasi</div>' + buildNotif() +
          "</div>" +
        "</div>" +
        '<div class="relative">' +
          '<button id="stu-user-btn" class="flex items-center gap-2 pl-2 border-l border-slate-200">' +
            '<span class="avatar w-9 h-9 text-xs">' + (user ? UI.inisial(user.nama) : "??") + "</span>" +
            '<span class="hidden md:block text-left leading-tight"><span class="block text-sm font-bold text-slate-700">' + (user ? user.nama : "") + '</span><span class="block text-xs text-slate-400">' + (user ? user.sub : "") + "</span></span>" +
            '<span class="hidden md:block text-slate-400">' + UI.Icon.chevronDown + "</span>" +
          "</button>" +
          '<div id="stu-user-panel" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-xl border border-slate-100 overflow-hidden">' +
            (role === "peserta" ? '<a href="' + base + 'peserta/profil.html" class="block px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">Profil Saya</a>' : "") +
            '<a href="' + base + 'cek-sertifikat.html" class="block px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">Cek Sertifikat</a>' +
            '<button id="stu-logout-2" class="block w-full text-left px-4 py-2.5 text-sm text-rose-600 hover:bg-rose-50 border-t border-slate-100">Keluar</button>' +
          "</div>" +
        "</div>" +
      "</header>";

    shell.insertAdjacentHTML("beforebegin", sidebarHtml + topbarHtml);

    // interaksi
    function toggle(id) { var el = document.getElementById(id); el.classList.toggle("hidden"); }
    document.getElementById("stu-notif-btn").addEventListener("click", function (e) { e.stopPropagation(); document.getElementById("stu-user-panel").classList.add("hidden"); toggle("stu-notif-panel"); });
    document.getElementById("stu-user-btn").addEventListener("click", function (e) { e.stopPropagation(); document.getElementById("stu-notif-panel").classList.add("hidden"); toggle("stu-user-panel"); });
    document.addEventListener("click", function () {
      document.getElementById("stu-notif-panel").classList.add("hidden");
      document.getElementById("stu-user-panel").classList.add("hidden");
    });
    var menuBtn = document.getElementById("stu-menu-btn");
    if (menuBtn) menuBtn.addEventListener("click", function () { document.getElementById("stu-sidebar-mobile").classList.remove("hidden"); });
    var closeBtn = document.getElementById("stu-sidebar-close");
    if (closeBtn) closeBtn.addEventListener("click", function () { document.getElementById("stu-sidebar-mobile").classList.add("hidden"); });
    var backdrop = document.getElementById("stu-sidebar-backdrop");
    if (backdrop) backdrop.addEventListener("click", function () { document.getElementById("stu-sidebar-mobile").classList.add("hidden"); });

    function doLogout() { Store.logout(); window.location.href = base + "login.html"; }
    ["stu-logout", "stu-logout-m", "stu-logout-2"].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener("click", doLogout);
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    var shell = document.getElementById("shell");
    if (shell) renderShell(shell);
  });

  window.STU_LAYOUT = { ROLE_LABEL: ROLE_LABEL, currentUserDisplay: currentUserDisplay };
})();
