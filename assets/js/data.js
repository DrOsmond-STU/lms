/**
 * STU LMS — Data dummy (purwarupa)
 * Semua data di file ini adalah data contoh (mock) untuk kebutuhan demo UI/UX.
 * Tidak terhubung ke backend/database sungguhan.
 */
(function (global) {
  "use strict";

  var TAHUN_INI = 2026;

  var universitas = [
    { id: "stu", nama: "Universitas Semesta Teknologi Utama", singkatan: "USTU", kota: "Jakarta", akreditasi: "A" },
    { id: "unm", nama: "Universitas Nusantara Mandiri", singkatan: "UNM", kota: "Bandung", akreditasi: "A" },
    { id: "itc", nama: "Institut Teknologi Cendekia", singkatan: "ITC", kota: "Surabaya", akreditasi: "B" },
    { id: "pgm", nama: "Politeknik Graha Mandiri", singkatan: "PGM", kota: "Yogyakarta", akreditasi: "B" }
  ];

  // ---------------------------------------------------------------------
  // Skema sertifikasi
  // ---------------------------------------------------------------------
  var skema = [
    {
      id: "aws-cp", kategori: "internasional", nama: "AWS Certified Cloud Practitioner",
      penyelenggara: "Amazon Web Services (AWS)", kodeSkema: "AWS-CCP", level: "Fundamental",
      durasiJam: 24, biaya: 0, skorMinimal: 70,
      deskripsi: "Memahami konsep dasar layanan cloud AWS, model keamanan, dan skema biaya untuk memulai karier di bidang cloud computing.",
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
      deskripsi: "Skema sertifikasi kompetensi berbasis SKKNI untuk pengembang web pemula: HTML/CSS, dasar pemrograman, dan basis data sederhana.",
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

  // ---------------------------------------------------------------------
  // Instruktur
  // ---------------------------------------------------------------------
  var instruktur = [
    { id: "ins-01", nama: "Dr. Andi Wijaya, S.Kom., M.T.", universitasId: "stu", keahlian: "Cloud Computing & Infrastruktur", email: "andi.wijaya@stu.ac.id", mengampu: ["aws-cp", "az-900"] },
    { id: "ins-02", nama: "Siti Rahmawati, S.T., M.M.", universitasId: "unm", keahlian: "Pemasaran Digital & Manajemen Proyek", email: "siti.rahmawati@unm.ac.id", mengampu: ["dm-bnsp", "capm"] },
    { id: "ins-03", nama: "Bambang Kusuma, S.T.", universitasId: "stu", keahlian: "Jaringan & Keamanan Siber", email: "bambang.kusuma@stu.ac.id", mengampu: ["ccna", "jna-bnsp"] },
    { id: "ins-04", nama: "Ir. Yulianti Puspasari, M.T., CSP", universitasId: "itc", keahlian: "K3 & QHSE", email: "yulianti.p@itc.ac.id", mengampu: ["k3-bnsp"] },
    { id: "ins-05", nama: "Fajar Nugroho, S.Kom.", universitasId: "pgm", keahlian: "Pengembangan Web", email: "fajar.nugroho@pgm.ac.id", mengampu: ["jwd-bnsp"] }
  ];

  // ---------------------------------------------------------------------
  // Kelas — satu kelas/batch aktif per skema
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
      id: "kls-aws-cp", skemaId: "aws-cp", instrukturId: "ins-01", batch: "Batch Genap 2026", kuota: 40,
      modul: modulStandar("aws", [
        { judul: "Pengantar Cloud Computing & AWS", tipe: "video", durasi: "45 menit" },
        { judul: "Model Keamanan & Tanggung Jawab Bersama", tipe: "video", durasi: "40 menit" },
        { judul: "Layanan Inti AWS (Compute, Storage, Database)", tipe: "pdf", durasi: "18 halaman" },
        { judul: "Model Biaya & Dukungan AWS", tipe: "pdf", durasi: "12 halaman" },
        { judul: "Kuis Akhir: AWS Cloud Practitioner", tipe: "quiz", durasi: "5 soal" }
      ]),
      kuis: [
        { soal: "Model layanan cloud yang menyediakan infrastruktur (server, storage, jaringan) tanpa mahasiswa perlu mengelola perangkat fisik disebut?", opsi: ["SaaS", "IaaS", "PaaS", "On-Premise"], jawaban: 1 },
        { soal: "Dalam model Shared Responsibility AWS, siapa yang bertanggung jawab atas keamanan 'di dalam' cloud (data & konfigurasi aplikasi)?", opsi: ["AWS sepenuhnya", "Pelanggan (customer)", "Pemerintah", "Tidak ada pihak yang bertanggung jawab"], jawaban: 1 },
        { soal: "Layanan AWS untuk penyimpanan objek (object storage) adalah?", opsi: ["EC2", "RDS", "S3", "VPC"], jawaban: 2 },
        { soal: "Manfaat utama elastisitas (elasticity) pada cloud computing adalah?", opsi: ["Biaya tetap tanpa perubahan", "Sumber daya menyesuaikan otomatis sesuai kebutuhan", "Data selalu tersimpan permanen", "Tidak memerlukan koneksi internet"], jawaban: 1 },
        { soal: "AWS Free Tier bertujuan untuk?", opsi: ["Menghapus tagihan pengguna lama", "Memberi akses uji coba layanan dengan batas gratis tertentu", "Menggantikan seluruh biaya produksi", "Hanya berlaku untuk pemerintah"], jawaban: 1 }
      ]
    },
    {
      id: "kls-az-900", skemaId: "az-900", instrukturId: "ins-01", batch: "Batch Genap 2026", kuota: 35,
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
      id: "kls-ccna", skemaId: "ccna", instrukturId: "ins-03", batch: "Batch Genap 2026", kuota: 30,
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
      id: "kls-capm", skemaId: "capm", instrukturId: "ins-02", batch: "Batch Genap 2026", kuota: 30,
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
      id: "kls-jwd-bnsp", skemaId: "jwd-bnsp", instrukturId: "ins-05", batch: "Batch Genap 2026", kuota: 32,
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
      id: "kls-dm-bnsp", skemaId: "dm-bnsp", instrukturId: "ins-02", batch: "Batch Genap 2026", kuota: 32,
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
      id: "kls-k3-bnsp", skemaId: "k3-bnsp", instrukturId: "ins-04", batch: "Batch Genap 2026", kuota: 28,
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
      id: "kls-jna-bnsp", skemaId: "jna-bnsp", instrukturId: "ins-03", batch: "Batch Genap 2026", kuota: 30,
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

  // ---------------------------------------------------------------------
  // Mahasiswa (dummy) — mhs-01 adalah akun demo login "Mahasiswa"
  // ---------------------------------------------------------------------
  var mahasiswa = [
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
    { id: "mhs-12", nama: "Larasati Wulandari", npm: "2020045011", universitasId: "itc", prodi: "Teknik Industri", semester: 9, email: "larasati.w@itc.ac.id" }
  ];

  // ---------------------------------------------------------------------
  // Pendaftaran / enrolments
  // status: 'terdaftar' | 'berjalan' | 'menunggu_approval' | 'lulus' | 'tidak_lulus'
  // ---------------------------------------------------------------------
  var pendaftaran = [
    // Raka Prasetya (mhs-01) — akun demo mahasiswa: 1 lulus, 1 berjalan, 1 terdaftar
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
    { id: "pd-016", mahasiswaId: "mhs-12", skemaId: "k3-bnsp", status: "lulus", progres: 100, skorKuis: 87, tanggalDaftar: "2025-06-01", tanggalSelesai: "2025-07-30", modulSelesai: ["k3-m1", "k3-m2", "k3-m3", "k3-m4", "k3-m5"] }
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
    { id: "cert-008", nomor: "BNSP/K3U/ITC/2025/00071", mahasiswaId: "mhs-12", skemaId: "k3-bnsp", tanggalTerbit: "2025-08-02", berlakuHingga: "2028-08-02", status: "dicabut", catatanPencabutan: "Ditemukan pelanggaran integritas ujian pada audit internal LSP." }
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
    mahasiswa: { userId: "mhs-01", role: "mahasiswa" },
    instruktur: { userId: "ins-01", role: "instruktur" },
    admin: { userId: "adm-01", role: "admin" }
  };

  var admin = [
    { id: "adm-01", nama: "Dewi Anggraini", jabatan: "Super Admin LMS", email: "dewi.anggraini@semestateknologiutama.com" }
  ];

  global.LMS_DATA = {
    tahunSekarang: TAHUN_INI,
    universitas: universitas,
    skema: skema,
    instruktur: instruktur,
    kelas: kelas,
    mahasiswa: mahasiswa,
    pendaftaran: pendaftaran,
    sertifikat: sertifikat,
    notifikasi: notifikasi,
    admin: admin,
    akunDemo: akunDemo
  };
})(window);
