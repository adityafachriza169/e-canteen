<?php
session_start();
include "koneksi.php";
if (!isset($_SESSION['username']) || (int) ($_SESSION['role'] ?? -1) !== 2) {
    header("Location: login.php");
    exit;
}
// ── Data user yang sedang login ──
$id_user_login = (int) $_SESSION['id_user'];

$stmt_user = mysqli_prepare($konek, "SELECT id_user, nama, username, pp FROM users WHERE id_user = ? LIMIT 1");
mysqli_stmt_bind_param($stmt_user, 'i', $id_user_login);
mysqli_stmt_execute($stmt_user);
$query_user = mysqli_stmt_get_result($stmt_user);
$data_user = mysqli_fetch_assoc($query_user);
mysqli_stmt_close($stmt_user);

$id_user_login = (int) ($data_user['id_user'] ?? 0);
$id_user_login = (int) ($_SESSION['id_user'] ?? $id_user_login);
$nama_user = htmlspecialchars($data_user['nama'] ?? 'User');
$username_user = htmlspecialchars($data_user['username'] ?? 'user');
$pp_user = $data_user['pp'] ?? '';

// ── Jumlah item keranjang ──
$q_keranjang = mysqli_query($konek, "SELECT SUM(jumlah) AS total FROM keranjang WHERE id_user = $id_user_login");
$r_keranjang = mysqli_fetch_assoc($q_keranjang);
$total_keranjang = (int) ($r_keranjang['total'] ?? 0);


// ── Pastikan kolom buka ada di penjual ──
$cek_kolom = mysqli_query($konek, "SHOW COLUMNS FROM penjual LIKE 'buka'");
if (mysqli_num_rows($cek_kolom) === 0) {
    mysqli_query($konek, "ALTER TABLE penjual ADD COLUMN buka TINYINT(1) NOT NULL DEFAULT 1");
}

// ── Rekomendasi menu (12 acak, stok > 0, HANYA dari penjual yang BUKA) ──
$query_menu = mysqli_query($konek, "
    SELECT m.*,
           k.nama_kantin,
           p.profil AS foto_kantin,
           COALESCE(agg.terjual, 0)      AS terjual,
           COALESCE(agg.jumlah_order, 0) AS jumlah_order,
           COALESCE((SELECT ROUND(AVG(bintang),1) FROM ulasan WHERE id_menu = m.id), 0) AS rata_rating,
           COALESCE((SELECT COUNT(id)             FROM ulasan WHERE id_menu = m.id), 0) AS jumlah_ulasan
    FROM menu_req m
    LEFT JOIN kantin k ON m.id_kantin = k.id_kantin
    LEFT JOIN penjual p ON m.id_kantin = p.id_kantin
    LEFT JOIN (
        SELECT id_menu, SUM(jumlah) AS terjual, COUNT(id_transaksi) AS jumlah_order
        FROM transaksi
        WHERE status IN ('selesai','lunas','diambil','completed')
        GROUP BY id_menu
    ) agg ON m.id = agg.id_menu
    WHERE m.stok > 0 AND m.aktif = 1
      AND p.buka = 1
      AND p.status = 1
    ORDER BY RAND()
    LIMIT 12
");
$daftar_menu = [];
while ($row = mysqli_fetch_assoc($query_menu)) {
    $daftar_menu[] = $row;
}

// ── Daftar kantin (semua penjual aktif, buka & tutup) ──
$query_kantin = mysqli_query($konek, "
    SELECT p.*, k.nama_kantin, k.id_kantin
    FROM penjual p
    JOIN kantin k ON p.id_kantin = k.id_kantin
    WHERE p.status = 1
    ORDER BY p.buka DESC, p.id_penjual ASC
");
$daftar_kantin = [];
while ($row = mysqli_fetch_assoc($query_kantin)) {
    $daftar_kantin[] = $row;
}

// ── Top 3 Makanan: rating tertinggi + ulasan terbanyak (hanya penjual buka) ──
$query_top_makanan = mysqli_query($konek, "
    SELECT m.id, m.nama_makanan, m.foto, m.kategori,
           COALESCE(SUM(t.jumlah), 0)           AS terjual,
           COALESCE(ROUND(AVG(u.bintang),1), 0) AS rata_rating,
           COUNT(DISTINCT u.id)                  AS jumlah_ulasan
    FROM menu_req m
    JOIN penjual p ON m.id_kantin = p.id_kantin
    LEFT JOIN transaksi t ON m.id = t.id_menu AND t.status IN ('selesai','lunas','diambil','completed')
    LEFT JOIN ulasan u ON m.id = u.id_menu
    WHERE m.kategori NOT IN ('Minuman','minuman','Snack','snack','Cemilan','cemilan')
      AND m.stok > 0 AND m.aktif = 1
      AND p.buka = 1 AND p.status = 1
    GROUP BY m.id, m.nama_makanan, m.foto, m.kategori
    ORDER BY rata_rating DESC, jumlah_ulasan DESC, terjual DESC
    LIMIT 3
");
$top_makanan = [];
while ($row = mysqli_fetch_assoc($query_top_makanan)) {
    $top_makanan[] = $row;
}

// ── Top 3 Minuman: rating tertinggi + ulasan terbanyak (hanya penjual buka) ──
$query_top_minuman = mysqli_query($konek, "
    SELECT m.id, m.nama_makanan, m.foto, m.kategori,
           COALESCE(SUM(t.jumlah), 0)           AS terjual,
           COALESCE(ROUND(AVG(u.bintang),1), 0) AS rata_rating,
           COUNT(DISTINCT u.id)                  AS jumlah_ulasan
    FROM menu_req m
    JOIN penjual p ON m.id_kantin = p.id_kantin
    LEFT JOIN transaksi t ON m.id = t.id_menu AND t.status IN ('selesai','lunas','diambil','completed')
    LEFT JOIN ulasan u ON m.id = u.id_menu
    WHERE m.kategori IN ('Minuman','minuman')
      AND m.stok > 0 AND m.aktif = 1
      AND p.buka = 1 AND p.status = 1
    GROUP BY m.id, m.nama_makanan, m.foto, m.kategori
    ORDER BY rata_rating DESC, jumlah_ulasan DESC, terjual DESC
    LIMIT 3
");
$top_minuman = [];
while ($row = mysqli_fetch_assoc($query_top_minuman)) {
    $top_minuman[] = $row;
}

// ── Top 3 Snack: rating tertinggi + ulasan terbanyak (hanya penjual buka) ──
$query_top_snack = mysqli_query($konek, "
    SELECT m.id, m.nama_makanan, m.foto, m.kategori,
           COALESCE(SUM(t.jumlah), 0)           AS terjual,
           COALESCE(ROUND(AVG(u.bintang),1), 0) AS rata_rating,
           COUNT(DISTINCT u.id)                  AS jumlah_ulasan
    FROM menu_req m
    JOIN penjual p ON m.id_kantin = p.id_kantin
    LEFT JOIN transaksi t ON m.id = t.id_menu AND t.status IN ('selesai','lunas','diambil','completed')
    LEFT JOIN ulasan u ON m.id = u.id_menu
    WHERE m.kategori IN ('Snack','snack','Cemilan','cemilan')
      AND m.stok > 0 AND m.aktif = 1
      AND p.buka = 1 AND p.status = 1
    GROUP BY m.id, m.nama_makanan, m.foto, m.kategori
    ORDER BY rata_rating DESC, jumlah_ulasan DESC, terjual DESC
    LIMIT 3
");
$top_snack = [];
while ($row = mysqli_fetch_assoc($query_top_snack)) {
    $top_snack[] = $row;
}

// ── Helper bintang ──
function renderStars(float $rating): string
{
    $full = (int) floor($rating);
    $half = ($rating - $full) >= 0.5 ? 1 : 0;
    $empty = 5 - $full - $half;
    return str_repeat('★', $full) . str_repeat('½', $half) . str_repeat('☆', $empty);
}

$medal = [
    1 => '../gambar/medal1.png',
    2 => '../gambar/medal2.png',
    3 => '../gambar/medal3.png',
];

$id_user_chat = $id_user_login;
$penjual_chat = mysqli_query(
    $konek,
    "SELECT p.id_penjual, p.nama, k.nama_kantin
     FROM penjual p
     JOIN kantin k ON p.id_kantin = k.id_kantin
     WHERE p.status = 1
     ORDER BY k.nama_kantin, p.nama"
);
$penjual_list_chat = [];
while ($r = mysqli_fetch_assoc($penjual_chat)) {
    $penjual_list_chat[] = $r;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home — E-Canteen</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link
        href="https://fonts.googleapis.com/css2?family=Righteous&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="user.css">
    <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
    <style>
        /* ── Search highlight ── */
        .card.hidden {
            display: none;
        }

        mark.highlight {
            background: #ffe066;
            color: #b34b00;
            border-radius: 3px;
            padding: 0 2px;
            font-weight: 700;
        }

        .no-result-msg {
            display: none;
            grid-column: 1/-1;
            text-align: center;
            padding: 48px 20px;
            color: #aaa;
        }

        .no-result-msg i {
            font-size: 42px;
            color: #e8650a;
            opacity: .5;
            margin-bottom: 12px;
            display: block;
        }

        .no-result-msg h3 {
            font-size: 16px;
            font-weight: 700;
            color: #555;
            margin-bottom: 6px;
        }

        .no-result-msg p {
            font-size: 13px;
        }

        .search-active-pill {
            display: none;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 50px;
            padding: 4px 12px;
            font-size: 12px;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            transition: .2s;
        }

        .search-active-pill.show {
            display: flex;
        }

        .search-active-pill:hover {
            background: rgba(255, 255, 255, 0.32);
        }

        .sheet-result-count {
            font-size: 12px;
            color: #e8650a;
            font-weight: 600;
            margin-top: 10px;
            min-height: 18px;
        }

        /* ── Kantin tutup ── */
        .kantin-card {
            position: relative;
            overflow: hidden;
        }
        .kantin-card.tutup {
            cursor: default;
        }
        .kantin-card.tutup .card-image img {
            filter: grayscale(60%) brightness(0.7);
        }
        .kantin-tutup-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.38);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: inherit;
            z-index: 3;
            pointer-events: none;
        }
        .kantin-tutup-badge {
            background: #e11d48;
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            padding: 5px 14px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 5px;
            letter-spacing: .03em;
            box-shadow: 0 2px 10px rgba(225,29,72,.4);
        }
        .kantin-buka-badge {
            position: absolute;
            top: 8px; left: 8px;
            z-index: 4;
        }
        .kantin-tutup-txt {
            display: none;
        }

        /* ── Ranking row: clickable style ── */
        .rank-row {
            cursor: pointer;
            transition: background .18s, transform .15s;
            border-radius: 10px;
        }

        .rank-row:hover {
            background: rgba(232, 101, 10, 0.07);
            transform: translateX(3px);
        }

        .rank-row:active {
            background: rgba(232, 101, 10, 0.14);
        }

        /* ── Chat popup: hidden by default, shown via JS ── */
        #chatPopup {
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 380px;
            max-height: 560px;
            z-index: 9999;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
            flex-direction: column;
        }

        #chatPopup.open {
            display: flex;
        }

        #chatOverlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.35);
            z-index: 9998;
            backdrop-filter: blur(2px);
        }

        #chatOverlay.open {
            display: block;
        }

        /* Mobile: chat popup full screen bottom sheet */
        @media (max-width: 768px) {
            #chatPopup {
                width: 100% !important;
                max-height: 85vh !important;
                bottom: 0 !important;
                right: 0 !important;
                border-radius: 20px 20px 0 0 !important;
            }
        }

        /* ── MOBILE FULL-WIDTH FIX ── */
        @media (max-width: 768px) {

            html,
            body {
                width: 100% !important;
                max-width: 100% !important;
                overflow-x: hidden !important;
            }

            section,
            .hero,
            .categories,
            .ranking-section,
            .rekomendasi-outer,
            .footer {
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }

            .hero {
                display: block !important;
                margin-top: -8px !important;
                padding: 16px !important;
                min-height: auto !important;
            }

            .hero-left,
            .hero-right {
                width: 100% !important;
                max-width: 100% !important;
                float: none !important;
            }

            .hero-right {
                margin-top: 16px !important;
                display: block !important;
            }

            .hero-image-wrap {
                width: 100% !important;
                height: 200px !important;
                margin: 0 !important;
            }

            .container {
                width: 100% !important;
                max-width: 100% !important;
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                padding: 0 !important;
            }

            .categories-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }

            .ranking-grid {
                grid-template-columns: minmax(0, 1fr) !important;
            }

            .footer-grid {
                grid-template-columns: minmax(0, 1fr) !important;
            }
        }
    </style>
</head>

<body>

    <!-- ══ TOPBAR (desktop) ══ -->
    <div class="topbar">
        <div class="top-left">
            <a href="user.php">Home</a><span class="sep">|</span>
            <a href="menu.php">Menu</a><span class="sep">|</span>
            <a href="pesan.php?tab=pesanan">Pesanan</a>
        </div>
        <div class="top-right">
            <a href="pesan.php?tab=notif"><i class="fa-regular fa-bell"></i> Notifikasi</a>

            <a href=""><i class="fa-regular fa-circle-question"></i> Bantuan</a>
            <div class="lang">
                <i class="fa-solid fa-globe" style="font-size:11px"></i>
                <span>Bahasa Indonesia</span>
                <i class="fa-solid fa-chevron-down"></i>
                <div class="lang-drop">
                    <a href="" class="active"><i class="fa-solid fa-check"></i> Bahasa Indonesia</a>
                    <a href="">English</a>
                </div>
            </div>
            <div class="profile">
                <?php if (!empty($pp_user)): ?>
                    <img src="../gambar/profil/<?php echo htmlspecialchars($pp_user); ?>"
                        alt="<?php echo htmlspecialchars($username_user); ?>"
                        onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($username_user); ?>&background=e8650a&color=fff&size=32'">
                <?php else: ?>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($username_user); ?>&background=e8650a&color=fff&size=32"
                        alt="<?php echo htmlspecialchars($username_user); ?>">
                <?php endif; ?>
                <span><?php echo htmlspecialchars($username_user); ?></span>
                <div class="profile-drop">
                    <a href="pesan.php?tab=akun"><i class="fa-regular fa-user"></i> Akun Saya</a>
                    <div class="drop-line"></div>
                    <a href="logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Log Out</a>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ NAVBAR DESKTOP ══ -->
    <div class="navbar orange-bg">
        <div class="navbar-texture"></div>
        <div class="orb1"></div>
        <div class="orb2"></div>
        <div class="logo">
            <div class="logo-img-wrap"><img src="../gambar/logo.png" alt="Logo"></div>
            <div class="logo-text">
                <span class="logo-title">E-Canteen</span>
                <span class="logo-sub">Esemkita</span>
            </div>
        </div>
        <div class="search-box">
            <input type="text" id="navSearchInput" placeholder="Cari makanan dan minuman sehat...">
            <button id="navSearchBtn"><i class="fa-solid fa-magnifying-glass"></i></button>
        </div>
        <div class="icon-actions">
            <div class="search-active-pill" id="searchPill" onclick="clearSearch()">
                <i class="fa-solid fa-xmark"></i>
                <span id="pillText">Hapus filter</span>
            </div>
            <a href="keranjang.php" style="text-decoration:none;color:inherit">
                <div class="icon-btn" style="position:relative">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span id="cart-badge-mobile" style="
            position:absolute; top:-6px; right:-6px;
            background:#ff6b00; color:#fff;
            font-size:10px; font-weight:700;
            min-width:18px; height:18px;
            border-radius:9px; padding:0 4px;
            display:<?php echo $total_keranjang > 0 ? 'flex' : 'none'; ?>;
            align-items:center; justify-content:center;">
                        <?php echo $total_keranjang > 0 ? $total_keranjang : ''; ?>
                    </span>
                </div>
            </a>
            <button onclick="openChatPopup()" class="icon-btn notif-chat-btn"
                style="background:none;border:none;cursor:pointer;">
                <i class="fa-solid fa-comment-dots"></i>
                <span class="chat-badge" id="chatBadge" style="display:none"></span>
            </button>
        </div>
    </div>

    <!-- ══ MOBILE TOP NAV ══ -->
    <div class="mobile-nav">
        <div class="mob-top orange-bg">
            <div class="mob-orb"></div>
            <div class="mob-logo">
                <img src="../gambar/logo.png" alt="Logo">
                <div class="mob-logo-text">
                    <span class="mob-logo-title">E-Canteen</span>
                    <span class="mob-logo-sub">Esemkita</span>
                </div>
            </div>
            <div class="mob-icons">
                <!-- Chat button -->
                <button onclick="openChatPopup()" class="icon-btn notif-chat-btn"
                    style="background:none;border:none;cursor:pointer;">
                    <i class="fa-solid fa-comment-dots"></i>
                    <span class="chat-badge" id="chatBadgeMob" style="display:none"></span>
                </button>
                <!-- Cart button -->
                <div class="mob-icon-btn">
                    <a href="keranjang.php"
                        style="color:inherit;text-decoration:none;display:flex;align-items:center;justify-content:center;width:100%;height:100%;">
                        <i class="fa-solid fa-cart-shopping"></i>
                        <?php if ($total_keranjang > 0): ?>
                            <div class="mob-badge"><?php echo $total_keranjang; ?></div>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ BOTTOM NAV MOBILE ══ -->
    <div class="mob-bottom-nav">
        <a href="user.php" class="mob-nav-item active">
            <i class="fa-solid fa-house"></i>
            <span>Home</span>
        </a>
        <a href="menu.php" class="mob-nav-item">
            <i class="fa-solid fa-bowl-food"></i>
            <span>Menu</span>
        </a>

        <a href="#" class="mob-nav-search" style="text-decoration:none;color:inherit;" onclick="event.preventDefault(); document.getElementById('searchOverlay').classList.add('open'); setTimeout(function(){ document.getElementById('sheetInput').focus(); }, 200);">
            <div class="mob-nav-search-btn">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>
            <span>Cari</span>
        </a>
        <a href="pesan.php?tab=pesanan" class="mob-nav-item" style="position:relative">
            <i class="fa-solid fa-bag-shopping"></i>
            <span>Pesanan</span>
        </a>
        <a href="pesan.php?tab=akun" class="mob-nav-item">
            <i class="fa-regular fa-user"></i>
            <span>Akun</span>
        </a>
    </div>

    <!-- ══ SEARCH OVERLAY (mobile) ══ -->
    <div class="search-overlay" id="searchOverlay" onclick="if(event.target===this)this.classList.remove('open')">
        <div class="search-sheet">
            <div class="search-sheet-handle"></div>
            <h3>Cari Makanan &amp; Minuman</h3>
            <div class="search-sheet-box">
                <input id="sheetInput" type="text" placeholder="Ketik nama menu...">
                <button onclick="runSheetSearch()"><i class="fa-solid fa-magnifying-glass"></i></button>
            </div>
            <div class="sheet-result-count" id="sheetResultCount"></div>
            <div class="search-suggestions">
                <p>Populer</p>
                <div class="suggestion-chips">
                    <div class="chip" onclick="searchFromChip(this)">Nasi Goreng</div>
                    <div class="chip" onclick="searchFromChip(this)">Mie Ayam</div>
                    <div class="chip" onclick="searchFromChip(this)">Es Teh</div>
                    <div class="chip" onclick="searchFromChip(this)">Bakso</div>
                    <div class="chip" onclick="searchFromChip(this)">Soto Ayam</div>
                    <div class="chip" onclick="searchFromChip(this)">Jus Alpukat</div>
                </div>
            </div>
        </div>
    </div>

    <div class="mob-bottom-spacer"></div>

    <!-- ══ HERO ══ -->
    <section class="hero">
        <div class="hero-left">
            <h1 class="hero-title">
                <span class="line1">Kantin SMK</span>
                <span class="line2">Sehat, Enak,</span>
                <span class="line3">Terjangkau</span>
            </h1>
            <p class="hero-desc">
                Nikmati berbagai pilihan makanan dan minuman bergizi dengan harga terjangkau setiap hari —
                pesan mudah, ambil cepat, bayar digital.
            </p>
            <div class="hero-cta">
                <a href="menu.php" class="btn-primary">🍽 Lihat Menu</a>
            </div>
        </div>

        <div class="hero-right">
            <div class="hero-image-wrap">
                
                <div class="hero-image">
                    <img src="../gambar/kantinsmea.png" alt="Kantin">
                </div>
            </div>
        </div>
    </section>

    <!-- ══ CATEGORY CANTEEN ══ -->
    <section class="categories">
        <h1 class="categories-title">CATEGORY CANTEEN</h1>
        <?php if (empty($daftar_kantin)): ?>
            <div style="text-align:center;padding:40px 20px;color:#aaa;">
                <i class="fa-solid fa-store-slash"
                    style="font-size:36px;color:#e8650a;opacity:.4;display:block;margin-bottom:12px;"></i>
                <p style="font-size:14px;font-weight:600;">Semua kantin sedang tutup</p>
                <p style="font-size:12px;">Silakan cek kembali nanti</p>
            </div>
        <?php else: ?>
            <div class="categories-grid">
                <?php foreach ($daftar_kantin as $kantin):
                    $is_buka = (int)($kantin['buka'] ?? 1) === 1;
                    $imgSrc  = !empty($kantin['profil'])
                        ? '../admin/uploads/' . htmlspecialchars($kantin['profil'])
                        : '../gambar/default-kantin.jpg';
                ?>
                    <?php if ($is_buka): ?>
                    <!-- Kantin BUKA: bisa diklik -->
                    <a href="kategori.php?id_kantin=<?= (int)$kantin['id_kantin'] ?>" style="text-decoration:none">
                    <?php else: ?>
                    <!-- Kantin TUTUP: tidak bisa diklik -->
                    <div style="text-decoration:none;cursor:default;">
                    <?php endif; ?>

                        <div class="kantin-card <?= $is_buka ? '' : 'tutup' ?>">
                            <div class="card-image">
                                <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($kantin['nama']) ?>"
                                    onerror="this.src='../gambar/default-kantin.jpg'">
                            </div>

                            <?php if ($is_buka): ?>
                            <!-- Badge BUKA -->
                            <div class="kantin-buka-badge">
                                <i class="fa-solid fa-circle" style="color:#22c55e;font-size:7px;"></i> 
                            </div>
                            <?php else: ?>
                            <!-- Overlay + Badge TUTUP -->
                            <div class="kantin-tutup-overlay">
                                <div class="kantin-tutup-badge">
                                    <i class="fa-solid fa-store-slash" style="font-size:10px;"></i>
                                    Tutup
                                </div>
                            </div>
                            <?php endif; ?>

                            <h3><?= htmlspecialchars($kantin['nama']) ?></h3>
                        </div>

                    <?php if ($is_buka): ?>
                    </a>
                    <?php else: ?>
                    </div>
                    <?php endif; ?>

                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- ══ RANKING ══ -->
    <section class="ranking-section">
        <div class="ranking-grid">

            <!-- MAKANAN -->
            <div class="rank-card">
                <div class="rank-card-head">
                    <div class="rank-head-left">
                        <div class="rank-icon makan"><img src="../gambar/image.png" alt=""></div>
                        <div>
                            <div class="rank-card-name">Peringkat Makanan</div>
                            <div class="rank-card-sub">Rating tertinggi &amp; ulasan terbanyak</div>
                        </div>
                    </div>
                </div>
                <div class="rank-list">
                    <?php if (!empty($top_makanan)): ?>
                        <?php foreach ($top_makanan as $i => $item): ?>
                            <div class="rank-row" onclick="window.location.href='klik.php?id=<?php echo (int) $item['id']; ?>'">
                                <div class="rank-num"><img src="<?php echo $medal[$i + 1] ?? $medal[3]; ?>"
                                        alt="#<?php echo $i + 1; ?>"></div>
                                <img class="rank-img" src="../penjual/uploads/<?php echo htmlspecialchars($item['foto']); ?>"
                                    alt="<?php echo htmlspecialchars($item['nama_makanan']); ?>"
                                    onerror="this.src='../gambar/default-food.png'">
                                <div class="rank-info">
                                    <div class="rank-item-name"><?php echo htmlspecialchars($item['nama_makanan']); ?></div>
                                    <div class="rank-meta">
                                        <span class="rank-stars"><?php echo renderStars((float) $item['rata_rating']); ?></span>
                                        <span><?php echo number_format((float) $item['rata_rating'], 1); ?>
                                            (<?php echo (int) $item['jumlah_ulasan']; ?> ulasan)</span>
                                        <span><?php echo (int) $item['terjual']; ?> terjual</span>
                                        <?php if ($i === 0): ?><span class="rank-hot">🔥 Terlaris</span><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:#aaa;padding:16px;font-size:13px;">Belum ada data penjualan.</p>
                    <?php endif; ?>
                </div>
                <div class="rank-foot"><a href="menu.php?kategori=Makanan" class="rank-foot-btn makan">Lihat Semua
                        Makanan ›</a></div>
            </div>

            <!-- MINUMAN -->
            <div class="rank-card">
                <div class="rank-card-head">
                    <div class="rank-head-left">
                        <div class="rank-icon minum"><img src="../gambar/image.png" alt=""></div>
                        <div>
                            <div class="rank-card-name">Peringkat Minuman</div>
                            <div class="rank-card-sub">Rating tertinggi &amp; ulasan terbanyak</div>
                        </div>
                    </div>
                </div>
                <div class="rank-list">
                    <?php if (!empty($top_minuman)): ?>
                        <?php foreach ($top_minuman as $i => $item): ?>
                            <div class="rank-row" onclick="window.location.href='klik.php?id=<?php echo (int) $item['id']; ?>'">
                                <div class="rank-num"><img src="<?php echo $medal[$i + 1] ?? $medal[3]; ?>"
                                        alt="#<?php echo $i + 1; ?>"></div>
                                <img class="rank-img" src="../penjual/uploads/<?php echo htmlspecialchars($item['foto']); ?>"
                                    alt="<?php echo htmlspecialchars($item['nama_makanan']); ?>"
                                    onerror="this.src='../gambar/default-food.png'">
                                <div class="rank-info">
                                    <div class="rank-item-name"><?php echo htmlspecialchars($item['nama_makanan']); ?></div>
                                    <div class="rank-meta">
                                        <span class="rank-stars"><?php echo renderStars((float) $item['rata_rating']); ?></span>
                                        <span><?php echo number_format((float) $item['rata_rating'], 1); ?>
                                            (<?php echo (int) $item['jumlah_ulasan']; ?> ulasan)</span>
                                        <span><?php echo (int) $item['terjual']; ?> terjual</span>
                                        <?php if ($i === 0): ?><span class="rank-hot">🔥 Terlaris</span><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:#aaa;padding:16px;font-size:13px;">Belum ada data penjualan.</p>
                    <?php endif; ?>
                </div>
                <div class="rank-foot"><a href="menu.php?kategori=Minuman" class="rank-foot-btn minum">Lihat Semua
                        Minuman ›</a></div>
            </div>

            <!-- SNACK -->
            <div class="rank-card">
                <div class="rank-card-head">
                    <div class="rank-head-left">
                        <div class="rank-icon snack"><img src="../gambar/image.png" alt=""></div>
                        <div>
                            <div class="rank-card-name">Peringkat Snack</div>
                            <div class="rank-card-sub">Rating tertinggi &amp; ulasan terbanyak</div>
                        </div>
                    </div>
                </div>
                <div class="rank-list">
                    <?php if (!empty($top_snack)): ?>
                        <?php foreach ($top_snack as $i => $item): ?>
                            <div class="rank-row" onclick="window.location.href='klik.php?id=<?php echo (int) $item['id']; ?>'">
                                <div class="rank-num"><img src="<?php echo $medal[$i + 1] ?? $medal[3]; ?>"
                                        alt="#<?php echo $i + 1; ?>"></div>
                                <img class="rank-img" src="../penjual/uploads/<?php echo htmlspecialchars($item['foto']); ?>"
                                    alt="<?php echo htmlspecialchars($item['nama_makanan']); ?>"
                                    onerror="this.src='../gambar/default-food.png'">
                                <div class="rank-info">
                                    <div class="rank-item-name"><?php echo htmlspecialchars($item['nama_makanan']); ?></div>
                                    <div class="rank-meta">
                                        <span class="rank-stars"><?php echo renderStars((float) $item['rata_rating']); ?></span>
                                        <span><?php echo number_format((float) $item['rata_rating'], 1); ?>
                                            (<?php echo (int) $item['jumlah_ulasan']; ?> ulasan)</span>
                                        <span><?php echo (int) $item['terjual']; ?> terjual</span>
                                        <?php if ($i === 0): ?><span class="rank-hot">🔥 Terlaris</span><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:#aaa;padding:16px;font-size:13px;">Belum ada data penjualan.</p>
                    <?php endif; ?>
                </div>
                <div class="rank-foot"><a href="menu.php?kategori=Snack" class="rank-foot-btn snack">Lihat Semua Snack
                        ›</a></div>
            </div>

        </div>
    </section>

    <!-- ══ REKOMENDASI ══ -->
    <div class="rekomendasi-outer">
        <div class="rekomendasi-title-wrap">
            <h2 class="rekomendasi-title">REKOMENDASI</h2>
        </div>

        <div class="container" id="cardContainer">
            <?php if (!empty($daftar_menu)): ?>
                <?php foreach ($daftar_menu as $menu): ?>
                    <a href="klik.php?id=<?php echo $menu['id']; ?>" style="display:flex;flex-direction:column;">
                        <div class="card" data-nama="<?php echo htmlspecialchars($menu['nama_makanan']); ?>"
                            data-kantin="<?php echo htmlspecialchars($menu['nama_kantin'] ?? ''); ?>"
                            data-kategori="<?php echo htmlspecialchars($menu['kategori']); ?>">
                            <div class="gambar">
                                <img src="../penjual/uploads/<?php echo htmlspecialchars($menu['foto']); ?>"
                                    alt="<?php echo htmlspecialchars($menu['nama_makanan']); ?>"
                                    onerror="this.src='../gambar/default-food.png'">
                            </div>
                            <div class="isi">
                                <div class="kategori">
                                    <span class="kantin"><?php echo htmlspecialchars($menu['nama_kantin'] ?? ''); ?></span>
                                    <div class="sekat"></div>
                                    <?php if ($menu['kategori'] == 'Minuman'): ?>
                                        <i class="fa-solid fa-mug-hot"></i>
                                    <?php else: ?>
                                        <i class="fa-solid fa-utensils"></i>
                                    <?php endif; ?>
                                    <span class="makanan"><?php echo htmlspecialchars($menu['kategori']); ?></span>
                                </div>
                                <div class="nama"><?php echo htmlspecialchars($menu['nama_makanan']); ?></div>
                                <div class="harga">Rp<?php echo number_format($menu['harga'], 0, ',', '.'); ?></div>
                                <div class="info">
                                    <div class="box">
                                        <div class="atas">
                                            <i class="fa-solid fa-star star"></i>
                                            <span><?php $r = (float) ($menu['rata_rating'] ?? 0);
                                            echo $r > 0 ? number_format($r, 1) : '-'; ?></span>
                                        </div>
                                        <div class="bawah">Rating</div>
                                    </div>
                                    <div class="garis"></div>
                                    <div class="box">
                                        <div class="atas">
                                            <i class="fa-solid fa-bag-shopping"></i>
                                            <span><?php echo (int) ($menu['terjual'] ?? 0); ?></span>
                                        </div>
                                        <div class="bawah">Terjual</div>
                                    </div>
                                    <div class="garis"></div>
                                    <div class="box">
                                        <div class="atas">
                                            <i class="fa-solid fa-box"></i>
                                            <span><?php echo htmlspecialchars($menu['stok']); ?></span>
                                        </div>
                                        <div class="bawah">Stock</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column:1/-1;text-align:center;padding:48px 20px;color:#aaa;">
                    <i class="fa-solid fa-store-slash"
                        style="font-size:42px;color:#e8650a;opacity:.4;display:block;margin-bottom:14px;"></i>
                    <h3 style="font-size:16px;font-weight:700;color:#555;margin-bottom:6px;">Belum ada menu tersedia</h3>
                    <p style="font-size:13px;">Semua kantin sedang tutup. Silakan cek kembali nanti.</p>
                </div>
            <?php endif; ?>

            <div class="no-result-msg" id="noResultMsg">
                <i class="fa-solid fa-magnifying-glass"></i>
                <h3>Menu tidak ditemukan</h3>
                <p id="noResultText">Coba kata kunci lain</p>
            </div>
        </div>

        <div class="lihat-lainnya-wrap">
            <a href="menu.php" class="lihat-lainnya-btn">
                Lihat Lainnya <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
    </div>

    <!-- ══ FOOTER ══ -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <footer class="footer" id="kontak">
        <div class="footer-grid">

            <!-- Brand -->
            <div class="footer-brand">
                <img src="../gambar/logo.png" alt="logo">
                <p>E-Canteen adalah platform kantin digital sekolah untuk mempermudah pemesanan makanan, pembayaran, dan
                    informasi menu secara online.</p>
                <div class="footer-quote">
                    <i class="bi bi-quote"></i>
                    <span>Makan nyaman, belajar tenang.</span>
                </div>
                <div class="footer-jam">
                    <h4><i class="bi bi-clock"></i> JAM OPERASIONAL</h4>
                    <div class="jam-row">
                        <span>Senin – Jumat</span>
                        <span>07.00 – 15.00</span>
                    </div>
                </div>
            </div>

            <!-- Menu -->
            <div class="footer-col">
                <h3>MENU</h3>
                <ul>
                    <li><a href="#"><i class="bi bi-chevron-right"></i> Home</a></li>
                    <li><a href="#"><i class="bi bi-chevron-right"></i> Menu Kantin</a></li>
                    <li><a href="#"><i class="bi bi-chevron-right"></i> Pesanan Saya</a></li>
                    <li><a href="#"><i class="bi bi-chevron-right"></i> Tentang Kami</a></li>
                    <li><a href="#"><i class="bi bi-chevron-right"></i> FAQ</a></li>
                    <li><a href="#"><i class="bi bi-chevron-right"></i> Kontak Kami</a></li>
                    <li><a href="pusat_kendala.php"><i class="bi bi-chevron-right"></i> Pusat Kendala</a></li>
                </ul>
                <div class="footer-kategori">
                    <h3>KATEGORI</h3>
                    <ul>
                        <li><a href="menu.php?kategori=Makanan"><i class="bi bi-egg-fried"></i> Makanan</a></li>
                        <li><a href="menu.php?kategori=Minuman"><i class="bi bi-cup-straw"></i> Minuman</a></li>
                        <li><a href="menu.php?kategori=Snack"><i class="bi bi-basket"></i> Snack</a></li>
                    </ul>
                </div>
            </div>

            <!-- Kontak -->
            <div class="footer-col">
                <h3>KONTAK KAMI</h3>
                <div class="contact-item">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span>No. 3 Jl. Ki Mangun Sarkoro 66233 <br> Boyolangu Jawa Timur</span>
                </div>
                <div class="contact-item">
                    <i class="bi bi-telephone-fill"></i>
                    <span>0812-3456-7890</span>
                </div>
                <div class="contact-item">
                    <i class="bi bi-envelope-fill"></i>
                    <a href="mailto:canteen@gmail.com">canteen@gmail.com</a>
                </div>
                <div class="contact-item">
                    <i class="bi bi-clock-fill"></i>
                    <span>Senin – Jumat 07.00 – 15.00</span>
                </div>
                <div id="map"></div>
            </div>

            <!-- Sosmed -->
            <div class="footer-col">
                <h3>IKUTI KAMI</h3>
                <div class="footer-social">
                    <a href="https://instagram.com/username_kalian" target="_blank"><i class="bi bi-instagram"></i></a>
                    <a href="https://tiktok.com/@username_kalian" target="_blank"><i class="bi bi-tiktok"></i></a>
                    <a href="https://youtube.com/@channel_kalian" target="_blank"><i class="bi bi-youtube"></i></a>
                    <a href="https://wa.me/6285807106570" target="_blank"><i class="bi bi-whatsapp"></i></a>
                </div>
                <div class="footer-help">
                    <h3>BUTUH BANTUAN?</h3>
                    <p>Punya pertanyaan atau keluhan? Hubungi kami, tim kami siap membantu!</p>
                    <a href="mailto:canteen@gmail.com">
                        <button>Hubungi Kami <i class="bi bi-arrow-right"></i></button>
                    </a>
                </div>
            </div>

        </div>
        <div class="footer-bottom">
            <p>© 2026 E-Canteen Sekolah. All rights reserved.<br>Made with ❤️ by ZigiZaga Team</p>
        </div>
    </footer>

    <!-- LEAFLET -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var map = L.map('map').setView([-8.1165, 111.8931], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            L.marker([-8.1165, 111.8931])
                .addTo(map)
                .bindPopup('<b>SMK E-Canteen</b><br>Jl. Ki Mangun Sarkoro No.3, Boyolangu')
                .openPopup();
        });
    </script>

    <!-- ══ SCRIPTS ══ -->
    <script>
        /* ── Chat badge notifikasi ── */
        async function loadNotifChat() {
            try {
                const res = await fetch('chat_api.php?action=jumlah_belum_baca');
                const data = await res.json();
                const badges = document.querySelectorAll('.chat-badge');
                badges.forEach(b => {
                    if (data.belum_baca > 0) {
                        b.style.display = 'flex';
                        b.textContent = data.belum_baca;
                    } else {
                        b.style.display = 'none';
                    }
                });
            } catch (e) { console.error('Notif chat error:', e); }
        }
        loadNotifChat();
        setInterval(loadNotifChat, 10000);

        /* ── Search redirect ── */
        const navInput = document.getElementById('navSearchInput');
        const navBtn = document.getElementById('navSearchBtn');
        const sheetInput = document.getElementById('sheetInput');

        function goSearch(query) {
            const q = query.trim();
            if (!q) return;
            window.location.href = 'search.php?q=' + encodeURIComponent(q);
        }

        navBtn.addEventListener('click', () => goSearch(navInput.value));
        navInput.addEventListener('keydown', e => { if (e.key === 'Enter') goSearch(navInput.value); });
        sheetInput.addEventListener('keydown', e => { if (e.key === 'Enter') goSearch(sheetInput.value); });

        function searchFromChip(el) {
            document.getElementById('searchOverlay').classList.remove('open');
            goSearch(el.textContent.trim());
        }
        function runSheetSearch() {
            const val = document.getElementById('sheetInput').value.trim();
            if (!val) return;
            document.getElementById('searchOverlay').classList.remove('open');
            goSearch(val);
        }
    </script>

    <?php include 'chat_popup.php'; ?>

</body>

</html>