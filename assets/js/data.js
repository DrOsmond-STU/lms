/**
 * STU LMS — Data dummy (purwarupa)
 * Semua data di file ini adalah data contoh (mock) untuk kebutuhan demo UI/UX.
 * Tidak terhubung ke backend/database sungguhan.
 */
(function (global) {
  "use strict";

  var TAHUN_INI = 2026;

  var organisasi = [
    { id: "stu", nama: "Universitas Semesta Teknologi Utama", singkatan: "USTU", kota: "Jakarta", akreditasi: "A", tipe: "institusi" },
    { id: "unm", nama: "Universitas Nusantara Mandiri", singkatan: "UNM", kota: "Bandung", akreditasi: "A", tipe: "institusi" },
    { id: "itc", nama: "Institut Teknologi Cendekia", singkatan: "ITC", kota: "Surabaya", akreditasi: "B", tipe: "institusi" },
    { id: "pgm", nama: "Politeknik Graha Mandiri", singkatan: "PGM", kota: "Yogyakarta", akreditasi: "B", tipe: "institusi" },
    { id: "corp-01", nama: "PT Maju Bersama Indonesia", singkatan: "PMB", kota: "Jakarta", akreditasi: "-", tipe: "korporat", industri: "Manufaktur & Logistik", picKorporat: "Hendra Saputra (HR Learning & Development Manager)" }
  ];

  // ---------------------------------------------------------------------
  // Pelatihan sertifikasi
  // ---------------------------------------------------------------------
  var pelatihan = [
    {
      id: "aws-cp", kategori: "internasional", nama: "AWS Certified Cloud Practitioner",
      penyelenggara: "Amazon Web Services (AWS)", kodeSkema: "AWS-CCP", level: "Fundamental",
      durasiJam: 24, biaya: 0, skorMinimal: 70,
      deskripsi: "Memahami konsep dasar layanan cloud AWS, model keamanan, dan pelatihan biaya untuk memulai karier di bidang cloud computing.",
      tags: ["Cloud Computing", "AWS", "Infrastruktur"]
    },
    {
      id: "az-900", kategori: "internasional", nama: "Microsoft Azure Fundamentals (AZ-900)",
      penyelenggara: "Microsoft", kodeSkema: "AZ-900", level: "Fundamental",
      durasiJam: 20, biaya: 0, skorMinimal: 70,
      deskripsi: "Pengenalan konsep cloud, layanan inti Azure, keamanan, privasi, kepatuhan, serta harga dan dukungan Azure.",
      tags: ["Cloud Computing", "Microsoft Azure"]
    },
    {
      id: "ccna", kategori: "internasional", nama: "Cisco Certified Network Associate (CCNA)",
      penyelenggara: "Cisco Systems", kodeSkema: "CCNA-200-301", level: "Associate",
      durasiJam: 40, biaya: 0, skorMinimal: 75,
      deskripsi: "Kompetensi dasar jaringan: routing & switching, keamanan jaringan, otomasi, dan fundamental IP services.",
      tags: ["Jaringan", "Cisco", "Keamanan Siber"]
    },
    {
      id: "capm", kategori: "internasional", nama: "Certified Associate in Project Management (CAPM)",
      penyelenggara: "Project Management Institute (PMI)", kodeSkema: "CAPM", level: "Associate",
      durasiJam: 30, biaya: 0, skorMinimal: 70,
      deskripsi: "Dasar-dasar manajemen proyek mengacu pada PMBOK Guide: ruang lingkup, jadwal, biaya, dan risiko proyek.",
      tags: ["Manajemen Proyek", "PMI"]
    },
    {
      id: "jwd-bnsp", kategori: "bnsp", nama: "Junior Web Developer",
      penyelenggara: "LSP Teknologi Informasi Indonesia", kodeSkema: "SKKNI-JWD-2020", level: "KKNI II",
      durasiJam: 32, biaya: 0, skorMinimal: 70,
      deskripsi: "Pelatihan sertifikasi kompetensi berbasis SKKNI untuk pengembang web pemula: HTML/CSS, dasar pemrograman, dan basis data sederhana.",
      tags: ["Pengembangan Web", "BNSP"]
    },
    {
      id: "dm-bnsp", kategori: "bnsp", nama: "Digital Marketing",
      penyelenggara: "LSP Digital Kreatif", kodeSkema: "SKKNI-DM-2021", level: "KKNI III",
      durasiJam: 28, biaya: 0, skorMinimal: 70,
      deskripsi: "Kompetensi pemasaran digital: strategi konten, SEO/SEM dasar, media sosial, dan analitik pemasaran.",
      tags: ["Pemasaran Digital", "BNSP"]
    },
    {
      id: "k3-bnsp", kategori: "bnsp", nama: "Ahli K3 Umum",
      penyelenggara: "LSP QHSE Nusantara", kodeSkema: "SKKNI-K3U-2019", level: "KKNI IV",
      durasiJam: 36, biaya: 0, skorMinimal: 75,
      deskripsi: "Kompetensi Keselamatan dan Kesehatan Kerja (K3) umum sesuai regulasi ketenagakerjaan untuk lingkungan kerja industri.",
      tags: ["K3", "QHSE", "BNSP"]
    },
    {
      id: "jna-bnsp", kategori: "bnsp", nama: "Junior Network Administrator",
      penyelenggara: "LSP Teknologi Informasi Indonesia", kodeSkema: "SKKNI-JNA-2020", level: "KKNI II",
      durasiJam: 30, biaya: 0, skorMinimal: 70,
      deskripsi: "Kompetensi administrasi jaringan dasar: instalasi perangkat jaringan, konfigurasi, dan pemeliharaan jaringan LAN/WAN sederhana.",
      tags: ["Jaringan", "BNSP"]
    }
  ];

  // Field tambahan untuk konteks platform pelatihan generik: mode, status
  // katalog (Course Management), bahasa, dan harga publik (Payment/E-Commerce).
  var MODE_PELATIHAN = { "aws-cp": "online", "az-900": "online", "ccna": "hybrid", "capm": "online", "jwd-bnsp": "offline", "dm-bnsp": "online", "k3-bnsp": "hybrid", "jna-bnsp": "online" };
  var STATUS_PELATIHAN = { "az-900": "review", "jna-bnsp": "draft" };
  var HARGA_PELATIHAN = { "capm": 350000, "dm-bnsp": 250000 };
  pelatihan.forEach(function (s) {
    s.mode = MODE_PELATIHAN[s.id] || "online";
    s.status = STATUS_PELATIHAN[s.id] || "published";
    s.bahasa = "Indonesia";
    s.harga = HARGA_PELATIHAN[s.id] || 0;
  });
  // Satu pelatihan berstatus arsip, untuk demo Course Management (status lifecycle).
  pelatihan.push({
    id: "wp-arsip", kategori: "internasional", nama: "WordPress Fundamentals (Diarsipkan)",
    penyelenggara: "Automattic Learning Partner", kodeSkema: "WP-FUND-2023", level: "Fundamental",
    durasiJam: 16, biaya: 0, skorMinimal: 70,
    deskripsi: "Program pelatihan dasar pengelolaan CMS WordPress. Tidak lagi dibuka untuk peserta baru.",
    tags: ["CMS", "Web"], mode: "online", status: "archived", bahasa: "Indonesia", harga: 0
  });

  // ---------------------------------------------------------------------
  // Trainer
  // ---------------------------------------------------------------------
  var trainer = [
    { id: "ins-01", nama: "Dr. Andi Wijaya, S.Kom., M.T.", universitasId: "stu", keahlian: "Cloud Computing & Infrastruktur", email: "andi.wijaya@stu.ac.id", mengampu: ["aws-cp", "az-900"] },
    { id: "ins-02", nama: "Siti Rahmawati, S.T., M.M.", universitasId: "unm", keahlian: "Pemasaran Digital & Manajemen Proyek", email: "siti.rahmawati@unm.ac.id", mengampu: ["dm-bnsp", "capm"] },
    { id: "ins-03", nama: "Bambang Kusuma, S.T.", universitasId: "stu", keahlian: "Jaringan & Keamanan Siber", email: "bambang.kusuma@stu.ac.id", mengampu: ["ccna", "jna-bnsp"] },
    { id: "ins-04", nama: "Ir. Yulianti Puspasari, M.T., CSP", universitasId: "itc", keahlian: "K3 & QHSE", email: "yulianti.p@itc.ac.id", mengampu: ["k3-bnsp"] },
    { id: "ins-05", nama: "Fajar Nugroho, S.Kom.", universitasId: "pgm", keahlian: "Pengembangan Web", email: "fajar.nugroho@pgm.ac.id", mengampu: ["jwd-bnsp"] }
  ];

  // ---------------------------------------------------------------------
  // Kelas — satu kelas/batch aktif per pelatihan
  // ---------------------------------------------------------------------
  function modulStandar(prefix, judulList) {
    return judulList.map(function (j, idx) {
      return {
        id: prefix + "-m" + (idx + 1),
        judul: j.judul,
        tipe: j.tipe, // 'video' | 'pdf' | 'quiz'
        durasi: j.durasi
      };
    });
  }

  var kelas = [
    {
      id: "kls-aws-cp", skemaId: "aws-cp", instrukturId: "ins-01", batch: "Genap 2026", kuota: 40,
      modul: modulStandar("aws", [
        { judul: "Pengantar Cloud Computing & AWS", tipe: "video", durasi: "45 menit" },
        { judul: "Model Keamanan & Tanggung Jawab Bersama", tipe: "video", durasi: "40 menit" },
        { judul: "Layanan Inti AWS (Compute, Storage, Database)", tipe: "pdf", durasi: "18 halaman" },
        { judul: "Model Biaya & Dukungan AWS", tipe: "pdf", durasi: "12 halaman" },
        { judul: "Kuis Akhir: AWS Cloud Practitioner", tipe: "quiz", durasi: "5 soal" }
      ]),
      kuis: [
        { soal: "Model layanan cloud yang menyediakan infrastruktur (server, storage, jaringan) tanpa peserta perlu mengelola perangkat fisik disebut?", opsi: ["SaaS", "IaaS", "PaaS", "On-Premise"], jawaban: 1 },
        { soal: "Dalam model Shared Responsibility AWS, siapa yang bertanggung jawab atas keamanan 'di dalam' cloud (data & konfigurasi aplikasi)?", opsi: ["AWS sepenuhnya", "Pelanggan (customer)", "Pemerintah", "Tidak ada pihak yang bertanggung jawab"], jawaban: 1 },
        { soal: "Layanan AWS untuk penyimpanan objek (object storage) adalah?", opsi: ["EC2", "RDS", "S3", "VPC"], jawaban: 2 },
        { soal: "Manfaat utama elastisitas (elasticity) pada cloud computing adalah?", opsi: ["Biaya tetap tanpa perubahan", "Sumber daya menyesuaikan otomatis sesuai kebutuhan", "Data selalu tersimpan permanen", "Tidak memerlukan koneksi internet"], jawaban: 1 },
        { soal: "AWS Free Tier bertujuan untuk?", opsi: ["Menghapus tagihan pengguna lama", "Memberi akses uji coba layanan dengan batas gratis tertentu", "Menggantikan seluruh biaya produksi", "Hanya berlaku untuk pemerintah"], jawaban: 1 }
      ]
    },
    {
      id: "kls-az-900", skemaId: "az-900", instrukturId: "ins-01", batch: "Genap 2026", kuota: 35,
      modul: modulStandar("az", [
        { judul: "Konsep Dasar Cloud & Model Azure", tipe: "video", durasi: "42 menit" },
        { judul: "Layanan Inti Microsoft Azure", tipe: "video", durasi: "38 menit" },
        { judul: "Keamanan, Privasi, dan Kepatuhan Azure", tipe: "pdf", durasi: "15 halaman" },
        { judul: "Harga & Dukungan Azure", tipe: "pdf", durasi: "10 halaman" },
        { judul: "Kuis Akhir: Azure Fundamentals", tipe: "quiz", durasi: "5 soal" }
      ]),
      kuis: [
        { soal: "Azure Resource Manager (ARM) digunakan untuk?", opsi: ["Mengelola & mengorganisasi resource Azure", "Mengganti sistem operasi", "Mencetak invoice", "Menghapus akun pengguna"], jawaban: 0 },
        { soal: "Model cloud yang sepenuhnya dikelola pihak ketiga di internet disebut?", opsi: ["Private Cloud", "Public Cloud", "Hybrid Cloud", "Community Cloud"], jawaban: 1 },
        { soal: "Fitur Azure untuk memantau kesehatan & performa layanan disebut?", opsi: ["Azure Monitor", "Azure DNS", "Azure Files", "Azure DevTest"], jawaban: 0 },
        { soal: "SLA (Service Level Agreement) pada Azure menjamin?", opsi: ["Harga termurah", "Tingkat ketersediaan/uptime layanan", "Jumlah pengguna maksimum", "Warna antarmuka"], jawaban: 1 },
        { soal: "Azure Active Directory berfungsi sebagai?", opsi: ["Layanan identitas & manajemen akses", "Penyimpanan file media", "Mesin virtual", "Load balancer"], jawaban: 0 }
      ]
    },
    {
      id: "kls-ccna", skemaId: "ccna", instrukturId: "ins-03", batch: "Genap 2026", kuota: 30,
      modul: modulStandar("ccna", [
        { judul: "Dasar Jaringan & Model OSI", tipe: "video", durasi: "50 menit" },
        { judul: "Routing & Switching Dasar", tipe: "video", durasi: "55 menit" },
        { judul: "Keamanan Jaringan Dasar", tipe: "pdf", durasi: "20 halaman" },
        { judul: "IP Services & Otomasi Jaringan", tipe: "pdf", durasi: "16 halaman" },
        { judul: "Kuis Akhir: CCNA 200-301", tipe: "quiz", durasi: "5 soal" }
      ]),
      kuis: [
        { soal: "Perangkat yang bekerja pada Layer 2 model OSI untuk meneruskan frame berdasarkan MAC address adalah?", opsi: ["Router", "Switch", "Hub", "Modem"], jawaban: 1 },
        { soal: "Protokol routing yang termasuk kategori link-state adalah?", opsi: ["RIP", "OSPF", "Static Route", "ARP"], jawaban: 1 },
        { soal: "VLAN digunakan untuk?", opsi: ["Memperbesar bandwidth fisik", "Segmentasi jaringan secara logis", "Mengganti alamat IP publik", "Menghapus broadcast domain"], jawaban: 1 },
        { soal: "Alamat IP versi 4 terdiri dari berapa bit?", opsi: ["16 bit", "32 bit", "64 bit", "128 bit"], jawaban: 1 },
        { soal: "ACL (Access Control List) pada perangkat Cisco berfungsi untuk?", opsi: ["Menyaring lalu lintas jaringan", "Mempercepat routing", "Membuat VLAN otomatis", "Mengatur warna kabel"], jawaban: 0 }
      ]
    },
    {
      id: "kls-capm", skemaId: "capm", instrukturId: "ins-02", batch: "Genap 2026", kuota: 30,
      modul: modulStandar("capm", [
        { judul: "Pengantar Manajemen Proyek & PMBOK", tipe: "video", durasi: "40 menit" },
        { judul: "Ruang Lingkup & Jadwal Proyek", tipe: "video", durasi: "35 menit" },
        { judul: "Manajemen Biaya & Risiko Proyek", tipe: "pdf", durasi: "14 halaman" },
        { judul: "Manajemen Stakeholder & Komunikasi", tipe: "pdf", durasi: "10 halaman" },
        { judul: "Kuis Akhir: CAPM Fundamentals", tipe: "quiz", durasi: "5 soal" }
      ]),
      kuis: [
        { soal: "Dokumen yang secara resmi mengesahkan dimulainya sebuah proyek disebut?", opsi: ["Project Charter", "Risk Register", "Status Report", "Change Log"], jawaban: 0 },
        { soal: "Teknik penjadwalan yang menggambarkan jalur terpanjang aktivitas proyek disebut?", opsi: ["Critical Path Method", "SWOT Analysis", "RACI Matrix", "Gantt Only"], jawaban: 0 },
        { soal: "Manajemen risiko proaktif bertujuan untuk?", opsi: ["Menghindari seluruh risiko", "Mengidentifikasi & merespons risiko sebelum terjadi", "Menunda proyek", "Menambah anggaran tanpa analisis"], jawaban: 1 },
        { soal: "Stakeholder proyek adalah?", opsi: ["Hanya sponsor proyek", "Pihak yang terdampak/mempengaruhi proyek", "Hanya tim inti proyek", "Vendor eksternal saja"], jawaban: 1 },
        { soal: "Baseline biaya proyek digunakan sebagai?", opsi: ["Acuan pembanding realisasi biaya", "Batas maksimum tanpa toleransi", "Dokumen legal kontrak", "Laporan akhir proyek"], jawaban: 0 }
      ]
    },
    {
      id: "kls-jwd-bnsp", skemaId: "jwd-bnsp", instrukturId: "ins-05", batch: "Genap 2026", kuota: 32,
      modul: modulStandar("jwd", [
        { judul: "Dasar HTML & Struktur Halaman Web", tipe: "video", durasi: "48 menit" },
        { judul: "CSS & Responsive Layout", tipe: "video", durasi: "50 menit" },
        { judul: "Dasar Pemrograman JavaScript", tipe: "pdf", durasi: "18 halaman" },
        { judul: "Integrasi Basis Data Sederhana", tipe: "pdf", durasi: "12 halaman" },
        { judul: "Kuis Akhir: Junior Web Developer", tipe: "quiz", durasi: "5 soal" }
      ]),
      kuis: [
        { soal: "Tag HTML untuk membuat tautan (link) adalah?", opsi: ["<link>", "<a>", "<href>", "<nav>"], jawaban: 1 },
        { soal: "CSS Flexbox digunakan untuk?", opsi: ["Mengatur tata letak elemen secara fleksibel", "Menyimpan data ke server", "Membuat animasi video", "Mengenkripsi data"], jawaban: 0 },
        { soal: "Variabel pada JavaScript dapat dideklarasikan dengan kata kunci?", opsi: ["var, let, const", "int, float, str", "def, class", "select, insert"], jawaban: 0 },
        { soal: "Bahasa query standar untuk mengambil data dari basis data relasional adalah?", opsi: ["HTML", "SQL", "CSS", "JSON"], jawaban: 1 },
        { soal: "Website yang tampilannya menyesuaikan ukuran layar disebut?", opsi: ["Statis", "Responsif", "Terenkripsi", "Tervirtualisasi"], jawaban: 1 }
      ]
    },
    {
      id: "kls-dm-bnsp", skemaId: "dm-bnsp", instrukturId: "ins-02", batch: "Genap 2026", kuota: 32,
      modul: modulStandar("dm", [
        { judul: "Strategi Konten & Branding Digital", tipe: "video", durasi: "36 menit" },
        { judul: "Dasar SEO & SEM", tipe: "video", durasi: "42 menit" },
        { judul: "Pemasaran Media Sosial", tipe: "pdf", durasi: "14 halaman" },
        { judul: "Analitik & Pengukuran Kinerja Kampanye", tipe: "pdf", durasi: "10 halaman" },
        { judul: "Kuis Akhir: Digital Marketing", tipe: "quiz", durasi: "5 soal" }
      ]),
      kuis: [
        { soal: "SEO bertujuan untuk?", opsi: ["Meningkatkan peringkat organik di mesin pencari", "Membeli iklan berbayar", "Menghapus konten lama", "Mengganti domain website"], jawaban: 0 },
        { soal: "Metrik CTR (Click Through Rate) mengukur?", opsi: ["Jumlah klik dibanding jumlah tayangan iklan", "Jumlah pengikut media sosial", "Jumlah produk terjual", "Durasi video ditonton"], jawaban: 0 },
        { soal: "Konten evergreen adalah konten yang?", opsi: ["Hanya relevan sesaat", "Tetap relevan dalam jangka panjang", "Hanya berbentuk video", "Tidak boleh diperbarui"], jawaban: 1 },
        { soal: "A/B Testing pada pemasaran digital digunakan untuk?", opsi: ["Membandingkan dua versi untuk melihat performa terbaik", "Menggandakan anggaran iklan", "Menghapus akun kompetitor", "Mengubah harga produk"], jawaban: 0 },
        { soal: "Buyer persona adalah?", opsi: ["Profil representatif target pelanggan", "Nama produk baru", "Jenis mata uang digital", "Template email"], jawaban: 0 }
      ]
    },
    {
      id: "kls-k3-bnsp", skemaId: "k3-bnsp", instrukturId: "ins-04", batch: "Genap 2026", kuota: 28,
      modul: modulStandar("k3", [
        { judul: "Dasar Hukum & Regulasi K3", tipe: "video", durasi: "40 menit" },
        { judul: "Identifikasi Bahaya & Penilaian Risiko", tipe: "video", durasi: "45 menit" },
        { judul: "Sistem Manajemen K3 (SMK3)", tipe: "pdf", durasi: "20 halaman" },
        { judul: "Kesiapsiagaan Tanggap Darurat", tipe: "pdf", durasi: "14 halaman" },
        { judul: "Kuis Akhir: Ahli K3 Umum", tipe: "quiz", durasi: "5 soal" }
      ]),
      kuis: [
        { soal: "Kepanjangan K3 adalah?", opsi: ["Keselamatan dan Kesehatan Kerja", "Ketertiban Kerja Karyawan", "Kompetensi Kerja Karyawan", "Kebijakan Kerja Khusus"], jawaban: 0 },
        { soal: "HIRARC adalah metode untuk?", opsi: ["Identifikasi bahaya, penilaian, dan pengendalian risiko", "Perhitungan gaji karyawan", "Audit keuangan perusahaan", "Penjadwalan produksi"], jawaban: 0 },
        { soal: "APD adalah singkatan dari?", opsi: ["Alat Pelindung Diri", "Analisis Produktivitas Dasar", "Aturan Perusahaan Digital", "Alur Proses Distribusi"], jawaban: 0 },
        { soal: "Tujuan utama SMK3 di perusahaan adalah?", opsi: ["Mengurangi risiko kecelakaan kerja secara sistematis", "Meningkatkan target penjualan", "Mempercepat proses rekrutmen", "Mengganti seragam kerja"], jawaban: 0 },
        { soal: "Simbol segitiga kuning dengan tanda seru pada rambu K3 menandakan?", opsi: ["Peringatan bahaya", "Larangan merokok", "Jalur evakuasi", "Area parkir"], jawaban: 0 }
      ]
    },
    {
      id: "kls-jna-bnsp", skemaId: "jna-bnsp", instrukturId: "ins-03", batch: "Genap 2026", kuota: 30,
      modul: modulStandar("jna", [
        { judul: "Instalasi Perangkat Jaringan Dasar", tipe: "video", durasi: "38 menit" },
        { judul: "Konfigurasi LAN & WLAN", tipe: "video", durasi: "44 menit" },
        { judul: "Troubleshooting Jaringan Dasar", tipe: "pdf", durasi: "16 halaman" },
        { judul: "Pemeliharaan & Dokumentasi Jaringan", tipe: "pdf", durasi: "10 halaman" },
        { judul: "Kuis Akhir: Junior Network Administrator", tipe: "quiz", durasi: "5 soal" }
      ]),
      kuis: [
        { soal: "Perintah dasar untuk menguji konektivitas jaringan adalah?", opsi: ["ping", "format", "delete", "compile"], jawaban: 0 },
        { soal: "WLAN merupakan singkatan dari?", opsi: ["Wireless Local Area Network", "Wide Local Access Node", "Web Local Area Network", "Wired Logical Area Network"], jawaban: 0 },
        { soal: "Subnetting bertujuan untuk?", opsi: ["Membagi jaringan besar menjadi beberapa jaringan kecil", "Menghapus alamat IP", "Mempercepat listrik", "Mengganti kabel fiber"], jawaban: 0 },
        { soal: "Dokumentasi jaringan penting untuk?", opsi: ["Memudahkan pemeliharaan & troubleshooting", "Mengurangi jumlah perangkat", "Meningkatkan harga jaringan", "Menghapus histori akses"], jawaban: 0 },
        { soal: "DHCP berfungsi untuk?", opsi: ["Memberikan alamat IP otomatis ke perangkat", "Mengenkripsi lalu lintas data", "Menyimpan cadangan data", "Memblokir seluruh akses internet"], jawaban: 0 }
      ]
    }
  ];

  // Struktur konten Course → Module → Chapter → Lesson (untuk layar Content
  // Management trainer/admin). Dibungkus otomatis dari daftar modul/lesson
  // yang sudah ada supaya tidak mendata ulang seluruh konten dari nol.
  kelas.forEach(function (k) {
    var lessonModul = k.modul.filter(function (m) { return m.tipe !== "quiz"; });
    var lessonQuiz = k.modul.filter(function (m) { return m.tipe === "quiz"; });
    k.strukturModul = [
      {
        judul: "Modul 1 — Fondasi",
        bab: [
          { judul: "Bab 1. Pengantar", lesson: lessonModul.slice(0, Math.ceil(lessonModul.length / 2)) },
          { judul: "Bab 2. Pendalaman", lesson: lessonModul.slice(Math.ceil(lessonModul.length / 2)) }
        ]
      },
      {
        judul: "Modul 2 — Evaluasi",
        bab: [
          { judul: "Bab 1. Uji Pemahaman", lesson: lessonQuiz }
        ]
      }
    ];
    // Ujian Akhir (Final Examination) — terpisah dari Quiz per-modul.
    k.ujianAkhir = {
      id: k.id + "-ujian",
      judul: "Ujian Akhir — " + k.batch,
      durasiMenit: 30,
      jumlahSoal: k.kuis.length,
      jadwal: "Tersedia setelah seluruh modul & kuis selesai",
      status: "terjadwal"
    };
  });

  // ---------------------------------------------------------------------
  // Peserta (dummy) — mhs-01 adalah akun demo login "Peserta"
  // ---------------------------------------------------------------------
  var peserta = [
    { id: "mhs-01", nama: "Raka Prasetya", npm: "2021071001", universitasId: "stu", prodi: "Teknik Informatika", semester: 7, email: "raka.prasetya@stu.ac.id" },
    { id: "mhs-02", nama: "Dewi Lestari", npm: "2021071014", universitasId: "stu", prodi: "Sistem Informasi", semester: 7, email: "dewi.lestari@stu.ac.id" },
    { id: "mhs-03", nama: "Muhammad Ilham", npm: "2020071022", universitasId: "stu", prodi: "Teknik Informatika", semester: 9, email: "m.ilham@stu.ac.id" },
    { id: "mhs-04", nama: "Putri Ayu Wandari", npm: "2022092031", universitasId: "unm", prodi: "Manajemen", semester: 5, email: "putri.wandari@unm.ac.id" },
    { id: "mhs-05", nama: "Rizky Ramadhan", npm: "2021092008", universitasId: "unm", prodi: "Ilmu Komunikasi", semester: 7, email: "rizky.ramadhan@unm.ac.id" },
    { id: "mhs-06", nama: "Anisa Fitriani", npm: "2021045019", universitasId: "itc", prodi: "Teknik Industri", semester: 7, email: "anisa.fitriani@itc.ac.id" },
    { id: "mhs-07", nama: "Yoga Pratama", npm: "2020045003", universitasId: "itc", prodi: "K3 / Teknik Industri", semester: 9, email: "yoga.pratama@itc.ac.id" },
    { id: "mhs-08", nama: "Salsabila Zahra", npm: "2022063012", universitasId: "pgm", prodi: "Teknik Komputer", semester: 5, email: "salsabila.zahra@pgm.ac.id" },
    { id: "mhs-09", nama: "Agus Setiawan", npm: "2021063027", universitasId: "pgm", prodi: "Teknik Komputer", semester: 7, email: "agus.setiawan@pgm.ac.id" },
    { id: "mhs-10", nama: "Nadia Kirana", npm: "2022071044", universitasId: "stu", prodi: "Sistem Informasi", semester: 5, email: "nadia.kirana@stu.ac.id" },
    { id: "mhs-11", nama: "Fikri Hidayat", npm: "2021092017", universitasId: "unm", prodi: "Teknik Informatika", semester: 7, email: "fikri.hidayat@unm.ac.id" },
    { id: "mhs-12", nama: "Larasati Wulandari", npm: "2020045011", universitasId: "itc", prodi: "Teknik Industri", semester: 9, email: "larasati.w@itc.ac.id" },
    // Peserta korporat (Corporate Training) — didaftarkan massal oleh PT Maju Bersama Indonesia
    { id: "mhs-13", nama: "Wahyu Saputra", npm: "PMB-EMP-0231", universitasId: "corp-01", departemen: "Operasional Gudang", email: "wahyu.saputra@majubersama.co.id" },
    { id: "mhs-14", nama: "Rina Marlina", npm: "PMB-EMP-0245", universitasId: "corp-01", departemen: "Quality Control", email: "rina.marlina@majubersama.co.id" },
    { id: "mhs-15", nama: "Doni Kurniawan", npm: "PMB-EMP-0198", universitasId: "corp-01", departemen: "Operasional Gudang", email: "doni.kurniawan@majubersama.co.id" },
    { id: "mhs-16", nama: "Eka Purnamasari", npm: "PMB-EMP-0267", universitasId: "corp-01", departemen: "HSE (Health, Safety & Environment)", email: "eka.purnamasari@majubersama.co.id" }
  ];

  // ---------------------------------------------------------------------
  // Pendaftaran / enrolments
  // status: 'terdaftar' | 'berjalan' | 'menunggu_approval' | 'lulus' | 'tidak_lulus'
  // ---------------------------------------------------------------------
  var pendaftaran = [
    // Raka Prasetya (mhs-01) — akun demo peserta: 1 lulus, 1 berjalan, 1 terdaftar
    { id: "pd-001", mahasiswaId: "mhs-01", skemaId: "aws-cp", status: "lulus", progres: 100, skorKuis: 90, tanggalDaftar: "2026-01-12", tanggalSelesai: "2026-02-20", modulSelesai: ["aws-m1", "aws-m2", "aws-m3", "aws-m4", "aws-m5"] },
    { id: "pd-002", mahasiswaId: "mhs-01", skemaId: "jwd-bnsp", status: "berjalan", progres: 60, skorKuis: null, tanggalDaftar: "2026-03-02", tanggalSelesai: null, modulSelesai: ["jwd-m1", "jwd-m2", "jwd-m3"] },
    { id: "pd-003", mahasiswaId: "mhs-01", skemaId: "ccna", status: "terdaftar", progres: 0, skorKuis: null, tanggalDaftar: "2026-08-20", tanggalSelesai: null, modulSelesai: [] },

    { id: "pd-004", mahasiswaId: "mhs-02", skemaId: "dm-bnsp", status: "lulus", progres: 100, skorKuis: 84, tanggalDaftar: "2025-11-05", tanggalSelesai: "2025-12-18", modulSelesai: ["dm-m1", "dm-m2", "dm-m3", "dm-m4", "dm-m5"] },
    { id: "pd-005", mahasiswaId: "mhs-02", skemaId: "capm", status: "menunggu_approval", progres: 100, skorKuis: 80, tanggalDaftar: "2026-04-01", tanggalSelesai: "2026-06-10", modulSelesai: ["capm-m1", "capm-m2", "capm-m3", "capm-m4", "capm-m5"] },

    { id: "pd-006", mahasiswaId: "mhs-03", skemaId: "ccna", status: "lulus", progres: 100, skorKuis: 88, tanggalDaftar: "2025-09-10", tanggalSelesai: "2025-11-02", modulSelesai: ["ccna-m1", "ccna-m2", "ccna-m3", "ccna-m4", "ccna-m5"] },
    { id: "pd-007", mahasiswaId: "mhs-03", skemaId: "jna-bnsp", status: "tidak_lulus", progres: 100, skorKuis: 55, tanggalDaftar: "2026-01-15", tanggalSelesai: "2026-02-28", modulSelesai: ["jna-m1", "jna-m2", "jna-m3", "jna-m4", "jna-m5"] },

    { id: "pd-008", mahasiswaId: "mhs-04", skemaId: "capm", status: "lulus", progres: 100, skorKuis: 76, tanggalDaftar: "2025-10-01", tanggalSelesai: "2025-11-20", modulSelesai: ["capm-m1", "capm-m2", "capm-m3", "capm-m4", "capm-m5"] },
    { id: "pd-009", mahasiswaId: "mhs-05", skemaId: "dm-bnsp", status: "berjalan", progres: 40, skorKuis: null, tanggalDaftar: "2026-06-01", tanggalSelesai: null, modulSelesai: ["dm-m1", "dm-m2"] },

    { id: "pd-010", mahasiswaId: "mhs-06", skemaId: "k3-bnsp", status: "lulus", progres: 100, skorKuis: 92, tanggalDaftar: "2025-08-01", tanggalSelesai: "2025-09-25", modulSelesai: ["k3-m1", "k3-m2", "k3-m3", "k3-m4", "k3-m5"] },
    { id: "pd-011", mahasiswaId: "mhs-07", skemaId: "k3-bnsp", status: "menunggu_approval", progres: 100, skorKuis: 78, tanggalDaftar: "2026-05-01", tanggalSelesai: "2026-07-15", modulSelesai: ["k3-m1", "k3-m2", "k3-m3", "k3-m4", "k3-m5"] },

    { id: "pd-012", mahasiswaId: "mhs-08", skemaId: "jwd-bnsp", status: "lulus", progres: 100, skorKuis: 81, tanggalDaftar: "2025-07-01", tanggalSelesai: "2025-08-22", modulSelesai: ["jwd-m1", "jwd-m2", "jwd-m3", "jwd-m4", "jwd-m5"] },
    { id: "pd-013", mahasiswaId: "mhs-09", skemaId: "jna-bnsp", status: "berjalan", progres: 80, skorKuis: null, tanggalDaftar: "2026-07-01", tanggalSelesai: null, modulSelesai: ["jna-m1", "jna-m2", "jna-m3", "jna-m4"] },

    { id: "pd-014", mahasiswaId: "mhs-10", skemaId: "az-900", status: "terdaftar", progres: 0, skorKuis: null, tanggalDaftar: "2026-08-25", tanggalSelesai: null, modulSelesai: [] },
    { id: "pd-015", mahasiswaId: "mhs-11", skemaId: "aws-cp", status: "lulus", progres: 100, skorKuis: 95, tanggalDaftar: "2025-12-01", tanggalSelesai: "2026-01-18", modulSelesai: ["aws-m1", "aws-m2", "aws-m3", "aws-m4", "aws-m5"] },
    { id: "pd-016", mahasiswaId: "mhs-12", skemaId: "k3-bnsp", status: "lulus", progres: 100, skorKuis: 87, tanggalDaftar: "2025-06-01", tanggalSelesai: "2025-07-30", modulSelesai: ["k3-m1", "k3-m2", "k3-m3", "k3-m4", "k3-m5"] },

    // Rombongan korporat PT Maju Bersama Indonesia (Corporate Training)
    { id: "pd-017", mahasiswaId: "mhs-13", skemaId: "k3-bnsp", status: "lulus", progres: 100, skorKuis: 85, tanggalDaftar: "2026-04-01", tanggalSelesai: "2026-05-20", modulSelesai: ["k3-m1", "k3-m2", "k3-m3", "k3-m4", "k3-m5"] },
    { id: "pd-018", mahasiswaId: "mhs-14", skemaId: "k3-bnsp", status: "berjalan", progres: 80, skorKuis: null, tanggalDaftar: "2026-07-01", tanggalSelesai: null, modulSelesai: ["k3-m1", "k3-m2", "k3-m3", "k3-m4"] },
    { id: "pd-019", mahasiswaId: "mhs-15", skemaId: "k3-bnsp", status: "berjalan", progres: 40, skorKuis: null, tanggalDaftar: "2026-07-15", tanggalSelesai: null, modulSelesai: ["k3-m1", "k3-m2"] },
    { id: "pd-020", mahasiswaId: "mhs-16", skemaId: "k3-bnsp", status: "menunggu_approval", progres: 100, skorKuis: 80, tanggalDaftar: "2026-05-01", tanggalSelesai: "2026-06-25", modulSelesai: ["k3-m1", "k3-m2", "k3-m3", "k3-m4", "k3-m5"] }
  ];

  // ---------------------------------------------------------------------
  // Sertifikat terbit (hanya untuk pendaftaran berstatus 'lulus')
  // status: 'aktif' | 'kedaluwarsa' | 'dicabut'
  // ---------------------------------------------------------------------
  var sertifikat = [
    { id: "cert-001", nomor: "INTL/AWS-CCP/USTU/2026/00001", mahasiswaId: "mhs-01", skemaId: "aws-cp", tanggalTerbit: "2026-02-22", berlakuHingga: "2029-02-22", status: "aktif" },
    { id: "cert-002", nomor: "BNSP/DM/UNM/2025/00014", mahasiswaId: "mhs-02", skemaId: "dm-bnsp", tanggalTerbit: "2025-12-20", berlakuHingga: "2028-12-20", status: "aktif" },
    { id: "cert-003", nomor: "INTL/CCNA/USTU/2025/00027", mahasiswaId: "mhs-03", skemaId: "ccna", tanggalTerbit: "2025-11-05", berlakuHingga: "2028-11-05", status: "aktif" },
    { id: "cert-004", nomor: "INTL/CAPM/UNM/2025/00009", mahasiswaId: "mhs-04", skemaId: "capm", tanggalTerbit: "2025-11-22", berlakuHingga: "2028-11-22", status: "aktif" },
    { id: "cert-005", nomor: "BNSP/K3U/ITC/2022/00133", mahasiswaId: "mhs-06", skemaId: "k3-bnsp", tanggalTerbit: "2022-09-28", berlakuHingga: "2025-09-28", status: "kedaluwarsa" },
    { id: "cert-006", nomor: "BNSP/JWD/PGM/2025/00058", mahasiswaId: "mhs-08", skemaId: "jwd-bnsp", tanggalTerbit: "2025-08-25", berlakuHingga: "2028-08-25", status: "aktif" },
    { id: "cert-007", nomor: "INTL/AWS-CCP/UNM/2026/00002", mahasiswaId: "mhs-11", skemaId: "aws-cp", tanggalTerbit: "2026-01-20", berlakuHingga: "2029-01-20", status: "aktif" },
    { id: "cert-008", nomor: "BNSP/K3U/ITC/2025/00071", mahasiswaId: "mhs-12", skemaId: "k3-bnsp", tanggalTerbit: "2025-08-02", berlakuHingga: "2028-08-02", status: "dicabut", catatanPencabutan: "Ditemukan pelanggaran integritas ujian pada audit internal LSP." },
    { id: "cert-009", nomor: "BNSP/K3U/PMB/2026/00088", mahasiswaId: "mhs-13", skemaId: "k3-bnsp", tanggalTerbit: "2026-05-25", berlakuHingga: "2029-05-25", status: "aktif" }
  ];

  // ---------------------------------------------------------------------
  // Notifikasi (contoh, ditampilkan di topbar)
  // ---------------------------------------------------------------------
  var notifikasi = [
    { judul: "Sertifikat AWS Cloud Practitioner terbit", waktu: "2 hari lalu", tipe: "sukses" },
    { judul: "Modul baru tersedia di kelas Junior Web Developer", waktu: "5 hari lalu", tipe: "info" },
    { judul: "Batas akhir pengisian kuis CCNA: 10 hari lagi", waktu: "1 minggu lalu", tipe: "peringatan" }
  ];

  var akunDemo = {
    peserta: { userId: "mhs-01", role: "peserta" },
    trainer: { userId: "ins-01", role: "trainer" },
    admin: { userId: "adm-01", role: "admin" }
  };

  var admin = [
    { id: "adm-01", nama: "Dewi Anggraini", jabatan: "Super Admin LMS", email: "dewi.anggraini@semestateknologiutama.com" }
  ];

  // ---------------------------------------------------------------------
  // Assignment (Tugas) & Submission
  // ---------------------------------------------------------------------
  var tugas = [
    { id: "tugas-jwd-01", kelasId: "kls-jwd-bnsp", judul: "Membangun Landing Page Responsif", deskripsi: "Buat satu halaman landing page responsif (HTML/CSS/JS) sesuai brief pada modul, lalu unggah dalam bentuk arsip .zip.", deadline: "2026-09-20", lampiranContoh: "Panduan-Tugas-1.pdf" },
    { id: "tugas-dm-01", kelasId: "kls-dm-bnsp", judul: "Menyusun Rencana Kampanye Digital 30 Hari", deskripsi: "Susun rencana kampanye pemasaran digital 30 hari untuk produk pilihan, mencakup konten, kanal, dan target KPI.", deadline: "2026-09-15", lampiranContoh: "Template-Rencana-Kampanye.docx" }
  ];

  var pengumpulanTugas = [
    { id: "sub-001", tugasId: "tugas-jwd-01", mahasiswaId: "mhs-01", fileNama: "raka-landingpage-v1.zip", tanggalKumpul: "2026-08-30", status: "submitted", nilai: null, feedback: null, riwayatRevisi: [] },
    { id: "sub-002", tugasId: "tugas-jwd-01", mahasiswaId: "mhs-08", fileNama: "salsabila-tugas1.zip", tanggalKumpul: "2025-08-15", status: "approved", nilai: 88, feedback: "Bagus, struktur HTML rapi dan sudah responsif di semua breakpoint.", riwayatRevisi: [] },
    { id: "sub-003", tugasId: "tugas-dm-01", mahasiswaId: "mhs-02", fileNama: "dewi-rencana-kampanye.docx", tanggalKumpul: "2025-12-10", status: "approved", nilai: 90, feedback: "Strategi konten kuat dan realistis, lanjutkan ke tahap eksekusi.", riwayatRevisi: [] },
    { id: "sub-004", tugasId: "tugas-dm-01", mahasiswaId: "mhs-05", fileNama: "rizky-rencana-kampanye-v1.docx", tanggalKumpul: "2026-06-20", status: "revision", nilai: null, feedback: "Tambahkan target KPI yang lebih terukur dan rincian anggaran per kanal.", riwayatRevisi: [{ tanggal: "2026-06-20", catatan: "Revisi pertama diminta oleh trainer." }] }
  ];

  // ---------------------------------------------------------------------
  // Attendance (Presensi) — untuk kelas offline/hybrid
  // ---------------------------------------------------------------------
  var sesiPresensi = [
    { id: "sesi-k3-1", kelasId: "kls-k3-bnsp", judul: "Sesi 1 — Regulasi & Dasar Hukum K3", tanggal: "2026-05-04", tipe: "offline" },
    { id: "sesi-k3-2", kelasId: "kls-k3-bnsp", judul: "Sesi 2 — Identifikasi Bahaya (HIRARC)", tanggal: "2026-05-11", tipe: "offline" },
    { id: "sesi-k3-3", kelasId: "kls-k3-bnsp", judul: "Sesi 3 — Simulasi Tanggap Darurat", tanggal: "2026-05-18", tipe: "offline" },
    { id: "sesi-ccna-1", kelasId: "kls-ccna", judul: "Sesi 1 — Praktik Konfigurasi Switch", tanggal: "2026-08-10", tipe: "hybrid" },
    { id: "sesi-ccna-2", kelasId: "kls-ccna", judul: "Sesi 2 — Praktik Routing Dasar", tanggal: "2026-08-17", tipe: "hybrid" }
  ];

  var presensi = [
    { id: "hdr-001", sesiId: "sesi-k3-1", mahasiswaId: "mhs-06", status: "hadir", waktuCheckIn: "08:58", metode: "qr" },
    { id: "hdr-002", sesiId: "sesi-k3-2", mahasiswaId: "mhs-06", status: "hadir", waktuCheckIn: "09:02", metode: "qr" },
    { id: "hdr-003", sesiId: "sesi-k3-3", mahasiswaId: "mhs-06", status: "hadir", waktuCheckIn: "08:55", metode: "qr" },
    { id: "hdr-004", sesiId: "sesi-k3-1", mahasiswaId: "mhs-07", status: "hadir", waktuCheckIn: "09:00", metode: "manual" },
    { id: "hdr-005", sesiId: "sesi-k3-2", mahasiswaId: "mhs-07", status: "terlambat", waktuCheckIn: "09:25", metode: "manual" },
    { id: "hdr-006", sesiId: "sesi-k3-1", mahasiswaId: "mhs-12", status: "hadir", waktuCheckIn: "08:50", metode: "qr" },
    { id: "hdr-007", sesiId: "sesi-k3-2", mahasiswaId: "mhs-12", status: "hadir", waktuCheckIn: "08:57", metode: "qr" },
    { id: "hdr-008", sesiId: "sesi-k3-3", mahasiswaId: "mhs-12", status: "hadir", waktuCheckIn: "08:59", metode: "qr" },
    { id: "hdr-009", sesiId: "sesi-k3-1", mahasiswaId: "mhs-13", status: "hadir", waktuCheckIn: "08:49", metode: "qr" },
    { id: "hdr-010", sesiId: "sesi-k3-2", mahasiswaId: "mhs-13", status: "hadir", waktuCheckIn: "08:52", metode: "qr" },
    { id: "hdr-011", sesiId: "sesi-k3-3", mahasiswaId: "mhs-13", status: "hadir", waktuCheckIn: "08:56", metode: "qr" },
    { id: "hdr-012", sesiId: "sesi-k3-1", mahasiswaId: "mhs-14", status: "hadir", waktuCheckIn: "09:01", metode: "qr" },
    { id: "hdr-013", sesiId: "sesi-k3-2", mahasiswaId: "mhs-14", status: "tidak_hadir", waktuCheckIn: null, metode: null },
    { id: "hdr-014", sesiId: "sesi-k3-1", mahasiswaId: "mhs-15", status: "hadir", waktuCheckIn: "09:05", metode: "manual" },
    { id: "hdr-015", sesiId: "sesi-k3-1", mahasiswaId: "mhs-16", status: "hadir", waktuCheckIn: "08:53", metode: "qr" },
    { id: "hdr-016", sesiId: "sesi-k3-2", mahasiswaId: "mhs-16", status: "hadir", waktuCheckIn: "08:58", metode: "qr" },
    { id: "hdr-017", sesiId: "sesi-k3-3", mahasiswaId: "mhs-16", status: "hadir", waktuCheckIn: "08:54", metode: "qr" },
    { id: "hdr-018", sesiId: "sesi-ccna-1", mahasiswaId: "mhs-03", status: "hadir", waktuCheckIn: "13:00", metode: "qr" },
    { id: "hdr-019", sesiId: "sesi-ccna-2", mahasiswaId: "mhs-03", status: "hadir", waktuCheckIn: "13:03", metode: "qr" },
    { id: "hdr-020", sesiId: "sesi-ccna-1", mahasiswaId: "mhs-01", status: "tidak_hadir", waktuCheckIn: null, metode: null }
  ];

  // ---------------------------------------------------------------------
  // Live Class
  // ---------------------------------------------------------------------
  var liveClass = [
    { id: "live-az-1", kelasId: "kls-az-900", judul: "Sesi Tanya Jawab: Azure Fundamentals", tanggal: "2026-09-10", waktu: "19:00 - 20:30 WIB", platform: "Microsoft Teams", link: "https://teams.microsoft.com/meet/stu-lms-az900-demo", status: "akan_datang", rekaman: null },
    { id: "live-ccna-1", kelasId: "kls-ccna", judul: "Praktik Konfigurasi Switch (Live)", tanggal: "2026-08-10", waktu: "13:00 - 15:00 WIB", platform: "Zoom", link: "https://zoom.us/j/stu-lms-ccna-demo", status: "selesai", rekaman: "https://stu-lms-demo.local/rekaman/ccna-sesi1.mp4" },
    { id: "live-ccna-2", kelasId: "kls-ccna", judul: "Praktik Routing Dasar (Live)", tanggal: "2026-09-14", waktu: "13:00 - 15:00 WIB", platform: "Zoom", link: "https://zoom.us/j/stu-lms-ccna-demo-2", status: "akan_datang", rekaman: null },
    { id: "live-k3-1", kelasId: "kls-k3-bnsp", judul: "Simulasi Tanggap Darurat (Live)", tanggal: "2026-09-08", waktu: "08:30 - 11:30 WIB", platform: "Zoom", link: "https://zoom.us/j/stu-lms-k3-demo", status: "akan_datang", rekaman: null }
  ];

  // ---------------------------------------------------------------------
  // Gamification — Badge, Poin, Leaderboard
  // ---------------------------------------------------------------------
  var lencana = [
    { id: "b1", nama: "Langkah Pertama", ikon: "🚀", deskripsi: "Menyelesaikan modul pertama pada sebuah pelatihan." },
    { id: "b2", nama: "Juara Kuis", ikon: "🏆", deskripsi: "Meraih skor kuis akhir 90 atau lebih." },
    { id: "b3", nama: "Rajin Hadir", ikon: "📌", deskripsi: "Kehadiran sesi tatap muka/live 100%." },
    { id: "b4", nama: "Lulus Tepat Waktu", ikon: "⚡", deskripsi: "Lulus pelatihan dalam waktu kurang dari 45 hari sejak pendaftaran." },
    { id: "b5", nama: "Kolektor Sertifikat", ikon: "🎖️", deskripsi: "Memiliki dua atau lebih sertifikat berstatus aktif." }
  ];

  var pencapaianPeserta = [
    { mahasiswaId: "mhs-01", poin: 480, lencanaIds: ["b1", "b2"] },
    { mahasiswaId: "mhs-02", poin: 620, lencanaIds: ["b1", "b2", "b4"] },
    { mahasiswaId: "mhs-03", poin: 550, lencanaIds: ["b1", "b2"] },
    { mahasiswaId: "mhs-04", poin: 500, lencanaIds: ["b1", "b4"] },
    { mahasiswaId: "mhs-06", poin: 700, lencanaIds: ["b1", "b3", "b4"] },
    { mahasiswaId: "mhs-08", poin: 410, lencanaIds: ["b1"] },
    { mahasiswaId: "mhs-11", poin: 650, lencanaIds: ["b1", "b2", "b4"] },
    { mahasiswaId: "mhs-12", poin: 590, lencanaIds: ["b1", "b3"] },
    { mahasiswaId: "mhs-13", poin: 430, lencanaIds: ["b1", "b3"] }
  ];

  // ---------------------------------------------------------------------
  // Discussion / Community — forum per kelas
  // ---------------------------------------------------------------------
  var diskusiThread = [
    { id: "thr-01", kelasId: "kls-aws-cp", judul: "Bedanya EC2 vs Lambda kapan pakai yang mana?", penulisId: "mhs-01", penulisPeran: "peserta", waktu: "3 hari lalu", dilaporkan: false },
    { id: "thr-02", kelasId: "kls-aws-cp", judul: "Rekomendasi urutan belajar sebelum ujian akhir", penulisId: "mhs-11", penulisPeran: "peserta", waktu: "1 minggu lalu", dilaporkan: false },
    { id: "thr-03", kelasId: "kls-jwd-bnsp", judul: "Tugas landing page boleh pakai framework CSS?", penulisId: "mhs-01", penulisPeran: "peserta", waktu: "2 hari lalu", dilaporkan: false },
    { id: "thr-04", kelasId: "kls-k3-bnsp", judul: "Materi simulasi tanggap darurat wajib hadir offline?", penulisId: "mhs-13", penulisPeran: "peserta", waktu: "5 hari lalu", dilaporkan: false }
  ];

  var diskusiKomentar = [
    { id: "kom-01", threadId: "thr-01", penulisId: "ins-01", penulisPeran: "trainer", isi: "Lambda cocok untuk beban kerja event-driven yang singkat; EC2 lebih cocok untuk aplikasi jangka panjang yang butuh kontrol penuh atas server.", waktu: "3 hari lalu", suka: 4 },
    { id: "kom-02", threadId: "thr-01", penulisId: "mhs-08", penulisPeran: "peserta", isi: "Terima kasih Pak, jadi lebih jelas!", waktu: "2 hari lalu", suka: 1 },
    { id: "kom-03", threadId: "thr-02", penulisId: "ins-01", penulisPeran: "trainer", isi: "Urutan yang disarankan: modul 1-2 dulu (konsep), lalu 3-4 (layanan & biaya), baru kerjakan kuis tiap modul sebelum ke Ujian Akhir.", waktu: "6 hari lalu", suka: 6 },
    { id: "kom-04", threadId: "thr-03", penulisId: "ins-05", penulisPeran: "trainer", isi: "Boleh, selama struktur HTML dasarnya tetap Anda tulis sendiri (bukan hasil generate penuh).", waktu: "1 hari lalu", suka: 2 },
    { id: "kom-05", threadId: "thr-04", penulisId: "ins-04", penulisPeran: "trainer", isi: "Untuk sesi simulasi tanggap darurat wajib hadir langsung karena melibatkan praktik fisik. Sesi lain bisa hybrid.", waktu: "4 hari lalu", suka: 3 }
  ];

  // ---------------------------------------------------------------------
  // Notification Center (khusus mhs-01 — akun demo peserta)
  // ---------------------------------------------------------------------
  var notifikasiPeserta = [
    { id: "np-01", mahasiswaId: "mhs-01", kategori: "registrasi", judul: "Selamat datang di STU LMS", isi: "Akun Anda berhasil terverifikasi. Mulai jelajahi katalog pelatihan sekarang.", waktu: "12 Januari 2026", dibaca: true },
    { id: "np-02", mahasiswaId: "mhs-01", kategori: "enrollment", judul: "Pendaftaran AWS Cloud Practitioner dikonfirmasi", isi: "Anda resmi terdaftar pada Batch Genap 2026.", waktu: "12 Januari 2026", dibaca: true },
    { id: "np-03", mahasiswaId: "mhs-01", kategori: "sertifikat", judul: "Sertifikat AWS Cloud Practitioner terbit", isi: "Sertifikat Anda sudah dapat diunduh pada menu Sertifikat Saya.", waktu: "22 Februari 2026", dibaca: true },
    { id: "np-04", mahasiswaId: "mhs-01", kategori: "jadwal", judul: "Modul baru tersedia: Junior Web Developer", isi: "Modul 'Dasar Pemrograman JavaScript' sudah dapat diakses.", waktu: "5 hari lalu", dibaca: false },
    { id: "np-05", mahasiswaId: "mhs-01", kategori: "reminder_tugas", judul: "Tenggat Tugas Landing Page: 3 hari lagi", isi: "Segera kumpulkan tugas 'Membangun Landing Page Responsif' sebelum 20 September 2026.", waktu: "2 hari lalu", dibaca: false },
    { id: "np-06", mahasiswaId: "mhs-01", kategori: "reminder_ujian", judul: "Batas akhir pengisian kuis CCNA: 10 hari lagi", isi: "Selesaikan seluruh modul sebelum mengerjakan kuis akhir CCNA.", waktu: "1 minggu lalu", dibaca: false },
    { id: "np-07", mahasiswaId: "mhs-01", kategori: "pencapaian", judul: "Lencana baru: Juara Kuis 🏆", isi: "Selamat! Anda meraih skor 90 pada kuis AWS Cloud Practitioner.", waktu: "22 Februari 2026", dibaca: true },
    { id: "np-08", mahasiswaId: "mhs-01", kategori: "live_class", judul: "Live Class akan datang: Sesi Tanya Jawab Azure", isi: "10 September 2026, 19:00 WIB via Microsoft Teams.", waktu: "1 hari lalu", dibaca: false }
  ];

  // ---------------------------------------------------------------------
  // Payment & E-Commerce
  // ---------------------------------------------------------------------
  var kupon = [
    { id: "kupon-01", kode: "BELAJAR20", diskonPersen: 20, diskonNominal: null, berlakuHingga: "2026-12-31", kuotaSisa: 50 },
    { id: "kupon-02", kode: "HEMAT50K", diskonPersen: null, diskonNominal: 50000, berlakuHingga: "2026-10-31", kuotaSisa: 12 },
    { id: "kupon-03", kode: "EXPIRED10", diskonPersen: 10, diskonNominal: null, berlakuHingga: "2026-01-01", kuotaSisa: 0 }
  ];

  var transaksi = [
    { id: "tx-001", nomorInvoice: "INV/2025/10/0041", mahasiswaId: "mhs-04", skemaId: "capm", jumlah: 350000, metode: "Virtual Account BCA", status: "lunas", tanggal: "2025-10-28", kuponDipakai: null },
    { id: "tx-002", nomorInvoice: "INV/2025/11/0007", mahasiswaId: "mhs-02", skemaId: "dm-bnsp", jumlah: 250000, metode: "QRIS", status: "lunas", tanggal: "2025-11-04", kuponDipakai: null },
    { id: "tx-003", nomorInvoice: "INV/2026/06/0018", mahasiswaId: "mhs-05", skemaId: "dm-bnsp", jumlah: 250000, metode: "E-Wallet (OVO)", status: "menunggu", tanggal: "2026-06-01", kuponDipakai: null },
    { id: "tx-004", nomorInvoice: "INV/2026/07/0055", mahasiswaId: "mhs-09", skemaId: "capm", jumlah: 350000, metode: "Virtual Account Mandiri", status: "gagal", tanggal: "2026-07-10", kuponDipakai: null },
    { id: "tx-005", nomorInvoice: "INV/2026/03/0002", mahasiswaId: "mhs-07", skemaId: "dm-bnsp", jumlah: 250000, metode: "QRIS", status: "refund", tanggal: "2026-03-15", kuponDipakai: null, catatanRefund: "Peserta mengundurkan diri sebelum kelas dimulai." }
  ];

  // ---------------------------------------------------------------------
  // Certificate Template Management
  // ---------------------------------------------------------------------
  var templateSertifikat = [
    { id: "tpl-01", nama: "Template Internasional — Navy Elegant", kategori: "internasional", versi: "v2.1", statusAktif: true, deskripsi: "Latar navy-teal, QR pojok kanan bawah, tanda tangan Super Admin." },
    { id: "tpl-02", nama: "Template BNSP — Formal SKKNI", kategori: "bnsp", versi: "v1.4", statusAktif: true, deskripsi: "Mengikuti tata letak formal LSP dengan nomor pelatihan & logo BNSP." },
    { id: "tpl-03", nama: "Template Internasional — Classic (Lama)", kategori: "internasional", versi: "v1.0", statusAktif: false, deskripsi: "Versi lama, digantikan v2.1 sejak Januari 2026." }
  ];

  // ---------------------------------------------------------------------
  // Audit Trail
  // ---------------------------------------------------------------------
  var auditLog = [
    { id: "log-01", waktu: "2026-09-01 08:12", aktor: "Dewi Anggraini", peran: "Admin", aksi: "login", objek: "Sesi Admin", detail: "Login berhasil dari 118.99.x.x" },
    { id: "log-02", waktu: "2026-09-01 08:20", aktor: "Dewi Anggraini", peran: "Admin", aksi: "sertifikat_terbit", objek: "BNSP/K3U/PMB/2026/00088", detail: "Menyetujui kelulusan Wahyu Saputra (K3 Umum)." },
    { id: "log-03", waktu: "2026-08-30 14:05", aktor: "Dr. Andi Wijaya", peran: "Trainer", aksi: "materi_ditambahkan", objek: "kls-aws-cp", detail: "Menambahkan materi baru pada kelas AWS Cloud Practitioner." },
    { id: "log-04", waktu: "2026-08-28 10:41", aktor: "Dewi Anggraini", peran: "Admin", aksi: "pengguna_dibuat", objek: "mhs-16", detail: "Menambahkan peserta korporat baru: Eka Purnamasari (PT Maju Bersama Indonesia)." },
    { id: "log-05", waktu: "2026-08-25 09:15", aktor: "Sistem", peran: "System", aksi: "sertifikat_dicabut", objek: "BNSP/K3U/ITC/2025/00071", detail: "Sertifikat dicabut mengikuti keputusan audit internal LSP." },
    { id: "log-06", waktu: "2026-08-20 16:30", aktor: "Dewi Anggraini", peran: "Admin", aksi: "kupon_dibuat", objek: "HEMAT50K", detail: "Membuat kupon diskon Rp50.000 berlaku hingga 31 Okt 2026." },
    { id: "log-07", waktu: "2026-08-18 11:02", aktor: "Siti Rahmawati", peran: "Trainer", aksi: "nilai_diubah", objek: "sub-004", detail: "Meminta revisi tugas Rencana Kampanye milik Rizky Ramadhan." },
    { id: "log-08", waktu: "2026-08-15 13:47", aktor: "Dewi Anggraini", peran: "Admin", aksi: "pelatihan_dipublikasikan", objek: "jna-bnsp", detail: "Mengubah status Junior Network Administrator menjadi draft untuk revisi kurikulum." },
    { id: "log-09", waktu: "2026-08-10 08:00", aktor: "Bambang Kusuma", peran: "Trainer", aksi: "presensi_dicatat", objek: "sesi-ccna-1", detail: "Mencatat kehadiran sesi praktik konfigurasi switch." },
    { id: "log-10", waktu: "2026-08-05 19:22", aktor: "Raka Prasetya", peran: "Peserta", aksi: "login", objek: "Sesi Peserta", detail: "Login berhasil dari perangkat baru." },
    { id: "log-11", waktu: "2026-07-30 09:10", aktor: "Dewi Anggraini", peran: "Admin", aksi: "pengaturan_diubah", objek: "Integrasi API", detail: "Menonaktifkan API key 'Uji Coba Vendor Lama'." },
    { id: "log-12", waktu: "2026-07-22 15:55", aktor: "Sistem", peran: "System", aksi: "pembayaran_gagal", objek: "INV/2026/07/0055", detail: "Transaksi Virtual Account Mandiri gagal (kedaluwarsa)." },
    { id: "log-13", waktu: "2026-07-10 12:00", aktor: "Ir. Yulianti Puspasari", peran: "Trainer", aksi: "jadwal_live_class", objek: "live-k3-1", detail: "Menjadwalkan sesi live 'Simulasi Tanggap Darurat'." },
    { id: "log-14", waktu: "2026-06-15 10:30", aktor: "Dewi Anggraini", peran: "Admin", aksi: "peran_diubah", objek: "adm-01", detail: "Memperbarui hak akses Super Admin lintas organisasi." },
    { id: "log-15", waktu: "2026-06-01 08:00", aktor: "Hendra Saputra", peran: "Admin Korporat", aksi: "pendaftaran_massal", objek: "PT Maju Bersama Indonesia", detail: "Mendaftarkan 4 karyawan ke pelatihan Ahli K3 Umum." }
  ];

  // ---------------------------------------------------------------------
  // Data Privacy — permintaan ekspor/penghapusan data
  // ---------------------------------------------------------------------
  var permintaanPrivasi = [
    { id: "priv-01", mahasiswaId: "mhs-09", tipe: "ekspor_data", tanggal: "2026-07-01", status: "selesai" },
    { id: "priv-02", mahasiswaId: "mhs-05", tipe: "hapus_akun", tanggal: "2026-08-20", status: "menunggu" }
  ];

  // ---------------------------------------------------------------------
  // API & Integration
  // ---------------------------------------------------------------------
  var apiKeys = [
    { id: "key-01", label: "Portal HRIS PT Maju Bersama", keyMasked: "sk_live_4f2a••••••••c91d", dibuat: "2026-06-01", statusAktif: true },
    { id: "key-02", label: "Integrasi Verifikasi Internal QHSE", keyMasked: "sk_live_88bd••••••••7a02", dibuat: "2026-02-14", statusAktif: true },
    { id: "key-03", label: "Uji Coba Vendor Lama", keyMasked: "sk_test_11aa••••••••0099", dibuat: "2025-05-10", statusAktif: false }
  ];

  var integrasi = [
    { id: "int-payment", nama: "Payment Gateway", penyedia: "Midtrans (contoh)", aktif: true },
    { id: "int-email", nama: "Email Transaksional", penyedia: "SMTP / SendGrid (contoh)", aktif: true },
    { id: "int-wa", nama: "WhatsApp Notification", penyedia: "Provider WA Business API (contoh)", aktif: false },
    { id: "int-meet", nama: "Zoom / Google Meet / MS Teams", penyedia: "Konfigurasi per kelas", aktif: true },
    { id: "int-sso", nama: "SSO / OAuth", penyedia: "Google OAuth2 (contoh)", aktif: false },
    { id: "int-hr", nama: "HR System Korporat", penyedia: "PT Maju Bersama Indonesia HRIS", aktif: true }
  ];

  // ---------------------------------------------------------------------
  // CMS Landing Page (default — dapat diubah admin lewat Pengaturan)
  // ---------------------------------------------------------------------
  var cmsKontenDefault = {
    heroBadge: "Purwarupa UI/UX · Data Dummy",
    heroJudul: "Satu Platform untuk Pelatihan &amp; Sertifikasi <span class=\"text-accent-400\">Internasional</span> &amp; <span class=\"text-accent-400\">BNSP</span>",
    heroSubjudul: "STU LMS membantu peserta memilih program pelatihan, belajar terarah lewat modul &amp; kuis, hingga mengunduh sertifikat resmi yang dapat divalidasi publik.",
    faq: [
      { q: "Apakah semua pelatihan berbayar?", a: "Tidak. Sebagian besar pelatihan gratis melalui kerja sama institusi/organisasi, dan sebagian bersifat berbayar mandiri dengan harga yang tertera pada katalog." },
      { q: "Apakah sertifikat dapat diverifikasi pihak lain?", a: "Ya. Setiap sertifikat memiliki nomor unik dan QR Code yang dapat diverifikasi publik tanpa perlu login." },
      { q: "Apakah organisasi/perusahaan bisa mendaftarkan banyak peserta sekaligus?", a: "Bisa, melalui modul Corporate Training — admin organisasi dapat mendaftarkan peserta secara massal dan memantau progres tim." },
      { q: "Bagaimana jika saya kehilangan akses akun?", a: "Gunakan menu Lupa Kata Sandi pada halaman masuk, atau hubungi admin organisasi/kampus Anda." }
    ],
    testimoni: [
      { nama: "Salsabila Zahra", peran: "Lulusan Junior Web Developer (BNSP)", isi: "Materinya runtut dan sertifikatnya bisa langsung dicek perusahaan tempat saya melamar kerja." },
      { nama: "Hendra Saputra", peran: "HR Learning & Development, PT Maju Bersama Indonesia", isi: "Fitur pendaftaran massal sangat membantu kami melatih puluhan karyawan sekaligus dan memantau progresnya dari satu dashboard." },
      { nama: "Dr. Andi Wijaya", peran: "Trainer Cloud Computing", isi: "Sebagai trainer, saya bisa mengelola materi, kuis, dan penilaian tugas dalam satu tempat tanpa ribet." }
    ]
  };

  global.LMS_DATA = {
    tahunSekarang: TAHUN_INI,
    organisasi: organisasi,
    pelatihan: pelatihan,
    trainer: trainer,
    kelas: kelas,
    peserta: peserta,
    pendaftaran: pendaftaran,
    sertifikat: sertifikat,
    notifikasi: notifikasi,
    admin: admin,
    akunDemo: akunDemo,
    tugas: tugas,
    pengumpulanTugas: pengumpulanTugas,
    sesiPresensi: sesiPresensi,
    presensi: presensi,
    liveClass: liveClass,
    lencana: lencana,
    pencapaianPeserta: pencapaianPeserta,
    diskusiThread: diskusiThread,
    diskusiKomentar: diskusiKomentar,
    notifikasiPeserta: notifikasiPeserta,
    kupon: kupon,
    transaksi: transaksi,
    templateSertifikat: templateSertifikat,
    auditLog: auditLog,
    permintaanPrivasi: permintaanPrivasi,
    apiKeys: apiKeys,
    integrasi: integrasi,
    cmsKontenDefault: cmsKontenDefault
  };
})(window);
