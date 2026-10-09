<?php
ob_start();
session_start();
include "koneksi.php";


if (!isset($_SESSION['role']) || $_SESSION['role'] != 2) {
    header("Location: login.php");
    exit;
}

$id_user = (int) $_SESSION['id_user'];

// ── Ambil data user ──
$q_user = mysqli_prepare($konek, "SELECT username, pp FROM users WHERE id_user=?");
mysqli_stmt_bind_param($q_user, "i", $id_user);
mysqli_stmt_execute($q_user);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($q_user));
$username_user = $user['username'] ?? '';
$pp_user       = $user['pp'] ?? '';

$from  = $_GET['from'] ?? 'menu';
$items = [];

if ($from === 'keranjang') {
    $raw_ids = $_GET['ids'] ?? '';
    $id_list = array_filter(array_map('intval', explode(',', $raw_ids)));
    if (empty($id_list)) {
        header("Location: keranjang.php");
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($id_list), '?'));
    $types        = str_repeat('i', count($id_list));
    $q = mysqli_prepare(
        $konek,
        "SELECT k.id AS id_keranjang, k.jumlah,
                m.id AS id_menu, m.nama_makanan,
                (m.harga + COALESCE(v.harga_tambah, 0)) AS harga,
                m.foto,
                kn.nama_kantin, kn.id_kantin,
                v.nama AS nama_varian,
                v.id   AS id_varian
         FROM keranjang k
         JOIN menu_req m  ON k.id_menu   = m.id
         JOIN kantin kn   ON m.id_kantin = kn.id_kantin
         LEFT JOIN varian_menu v ON k.id_varian = v.id
         WHERE k.id IN ($placeholders) AND k.id_user = ?"
    );
    $bind_params = array_merge($id_list, [$id_user]);
    $types .= 'i';
    mysqli_stmt_bind_param($q, $types, ...$bind_params);
    mysqli_stmt_execute($q);
    $result = mysqli_stmt_get_result($q);
    while ($r = mysqli_fetch_assoc($result)) {
        $items[] = $r;
    }

} else {
    $id_menu = (int) ($_GET['id_menu'] ?? 0);
    $jumlah  = max(1, (int) ($_GET['jumlah'] ?? 1));
    $id_varian = (int) ($_GET['id_varian'] ?? 0);
    if (!$id_menu) {
        header("Location: menu.php");
        exit;
    }

    $q = mysqli_prepare(
        $konek,
        "SELECT m.id AS id_menu, m.nama_makanan,
                (m.harga + COALESCE(v.harga_tambah, 0)) AS harga,
                m.foto,
                p.nama AS nama_kantin, p.id_kantin,
                v.nama AS nama_varian,
                v.id   AS id_varian
         FROM menu_req m
         JOIN penjual p ON m.id_kantin = p.id_kantin
         LEFT JOIN varian_menu v ON v.id = ? AND v.id_menu = m.id
         WHERE m.id = ? AND m.aktif = 1"
    );
    mysqli_stmt_bind_param($q, "ii", $id_varian, $id_menu);
    mysqli_stmt_execute($q);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
    if (!$r) {
        header("Location: menu.php");
        exit;
    }
    $r['jumlah']       = $jumlah;
    $r['id_keranjang'] = null;
    $items[] = $r;
}

if (empty($items)) {
    header("Location: " . ($from === 'keranjang' ? 'keranjang.php' : 'menu.php'));
    exit;
}

$grand_total = array_sum(array_map(fn($it) => $it['harga'] * $it['jumlah'], $items));

$q_notif = mysqli_prepare($konek, "SELECT COUNT(*) AS total FROM transaksi WHERE id_user=? AND status IN ('pending','diproses')");
mysqli_stmt_bind_param($q_notif, "i", $id_user);
mysqli_stmt_execute($q_notif);
$total_notif = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($q_notif))['total'];

$q_cart = mysqli_prepare($konek, "SELECT COALESCE(SUM(jumlah),0) AS total FROM keranjang WHERE id_user=?");
mysqli_stmt_bind_param($q_cart, "i", $id_user);
mysqli_stmt_execute($q_cart);
$total_keranjang = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($q_cart))['total'];

// Simpan halaman terakhir
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
setcookie("last_page", $_SERVER['REQUEST_URI'], time() + (30 * 24 * 60 * 60), "/", "", $secure, false);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Checkout – E-Canteen</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Righteous&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="co.css">
    <style>
        /* ─── PRODUCT ROW ─────────────────────────────────────── */
        .seller-row {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 9px 20px;
            font-weight: 700;
            font-size: 13px;
            color: var(--text-secondary);
            border-bottom: 1px solid #f5f5f5;
            background: #fdf9f5;
        }
        .seller-row i { color: var(--brand); }

        .product-row-db {
            display: grid;
            grid-template-columns: 1fr 140px 110px;
            align-items: center;
            gap: 14px;
            padding: 16px 20px;
            border-bottom: 1px solid #f7f7f7;
            transition: background .12s;
        }
        .product-row-db:last-child { border-bottom: none; }
        .product-row-db:hover { background: #fdfaf7; }

        .prod-info-db {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .prod-img-db {
            width: 70px; height: 70px;
            border-radius: 12px;
            object-fit: cover;
            background: #f5f5f5;
            flex-shrink: 0;
        }
        .prod-info-text { flex: 1; min-width: 0; }
        .prod-name-db  { font-size: 14px; font-weight: 700; color: #222; margin-bottom: 3px; }
        .prod-price-db { font-size: 13px; color: var(--brand); font-weight: 700; }

        .qty-sync-row {
            display: flex; align-items: center;
            background: #f7f7f7;
            border-radius: 10px; overflow: hidden;
            border: 1.5px solid #eee;
            width: fit-content;
        }
        .qty-sync-btn {
            width: 34px; height: 34px; border: none;
            background: transparent; font-size: 18px; cursor: pointer;
            color: var(--brand); font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            transition: background .15s;
        }
        .qty-sync-btn:hover { background: #fff0e6; }
        .qty-sync-input {
            width: 40px; height: 34px; border: none;
            text-align: center; font-size: 14px; font-weight: 700;
            background: transparent; outline: none;
            font-family: inherit;
        }
        .qty-sync-input::-webkit-inner-spin-button,
        .qty-sync-input::-webkit-outer-spin-button { -webkit-appearance: none; }

        .prod-qty-wrap {
            display: flex; flex-direction: column;
            align-items: flex-end; gap: 8px;
        }
        .prod-subtotal-db {
            font-size: 13px; font-weight: 800;
            color: #333; text-align: right; white-space: nowrap;
        }

        /* ─── LOADING OVERLAY ─────────────────────────────────── */
        #loadingOverlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.45); z-index: 9999;
            align-items: center; justify-content: center;
            flex-direction: column; gap: 14px;
        }
        #loadingOverlay.show { display: flex; }
        .spinner {
            width: 44px; height: 44px;
            border: 4px solid rgba(255,255,255,.3);
            border-top-color: #fff; border-radius: 50%;
            animation: spin .7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        #loadingOverlay p { color: #fff; font-size: 15px; font-weight: 600; }

        /* ─── CATATAN PER KANTIN ─────────────────────────────── */
        .note-kantin-wrap {
            padding: 12px 20px 16px;
            border-top: 1px dashed #f0e8df;
            background: #fffaf7;
        }
        .note-kantin-label {
            font-size: 12px; font-weight: 700;
            color: #888; display: block; margin-bottom: 6px;
        }
        .note-kantin-label i { color: #e8650a; margin-right: 4px; }
        .note-kantin-input {
            width: 100%; box-sizing: border-box;
            resize: none;
            border: 1.5px solid #eee;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 13px;
            font-family: inherit;
            color: #333;
            background: #fff;
            transition: border-color .15s;
            outline: none;
        }
        .note-kantin-input:focus { border-color: #e8650a; }
        .note-kantin-counter {
            text-align: right; font-size: 11px;
            color: #bbb; margin-top: 4px;
        }

        /* ─── MOBILE ≤768px ───────────────────────────────────── */
        @media (max-width: 768px) {
            .seller-row { padding: 9px 14px; }

            .product-row-db {
                display: flex;
                flex-direction: row;
                align-items: flex-start;
                gap: 12px;
                padding: 14px;
                grid-template-columns: unset;
            }

            .prod-info-db {
                flex: 1; min-width: 0;
                flex-direction: row;
                align-items: flex-start;
                gap: 10px;
            }

            .prod-img-db {
                width: 72px; height: 72px; min-width: 72px;
                border-radius: 10px;
            }

            .prod-info-text { flex: 1; min-width: 0; }
            .prod-name-db   { font-size: 13px; }

            .prod-qty-wrap {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
                width: 100%;
                margin-top: 8px;
                gap: 8px;
            }

            .qty-sync-btn   { width: 30px; height: 30px; font-size: 16px; }
            .qty-sync-input { width: 34px; height: 30px; font-size: 13px; }

            .prod-subtotal-db {
                font-size: 14px; font-weight: 800;
                color: var(--brand); text-align: right;
            }
        }
    </style>
</head>

<body>

    <!-- LOADING OVERLAY -->
    <div id="loadingOverlay">
        <div class="spinner"></div>
        <p>Membuat pesanan...</p>
    </div>

    <!-- TOPBAR (desktop only) -->
    <div class="topbar">
        <div class="top-left">
            <a href="user.php">Home</a>
            <span class="sep">|</span>
            <a href="menu.php">Menu</a>
            <span class="sep">|</span>
            <a href="pesan.php?tab=pesanan">Pesanan</a>
        </div>
        <div class="top-right">
            <a href="pesan.php?tab=notif">
                <i class="fa-regular fa-bell"></i> Notifikasi
                <?php if ($total_notif > 0): ?>
                    <span style="background:#e8650a;color:#fff;border-radius:50%;font-size:10px;padding:1px 5px;font-weight:700">
                        <?= $total_notif ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href=""><i class="fa-regular fa-circle-question"></i> Bantuan</a>
            <div class="profile">
                <?php if (!empty($pp_user)): ?>
                    <img src="../gambar/profil/<?= htmlspecialchars($pp_user) ?>"
                         alt="<?= htmlspecialchars($username_user) ?>"
                         onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($username_user) ?>&background=e8650a&color=fff&size=32'">
                <?php else: ?>
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($username_user) ?>&background=e8650a&color=fff&size=32" alt="">
                <?php endif; ?>
                <span><?= htmlspecialchars($username_user) ?></span>
                <div class="profile-drop">
                    <a href="pesan.php?tab=akun"><i class="fa-regular fa-user"></i> Akun Saya</a>
                    <a href=""><i class="fa-solid fa-gear"></i> Pengaturan</a>
                    <div class="drop-line"></div>
                    <a href="logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Log Out</a>
                </div>
            </div>
        </div>
    </div>

    <!-- NAVBAR -->
    <div class="navbar orange-bg">
        <div class="logo">
            <div class="logo-img-wrap">
                <img src="../gambar/logo.png" alt="">
            </div>
            <div class="logo-text">
                <span class="logo-title">E-Canteen</span>
                <span class="logo-sub">Esemkita</span>
            </div>
        </div>
        <span class="nav-divider"></span>
        <span class="nav-label">Checkout</span>
        <div class="icon-actions">
            <a href="keranjang.php" style="text-decoration:none;color:inherit">
                <div class="icon-btn">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <?php if ($total_keranjang > 0): ?>
                        <div class="icon-badge"><?= $total_keranjang ?></div>
                    <?php endif; ?>
                </div>
            </a>
            <div class="icon-btn">
                <i class="fa-solid fa-comment-dots"></i>
            </div>
        </div>
    </div>

    <!-- CONTENT -->
    <div class="checkout-page">

        <!-- PRODUK -->
        <div class="section-card">
            <div class="product-header">
                <span>Produk</span>
                <span style="text-align:center;">Jumlah</span>
                <span style="text-align:right;">Subtotal</span>
            </div>

            <div class="product-rows-wrap">
                <?php
                $grouped = [];
                foreach ($items as $it) {
                    $grouped[$it['nama_kantin']][] = $it;
                }
                foreach ($grouped as $nama_kantin => $group_items):
                ?>
                    <div class="seller-row">
                        <i class="fa-solid fa-store"></i>
                        <?= htmlspecialchars($nama_kantin) ?>
                    </div>

                    <?php foreach ($group_items as $idx => $it):
                        // Gunakan index global unik supaya tidak duplikat walau id_menu sama
                        static $row_idx = 0; $row_idx++;
                        $foto_src      = !empty($it['foto']) ? '../penjual/uploads/' . htmlspecialchars($it['foto']) : '../gambar/default-food.png';
                        $subtotal_awal = $it['harga'] * $it['jumlah'];
                        $nama_varian   = $it['nama_varian'] ?? '';
                    ?>
                    <div class="product-row-db" id="row-item-<?= $row_idx ?>">

                        <div class="prod-info-db">
                            <img class="prod-img-db"
                                 src="<?= $foto_src ?>"
                                 onerror="this.src='../gambar/default-food.png'"
                                 alt="<?= htmlspecialchars($it['nama_makanan']) ?>">
                            <div class="prod-info-text">
                                <div class="prod-name-db"><?= htmlspecialchars($it['nama_makanan']) ?></div>
                                <?php if (!empty($nama_varian)): ?>
                                <div style="font-size:11px;color:#888;font-weight:600;margin-bottom:2px;">
                                    <i class="fa-solid fa-tag" style="font-size:10px;color:#e8650a;margin-right:3px;"></i>
                                    Varian: <?= htmlspecialchars($nama_varian) ?>
                                </div>
                                <?php endif; ?>
                                <div class="prod-price-db">Rp<?= number_format($it['harga'], 0, ',', '.') ?></div>
                                <div class="prod-qty-wrap">
                                    <div class="qty-sync-row">
                                        <button class="qty-sync-btn"
                                            onclick="ubahQty(<?= $row_idx ?>, -1, <?= $it['harga'] ?>)">−</button>
                                        <input type="number" class="qty-sync-input"
                                            id="qty-<?= $row_idx ?>"
                                            value="<?= $it['jumlah'] ?>" min="1"
                                            data-harga="<?= $it['harga'] ?>"
                                            onchange="setQty(<?= $row_idx ?>, <?= $it['harga'] ?>, this)">
                                        <button class="qty-sync-btn"
                                            onclick="ubahQty(<?= $row_idx ?>, 1, <?= $it['harga'] ?>)">+</button>
                                    </div>
                                    <div class="prod-subtotal-db" id="sub-<?= $row_idx ?>">
                                        Rp<?= number_format($subtotal_awal, 0, ',', '.') ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" class="hidden-id-menu"      value="<?= $it['id_menu'] ?>">
                        <input type="hidden" class="hidden-id-kantin"    value="<?= $it['id_kantin'] ?>">
                        <input type="hidden" class="hidden-nama-kantin"  value="<?= htmlspecialchars($it['nama_kantin']) ?>">
                        <input type="hidden" class="hidden-nama-makanan" value="<?= htmlspecialchars($it['nama_makanan']) ?>">
                        <input type="hidden" class="hidden-nama-varian"  value="<?= htmlspecialchars($nama_varian) ?>">
                        <input type="hidden" class="hidden-id-varian"    value="<?= (int)($it['id_varian'] ?? 0) ?>">
                        <?php if (!empty($it['id_keranjang'])): ?>
                            <input type="hidden" class="hidden-id-keranjang" value="<?= $it['id_keranjang'] ?>">
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <!-- Catatan per toko -->
                    <div class="note-kantin-wrap">
                        <label class="note-kantin-label">
                            <i class="fa-solid fa-pen-to-square"></i>
                            Catatan untuk <?= htmlspecialchars($nama_kantin) ?>
                        </label>
                        <textarea class="note-kantin-input note-per-kantin"
                            data-kantin="<?= htmlspecialchars($nama_kantin) ?>"
                            placeholder="Catatan untuk <?= htmlspecialchars($nama_kantin) ?>... (opsional)"
                            rows="2" maxlength="200"></textarea>
                        <div class="note-kantin-counter"><span class="note-kantin-count">0</span>/200</div>
                    </div>

                <?php endforeach; ?>
            </div>

            <!-- DELIVERY -->
            <div class="ship-wrap">
                <div class="ship-title">
                    <i class="fa-solid fa-truck"></i>
                    Metode Pengambilan
                </div>
                <div class="delivery-options">
                    <div class="delivery-card active" onclick="selectDelivery(0, this)">
                        <div class="delivery-top">
                            <i class="fa-solid fa-store"></i>
                            <span>Ambil di Kantin</span>
                        </div>
                        <div class="delivery-desc">Ambil langsung di kantin sekolah</div>
                    </div>
                </div>
            </div>

            <div class="total-order">
                <span>Total Pesanan:</span>
                <span id="orderTotal">Rp<?= number_format($grand_total, 0, ',', '.') ?></span>
            </div>
        </div>

        <!-- PAYMENT -->
        <div class="section-card">
            <div class="section-title">
                <i class="fa-solid fa-wallet"></i>
                Metode Pembayaran
            </div>
            <div class="payment-wrap">
                <div class="payment-buttons">
                    <button class="pay-btn active" id="payBtn-CASH" onclick="selectPay('CASH', this)">
                        <i class="fa-solid fa-money-bill-wave"></i> CASH
                    </button>
                    <button class="pay-btn" id="payBtn-QRIS" onclick="selectPay('QRIS', this)">
                        <i class="fa-solid fa-qrcode"></i> QRIS
                    </button>
                </div>
            </div>
        </div>

        <!-- SUMMARY -->
        <div class="section-card">
            <div class="summary-row">
                <span>Subtotal Produk</span>
                <span id="summarySubtotal">Rp<?= number_format($grand_total, 0, ',', '.') ?></span>
            </div>
            <div class="summary-row">
                <span>Biaya Pengambilan</span>
                <span id="summaryShipping">Rp0</span>
            </div>
            <div class="summary-total">
                <span>Total Pembayaran</span>
                <span id="grandTotal">Rp<?= number_format($grand_total, 0, ',', '.') ?></span>
            </div>
            <button class="checkout-btn" onclick="checkout()">
                <i class="fa-solid fa-bag-shopping"></i>
                Buat Pesanan
            </button>
        </div>

    </div><!-- end .checkout-page -->

    <!-- MOBILE BOTTOM NAV -->
    <div class="mob-bottom-nav">

        <a href="user.php" style="flex:1;display:flex;text-decoration:none;color:inherit;">
            <div class="mob-nav-item">
                <i class="fa-solid fa-house"></i>
                <span>Home</span>
            </div>
        </a>

        <a href="menu.php" style="flex:1;display:flex;text-decoration:none;color:inherit;">
            <div class="mob-nav-item">
                <i class="fa-solid fa-bowl-food"></i>
                <span>Menu</span>
            </div>
        </a>

        <div class="mob-nav-search"
            onclick="document.getElementById('searchOverlay').classList.add('open');setTimeout(()=>document.getElementById('sheetInput').focus(),300)">
            <div class="mob-nav-search-btn"><i class="fa-solid fa-magnifying-glass"></i></div>
            <span>Cari</span>
        </div>

        <a href="pesan.php?tab=pesanan" style="flex:1;display:flex;text-decoration:none;color:inherit;">
            <div class="mob-nav-item active" style="position:relative;">
                <i class="fa-solid fa-bag-shopping"></i>
                <span>Pesanan</span>
                <?php if ($total_notif > 0): ?>
                    <div style="position:absolute;top:6px;right:calc(50% - 18px);background:#e8650a;color:#fff;border-radius:8px;font-size:9px;font-weight:700;padding:1px 5px;min-width:16px;text-align:center;">
                        <?= $total_notif ?>
                    </div>
                <?php endif; ?>
            </div>
        </a>

        <a href="pesan.php?tab=akun" style="flex:1;display:flex;text-decoration:none;color:inherit;">
            <div class="mob-nav-item">
                <i class="fa-regular fa-user"></i>
                <span>Akun</span>
            </div>
        </a>

    </div>

    <!-- SEARCH OVERLAY -->
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
                    <div class="chip">Nasi Goreng</div>
                    <div class="chip">Mie Ayam</div>
                    <div class="chip">Es Teh</div>
                    <div class="chip">Bakso</div>
                    <div class="chip">Soto Ayam</div>
                    <div class="chip">Jus Alpukat</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let shippingCost   = 0;
        let selectedMetode = 'CASH';

localStorage.setItem("last_page", window.location.href);

        function rupiah(num) {
            return 'Rp' + parseInt(num).toLocaleString('id-ID');
        }

        function hitungGrandTotal() {
            let subtotal = 0;
            document.querySelectorAll('.prod-subtotal-db').forEach(el => {
                subtotal += parseInt(el.textContent.replace(/[^0-9]/g, '')) || 0;
            });
            document.getElementById('summarySubtotal').textContent = rupiah(subtotal);
            document.getElementById('summaryShipping').textContent = rupiah(shippingCost);
            document.getElementById('orderTotal').textContent      = rupiah(subtotal + shippingCost);
            document.getElementById('grandTotal').textContent      = rupiah(subtotal + shippingCost);
        }

        function ubahQty(row_idx, delta, harga) {
            const input = document.getElementById('qty-' + row_idx);
            let val = parseInt(input.value) + delta;
            if (val < 1) val = 1;
            input.value = val;
            document.getElementById('sub-' + row_idx).textContent = rupiah(harga * val);
            hitungGrandTotal();
        }

        function setQty(row_idx, harga, el) {
            let val = parseInt(el.value);
            if (!val || val < 1) { val = 1; el.value = 1; }
            document.getElementById('sub-' + row_idx).textContent = rupiah(harga * val);
            hitungGrandTotal();
        }

        function selectDelivery(cost, element) {
            shippingCost = cost;
            document.querySelectorAll('.delivery-card').forEach(c => c.classList.remove('active'));
            element.classList.add('active');
            hitungGrandTotal();
        }

        function selectPay(metode, btn) {
            selectedMetode = metode;
            document.querySelectorAll('.pay-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        }

        // Counter karakter per textarea catatan kantin
        document.addEventListener('input', function (e) {
            if (e.target.classList.contains('note-per-kantin')) {
                const wrap    = e.target.closest('.note-kantin-wrap');
                const counter = wrap?.querySelector('.note-kantin-count');
                if (counter) {
                    counter.textContent = e.target.value.length;
                    counter.style.color = e.target.value.length > 180 ? '#c44b00' : '';
                }
            }
        });

        function checkout() {
    const rows = document.querySelectorAll('.product-row-db');
    if (rows.length === 0) { alert('Tidak ada item untuk dipesan.'); return; }

    const items = [];
    // Kumpulkan catatan per kantin dulu
    const catatanMap = {};
    document.querySelectorAll('.note-per-kantin').forEach(ta => {
        catatanMap[ta.dataset.kantin] = ta.value.trim();
    });

    rows.forEach(row => {
        const id_menu      = row.querySelector('.hidden-id-menu')?.value;
        const id_kantin    = row.querySelector('.hidden-id-kantin')?.value;
        const nama_kantin  = row.querySelector('.hidden-nama-kantin')?.value;
        const nama_makanan = row.querySelector('.hidden-nama-makanan')?.value;
        const nama_varian  = row.querySelector('.hidden-nama-varian')?.value || '';
        const id_varian    = parseInt(row.querySelector('.hidden-id-varian')?.value) || 0;
        const id_keranjang = row.querySelector('.hidden-id-keranjang')?.value || null;
        const qty_input    = row.querySelector('input[type="number"].qty-sync-input');
        const jumlah       = parseInt(qty_input?.value) || 1;
        const harga        = parseInt(qty_input?.dataset.harga) || 0;
        const catatan      = catatanMap[nama_kantin] || '';
        items.push({ id_menu, id_kantin, nama_kantin, nama_makanan, nama_varian, id_varian, id_keranjang, jumlah, harga, catatan });
    });

    const grandTotalVal = parseInt(
        document.getElementById('grandTotal').textContent.replace(/[^0-9]/g, '')
    );

    if (!confirm('Konfirmasi pesanan?\n\nTotal: Rp' + grandTotalVal.toLocaleString('id-ID') + '\nMetode: ' + selectedMetode)) return;

if (selectedMetode === 'QRIS') {
    document.getElementById('loadingOverlay').classList.add('show');
    fetch('proses_co.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({
            items,
            metode:   'QRIS',
            total:    grandTotalVal,
            shipping: shippingCost
        })
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('loadingOverlay').classList.remove('show');
        if (data.status === 'ok') {
            const ids = data.id_transaksi.join(',');
            window.location.href = 'bayar_qris.php?ids=' + ids;
        } else {
            alert('❌ Gagal membuat pesanan: ' + (data.msg || 'Terjadi kesalahan'));
        }
    })
    .catch(() => {
        document.getElementById('loadingOverlay').classList.remove('show');
        alert('❌ Kesalahan jaringan. Coba lagi.');
    });
    return;
}


    document.getElementById('loadingOverlay').classList.add('show');

    fetch('proses_co.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({
            items,
            metode:   selectedMetode,
            total:    grandTotalVal,
            shipping: shippingCost
        })
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('loadingOverlay').classList.remove('show');
        if (data.status === 'ok') {
            window.location.href = 'pesan.php?tab=pesanan&baru=1';
        } else {
            alert('❌ Gagal membuat pesanan: ' + (data.msg || 'Terjadi kesalahan'));
        }
    })
    .catch(() => {
        document.getElementById('loadingOverlay').classList.remove('show');
        alert('❌ Kesalahan jaringan. Coba lagi.');
    });
}
    </script>

</body>
</html>