<?php
/**
 * rating.php
 * Halaman Nilai Produk — terhubung ke tabel `ulasan`
 * Akses: users/rating.php?id_transaksi=xxx
 *
 * Tabel ulasan: id, id_menu, id_user, bintang, komentar, created_at, foto, video
 */
session_start();
require 'koneksi.php';


if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

$id_user      = (int) $_SESSION['id_user'];
$id_transaksi = (int) ($_GET['id_transaksi'] ?? 0);

if (!$id_transaksi) {
    header("Location: pesan.php?tab=pesanan");
    exit;
}

// Ambil data transaksi + menu
$q = mysqli_prepare($konek,
    "SELECT t.id_transaksi, t.nama_makanan, t.nama_kantin, t.total, t.status,
            t.id_menu, m.foto AS foto_menu, m.harga
     FROM transaksi t
     LEFT JOIN menu_req m ON m.id = t.id_menu
     WHERE t.id_transaksi = ? AND t.id_user = ?");
mysqli_stmt_bind_param($q, "ii", $id_transaksi, $id_user);
mysqli_stmt_execute($q);
$trx = mysqli_fetch_assoc(mysqli_stmt_get_result($q));

if (!$trx) {
    header("Location: pesan.php?tab=pesanan");
    exit;
}

// Cek status harus lunas
if ($trx['status'] !== 'lunas') {
    header("Location: pesan.php?tab=pesanan");
    exit;
}

// Cek sudah pernah rating?
$cek = mysqli_prepare($konek,
    "SELECT id FROM ulasan WHERE id_menu = ? AND id_user = ?");
mysqli_stmt_bind_param($cek, "ii", $trx['id_menu'], $id_user);
mysqli_stmt_execute($cek);
$sudahRating = mysqli_fetch_assoc(mysqli_stmt_get_result($cek));

// Data user
$qu = mysqli_prepare($konek, "SELECT username, nama, pp FROM users WHERE id_user = ?");
mysqli_stmt_bind_param($qu, "i", $id_user);
mysqli_stmt_execute($qu);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($qu));
$username_user = $user['username'] ?? '';
$pp_user       = $user['pp']       ?? '';
$avatarSrc = $pp_user
    ? '../gambar/profil/' . htmlspecialchars($pp_user)
    : 'https://ui-avatars.com/api/?name=' . urlencode($username_user) . '&background=e8650a&color=fff&size=80';

// Keranjang count
$q_cart = mysqli_prepare($konek, "SELECT COALESCE(SUM(jumlah),0) AS total FROM keranjang WHERE id_user=?");
mysqli_stmt_bind_param($q_cart, "i", $id_user);
mysqli_stmt_execute($q_cart);
$total_keranjang = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($q_cart))['total'];

// Notif count
$q_notif = mysqli_prepare($konek,
    "SELECT COUNT(*) AS total FROM transaksi WHERE id_user=? AND is_read=0");
mysqli_stmt_bind_param($q_notif, "i", $id_user);
mysqli_stmt_execute($q_notif);
$total_notif = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($q_notif))['total'];

// Foto menu
$fotoMenu = $trx['foto_menu']
    ? '../penjual/uploads/' . $trx['foto_menu']
    : '../gambar/default-food.png';

// ── HANDLE SUBMIT ──
$sukses = false;
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bintang          = (int)   ($_POST['bintang']           ?? 0);
    $bintang_pelayan  = (int)   ($_POST['bintang_pelayan']   ?? 0);
    $bintang_kecepatan= (int)   ($_POST['bintang_kecepatan'] ?? 0);
    $komentar_rasa    = trim(   $_POST['komentar_rasa']       ?? '');
    $komentar_harga   = trim(   $_POST['komentar_harga']      ?? '');
    $sembunyikan      = isset(  $_POST['sembunyikan'])        ? 1 : 0;

    $komentar_gabung  = '';
    if ($komentar_rasa)  $komentar_gabung .= 'Rasa: ' . $komentar_rasa;
    if ($komentar_harga) $komentar_gabung .= ($komentar_gabung ? "\n" : '') . 'Harga: ' . $komentar_harga;

    if ($bintang < 1 || $bintang > 5) {
        $error = 'Pilih bintang terlebih dahulu.';
    } else {
        $foto_path  = null;
        $video_path = null;
        $uploadDir  = 'uploads/ulasan/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        // Upload foto
        if (!empty($_FILES['foto']['name'])) {
            $ext  = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp'];
            if (in_array($ext, $allowed) && $_FILES['foto']['size'] <= 5*1024*1024) {
                $fname = 'foto_' . $id_user . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $fname)) {
                    $foto_path = $fname;
                }
            }
        }

        // Upload video
        if (!empty($_FILES['video']['name'])) {
            $ext  = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
            $allowed = ['mp4','webm','mov'];
            if (in_array($ext, $allowed) && $_FILES['video']['size'] <= 50*1024*1024) {
                $fname = 'video_' . $id_user . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['video']['tmp_name'], $uploadDir . $fname)) {
                    $video_path = $fname;
                }
            }
        }

        // Insert / Update ulasan
        if ($sudahRating) {
            $ins = mysqli_prepare($konek,
                "UPDATE ulasan SET bintang=?, komentar=?, foto=?, video=? WHERE id_menu=? AND id_user=?");
            mysqli_stmt_bind_param($ins, "isssii",
                $bintang, $komentar_gabung, $foto_path, $video_path,
                $trx['id_menu'], $id_user);
        } else {
            $ins = mysqli_prepare($konek,
                "INSERT INTO ulasan (id_menu, id_user, bintang, komentar, foto, video)
                 VALUES (?,?,?,?,?,?)");
            mysqli_stmt_bind_param($ins, "iiisss",
                $trx['id_menu'], $id_user, $bintang, $komentar_gabung, $foto_path, $video_path);
        }

        if (mysqli_stmt_execute($ins)) {
            $sukses = true;
        } else {
            $error = 'Gagal menyimpan ulasan: ' . mysqli_error($konek);
        }
    }
}
// Simpan halaman terakhir
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
setcookie("last_page", $_SERVER['REQUEST_URI'], time() + (30 * 24 * 60 * 60), "/", "", $secure, false);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nilai Produk – E-Canteen Esemkita</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Righteous&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="pesan.css">
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Plus Jakarta Sans',sans-serif; background:#f5f5f5; color:#222; }

        /* ── PAGE ── */
        .page {
            max-width: 800px;
            margin: 32px auto 60px;
            padding: 0 20px;
        }
        .page-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 24px;
        }
        .back-btn {
            width: 48px; height: 48px;
            border-radius: 15px; border: none;
            background: #fff; color: #e8650a;
            font-size: 18px; cursor: pointer;
            box-shadow: 0 6px 20px rgba(0,0,0,.08);
            transition: .2s; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
        }
        .back-btn:hover { transform: translateY(-2px); }
        .page-header h1 { font-size: 26px; font-weight: 700; }

        /* ── CARD ── */
        .card {
            background: #fff;
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 8px 28px rgba(0,0,0,.05);
            margin-bottom: 18px;
        }

        /* ── PRODUCT TOP ── */
        .product-top {
            display: flex; align-items: center; gap: 16px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }
        .product-top img {
            width: 85px; height: 85px;
            border-radius: 16px; object-fit: cover;
            flex-shrink: 0;
        }
        .product-info h2 { font-size: 18px; font-weight: 700; margin-bottom: 4px; color:#1e293b; }
        .product-info .kantin { font-size: 13px; color: #888; display:flex; align-items:center; gap:4px; }
        .product-info .total  { font-size: 14px; font-weight: 700; color: #e8650a; margin-top: 4px; }

        /* ── SUDAH RATING BANNER ── */
        .sudah-rating-banner {
            background: linear-gradient(135deg,#f0fdf4,#dcfce7);
            border: 1.5px solid #86efac;
            border-radius: 14px; padding: 16px 20px;
            display: flex; align-items: center; gap: 12px;
            font-size: 14px; color: #15803d; font-weight: 600;
            margin-bottom: 18px;
        }
        .sudah-rating-banner i { font-size: 20px; }

        /* ── SECTION TITLE ── */
        .section-title {
            font-size: 15px; font-weight: 700;
            color: #1e293b; margin-bottom: 14px;
        }

        /* ── STARS ── */
        .stars { display: flex; gap: 8px; margin-bottom: 8px; }
        .stars i {
            font-size: 36px; color: #ffb800;
            cursor: pointer; transition: .15s;
        }
        .stars i:hover { transform: scale(1.1); }
        .star-label {
            font-size: 13px; color: #94a3b8;
            margin-bottom: 20px; min-height: 20px;
            font-weight: 600;
        }

        .small-stars { display: flex; gap: 5px; }
        .small-stars i {
            font-size: 22px; color: #ffb800;
            cursor: pointer; transition: .15s;
        }
        .small-stars i:hover { transform: scale(1.1); }

        .rating-item {
            display: flex; align-items: center;
            gap: 14px; flex-wrap: wrap;
        }
        .rating-item span { font-size: 14px; font-weight: 600; min-width: 140px; }

        .other-rating-wrap {
            display: flex; align-items: center;
            gap: 28px; flex-wrap: wrap;
        }

        /* ── UPLOAD GRID ── */
        .upload-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 14px; margin-bottom: 20px;
        }
        .upload-box {
            height: 160px; border-radius: 18px;
            border: 2px dashed #ddd; background: #fafafa;
            display: flex; align-items: center;
            justify-content: center; cursor: pointer;
            overflow: hidden; transition: .2s;
        }
        .upload-box:hover { border-color: #e8650a; background: #fff6ef; }
        .upload-content {
            display: flex; flex-direction: column;
            align-items: center; gap: 10px;
        }
        .upload-content i { font-size: 32px; color: #bbb; }
        .upload-content span { font-size: 13px; font-weight: 600; color: #888; }
        .preview-img, .preview-video { width:100%; height:100%; object-fit:cover; }

        /* ── TEXTAREA ── */
        .input-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 14px; }
        .input-group label { font-size: 14px; font-weight: 700; color: #333; }
        .input-group textarea {
            width: 100%; min-height: 100px;
            border-radius: 14px; border: 1.5px solid #eee;
            background: #fafafa; padding: 14px;
            resize: none; outline: none;
            font-family: inherit; font-size: 14px;
            line-height: 1.7; transition: .2s; color: #1e293b;
        }
        .input-group textarea:focus {
            border-color: #e8650a; background: #fff;
            box-shadow: 0 0 0 4px rgba(232,101,10,.1);
        }

        /* ── FOOTER ── */
        .review-footer {
            display: flex; align-items: center;
            justify-content: space-between; gap: 16px;
            margin-top: 4px;
        }
        .checkbox { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #666; cursor: pointer; }
        .checkbox input { width: 16px; height: 16px; accent-color: #e8650a; }
        .char-count { font-size: 12px; color: #bbb; font-weight: 600; }

        /* ── ALERT ── */
        .alert { border-radius: 12px; padding: 14px 18px; font-size: 14px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
        .alert-error   { background: #fef2f2; color: #b91c1c; border: 1.5px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #15803d; border: 1.5px solid #86efac; }

        /* ── SUBMIT ── */
        .submit-wrap { margin-top: 24px; display: flex; justify-content: flex-end; }
        .submit-btn {
            height: 52px; padding: 0 36px;
            border: none; border-radius: 14px;
            background: linear-gradient(135deg,#f98020,#e8650a);
            color: #fff; font-size: 15px; font-weight: 700;
            cursor: pointer; transition: .2s;
            box-shadow: 0 10px 24px rgba(232,101,10,.25);
            display: flex; align-items: center; gap: 8px;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 14px 32px rgba(232,101,10,.3); }
        .submit-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }

        /* ── SUKSES OVERLAY ── */
        .sukses-wrap {
            text-align: center; padding: 48px 20px;
        }
        .sukses-icon {
            font-size: 64px; margin-bottom: 16px;
            animation: bounceIn .5s ease;
        }
        @keyframes bounceIn {
            0%   { transform: scale(0.5); opacity:0; }
            70%  { transform: scale(1.1); opacity:1; }
            100% { transform: scale(1); }
        }
        .sukses-wrap h2 { font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 8px; }
        .sukses-wrap p { font-size: 14px; color: #94a3b8; margin-bottom: 28px; }
        .sukses-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 12px 28px; border-radius: 14px;
            background: linear-gradient(135deg,#f98020,#e8650a);
            color: #fff; font-size: 14px; font-weight: 700;
            text-decoration: none; transition: .2s;
        }
        .sukses-btn:hover { transform: translateY(-2px); }

        /* ── RESPONSIVE ── */
        @media (max-width: 600px) {
            .page { padding: 0 12px; }
            .page-header h1 { font-size: 20px; }
            .stars i { font-size: 30px; }
            .upload-grid { grid-template-columns: 1fr; }
            .other-rating-wrap { flex-direction: column; align-items: flex-start; gap: 18px; }
            .review-footer { flex-direction: column; align-items: flex-start; }
            .submit-wrap { justify-content: stretch; }
            .submit-btn { width: 100%; justify-content: center; }
            .rating-item span { min-width: unset; }
            .product-top img { width: 64px; height: 64px; }
        }
    </style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
    <div class="top-left">
        <a href="user.php">Home</a><span class="sep">|</span>
        <a href="menu.php">Menu</a><span class="sep">|</span>
        <a href="pesan.php?tab=pesanan">Pesanan</a>
    </div>
    <div class="top-right">
        <a href="pesan.php?tab=notif">
            <i class="fa-regular fa-bell"></i> Notifikasi
            <?php if ($total_notif > 0): ?>
                <span class="notif-pill"><?= $total_notif ?></span>
            <?php endif; ?>
        </a>
        <a href="#"><i class="fa-regular fa-circle-question"></i> Bantuan</a>
        <div class="lang">
            <i class="fa-solid fa-globe"></i>
            <span>Bahasa Indonesia</span>
            <i class="fa-solid fa-chevron-down"></i>
        </div>
        <div class="profile">
            <img src="<?= $avatarSrc ?>"
                 alt="<?= htmlspecialchars($username_user) ?>"
                 onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($username_user) ?>&background=e8650a&color=fff&size=80'">
            <span><?= htmlspecialchars($username_user) ?></span>
            <div class="profile-drop">
                <a href="pesan.php?tab=akun"><i class="fa-regular fa-user"></i> Akun Saya</a>
                <div class="drop-line"></div>
                <a href="logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Log Out</a>
            </div>
        </div>
    </div>
</div>

<!-- NAVBAR DESKTOP -->
<div class="navbar orange-bg">
    <div class="navbar-texture"></div>
    <div class="orb1"></div>
    <div class="orb2"></div>
    <div class="logo">
        <div class="logo-img-wrap"><img src="../gambar/logo.png" alt="E-Canteen"></div>
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
        <a href="keranjang.php" style="text-decoration:none;color:inherit">
            <div class="icon-btn">
                <i class="fa-solid fa-cart-shopping"></i>
                <?php if ($total_keranjang > 0): ?>
                    <div class="icon-badge"><?= $total_keranjang ?></div>
                <?php endif; ?>
            </div>
        </a>
    </div>
</div>

<!-- MOBILE TOP NAV -->
<div class="mobile-nav">
    <div class="mob-top orange-bg">
        <div class="mob-orb"></div>
        <button class="mob-hamburger" id="mobHamburger" onclick="toggleDrawer()" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
        <div class="mob-logo">
            <img src="../gambar/logo.png" alt="Logo">
            <div class="mob-logo-text">
                <span class="mob-logo-title">E-Canteen</span>
                <span class="mob-logo-sub">Esemkita</span>
            </div>
        </div>
        <div class="mob-icons">
            <a href="keranjang.php" style="text-decoration:none;color:inherit">
                <div class="mob-icon-btn">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <?php if ($total_keranjang > 0): ?>
                        <div class="mob-badge"><?= $total_keranjang ?></div>
                    <?php endif; ?>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- PAGE -->
<div class="page">

    <div class="page-header">
        <h1>Nilai Produk</h1>
    </div>

    <?php if ($sukses): ?>
    <!-- SUKSES -->
    <div class="card">
        <div class="sukses-wrap">
            <div class="sukses-icon">⭐</div>
            <h2>Terima Kasih!</h2>
            <p>Ulasanmu untuk <strong><?= htmlspecialchars($trx['nama_makanan']) ?></strong> sudah berhasil dikirim.<br>
               Pendapatmu sangat membantu pembeli lainnya!</p>
            <a href="pesan.php?tab=pesanan" class="sukses-btn" onclick="localStorage.setItem('rated_trx_<?= $id_transaksi ?>','1')">
                <i class="fa-solid fa-bag-shopping"></i> Kembali ke Pesanan
            </a>
        </div>
    </div>
    <script>
        // Simpan otomatis ke localStorage agar tombol rating langsung hilang saat kembali
        localStorage.setItem('rated_trx_<?= $id_transaksi ?>', '1');
    </script>

    <?php else: ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($sudahRating): ?>
        <div class="sudah-rating-banner">
            <i class="fa-solid fa-circle-check"></i>
            Kamu sudah memberikan rating untuk produk ini. Kamu bisa memperbarui ulasanmu di bawah.
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="ratingForm">

        <!-- CARD UTAMA -->
        <div class="card">

            <!-- Info Produk -->
            <div class="product-top">
                <img src="<?= htmlspecialchars($fotoMenu) ?>"
                     onerror="this.src='../gambar/default-food.png'"
                     alt="<?= htmlspecialchars($trx['nama_makanan']) ?>">
                <div class="product-info">
                    <h2><?= htmlspecialchars($trx['nama_makanan']) ?></h2>
                    <div class="kantin">
                        <i class="fa-solid fa-store" style="color:#e8650a"></i>
                        <?= htmlspecialchars($trx['nama_kantin']) ?>
                    </div>
                    <div class="total">
                        Rp <?= number_format($trx['total'], 0, ',', '.') ?>
                    </div>
                </div>
            </div>

            <!-- Bintang Utama -->
            <div class="section-title">Nilai Produk</div>
            <div class="stars" id="mainStars">
                <i class="fa-regular fa-star" data-val="1"></i>
                <i class="fa-regular fa-star" data-val="2"></i>
                <i class="fa-regular fa-star" data-val="3"></i>
                <i class="fa-regular fa-star" data-val="4"></i>
                <i class="fa-regular fa-star" data-val="5"></i>
            </div>
            <div class="star-label" id="starLabel">Ketuk bintang untuk memberi nilai</div>
            <input type="hidden" name="bintang" id="bintangInput" value="0">

            <!-- Upload Foto & Video -->
            <div class="section-title">Tambahkan Foto atau Video <span style="font-weight:400;color:#94a3b8;font-size:12px">(opsional)</span></div>
            <div class="upload-grid">
                <!-- FOTO -->
                <label class="upload-box" for="photoInput">
                    <input type="file" id="photoInput" name="foto" accept="image/*" hidden>
                    <div id="photoPreview" class="upload-content">
                        <i class="fa-regular fa-image"></i>
                        <span>Upload Foto</span>
                    </div>
                </label>
                <!-- VIDEO -->
                <label class="upload-box" for="videoInput">
                    <input type="file" id="videoInput" name="video" accept="video/*" hidden>
                    <div id="videoPreview" class="upload-content">
                        <i class="fa-solid fa-video"></i>
                        <span>Upload Video</span>
                    </div>
                </label>
            </div>

            <!-- Ulasan Teks -->
            <div class="section-title">Tulis Ulasan</div>
            <div class="input-group">
                <label>Rasa</label>
                <textarea id="fiturInput" name="komentar_rasa"
                    placeholder="Tulis bagaimana pendapatmu tentang rasanya..."></textarea>
            </div>
            <div class="input-group">
                <label>Sepadan Dengan Harga</label>
                <textarea id="hargaInput" name="komentar_harga"
                    placeholder="Bagaimana menurutmu soal harga produk ini..."></textarea>
            </div>
            <div class="review-footer">
                <label class="checkbox">
                    <input type="checkbox" name="sembunyikan">
                    Sembunyikan username pada penilaian
                </label>
                <div class="char-count" id="charCount">0 karakter</div>
            </div>
        </div>

        <!-- CARD PENILAIAN LAINNYA -->
        <div class="card">
            <div class="section-title" style="margin-bottom:18px">Penilaian Lainnya</div>
            <div class="other-rating-wrap">
                <div class="rating-item">
                    <span>Pelayanan Penjual</span>
                    <div class="small-stars" id="starsPelayan">
                        <i class="fa-regular fa-star"></i>
                        <i class="fa-regular fa-star"></i>
                        <i class="fa-regular fa-star"></i>
                        <i class="fa-regular fa-star"></i>
                        <i class="fa-regular fa-star"></i>
                    </div>
                    <input type="hidden" name="bintang_pelayan" id="bintangPelayan" value="0">
                </div>
                <div class="rating-item">
                    <span>Kecepatan Penyajian</span>
                    <div class="small-stars" id="starsKecepatan">
                        <i class="fa-regular fa-star"></i>
                        <i class="fa-regular fa-star"></i>
                        <i class="fa-regular fa-star"></i>
                        <i class="fa-regular fa-star"></i>
                        <i class="fa-regular fa-star"></i>
                    </div>
                    <input type="hidden" name="bintang_kecepatan" id="bintangKecepatan" value="0">
                </div>
            </div>

            <!-- SUBMIT -->
            <div class="submit-wrap">
                <button type="submit" class="submit-btn" id="submitBtn">
                    <i class="fa-solid fa-paper-plane"></i> KIRIM PENILAIAN
                </button>
            </div>
        </div>

    </form>
    <?php endif; ?>

</div>

<!-- BOTTOM NAV MOBILE (pasif di halaman ini) -->
<div class="mob-bottom-nav">
    <a href="user.php" class="mob-nav-item">
        <i class="fa-solid fa-house"></i><span>Home</span>
    </a>
    <a href="menu.php" class="mob-nav-item">
        <i class="fa-solid fa-bowl-food"></i><span>Menu</span>
    </a>
    <a href="pesan.php?tab=pesanan" class="mob-nav-item active">
        <i class="fa-solid fa-bag-shopping"></i><span>Pesanan</span>
    </a>
    <a href="pesan.php?tab=akun" class="mob-nav-item">
        <i class="fa-regular fa-user"></i><span>Akun</span>
    </a>
</div>
<div class="mob-bottom-spacer"></div>

<script>
// =============================================
// MAIN STARS
// =============================================
const mainStarsEl = document.querySelectorAll('#mainStars i');
const bintangInput = document.getElementById('bintangInput');
const starLabel    = document.getElementById('starLabel');
let mainRating = 0;

const starLabels = ['','Sangat Buruk 😞','Buruk 😕','Cukup 😐','Bagus 😊','Sangat Bagus 🤩'];

function updateMainStars(val) {
    mainStarsEl.forEach((s, i) => {
        s.className = i < val ? 'fa-solid fa-star' : 'fa-regular fa-star';
    });
}

mainStarsEl.forEach((star, idx) => {
    star.addEventListener('click', () => {
        mainRating = idx + 1;
        bintangInput.value = mainRating;
        updateMainStars(mainRating);
        starLabel.textContent = starLabels[mainRating];
        starLabel.style.color = ['','#ef4444','#f97316','#f59e0b','#22c55e','#10b981'][mainRating];
    });
    star.addEventListener('mouseover', () => updateMainStars(idx + 1));
    star.addEventListener('mouseleave', () => updateMainStars(mainRating));
});

// =============================================
// SMALL STARS (generic)
// =============================================
function initSmallStars(containerId, inputId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    const stars  = container.querySelectorAll('i');
    const input  = document.getElementById(inputId);
    let rating   = 0;

    function update(val) {
        stars.forEach((s, i) => {
            s.className = i < val ? 'fa-solid fa-star' : 'fa-regular fa-star';
        });
    }
    stars.forEach((s, i) => {
        s.addEventListener('click',     () => { rating = i+1; input.value = rating; update(rating); });
        s.addEventListener('mouseover', () => update(i+1));
        s.addEventListener('mouseleave',() => update(rating));
    });
}
initSmallStars('starsPelayan',   'bintangPelayan');
initSmallStars('starsKecepatan', 'bintangKecepatan');

// =============================================
// CHARACTER COUNT
// =============================================
const fiturInput = document.getElementById('fiturInput');
const hargaInput = document.getElementById('hargaInput');
const charCount  = document.getElementById('charCount');

function updateCount() {
    const total = (fiturInput?.value.length||0) + (hargaInput?.value.length||0);
    if (charCount) charCount.textContent = total + ' karakter';
}
fiturInput?.addEventListener('input', updateCount);
hargaInput?.addEventListener('input', updateCount);

// =============================================
// PHOTO PREVIEW
// =============================================
document.getElementById('photoInput')?.addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('photoPreview').innerHTML =
            `<img src="${e.target.result}" class="preview-img">`;
    };
    reader.readAsDataURL(file);
});

// =============================================
// VIDEO PREVIEW
// =============================================
document.getElementById('videoInput')?.addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    const url = URL.createObjectURL(file);
    document.getElementById('videoPreview').innerHTML =
        `<video class="preview-video" controls><source src="${url}" type="${file.type}"></video>`;
});

// =============================================
// FORM SUBMIT — validasi bintang
// =============================================
document.getElementById('ratingForm')?.addEventListener('submit', function(e) {
    if (parseInt(document.getElementById('bintangInput').value) < 1) {
        e.preventDefault();
        // Flash warning di star label
        if (starLabel) {
            starLabel.textContent = '⚠️ Pilih bintang terlebih dahulu!';
            starLabel.style.color = '#ef4444';
            mainStarsEl.forEach(s => {
                s.style.animation = 'shake .3s ease';
                setTimeout(() => s.style.animation = '', 400);
            });
        }
        return;
    }
    const btn = document.getElementById('submitBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mengirim...';
    }
});

// Shake keyframe
const style = document.createElement('style');
style.textContent = `@keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-4px)} 75%{transform:translateX(4px)} }`;
document.head.appendChild(style);
// Simpan halaman sekarang
localStorage.setItem("last_page", window.location.href);
</script>
</body>
</html>