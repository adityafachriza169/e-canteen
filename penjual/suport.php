<?php
// =============================================
// SESSION & AUTH CHECK
// =============================================
session_start();


if (!isset($_SESSION['username']) || (int)($_SESSION['role'] ?? 0) !== 1) {
    header('Location: ../users/login.php');
    exit;
}

// =============================================
// KONEKSI DATABASE
// =============================================
include "../users/koneksi.php";
if (!isset($db) && isset($konek)) $db = $konek;

// =============================================
// AMBIL DATA PENJUAL DARI SESSION USERNAME
// =============================================
$ses_username = $_SESSION['username'];

$stmt_p = mysqli_prepare($db, "
    SELECT p.id_penjual, p.id_kantin, p.nama, p.username, p.profil,
           k.nama_kantin
    FROM penjual p
    LEFT JOIN kantin k ON k.id_kantin = p.id_kantin
    WHERE p.username = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt_p, "s", $ses_username);
mysqli_stmt_execute($stmt_p);
$penjual_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_p));
mysqli_stmt_close($stmt_p);

if (!$penjual_data) {
    session_destroy();
    header('Location: ../users/login.php');
    exit;
}

$nama_penjual = $penjual_data['nama']        ?? 'Penjual';
$nama_kantin  = $penjual_data['nama_kantin'] ?? 'Kantin';
$id_kantin    = (int)($penjual_data['id_kantin'] ?? 0);

// Foto profil
$foto_profil_file = $penjual_data['profil'] ?? '';
$kemungkinan_path = [
    'profil/' . $foto_profil_file,
    '../profil/' . $foto_profil_file,
    'uploads/' . $foto_profil_file,
    '../admin/uploads/' . $foto_profil_file,
];
$foto_profil = 'https://ui-avatars.com/api/?name=' . urlencode($nama_penjual) . '&background=e8650a&color=fff&size=80';
foreach ($kemungkinan_path as $_p) {
    if (!empty($foto_profil_file) && file_exists($_p)) { $foto_profil = $_p; break; }
}

// ── Jumlah pesanan pending ──
$pending_orders = 0;
{
    $__q = mysqli_prepare($db, "SELECT COUNT(*) AS jml FROM transaksi WHERE id_kantin = ? AND status = 'pending'");
    mysqli_stmt_bind_param($__q, 'i', $id_kantin);
    mysqli_stmt_execute($__q);
    $pending_orders = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($__q))['jml'];
    mysqli_stmt_close($__q);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>E-Canteen — Support</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Righteous&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="suport.css">
  <link rel="stylesheet" href="../custom_confirm.css">
</head>
<body>

  <!-- ═══════════════ TOPBAR ═══════════════ -->
  <div class="topbar">
    <div class="top-left"></div>
    <div class="top-right">
      <a href="pesanan.php"><i class="fa-regular fa-bell"></i> Notifikasi</a>
      <a href="suport.php"><i class="fa-regular fa-circle-question"></i> Bantuan</a>
      <div class="lang">
        <i class="fa-solid fa-globe"></i>
        <span>Bahasa Indonesia</span>
        <i class="fa-solid fa-chevron-down"></i>
        <div class="lang-drop">
          <a href="#" class="active"><i class="fa-solid fa-check"></i> Bahasa Indonesia</a>
          <a href="#">English</a>
        </div>
      </div>
    </div>
  </div>

  <!-- NAVBAR -->
  <div class="navbar orange-bg">
    <div class="navbar-texture"></div>
    <div class="orb1"></div>
    <div class="orb2"></div>

    <!-- Hamburger (mobile only) -->
    <button class="hamburger-btn" id="hamburgerBtn" aria-label="Buka menu">
      <i class="fa-solid fa-bars"></i>
    </button>

    <div class="logo">
      <div class="logo-img-wrap">
        <img src="../gambar/logo.png" alt="Logo">
      </div>
      <div class="logo-text">
        <span class="logo-title">E-Canteen</span>
        <span class="logo-sub">Esemkita</span>
      </div>
    </div>

    <div class="search-wrap">
      <div class="search-box">
        <input type="text" placeholder="Cari makanan dan minuman sehat...">
        <button><i class="fa-solid fa-magnifying-glass"></i></button>
      </div>
    </div>

    <div class="icon-actions">
      <button onclick="openChatPopup()" class="icon-btn chat-trigger-btn notif-chat-btn">
    <i class="fa-solid fa-comment-dots"></i>
    <span class="icon-badge" id="chatBadge" style="display:none"></span>
</button>
    </div>
  </div>

  <!-- Sidebar overlay (mobile) -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- PAGE WRAPPER -->
  <div class="page-wrapper">

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-profile">
      <div class="sidebar-avatar">
        <img src="<?= htmlspecialchars($foto_profil) ?>" alt="Avatar"
             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($nama_penjual) ?>&background=e8650a&color=fff&size=80'" />
      </div>
      <div class="sidebar-profile-info">
        <h3><?= htmlspecialchars($nama_penjual) ?></h3>
        <small style="color:#aaa;font-size:.75rem;"><?= htmlspecialchars($nama_kantin) ?></small><br>
        <a href="profil/profile.php">✏️ Ubah Profil</a>
      </div>
    </div>

      <div class="sidebar-nav-area">
        <a href="dasbord.php">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M6 19h3v-6h6v6h3v-9l-6-4.5L6 10zm-2 2V9l8-6l8 6v12h-7v-6h-2v6zm8-8.75"/></svg>
          Dashboard
        </a>
        <a href="produk.php">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M19.78 12.08v4.5H4.26v-4.51H4a6.4 6.4 0 0 1-3-.73v10.24a2 2 0 0 0 2 2h18a2 2 0 0 0 2-2V11.35a6.3 6.3 0 0 1-3 .73Zm-11 9.5a.75.75 0 0 1-1.5 0v-3a.75.75 0 0 1 1.5 0Zm8 0a.75.75 0 0 1-1.5 0v-3a.75.75 0 0 1 1.5 0Zm-3.04-21h-3.45L9.23 4.83h5.58zm9.51 4.25l-1.14-3.56a1.09 1.09 0 0 0-1-.69h-5.82l1.06 4.25Zm.48 1.5H.29L0 7.21a3 3 0 0 0 1.15 2.44l.12.1l.31.19l.13.07l.17.08H2l.16.07l.14.05l.18.06H4.2a4.13 4.13 0 0 0 3.21-1.19a.74.74 0 0 1 1.19 0c1.49 2 5.33 2 6.81 0a.74.74 0 0 1 1.19 0a4.17 4.17 0 0 0 3.24 1.5h.82a2.5 2.5 0 0 0 .38-.06h.15l.37-.11l.15-.05l.35-.16l.15-.08l.32-.2l.14-.1A3 3 0 0 0 24 7.22ZM8.74.58h-5.8a1.1 1.1 0 0 0-1 .69L.78 4.83h6.9Z"/></svg>
          Produk Saya
        </a>
        <a href="pesanan.php" style="position:relative;">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M19 4h-2V3a1 1 0 0 0-2 0v1H9V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3m1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7h16Zm0-9H4V7a1 1 0 0 1 1-1h2v1a1 1 0 0 0 2 0V6h6v1a1 1 0 0 0 2 0V6h2a1 1 0 0 1 1 1Z"/></svg>
          Pesanan Masuk
<span id="sidebarPesananBadge" style="
    position:absolute;top:50%;right:12px;transform:translateY(-50%);
    background:#ef4444;color:#fff;border-radius:10px;font-size:11px;font-weight:800;
    min-width:20px;height:20px;display:none;
    align-items:center;justify-content:center;
    padding:0 5px;border:2px solid #fff;
    box-shadow:0 2px 8px rgba(239,68,68,.45);line-height:1;">
  </span>
        </a>
        <a href="pendapatan.php">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 512 512"><path fill="currentColor" d="M32 376a56 56 0 0 0 56 56h336a56 56 0 0 0 56-56V222H32Zm66-76a30 30 0 0 1 30-30h48a30 30 0 0 1 30 30v20a30 30 0 0 1-30 30h-48a30 30 0 0 1-30-30ZM424 80H88a56 56 0 0 0-56 56v26h448v-26a56 56 0 0 0-56-56"/></svg>
          Pendapatan
        </a>
      </div>

      <div class="sidebar-divider"></div>

      <div class="sidebar-bottom">
        <a href="suport.php" class="active">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M22 17.002a6 6 0 0 1-4.713 5.86l-.638-1.914A4 4 0 0 0 19.465 19H17a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h2.938a8.001 8.001 0 0 0-15.876 0H7a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-5C2 6.477 6.477 2 12 2s10 4.477 10 10zM20 17v-4h-3v4zM4 13v4h3v-4z"/></svg>
          Support
        </a>
        <a href="pengaturan.php">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="m9.25 22l-.4-3.2q-.325-.125-.612-.3t-.563-.375L4.7 19.375l-2.75-4.75l2.575-1.95Q4.5 12.5 4.5 12.338v-.675q0-.163.025-.338L1.95 9.375l2.75-4.75l2.975 1.25q.275-.2.575-.375t.6-.3l.4-3.2h5.5l.4 3.2q.325.125.613.3t.562.375l2.975-1.25l2.75 4.75l-2.575 1.95q.025.175.025.338v.674q0 .163-.05.338l2.575 1.95l-2.75 4.75l-2.95-1.25q-.275.2-.575.375t-.6.3l-.4 3.2zM11 20h1.975l.35-2.65q.775-.2 1.438-.587t1.212-.938l2.475 1.025l.975-1.7l-2.15-1.625q.125-.35.175-.737T17.5 12t-.05-.787t-.175-.738l2.15-1.625l-.975-1.7l-2.475 1.05q-.55-.575-1.212-.962t-1.438-.588L13 4h-1.975l-.35 2.65q-.775.2-1.437.588t-1.213.937L5.55 7.15l-.975 1.7l2.15 1.6q-.125.375-.175.75t-.05.8q0 .4.05.775t.175.75l-2.15 1.625l.975 1.7l2.475-1.05q.55.575 1.213.963t1.437.587zm1.05-4.5q1.45 0 2.475-1.025T15.55 12t-1.025-2.475T12.05 8.5q-1.475 0-2.488 1.025T8.55 12t1.013 2.475T12.05 15.5M12 12"/></svg>
          Pengaturan
        </a>
      </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main">
      <div class="support-wrap">

        <!-- HEADER -->
        <div class="support-header">
          <div class="support-judul">Support</div>
          <div class="support-sub">Butuh bantuan? Kami siap membantu kamu 24 jam</div>
        </div>

        <div class="support-divider"></div>

        <!-- HUBUNGI ADMIN -->
        <div class="support-section">
          <div class="support-section-title">
            <i class="fa-solid fa-headset"></i> Hubungi Admin
          </div>
          <div class="support-section-sub">Hubungi admin sekolah langsung melalui saluran berikut:</div>

          <div class="kontak-grid">
            <div class="kontak-card" onclick="window.open('https://wa.me/6281234567890')">
              <div class="kontak-icon whatsapp">
                <i class="fa-brands fa-whatsapp"></i>
              </div>
              <div class="kontak-info">
                <div class="kontak-title">WhatsApp</div>
                <div class="kontak-sub">+62 812-3456-7890</div>
              </div>
              <i class="fa-solid fa-chevron-right kontak-arrow"></i>
            </div>

            <div class="kontak-card" onclick="window.open('mailto:admin@esemkita.sch.id')">
              <div class="kontak-icon email">
                <i class="fa-solid fa-envelope"></i>
              </div>
              <div class="kontak-info">
                <div class="kontak-title">Email</div>
                <div class="kontak-sub">admin@esemkita.sch.id</div>
              </div>
              <i class="fa-solid fa-chevron-right kontak-arrow"></i>
            </div>
          </div>
        </div>

        <div class="support-divider"></div>

        <!-- FAQ -->
        <div class="support-section">
          <div class="support-section-title">
            <i class="fa-solid fa-circle-question"></i> Pertanyaan Umum (FAQ)
          </div>
          <div class="support-section-sub">Klik pertanyaan untuk melihat jawaban</div>

          <div class="faq-list">

            <!-- 1 -->
            <div class="faq-item" onclick="toggleFaq(this)">
              <div class="faq-question">
                <span>Bagaimana cara menerima pesanan masuk?</span>
                <i class="fa-solid fa-chevron-down"></i>
              </div>
              <div class="faq-answer">
                Buka halaman <strong>Pesanan Masuk</strong>. Setiap pesanan baru akan muncul dengan status <strong>Menunggu</strong>. Klik tombol <strong>Masak</strong> untuk memulai memproses pesanan — statusnya otomatis berubah jadi <em>Dimasak</em>. Setelah makanan selesai, klik <strong>Siap</strong>, dan klik <strong>Selesai</strong> setelah pelanggan mengambil pesanan. Pastikan kamu tidak melewatkan notifikasi pesanan baru.
              </div>
            </div>

            <!-- 2 -->
            <div class="faq-item" onclick="toggleFaq(this)">
              <div class="faq-question">
                <span>Bagaimana cara menolak pesanan?</span>
                <i class="fa-solid fa-chevron-down"></i>
              </div>
              <div class="faq-answer">
                Pada pesanan dengan status <strong>Menunggu</strong>, klik tombol <strong>Tolak</strong> (warna merah). Pesanan akan langsung berstatus <em>Dibatalkan</em> dan pelanggan akan mendapat notifikasi bahwa pesanannya tidak dapat diproses. Sebaiknya tolak pesanan hanya jika bahan habis atau ada kendala mendesak, agar rating tokomu tetap baik.
              </div>
            </div>

            <!-- 3 -->
            <div class="faq-item" onclick="toggleFaq(this)">
              <div class="faq-question">
                <span>Bagaimana cara menambah atau mengubah produk?</span>
                <i class="fa-solid fa-chevron-down"></i>
              </div>
              <div class="faq-answer">
                Buka halaman <strong>Produk Saya</strong>, lalu klik <strong>Tambah Produk</strong> di pojok kanan atas untuk menambah menu baru. Isi nama produk, harga, deskripsi singkat, dan foto produk yang menarik, lalu klik <strong>Simpan</strong>. Untuk mengubah produk yang sudah ada, klik ikon pensil (✏️) pada kartu produk yang ingin diedit. Kamu juga bisa menonaktifkan produk sementara tanpa menghapusnya.
              </div>
            </div>

            <!-- 4 -->
            <div class="faq-item" onclick="toggleFaq(this)">
              <div class="faq-question">
                <span>Bagaimana cara melihat laporan pendapatan?</span>
                <i class="fa-solid fa-chevron-down"></i>
              </div>
              <div class="faq-answer">
                Buka halaman <strong>Pendapatan</strong>. Di sana kamu bisa melihat ringkasan total pendapatan hari ini, minggu ini, dan bulan ini. Gunakan fitur <strong>Filter Tanggal</strong> untuk melihat rekap di rentang waktu tertentu. Kamu juga bisa mengunduh laporan dalam format PDF atau cetak langsung dari halaman tersebut.
              </div>
            </div>

            <!-- 5 -->
            <div class="faq-item" onclick="toggleFaq(this)">
              <div class="faq-question">
                <span>Bagaimana cara menarik saldo/dana?</span>
                <i class="fa-solid fa-chevron-down"></i>
              </div>
              <div class="faq-answer">
                Buka halaman <strong>Pendapatan</strong>, lalu klik tombol <strong>Tarik Dana</strong>. Pastikan kamu sudah mendaftarkan rekening bank terlebih dahulu di halaman <strong>Pengaturan → Bank &amp; Kartu</strong>. Masukkan nominal yang ingin ditarik, lalu konfirmasi. Dana akan diproses dalam <strong>1×24 jam kerja</strong>. Saldo minimal untuk penarikan adalah Rp 10.000.
              </div>
            </div>

            <!-- 6 -->
            <div class="faq-item" onclick="toggleFaq(this)">
              <div class="faq-question">
                <span>Mengapa saldo saya belum bertambah setelah pesanan selesai?</span>
                <i class="fa-solid fa-chevron-down"></i>
              </div>
              <div class="faq-answer">
                Saldo baru ditambahkan setelah pesanan berstatus <strong>Selesai</strong>. Jika sudah berstatus Selesai tapi saldo belum masuk, tunggu beberapa menit karena sistem perlu waktu memproses. Apabila lebih dari 30 menit belum masuk, hubungi admin melalui WhatsApp atau email di bagian atas halaman ini.
              </div>
            </div>

            <!-- 7 -->
            <div class="faq-item" onclick="toggleFaq(this)">
              <div class="faq-question">
                <span>Bagaimana cara mendaftarkan rekening bank?</span>
                <i class="fa-solid fa-chevron-down"></i>
              </div>
              <div class="faq-answer">
                Buka <strong>Pengaturan → Bank &amp; Kartu</strong>, lalu klik <strong>Tambah Rekening</strong>. Pilih nama bank dari daftar yang tersedia, masukkan nama pemilik rekening sesuai buku tabungan dan nomor rekening dengan benar. Klik <strong>Simpan</strong> dan tunggu verifikasi dari admin (biasanya 1×24 jam). Setelah terverifikasi, rekening siap digunakan untuk penarikan dana.
              </div>
            </div>

            <!-- 8 -->
            <div class="faq-item" onclick="toggleFaq(this)">
              <div class="faq-question">
                <span>Bagaimana cara mengubah foto profil atau nama toko?</span>
                <i class="fa-solid fa-chevron-down"></i>
              </div>
              <div class="faq-answer">
                Klik link <strong>✏️ Ubah Profil</strong> di bagian atas sidebar, atau buka halaman <strong>Pengaturan → Profil Toko</strong>. Dari sana kamu bisa mengganti foto profil, nama toko, deskripsi toko, dan jam operasional. Pastikan foto profil berukuran minimal 200×200 piksel dan format JPG/PNG untuk hasil terbaik.
              </div>
            </div>

            <!-- 9 -->
            <div class="faq-item" onclick="toggleFaq(this)">
              <div class="faq-question">
                <span>Apa yang harus dilakukan jika aplikasi error atau lambat?</span>
                <i class="fa-solid fa-chevron-down"></i>
              </div>
              <div class="faq-answer">
                Coba langkah-langkah berikut: <strong>(1)</strong> Refresh halaman (tekan F5 atau tarik ke bawah di mobile). <strong>(2)</strong> Hapus cache browser — buka pengaturan browser, cari "Hapus Data Browsing", centang Cache dan Cookies, lalu hapus. <strong>(3)</strong> Coba gunakan browser lain seperti Chrome atau Firefox. <strong>(4)</strong> Pastikan koneksi internetmu stabil. Jika masalah masih berlanjut, laporkan melalui form di bawah ini.
              </div>
            </div>

            <!-- 10 -->
            <div class="faq-item" onclick="toggleFaq(this)">
              <div class="faq-question">
                <span>Bagaimana cara mengatur jam buka/tutup toko?</span>
                <i class="fa-solid fa-chevron-down"></i>
              </div>
              <div class="faq-answer">
                Buka <strong>Pengaturan → Jam Operasional</strong>. Atur jam buka dan tutup toko untuk setiap hari. Kamu juga bisa mengaktifkan mode <strong>Libur</strong> untuk menonaktifkan toko sementara — saat mode libur aktif, pelanggan tidak bisa memesan dari tokomu. Jangan lupa klik <strong>Simpan Perubahan</strong> setelah mengatur jadwal.
              </div>
            </div>

          </div>
        </div>

        <div class="support-divider"></div>

        <!-- LAPORAN MASALAH -->
        <div class="support-section">
          <div class="support-section-title">
            <i class="fa-solid fa-triangle-exclamation"></i> Laporkan Masalah
          </div>
          <div class="support-section-sub">Ada kendala teknis? Ceritakan kepada kami dan kami akan segera menindaklanjuti</div>

          <div class="laporan-form">
            <div class="laporan-field">
              <label class="laporan-label">Kategori Masalah</label>
              <select class="laporan-select" id="kategoriSelect">
                <option value="">-- Pilih Kategori --</option>
                <option>Masalah Login / Akun</option>
                <option>Masalah Pesanan</option>
                <option>Masalah Pembayaran / Saldo</option>
                <option>Masalah Produk</option>
                <option>Bug / Error Aplikasi</option>
                <option>Masalah Notifikasi</option>
                <option>Masalah Rekening Bank</option>
                <option>Lainnya</option>
              </select>
            </div>

            <div class="laporan-field">
              <label class="laporan-label">Deskripsi Masalah</label>
              <textarea class="laporan-textarea" id="deskripsiTextarea"
                placeholder="Ceritakan masalah yang kamu alami secara detail. Semakin lengkap informasinya, semakin cepat kami bisa membantu..."></textarea>
            </div>

            <button class="btn-kirim-laporan" onclick="kirimLaporan()">
              <i class="fa-solid fa-paper-plane"></i> Kirim Laporan
            </button>
          </div>
        </div>

      </div><!-- /support-wrap -->
    </main>
  </div><!-- /page-wrapper -->

  <!-- SUKSES OVERLAY -->
  <div class="sukses-overlay" id="suksesOverlay">
    <div class="sukses-box">
      <div class="sukses-icon"><i class="fa-solid fa-circle-check"></i></div>
      <div class="sukses-title">Laporan Terkirim!</div>
      <div class="sukses-sub">Laporan kamu sudah diterima. Admin akan segera menghubungi kamu dalam 1×24 jam kerja.</div>
      <button class="btn-sukses-ok" onclick="tutupSukses()">Oke, Mengerti</button>
    </div>
  </div>

  <script src="suport.js"></script>
  <script>
async function loadNotifChat() {
    try {
        const res = await fetch('../users/chat_api.php?action=jumlah_belum_baca');
        const data = await res.json();

        const badge = document.getElementById('chatBadge');

        if (data.belum_baca > 0) {
            badge.style.display = 'flex';
            badge.textContent = data.belum_baca;
        } else {
            badge.style.display = 'none';
        }

    } catch (err) {
        console.error('Gagal ambil notif chat:', err);
    }
}

loadNotifChat();
setInterval(loadNotifChat, 1000);
</script>
    <?php include 'chat_popup_penjual.php';?>

<script>
// ── Badge Pesanan Masuk sidebar (poll tiap 5 detik) ──
function loadSidebarPesananBadge() {
    fetch('pesanan.php?action=get_notif_count')
        .then(r => r.json())
        .then(data => {
            const badge = document.getElementById('sidebarPesananBadge');
            if (!badge) return;
            const pending = data.pending || 0;
            if (pending > 0) {
                badge.textContent   = pending;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        })
        .catch(() => {});
}
loadSidebarPesananBadge();
setInterval(loadSidebarPesananBadge, 5000);
</script>
<script src="../custom_confirm.js"></script>
</body>
</html>