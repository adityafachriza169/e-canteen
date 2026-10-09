<?php
session_start();

// AUTH
if (!isset($_SESSION['username']) || (int)($_SESSION['role'] ?? 0) !== 1) {
    header('Location: ../../users/login.php');
    exit;
}

include "../../users/koneksi.php";
$conn = $konek;
$conn->set_charset('utf8mb4');

$ses_username = $_SESSION['username'];
$stmt = $conn->prepare("
    SELECT p.id_penjual, p.nama, p.email, p.username, p.profil,
           p.id_kantin, k.nama_kantin
    FROM   penjual p
    LEFT JOIN kantin k ON k.id_kantin = p.id_kantin
    WHERE  p.username = ?
    LIMIT  1
");
$stmt->bind_param('s', $ses_username);
$stmt->execute();
$data_penjual = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$data_penjual) {
    session_destroy();
    header('Location: ../../users/login.php');
    exit;
}

$user_nama   = $data_penjual['nama'] ?: $data_penjual['username'];
$nama_kantin = $data_penjual['nama_kantin'] ?? 'Kantin';

// Foto profil
$foto_profil_file = $data_penjual['profil'];
$kemungkinan_path = [
    '../profil/' . $foto_profil_file,
    'profil/'    . $foto_profil_file,
    '../../admin/uploads/' . $foto_profil_file,
];
$foto_profil = 'https://ui-avatars.com/api/?name=' . urlencode($user_nama) . '&background=e8650a&color=fff&size=80';
foreach ($kemungkinan_path as $path) {
    if (!empty($foto_profil_file) && file_exists($path)) {
        $foto_profil = $path;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>`
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>E-Canteen – Pengaturan Notifikasi</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Righteous&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="notif.css" />
</head>
<body>

 <!-- ═══════════════ TOPBAR ═══════════════ -->
  <div class="topbar">
    <div class="top-left"></div>
    <div class="top-right">
      <a href="../pesanan.php"><i class="fa-regular fa-bell"></i> Notifikasi</a>
      <a href="../suport.php"><i class="fa-regular fa-circle-question"></i> Bantuan</a>
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


  <!-- ═══════════════ NAVBAR ═══════════════ -->
  <div class="navbar orange-bg">
    <div class="navbar-texture"></div>
    <div class="orb1"></div>
    <div class="orb2"></div>

    <!-- Hamburger: tampil di mobile -->
    <button class="hamburger-btn" id="hamburgerBtn" aria-label="Buka menu navigasi" onclick="toggleSidebar()">
      <i class="fa-solid fa-bars"></i>
    </button>

    <div class="logo">
      <div class="logo-img-wrap">
        <img src="../../gambar/logo.png" alt="Logo E-Canteen" />
      </div>
      <div class="logo-text">
        <span class="logo-title">E-Canteen</span>
        <span class="logo-sub">Esemkita</span>
      </div>
    </div>

    <!-- Overlay: klik di luar sidebar untuk tutup -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <div class="search-wrap">
      <div class="search-box">
        <input type="text" placeholder="Cari..." />
        <button><i class="fa-solid fa-magnifying-glass"></i></button>
      </div>
    </div>

    <div class="icon-actions">
      
    </div>
  </div>

  <!-- ═══════════════ PAGE WRAPPER ═══════════════ -->
  <div class="page-wrapper">

    <!-- ── SIDEBAR ── -->
    <aside class="sidebar" id="sidebar">

      <div class="sidebar-profile">
        <div class="sidebar-avatar">
          <img
            src="<?= htmlspecialchars($foto_profil) ?>"
            alt="Avatar"
            onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user_nama) ?>&background=e8650a&color=fff&size=80'"
          />
        </div>
        <div class="sidebar-profile-info">
          <h3><?= htmlspecialchars($user_nama) ?></h3>
          <small style="color:#aaa;font-size:.75rem;"><?= htmlspecialchars($nama_kantin) ?></small><br />
          <a href="profile.php">✏️ Ubah Profil</a>
        </div>
      </div>

      <div class="sidebar-section">
        <div class="sidebar-section-title">
          <i class="fa-regular fa-user"></i>
          Akun Saya
        </div>
        <div class="sidebar-section-links">
          <a href="profile.php">Profil</a>
          <a href="bank.php">QRIS Saya</a>
          <a href="notif.php" class="active">Pengaturan Notifikasi</a>
        </div>
      </div>

      <div class="sidebar-divider"></div>

      <!-- Navigasi utama -->
      <div class="sidebar-section">
        <div class="sidebar-section-title">
          <i class="fa-solid fa-grip"></i>
          Menu Utama
        </div>
        <div class="sidebar-section-links">
          <a href="../dasbord.php">Dashboard</a>
          <a href="../produk.php">Produk Saya</a>
          <a href="../pesanan.php">Pesanan Masuk</a>
          <a href="../pendapatan.php">Pendapatan</a>
        </div>
      </div>

    </aside>

    <!-- ── MAIN CONTENT ── -->
    <main class="main">

      <div class="notif-wrap">

        <!-- HEADER -->
        <div class="notif-header">
          <div class="notif-judul">Pengaturan Notifikasi</div>
          <div class="notif-sub">Kelola notifikasi yang ingin kamu terima</div>
        </div>

        <div class="notif-divider"></div>

        <!-- NOTIFIKASI APLIKASI -->
        <div class="notif-section">
          <div class="notif-section-header">
            <div class="notif-section-left">
              <div class="notif-section-title">
                <i class="fa-solid fa-bell"></i> Notifikasi Aplikasi
              </div>
              <div class="notif-section-sub">Notifikasi yang muncul langsung di aplikasi</div>
            </div>
            <label class="toggle">
              <input type="checkbox" checked onchange="toggleSection(this, 'sectionAplikasi')" />
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div class="notif-items" id="sectionAplikasi">
            <div class="notif-item">
              <div class="notif-item-info">
                <div class="notif-item-title">Pesanan Masuk Baru</div>
                <div class="notif-item-sub">Notifikasi ketika ada pesanan baru dari pelanggan</div>
              </div>
              <label class="toggle">
                <input type="checkbox" checked />
                <span class="toggle-slider"></span>
              </label>
            </div>
            <div class="notif-item">
              <div class="notif-item-info">
                <div class="notif-item-title">Pesanan Dibatalkan</div>
                <div class="notif-item-sub">Notifikasi ketika pelanggan membatalkan pesanan</div>
              </div>
              <label class="toggle">
                <input type="checkbox" checked />
                <span class="toggle-slider"></span>
              </label>
            </div>
            <div class="notif-item">
              <div class="notif-item-info">
                <div class="notif-item-title">Pengingat Pesanan Belum Diproses</div>
                <div class="notif-item-sub">Notifikasi ketika ada pesanan yang belum diproses lebih dari 5 menit</div>
              </div>
              <label class="toggle">
                <input type="checkbox" checked />
                <span class="toggle-slider"></span>
              </label>
            </div>
            <div class="notif-item">
              <div class="notif-item-info">
                <div class="notif-item-title">Pengumuman Admin</div>
                <div class="notif-item-sub">Informasi dan pengumuman penting dari admin sekolah</div>
              </div>
              <label class="toggle">
                <input type="checkbox" checked />
                <span class="toggle-slider"></span>
              </label>
            </div>
          </div>
        </div>

        <div class="notif-divider"></div>

        <!-- NOTIFIKASI EMAIL -->
        <div class="notif-section">
          <div class="notif-section-header">
            <div class="notif-section-left">
              <div class="notif-section-title">
                <i class="fa-solid fa-envelope"></i> Notifikasi Email
              </div>
              <div class="notif-section-sub">
                Dikirim ke email kantin kamu:
                <span class="notif-email-badge"><?= htmlspecialchars($data_penjual['email'] ?? '-') ?></span>
              </div>
            </div>
            <label class="toggle">
              <input type="checkbox" checked onchange="toggleSection(this, 'sectionEmail')" />
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div class="notif-items" id="sectionEmail">
            <div class="notif-item">
              <div class="notif-item-info">
                <div class="notif-item-title">Laporan Pendapatan Harian</div>
                <div class="notif-item-sub">Ringkasan pendapatan dikirim setiap hari pukul 20.00</div>
              </div>
              <label class="toggle">
                <input type="checkbox" checked />
                <span class="toggle-slider"></span>
              </label>
            </div>
            <div class="notif-item">
              <div class="notif-item-info">
                <div class="notif-item-title">Penarikan Dana Berhasil</div>
                <div class="notif-item-sub">Konfirmasi ketika penarikan dana berhasil diproses</div>
              </div>
              <label class="toggle">
                <input type="checkbox" checked />
                <span class="toggle-slider"></span>
              </label>
            </div>
            <div class="notif-item">
              <div class="notif-item-info">
                <div class="notif-item-title">Penarikan Dana Gagal</div>
                <div class="notif-item-sub">Notifikasi ketika penarikan dana gagal diproses</div>
              </div>
              <label class="toggle">
                <input type="checkbox" checked />
                <span class="toggle-slider"></span>
              </label>
            </div>
            <div class="notif-item">
              <div class="notif-item-info">
                <div class="notif-item-title">Pengumuman Admin</div>
                <div class="notif-item-sub">Informasi penting dari admin sekolah via email</div>
              </div>
              <label class="toggle">
                <input type="checkbox" />
                <span class="toggle-slider"></span>
              </label>
            </div>
          </div>
        </div>

        <div class="notif-divider"></div>

        <!-- TOMBOL SIMPAN -->
        <div class="notif-footer">
          <button class="btn-simpan-notif" onclick="simpanNotif()">
            <i class="fa-solid fa-floppy-disk"></i> Simpan Pengaturan
          </button>
        </div>

      </div><!-- /notif-wrap -->

    </main>

    <!-- SUKSES OVERLAY -->
    <div class="sukses-overlay" id="suksesOverlay">
      <div class="sukses-box">
        <div class="sukses-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div class="sukses-title">Pengaturan Disimpan!</div>
        <div class="sukses-sub">Pengaturan notifikasi kamu berhasil diperbarui.</div>
        <button class="btn-sukses-ok" onclick="tutupSukses()">Oke, Mengerti</button>
      </div>
    </div>

  </div><!-- /page-wrapper -->

  <script>
    /* ── Hamburger sidebar — identik dengan bank.php & dasbord.php ── */
    function toggleSidebar() {
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('sidebarOverlay');
      if (sidebar.classList.contains('open')) {
        closeSidebar();
      } else {
        sidebar.classList.add('open');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
      }
    }

    function closeSidebar() {
      document.getElementById('sidebar').classList.remove('open');
      document.getElementById('sidebarOverlay').classList.remove('show');
      document.body.style.overflow = '';
    }

    /* Tutup sidebar saat ESC ditekan */
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closeSidebar();
    });

    /* ── Notifikasi ── */
    function toggleSection(checkbox, sectionId) {
      const section = document.getElementById(sectionId);
      section.classList.toggle('disabled', !checkbox.checked);
    }

    function simpanNotif() {
      document.getElementById('suksesOverlay').classList.add('open');
      document.body.style.overflow = 'hidden';
    }

    function tutupSukses() {
      document.getElementById('suksesOverlay').classList.remove('open');
      document.body.style.overflow = '';
    }
  </script>

</body>
</html>