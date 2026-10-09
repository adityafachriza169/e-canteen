<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

$id_user = $_SESSION['id_user'];

$query = mysqli_query($konek, "
SELECT 
    id_user,
    username,
    nama,
    email,
    pp,
    no_hp,
    gender,
    tanggal_lahir
FROM users
WHERE id_user = '$id_user'
");

$q_keranjang   = mysqli_query($konek, "SELECT SUM(jumlah) AS total FROM keranjang WHERE id_user = $id_user");
$r_keranjang   = mysqli_fetch_assoc($q_keranjang);
$total_keranjang = (int) ($r_keranjang['total'] ?? 0);

$user = mysqli_fetch_assoc($query);

$username_user = $user['username'] ?? '';
$pp_user       = $user['pp']       ?? '';
$nama          = $user['nama']     ?? '';
$email         = $user['email']    ?? '';
$no_hp         = $user['no_hp']    ?? '';
$gender        = $user['gender']   ?? '';
$tgl_lahir     = $user['tanggal_lahir'] ?? '';

$q      = $_GET['q'] ?? '';
$q_like = '%' . $q . '%';

// =====================================================
// QUERY: filter status penjual aktif
// Sesuaikan kolom status sesuai tabel users kamu
// Contoh: u.status = 'aktif' ATAU u.is_active = 1
// =====================================================
$stmt_menu = mysqli_prepare($konek, "
    SELECT m.*, k.nama_kantin, p.id_penjual, p.nama AS nama_penjual,
           COALESCE((SELECT ROUND(AVG(bintang),1) FROM ulasan WHERE id_menu = m.id), 0) AS rating,
           COALESCE((SELECT SUM(jumlah) FROM transaksi WHERE id_menu = m.id AND status IN ('selesai','lunas','diambil','completed')), 0) AS terjual
    FROM menu_req m
    JOIN kantin k ON m.id_kantin = k.id_kantin
    JOIN penjual p ON m.id_kantin = p.id_kantin
    WHERE m.nama_makanan LIKE ?
      AND m.aktif = 1
      AND p.buka = 1
      AND p.status = 1
    ORDER BY terjual DESC
");
mysqli_stmt_bind_param($stmt_menu, 's', $q_like);
mysqli_stmt_execute($stmt_menu);
$query_menu = mysqli_stmt_get_result($stmt_menu);

$total_produk = mysqli_num_rows($query_menu);

// ── Daftar penjual aktif untuk filter Brand/Kantin ──
$res_penjual = mysqli_query($konek, "
    SELECT p.id_penjual, p.nama, k.nama_kantin
    FROM penjual p
    JOIN kantin k ON p.id_kantin = k.id_kantin
    WHERE p.status = 1 AND p.buka = 1
    ORDER BY p.id_penjual ASC
");
$daftar_penjual = [];
while ($row = mysqli_fetch_assoc($res_penjual)) {
    $daftar_penjual[] = $row;
}

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
setcookie("last_page", $_SERVER['REQUEST_URI'], time() + (30 * 24 * 60 * 60), "/", "", $secure, false);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Canteen ESEMKITA – Menu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Righteous&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="search.css">
</head>

<body>

    <!-- ===== TOPBAR ===== -->
    <div class="topbar">
        <div class="top-left">
            <a href="user.php">Home</a><span class="sep">|</span>
            <a href="menu.php">Menu</a><span class="sep">|</span>
            <a href="pesan.php?tab=pesanan">Pesanan</a>
        </div>
        <div class="top-right">
            <a href=""><i class="fa-regular fa-bell"></i> Notifikasi</a>
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

    <!-- ===== NAVBAR ===== -->
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
            <button id="navSearchBtn">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
        </div>
        <div class="icon-actions">
            <a href="keranjang.php" style="text-decoration:none;color:inherit">
                <div class="icon-btn" style="position:relative">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span id="cart-badge" style="
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
            <button onclick="openChatPopup()" class="icon-btn notif-chat-btn" style="background:none;border:none;cursor:pointer;">
                <i class="fa-solid fa-comment-dots"></i>
                <span class="chat-badge" id="chatBadge" style="display:none"></span>
            </button>
        </div>
    </div>

    <!-- ===== MOBILE NAV ===== -->
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
                    <span class="chat-badge" id="chatBadgeMob" style="display:none"></span>
                </button>
                <!-- TOMBOL FILTER MOBILE (pojok kanan atas) -->
                <button class="mob-filter-btn" id="mobFilterBtn" onclick="openFilterDrawer()">
                    <i class="fa-solid fa-sliders"></i>
                    <span class="mob-filter-badge" id="mobFilterBadge" style="display:none">0</span>
                </button>
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
            </div>
        </div>
    </div>

    <!-- ===== BOTTOM NAV MOBILE ===== -->
    <div class="mob-bottom-nav">
        <a href="user.php" class="mob-nav-item">
            <i class="fa-solid fa-house"></i>
            <span>Home</span>
        </a>
        <a href="menu.php" class="mob-nav-item">
            <i class="fa-solid fa-bowl-food"></i>
            <span>Menu</span>
        </a>
        <a href="#" class="mob-nav-search active" onclick="event.preventDefault(); document.getElementById('searchOverlay').classList.add('open'); setTimeout(function(){ document.getElementById('sheetInput').focus(); }, 300);">
            <div class="mob-nav-search-btn">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>
            <span>Cari</span>
        </a>
        <a href="pesan.php?tab=pesanan" class="mob-nav-item" style="position:relative">
            <i class="fa-solid fa-bag-shopping"></i>
            <span>Pesanan</span>
            <?php if ($total_keranjang > 0): ?>
            <div class="mob-nav-badge"><?php echo $total_keranjang; ?></div>
            <?php endif; ?>
        </a>
        <a href="pesan.php?tab=akun" class="mob-nav-item">
            <i class="fa-regular fa-user"></i>
            <span>Akun</span>
        </a>
    </div>

    <!-- ===== SEARCH OVERLAY MOBILE ===== -->
    <div class="search-overlay" id="searchOverlay" onclick="if(event.target===this)this.classList.remove('open')">
        <div class="search-sheet">
            <div class="search-sheet-handle"></div>
            <h3>Cari Makanan & Minuman</h3>
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

    <!-- ===== FILTER DRAWER MOBILE ===== -->
    <div class="sidebar-overlay" id="filterOverlay" onclick="closeFilterDrawer()"></div>
    <div class="sidebar-drawer" id="filterDrawer">
        <div class="drawer-handle"></div>
        <div class="drawer-title">
            <i class="fa-solid fa-sliders"></i> Filter Pencarian
            <button onclick="closeFilterDrawer()" style="margin-left:auto;background:none;border:none;cursor:pointer;color:#aaa;font-size:18px;line-height:1;padding:0 4px;">&#x2715;</button>
        </div>
        <div class="drawer-body">

            <!-- BRAND / KANTIN -->
            <div class="filter-section">
                <div class="filter-label"><i class="fa-solid fa-store"></i> Brand / Kantin</div>
                <?php foreach (array_slice($daftar_penjual, 0, 4) as $pj): ?>
                <div class="filter-option">
                    <input type="checkbox" id="db<?php echo $pj['id_penjual']; ?>" value="penjual-<?php echo $pj['id_penjual']; ?>" onchange="syncAndFilter('b<?php echo $pj['id_penjual']; ?>', this)">
                    <label for="db<?php echo $pj['id_penjual']; ?>"><?php echo htmlspecialchars($pj['nama']); ?></label>
                </div>
                <?php endforeach; ?>
                <?php if (count($daftar_penjual) > 4): ?>
                <div class="extra-options" id="dextra-brand">
                    <?php foreach (array_slice($daftar_penjual, 4) as $pj): ?>
                    <div class="filter-option">
                        <input type="checkbox" id="db<?php echo $pj['id_penjual']; ?>" value="penjual-<?php echo $pj['id_penjual']; ?>" onchange="syncAndFilter('b<?php echo $pj['id_penjual']; ?>', this)">
                        <label for="db<?php echo $pj['id_penjual']; ?>"><?php echo htmlspecialchars($pj['nama']); ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button class="more-btn" onclick="toggleExtra('dextra-brand', this)">
                    <i class="fa-solid fa-chevron-down"></i> <span>Lainnya</span>
                </button>
                <?php endif; ?>
            </div>

            <!-- METODE PEMBAYARAN -->
            <div class="filter-section">
                <div class="filter-label"><i class="fa-solid fa-wallet"></i> Metode Pembayaran</div>
                <div class="filter-option">
                    <input type="checkbox" id="dp1" onchange="syncAndFilter('p1', this)"><label for="dp1">COD (Bayar di Tempat)</label>
                </div>
                <div class="filter-option">
                    <input type="checkbox" id="dp2" onchange="syncAndFilter('p2', this)"><label for="dp2">E-wallet (Uang Digital)</label>
                </div>
            </div>

            <!-- KATEGORI -->
            <div class="filter-section">
                <div class="filter-label"><i class="fa-solid fa-layer-group"></i> Berdasarkan Kategori</div>
                <div class="filter-option">
                    <input type="checkbox" id="dk1" value="makanan" onchange="syncAndFilter('k1', this)"><label for="dk1">Makanan</label>
                </div>
                <div class="filter-option">
                    <input type="checkbox" id="dk2" value="minuman" onchange="syncAndFilter('k2', this)"><label for="dk2">Minuman</label>
                </div>
                <div class="filter-option">
                    <input type="checkbox" id="dk3" value="snack" onchange="syncAndFilter('k3', this)"><label for="dk3">Snack</label>
                </div>
            </div>

            <!-- BATAS HARGA -->
            <div class="filter-section">
                <div class="filter-label"><i class="fa-solid fa-tag"></i> Batas Harga</div>
                <div class="harga-row">
                    <input type="text" id="dhargaMin" placeholder="Rp MIN" oninput="syncHarga('hargaMin', this)">
                    <span>—</span>
                    <input type="text" id="dhargaMax" placeholder="Rp MAKS" oninput="syncHarga('hargaMax', this)">
                </div>
                <button class="btn-pakai" onclick="filterProducts()">PAKAI</button>
            </div>

            <!-- PENILAIAN / RATING -->
            <div class="filter-section">
                <div class="filter-label"><i class="fa-solid fa-star"></i> Penilaian</div>
                <div class="star-option">
                    <input type="radio" name="drating" id="dr5" value="5" onchange="syncRadio('r5')">
                    <label for="dr5"><span class="stars-display"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></span>&nbsp;ke atas</label>
                </div>
                <div class="star-option">
                    <input type="radio" name="drating" id="dr4" value="4" onchange="syncRadio('r4')">
                    <label for="dr4"><span class="stars-display"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-regular fa-star empty"></i></span>&nbsp;ke atas</label>
                </div>
                <div class="star-option">
                    <input type="radio" name="drating" id="dr3" value="3" onchange="syncRadio('r3')">
                    <label for="dr3"><span class="stars-display"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-regular fa-star empty"></i><i class="fa-regular fa-star empty"></i></span>&nbsp;ke atas</label>
                </div>
                <div class="extra-options" id="dextra-rating">
                    <div class="star-option">
                        <input type="radio" name="drating" id="dr2" value="2" onchange="syncRadio('r2')">
                        <label for="dr2"><span class="stars-display"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-regular fa-star empty"></i><i class="fa-regular fa-star empty"></i><i class="fa-regular fa-star empty"></i></span>&nbsp;ke atas</label>
                    </div>
                    <div class="star-option">
                        <input type="radio" name="drating" id="dr1" value="1" onchange="syncRadio('r1')">
                        <label for="dr1"><span class="stars-display"><i class="fa-solid fa-star"></i><i class="fa-regular fa-star empty"></i><i class="fa-regular fa-star empty"></i><i class="fa-regular fa-star empty"></i><i class="fa-regular fa-star empty"></i></span>&nbsp;ke atas</label>
                    </div>
                </div>
                <button class="more-btn" onclick="toggleExtra('dextra-rating', this)" style="margin-top:4px">
                    <i class="fa-solid fa-chevron-down"></i> <span>Lainnya</span>
                </button>
            </div>

            <!-- RESET -->
            <div class="filter-section">
                <button class="btn-pakai" style="background:#888;margin-top:0" onclick="resetFilter()">
                    <i class="fa-solid fa-rotate-left"></i> Reset Filter
                </button>
            </div>

        </div><!-- end .drawer-body -->
    </div><!-- end .sidebar-drawer -->

    <div class="mob-bottom-spacer"></div>

    <!-- ===== PAGE WRAPPER ===== -->
    <div class="page-wrapper">

        <!-- ===== SIDEBAR FILTER ===== -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <i class="fa-solid fa-sliders"></i> Filter Pencarian
            </div>

            <!-- BRAND / KANTIN dari DB -->
            <div class="filter-section">
                <div class="filter-label">
                    <i class="fa-solid fa-store"></i> Brand / Kantin
                </div>

                <?php foreach (array_slice($daftar_penjual, 0, 4) as $i => $pj): ?>
                <div class="filter-option">
                    <input type="checkbox" id="b<?php echo $pj['id_penjual']; ?>" value="penjual-<?php echo $pj['id_penjual']; ?>">
                    <label for="b<?php echo $pj['id_penjual']; ?>"><?php echo htmlspecialchars($pj['nama']); ?></label>
                </div>
                <?php endforeach; ?>

                <?php if (count($daftar_penjual) > 4): ?>
                <!-- Extra brand (hidden) -->
                <div class="extra-options" id="extra-brand">
                    <?php foreach (array_slice($daftar_penjual, 4) as $pj): ?>
                    <div class="filter-option">
                        <input type="checkbox" id="b<?php echo $pj['id_penjual']; ?>" value="penjual-<?php echo $pj['id_penjual']; ?>">
                        <label for="b<?php echo $pj['id_penjual']; ?>"><?php echo htmlspecialchars($pj['nama']); ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button class="more-btn" onclick="toggleExtra('extra-brand', this)">
                    <i class="fa-solid fa-chevron-down"></i> <span>Lainnya</span>
                </button>
                <?php endif; ?>

            <!-- METODE PEMBAYARAN -->
            <div class="filter-section">
                <div class="filter-label">
                    <i class="fa-solid fa-wallet"></i> Metode Pembayaran
                </div>
                <div class="filter-option">
                    <input type="checkbox" id="p1"><label for="p1">COD (Bayar di Tempat)</label>
                </div>
                <div class="filter-option">
                    <input type="checkbox" id="p2"><label for="p2">E-wallet (Uang Digital)</label>
                </div>
            </div>

            <!-- KATEGORI -->
            <div class="filter-section">
                <div class="filter-label">
                    <i class="fa-solid fa-layer-group"></i> Berdasarkan Kategori
                </div>
                <div class="filter-option">
                    <input type="checkbox" id="k1" value="makanan"><label for="k1">Makanan</label>
                </div>
                <div class="filter-option">
                    <input type="checkbox" id="k2" value="minuman"><label for="k2">Minuman</label>
                </div>
                <div class="filter-option">
                    <input type="checkbox" id="k3" value="snack"><label for="k3">Snack</label>
                </div>
            </div>

            <!-- BATAS HARGA -->
            <div class="filter-section">
                <div class="filter-label">
                    <i class="fa-solid fa-tag"></i> Batas Harga
                </div>
                <div class="harga-row">
                    <input type="text" id="hargaMin" placeholder="Rp MIN">
                    <span>—</span>
                    <input type="text" id="hargaMax" placeholder="Rp MAKS">
                </div>
                <button class="btn-pakai" onclick="filterProducts()">PAKAI</button>
            </div>

            <!-- PENILAIAN / RATING -->
            <div class="filter-section">
                <div class="filter-label">
                    <i class="fa-solid fa-star"></i> Penilaian
                </div>
                <div class="star-option">
                    <input type="radio" name="rating" id="r5" value="5">
                    <label for="r5">
                        <span class="stars-display">
                            <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i>
                        </span>
                        &nbsp;ke atas
                    </label>
                </div>
                <div class="star-option">
                    <input type="radio" name="rating" id="r4" value="4">
                    <label for="r4">
                        <span class="stars-display">
                            <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                            <i class="fa-regular fa-star empty"></i>
                        </span>
                        &nbsp;ke atas
                    </label>
                </div>
                <div class="star-option">
                    <input type="radio" name="rating" id="r3" value="3">
                    <label for="r3">
                        <span class="stars-display">
                            <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i><i class="fa-regular fa-star empty"></i>
                            <i class="fa-regular fa-star empty"></i>
                        </span>
                        &nbsp;ke atas
                    </label>
                </div>
                <div class="extra-options" id="extra-rating">
                    <div class="star-option">
                        <input type="radio" name="rating" id="r2" value="2">
                        <label for="r2">
                            <span class="stars-display">
                                <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                                <i class="fa-regular fa-star empty"></i><i class="fa-regular fa-star empty"></i>
                                <i class="fa-regular fa-star empty"></i>
                            </span>
                            &nbsp;ke atas
                        </label>
                    </div>
                    <div class="star-option">
                        <input type="radio" name="rating" id="r1" value="1">
                        <label for="r1">
                            <span class="stars-display">
                                <i class="fa-solid fa-star"></i><i class="fa-regular fa-star empty"></i>
                                <i class="fa-regular fa-star empty"></i><i class="fa-regular fa-star empty"></i>
                                <i class="fa-regular fa-star empty"></i>
                            </span>
                            &nbsp;ke atas
                        </label>
                    </div>
                </div>
                <button class="more-btn" onclick="toggleExtra('extra-rating', this)" style="margin-top:4px">
                    <i class="fa-solid fa-chevron-down"></i> <span>Lainnya</span>
                </button>
            </div>

            <!-- TOMBOL RESET FILTER -->
            <div class="filter-section">
                <button class="btn-pakai" style="background:#888;margin-top:0" onclick="resetFilter()">
                    <i class="fa-solid fa-rotate-left"></i> Reset Filter
                </button>
            </div>

        </aside>

        <!-- ===== MAIN CONTENT ===== -->
        <main class="main-content">

            <!-- HASIL PENCARIAN INFO -->
            <div class="hasil-bar">
                <i class="fa-solid fa-circle-info"></i>
                Hasil pencarian untuk &nbsp;
                <strong>'<?php echo htmlspecialchars($q ?: "semua menu"); ?>'</strong>
                <span class="hasil-count" id="hasilCount"><?php echo $total_produk; ?> produk ditemukan</span>
            </div>

            <!-- SORT BAR -->
            <div class="sort-bar">
                <span class="sort-label">Urutkan:</span>
                <button class="sort-btn active">Terkait</button>
                <button class="sort-btn">Terbaru</button>
                <button class="sort-btn">Terlaris</button>
                <select class="sort-select" id="sortSelect" onchange="sortProducts(this.value)">
                    <option value="">Harga</option>
                    <option value="asc">Harga: Rendah ke Tinggi</option>
                    <option value="desc">Harga: Tinggi ke Rendah</option>
                </select>
            </div>

            <!-- MENU CARDS GRID -->
            <div class="container" id="cardContainer">

                <?php while ($menu = mysqli_fetch_assoc($query_menu)): ?>

                <a href="klik.php?id=<?php echo $menu['id']; ?>" class="card-link">
                    <div class="card"
                         data-kantin="penjual-<?php echo (int)$menu['id_penjual']; ?>"
                         data-nama-kantin="<?php echo htmlspecialchars(strtolower($menu['nama_kantin'])); ?>"
                         data-nama-penjual="<?php echo htmlspecialchars(strtolower($menu['nama_penjual'])); ?>"
                         data-kategori="<?php echo htmlspecialchars(strtolower($menu['kategori'])); ?>"
                         data-harga="<?php echo (int)$menu['harga']; ?>"
                         data-rating="<?php echo (float)$menu['rating']; ?>"
                         data-nama="<?php echo htmlspecialchars(strtolower($menu['nama_makanan'])); ?>">

                        <div class="gambar">
                            <img src="../penjual/uploads/<?php echo htmlspecialchars($menu['foto']); ?>"
                                 alt="<?php echo htmlspecialchars($menu['nama_makanan']); ?>"
                                 onerror="this.src='../gambar/no-image.png'">
                        </div>

                        <div class="isi">

                            <div class="kategori">
                                <span class="kantin">
                                    <?php echo htmlspecialchars($menu['nama_kantin']); ?>
                                </span>
                                <div class="sekat"></div>
                                <i class="fa-solid fa-utensils"></i>
                                <span class="makanan">
                                    <?php echo htmlspecialchars($menu['kategori']); ?>
                                </span>
                            </div>

                            <div class="nama">
                                <?php echo htmlspecialchars($menu['nama_makanan']); ?>
                            </div>

                            <div class="harga">
                                Rp<?php echo number_format($menu['harga'], 0, ',', '.'); ?>
                            </div>

                            <!-- RATING BINTANG -->
                            <div class="rating-row">
                                <?php
                                $rating     = (float)$menu['rating'];
                                $full_stars = floor($rating);
                                $half_star  = ($rating - $full_stars) >= 0.5;
                                if ($rating <= 0):
                                ?>
                                    <!-- Belum ada rating: 1 bintang kuning + tanda strip -->
                                    <i class="fa-solid fa-star" style="color:#f5a623;font-size:12px"></i>
                                    <span class="rating-val">-</span>
                                <?php else: ?>
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <?php if ($s <= $full_stars): ?>
                                            <i class="fa-solid fa-star" style="color:#f5a623;font-size:12px"></i>
                                        <?php elseif ($s == $full_stars + 1 && $half_star): ?>
                                            <i class="fa-solid fa-star-half-stroke" style="color:#f5a623;font-size:12px"></i>
                                        <?php else: ?>
                                            <i class="fa-regular fa-star" style="color:#ccc;font-size:12px"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    <span class="rating-val"><?php echo number_format($rating, 1); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="info">
                                <div class="box">
                                    <div class="atas">
                                        <i class="fa-solid fa-box"></i>
                                        <span><?php echo $menu['stok']; ?></span>
                                    </div>
                                    <div class="bawah">Stock</div>
                                </div>
                                <div class="box">
                                    <div class="atas">
                                        <i class="fa-solid fa-fire"></i>
                                        <span><?php echo $menu['terjual']; ?></span>
                                    </div>
                                    <div class="bawah">Terjual</div>
                                </div>
                            </div>

                        </div>
                    </div>
                </a>

                <?php endwhile; ?>

            </div><!-- end .container -->

            <br><hr><br>

            <!-- BANNER: ADA HASIL -->
            <?php if ($total_produk > 0): ?>
            <div id="banner-ada">
                <div class="banner-icon">
                    <i class="fa-solid fa-circle-info"></i>
                </div>
                <div class="banner-text">
                    <p class="not-found-title">Kami menampilkan hasil berdasarkan pencarian Anda</p>
                    <p class="not-found-sub">Ingin melihat menu lainnya? Tampilkan semua produk yang tersedia.</p>
                </div>
                <button class="btn-semua-menu" onclick="window.location.href='menu.php'">
                    <i class="fa-solid fa-border-all"></i> Tampilkan Semua Produk
                </button>
            </div>
            <?php endif; ?>

            <!-- BANNER: TIDAK ADA HASIL (dari PHP) -->
            <?php if ($total_produk == 0): ?>
            <div id="banner-kosong">
                <div class="banner-icon not-found">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <div class="banner-text">
                    <p class="not-found-title">Produk yang Anda cari tidak ada</p>
                    <p class="not-found-sub">Coba periksa ketikan atau cari menu lain</p>
                </div>
                <button class="btn-semua-menu" onclick="window.location.href='menu.php'">
                    <i class="fa-solid fa-border-all"></i> Tampilkan Semua Produk
                </button>
            </div>
            <?php endif; ?>

            <!-- BANNER: TIDAK ADA HASIL (dari JS filter) -->
            <div id="banner-filter-kosong" style="display:none">
                <div class="banner-icon not-found">
                    <i class="fa-solid fa-filter-circle-xmark"></i>
                </div>
                <div class="banner-text">
                    <p class="not-found-title">Tidak ada produk yang cocok dengan filter</p>
                    <p class="not-found-sub">Coba ubah atau hapus beberapa filter pencarian</p>
                </div>
                <button class="btn-semua-menu" style="background:#888" onclick="resetFilter()">
                    <i class="fa-solid fa-rotate-left"></i> Reset Filter
                </button>
            </div>

        </main>
    </div><!-- end .page-wrapper -->

    <script>
    // =========================================
    // AMBIL QUERY PENCARIAN DARI URL
    // =========================================
    const params    = new URLSearchParams(window.location.search);
    const searchQuery = (params.get('q') || '').toLowerCase().trim();

    // Isi input navbar dengan nilai pencarian
    const navInput = document.getElementById('navSearchInput');
    if (navInput) navInput.value = searchQuery;

    // =========================================
    // TOGGLE EXTRA (sidebar show more/less)
    // =========================================
    function toggleExtra(id, btn) {
        const el     = document.getElementById(id);
        const isOpen = el.classList.contains('show');
        el.classList.toggle('show');
        btn.classList.toggle('open');
        btn.querySelector('span').textContent = isOpen ? 'Lainnya' : 'Sembunyikan';
    }

    // =========================================
    // SORT BUTTON ACTIVE
    // =========================================
    document.querySelectorAll('.sort-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.sort-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        });
    });

    // =========================================
    // SEARCH REDIRECT
    // =========================================
    const navBtn     = document.getElementById('navSearchBtn');
    const sheetInput = document.getElementById('sheetInput');

    function goSearch(q) {
        const val = q.trim();
        if (!val) return;
        window.location.href = `search.php?q=${encodeURIComponent(val)}`;
    }

    navBtn?.addEventListener('click', () => goSearch(navInput.value));
    navInput?.addEventListener('keydown', e => { if (e.key === 'Enter') goSearch(navInput.value); });
    sheetInput?.addEventListener('keydown', e => { if (e.key === 'Enter') goSearch(sheetInput.value); });

    function searchFromChip(el) {
        goSearch(el.textContent.trim());
    }

    // =========================================
    // MAIN FILTER FUNCTION
    // =========================================
    function filterProducts() {

        const cards = document.querySelectorAll('#cardContainer .card');

        // --- BRAND / PENJUAL (id dinamis dari DB) ---
        const selectedKantin = [];
        document.querySelectorAll('.sidebar input[type="checkbox"][id^="b"]').forEach(cb => {
            if (cb.checked) selectedKantin.push(cb.value); // value = "penjual-X"
        });

        // --- KATEGORI ---
        const selectedKategori = [];
        if (document.getElementById('k1')?.checked) selectedKategori.push('makanan');
        if (document.getElementById('k2')?.checked) selectedKategori.push('minuman');
        if (document.getElementById('k3')?.checked) selectedKategori.push('snack');

        // --- RATING ---
        let minRating = 0;
        for (let r = 5; r >= 1; r--) {
            const radio = document.getElementById('r' + r);
            if (radio && radio.checked) {
                minRating = r;
                break;
            }
        }

        // --- HARGA ---
        const rawMin = document.getElementById('hargaMin')?.value.replace(/\D/g, '') || '';
        const rawMax = document.getElementById('hargaMax')?.value.replace(/\D/g, '') || '';
        const minHarga = rawMin !== '' ? parseInt(rawMin) : 0;
        const maxHarga = rawMax !== '' ? parseInt(rawMax) : 999999999;

        // --- LOOP SETIAP CARD ---
        let total = 0;

        cards.forEach(card => {
            const wrapper = card.closest('a') || card;

            const kantin   = (card.dataset.kantin   || '').toLowerCase();
            const kategori = (card.dataset.kategori || '').toLowerCase();
            const harga    = parseInt(card.dataset.harga  || '0');
            const rating   = parseFloat(card.dataset.rating || '0');
            const nama     = (card.dataset.nama     || '').toLowerCase();

            // Cek semua kondisi filter
            const cocokSearch   = searchQuery === '' || nama.includes(searchQuery);
            const cocokKantin   = selectedKantin.length   === 0 || selectedKantin.includes(kantin);
            const cocokKategori = selectedKategori.length === 0 || selectedKategori.includes(kategori);
            const cocokHarga    = harga >= minHarga && harga <= maxHarga;
            const cocokRating   = rating >= minRating;

            const tampil = cocokSearch && cocokKantin && cocokKategori && cocokHarga && cocokRating;

            wrapper.style.display = tampil ? 'block' : 'none';
            if (tampil) total++;
        });

        // --- UPDATE COUNTER ---
        const hasilCount = document.getElementById('hasilCount');
        if (hasilCount) hasilCount.textContent = `${total} produk ditemukan`;

        // --- BANNER LOGIC ---
        const bannerAda          = document.getElementById('banner-ada');
        const bannerKosong       = document.getElementById('banner-kosong');
        const bannerFilterKosong = document.getElementById('banner-filter-kosong');

        const adaFilter = selectedKantin.length > 0 || selectedKategori.length > 0
                       || minRating > 0 || rawMin !== '' || rawMax !== '';

        if (total > 0) {
            if (bannerAda)          bannerAda.style.display          = 'flex';
            if (bannerKosong)       bannerKosong.style.display       = 'none';
            if (bannerFilterKosong) bannerFilterKosong.style.display = 'none';
        } else {
            if (bannerAda) bannerAda.style.display = 'none';
            if (adaFilter) {
                // Hasil kosong karena filter
                if (bannerFilterKosong) bannerFilterKosong.style.display = 'flex';
                if (bannerKosong)       bannerKosong.style.display       = 'none';
            } else {
                // Memang tidak ada dari server
                if (bannerFilterKosong) bannerFilterKosong.style.display = 'none';
                if (bannerKosong)       bannerKosong.style.display       = 'flex';
            }
        }
    }

    // =========================================
    // SORT PRODUK BERDASARKAN HARGA
    // =========================================
    function sortProducts(arah) {
        const container = document.getElementById('cardContainer');
        const links     = Array.from(container.querySelectorAll('.card-link'));

        links.sort((a, b) => {
            const hA = parseInt(a.querySelector('.card').dataset.harga || '0');
            const hB = parseInt(b.querySelector('.card').dataset.harga || '0');
            return arah === 'asc' ? hA - hB : hB - hA;
        });

        links.forEach(link => container.appendChild(link));
    }

    // =========================================
    // RESET FILTER
    // =========================================
    function resetFilter() {
        // Reset semua checkbox brand (id dinamis dari DB)
        document.querySelectorAll('.sidebar input[type="checkbox"][id^="b"]').forEach(cb => {
            cb.checked = false;
        });
        // Reset checkbox kategori
        ['k1','k2','k3'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.checked = false;
        });
        // Reset radio rating
        for (let r = 1; r <= 5; r++) {
            const el = document.getElementById('r' + r);
            if (el) el.checked = false;
        }
        // Reset harga
        const hargaMin = document.getElementById('hargaMin');
        const hargaMax = document.getElementById('hargaMax');
        if (hargaMin) hargaMin.value = '';
        if (hargaMax) hargaMax.value = '';

        filterProducts();
    }

    // =========================================
    // FORMAT INPUT HARGA OTOMATIS (Rp)
    // =========================================
    ['hargaMin','hargaMax'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', function () {
            let val = this.value.replace(/\D/g, '');
            this.value = val ? 'Rp' + parseInt(val).toLocaleString('id-ID') : '';
        });
    });
    // Drawer harga inputs format
    ['dhargaMin','dhargaMax'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', function () {
            let val = this.value.replace(/\D/g, '');
            this.value = val ? 'Rp' + parseInt(val).toLocaleString('id-ID') : '';
        });
    });

    // =========================================
    // BIND EVENT LISTENER FILTER
    // =========================================
    document.querySelectorAll(
        '.sidebar input[type="checkbox"], .sidebar input[type="radio"]'
    ).forEach(input => {
        input.addEventListener('change', filterProducts);
    });

    // =========================================
    // JALANKAN FILTER SAAT LOAD
    // =========================================
    filterProducts();

    // =========================================
    // MOBILE FILTER DRAWER
    // =========================================
    function openFilterDrawer() {
        document.getElementById('filterOverlay').classList.add('open');
        document.getElementById('filterDrawer').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeFilterDrawer() {
        document.getElementById('filterOverlay').classList.remove('open');
        document.getElementById('filterDrawer').classList.remove('open');
        document.body.style.overflow = '';
    }

    // Sync checkbox drawer → sidebar (hidden) lalu filter
    function syncAndFilter(sidebarId, drawerEl) {
        const sidebarEl = document.getElementById(sidebarId);
        if (sidebarEl) sidebarEl.checked = drawerEl.checked;
        filterProducts();
        updateFilterBadge();
    }

    // Sync harga drawer → sidebar
    function syncHarga(sidebarId, drawerEl) {
        const sidebarEl = document.getElementById(sidebarId);
        if (sidebarEl) sidebarEl.value = drawerEl.value;
    }

    // Sync radio rating drawer → sidebar
    function syncRadio(sidebarId) {
        const sidebarEl = document.getElementById(sidebarId);
        if (sidebarEl) {
            sidebarEl.checked = true;
            // uncheck others in sidebar
            for (let r = 1; r <= 5; r++) {
                const el = document.getElementById('r' + r);
                if (el && 'r' + r !== sidebarId) el.checked = false;
            }
        }
        filterProducts();
        updateFilterBadge();
    }

    // Update badge angka di tombol filter
    function updateFilterBadge() {
        let count = 0;
        // Hitung dari drawer inputs brand (id dinamis)
        document.querySelectorAll('.sidebar-drawer input[type="checkbox"][id^="db"]').forEach(el => {
            if (el.checked) count++;
        });
        ['dk1','dk2','dk3'].forEach(id => {
            const el = document.getElementById(id);
            if (el && el.checked) count++;
        });
        for (let r = 1; r <= 5; r++) {
            const el = document.getElementById('dr' + r);
            if (el && el.checked) count++;
        }
        const hMin = document.getElementById('dhargaMin');
        const hMax = document.getElementById('dhargaMax');
        if (hMin && hMin.value) count++;
        if (hMax && hMax.value) count++;

        const badge = document.getElementById('mobFilterBadge');
        if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? 'flex' : 'none';
        }
    }

    // Override resetFilter agar juga reset drawer inputs
    const _origResetFilter = resetFilter;
    resetFilter = function() {
        // Reset drawer checkboxes brand (id dinamis)
        document.querySelectorAll('.sidebar-drawer input[type="checkbox"][id^="db"]').forEach(el => {
            el.checked = false;
        });
        ['dk1','dk2','dk3'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.checked = false;
        });
        for (let r = 1; r <= 5; r++) {
            const el = document.getElementById('dr' + r);
            if (el) el.checked = false;
        }
        const hMin = document.getElementById('dhargaMin');
        const hMax = document.getElementById('dhargaMax');
        if (hMin) hMin.value = '';
        if (hMax) hMax.value = '';
        _origResetFilter();
        updateFilterBadge();
    };

    // Simpan halaman terakhir
    localStorage.setItem("last_page", window.location.href);
    </script>

<?php include 'chat_popup.php'; ?>
</body>
</html>