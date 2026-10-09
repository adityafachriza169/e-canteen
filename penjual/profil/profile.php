<?php
session_start();

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
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) { session_destroy(); header('Location: ../../users/login.php'); exit; }

$id_penjual = (int)$data['id_penjual'];

if (isset($_POST['simpan'])) {
    $nama_baru = trim($_POST['nama_kantin']);
    $foto_baru = $data['profil'];

    if (!empty($_FILES['foto']['name'])) {
        $ext   = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $allow = ['jpg','jpeg','png'];
        if (in_array($ext, $allow) && $_FILES['foto']['size'] <= 1048576) {
            $namaFile   = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['foto']['name']));
            $uploadPath = '../../admin/uploads/' . $namaFile;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $uploadPath)) {
                $foto_baru = $namaFile;
            }
        }
    }

    $upd = $conn->prepare("UPDATE penjual SET nama=?, profil=? WHERE id_penjual=?");
    $upd->bind_param('ssi', $nama_baru, $foto_baru, $id_penjual);
    $upd->execute();
    $upd->close();
    header('Location: profile.php');
    exit;
}

$foto_src = !empty($data['profil'])
    ? '../../admin/uploads/' . htmlspecialchars($data['profil'])
    : '../../admin/uploads/default.png';

$nama_kantin_display = !empty($data['nama_kantin'])
    ? htmlspecialchars($data['nama_kantin'])
    : 'Kantin ' . htmlspecialchars($data['id_kantin']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Profil – E-Canteen</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<link href="https://fonts.googleapis.com/css2?family=Righteous&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="profile.css">
</head>
<body>
    <!-- Mobile topbar (fixed, hanya muncul di mobile) -->
<div class="mob-topbar">
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


<!-- ══════════════ NAVBAR ══════════════ -->
<div class="navbar orange-bg">
    <div class="navbar-texture"></div>
    <div class="orb1"></div>
    <div class="orb2"></div>

    <button class="mob-menu-btn" id="menuToggle" aria-label="Buka menu" aria-expanded="false">
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
    

    <div class="search-wrap">
        <div class="search-box">
            <input type="text" placeholder="Cari makanan dan minuman sehat...">
            <button aria-label="Cari"><i class="fa-solid fa-magnifying-glass"></i></button>
        </div>
    </div>

    <button class="mob-search-btn" id="searchToggle" aria-label="Cari">
        <i class="fa-solid fa-magnifying-glass"></i>
    </button>
</div>

<!-- Mobile search overlay -->
<div class="search-overlay" id="searchOverlay">
    <div class="search-overlay-box">
        <input type="text" id="searchOverlayInput" placeholder="Cari makanan dan minuman...">
        <button aria-label="Cari"><i class="fa-solid fa-magnifying-glass"></i></button>
    </div>
</div>

<!-- Sidebar backdrop -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ══════════════ PAGE WRAPPER ══════════════ -->
<div class="page-wrapper">

    <!-- ══ SIDEBAR ══ -->
    <aside class="sidebar" id="sidebar" aria-label="Menu navigasi">

        <!-- Profile block -->
        <div class="sidebar-profile">
            <div class="sidebar-avatar">
                <img src="<?= $foto_src ?>" alt="Foto profil" id="sidebarAvatarImg">
            </div>
            <div class="sidebar-profile-info">
                <h3><?= htmlspecialchars($data['username']) ?></h3>
                <span class="sidebar-kantin-name"><?= $nama_kantin_display ?></span>
                <a href="profile.php">✏️ Ubah Profil</a>
            </div>
        </div>

        <!-- Akun Saya -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">
                <i class="fa-regular fa-user"></i>
                Akun Saya
            </div>
            <div class="sidebar-section-links">
                <a href="profile.php" class="active">Profil</a>
                <a href="bank.php">QRIS Saya</a>
                <a href="notif.php">Pengaturan Notifikasi</a>
            </div>
        </div>

        <div class="sidebar-divider"></div>

        <!-- Menu Utama -->
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

    <!-- ══ MAIN ══ -->
    <main class="main">
        <div class="profil-wrap">

            <!-- FOTO -->
            <div class="profil-foto-area">
                <div class="profil-foto-wrap" id="fotoWrap" title="Klik untuk ganti foto">
                    <img src="<?= $foto_src ?>" alt="Foto Profil" class="profil-foto" id="previewFoto">
                </div>
                <div class="foto-meta">
                    <button type="button" class="btn-pilih-gambar" id="btnPilihGambar">
                        <i class="fa-regular fa-image"></i> Pilih Gambar
                    </button>
                    <div class="profil-foto-hint">Maks. 1 MB · JPEG / PNG</div>
                </div>
            </div>

            <!-- FORM -->
            <div class="profil-form-area">
                <div class="profil-judul">Profil Saya</div>
                <div class="profil-sub">Kelola informasi profil Anda untuk mengontrol, melindungi dan mengamankan akun</div>
                <div class="profil-divider"></div>

                <form method="POST" enctype="multipart/form-data" id="profilForm">
                    <input type="file" name="foto" id="inputFoto" accept="image/jpeg,image/png" style="display:none">

                    <div class="form-row">
                        <label class="form-label" for="f_username">Username</label>
                        <div class="form-field">
                            <input id="f_username" type="text" class="form-input input-readonly"
                                value="<?= htmlspecialchars($data['username']) ?>" readonly>
                        </div>
                    </div>

                    <div class="form-row">
                        <label class="form-label" for="f_kantin_id">No. Kantin</label>
                        <div class="form-field">
                            <input id="f_kantin_id" type="text" class="form-input input-readonly"
                                value="<?= htmlspecialchars($data['id_kantin']) ?>" readonly>
                        </div>
                    </div>

                    <div class="form-row">
                        <label class="form-label">Email</label>
                        <div class="form-field">
                            <div class="form-masked">
                                <span class="input-readonly-text"><?= htmlspecialchars($data['email']) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <label class="form-label" for="f_nama">Nama Kantin</label>
                        <div class="form-field">
                            <input id="f_nama" type="text" name="nama_kantin" class="form-input"
                                value="<?= htmlspecialchars($data['nama']) ?>"
                                placeholder="Masukkan nama kantin" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <label class="form-label"></label>
                        <div class="form-field">
                            <button type="submit" name="simpan" class="btn-simpan">
                                <i class="fa-solid fa-floppy-disk"></i> Simpan
                            </button>
                        </div>
                    </div>
                </form>
            </div>

        </div>
    </main>
</div>

<script>
(function () {
    /* ── Foto preview ── */
    const inputFoto   = document.getElementById('inputFoto');
    const previewFoto = document.getElementById('previewFoto');
    const fotoWrap    = document.getElementById('fotoWrap');
    const btnPilih    = document.getElementById('btnPilihGambar');
    const sidebarAvatar = document.getElementById('sidebarAvatarImg');

    [fotoWrap, btnPilih].forEach(el => el.addEventListener('click', () => inputFoto.click()));

    inputFoto.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        if (file.size > 1048576) {
            alert('Ukuran gambar maksimal 1 MB!');
            this.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = e => {
            previewFoto.src = e.target.result;
            if (sidebarAvatar) sidebarAvatar.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });

    /* ── Sidebar toggle ── */
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sidebarOverlay');
    const menuBtn  = document.getElementById('menuToggle');

    const openSidebar = () => {
    sidebar.classList.add('open');
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
    menuBtn.setAttribute('aria-expanded', 'true');
    // Tambahkan blur
    document.querySelector('.topbar')?.classList.add('blurred');
    document.querySelector('.navbar')?.classList.add('blurred');
};

const closeSidebar = () => {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
    menuBtn.setAttribute('aria-expanded', 'false');
    // Hapus blur
    document.querySelector('.topbar')?.classList.remove('blurred');
    document.querySelector('.navbar')?.classList.remove('blurred');
};

    menuBtn.addEventListener('click', openSidebar);
    overlay.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { closeSidebar(); closeSearch(); }
    });
    sidebar.querySelectorAll('a').forEach(a =>
        a.addEventListener('click', () => { if (window.innerWidth <= 768) closeSidebar(); })
    );

    /* ── Search overlay ── */
    const searchOverlay = document.getElementById('searchOverlay');
    const searchInput   = document.getElementById('searchOverlayInput');
    const searchBtn     = document.getElementById('searchToggle');

    const openSearch  = () => { searchOverlay.classList.add('open'); setTimeout(() => searchInput.focus(), 80); };
    const closeSearch = () => searchOverlay.classList.remove('open');

    searchBtn.addEventListener('click', openSearch);
    searchOverlay.addEventListener('click', e => { if (e.target === searchOverlay) closeSearch(); });

    /* ── Auto-close sidebar on resize ── */
    window.addEventListener('resize', () => { if (window.innerWidth > 768) closeSidebar(); });
})();
</script>
</body>
</html>