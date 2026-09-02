/**
 * STU LMS — validasi sertifikat & pembuatan file PDF sertifikat (dummy) + QR.
 * Memakai jsPDF & qrcodejs (CDN) sepenuhnya di sisi klien (tanpa server).
 */
(function (global) {
  "use strict";

  function statusEfektif(cert) {
    if (cert.status === "dicabut") return "dicabut";
    var hariIni = new Date().toISOString().slice(0, 10);
    if (cert.berlakuHingga && cert.berlakuHingga < hariIni) return "kedaluwarsa";
    return "aktif";
  }

  function cariSertifikat(nomorInput) {
    if (!nomorInput) return null;
    var norm = nomorInput.trim().toUpperCase();
    var found = Store.getSertifikat().filter(function (c) {
      return c.nomor.toUpperCase() === norm;
    })[0];
    if (!found) return null;
    return lengkapi(found);
  }

  function lengkapi(cert) {
    var mhs = Store.mahasiswaById(cert.mahasiswaId);
    var skema = Store.skemaById(cert.skemaId);
    var univ = mhs ? Store.universitasById(mhs.universitasId) : null;
    return {
      cert: cert,
      mahasiswa: mhs,
      skema: skema,
      universitas: univ,
      statusEfektif: statusEfektif(cert)
    };
  }

  function urlValidasi(nomor) {
    var loc = window.location;
    var path = loc.pathname.replace(/\/(mahasiswa|instruktur|admin)\/.*$/, "/");
    return loc.origin + path.replace(/\/[^/]*$/, "/") + "cek-sertifikat.html?no=" + encodeURIComponent(nomor);
  }

  function buatQRDataURL(teks, ukuran) {
    return new Promise(function (resolve) {
      var holder = document.createElement("div");
      holder.style.display = "none";
      document.body.appendChild(holder);
      /* eslint-disable no-undef */
      new QRCode(holder, { text: teks, width: ukuran || 220, height: ukuran || 220, correctLevel: QRCode.CorrectLevel.M });
      /* eslint-enable no-undef */
      setTimeout(function () {
        var canvas = holder.querySelector("canvas");
        var dataUrl = canvas ? canvas.toDataURL("image/png") : (holder.querySelector("img") || {}).src;
        holder.remove();
        resolve(dataUrl);
      }, 150);
    });
  }

  function unduhSertifikatPDF(lengkap) {
    var cert = lengkap.cert, mhs = lengkap.mahasiswa, skema = lengkap.skema, univ = lengkap.universitas;
    var linkQR = urlValidasi(cert.nomor);

    buatQRDataURL(linkQR, 260).then(function (qrDataUrl) {
      var jsPDFCtor = window.jspdf.jsPDF;
      var doc = new jsPDFCtor({ orientation: "landscape", unit: "mm", format: "a4" });
      var W = doc.internal.pageSize.getWidth();
      var H = doc.internal.pageSize.getHeight();

      // Latar & bingkai
      doc.setFillColor(11, 47, 82);
      doc.rect(0, 0, W, H, "F");
      doc.setFillColor(255, 255, 255);
      doc.roundedRect(8, 8, W - 16, H - 16, 3, 3, "F");
      doc.setDrawColor(20, 184, 166);
      doc.setLineWidth(1.1);
      doc.roundedRect(12, 12, W - 24, H - 24, 2, 2, "S");

      // Header
      doc.setTextColor(11, 47, 82);
      doc.setFont("helvetica", "bold");
      doc.setFontSize(13);
      doc.text("STU LMS", W / 2, 24, { align: "center" });
      doc.setFont("helvetica", "normal");
      doc.setFontSize(9);
      doc.setTextColor(100, 116, 139);
      doc.text("Platform Pembelajaran & Sertifikasi Mahasiswa", W / 2, 29, { align: "center" });

      doc.setDrawColor(226, 232, 240);
      doc.line(60, 34, W - 60, 34);

      doc.setFont("helvetica", "bold");
      doc.setFontSize(22);
      doc.setTextColor(11, 47, 82);
      doc.text(skema && skema.kategori === "internasional" ? "SERTIFIKAT KOMPETENSI INTERNASIONAL" : "SERTIFIKAT KOMPETENSI BNSP", W / 2, 46, { align: "center" });

      doc.setFont("helvetica", "normal");
      doc.setFontSize(11);
      doc.setTextColor(71, 85, 105);
      doc.text("Dengan ini menyatakan bahwa:", W / 2, 58, { align: "center" });

      doc.setFont("helvetica", "bold");
      doc.setFontSize(24);
      doc.setTextColor(20, 100, 90);
      doc.text((mhs ? mhs.nama : "-").toUpperCase(), W / 2, 70, { align: "center" });

      doc.setFont("helvetica", "normal");
      doc.setFontSize(10.5);
      doc.setTextColor(100, 116, 139);
      var subLine = (mhs ? "NPM " + mhs.npm : "") + (univ ? "  ·  " + univ.nama : "");
      doc.text(subLine, W / 2, 76, { align: "center" });

      doc.setFontSize(11);
      doc.setTextColor(71, 85, 105);
      doc.text("telah dinyatakan LULUS dan berhak menyandang skema sertifikasi:", W / 2, 87, { align: "center" });

      doc.setFont("helvetica", "bold");
      doc.setFontSize(15);
      doc.setTextColor(11, 47, 82);
      doc.text(skema ? skema.nama : "-", W / 2, 96, { align: "center" });

      doc.setFont("helvetica", "italic");
      doc.setFontSize(10);
      doc.setTextColor(100, 116, 139);
      doc.text("Diselenggarakan oleh " + (skema ? skema.penyelenggara : "-"), W / 2, 102, { align: "center" });

      // Footer info kiri
      doc.setFont("helvetica", "normal");
      doc.setFontSize(9);
      doc.setTextColor(71, 85, 105);
      var kiriY = H - 34;
      doc.text("Nomor Sertifikat  : " + cert.nomor, 24, kiriY);
      doc.text("Tanggal Terbit     : " + UI.formatTanggal(cert.tanggalTerbit), 24, kiriY + 5);
      doc.text("Berlaku Hingga    : " + UI.formatTanggal(cert.berlakuHingga), 24, kiriY + 10);

      doc.setDrawColor(148, 163, 184);
      doc.line(24, H - 16, 74, H - 16);
      doc.setFontSize(8.5);
      doc.text("Dewi Anggraini", 24, H - 12);
      doc.setTextColor(148, 163, 184);
      doc.text("Super Admin · STU LMS", 24, H - 8.5);

      // QR kanan
      if (qrDataUrl) {
        doc.addImage(qrDataUrl, "PNG", W - 55, H - 55, 28, 28);
      }
      doc.setFontSize(7.5);
      doc.setTextColor(100, 116, 139);
      doc.text("Pindai untuk verifikasi keaslian", W - 41, H - 24, { align: "center" });

      doc.save("Sertifikat-" + cert.nomor.replace(/\//g, "-") + ".pdf");
    });
  }

  global.Certificate = {
    cari: cariSertifikat,
    lengkapi: lengkapi,
    statusEfektif: statusEfektif,
    urlValidasi: urlValidasi,
    unduhPDF: unduhSertifikatPDF,
    buatQRDataURL: buatQRDataURL
  };
})(window);
