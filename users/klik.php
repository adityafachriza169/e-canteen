<?php
// ============================================================
// klik.php — Halaman Detail Produk
// Disesuaikan dengan skema DB terbaru
// ============================================================
session_start();
require 'koneksi.php';


if (!isset($_SESSION['id_user'])) {
    header('Location: login.php'); exit;
}

$id_user = (int)$_SESSION['id_user'];

// ── Ambil id dari URL ──
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: menu.php'); exit; }

// ── Data Produk + Kantin + Rating ──
// Catatan:
//   • Tabel menu     → menu_req (kolom: id, id_kantin, nama_makanan, foto, harga,
//                       stok, terjual, kategori, deskripsi, waktu_proses, aktif)
//   • Tabel kantin   → kolom kunci: id (PK), nama_kantin
//     kolom opsional (dipakai jika ada): foto, status, bergabung,
//     total_produk, pengikut, persen_chat, waktu_balas
//   • v_rating_menu  → view: id_menu, total_ulasan, rata_bintang
$sqlProduk = "
    SELECT
        m.id, m.id_kantin,
        m.nama_makanan, m.foto, m.harga, m.stok,
        m.terjual, m.kategori,
        m.deskripsi,
        m.waktu_proses,
        k.nama_kantin,
        p.id_penjual,
        p.nama   AS nama_penjual,
        p.profil AS foto_kantin,
        COALESCE((SELECT AVG(bintang) FROM ulasan WHERE id_menu = m.id), 0) AS rata_bintang,
        COALESCE((SELECT COUNT(*)    FROM ulasan WHERE id_menu = m.id), 0) AS total_ulasan,
        'online' AS status_kantin,
        NULL AS bergabung,
        0 AS total_produk,
        0 AS pengikut,
        100 AS persen_chat,
        'hitungan menit' AS waktu_balas
    FROM menu_req m
    JOIN kantin k ON m.id_kantin = k.id_kantin
    JOIN penjual p ON m.id_kantin = p.id_kantin
    WHERE m.id = $id AND m.aktif = 1
      AND p.buka = 1 AND p.status = 1
    LIMIT 1
";
$resProduk = mysqli_query($konek, $sqlProduk);
if (!$resProduk || mysqli_num_rows($resProduk) === 0) {
    header('Location: menu.php'); exit;
}
$produk = mysqli_fetch_assoc($resProduk);
$idKantin    = (int)$produk['id_kantin'];
$idPenjual   = (int)($produk['id_penjual'] ?? 0);
$namaPenjual = $produk['nama_penjual'] ?? $produk['nama_kantin'];
$fotoPenjual = $produk['foto_kantin']  ?? '';

// ── Foto Galeri ──
// Tabel foto_menu tidak ada di DB, langsung pakai foto utama dari menu_req
$fotos = [];
if (!empty($produk['foto'])) {
    $fotos = [['nama_file' => $produk['foto']]];
}
$fotoUtama = !empty($fotos) ? $fotos[0]['nama_file'] : '';

// ── Varian (tabel varian_menu) ──
// kolom: id, id_menu, nama, foto_varian, harga_tambah
$resVarian = mysqli_query($konek, "SELECT * FROM varian_menu WHERE id_menu = $id ORDER BY id ASC");
$varians = [];
while ($row = mysqli_fetch_assoc($resVarian)) { $varians[] = $row; }

// ── Ulasan ──
// users.id = kolom PK tabel users (join ke ulasan.id_user)
$resUlasan = mysqli_query($konek, "
    SELECT u.id, u.bintang, u.komentar, u.created_at, u.foto AS foto_ulasan,
           us.username, us.pp
    FROM ulasan u
    JOIN users us ON u.id_user = us.id_user
    WHERE u.id_menu = $id
    ORDER BY u.created_at DESC
");
$ulasanList = [];
while ($ul = mysqli_fetch_assoc($resUlasan)) {
    $ul['fotos'] = !empty($ul['foto_ulasan'])
        ? ['uploads/ulasan/' . $ul['foto_ulasan']]
        : [];
    $ulasanList[] = $ul;
}

// ── Rekomendasi dari toko yang sama ──
$resRekToko = mysqli_query($konek, "
    SELECT m.id, m.nama_makanan, m.foto, m.harga, m.stok, m.terjual, m.kategori,
           k.nama_kantin,
           COALESCE((SELECT AVG(bintang) FROM ulasan WHERE id_menu = m.id), 0) AS rata_bintang
    FROM menu_req m
    JOIN kantin k ON m.id_kantin = k.id_kantin
    JOIN penjual p ON m.id_kantin = p.id_kantin
    WHERE m.id_kantin = $idKantin AND m.id != $id AND m.aktif = 1
      AND p.buka = 1 AND p.status = 1
    ORDER BY m.terjual DESC
    LIMIT 6
");
$rekToko = [];
while ($row = mysqli_fetch_assoc($resRekToko)) { $rekToko[] = $row; }

// ── Rekomendasi lainnya ──
$resRekLain = mysqli_query($konek, "
    SELECT m.id, m.nama_makanan, m.foto, m.harga, m.stok, m.terjual, m.kategori,
           k.nama_kantin,
           COALESCE((SELECT AVG(bintang) FROM ulasan WHERE id_menu = m.id), 0) AS rata_bintang
    FROM menu_req m
    JOIN kantin k ON m.id_kantin = k.id_kantin
    JOIN penjual p ON m.id_kantin = p.id_kantin
    WHERE m.id_kantin != $idKantin AND m.aktif = 1
      AND p.buka = 1 AND p.status = 1
    ORDER BY m.terjual DESC
    LIMIT 12
");
$rekLain = [];
while ($row = mysqli_fetch_assoc($resRekLain)) { $rekLain[] = $row; }

// ── Total keranjang user ──
$qCart = mysqli_prepare($konek, "SELECT COALESCE(SUM(jumlah),0) AS total FROM keranjang WHERE id_user=?");
mysqli_stmt_bind_param($qCart, "i", $id_user);
mysqli_stmt_execute($qCart);
$totalKeranjang = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($qCart))['total'];

// ── Helpers ──
function rupiah($n) {
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}
function bintangHTML($n) {
    $n = (float)$n;
    $h = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($n >= $i) {
            $h .= '<span style="color:var(--orange,#ff7a00)">★</span>';
        } elseif ($n >= $i - 0.5) {
            $h .= '<span style="position:relative;display:inline-block;">
                     <span style="color:#ddd">★</span>
                     <span style="position:absolute;left:0;top:0;width:55%;overflow:hidden;color:var(--orange,#ff7a00)">★</span>
                   </span>';
        } else {
            $h .= '<span style="color:#ddd">★</span>';
        }
    }
    return $h;
}

function cardProduk($p) {
    $foto   = !empty($p['foto'])
        ?'../penjual/uploads/' . htmlspecialchars($p['foto'])
        : '../gambar/default-food.png';
    $nama    = htmlspecialchars($p['nama_makanan']);
    $kantin  = htmlspecialchars($p['nama_kantin']);
    $harga   = rupiah($p['harga']);
    $terjual = (int)$p['terjual'];
    $stok    = (int)$p['stok'];
    $rating  = number_format((float)$p['rata_bintang'], 1);
    $idProd  = (int)$p['id'];
    $ikon    = $p['kategori'] === 'Minuman' ? 'fa-mug-hot' : 'fa-utensils';
    $kategori = htmlspecialchars($p['kategori']);

    return <<<HTML
    <a href="klik.php?id={$idProd}" style="text-decoration:none;color:inherit;display:block;">
        <div class="card">
            <div class="gambar">
                <img src="{$foto}" alt="{$nama}" loading="lazy"
                    onerror="this.src='../gambar/default-food.png'">
            </div>
            <div class="isi">
                <div class="kategori">
                    <span class="kantin">{$kantin}</span>
                    <div class="sekat"></div>
                    <i class="fa-solid {$ikon}"></i>
                    <span class="makanan">{$kategori}</span>
                </div>
                <div class="nama">{$nama}</div>
                <div class="harga">{$harga}</div>
                <div class="info">
                    <div class="box">
                        <div class="atas"><i class="fa-solid fa-star star"></i><span>{$rating}</span></div>
                        <div class="bawah">Rating</div>
                    </div>
                    <div class="garis"></div>
                    <div class="box">
                        <div class="atas"><i class="fa-solid fa-bag-shopping"></i><span>{$terjual}</span></div>
                        <div class="bawah">Terjual</div>
                    </div>
                    <div class="garis"></div>
                    <div class="box">
                        <div class="atas"><i class="fa-solid fa-box"></i><span>{$stok}</span></div>
                        <div class="bawah">Stock</div>
                    </div>
                </div>
            </div>
        </div>
    </a>
HTML;
}

// ── Session user ──
$username_user = $_SESSION['username'] ?? 'Tamu';
$pp_user       = $_SESSION['pp']       ?? null;

// ── Tahun bergabung kantin ──
$tahunBergabung = '-';
if (!empty($produk['bergabung'])) {
    try {
        $diff = (new DateTime())->diff(new DateTime($produk['bergabung']));
        $tahunBergabung = $diff->y > 0 ? $diff->y . ' tahun lalu' : ($diff->m > 0 ? $diff->m . ' bulan lalu' : 'Baru bergabung');
    } catch (Exception $e) { $tahunBergabung = '-'; }
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
    <title><?= htmlspecialchars($produk['nama_makanan']) ?> — E-Canteen Esemkita</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Righteous&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="klik.css">
    <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
    <style>
        /* ── Animasi terbang ke keranjang (Shopee style) ── */
        .fly-img {
            position: fixed;
            width: 50px; height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e8650a;
            pointer-events: none;
            z-index: 99999;
            transition: none;
        }
        .fly-img.animate {
            transition: top .7s cubic-bezier(.4,0,.2,1),
                        left .7s cubic-bezier(.4,0,.2,1),
                        opacity .7s ease,
                        transform .7s ease;
            opacity: 0;
            transform: scale(.2);
        }
        @keyframes badgeBounce {
            0%  { transform: scale(1); }
            30% { transform: scale(1.6); }
            60% { transform: scale(.85); }
            100%{ transform: scale(1); }
        }
        .badge-bounce { animation: badgeBounce .5s ease forwards; }

        #cart-toast {
            position: fixed;
            bottom: 80px; left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: #222; color: #fff;
            padding: 10px 22px; border-radius: 24px;
            font-size: 13px; font-weight: 500;
            opacity: 0; pointer-events: none;
            transition: opacity .3s, transform .3s;
            z-index: 99999; white-space: nowrap;
        }
        #cart-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

        /* ── VARIAN POPUP FOTO ── */
        #varianFotoPopup {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.75);
            z-index: 100000;
            align-items: center;
            justify-content: center;
        }
        #varianFotoPopup.show { display: flex; }
        #varianFotoPopup .vfp-inner {
            background: #fff;
            border-radius: 16px;
            padding: 16px;
            max-width: 380px;
            width: 90%;
            text-align: center;
            position: relative;
            animation: vfpIn .25s ease;
        }
        @keyframes vfpIn {
            from { opacity:0; transform: scale(.88); }
            to   { opacity:1; transform: scale(1); }
        }
        #varianFotoPopup .vfp-inner img {
            width: 100%; max-height: 300px;
            object-fit: cover;
            border-radius: 10px;
        }
        #varianFotoPopup .vfp-nama {
            margin-top: 10px;
            font-weight: 700;
            font-size: 15px;
            color: #222;
        }
        #varianFotoPopup .vfp-harga {
            font-size: 13px;
            color: #e8650a;
            margin-top: 4px;
        }
        #varianFotoPopup .vfp-close {
            position: absolute;
            top: 10px; right: 14px;
            background: none; border: none;
            font-size: 22px; cursor: pointer;
            color: #555; line-height: 1;
        }

        /* ── Harga total animasi ── */
        .price-box.price-updated {
            animation: priceFlash .4s ease;
        }
        @keyframes priceFlash {
            0%   { transform: scale(1); }
            40%  { transform: scale(1.08); color: #e8650a; }
            100% { transform: scale(1); }
        }

        /* ── Variant chip dengan foto kecil ── */
        .variant-chip { position: relative; cursor: pointer; }
        .variant-chip img {
            width: 28px; height: 28px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 4px;
            vertical-align: middle;
            border: 1.5px solid #ddd;
            cursor: pointer;
            transition: border-color .2s;
        }
        .variant-chip.active img { border-color: #e8650a; }
        .variant-chip img:hover { border-color: #e8650a; }
        .variant-chip .chip-harga-tag {
            display: block;
            font-size: 10px;
            color: #e8650a;
            font-weight: 600;
            margin-top: 2px;
        }
        @keyframes varianShake {
            0%,100% { transform: translateX(0); }
            20%     { transform: translateX(-5px); }
            40%     { transform: translateX(5px); }
            60%     { transform: translateX(-4px); }
            80%     { transform: translateX(4px); }
        }
        .variant-chip.wajib-pilih {
            outline: 2px solid #e8650a;
            border-radius: 8px;
        }
    </style>
</head>
<body>

<!-- ── TOPBAR ── -->
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
            <?php if ($pp_user): ?>
                <img src="../gambar/profil/<?= htmlspecialchars($pp_user) ?>"
                     alt="<?= htmlspecialchars($username_user) ?>"
                     onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($username_user) ?>&background=e8650a&color=fff&size=32'">
            <?php else: ?>
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($username_user) ?>&background=e8650a&color=fff&size=32"
                     alt="<?= htmlspecialchars($username_user) ?>">
            <?php endif; ?>
            <span><?= htmlspecialchars($username_user) ?></span>
            <div class="profile-drop">
                <a href="pesan.php?tab=akun"><i class="fa-regular fa-user"></i> Akun Saya</a>
                <div class="drop-line"></div>
                <a href="logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Log Out</a>
            </div>
        </div>
    </div>
</div>

<!-- ── NAVBAR (DESKTOP) ── -->
<div class="navbar orange-bg">
    <div class="navbar-texture"></div>
    <div class="orb1"></div><div class="orb2"></div>
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
        <a href="keranjang.php" style="text-decoration:none;color:inherit">
            <div class="icon-btn" id="cartIconDesktop">
                <i class="fa-solid fa-cart-shopping"></i>
                <div class="icon-badge" id="cartBadgeDesktop"><?= $totalKeranjang > 0 ? $totalKeranjang : '' ?></div>
            </div>
        </a>
       <button onclick="openChatPopup()" class="icon-btn notif-chat-btn" style="background:none;border:none;cursor:pointer;">
            <i class="fa-solid fa-comment-dots"></i>
            <span class="chat-badge" id="chatBadge" style="display:none"></span>
        </button>
    </div>
</div>

<!-- ── MOBILE NAV ── -->
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
            <button onclick="openChatPopup()" class="icon-btn notif-chat-btn" style="background:none;border:none;cursor:pointer;">
            <i class="fa-solid fa-comment-dots"></i>
            <span class="chat-badge" id="chatBadge" style="display:none"></span>
        </button>
            <a href="keranjang.php" style="text-decoration:none;color:inherit">
                <div class="mob-icon-btn" id="cartIconMob">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <div class="mob-badge" id="cartBadgeMob"><?= $totalKeranjang > 0 ? $totalKeranjang : '' ?></div>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- ── MOBILE BOTTOM NAV ── -->
<div class="mob-bottom-nav">

    <a href="user.php" class="mob-nav-link">
        <div class="mob-nav-item">
            <i class="fa-solid fa-house"></i>
            <span>Home</span>
        </div>
    </a>

    <a href="menu.php" class="mob-nav-link">
        <div class="mob-nav-item">
            <i class="fa-solid fa-bowl-food"></i>
            <span>Menu</span>
        </div>
    </a>

    <div class="mob-nav-search"
        onclick="document.getElementById('searchOverlay').classList.add('open');
        setTimeout(()=>document.getElementById('sheetInput').focus(),300)">

        <div class="mob-nav-search-btn">
            <i class="fa-solid fa-magnifying-glass"></i>
        </div>

        <span>Cari</span>
    </div>

    <a href="pesan.php?tab=pesanan" class="mob-nav-link">
        <div class="mob-nav-item">
            <i class="fa-solid fa-bag-shopping"></i>
            <span>Pesanan</span>
        </div>
    </a>

    <a href="pesan.php?tab=akun" class="mob-nav-link">
        <div class="mob-nav-item">
            <i class="fa-regular fa-user"></i>
            <span>Akun</span>
        </div>
    </a>

</div>

<!-- ── SEARCH OVERLAY ── -->
<div class="search-overlay" id="searchOverlay" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="search-sheet">
        <div class="search-sheet-handle"></div>
        <h3>Cari Makanan &amp; Minuman</h3>
        <div class="search-sheet-box">
            <input id="sheetInput" type="text" placeholder="Ketik nama menu...">
            <button><i class="fa-solid fa-magnifying-glass"></i></button>
        </div>
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
<div id="cart-toast"></div>

<!-- ============================================================
     MAIN — DETAIL PRODUK
============================================================ -->
<div class="main">
    <div class="product-card">

        <!-- ── KOLOM KIRI: Galeri ── -->
        <div style="display:flex;flex-direction:column;gap:12px;">
            <div class="gallery-wrap">

                <!-- Thumbnail sidebar -->
                <div class="thumb-sidebar">
                    <button class="t-nav" id="thumbUp">
                        <svg viewBox="0 0 24 24" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>
                    </button>

                    <?php foreach ($fotos as $i => $f): ?>
                    <div class="thumb-item <?= $i === 0 ? 'active' : '' ?>"
                         data-src="../penjual/uploads/<?= htmlspecialchars($f['nama_file']) ?>">
                        <img src="../penjual/uploads/<?= htmlspecialchars($f['nama_file']) ?>"
                             alt="Foto <?= $i+1 ?>"
                             onerror="this.src='../gambar/default-food.png'">
                    </div>
                    <?php endforeach; ?>

                    <button class="t-nav" id="thumbDown">
                        <svg viewBox="0 0 24 24" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                </div>

                <!-- Gambar utama -->
                <div class="main-img-wrap">
                    <img id="mainImg"
                         src="../penjual/uploads/<?= htmlspecialchars($fotoUtama) ?>"
                         alt="<?= htmlspecialchars($produk['nama_makanan']) ?>"
                         onerror="this.src='../gambar/default-food.png'">
                </div>
            </div>

            <!-- Aksi galeri -->
            <div class="gallery-actions">
                <button class="fav-btn" id="favBtn">
                    <svg viewBox="0 0 24 24" stroke-width="2">
                        <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
                    </svg>
                    Favorite
                </button>
                <div class="divider-v"></div>
                <span class="share-label">Share :</span>
                <div class="share-icons">
                    <!-- Instagram -->
                    <div class="share-icon" title="Instagram" onclick="shareIG()">
                        <svg width="32" height="32" viewBox="0 0 32 32">
                            <defs><radialGradient id="ig" cx="30%" cy="107%" r="150%">
                                <stop offset="0%" stop-color="#fdf497"/>
                                <stop offset="5%" stop-color="#fdf497"/>
                                <stop offset="45%" stop-color="#fd5949"/>
                                <stop offset="60%" stop-color="#d6249f"/>
                                <stop offset="90%" stop-color="#285AEB"/>
                            </radialGradient></defs>
                            <rect width="32" height="32" rx="8" fill="url(#ig)"/>
                            <rect x="8" y="8" width="16" height="16" rx="5" fill="none" stroke="#fff" stroke-width="1.8"/>
                            <circle cx="16" cy="16" r="4" fill="none" stroke="#fff" stroke-width="1.8"/>
                            <circle cx="22" cy="10" r="1.2" fill="#fff"/>
                        </svg>
                    </div>
                    <!-- TikTok -->
                    <div class="share-icon" title="TikTok" onclick="shareTikTok()">
                        <svg width="32" height="32" viewBox="0 0 32 32">
                            <rect width="32" height="32" rx="8" fill="#000"/>
                            <path d="M22 10.5c-1.1-.1-2.1-.6-2.8-1.5-.8-1-1.2-2.2-1.2-3.5H15v13.8c0 1.4-1.1 2.5-2.5 2.5s-2.5-1.1-2.5-2.5 1.1-2.5 2.5-2.5c.2 0 .5 0 .7.1V14c-.2 0-.5-.1-.7-.1-3 0-5.5 2.5-5.5 5.5S9.5 25 12.5 25s5.5-2.5 5.5-5.5V14c1.1.8 2.5 1.2 4 1.2v-3c-.7 0-1.4-.3-2-.7z" fill="#fff"/>
                        </svg>
                    </div>
                    <!-- WhatsApp -->
                    <div class="share-icon" id="waShare" title="WhatsApp">
                        <svg width="32" height="32" viewBox="0 0 32 32">
                            <rect width="32" height="32" rx="8" fill="#25D366"/>
                            <path d="M16 6C10.48 6 6 10.48 6 16c0 1.9.52 3.67 1.43 5.19L6 26l4.95-1.38C12.41 25.49 14.17 26 16 26c5.52 0 10-4.48 10-10S21.52 6 16 6zm4.96 13.5c-.2.56-1.18 1.07-1.64 1.14-.42.06-.95.09-1.53-.1-.35-.11-.81-.27-1.39-.52-2.44-1.05-4.03-3.52-4.15-3.68-.12-.16-.98-1.3-.98-2.47 0-1.18.61-1.76.83-2 .22-.24.48-.3.64-.3.16 0 .32 0 .46.01.15.01.35-.06.55.42l.71 1.75c.07.17.03.37-.04.52l-.31.42c-.1.13-.2.27-.09.53.12.26.53 1.04 1.14 1.69.79.85 1.45 1.11 1.65 1.24.26.16.41.14.56-.08l.6-.84c.15-.22.3-.18.5-.11l1.6.75c.2.1.33.14.38.22.05.09.05.5-.15 1.06z" fill="#fff"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Deskripsi -->
            <div class="desc-box">
                <div class="desc-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                </div>
                <div>
                    <strong>Deskripsi :</strong>
                    <p><?= nl2br(htmlspecialchars($produk['deskripsi'] ?? '-')) ?></p>
                </div>
            </div>
        </div>

        <!-- ── KOLOM KANAN: Info Produk ── -->
        <div class="product-info">
            <div class="product-meta">
                <span><?= htmlspecialchars($produk['nama_kantin']) ?></span>
                <span class="sep">|</span>
                <?php if ($produk['kategori'] === 'Minuman'): ?>
                    <i class="fa-solid fa-mug-hot" style="color:#555"></i>
                <?php else: ?>
                    <i class="fa-solid fa-utensils" style="color:#555"></i>
                <?php endif; ?>
                <span><?= htmlspecialchars($produk['kategori']) ?></span>
            </div>

            <div class="product-name"><?= htmlspecialchars($produk['nama_makanan']) ?></div>
            <hr>

            <!-- Rating -->
            <div class="rating-row">
                <strong style="font-size:16px"><?= number_format((float)$produk['rata_bintang'], 1) ?></strong>
                <div class="stars"><?= bintangHTML($produk['rata_bintang']) ?></div>
                <span class="rating-sep">|</span>
                <span><?= (int)$produk['total_ulasan'] ?>&nbsp;Penilaian</span>
                <span class="rating-sep">|</span>
                <span><?= (int)$produk['terjual'] ?>&nbsp;Terjual</span>
            </div>

            <!-- Harga -->
            <div class="price-box"><?= rupiah($produk['harga']) ?></div>

            <div class="info-section">

                <!-- Waktu pengerjaan -->
                <div class="info-row">
                    <span class="info-label">Pengerjaan</span>
                    <div class="time-badge">
                        <svg viewBox="0 0 24 24" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <?= htmlspecialchars($produk['waktu_proses'] ?? '2-3 menit') ?>
                    </div>
                </div>

                <!-- Varian (jika ada) -->
                <?php if (!empty($varians)): ?>
                <div class="info-row" style="align-items:flex-start">
                    <span class="info-label">Varian <span style="color:#e8650a;font-size:11px;font-weight:600">(wajib)</span></span>
                    <div class="variants">
                        <?php foreach ($varians as $i => $v): ?>
                        <div class="variant-chip"
                             data-id="<?= (int)$v['id'] ?>"
                             data-varian="<?= htmlspecialchars($v['nama']) ?>"
                             data-harga-tambah="<?= (int)$v['harga_tambah'] ?>"
                             data-foto="<?= !empty($v['foto_varian']) ? htmlspecialchars('../penjual/uploads/' . $v['foto_varian']) : '' ?>">
                            <?php if (!empty($v['foto_varian'])): ?>
                                <img src="../penjual/uploads/<?= htmlspecialchars($v['foto_varian']) ?>"
                                     alt="<?= htmlspecialchars($v['nama']) ?>"
                                     onclick="event.stopPropagation(); bukaVarianPopup(this.closest('.variant-chip'))"
                                     onerror="this.style.display='none'"
                                     title="Klik untuk lihat foto">
                            <?php endif; ?>
                            <span><?= htmlspecialchars($v['nama']) ?></span>
                            <?php if ((int)$v['harga_tambah'] > 0): ?>
                                <span class="chip-harga-tag">+Rp <?= number_format((int)$v['harga_tambah'], 0, ',', '.') ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Kuantitas -->
                <div class="info-row">
                    <span class="info-label">Kuantitas</span>
                    <div class="qty-row">
                        <div class="qty-control">
                            <button class="qty-btn" onclick="changeQty(-1)">−</button>
                            <div class="qty-val" id="qty">1</div>
                            <button class="qty-btn" onclick="changeQty(1)">+</button>
                        </div>
                        <span class="stock-label">Tersedia <?= (int)$produk['stok'] ?></span>
                    </div>
                </div>

                <!-- Tombol Aksi -->
                <div class="action-btns">
                    <?php if ($produk['stok'] > 0): ?>
                    <button class="btn-cart" id="btnKeranjang" onclick="tambahKeranjang()">
                        <svg viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4zM3 6h18M16 10a4 4 0 01-8 0"/></svg>
                        Masukkan Keranjang
                    </button>
                    <button class="btn-buy" onclick="prosesOrder()">
                    <i class="fa-solid fa-bag-shopping"></i>&nbsp; Pesan Sekarang (<span id="checkout-count">0</span>)
                </button>
                    <?php else: ?>
                    <button class="btn-cart" disabled style="opacity:.5;cursor:not-allowed">
                        <svg viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4zM3 6h18M16 10a4 4 0 01-8 0"/></svg>
                        Stok Habis
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div><!-- .product-card -->
</div><!-- .main -->

<!-- ============================================================
     INFO TOKO
============================================================ -->
<div class="store-section">
    <div class="store-card">
        <div class="store-left">
            <div class="store-avatar-wrap">
                <?php
                $fotoKantin = !empty($produk['foto_kantin'])
                    ? '../admin/uploads/' . htmlspecialchars($produk['foto_kantin'])
                    : 'https://images.unsplash.com/photo-1554118811-1e0d58224f24?q=80&w=200';
                ?>
                <img src="<?= $fotoKantin ?>"
                     alt="<?= htmlspecialchars($produk['nama_kantin']) ?>"
                     onerror="this.src='https://images.unsplash.com/photo-1554118811-1e0d58224f24?q=80&w=200'">
                <div class="store-online-dot <?= $produk['status_kantin'] !== 'online' ? 'offline' : '' ?>"></div>
            </div>
            <div class="store-info">
                <h3><?= htmlspecialchars($produk['nama_kantin']) ?></h3>
                <div class="store-online-label">
                    <span class="dot <?= $produk['status_kantin'] !== 'online' ? 'offline' : '' ?>"></span>
                    <?= htmlspecialchars($produk['status_kantin']) ?>
                </div>
                <div class="store-actions">
                    <button class="chat-store-btn"
                        onclick="chatToko(<?= $idPenjual ?>, '<?= htmlspecialchars($namaPenjual, ENT_QUOTES) ?>', '<?= htmlspecialchars($produk['nama_kantin'], ENT_QUOTES) ?>', '<?= htmlspecialchars($fotoPenjual, ENT_QUOTES) ?>')">
                        <i class="fa-regular fa-comment"></i> Chat Toko
                    </button>
                    <a href="kategori.php?id_penjual=<?= $idPenjual ?>">
                        <button class="visit-store-btn"><i class="fa-solid fa-store"></i> Kunjungi Toko</button>
                    </a>
                </div>
            </div>
        </div>
        <div class="store-stats-grid">
            <div class="store-stat-item">
                <span class="stat-label">Penilaian</span>
                <span class="stat-value orange">
                    <?= $produk['total_ulasan'] >= 1000
                        ? number_format($produk['total_ulasan']/1000, 1).'RB'
                        : (int)$produk['total_ulasan'] ?>
                </span>
            </div>
            <div class="store-stat-item">
                <span class="stat-label">Persentase Chat Dibalas</span>
                <span class="stat-value orange"><?= (int)$produk['persen_chat'] ?>%</span>
            </div>
            <div class="store-stat-item">
                <span class="stat-label">Bergabung</span>
                <span class="stat-value orange"><?= htmlspecialchars($tahunBergabung) ?></span>
            </div>
            <div class="store-stat-item">
                <span class="stat-label">Produk</span>
                <span class="stat-value orange"><?= (int)$produk['total_produk'] ?></span>
            </div>
            <div class="store-stat-item">
                <span class="stat-label">Waktu Chat Dibalas</span>
                <span class="stat-value orange"><?= htmlspecialchars($produk['waktu_balas']) ?></span>
            </div>
            <div class="store-stat-item">
                <span class="stat-label">Pengikut</span>
                <span class="stat-value orange"><?= (int)$produk['pengikut'] ?></span>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     ULASAN
============================================================ -->
<div class="review-section">
    <div class="section-title-row">
        <h2>Ulasan Pembeli</h2>
        <span><?= count($ulasanList) ?> Ulasan</span>
    </div>
    <div id="review-list"></div>
    <div class="review-pagination" id="review-pagination"></div>
</div>

<!-- ============================================================
     REKOMENDASI DARI TOKO INI
============================================================ -->
<?php if (!empty($rekToko)): ?>
<div class="recommend-section">
    <div class="section-title-row">
        <h2>Rekomendasi Dari <?= htmlspecialchars($produk['nama_kantin']) ?></h2>
        <a href="kategori.php?id_penjual=<?= $idPenjual ?>">
            <button class="visit-store-btn"><i class="fa-solid fa-store"></i> Lihat Semua ›</button>
        </a>
    </div>
    <div class="rekomendasi-outer" style="padding:0;background:transparent;">
        <div class="container">
            <?php foreach ($rekToko as $p): echo cardProduk($p); endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================
     REKOMENDASI LAINNYA
============================================================ -->
<?php if (!empty($rekLain)): ?>
<section>
    <br><hr><br>
    <div class="section-title-row">
        <h2>Rekomendasi yang mungkin anda sukai</h2>
    </div>
    <br>
    <div class="rekomendasi-outer" style="padding:0;background:transparent;">
        <div class="container">
            <?php foreach ($rekLain as $p): echo cardProduk($p); endforeach; ?>
        </div>
    </div>
    <div class="lihat-lainnya-wrap">
        <a href="menu.php" class="lihat-lainnya-btn">Lihat Lainnya <i class="fa-solid fa-arrow-right"></i></a>
    </div>
</section>
<?php endif; ?>

<!-- ── POPUP FOTO VARIAN ── -->
<div id="varianFotoPopup" onclick="if(event.target===this)closeVarianPopup()">
    <div class="vfp-inner">
        <button class="vfp-close" onclick="closeVarianPopup()">&times;</button>
        <img id="vfpImg" src="" alt="Foto Varian" onerror="this.src='../gambar/default-food.png'">
        <div class="vfp-nama" id="vfpNama"></div>
        <div class="vfp-harga" id="vfpHarga"></div>
    </div>
</div>

<!-- ── POPUP GAMBAR ── -->
<div class="image-popup" id="imagePopup">
    <button class="popup-nav prev" id="prevBtn">&#10094;</button>
    <img id="popupImg" src="" alt="">
    <button class="popup-nav next" id="nextBtn">&#10095;</button>
    <span class="popup-close">&times;</span>
</div>

<!-- ============================================================
     JAVASCRIPT
============================================================ -->
<script>
// ── Data dari PHP ──
const stokMax    = <?= (int)$produk['stok'] ?>;
const idProduk   = <?= (int)$id ?>;
const namaProduk = <?= json_encode($produk['nama_makanan'], JSON_UNESCAPED_UNICODE) ?>;
const hasVarians = <?= !empty($varians) ? 'true' : 'false' ?>;
const fotoUtama  = <?= json_encode($fotoUtama ? '../penjual/uploads/' . $fotoUtama : '../gambar/default-food.png', JSON_UNESCAPED_UNICODE) ?>;
const allReviews = <?= json_encode(array_map(function($u) {
    return [
        'name'   => $u['username'],
        'avatar' => $u['pp']
            ? '../gambar/profil/' . $u['pp']
            : 'https://ui-avatars.com/api/?name=' . urlencode($u['username']) . '&background=e8650a&color=fff&size=100',
        'stars'  => (int)$u['bintang'],
        'text'   => $u['komentar'] ?? '',
        'images' => $u['fotos'],
    ];
}, $ulasanList), JSON_UNESCAPED_UNICODE) ?>;

// ── GALERI ──
const thumbs   = document.querySelectorAll('.thumb-item');
const mainImg  = document.getElementById('mainImg');
const popup    = document.getElementById('imagePopup');
const popupImg = document.getElementById('popupImg');
let currentIndex = 0;

function showImage(index, openPopup = false) {
    if (index >= thumbs.length) index = 0;
    if (index < 0) index = thumbs.length - 1;
    currentIndex = index;
    thumbs.forEach(t => t.classList.remove('active'));
    thumbs[currentIndex].classList.add('active');
    const src = thumbs[currentIndex].dataset.src;
    mainImg.src  = src;
    popupImg.src = src;
    if (openPopup) popup.classList.add('show');
}

thumbs.forEach((item, i) => item.addEventListener('click', () => showImage(i, true)));
mainImg.addEventListener('click', () => { popupImg.src = mainImg.src; popup.classList.add('show'); });
document.getElementById('thumbUp').addEventListener('click',   () => showImage(currentIndex - 1));
document.getElementById('thumbDown').addEventListener('click', () => showImage(currentIndex + 1));
document.getElementById('prevBtn').addEventListener('click',   () => showImage(currentIndex - 1));
document.getElementById('nextBtn').addEventListener('click',   () => showImage(currentIndex + 1));
document.querySelector('.popup-close').addEventListener('click', () => popup.classList.remove('show'));
popup.addEventListener('click', e => { if (e.target === popup) popup.classList.remove('show'); });
document.addEventListener('keydown', e => {
    if (popup.classList.contains('show')) {
        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') showImage(currentIndex + 1);
        if (e.key === 'ArrowLeft'  || e.key === 'ArrowUp')   showImage(currentIndex - 1);
        if (e.key === 'Escape') popup.classList.remove('show');
    }
    if (e.key === 'Escape') document.getElementById('searchOverlay')?.classList.remove('open');
});

// ── KUANTITAS ──
let qty = 1;
function changeQty(d) {
    qty = Math.max(1, Math.min(stokMax, qty + d));
    document.getElementById('qty').textContent = qty;
}

// ── VARIAN ──
const hargaBase = <?= (int)$produk['harga'] ?>;
let hargaTambah = 0;
let varianAktif = null;

function formatRupiah(n) {
    return 'Rp ' + n.toLocaleString('id-ID');
}

function updateHargaDisplay() {
    const total = hargaBase + hargaTambah;
    const box = document.querySelector('.price-box');
    box.textContent = formatRupiah(total);
    box.classList.remove('price-updated');
    void box.offsetWidth; // reflow untuk restart animasi
    box.classList.add('price-updated');
}

// Tidak auto-pilih varian saat load — user harus klik sendiri
// (hargaTambah = 0, varianAktif = null sudah di-set di atas)

document.querySelectorAll('.variant-chip').forEach(v => {
    v.addEventListener('click', function(e) {
        e.stopPropagation();
        document.querySelectorAll('.variant-chip').forEach(x => x.classList.remove('active'));
        this.classList.add('active');
        hargaTambah = parseInt(this.dataset.hargaTambah) || 0;
        varianAktif = this.dataset.id || null;
        updateHargaDisplay();
    });
});

// Klik di luar area varian → reset ke default (tidak ada varian terpilih)
document.addEventListener('click', function(e) {
    if (!e.target.closest('.variants')) {
        const any = document.querySelector('.variant-chip.active');
        if (any) {
            document.querySelectorAll('.variant-chip').forEach(x => x.classList.remove('active'));
            hargaTambah = 0;
            varianAktif = null;
            updateHargaDisplay();
        }
    }
});

// ── POPUP FOTO VARIAN ──
function bukaVarianPopup(chip) {
    const foto  = chip.dataset.foto;
    const nama  = chip.dataset.varian;
    const harga = parseInt(chip.dataset.hargaTambah) || 0;
    if (!foto) return;
    document.getElementById('vfpImg').src   = foto;
    document.getElementById('vfpNama').textContent = nama;
    document.getElementById('vfpHarga').textContent = harga > 0
        ? '+ ' + formatRupiah(harga)
        : 'Tidak ada tambahan harga';
    document.getElementById('varianFotoPopup').classList.add('show');
}
function closeVarianPopup() {
    document.getElementById('varianFotoPopup').classList.remove('show');
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeVarianPopup();
});

// ── FAVORIT ──
let isFav = false;
document.getElementById('favBtn')?.addEventListener('click', function() {
    isFav = !isFav;
    this.style.color = isFav ? '#e8650a' : '';
    const path = this.querySelector('svg path');
    if (path) path.style.fill = isFav ? '#e8650a' : 'none';
});

// ── SHARE ──
document.getElementById('waShare')?.addEventListener('click', function() {
    const text = encodeURIComponent('Cek menu ' + namaProduk + ' di E-Canteen!\n' + window.location.href);
    window.open('https://wa.me/?text=' + text, '_blank');
});
function shareIG()     { navigator.clipboard.writeText(window.location.href).then(() => showToast('Link disalin! Tempel di Instagram.')); }
function shareTikTok() { navigator.clipboard.writeText(window.location.href).then(() => showToast('Link disalin! Tempel di TikTok.')); }

// ── TOAST ──
function showToast(msg) {
    const t = document.getElementById('cart-toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2500);
}

// ── UPDATE BADGE KERANJANG ──
function updateBadge(total) {
    ['cartBadgeDesktop','cartBadgeMob'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = total > 0 ? total : '';
        el.classList.remove('badge-bounce');
        void el.offsetWidth;
        el.classList.add('badge-bounce');
        setTimeout(() => el.classList.remove('badge-bounce'), 500);
    });
}

// ── ANIMASI TERBANG KE KERANJANG ──
function getCartTarget() {
    const id  = window.innerWidth >= 768 ? 'cartIconDesktop' : 'cartIconMob';
    const el  = document.getElementById(id);
    const rect = el.getBoundingClientRect();
    return { x: rect.left + rect.width/2, y: rect.top + rect.height/2 };
}

function flyToCart(srcEl) {
    const rect   = srcEl.getBoundingClientRect();
    const target = getCartTarget();
    const img    = document.createElement('img');
    img.className = 'fly-img';
    img.src = fotoUtama;
    img.onerror = () => { img.src = '../gambar/default-food.png'; };
    img.style.left = (rect.left + rect.width/2  - 25) + 'px';
    img.style.top  = (rect.top  + rect.height/2 - 25) + 'px';
    document.body.appendChild(img);
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            img.classList.add('animate');
            img.style.left = (target.x - 25) + 'px';
            img.style.top  = (target.y - 25) + 'px';
        });
    });
    img.addEventListener('transitionend', () => img.remove(), { once: true });
}

// ── TAMBAH KERANJANG (kirim ke tambah_keranjang.php) ──
function tambahKeranjang() {
    if (hasVarians && !varianAktif) {
        showToast('\u26a0\ufe0f Pilih varian terlebih dahulu!');
        document.querySelector('.variants')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        document.querySelectorAll('.variant-chip').forEach(c => {
            c.style.animation = 'none';
            void c.offsetWidth;
            c.style.animation = 'varianShake .4s ease';
        });
        return;
    }
    const btn = document.getElementById('btnKeranjang');
    flyToCart(btn);

    const body = 'id_menu=' + encodeURIComponent(idProduk)
               + '&jumlah=' + qty
               + (varianAktif ? '&id_varian=' + encodeURIComponent(varianAktif) : '');

    fetch('tambah_keranjang.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            updateBadge(data.total_keranjang);
            showToast('✅ ' + namaProduk + ' ditambahkan ke keranjang!');
        } else {
            showToast('❌ ' + (data.message || 'Gagal menambahkan'));
        }
    })
    .catch(() => showToast('❌ Gagal terhubung ke server'));
}

function prosesOrder() {
    if (hasVarians && !varianAktif) {
        showToast('\u26a0\ufe0f Pilih varian terlebih dahulu!');
        document.querySelector('.variants')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        document.querySelectorAll('.variant-chip').forEach(c => {
            c.style.animation = 'none';
            void c.offsetWidth;
            c.style.animation = 'varianShake .4s ease';
        });
        return;
    }
    const hargaFinal = hargaBase + hargaTambah;

    // Tambah ke keranjang dulu
    const body = 'id_menu=' + encodeURIComponent(idProduk)
               + '&jumlah=' + qty
               + (varianAktif ? '&id_varian=' + encodeURIComponent(varianAktif) : '');

    fetch('tambah_keranjang.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            // Langsung ke CO dengan id_keranjang hasil insert
            // co.php akan baca harga dari JOIN varian_menu otomatis
            window.location.href = 'co.php?from=keranjang&ids=' + data.id_keranjang;
        } else {
            showToast('❌ ' + (data.message || 'Gagal'));
        }
    })
    .catch(() => showToast('❌ Gagal terhubung ke server'));
}
// ── ULASAN PAGINATION ──
const PER_PAGE   = 5;
const totalPages = Math.ceil(allReviews.length / PER_PAGE);
let currentPage  = 1;

function starHTML(n) {
    let h = '';
    for (let i = 1; i <= 5; i++)
        h += '<span style="color:' + (i <= n ? '#ffb400' : '#ddd') + '">★</span>';
    return h;
}

function renderReviews(page) {
    const list  = document.getElementById('review-list');
    const slice = allReviews.slice((page-1)*PER_PAGE, page*PER_PAGE);
    if (!slice.length) {
        list.innerHTML = '<p style="color:#999;padding:20px 0">Belum ada ulasan.</p>';
        return;
    }
    list.innerHTML = slice.map(r => `
        <div class="review-card">
            <div class="review-top">
                <img src="${r.avatar}" alt="${r.name}"
                     onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(r.name)}&background=e8650a&color=fff&size=100'">
                <div>
                    <h4>${r.name}</h4>
                    <div class="review-stars" style="letter-spacing:2px">${starHTML(r.stars)}</div>
                </div>
            </div>
            <p class="review-text">${r.text}</p>
            ${r.images.length ? '<div class="review-images">'
                + r.images.map(img => `<img src="${img}" onerror="this.style.display='none'">`).join('')
                + '</div>' : ''}
        </div>
    `).join('');
}

function renderPagination(page) {
    const pag = document.getElementById('review-pagination');
    let html = `<button class="pag-btn arrow" onclick="goPage(${page-1})"
        ${page===1?'disabled style="opacity:.35;cursor:default"':''}>&#8249;</button>`;
    for (let i = 1; i <= totalPages; i++)
        html += `<button class="pag-btn${i===page?' active':''}" onclick="goPage(${i})">${i}</button>`;
    html += `<button class="pag-btn arrow" onclick="goPage(${page+1})"
        ${page===totalPages?'disabled style="opacity:.35;cursor:default"':''}>&#8250;</button>`;
    pag.innerHTML = html;
}

function goPage(p) {
    if (p < 1 || p > totalPages) return;
    currentPage = p;
    renderReviews(p);
    renderPagination(p);
    document.querySelector('.review-section').scrollIntoView({ behavior:'smooth', block:'start' });
}

renderReviews(1);
if (totalPages > 1) renderPagination(1);

// ── SEARCH ──
const navInput   = document.getElementById('navSearchInput');
const navBtn     = document.getElementById('navSearchBtn');
const sheetInput = document.getElementById('sheetInput');

function goSearch(q) {
    q = q.trim();
    if (!q) return;
    window.location.href = 'search.php?q=' + encodeURIComponent(q);
}
navBtn.addEventListener('click', () => goSearch(navInput.value));
navInput.addEventListener('keydown',   e => { if (e.key === 'Enter') goSearch(navInput.value); });
sheetInput.addEventListener('keydown', e => { if (e.key === 'Enter') goSearch(sheetInput.value); });
function searchFromChip(el) { goSearch(el.textContent.trim()); }
// Simpan halaman sekarang
localStorage.setItem("last_page", window.location.href);

// ── Chat Toko: buka popup lalu langsung masuk room penjual ──
function chatToko(idPenjual, namaPenjual, namaKantin, fotoPenjual) {
    if (!idPenjual) { alert('Penjual tidak ditemukan.'); return; }
    openChatPopup();
    // Tunggu popup & widget siap, lalu buka/buat room
    const tryOpen = (attempt) => {
        if (attempt > 20) return; // max 2 detik
        if (typeof _cpOpenRoomByPenjual === 'function') {
            _cpOpenRoomByPenjual(idPenjual, namaPenjual, namaKantin, fotoPenjual);
        } else {
            setTimeout(() => tryOpen(attempt + 1), 100);
        }
    };
    tryOpen(0);
}
</script>
<?php include 'chat_popup.php'; ?>

</body>
</html>