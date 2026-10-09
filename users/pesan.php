<?php
/* ══════════════════════════════════════════
   E-CANTEEN ESEMKITA — pesan.php
   Halaman Pesanan, Notifikasi & Akun
   Mobile: hamburger drawer nav
   UPDATE: Grouping multi-menu per kantin + struk grup
══════════════════════════════════════════ */
session_start();
include 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

$id_user = $_SESSION['id_user'];

/* ── Data user ── */
$q = mysqli_prepare($konek, "
    SELECT id_user, username, nama, email, pp, no_hp, gender, tanggal_lahir
    FROM users WHERE id_user = ?
");
mysqli_stmt_bind_param($q, "i", $id_user);
mysqli_stmt_execute($q);
$user          = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
$username_user = $user['username']      ?? '';
$pp_user       = $user['pp']            ?? '';
$nama          = $user['nama']          ?? '';
$email         = $user['email']         ?? '';
$no_hp         = $user['no_hp']         ?? '';
$gender        = $user['gender']        ?? '';
$tgl_lahir     = $user['tanggal_lahir'] ?? '';

/* ── Jumlah keranjang ── */
$q_cart = mysqli_prepare($konek, "SELECT COALESCE(SUM(jumlah),0) AS total FROM keranjang WHERE id_user=?");
mysqli_stmt_bind_param($q_cart, "i", $id_user);
mysqli_stmt_execute($q_cart);
$total_keranjang = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($q_cart))['total'];

/* ── Jumlah notif belum dibaca ── */
$q_notif = mysqli_prepare($konek, "
    SELECT COUNT(*) AS total FROM transaksi
    WHERE id_user = ? AND status IN ('pending', 'dimasak', 'siap', 'selesai')
");
mysqli_stmt_bind_param($q_notif, "i", $id_user);
mysqli_stmt_execute($q_notif);
$total_notif = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($q_notif))['total'];
mysqli_stmt_close($q_notif);


/* ── Helper: avatar URL ── */
function avatarUrl(string $pp, string $username): string {
    if ($pp !== '') return '../gambar/profil/' . htmlspecialchars($pp);
    return 'https://ui-avatars.com/api/?name=' . urlencode($username) . '&background=e8650a&color=fff&size=80';
}
$avatarSrc      = avatarUrl($pp_user, $username_user);
$avatarFallback = 'https://ui-avatars.com/api/?name=' . urlencode($username_user) . '&background=e8650a&color=fff&size=80';

/* ── Active tab dari URL ── */
$tab = $_GET['tab'] ?? 'akun';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan – E-Canteen Esemkita</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Righteous&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="pesan.css">
    <link rel="stylesheet" href="../custom_confirm.css">

    <style>
        /* ── Avatar zoom hint ── */
        .profile-avatar {
            position: relative;
            cursor: pointer;
        }
        .avatar-zoom-hint {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: rgba(0,0,0,0);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 22px;
            opacity: 0;
            transition: background .22s, opacity .22s;
        }
        .profile-avatar:hover .avatar-zoom-hint {
            background: rgba(0,0,0,.38);
            opacity: 1;
        }
        .profile-avatar:active .avatar-zoom-hint {
            background: rgba(0,0,0,.55);
        }

        /* ── Lightbox ── */
        .foto-lightbox {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.85);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            visibility: hidden;
            transition: opacity .28s, visibility .28s;
        }
        .foto-lightbox.open {
            opacity: 1;
            visibility: visible;
        }
        .foto-lightbox-inner {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            position: relative;
            animation: lbIn .3s cubic-bezier(.34,1.2,.64,1);
        }
        @keyframes lbIn {
            from { transform: scale(.82); opacity: 0; }
            to   { transform: scale(1);  opacity: 1; }
        }
        .foto-lightbox-close {
            position: absolute;
            top: -14px;
            right: -14px;
            width: 36px; height: 36px;
            border-radius: 50%;
            background: rgba(255,255,255,.15);
            border: 1.5px solid rgba(255,255,255,.3);
            color: #fff;
            font-size: 15px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            transition: background .18s;
            z-index: 1;
        }
        .foto-lightbox-close:hover { background: rgba(255,255,255,.28); }
        .foto-lightbox img {
            width: min(320px, 78vw);
            height: min(320px, 78vw);
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255,255,255,.25);
            box-shadow: 0 20px 60px rgba(0,0,0,.5), 0 0 0 1px rgba(255,255,255,.1);
            display: block;
        }
        .foto-lightbox-name {
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: .3px;
            text-shadow: 0 1px 6px rgba(0,0,0,.4);
        }
        .foto-lightbox-change {
            background: rgba(255,255,255,.15);
            border: 1.5px solid rgba(255,255,255,.3);
            color: #fff;
            padding: 9px 22px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Plus Jakarta Sans', sans-serif;
            display: flex; align-items: center; gap: 8px;
            transition: background .18s;
        }
        .foto-lightbox-change:hover { background: rgba(255,255,255,.26); }

        /* ── MODAL LOGOUT ── */
        .logout-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.45);
            backdrop-filter: blur(4px);
            z-index: 9000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .logout-modal-overlay.open { display: flex; }
        .logout-modal-box {
            background: #fff;
            border-radius: 20px;
            padding: 32px 28px 24px;
            max-width: 380px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,.2);
            animation: modalIn .3s cubic-bezier(.34,1.2,.64,1);
        }
        @keyframes modalIn {
            from { opacity: 0; transform: scale(.9) translateY(20px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }
        .logout-modal-icon {
            width: 60px; height: 60px;
            border-radius: 50%;
            background: #fff1f1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #dc2626;
            margin: 0 auto 18px;
        }
        .logout-modal-box h3 {
            font-size: 18px;
            font-weight: 800;
            color: #1a0a00;
            margin-bottom: 10px;
        }
        .logout-modal-box p {
            font-size: 13.5px;
            color: #7a5a40;
            line-height: 1.6;
        }
        .logout-modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 22px;
        }
        .btn-modal-cancel {
            flex: 1;
            padding: 12px;
            border-radius: 10px;
            border: 1.5px solid #e8d8c8;
            background: transparent;
            color: #a07050;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: background .15s;
        }
        .btn-modal-cancel:hover { background: #fdf3ec; }
        .btn-modal-logout {
            flex: 1;
            padding: 12px;
            border-radius: 10px;
            border: none;
            background: #dc2626;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: background .15s;
        }
        .btn-modal-logout:hover { background: #b91c1c; }

        /* ── Logout Card ── */
        .logout-section-card {
            background: #fff;
            border-radius: 14px;
            border: 1.5px solid #f0ddd0;
            overflow: hidden;
            margin-top: 16px;
            box-shadow: 0 4px 20px rgba(200,75,17,.08), 0 1px 6px rgba(0,0,0,.04);
        }
        .logout-section-header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 18px 20px 14px;
            border-bottom: 1px solid #fdf0e8;
        }
        .logout-section-header-icon {
            width: 40px; height: 40px;
            border-radius: 11px;
            background: #ffebee;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #dc2626;
            font-size: 16px;
            flex-shrink: 0;
        }
        .logout-section-header h3 {
            font-size: 15px;
            font-weight: 800;
            color: #1a0a00;
            margin-bottom: 2px;
        }
        .logout-section-header p {
            font-size: 12px;
            color: #a07050;
        }
        .logout-section-body { padding: 6px 0; }
        .logout-action-btn {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 20px;
            background: transparent;
            border: none;
            cursor: pointer;
            text-align: left;
            transition: background .15s;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .logout-action-btn:hover { background: #fff5f5; }
        .logout-action-icon {
            width: 40px; height: 40px;
            border-radius: 11px;
            background: #fff1f1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #dc2626;
            font-size: 16px;
            flex-shrink: 0;
        }
        .logout-action-label { font-size: 14px; font-weight: 700; color: #dc2626; }
        .logout-action-sub   { font-size: 12px; color: #a07070; margin-top: 2px; }
        .logout-action-arrow { margin-left: auto; color: #dc2626; font-size: 13px; opacity: .6; }

        /* ══════════════════════════════════════
           GROUP CARD — multi-item per kantin
        ══════════════════════════════════════ */
        .order-group-card {
            background: #fff;
            border-radius: 16px;
            border: 1.5px solid #f0e0d0;
            box-shadow: 0 4px 20px rgba(200,75,17,.09);
            overflow: hidden;
            margin-bottom: 14px;
            transition: box-shadow .2s, transform .2s;
        }
        .order-group-card:hover {
            box-shadow: 0 8px 32px rgba(200,75,17,.15);
            transform: translateY(-1px);
        }

        /* header strip kantin */
        .group-card-header {
            background: linear-gradient(90deg, #fff7ed, #fff3e8);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #fde8d0;
        }
        .group-kantin-icon {
            width: 36px; height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #f97316, #ea580c);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 16px; flex-shrink: 0;
        }
        .group-kantin-name {
            font-size: 14px;
            font-weight: 800;
            color: #7c2d12;
            flex: 1;
        }
        .group-kantin-date {
            font-size: 11px;
            color: #94a3b8;
        }
        .group-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 11px;
            border-radius: 50px;
            font-size: 11.5px;
            font-weight: 700;
        }

        /* item rows dalam card grup */
        .group-items-wrap {
            padding: 10px 16px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .group-item-row {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fdf8f4;
            border: 1px solid #fde8d0;
            border-radius: 10px;
            padding: 9px 12px;
        }
        .group-item-img {
            width: 44px; height: 44px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
            background: #f0f0f0;
        }
        .group-item-info {
            flex: 1;
            min-width: 0;
        }
        .group-item-name {
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .group-item-meta {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 2px;
        }
        .group-item-antrian {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #fff7ed;
            border: 1.5px dashed #f97316;
            color: #c2410c;
            font-size: 10.5px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 6px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .group-item-price {
            font-size: 13px;
            font-weight: 800;
            color: #e8650a;
            flex-shrink: 0;
            text-align: right;
            min-width: 70px;
        }

        /* ── Tombol batal per-item ── */
        .btn-batal-item {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 10px;
            border-radius: 8px;
            border: 1.5px solid #fca5a5;
            background: #fff1f1;
            color: #dc2626;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            font-family: 'Plus Jakarta Sans', sans-serif;
            flex-shrink: 0;
            transition: background .15s, border-color .15s;
            white-space: nowrap;
        }
        .btn-batal-item:hover {
            background: #fee2e2;
            border-color: #f87171;
        }
        .btn-batal-item:active {
            background: #fecaca;
        }

        /* footer card grup */
        .group-card-footer {
            padding: 12px 16px;
            border-top: 1px solid #fde8d0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            background: #fffbf7;
        }
        .group-total-wrap {}
        .group-total-label { font-size: 11px; color: #94a3b8; font-weight: 600; }
        .group-total-val   { font-size: 17px; font-weight: 800; color: #e8650a; margin-top: 1px; }
        .group-actions     { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

        /* ── Struk multi-item ── */
        .struk-popup {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.7);
            z-index: 9000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(6px);
        }
        .struk-popup.show { display: flex; }
        .struk-box {
            background: #fff;
            border-radius: 20px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
            max-width: 440px;
            width: 95%;
            max-height: 92vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 24px 60px rgba(0,0,0,.35);
        }
        .close-struk {
            position: absolute;
            top: 14px; right: 14px;
            width: 34px; height: 34px;
            border-radius: 50%;
            background: #f1f5f9;
            border: none;
            font-size: 15px;
            color: #64748b;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background .15s;
        }
        .close-struk:hover { background: #e2e8f0; }
        #strukCanvas {
            max-width: 100%;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,.12);
        }
        .download-popup-btn {
            padding: 12px 28px;
            border-radius: 12px;
            background: linear-gradient(135deg, #f97316, #ea580c);
            color: #fff;
            border: none;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            font-family: 'Plus Jakarta Sans', sans-serif;
            display: flex; align-items: center; gap: 8px;
            transition: filter .2s;
        }
        .download-popup-btn:hover { filter: brightness(1.1); }

        /* status badge colors */
        .badge-pending  { background: #fff7ed; color: #c2410c; }
        .badge-diproses { background: #fffbeb; color: #b45309; }
        .badge-siap     { background: #eff6ff; color: #1d4ed8; }
        .badge-selesai  { background: #f5f3ff; color: #7c3aed; }
        .badge-lunas    { background: #f0fdf4; color: #15803d; }
        .badge-batal    { background: #fef2f2; color: #b91c1c; }

        .info-tidak-batal {
            font-size: 11px; color: #94a3b8;
            display: flex; align-items: center; gap: 4px;
            padding: 4px 10px; border-radius: 50px; background: #f1f5f9;
        }
        .info-siap {
            font-size: 12px; color: #1d4ed8; font-weight: 700;
            display: flex; align-items: center; gap: 4px;
            padding: 4px 12px; border-radius: 50px; background: #eff6ff;
            animation: pulse-blue 1.5s infinite;
        }
        @keyframes pulse-blue { 0%,100%{opacity:1} 50%{opacity:.6} }
        .info-tunggu-lunas {
            font-size: 11px; color: #7c3aed;
            display: flex; align-items: center; gap: 4px;
            padding: 4px 10px; border-radius: 50px; background: #f5f3ff;
        }
        .struk-notice {
            background: #f5f3ff; border: 1px solid #e9d5ff;
            border-radius: 10px; padding: 10px 14px;
            font-size: 12px; color: #7c3aed;
            display: flex; align-items: center; gap: 8px; margin-top: 6px;
        }
        .lunas-notice {
            background: #f0fdf4; border: 1px solid #bbf7d0;
            border-radius: 10px; padding: 10px 14px;
            font-size: 12px; color: #15803d;
            display: flex; align-items: center; gap: 8px; margin-top: 6px;
        }
        a.btn-nilai {
            cursor: pointer;
        }
    </style>
</head>
<body>

<!-- ══════════════════════════════════════════
     TOPBAR (desktop only)
══════════════════════════════════════════ -->
<div class="topbar">
    <div class="top-left">
        <a href="user.php">Home</a><span class="sep">|</span>
        <a href="menu.php">Menu</a><span class="sep">|</span>
        <a href="pesan.php?tab=pesanan">Pesanan</a>
    </div>
    <div class="top-right">
        <a href="pesan.php?tab=notif">
            <i class="fa-regular fa-bell"></i> Notifikasi
        </a>
        <a href="#"><i class="fa-regular fa-circle-question"></i> Bantuan</a>
        <div class="lang">
            <i class="fa-solid fa-globe"></i>
            <span>Bahasa Indonesia</span>
            <i class="fa-solid fa-chevron-down"></i>
            <div class="lang-drop">
                <a href="" class="active"><i class="fa-solid fa-check"></i> Bahasa Indonesia</a>
                <a href="">English</a>
            </div>
        </div>
        <div class="profile">
            <img src="<?= $avatarSrc ?>"
                 alt="<?= htmlspecialchars($username_user) ?>"
                 onerror="this.src='<?= $avatarFallback ?>'">
            <span><?= htmlspecialchars($username_user) ?></span>
            <div class="profile-drop">
                <a href="pesan.php?tab=akun">
                    <i class="fa-regular fa-user"></i> Akun Saya
                </a>
                <div class="drop-line"></div>
                <a href="logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Log Out</a>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     NAVBAR DESKTOP
══════════════════════════════════════════ -->
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
        <button onclick="openChatPopup()" class="icon-btn notif-chat-btn" style="background:none;border:none;cursor:pointer;">
            <i class="fa-solid fa-comment-dots"></i>
            <span class="chat-badge" id="chatBadge" style="display:none"></span>
        </button>
    </div>
</div>

<!-- ══════════════════════════════════════════
     MOBILE TOP NAV
══════════════════════════════════════════ -->
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
            <div class="mob-icon-btn" style="position:relative" onclick="mobileSwitchSection('notif')">
                <i class="fa-regular fa-bell"></i>
                <?php if ($total_notif > 0): ?>
                    <div class="mob-badge"><?= $total_notif ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     DRAWER OVERLAY + DRAWER MENU (mobile)
══════════════════════════════════════════ -->
<div class="drawer-overlay" id="drawerOverlay" onclick="closeDrawer()"></div>

<div class="drawer" id="drawer">
    <div class="drawer-header orange-bg">
        <div class="drawer-orb"></div>
        <div class="drawer-avatar">
            <img src="<?= $avatarSrc ?>"
                 alt="<?= htmlspecialchars($username_user) ?>"
                 onerror="this.src='<?= $avatarFallback ?>'">
        </div>
        <div class="drawer-user-info">
            <h4><?= htmlspecialchars($username_user) ?></h4>
            <p><?= htmlspecialchars($email ?: 'Atur email kamu') ?></p>
        </div>
        <button class="drawer-close" onclick="closeDrawer()"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <nav class="drawer-nav">
        <div class="drawer-section-label">Menu Utama</div>

        <a href="user.php" class="drawer-item">
            <span class="drawer-item-icon orange"><i class="fa-solid fa-house"></i></span>
            <span>Home</span>
        </a>
        <a href="menu.php" class="drawer-item">
            <span class="drawer-item-icon green"><i class="fa-solid fa-bowl-food"></i></span>
            <span>Menu</span>
        </a>

        <div class="drawer-divider"></div>
        <div class="drawer-section-label">Akun Saya</div>

        <button class="drawer-item" onclick="drawerGo('pesanan')">
            <span class="drawer-item-icon blue"><i class="fa-solid fa-bag-shopping"></i></span>
            <span>Pesanan Saya</span>
            <?php if ($total_notif > 0): ?>
                <span class="drawer-badge"><?= $total_notif ?></span>
            <?php endif; ?>
        </button>
        <button class="drawer-item" onclick="drawerGo('notif')">
            <span class="drawer-item-icon yellow"><i class="fa-regular fa-bell"></i></span>
            <span>Notifikasi</span>
            <?php if ($total_notif > 0): ?>
                <span class="drawer-badge"><?= $total_notif ?></span>
            <?php endif; ?>
        </button>
        <button class="drawer-item" onclick="drawerGo('profil')">
            <span class="drawer-item-icon orange"><i class="fa-regular fa-user"></i></span>
            <span>Profil Saya</span>
        </button>
        <button class="drawer-item" onclick="drawerGo('password')">
            <span class="drawer-item-icon blue"><i class="fa-solid fa-lock"></i></span>
            <span>Ubah Password</span>
        </button>
        <button class="drawer-item" onclick="drawerGo('privasi')">
            <span class="drawer-item-icon green"><i class="fa-solid fa-shield-halved"></i></span>
            <span>Pengaturan Privasi</span>
        </button>
        <button class="drawer-item" onclick="drawerGo('pengaturan')">
            <span class="drawer-item-icon gray"><i class="fa-solid fa-gear"></i></span>
            <span>Pengaturan</span>
        </button>

        <div class="drawer-divider"></div>

        <a href="logout.php" class="drawer-item logout-item"
           onclick="return confirm('Yakin ingin log out?')">
            <span class="drawer-item-icon red"><i class="fa-solid fa-right-from-bracket"></i></span>
            <span>Log Out</span>
        </a>
    </nav>
</div>

<!-- ══════════════════════════════════════════
     SEARCH OVERLAY (mobile)
══════════════════════════════════════════ -->
<div class="search-overlay" id="searchOverlay"
     onclick="if(event.target===this)this.classList.remove('open')">
    <div class="search-sheet">
        <div class="search-sheet-handle"></div>
        <h3>Cari Makanan &amp; Minuman</h3>
        <div class="search-sheet-box">
            <input id="sheetInput" type="text" placeholder="Ketik nama menu...">
            <button><i class="fa-solid fa-magnifying-glass"></i></button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     MAIN WRAPPER
══════════════════════════════════════════ -->
<div class="order-wrapper">

    <!-- SIDEBAR (desktop only) -->
    <div class="order-sidebar">
        <div class="profile-box">
            <img src="<?= $avatarSrc ?>"
                 alt="<?= htmlspecialchars($username_user) ?>"
                 onerror="this.src='<?= $avatarFallback ?>'">
            <div>
                <h4><?= htmlspecialchars($username_user) ?></h4>
                <a href="pesan.php?tab=akun"><span>✎ Ubah Profil</span></a>
            </div>
        </div>
        <hr>
        <div class="sidebar-group">
            <div class="sidebar-menu akun-title">
                <i class="fa-regular fa-user"></i>
                <span>Akun Saya</span>
            </div>
            <div class="sidebar-submenu">
                <div class="sidebar-sub active-submenu">Profil</div>
                <div class="sidebar-sub">Ubah Password</div>
                <div class="sidebar-sub">Pengaturan Privasi</div>
            </div>
        </div>
        <div class="sidebar-menu">
            <i class="fa-regular fa-clipboard"></i>
            <span>Pesanan Saya</span>
        </div>
        <div class="sidebar-menu">
            <i class="fa-regular fa-bell"></i>
            <span>Notifikasi</span>
        </div>
        <div class="sidebar-menu">
            <i class="fa-solid fa-gear"></i>
            <span>Pengaturan</span>
        </div>
    </div>

    <!-- KONTEN UTAMA -->
    <div class="order-content"></div>

</div>

<!-- ══════════════════════════════════════════
     BOTTOM NAV MOBILE
══════════════════════════════════════════ -->
<div class="mob-bottom-nav">
    <a href="user.php" class="mob-nav-item">
        <i class="fa-solid fa-house"></i><span>Home</span>
    </a>
    <a href="menu.php" class="mob-nav-item">
        <i class="fa-solid fa-bowl-food"></i><span>Menu</span>
    </a>
    <button class="mob-nav-search"
            onclick="document.getElementById('searchOverlay').classList.add('open');setTimeout(()=>document.getElementById('sheetInput').focus(),300)">
        <div class="mob-nav-search-btn"><i class="fa-solid fa-magnifying-glass"></i></div>
        <span>Cari</span>
    </button>
    <button class="mob-nav-item" id="btnNavPesanan" onclick="mobileSwitchSection('pesanan')">
        <i class="fa-solid fa-bag-shopping"></i><span>Pesanan</span>
        <?php if ($total_notif > 0): ?>
            <div class="mob-nav-badge"><?= $total_notif ?></div>
        <?php endif; ?>
    </button>
    <button class="mob-nav-item" id="btnNavAkun" onclick="mobileSwitchSection('akun')">
        <i class="fa-regular fa-user"></i><span>Akun</span>
    </button>
</div>

<div class="mob-bottom-spacer"></div>
<div id="toast-notif"></div>

<!-- ══════════════════════════════════════════
     POPUP STRUK (canvas)
══════════════════════════════════════════ -->
<div class="struk-popup" id="strukPopup">
    <div class="struk-box">
        <button class="close-struk" onclick="closeStruk()">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <canvas id="strukCanvas" width="400" height="600"></canvas>
        <button class="download-popup-btn" id="downloadBtn">
            <i class="fa-solid fa-download"></i> Download Struk
        </button>
    </div>
</div>

<!-- ══════════════════════════════════════════
     MODAL LOGOUT
══════════════════════════════════════════ -->
<div class="logout-modal-overlay" id="logoutModalOverlay">
    <div class="logout-modal-box">
        <div class="logout-modal-icon">
            <i class="fa-solid fa-right-from-bracket"></i>
        </div>
        <h3>Keluar dari Akun?</h3>
        <p>Kamu akan keluar dari akun <strong><?= htmlspecialchars($username_user) ?></strong>. Pastikan semua pesananmu sudah tercatat sebelum keluar.</p>
        <div class="logout-modal-actions">
            <button class="btn-modal-cancel" onclick="closeLogoutModal()">Batal</button>
            <a href="logout.php" class="btn-modal-logout" style="text-decoration:none;display:flex;align-items:center;justify-content:center;">
                <i class="fa-solid fa-right-from-bracket" style="margin-right:6px"></i> Ya, Keluar
            </a>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     JAVASCRIPT
══════════════════════════════════════════ -->
<script>
/* ── Data dari PHP ── */
const PHP = {
    username  : <?= json_encode($username_user) ?>,
    nama      : <?= json_encode($nama) ?>,
    email     : <?= json_encode($email) ?>,
    no_hp     : <?= json_encode($no_hp) ?>,
    gender    : <?= json_encode($gender) ?>,
    tgl_lahir : <?= json_encode($tgl_lahir) ?>,
    pp_user   : <?= json_encode($pp_user) ?>,
    avatarSrc : <?= json_encode($avatarSrc) ?>,
    avatarFall: <?= json_encode($avatarFallback) ?>,
    totalNotif: <?= (int) $total_notif ?>,
};

/* ══════════════════════════════════════════
   HELPERS
══════════════════════════════════════════ */
function toast(msg) {
    const el = document.getElementById('toast-notif');
    el.textContent = msg;
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 2800);
}
function formatRupiah(val) {
    return 'Rp' + parseFloat(val).toLocaleString('id-ID');
}
function parseDate(str) {
    if (!str) return null;
    if (str.includes('+') || str.endsWith('Z')) return new Date(str);
    const s = str.includes('T') ? str : str.replace(' ', 'T');
    return new Date(s + '+07:00');
}
function formatTanggal(str) {
    const d = parseDate(str);
    if (!d) return '-';
    return d.toLocaleString('id-ID', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
}
function selisihWaktu(str) {
    const d = parseDate(str);
    if (!d) return '-';
    const mnt = Math.floor((Date.now() - d.getTime()) / 60000);
    if (mnt < 1)  return 'Baru saja';
    if (mnt < 60) return mnt + ' menit lalu';
    const jam = Math.floor(mnt / 60);
    if (jam < 24) return jam + ' jam lalu';
    return Math.floor(jam / 24) + ' hari lalu';
}
function fotoPenjual(foto) {
    return foto ? '../penjual/uploads/' + foto : '../gambar/default-food.png';
}
function labelStatus(status) {
    const map = {
        pending  : ['Menunggu Konfirmasi', 'badge-pending',  'fa-clock'],
        dimasak  : ['Sedang Dimasak',      'badge-diproses', 'fa-fire-burner'],
        siap     : ['Siap Diambil',        'badge-siap',     'fa-bell'],
        selesai  : ['Tunjukkan Struk',     'badge-selesai',  'fa-receipt'],
        lunas    : ['Pesanan Selesai',     'badge-lunas',    'fa-circle-check'],
        ditolak  : ['Dibatalkan',          'badge-batal',    'fa-ban'],
    };
    return map[status] || [status, '', 'fa-circle'];
}

/* ══════════════════════════════════════════
   GROUPING: satukan transaksi dari kantin
   yang sama dalam window waktu 5 menit
══════════════════════════════════════════ */
const GROUP_WINDOW_SEC = 300; // 5 menit

/**
 * Terima array flat transaksi dari get_pesanan.php,
 * kembalikan array group:
 * {
 *   groupKey, id_kantin, nama_kantin, tanggal, status, metode,
 *   items: [ { id_transaksi, nama_makanan, jumlah, total, antrian,
 *              catatan, metode, status, foto } ],
 *   totalGrup
 * }
 */
function groupTransaksi(list) {
    const groups = {};

    list.forEach(t => {
        const ts   = Math.floor(parseDate(t.tanggal).getTime() / 1000);
        const slot = Math.floor(ts / GROUP_WINDOW_SEC);
        const key  = (t.id_kantin || t.nama_kantin || 'X') + '_' + slot;

        if (!groups[key]) {
            groups[key] = {
                groupKey   : key,
                id_kantin  : t.id_kantin   || '',
                nama_kantin: t.nama_kantin || 'Kantin',
                tanggal    : t.tanggal,
                status     : t.status,
                metode     : t.metode,
                items      : [],
                totalGrup  : 0,
                foto_kantin: t.foto        || '',
            };
        }

        const g = groups[key];
        g.items.push({
            id_transaksi: t.id_transaksi,
            nama_makanan: t.nama_makanan || '-',
            jumlah      : t.jumlah,
            total       : parseFloat(t.total),
            antrian     : t.antrian,
            catatan     : t.catatan || '',
            metode      : t.metode,
            status      : t.status,
            foto        : t.foto || '',
            foto_user   : t.foto_user || '',
            nama_user   : t.nama_user  || PHP.nama,
        });
        g.totalGrup += parseFloat(t.total);

        // Status grup = status prioritas terendah (belum selesai)
        const PRIO = { pending:0, dimasak:1, siap:2, selesai:3, lunas:4, ditolak:5 };
        if ((PRIO[t.status] ?? 9) < (PRIO[g.status] ?? 9)) {
            g.status = t.status;
        }
    });

    return Object.values(groups);
}

/* ══════════════════════════════════════════
   RENDER CARD GRUP
══════════════════════════════════════════ */
function renderCardGrup(g) {
    const [lblStatus, clsBadge, icoStatus] = labelStatus(g.status);
    const multiItem = g.items.length > 1;

    /* ── item rows ──
       Jika status item adalah 'pending', tampilkan tombol batal per-item.
       Item yang sudah dimasak/siap/selesai tidak bisa dibatalkan.
    ── */
    const itemsHTML = g.items.map(it => {
        const imgSrc = fotoPenjual(it.foto);
        const kodeAntrian = '#KANTIN' + (g.id_kantin || '?') + 'KNV' + String(it.antrian).padStart(3,'0');

        /* Tombol batal hanya muncul jika status item ini masih 'pending' */
        const btnBatalItem = (it.status === 'pending')
            ? `<button class="btn-batal-item"
                       onclick='batalSatuItem(${it.id_transaksi}, "${g.groupKey}", ${JSON.stringify(it.nama_makanan)})'
                       title="Batalkan hanya menu ini">
                   <i class="fa-solid fa-xmark" style="font-size:9px"></i> Batal
               </button>`
            : '';

        return `
        <div class="group-item-row" id="item-row-${it.id_transaksi}">
            <img class="group-item-img"
                 src="${imgSrc}"
                 onerror="this.src='../gambar/default-food.png'"
                 alt="${it.nama_makanan}">
            <div class="group-item-info">
                <div class="group-item-name">${it.nama_makanan}</div>
                <div class="group-item-meta">${it.jumlah}× · via ${it.metode || '-'}</div>
            </div>
            <span class="group-item-antrian">
                <i class="fa-solid fa-ticket" style="font-size:9px"></i>
                ${kodeAntrian}
            </span>
            <div class="group-item-price">${formatRupiah(it.total)}</div>
            ${btnBatalItem}
        </div>`;
    }).join('');

    /* ── tombol aksi footer berdasarkan status grup ──
       Status 'pending': tidak ada tombol batal di footer lagi
       (pembatalan sudah per-item di masing-masing row di atas)
    ── */
    let tombol = '';

    if (g.status === 'pending') {
        /* Tidak ada tombol di footer untuk pending —
           pembatalan dilakukan per-item langsung di row masing-masing */
        tombol = `<span class="info-tidak-batal" style="color:#c2410c;background:#fff7ed;border:1px dashed #fca5a5;">
            <i class="fa-solid fa-circle-info"></i> Batalkan item lewat tombol di tiap menu
        </span>`;

    } else if (g.status === 'dimasak') {
        tombol = `
        <button class="btn-struk" onclick="showStrukGrup('${g.groupKey}')">
            <i class="fa-solid fa-download"></i> Download Struk
        </button>
        <span class="info-tidak-batal"><i class="fa-solid fa-lock"></i> Tidak dapat dibatalkan</span>`;

    } else if (g.status === 'siap') {
        tombol = `
        <button class="btn-struk" onclick="showStrukGrup('${g.groupKey}')">
            <i class="fa-solid fa-download"></i> Download Struk
        </button>
        <span class="info-siap"><i class="fa-solid fa-bell"></i> Segera ambil!</span>`;

    } else if (g.status === 'selesai') {
        tombol = `
        <button class="btn-struk btn-struk-show" onclick="showStrukGrup('${g.groupKey}')">
            <i class="fa-solid fa-receipt"></i> Lihat &amp; Tunjukkan Struk
        </button>
        <span class="info-tunggu-lunas"><i class="fa-solid fa-circle-info"></i> Tunjukkan ke penjual untuk konfirmasi</span>`;

    } else if (g.status === 'lunas') {
        tombol = `
        <a href="rating.php?id_transaksi=${g.items[0].id_transaksi}" class="btn-nilai"
           style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa-regular fa-star"></i> Beri Rating
        </a>
        <button class="btn-ulang" onclick="window.location.href='menu.php'">
            <i class="fa-solid fa-rotate-right"></i> Beli Lagi
        </button>`;

    } else if (g.status === 'ditolak') {
        tombol = `<span class="btn-disabled"><i class="fa-solid fa-ban"></i> Pesanan Ditolak</span>`;
    }

    /* ── notice strip ── */
    let notice = '';
    if (g.status === 'selesai') {
        notice = `<div class="struk-notice">
            <i class="fa-solid fa-triangle-exclamation"></i>
            Pesanan siap. Download struk &amp; tunjukkan ke <strong>${g.nama_kantin}</strong> untuk konfirmasi pembayaran.
        </div>`;
    } else if (g.status === 'lunas') {
        notice = `<div class="lunas-notice">
            <i class="fa-solid fa-circle-check"></i>
            Pesanan selesai &amp; lunas! Terima kasih sudah memesan. Jangan lupa beri rating!
        </div>`;
    }

    return `
    <div class="order-group-card" id="group-${g.groupKey}"
         data-status="${g.status}"
         data-search-text="${(g.nama_kantin + ' ' + g.items.map(i=>i.nama_makanan).join(' ')).toLowerCase()}">

        <!-- Header strip kantin -->
        <div class="group-card-header">
            <div class="group-kantin-icon">
                <i class="fa-solid fa-utensils"></i>
            </div>
            <div class="group-kantin-name">${g.nama_kantin}</div>
            ${multiItem ? `<span style="font-size:11px;color:#94a3b8;background:#f1f5f9;padding:2px 8px;border-radius:50px;font-weight:600;">${g.items.length} menu</span>` : ''}
            <span class="group-status-badge ${clsBadge}">
                <i class="fa-solid ${icoStatus}" style="font-size:10px"></i>
                ${lblStatus}
            </span>
        </div>

        <!-- Items -->
        <div class="group-items-wrap">
            ${itemsHTML}
            ${notice}
        </div>

        <!-- Footer -->
        <div class="group-card-footer">
            <div class="group-total-wrap">
                <div class="group-total-label">Total Pembayaran</div>
                <div class="group-total-val">${formatRupiah(g.totalGrup)}</div>
            </div>
            <div class="group-actions">
                ${tombol}
            </div>
        </div>
    </div>`;
}

/* ══════════════════════════════════════════
   PESANAN
══════════════════════════════════════════ */
let _currentTab  = 'All';
let _allPesanan  = [];  // flat array dari server
let _allGroups   = [];  // setelah grouping

function renderPesanan(activeTab = 'All') {
    _currentTab = activeTab;
    const oc = document.querySelector('.order-content');
    oc.className = 'order-content';
    const tabs = ['All', 'Sedang Diproses', 'Proses', 'Siap Ambil', 'Selesai', 'Lunas', 'Dibatalkan'];
    const tabsHTML = tabs.map(t =>
        `<div class="tab ${t === activeTab ? 'active' : ''}" onclick="switchTab('${t}')">${t}</div>`
    ).join('');
    oc.innerHTML = `
        <div class="order-tabs">${tabsHTML}</div>
        <div class="order-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="searchPesanan"
                   placeholder="Cari berdasarkan nama menu atau kantin..."
                   oninput="filterPesanan(this.value)">
        </div>
        <div id="pesananList">
            <div class="skeleton" style="height:160px;margin-bottom:12px;border-radius:16px;"></div>
            <div class="skeleton" style="height:160px;border-radius:16px;"></div>
        </div>`;
    fetchPesanan(activeTab);
}

function switchTab(tab) { renderPesanan(tab); }

function fetchPesanan(filter) {
    fetch('get_pesanan.php?filter=' + encodeURIComponent(filter || 'All'))
        .then(r => r.json())
        .then(data => {
            _allPesanan = data.data || [];
            _allGroups  = groupTransaksi(_allPesanan);
            tampilGrup(_allGroups);
        })
        .catch(() => {
            const el = document.getElementById('pesananList');
            if (el) el.innerHTML = '<p style="color:#e53935;padding:20px">Gagal memuat pesanan.</p>';
        });
}

function tampilGrup(groups) {
    const el = document.getElementById('pesananList');
    if (!el) return;
    if (!groups || groups.length === 0) {
        el.innerHTML = `<div class="empty-state"><span class="empty-icon">🛍️</span><p>Belum ada pesanan di sini</p></div>`;
        return;
    }
    el.innerHTML = groups.map(renderCardGrup).join('');
}

function filterPesanan(q) {
    if (!q.trim()) { tampilGrup(_allGroups); return; }
    const lower = q.toLowerCase();
    tampilGrup(_allGroups.filter(g =>
        g.data_search_text
            ? g.data_search_text.includes(lower)
            : (g.nama_kantin + ' ' + g.items.map(i=>i.nama_makanan).join(' ')).toLowerCase().includes(lower)
    ));
}

/* ══════════════════════════════════════════
   BATALKAN SATU ITEM (per-item)
   Hanya membatalkan 1 id_transaksi yang dipilih,
   item lain dalam grup tidak terpengaruh.
══════════════════════════════════════════ */
function batalSatuItem(id_transaksi, groupKey, nama_makanan) {
    confirmPopup({
        judul  : 'Batalkan Menu',
        pesan  : 'Yakin ingin membatalkan menu "' + nama_makanan + '"?',
        tipe   : 'warning',
        labelOK: 'Ya, Batalkan',
        onOK   : function() {
            fetch('batalkan_pesanan.php', {
                method : 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body   : 'id_transaksi=' + encodeURIComponent(id_transaksi)
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'ok') {
                    toast('✅ Menu berhasil dibatalkan');
                    fetchPesanan(_currentTab);
                } else {
                    toast('❌ ' + (data.msg || 'Gagal membatalkan'));
                }
            })
            .catch(() => toast('❌ Kesalahan jaringan'));
        }
    });
}

/* ══════════════════════════════════════════
   BATALKAN GRUP (semua item dalam grup)
   Fungsi ini tetap ada untuk backward-compat
   tapi tidak lagi dipanggil dari UI utama.
══════════════════════════════════════════ */
function batalGrup(idsJsonStr, groupKey) {
    if (!confirm('Yakin ingin membatalkan semua pesanan di grup ini?')) return;

    let ids;
    try { ids = JSON.parse(idsJsonStr); } catch(e) { ids = []; }
    if (!ids.length) return;

    Promise.all(ids.map(id =>
        fetch('batalkan_pesanan.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body   : 'id_transaksi=' + id
        }).then(r => r.json())
    ))
    .then(results => {
        const berhasil = results.filter(r => r.status === 'ok').length;
        if (berhasil > 0) {
            toast(`✅ ${berhasil} pesanan berhasil dibatalkan`);
            fetchPesanan(_currentTab);
        } else {
            toast('❌ ' + (results[0]?.msg || 'Gagal membatalkan'));
        }
    })
    .catch(() => toast('❌ Kesalahan jaringan'));
}

/* ══════════════════════════════════════════
   STRUK GRUP — canvas multi-item
   Setiap item tampil dengan kode antrian masing-masing
══════════════════════════════════════════ */

/* Lookup group by key */
function getGroupByKey(key) {
    return _allGroups.find(g => g.groupKey === key) || null;
}

function showStrukGrup(groupKey) {
    const g = getGroupByKey(groupKey);
    if (!g) { toast('Data grup tidak ditemukan'); return; }

    /* Hitung tinggi canvas dinamis:
       header(90) + avatar+nama(120) + per-item(52) + divider+total+footer(150) */
    const ITEM_H    = 56;
    const BASE_H    = 90 + 120 + 60 + 90 + 80;  // header, avatar, info, total, footer
    const canvasH   = BASE_H + g.items.length * ITEM_H;
    const CANVAS_W  = 400;

    const popup  = document.getElementById('strukPopup');
    const canvas = document.getElementById('strukCanvas');
    canvas.width  = CANVAS_W;
    canvas.height = canvasH;
    popup.classList.add('show');

    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, CANVAS_W, canvasH);

    /* ── Latar putih ── */
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, CANVAS_W, canvasH);

    /* ── Header orange ── */
    const grad = ctx.createLinearGradient(0, 0, CANVAS_W, 90);
    grad.addColorStop(0, '#d45500');
    grad.addColorStop(0.5, '#f57c1a');
    grad.addColorStop(1, '#ffc55a');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, CANVAS_W, 90);

    ctx.fillStyle = '#fff';
    ctx.font = 'bold 18px "Plus Jakarta Sans", sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('E-Canteen Esemkita', CANVAS_W/2, 34);
    ctx.font = '12px sans-serif';
    ctx.fillText('Bukti Transaksi Pesanan', CANVAS_W/2, 56);
    ctx.font = '10px sans-serif';
    ctx.fillStyle = 'rgba(255,255,255,.75)';
    ctx.fillText(new Date().toLocaleString('id-ID'), CANVAS_W/2, 76);

    /* ── Avatar user ── */
    const avatarUrl = PHP.pp_user
        ? '../gambar/profil/' + PHP.pp_user
        : PHP.avatarSrc;

    const drawAfterAvatar = (withImg, avatarImg) => {
        let y = 90;

        /* avatar circle */
        ctx.save();
        ctx.beginPath();
        ctx.arc(CANVAS_W/2, y + 46, 34, 0, Math.PI * 2);
        ctx.fillStyle = '#f97316';
        ctx.fill();
        if (withImg && avatarImg) {
            try {
                ctx.clip();
                ctx.drawImage(avatarImg, CANVAS_W/2 - 34, y + 12, 68, 68);
            } catch(e) {}
        } else {
            ctx.fillStyle = '#fff';
            ctx.font = 'bold 22px sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(
                (PHP.nama || PHP.username || '?').charAt(0).toUpperCase(),
                CANVAS_W/2, y + 46
            );
        }
        ctx.restore();
        ctx.textBaseline = 'alphabetic';

        y += 96;

        /* Nama user */
        ctx.textAlign = 'center';
        ctx.fillStyle = '#1e293b';
        ctx.font = 'bold 13px sans-serif';
        ctx.fillText(PHP.nama || PHP.username, CANVAS_W/2, y);
        y += 18;

        /* Nama kantin */
        ctx.fillStyle = '#e8650a';
        ctx.font = '11px sans-serif';
        ctx.fillText(g.nama_kantin, CANVAS_W/2, y);
        y += 20;

        /* Tanggal */
        ctx.fillStyle = '#94a3b8';
        ctx.font = '10px sans-serif';
        ctx.fillText(formatTanggal(g.tanggal), CANVAS_W/2, y);
        y += 18;

        /* ── Garis putus ── */
        const dashed = (yPos) => {
            ctx.save();
            ctx.setLineDash([5, 4]);
            ctx.strokeStyle = '#e2e8f0';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(20, yPos);
            ctx.lineTo(CANVAS_W - 20, yPos);
            ctx.stroke();
            ctx.restore();
        };

        dashed(y + 4);
        y += 18;

        /* ── Header kolom item ── */
        ctx.textAlign = 'left';
        ctx.fillStyle = '#94a3b8';
        ctx.font = 'bold 10px sans-serif';
        ctx.fillText('ITEM PESANAN', 24, y);
        ctx.textAlign = 'right';
        ctx.fillText('KODE ANTRIAN', CANVAS_W - 24, y);
        y += 6;
        dashed(y);
        y += 14;

        /* ── Tiap item ── */
        g.items.forEach((it, idx) => {
            const kode = '#KANTIN' + (g.id_kantin || '?') + 'KNV' + String(it.antrian).padStart(3,'0');

            /* nomor item & nama */
            ctx.textAlign = 'left';
            ctx.fillStyle = '#f97316';
            ctx.font = 'bold 11px sans-serif';
            ctx.fillText(String(idx + 1) + '.', 24, y);

            ctx.fillStyle = '#1e293b';
            ctx.font = 'bold 12px sans-serif';
            const maxNameW = 200;
            let namaTeks = it.nama_makanan;
            while (ctx.measureText(namaTeks).width > maxNameW && namaTeks.length > 6) {
                namaTeks = namaTeks.slice(0, -1);
            }
            if (namaTeks !== it.nama_makanan) namaTeks += '…';
            ctx.fillText(namaTeks, 40, y);

            /* qty & harga (baris kedua) */
            ctx.fillStyle = '#64748b';
            ctx.font = '11px sans-serif';
            ctx.fillText(it.jumlah + '× · ' + formatRupiah(it.total), 40, y + 15);

            /* kode antrian (kanan) */
            const kodeW  = ctx.measureText(kode).width + 16;
            const kodeX  = CANVAS_W - 24 - kodeW;
            const kodeY  = y - 12;

            /* kotak dashed */
            ctx.save();
            ctx.setLineDash([3, 3]);
            ctx.strokeStyle = '#f97316';
            ctx.lineWidth   = 1.2;
            ctx.strokeRect(kodeX, kodeY, kodeW, 20);
            ctx.restore();

            ctx.textAlign   = 'right';
            ctx.fillStyle   = '#c2410c';
            ctx.font        = 'bold 9.5px monospace';
            ctx.fillText(kode, CANVAS_W - 24 - 8, y + 1);

            y += ITEM_H;
        });

        dashed(y - 6);
        y += 12;

        /* ── Grand Total ── */
        ctx.textAlign = 'left';
        ctx.fillStyle = '#94a3b8';
        ctx.font = '12px sans-serif';
        ctx.fillText('Total Pembayaran', 24, y + 12);
        ctx.textAlign = 'right';
        ctx.fillStyle = '#f97316';
        ctx.font = 'bold 18px sans-serif';
        ctx.fillText(formatRupiah(g.totalGrup), CANVAS_W - 24, y + 12);
        y += 30;

        dashed(y);
        y += 22;

        /* ── Footer ── */
        ctx.textAlign = 'center';
        ctx.fillStyle = '#94a3b8';
        ctx.font      = '10px sans-serif';
        ctx.fillText('Terima kasih telah memesan di E-Canteen Esemkita', CANVAS_W/2, y);
        y += 16;
        ctx.fillText('© E-Canteen Esemkita', CANVAS_W/2, y);

        /* ── Download btn ── */
        document.getElementById('downloadBtn').onclick = () => {
            const a = document.createElement('a');
            a.download = 'struk-' + g.nama_kantin.replace(/\s+/g,'-') + '-' + Date.now() + '.png';
            a.href = canvas.toDataURL('image/png');
            a.click();
        };
    };

    /* Load avatar dengan fallback */
    const img = new Image();
    img.crossOrigin = 'anonymous';
    let settled = false;
    const finish = (ok) => {
        if (settled) return;
        settled = true;
        drawAfterAvatar(ok, img);
    };
    img.onload  = () => finish(true);
    img.onerror = () => finish(false);
    setTimeout(() => finish(false), 3000);
    img.src = avatarUrl;
}

function closeStruk() {
    document.getElementById('strukPopup').classList.remove('show');
}

/* ── backward compat: jika kode lama panggil showStrukById ── */
function showStrukById(id) {
    const g = _allGroups.find(g => g.items.some(i => i.id_transaksi == id));
    if (g) showStrukGrup(g.groupKey);
    else toast('Data tidak ditemukan');
}

/* ══════════════════════════════════════════
   LIGHTBOX FOTO PROFIL
══════════════════════════════════════════ */
function bukaFotoLightbox() {
    const img = document.querySelector('#avatarPreview img');
    if (!img) return;
    document.getElementById('fotoLightboxImg').src  = img.src;
    document.getElementById('fotoLightboxName').textContent = PHP.nama || PHP.username;
    document.getElementById('fotoLightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function tutupFotoLightbox() {
    document.getElementById('fotoLightbox').classList.remove('open');
    document.body.style.overflow = '';
}

/* ══════════════════════════════════════════
   MODAL LOGOUT
══════════════════════════════════════════ */
function showLogoutModal() {
    document.getElementById('logoutModalOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeLogoutModal() {
    document.getElementById('logoutModalOverlay').classList.remove('open');
    document.body.style.overflow = '';
}
document.getElementById('logoutModalOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeLogoutModal();
});

/* ══════════════════════════════════════════
   BOTTOM NAV ACTIVE STATE
══════════════════════════════════════════ */
function setBottomNavActive(section) {
    document.querySelectorAll('.mob-bottom-nav .mob-nav-item').forEach(b => b.classList.remove('active'));
    if (section === 'pesanan') {
        const btn = document.getElementById('btnNavPesanan');
        if (btn) btn.classList.add('active');
    } else if (section === 'akun') {
        const btn = document.getElementById('btnNavAkun');
        if (btn) btn.classList.add('active');
    }
}

/* ══════════════════════════════════════════
   DRAWER (hamburger) — mobile
══════════════════════════════════════════ */
function toggleDrawer() {
    const d = document.getElementById('drawer');
    const o = document.getElementById('drawerOverlay');
    const h = document.getElementById('mobHamburger');
    const open = d.classList.toggle('open');
    o.classList.toggle('open', open);
    h.classList.toggle('open', open);
    document.body.classList.toggle('drawer-lock', open);
}
function closeDrawer() {
    document.getElementById('drawer').classList.remove('open');
    document.getElementById('drawerOverlay').classList.remove('open');
    document.getElementById('mobHamburger').classList.remove('open');
    document.body.classList.remove('drawer-lock');
}
function drawerGo(section) {
    closeDrawer();
    const map = {
        pesanan    : () => renderPesanan('All'),
        notif      : () => renderNotifikasi(),
        profil     : () => loadAkunContent('Profil'),
        password   : () => loadAkunContent('Ubah Password'),
        privasi    : () => loadAkunContent('Pengaturan Privasi'),
        pengaturan : () => loadAkunContent('Pengaturan'),
    };
    if (map[section]) map[section]();
    document.querySelectorAll('.mob-nav-item').forEach(b => b.classList.remove('active'));
}

/* ══════════════════════════════════════════
   SUBMENU CONTENT
══════════════════════════════════════════ */
const submenuContent = {

    "Profil": `
    <div class="profile-page">
        <div class="profile-header">
            <h2>Profil Saya</h2>
            <p>Kelola informasi profil untuk mengontrol dan mengamankan akun</p>
        </div>
        <div class="profile-layout">
            <div class="profile-right">
                <div class="profile-avatar" id="avatarPreview" onclick="bukaFotoLightbox()" title="Klik untuk memperbesar">
                    <img src="${PHP.avatarSrc}" onerror="this.src='${PHP.avatarFall}'"
                         style="width:100%;height:100%;object-fit:cover;border-radius:50%" alt="Avatar">
                    <div class="avatar-zoom-hint"><i class="fa-solid fa-magnifying-glass-plus"></i></div>
                </div>
                <input type="file" id="inputFoto" accept="image/jpeg,image/png"
                       style="display:none" onchange="previewDanUpload(this)">
                <button class="upload-btn" onclick="document.getElementById('inputFoto').click()">
                    Pilih Gambar
                </button>
                <div class="upload-info">Maks. 1 MB &nbsp;·&nbsp; JPEG / PNG</div>
                <div id="uploadStatus" class="upload-status-msg"></div>
            </div>
            <div class="profile-form">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" id="username" value="${PHP.username}">
                </div>
                <div class="form-group">
                    <label>Nama</label>
                    <input type="text" id="nama" value="${PHP.nama}">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="email" value="${PHP.email}" placeholder="Masukkan email Gmail">
                </div>
                <div class="form-group">
                    <label>No. HP</label>
                    <input type="number" id="nohp" value="${PHP.no_hp}" placeholder="Masukkan nomor HP">
                </div>
                <div class="form-group">
                    <label>Jenis Kelamin</label>
                    <div class="gender-row">
                        <label><input type="radio" name="gender" value="Laki-laki"
                            ${PHP.gender==='Laki-laki'?'checked':''}> Laki-laki</label>
                        <label><input type="radio" name="gender" value="Perempuan"
                            ${PHP.gender==='Perempuan'?'checked':''}> Perempuan</label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Tanggal Lahir</label>
                    <input type="date" id="tgl_lahir" value="${PHP.tgl_lahir}" max="${(() => { const d = new Date(); d.setFullYear(d.getFullYear() - 15); return d.toISOString().split('T')[0]; })()}">
                </div>
                <button class="save-btn" onclick="simpanProfil()">Simpan</button>
            </div>
        </div>
    </div>`,

    "Ubah Password": `
    <div class="profile-page">
        <div class="profile-header">
            <h2>Ubah Password</h2>
            <p>Pastikan password baru Anda kuat dan mudah diingat</p>
        </div>
        <div class="profile-form pw-form">
            <div class="form-group">
                <label>Password Lama</label>
                <div class="pw-input-wrap">
                    <input type="password" id="pw_lama" placeholder="Password lama">
                    <span class="pw-toggle" onclick="togglePw('pw_lama',this)">
                        <i class="fa-solid fa-eye"></i>
                    </span>
                </div>
            </div>
            <div class="form-group">
                <label>Password Baru</label>
                <div class="pw-input-wrap">
                    <input type="password" id="pw_baru" placeholder="Minimal 8 karakter" oninput="cekKekuatan(this.value)">
                    <span class="pw-toggle" onclick="togglePw('pw_baru',this)">
                        <i class="fa-solid fa-eye"></i>
                    </span>
                </div>
            </div>
            <div class="kekuatan-wrap">
                <div id="kekuatan_bar"><div id="kekuatan_fill"></div></div>
                <div id="kekuatan_label"></div>
            </div>
            <div class="form-group">
                <label>Konfirmasi</label>
                <div class="pw-input-wrap">
                    <input type="password" id="pw_konfirm" placeholder="Ulangi password baru">
                    <span class="pw-toggle" onclick="togglePw('pw_konfirm',this)">
                        <i class="fa-solid fa-eye"></i>
                    </span>
                </div>
            </div>
            <button class="save-btn" onclick="simpanPassword()">Simpan Password</button>
            <div id="pwStatus" class="pw-status-msg"></div>
        </div>
    </div>`,

    "Pengaturan Privasi": `
    <div class="profile-page">
        <div class="profile-header">
            <h2>Pengaturan Privasi</h2>
            <p>Kontrol siapa yang bisa melihat informasi kamu</p>
        </div>
        <div class="profile-form">
            <div class="form-group form-group-inline">
                <label>Tampilkan Profil ke Publik</label>
                <input type="checkbox" class="cb-privacy">
            </div>
            <div class="form-group form-group-inline">
                <label>Riwayat Pesanan Hanya Saya</label>
                <input type="checkbox" checked class="cb-privacy">
            </div>
            <button class="save-btn">Simpan</button>
        </div>
    </div>`,

    "Pengaturan": `
    <div class="profile-page">
        <div class="profile-header">
            <h2>Pengaturan</h2>
            <p>Kelola tampilan dan preferensi akun Anda</p>
        </div>
        <div class="settings-modern">
            <div class="settings-card">
                <div class="settings-icon orange"><i class="fa-solid fa-moon"></i></div>
                <div class="settings-info">
                    <h4>Dark Mode</h4>
                    <p>Aktifkan mode gelap agar mata lebih nyaman saat malam hari</p>
                </div>
                <label class="switch-modern">
                    <input type="checkbox" id="darkToggle">
                    <span class="slider-modern"></span>
                </label>
            </div>
            <div class="settings-card">
                <div class="settings-icon blue"><i class="fa-solid fa-bell"></i></div>
                <div class="settings-info">
                    <h4>Notifikasi</h4>
                    <p>Terima pemberitahuan pesanan dan promo terbaru</p>
                </div>
                <label class="switch-modern">
                    <input type="checkbox" checked>
                    <span class="slider-modern"></span>
                </label>
            </div>
            <div class="settings-card">
                <div class="settings-icon green"><i class="fa-solid fa-language"></i></div>
                <div class="settings-info">
                    <h4>Bahasa</h4>
                    <p>Pilih bahasa aplikasi yang ingin digunakan</p>
                </div>
                <select class="setting-select">
                    <option>Bahasa Indonesia</option>
                    <option>English</option>
                </select>
            </div>
        </div>
        <div class="logout-section-card" style="margin-top:20px">
            <div class="logout-section-header">
                <div class="logout-section-header-icon">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </div>
                <div>
                    <h3>Keluar Akun</h3>
                    <p>Akhiri sesi E-Canteen kamu dengan aman</p>
                </div>
            </div>
            <div class="logout-section-body">
                <button class="logout-action-btn" onclick="showLogoutModal()">
                    <div class="logout-action-icon">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </div>
                    <div>
                        <div class="logout-action-label">Log Out</div>
                        <div class="logout-action-sub">Keluar dari akun ${PHP.username}</div>
                    </div>
                    <i class="fa-solid fa-chevron-right logout-action-arrow"></i>
                </button>
            </div>
        </div>
    </div>`,
};

/* ══════════════════════════════════════════
   LOAD KONTEN AKUN
══════════════════════════════════════════ */
function loadAkunContent(label) {
    const oc = document.querySelector('.order-content');
    oc.className = 'order-content';
    oc.innerHTML = submenuContent[label] || submenuContent['Profil'];
    if (label === 'Pengaturan') initSettings();
}

/* ══════════════════════════════════════════
   SWITCH SECTION (mobile bottom nav)
══════════════════════════════════════════ */
function mobileSwitchSection(section) {
    if (section === 'pesanan')    renderPesanan('All');
    else if (section === 'notif') renderNotifikasi();
    else if (section === 'akun')  loadAkunContent('Profil');
    else                          loadAkunContent('Profil');
    setBottomNavActive(section);
}

/* ══════════════════════════════════════════
   SIDEBAR DESKTOP
══════════════════════════════════════════ */
function initSidebar() {
    const sidebarMenus = document.querySelectorAll('.sidebar-menu');
    const sidebarSubs  = document.querySelectorAll('.sidebar-sub');
    const submenuGroup = document.querySelector('.sidebar-submenu');
    const akunTitle    = document.querySelector('.akun-title');
    const tab          = new URLSearchParams(window.location.search).get('tab') || 'akun';

    const activateMenu = label => {
        sidebarMenus.forEach(m => m.classList.remove('orange'));
        sidebarMenus.forEach(m => {
            if (m.querySelector('span')?.innerText.trim() === label) m.classList.add('orange');
        });
    };
    const showAkun = subLabel => {
        submenuGroup.style.display = 'flex';
        akunTitle.classList.add('active', 'orange');
        sidebarMenus.forEach(m => m.classList.remove('orange'));
        akunTitle.classList.add('orange');
        sidebarSubs.forEach(s => s.classList.remove('active-submenu'));
        const sub = [...sidebarSubs].find(s => s.innerText.trim() === subLabel);
        if (sub) sub.classList.add('active-submenu');
        loadAkunContent(subLabel);
    };
    const hideSubmenu = () => {
        submenuGroup.style.display = 'none';
        akunTitle.classList.remove('active');
    };

    if (tab === 'pesanan')    { hideSubmenu(); activateMenu('Pesanan Saya'); renderPesanan('All'); }
    else if (tab === 'notif') { hideSubmenu(); activateMenu('Notifikasi');   renderNotifikasi(); }
    else                      { showAkun('Profil'); }

    sidebarMenus.forEach(menu => {
        menu.addEventListener('click', function () {
            const label = this.querySelector('span')?.innerText.trim();
            if (!label) return;
            if (label === 'Akun Saya')         showAkun('Profil');
            else if (label === 'Pesanan Saya') { hideSubmenu(); activateMenu(label); renderPesanan('All'); }
            else if (label === 'Notifikasi')   { hideSubmenu(); activateMenu(label); renderNotifikasi(); }
            else if (label === 'Pengaturan')   { hideSubmenu(); activateMenu(label); loadAkunContent('Pengaturan'); }
        });
    });
    sidebarSubs.forEach(sub => {
        sub.addEventListener('click', function () {
            sidebarSubs.forEach(s => s.classList.remove('active-submenu'));
            this.classList.add('active-submenu');
            loadAkunContent(this.innerText.trim());
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    if (window.innerWidth <= 768) {
        const tab = new URLSearchParams(window.location.search).get('tab') || 'pesanan';
        if (tab === 'notif')        { renderNotifikasi(); }
        else if (tab === 'akun')    { loadAkunContent('Profil'); setBottomNavActive('akun'); }
        else                        { renderPesanan('All'); setBottomNavActive('pesanan'); }
    }
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.getElementById('searchOverlay')?.classList.remove('open');
        closeDrawer();
        closeLogoutModal();
        tutupFotoLightbox();
    }
});

/* ══════════════════════════════════════════
   NOTIFIKASI
══════════════════════════════════════════ */
function renderNotifikasi() {
    const oc = document.querySelector('.order-content');
    oc.className = 'order-content';
    oc.innerHTML = `
        <h2 class="notif-title">Notifikasi</h2>
        <div id="notifList">
            <div class="skeleton" style="height:90px;margin-bottom:12px"></div>
            <div class="skeleton" style="height:90px"></div>
        </div>`;
    fetch('get_notifikasi.php')
        .then(r => r.text())
        .then(text => {
            let data;
            try { data = JSON.parse(text); }
            catch(e) {
                console.error('JSON parse error:', e, text.substring(0, 200));
                const el = document.getElementById('notifList');
                if (el) el.innerHTML = '<p style="color:#e53935">Gagal parse data notifikasi.</p>';
                return;
            }
            const el = document.getElementById('notifList');
            if (!el) return;
            const list = data.data || [];
            _notifData = list;
            el.innerHTML = list.length
                ? list.map(renderCardNotif).join('')
                : `<div class="empty-state"><span class="empty-icon">🔔</span><p>Belum ada notifikasi</p></div>`;
        })
        .catch(e => {
            console.error('Fetch error:', e);
            const el = document.getElementById('notifList');
            if (el) el.innerHTML = '<p style="color:#e53935">Gagal memuat notifikasi.</p>';
        });
}

/* ══════════════════════════════════════════
   SIMPAN PROFIL
══════════════════════════════════════════ */
function simpanProfil() {
    const username = document.getElementById('username').value;
    const nama     = document.getElementById('nama').value;
    const email    = document.getElementById('email').value.trim();
    const nohp     = document.getElementById('nohp').value;
    const gender   = document.querySelector('input[name="gender"]:checked')?.value || '';
    const tgl      = document.getElementById('tgl_lahir').value;
    if (!/^[a-zA-Z0-9._%+-]+@gmail\.com$/.test(email)) {
        confirmPopup({
          judul   : 'Gagal',
          pesan   : 'Email harus berakhiran @gmail.com!',
          tipe    : 'warning'
        });
        return;
    }
    if (tgl) {
        const lahir    = new Date(tgl);
        const sekarang = new Date();
        const umur     = sekarang.getFullYear() - lahir.getFullYear();
        const sudahUlang = (
            sekarang.getMonth() > lahir.getMonth() ||
            (sekarang.getMonth() === lahir.getMonth() && sekarang.getDate() >= lahir.getDate())
        );
        const umurAktual = sudahUlang ? umur : umur - 1;
        if (umurAktual < 15) {
            confirmPopup({
          judul   : 'Gagal',
          pesan   : 'Umur tidak memenuhi syarat',
          tipe    : 'warning'
        });
        return;
        }
    }
    fetch('update_profil.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ username, nama, email, nohp, gender, tgl }).toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'ok') {
            confirmPopup({ judul: 'Berhasil', pesan: 'Profil berhasil diperbarui!', tipe: 'success', labelOK: 'Tutup' });
        } else {
            confirmPopup({
                judul: 'Gagal',
                pesan: data.msg || 'Gagal menyimpan profil',
                tipe: 'warning'
            });
        }
    })
    .catch(() => confirmPopup({
        judul: 'Kesalahan',
        pesan: 'Terjadi kesalahan, harap isi semua data dulu',
        tipe: 'warning'
    }));
}

/* ══════════════════════════════════════════
   UPLOAD FOTO
══════════════════════════════════════════ */
function previewDanUpload(input) {
    const file = input.files?.[0];
    if (!file) return;
    if (file.size > 1048576) { tampilStatus('error', 'File terlalu besar. Maks 1 MB.'); input.value = ''; return; }
    if (!['image/jpeg', 'image/png'].includes(file.type)) { tampilStatus('error', 'Format harus JPEG atau PNG.'); input.value = ''; return; }
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('avatarPreview').innerHTML =
            `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;border-radius:50%" alt="Avatar">`;
    };
    reader.readAsDataURL(file);
    tampilStatus('loading', 'Mengunggah...');
    const fd = new FormData(); fd.append('foto', file);
    fetch('upload_pp.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'ok') {
                tampilStatus('ok', 'Foto profil berhasil diperbarui!');
                const src = data.url + '?t=' + Date.now();
                document.querySelectorAll('.profile img, .profile-box img').forEach(i => i.src = src);
            } else {
                tampilStatus('error', data.msg || 'Upload gagal.');
            }
        })
        .catch(() => tampilStatus('error', 'Kesalahan jaringan.'));
}
function tampilStatus(tipe, pesan) {
    const el = document.getElementById('uploadStatus');
    if (!el) return;
    el.style.color = { ok: '#2e7d32', error: '#c62828', loading: '#e8650a' }[tipe] || '#333';
    el.style.display = 'block';
    el.textContent = pesan;
}

/* ══════════════════════════════════════════
   PASSWORD
══════════════════════════════════════════ */
function togglePw(id, icon) {
    const input = document.getElementById(id);
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
    icon.querySelector('i').classList.toggle('fa-eye');
    icon.querySelector('i').classList.toggle('fa-eye-slash');
}
function cekKekuatan(val) {
    const fill  = document.getElementById('kekuatan_fill');
    const label = document.getElementById('kekuatan_label');
    if (!fill || !label) return;
    let s = 0;
    if (val.length >= 8)           s++;
    if (/[A-Z]/.test(val))         s++;
    if (/[0-9]/.test(val))         s++;
    if (/[^A-Za-z0-9]/.test(val))  s++;
    const lvl = ['', 'Lemah', 'Cukup', 'Kuat', 'Sangat Kuat'];
    const clr = ['', '#e53935', '#fb8c00', '#43a047', '#1e88e5'];
    fill.style.width      = [0, 25, 50, 75, 100][s] + '%';
    fill.style.background = clr[s];
    label.style.color     = clr[s];
    label.textContent     = val.length ? lvl[s] : '';
}
function simpanPassword() {
    const lama    = document.getElementById('pw_lama').value;
    const baru    = document.getElementById('pw_baru').value;
    const konfirm = document.getElementById('pw_konfirm').value;
    if (!lama || !baru || !konfirm) { tampilPwStatus('error', '⚠️ Semua field wajib diisi.'); return; }
    if (baru.length < 8)            { tampilPwStatus('error', '⚠️ Minimal 8 karakter.'); return; }
    if (baru !== konfirm)           { tampilPwStatus('error', '⚠️ Konfirmasi tidak cocok.'); return; }
    fetch('proses_akun.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ aksi: 'ganti_password', pw_lama: lama, pw_baru: baru, konfirm }).toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'ok') {
            tampilPwStatus('ok', '✅ ' + data.msg);
            ['pw_lama', 'pw_baru', 'pw_konfirm'].forEach(id => document.getElementById(id).value = '');
            cekKekuatan('');
        } else {
            tampilPwStatus('error', '❌ ' + data.msg);
        }
    })
    .catch(() => tampilPwStatus('error', '❌ Kesalahan jaringan.'));
}
function tampilPwStatus(tipe, pesan) {
    const el = document.getElementById('pwStatus');
    if (!el) return;
    el.style.color   = tipe === 'ok' ? '#2e7d32' : '#c62828';
    el.style.display = 'block';
    el.textContent   = pesan;
    setTimeout(() => el.style.display = 'none', 4000);
}

/* ══════════════════════════════════════════
   SETTINGS
══════════════════════════════════════════ */
function initSettings() {
    const toggle = document.getElementById('darkToggle');
    if (!toggle) return;
    toggle.checked = document.body.classList.contains('dark-mode');
    toggle.addEventListener('change', function () {
        document.body.classList.toggle('dark-mode', this.checked);
    });
}

// Simpan halaman sekarang
localStorage.setItem("last_page", window.location.href);
</script>

<script src="pesan_js_patch.js"></script>

<script>
/* ── Chat badge notifikasi ── */
async function loadNotifChat() {
    try {
        const res  = await fetch('chat_api.php?action=jumlah_belum_baca');
        const data = await res.json();
        const badges = document.querySelectorAll('.chat-badge');
        badges.forEach(b => {
            if (data.belum_baca > 0) {
                b.style.display = 'flex';
                b.textContent   = data.belum_baca;
            } else {
                b.style.display = 'none';
            }
        });
    } catch (e) { console.error('Notif chat error:', e); }
}
loadNotifChat();
setInterval(loadNotifChat, 10000);
</script>

<script src="detail_pesanan_popup.js"></script>

<!-- LIGHTBOX FOTO PROFIL -->
<div class="foto-lightbox" id="fotoLightbox" onclick="tutupFotoLightbox()">
    <div class="foto-lightbox-inner" onclick="event.stopPropagation()">
        <button class="foto-lightbox-close" onclick="tutupFotoLightbox()">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <img id="fotoLightboxImg" src="" alt="Foto Profil">
        <div class="foto-lightbox-name" id="fotoLightboxName"></div>
        <button class="foto-lightbox-change" onclick="tutupFotoLightbox(); setTimeout(()=>document.getElementById('inputFoto')?.click(), 200)">
            <i class="fa-solid fa-camera"></i> Ganti Foto
        </button>
    </div>
</div>

<?php include 'chat_popup.php'; ?>
<script src="../custom_confirm.js"></script>
</body>
</html>