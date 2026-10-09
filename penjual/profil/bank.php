<?php
session_start();

// AUTH
if (!isset($_SESSION['username']) || (int)($_SESSION['role'] ?? 0) !== 1) {
    header('Location: ../../users/login.php');
    exit;
}

include "../koneksi.php";
$conn = $db;
$conn->set_charset('utf8mb4');

// Ambil data penjual
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

if (!$data_penjual) {
    session_destroy();
    header('Location: ../../users/login.php');
    exit;
}

$id_penjual  = (int) $data_penjual['id_penjual'];
$user_nama   = $data_penjual['nama'] ?: $data_penjual['username'];
$nama_kantin = $data_penjual['nama_kantin'] ?? 'Kantin';

// Foto profil
$foto_profil_file = $data_penjual['profil'];
$kemungkinan_path = [
    '../profil/'           . $foto_profil_file,
    'profil/'              . $foto_profil_file,
    '../../admin/uploads/' . $foto_profil_file,
];
$foto_profil = 'https://ui-avatars.com/api/?name=' . urlencode($user_nama) . '&background=e8650a&color=fff&size=80';
foreach ($kemungkinan_path as $path) {
    if (!empty($foto_profil_file) && file_exists($path)) {
        $foto_profil = $path;
        break;
    }
}

/* ────────────────────────────────────────
   LOGIKA:
   - Penjual hanya bisa upload foto QRIS baru
   - QRIS baru masuk dengan verified = 0 (pending)
   - QRIS pending TIDAK ditampilkan ke pembeli
   - Saat admin approve QRIS baru → QRIS lama (verified=1) otomatis dihapus
   - Di halaman ini: tampilkan QRIS verified (aktif) saja
   - Jika ada pending, tampilkan info "sedang menunggu verifikasi"
──────────────────────────────────────── */
$pesan_error  = '';
$pesan_sukses = '';

if (isset($_POST['simpan_qris'])) {

    // Cek apakah sudah ada QRIS pending (belum diverifikasi)
    $cek_pending = $conn->prepare("SELECT id_qris FROM penjual_qris WHERE id_penjual = ? AND verified = 0 LIMIT 1");
    $cek_pending->bind_param('i', $id_penjual);
    $cek_pending->execute();
    $ada_pending = $cek_pending->get_result()->fetch_assoc();
    $cek_pending->close();

    if ($ada_pending) {
        $pesan_error = 'Kamu masih memiliki QRIS yang sedang menunggu verifikasi admin. Tunggu hingga disetujui atau ditolak sebelum mengajukan yang baru.';
    } else {
        // Wajib upload gambar
        if (!isset($_FILES['gambar_qris']) || $_FILES['gambar_qris']['error'] !== UPLOAD_ERR_OK) {
            $pesan_error = 'Gambar QRIS wajib diupload.';
        } else {
            $folder_qris = '../../admin/uploads/qris/';
            if (!is_dir($folder_qris)) mkdir($folder_qris, 0755, true);

            $ext_allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $ext         = strtolower(pathinfo($_FILES['gambar_qris']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $ext_allowed)) {
                $pesan_error = 'Format gambar tidak didukung. Gunakan JPG, PNG, atau WEBP.';
            } elseif ($_FILES['gambar_qris']['size'] > 3 * 1024 * 1024) {
                $pesan_error = 'Ukuran gambar maksimal 3 MB.';
            } else {
                $nama_file_baru = 'qris_' . $id_penjual . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
                if (!move_uploaded_file($_FILES['gambar_qris']['tmp_name'], $folder_qris . $nama_file_baru)) {
                    $pesan_error = 'Gagal mengupload gambar. Coba lagi.';
                } else {
                    // Insert QRIS baru dengan verified = 0
                    // QRIS lama (verified=1) dibiarkan dulu — akan dihapus otomatis saat admin approve yang baru
                    $ins = $conn->prepare("
                        INSERT INTO penjual_qris (id_penjual, gambar_qris, verified, created_at)
                        VALUES (?, ?, 0, NOW())
                    ");
                    $ins->bind_param('is', $id_penjual, $nama_file_baru);
                    $ins->execute();
                    $ins->close();
                    $pesan_sukses = 'QRIS baru berhasil diajukan dan sedang menunggu verifikasi admin.';
                }
            }
        }
    }
}

/* ────────────────────────────────────────
   AMBIL DATA QRIS:
   - $qris_aktif  : verified = 1 (ditampilkan ke pembeli & di halaman ini)
   - $qris_pending: verified = 0 (hanya info status, tidak ditampilkan gambarnya)
──────────────────────────────────────── */
$st_aktif = $conn->prepare("SELECT * FROM penjual_qris WHERE id_penjual = ? AND verified = 1 ORDER BY verified_at DESC LIMIT 1");
$st_aktif->bind_param('i', $id_penjual);
$st_aktif->execute();
$qris_aktif = $st_aktif->get_result()->fetch_assoc();
$st_aktif->close();

$st_pending = $conn->prepare("SELECT * FROM penjual_qris WHERE id_penjual = ? AND verified = 0 ORDER BY created_at DESC LIMIT 1");
$st_pending->bind_param('i', $id_penjual);
$st_pending->execute();
$qris_pending = $st_pending->get_result()->fetch_assoc();
$st_pending->close();

$conn->close();

// Path gambar QRIS aktif
$qris_img_src = '';
if (!empty($qris_aktif['gambar_qris'])) {
    $qris_path = '../../admin/uploads/qris/' . $qris_aktif['gambar_qris'];
    if (file_exists($qris_path)) $qris_img_src = $qris_path;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>E-Canteen – QRIS Saya</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Righteous&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="bank.css" />
  <style>
    .qris-wrap { max-width: 580px; }

    /* ── QRIS AKTIF CARD ── */
    .qris-card {
      background: var(--card, #fff);
      border: 1.5px solid var(--border, #eee);
      border-radius: 20px;
      padding: 28px;
      display: flex;
      gap: 24px;
      align-items: flex-start;
      box-shadow: 0 6px 24px rgba(0,0,0,.06);
      transition: box-shadow .2s;
    }
    .qris-card:hover { box-shadow: 0 10px 32px rgba(0,0,0,.1); }

    .qris-img-wrap {
      flex-shrink: 0;
      width: 140px; height: 140px;
      border-radius: 14px;
      border: 2px solid #e8650a33;
      overflow: hidden;
      background: #f8f8f8;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; position: relative;
      transition: border-color .2s;
    }
    .qris-img-wrap:hover { border-color: #e8650a; }
    .qris-img-wrap img { width: 100%; height: 100%; object-fit: contain; }
    .qris-img-zoom-hint {
      position: absolute; bottom: 0; left: 0; right: 0;
      background: rgba(0,0,0,.45); color: #fff;
      font-size: 11px; font-weight: 600;
      text-align: center; padding: 5px 0;
      opacity: 0; transition: opacity .2s;
    }
    .qris-img-wrap:hover .qris-img-zoom-hint { opacity: 1; }

    .qris-img-placeholder {
      width: 140px; height: 140px;
      border-radius: 14px;
      background: linear-gradient(135deg, #e8650a22, #e8650a11);
      border: 2px dashed #e8650a55;
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      gap: 8px; flex-shrink: 0;
    }
    .qris-img-placeholder i { font-size: 34px; color: #e8650a88; }
    .qris-img-placeholder span { font-size: 12px; color: #e8650a88; font-weight: 600; }

    .qris-info { flex: 1; min-width: 0; }
    .qris-label { font-size: 11px; font-weight: 700; text-transform: uppercase;
                  letter-spacing: .8px; color: #aaa; margin-bottom: 6px; }
    .qris-kantin { font-size: 18px; font-weight: 800; color: #111; margin-bottom: 4px; }
    .qris-date { font-size: 12.5px; color: #888; margin-bottom: 14px; }
    .qris-date i { color: #e8650a; margin-right: 4px; }

    .badge-verified {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 5px 14px; border-radius: 20px;
      font-size: 12.5px; font-weight: 700;
      background: #d1fae5; color: #065f46;
      margin-bottom: 16px;
    }

    /* ── PENDING BANNER ── */
    .pending-banner {
      display: flex; align-items: flex-start; gap: 14px;
      background: #fff7ed;
      border: 1.5px solid #fed7aa;
      border-radius: 16px;
      padding: 18px 20px;
      margin-bottom: 20px;
    }
    .pending-banner-icon {
      width: 44px; height: 44px; border-radius: 12px;
      background: linear-gradient(135deg, #f97316, #fb923c);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .pending-banner-icon i { font-size: 20px; color: white; }
    .pending-banner-info { flex: 1; }
    .pending-banner-title { font-size: 14px; font-weight: 800; color: #c2410c; margin-bottom: 4px; }
    .pending-banner-sub { font-size: 12.5px; color: #92400e; line-height: 1.6; }
    .pending-banner-date { font-size: 11.5px; color: #b45309; margin-top: 6px; font-weight: 600; }

    /* ── EMPTY STATE ── */
    .qris-empty {
      text-align: center;
      padding: 52px 24px;
      border: 2px dashed #e8650a44;
      border-radius: 18px;
      background: #fff7f0;
    }
    .qris-empty i { font-size: 52px; color: #e8650a55; margin-bottom: 14px; display: block; }
    .qris-empty p { font-size: 16px; font-weight: 800; color: #333; margin-bottom: 6px; }
    .qris-empty span { font-size: 13px; color: #888; display: block; margin-bottom: 22px; line-height: 1.6; }

    /* ── TOMBOL ── */
    .btn-ajukan-qris {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;

  background: linear-gradient(135deg, #e8650a, #ff8c42);
  color: white;
  border: none;

  padding: 11px 18px;
  min-height: 44px;

  border-radius: 12px;

  font-size: 13.5px;
  font-weight: 700;
  line-height: 1;

  cursor: pointer;
  font-family: inherit;

  transition: .2s;
  box-shadow: 0 3px 12px rgba(232,101,10,.28);
}

.btn-ajukan-qris:hover {
  opacity: .9;
  transform: translateY(-1px);
}

.btn-ajukan-qris:disabled {
  background: #d1d5db;
  box-shadow: none;
  cursor: not-allowed;
  transform: none;
  opacity: 1;
}
    .btn-ajukan-qris:hover { opacity: .88; transform: translateY(-1px); }
    .btn-ajukan-qris:disabled {
      background: #d1d5db; box-shadow: none;
      cursor: not-allowed; transform: none; opacity: 1;
    }

    /* ── INFO NOTE ── */
    .qris-note {
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
      border-radius: 12px;
      padding: 12px 16px;
      font-size: 12.5px; color: #166534;
      line-height: 1.7; margin-top: 18px;
    }
    .qris-note strong { color: #15803d; }

    /* ── PESAN ── */
    .pesan-sukses {
      background: #d1fae5; color: #065f46;
      padding: 12px 16px; border-radius: 12px;
      font-size: 13.5px; font-weight: 600; margin-bottom: 18px;
      display: flex; align-items: center; gap: 8px;
    }
    .pesan-error {
      background: #fee2e2; color: #991b1b;
      padding: 12px 16px; border-radius: 12px;
      font-size: 13.5px; font-weight: 600; margin-bottom: 18px;
      display: flex; align-items: center; gap: 8px;
    }

    /* ── MODAL ── */
    .modal-overlay {
      display: none; position: fixed; inset: 0;
      background: rgba(0,0,0,.5); z-index: 999;
      align-items: center; justify-content: center;
      backdrop-filter: blur(4px); padding: 16px;
    }
    .modal-overlay.open { display: flex; }

    .modal-box {
      background: #fff; border-radius: 20px;
      padding: 28px; width: 100%; max-width: 480px;
      max-height: 90vh; overflow-y: auto;
      box-shadow: 0 24px 60px rgba(0,0,0,.2);
      animation: modalIn .28s ease;
    }
    @keyframes modalIn {
      from { opacity: 0; transform: scale(.95) translateY(10px); }
      to   { opacity: 1; transform: scale(1) translateY(0); }
    }

    .modal-header {
      display: flex; justify-content: space-between;
      align-items: flex-start; margin-bottom: 20px;
    }
    .modal-title { font-size: 17px; font-weight: 800; color: #111; }
    .modal-sub   { font-size: 13px; color: #888; margin-top: 3px; line-height: 1.5; }
    .modal-close {
      width: 32px; height: 32px; border-radius: 9px;
      background: #f5f5f5; border: 1px solid #eee;
      font-size: 16px; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      transition: .2s; flex-shrink: 0;
    }
    .modal-close:hover { background: #ffe4d4; color: #e8650a; }

    .modal-field { margin-bottom: 18px; }
    .modal-label {
      display: block; font-size: 13px; font-weight: 700;
      color: #555; margin-bottom: 7px;
    }

    /* Upload area */
    .upload-area {
      border: 2px dashed #e8650a55; border-radius: 12px;
      padding: 24px 20px; text-align: center;
      cursor: pointer; transition: .2s;
      background: #fff7f0; position: relative;
    }
    .upload-area:hover { border-color: #e8650a; background: #fff1e8; }
    .upload-area input[type="file"] {
      position: absolute; inset: 0; opacity: 0;
      cursor: pointer; width: 100%; height: 100%;
    }
    .upload-area i     { font-size: 32px; color: #e8650a88; margin-bottom: 10px; display: block; }
    .upload-area p     { font-size: 13.5px; color: #e8650a; font-weight: 700; margin-bottom: 4px; }
    .upload-area small { font-size: 11.5px; color: #aaa; }

    .upload-preview {
      display: none; margin-top: 12px;
      border-radius: 12px; overflow: hidden;
      border: 2px solid #e8650a33; text-align: center;
    }
    .upload-preview.show { display: block; }
    .upload-preview img { max-height: 200px; object-fit: contain; display: block; margin: 0 auto; }
    .upload-preview-name { padding: 7px 12px; font-size: 12px; color: #888; background: #fafafa; }

    .modal-footer {
      display: flex; gap: 10px; justify-content: flex-end;
      margin-top: 20px; padding-top: 18px;
      border-top: 1px solid #f0f0f0;
    }
    .btn-batal {
      padding: 10px 20px; border-radius: 10px;
      border: 1.5px solid #e5e7eb; background: none;
      font-size: 13.5px; font-weight: 600;
      cursor: pointer; font-family: inherit; color: #666; transition: .2s;
    }
    .btn-batal:hover { border-color: #ccc; background: #f5f5f5; }
    .btn-simpan-qris {
      padding: 10px 22px; border-radius: 10px;
      background: linear-gradient(135deg, #e8650a, #ff8c42);
      color: white; border: none;
      font-size: 13.5px; font-weight: 700;
      cursor: pointer; font-family: inherit; transition: .2s;
      display: inline-flex; align-items: center; gap: 7px;
      box-shadow: 0 3px 12px rgba(232,101,10,.28);
    }
    .btn-simpan-qris:hover { opacity: .88; transform: translateY(-1px); }

    /* Modal preview gambar */
    .modal-box-preview { max-width: 400px; }
    .qris-full-img { width: 100%; border-radius: 12px; border: 2px solid #eee; display: block; }
    .qris-full-info { text-align: center; margin-top: 12px; font-size: 13px; color: #666; }
    .qris-full-info strong { color: #111; font-size: 15px; display: block; margin-bottom: 3px; }

    @media (max-width: 500px) {
      .qris-card { flex-direction: column; align-items: center; text-align: center; }
      .qris-img-wrap, .qris-img-placeholder { width: 120px; height: 120px; }
    }
  </style>
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

  <!-- NAVBAR -->
  <div class="navbar orange-bg">
    <div class="navbar-texture"></div>
    <div class="orb1"></div>
    <div class="orb2"></div>
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
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
    <div class="search-wrap">
      <div class="search-box">
        <input type="text" placeholder="Cari..." />
        <button><i class="fa-solid fa-magnifying-glass"></i></button>
      </div>
    </div>
  </div>

  <!-- PAGE WRAPPER -->
  <div class="page-wrapper">

    <!-- SIDEBAR -->
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
          <i class="fa-regular fa-user"></i> Akun Saya
        </div>
        <div class="sidebar-section-links">
          <a href="profile.php">Profil</a>
          <a href="qris.php" class="active">QRIS Saya</a>
          <a href="notif.php">Pengaturan Notifikasi</a>
        </div>
      </div>
      <div class="sidebar-divider"></div>
      <div class="sidebar-section">
        <div class="sidebar-section-title">
          <i class="fa-solid fa-grip"></i> Menu Utama
        </div>
        <div class="sidebar-section-links">
          <a href="../dasbord.php">Dashboard</a>
          <a href="../produk.php">Produk Saya</a>
          <a href="../pesanan.php">Pesanan Masuk</a>
          <a href="../pendapatan.php">Pendapatan</a>
        </div>
      </div>
    </aside>

    <!-- MAIN -->
    <main class="main">
      
      <div class="bank-wrap qris-wrap">

        <div class="bank-header">
          <div>
            <div class="bank-judul">QRIS Saya</div>
            <div class="bank-sub">Kode QRIS untuk menerima pembayaran dari pembeli</div>
          </div>
        </div>

        <div class="bank-divider"></div>

        <?php if (!empty($pesan_sukses)): ?>
          <div class="pesan-sukses"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($pesan_sukses) ?></div>
        <?php endif; ?>
        <?php if (!empty($pesan_error)): ?>
          <div class="pesan-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($pesan_error) ?></div>
        <?php endif; ?>

        <?php if ($qris_pending): ?>
        <!-- BANNER QRIS PENDING -->
        <div class="pending-banner">
          <div class="pending-banner-icon">
            <i class="fa-solid fa-clock-rotate-left"></i>
          </div>
          <div class="pending-banner-info">
            <div class="pending-banner-title">⏳ QRIS Menunggu Verifikasi Admin</div>
            <div class="pending-banner-sub">
              Kamu telah mengajukan QRIS baru. QRIS ini belum aktif dan tidak ditampilkan ke pembeli
              hingga disetujui oleh admin.
              <?php if ($qris_aktif): ?>
                QRIS lama kamu masih aktif sementara menunggu persetujuan.
              <?php endif; ?>
            </div>
            <div class="pending-banner-date">
              <i class="fa-regular fa-calendar"></i>
              Diajukan: <?= date('d M Y, H:i', strtotime($qris_pending['created_at'])) ?> WIB
            </div>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($qris_aktif): ?>
        <!-- QRIS AKTIF -->
        <div class="qris-card">

          <?php if (!empty($qris_img_src)): ?>
            <div class="qris-img-wrap" onclick="bukaPreview('<?= htmlspecialchars($qris_img_src) ?>')">
              <img src="<?= htmlspecialchars($qris_img_src) ?>" alt="QRIS Aktif">
              <div class="qris-img-zoom-hint"><i class="fa-solid fa-magnifying-glass-plus"></i> Perbesar</div>
            </div>
          <?php else: ?>
            <div class="qris-img-placeholder">
              <i class="fa-solid fa-qrcode"></i>
              <span>Gambar tidak tersedia</span>
            </div>
          <?php endif; ?>

          <div class="qris-info">
            <div class="qris-label">QRIS Aktif</div>
            <div class="qris-kantin">🏪 <?= htmlspecialchars($nama_kantin) ?></div>
            <?php if (!empty($qris_aktif['verified_at'])): ?>
              <div class="qris-date">
                <i class="fa-solid fa-circle-check"></i>
                Diverifikasi: <?= date('d M Y', strtotime($qris_aktif['verified_at'])) ?>
              </div>
            <?php endif; ?>

            <div class="badge-verified">
              <i class="fa-solid fa-shield-check"></i> Terverifikasi & Aktif
            </div>

            <div>
              <?php if ($qris_pending): ?>
                <!-- Sudah ada pending, tombol disable -->
                <button class="btn-ajukan-qris" disabled title="Tunggu verifikasi QRIS yang sedang diajukan">
                  <i class="fa-solid fa-clock"></i> Menunggu Verifikasi
                </button>
              <?php else: ?>
                <button class="btn-ajukan-qris" onclick="bukaModal()">
                  <i class="fa-solid fa-arrows-rotate"></i> Ganti QRIS
                </button>
              <?php endif; ?>
            </div>
          </div>

        </div>

        <div class="qris-note">
          <strong>ℹ️ Info:</strong> QRIS baru yang kamu ajukan akan menggantikan QRIS ini secara otomatis
          setelah disetujui oleh admin. Selama proses verifikasi, QRIS yang aktif saat ini tetap digunakan.
        </div>

        <?php elseif (!$qris_pending): ?>
        <!-- BELUM ADA QRIS SAMA SEKALI -->
        <div class="qris-empty">
          <i class="fa-solid fa-qrcode"></i>
          <p>Belum ada QRIS terdaftar</p>
          <span>
            Daftarkan QRIS kamu agar pembeli dapat melakukan<br>pembayaran dengan mudah.
          </span>
          <button class="btn-ajukan-qris" onclick="bukaModal()" style="margin: 0 auto;">
            <i class="fa-solid fa-plus"></i> Daftarkan QRIS
          </button>
        </div>

        <?php else: ?>
        <!-- ADA PENDING TAPI BELUM PERNAH PUNYA QRIS AKTIF -->
        <div class="qris-empty" style="border-color:#fed7aa44;background:#fff7ed;">
          <i class="fa-solid fa-qrcode" style="color:#f97316;"></i>
          <p style="color:#c2410c;">QRIS Sedang Diproses</p>
          <span>
            Pengajuan QRIS kamu sedang ditinjau oleh admin.<br>
            Harap tunggu hingga diverifikasi untuk mulai menerima pembayaran.
          </span>
        </div>
        <?php endif; ?>

      </div>
      
    </main>
  </div>

  <!-- MODAL AJUKAN / GANTI QRIS -->
  <div class="modal-overlay" id="modalQris" onclick="if(event.target===this) tutupModal()">
    <div class="modal-box">
      <form method="POST" enctype="multipart/form-data">

        <div class="modal-header">
          <div>
            <div class="modal-title">
              <?= $qris_aktif ? '🔄 Ganti QRIS' : '📲 Daftarkan QRIS' ?>
            </div>
            <div class="modal-sub">
              <?php if ($qris_aktif): ?>
                Upload gambar QRIS baru. QRIS lama tetap aktif hingga admin menyetujui yang baru.
              <?php else: ?>
                Upload gambar QRIS dari aplikasi bank atau dompet digital kamu.
              <?php endif; ?>
            </div>
          </div>
          <button type="button" class="modal-close" onclick="tutupModal()">✕</button>
        </div>

        <div class="modal-field">
          <label class="modal-label">
            Gambar QRIS <span style="color:#e8650a">*</span>
          </label>
          <div class="upload-area" id="uploadArea">
            <input
              type="file"
              name="gambar_qris"
              id="inputGambarQris"
              accept="image/jpg,image/jpeg,image/png,image/webp"
              required
              onchange="previewUpload(this)"
            >
            <i class="fa-solid fa-cloud-arrow-up"></i>
            <p>Klik atau seret gambar QRIS ke sini</p>
            <small>Format: JPG, PNG, WEBP &nbsp;|&nbsp; Maks. 3 MB</small>
          </div>
          <div class="upload-preview" id="uploadPreview">
            <img id="uploadPreviewImg" src="" alt="Preview QRIS">
            <div class="upload-preview-name" id="uploadPreviewName"></div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-batal" onclick="tutupModal()">Batal</button>
          <button type="submit" name="simpan_qris" class="btn-simpan-qris">
            <i class="fa-solid fa-floppy-disk"></i>
            <?= $qris_aktif ? 'Ajukan QRIS Baru' : 'Daftarkan QRIS' ?>
          </button>
        </div>

      </form>
    </div>
  </div>

  <!-- MODAL PREVIEW GAMBAR QRIS -->
  <div class="modal-overlay" id="modalPreview" onclick="if(event.target===this) tutupPreview()">
    <div class="modal-box modal-box-preview">
      <div class="modal-header">
        <div class="modal-title">🔍 Pratinjau QRIS</div>
        <button type="button" class="modal-close" onclick="tutupPreview()">✕</button>
      </div>
      <img src="" id="previewFullImg" class="qris-full-img" alt="QRIS">
      <div class="qris-full-info">
        <strong><?= htmlspecialchars($nama_kantin) ?></strong>
        QRIS Terverifikasi
      </div>
    </div>
  </div>

  <script>
    /* SIDEBAR */
    function toggleSidebar() {
      const s = document.getElementById('sidebar');
      const o = document.getElementById('sidebarOverlay');
      if (s.classList.contains('open')) { closeSidebar(); }
      else { s.classList.add('open'); o.classList.add('show'); document.body.style.overflow = 'hidden'; }
    }
    function closeSidebar() {
      document.getElementById('sidebar').classList.remove('open');
      document.getElementById('sidebarOverlay').classList.remove('show');
      document.body.style.overflow = '';
    }

    /* MODAL FORM */
    function bukaModal() {
      document.getElementById('modalQris').classList.add('open');
      document.body.style.overflow = 'hidden';
    }
    function tutupModal() {
      document.getElementById('modalQris').classList.remove('open');
      document.body.style.overflow = '';
    }

    /* MODAL PREVIEW */
    function bukaPreview(src) {
      document.getElementById('previewFullImg').src = src;
      document.getElementById('modalPreview').classList.add('open');
      document.body.style.overflow = 'hidden';
    }
    function tutupPreview() {
      document.getElementById('modalPreview').classList.remove('open');
      document.body.style.overflow = '';
    }

    /* PREVIEW UPLOAD */
    function previewUpload(input) {
      const preview     = document.getElementById('uploadPreview');
      const previewImg  = document.getElementById('uploadPreviewImg');
      const previewName = document.getElementById('uploadPreviewName');
      if (input.files && input.files[0]) {
        const file   = input.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
          previewImg.src = e.target.result;
          previewName.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
          preview.classList.add('show');
        };
        reader.readAsDataURL(file);
      } else {
        preview.classList.remove('show');
      }
    }

    /* ESC tutup modal */
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') { tutupModal(); tutupPreview(); closeSidebar(); }
    });

    /* Auto buka modal jika ada error */
    <?php if (!empty($pesan_error) && isset($_POST['simpan_qris'])): ?>
    window.addEventListener('load', function() { bukaModal(); });
    <?php endif; ?>
  </script>

</body>
</html>