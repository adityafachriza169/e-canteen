<?php
session_start();
require 'koneksi.php';


if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

$id_user = $_SESSION['id_user'];

// ── DATA USER ──
$query = mysqli_query($konek, "
    SELECT id_user, username, nama, email, pp, no_hp, gender, tanggal_lahir
    FROM users WHERE id_user = '$id_user'
");
$user          = mysqli_fetch_assoc($query);
$username_user = $user['username'] ?? '';
$pp_user       = $user['pp'] ?? '';

// ── TOTAL KERANJANG (realtime dari DB) ──
$q_cart = mysqli_prepare($konek, "SELECT COALESCE(SUM(jumlah),0) AS total FROM keranjang WHERE id_user=?");
mysqli_stmt_bind_param($q_cart, "i", $id_user);
mysqli_stmt_execute($q_cart);
$total_keranjang = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($q_cart))['total'];

// ── DAFTAR MENU ──
$res_menu = mysqli_query($konek, "
    SELECT m.*, k.nama_kantin,
           COALESCE((SELECT ROUND(AVG(bintang),1) FROM ulasan WHERE id_menu = m.id), 0) AS rating
    FROM menu_req m
    JOIN kantin k ON m.id_kantin = k.id_kantin
    WHERE m.aktif = 1
    ORDER BY m.terjual DESC
");
$daftar_menu = [];
while ($row = mysqli_fetch_assoc($res_menu)) $daftar_menu[] = $row;

// ── HALAMAN AKTIF (untuk bottom nav) ──
$current_page = basename($_SERVER['PHP_SELF']); // "menu.php"

// Simpan halaman terakhir
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
setcookie("last_page", $_SERVER['REQUEST_URI'], time() + (30 * 24 * 60 * 60), "/", "", $secure, false);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu - E-Canteen</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Righteous&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="menu.css">
    <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
</head>
<body>

<!-- ══ TOPBAR ══ -->
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

<!-- ══ NAVBAR (DESKTOP) ══ -->
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
        <div class="icon-btn">
            <a href="keranjang.php" style="color:inherit;display:flex;align-items:center;position:relative;">
                <i class="fa-solid fa-cart-shopping"></i>
                <?php if ($total_keranjang > 0): ?>
                <span id="cartBadgeDesktop" class="icon-badge"><?php echo $total_keranjang; ?></span>
                <?php else: ?>
                <span id="cartBadgeDesktop" class="icon-badge" style="display:none;"></span>
                <?php endif; ?>
            </a>
        </div>
        <button onclick="openChatPopup()" class="icon-btn chat-trigger-btn notif-chat-btn">
    <i class="fa-solid fa-comment-dots"></i>

    <span class="chat-badge" id="chatBadge" style="display:none"></span>
</button>
    </div>
</div>

<!-- ══ NAVBAR (MOBILE TOP) ══ -->
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
            <button onclick="openChatPopup()" class="icon-btn chat-trigger-btn notif-chat-btn">
    <i class="fa-solid fa-comment-dots"></i>

    <span class="chat-badge" id="chatBadge" style="display:none"></span>
</button>
            <a href="keranjang.php" style="text-decoration:none;color:inherit">
                <div class="mob-icon-btn" id="cartIconMob">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <?php if ($total_keranjang > 0): ?>
                    <div class="mob-badge" id="cartBadgeMob"><?php echo $total_keranjang; ?></div>
                    <?php else: ?>
                    <div class="mob-badge" id="cartBadgeMob" style="display:none;"></div>
                    <?php endif; ?>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- ══ BOTTOM NAV (MOBILE) ══ -->
<div class="bottom-nav">
    <a href="user.php" class="<?php echo $current_page === 'user.php' ? 'active' : ''; ?>">
        <i class="fa-solid fa-house"></i>
        <span>Home</span>
    </a>
    <a href="menu.php" class="<?php echo $current_page === 'menu.php' ? 'active' : ''; ?>">
        <i class="fa-solid fa-utensils"></i>
        <span>Menu</span>
    </a>
    <a href="cari.php" class="<?php echo $current_page === 'cari.php' ? 'active' : ''; ?>">
        <i class="fa-solid fa-magnifying-glass"></i>
        <span>Cari</span>
    </a>
    <a href="pesan.php" class="<?php echo ($current_page === 'pesan.php' || $current_page === 'pesanan.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-receipt"></i>
        <span>Pesanan</span>
    </a>
    <a href="akun.php" class="<?php echo $current_page === 'akun.php' ? 'active' : ''; ?>">
        <i class="fa-solid fa-user"></i>
        <span>Akun</span>
    </a>
</div>

<!-- ══ SEARCH OVERLAY ══ -->
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

<!-- ══ TOAST ══ -->
<div id="cart-toast"></div>

<!-- ══ MAIN CONTENT ══ -->
<section>
    <div class="rekomendasi-outer">
        <div class="rekomendasi-title-wrap">
            <h2 class="rekomendasi-title">PILIHAN MENU</h2>
        </div>
        <div class="container" id="menuContainer">
            <?php if (!empty($daftar_menu)): ?>
                <?php foreach ($daftar_menu as $menu): ?>
                <a href="klik.php?id=<?php echo $menu['id']; ?>" style="text-decoration:none;color:inherit">
                    <div class="card"
                        data-id="<?php echo $menu['id']; ?>"
                        data-nama="<?php echo htmlspecialchars($menu['nama_makanan']); ?>"
                        data-kantin="<?php echo htmlspecialchars($menu['nama_kantin']); ?>"
                        data-kategori="<?php echo htmlspecialchars($menu['kategori']); ?>">

                        <!-- GAMBAR -->
                        <div class="gambar">
                            <img src="../penjual/uploads/<?php echo htmlspecialchars($menu['foto']); ?>"
                                alt="<?php echo htmlspecialchars($menu['nama_makanan']); ?>"
                                onerror="this.src='../gambar/default-food.png'" loading="lazy">
                        </div>

                        <!-- ISI -->
                        <div class="isi">
                            <div class="kategori">
                                <span class="kantin"><?php echo htmlspecialchars($menu['nama_kantin']); ?></span>
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
                                    <div class="atas"><i class="fa-solid fa-star star"></i><span><?php echo number_format((float)$menu['rating'], 1); ?></span></div>
                                    <div class="bawah">Rating</div>
                                </div>
                                <div class="garis"></div>
                                <div class="box">
                                    <div class="atas"><i class="fa-solid fa-bag-shopping"></i><span><?php echo htmlspecialchars($menu['terjual']); ?></span></div>
                                    <div class="bawah">Terjual</div>
                                </div>
                                <div class="garis"></div>
                                <div class="box">
                                    <div class="atas"><i class="fa-solid fa-box"></i><span><?php echo htmlspecialchars($menu['stok']); ?></span></div>
                                    <div class="bawah">Stock</div>
                                </div>
                            </div>
                        </div>

                        <!-- TOMBOL + TAMBAH KERANJANG -->
                        <?php if ($menu['stok'] > 0): ?>
                        <button class="card-add-btn"
                            data-id="<?php echo $menu['id']; ?>"
                            data-foto="../penjual/uploads/<?php echo htmlspecialchars($menu['foto']); ?>"
                            data-nama="<?php echo htmlspecialchars($menu['nama_makanan']); ?>"
                            onclick="event.preventDefault(); event.stopPropagation(); tambahKeranjang(this);"
                            title="Tambah ke keranjang">
                            <i class="fa-solid fa-plus" style="font-size:14px;pointer-events:none;"></i>
                        </button>
                        <?php else: ?>
                        <span class="card-sold-out">Habis</span>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-solid fa-bowl-food"></i>
                    <p>Belum ada menu tersedia.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="pag-wrap" id="pagWrap"></div>
    </div>
</section>

<!-- ══ FOOTER ══ -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<footer class="footer" id="kontak">
    <div class="footer-grid">
        <!-- BRAND -->
        <div class="footer-brand">
            <img src="../gambar/logo.png" alt="logo">
            <p>E-Canteen adalah platform kantin digital sekolah untuk mempermudah pemesanan makanan, pembayaran, dan informasi menu secara online.</p>
            <div class="footer-quote">
                <i class="bi bi-quote"></i>
                <span>Makan nyaman, belajar tenang.</span>
            </div>
            <div class="footer-jam">
                <h4><i class="bi bi-clock"></i> JAM OPERASIONAL</h4>
                <div class="jam-row"><span>Senin – Jumat</span><span>07.00 – 15.00</span></div>
            </div>
        </div>
        <!-- MENU -->
        <div class="footer-col">
            <h3>MENU</h3>
            <ul>
                <li><a href="user.php"><i class="bi bi-chevron-right"></i> Home</a></li>
                <li><a href="menu.php"><i class="bi bi-chevron-right"></i> Menu Kantin</a></li>
                <li><a href="pesan.php"><i class="bi bi-chevron-right"></i> Pesanan Saya</a></li>
                <li><a href="#"><i class="bi bi-chevron-right"></i> Tentang Kami</a></li>
                <li><a href="#"><i class="bi bi-chevron-right"></i> FAQ</a></li>
                <li><a href="#"><i class="bi bi-chevron-right"></i> Kontak Kami</a></li>
            </ul>
            <div class="footer-kategori">
                <h3>KATEGORI</h3>
                <ul>
                    <li><a href="#"><i class="bi bi-egg-fried"></i> Makanan</a></li>
                    <li><a href="#"><i class="bi bi-cup-straw"></i> Minuman</a></li>
                    <li><a href="#"><i class="bi bi-basket"></i> Snack</a></li>
                </ul>
            </div>
        </div>
        <!-- KONTAK -->
        <div class="footer-col">
            <h3>KONTAK KAMI</h3>
            <div class="contact-item"><i class="bi bi-geo-alt-fill"></i><span>No. 3 Jl. Ki Mangun Sarkoro 66233 <br> Boyolangu Jawa Timur</span></div>
            <div class="contact-item"><i class="bi bi-telephone-fill"></i><span>0812-3456-7890</span></div>
            <div class="contact-item"><i class="bi bi-envelope-fill"></i><a href="mailto:canteen@gmail.com">canteen@gmail.com</a></div>
            <div class="contact-item"><i class="bi bi-clock-fill"></i><span>Senin – Jumat 07.00 – 15.00</span></div>
            <div id="map"></div>
        </div>
        <!-- SOSMED -->
        <div class="footer-col">
            <h3>IKUTI KAMI</h3>
            <div class="footer-social">
                <a href="#"><i class="bi bi-instagram"></i></a>
                <a href="#"><i class="bi bi-tiktok"></i></a>
                <a href="#"><i class="bi bi-youtube"></i></a>
                <a href="#"><i class="bi bi-whatsapp"></i></a>
            </div>
            <div class="footer-help">
                <h3>BUTUH BANTUAN?</h3>
                <p>Punya pertanyaan atau keluhan? Hubungi kami, tim kami siap membantu!</p>
                <a href="mailto:canteen@gmail.com"><button>Hubungi Kami <i class="bi bi-arrow-right"></i></button></a>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <p>© 2026 E-Canteen Sekolah. All rights reserved. <br> Made with ❤️ by ZigiZaga Team</p>
    </div>
</footer>

<!-- LEAFLET -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const map = L.map('map').setView([-8.080247, 111.900696], 17);
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles © Esri'
    }).addTo(map);
    const customIcon = L.icon({
        iconUrl: 'https://cdn-icons-png.flaticon.com/512/684/684908.png',
        iconSize: [45, 45], iconAnchor: [22, 45], popupAnchor: [0, -40]
    });
    L.marker([-8.080247, 111.900696], { icon: customIcon }).addTo(map)
        .bindPopup('<b>SMKN 1 Boyolangu</b><br>Tulungagung, Jawa Timur');
    setTimeout(() => map.invalidateSize(), 300);
</script>

<!-- ══ PAGINATION ══ -->
<script>
(function() {
    const container    = document.getElementById('menuContainer');
    const pagWrap      = document.getElementById('pagWrap');
    const allCards     = Array.from(container.querySelectorAll('a'));
    const cardsPerPage = 12;
    let   currentPage  = 1;
    const totalPages   = Math.ceil(allCards.length / cardsPerPage);

    function showPage(page) {
        currentPage = page;
        allCards.forEach((c, i) => {
            const start = (page - 1) * cardsPerPage;
            c.style.display = (i >= start && i < start + cardsPerPage) ? '' : 'none';
        });
        renderPagination();
        // scroll ke atas section menu dengan smooth
        container.closest('section').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function renderPagination() {
        if (totalPages <= 1) { pagWrap.innerHTML = ''; return; }
        pagWrap.innerHTML = '';

        const prev = document.createElement('button');
        prev.innerHTML = '&#8249;';
        prev.className = 'pag-btn arrow';
        prev.disabled  = currentPage === 1;
        prev.onclick   = () => showPage(currentPage - 1);
        pagWrap.appendChild(prev);

        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                const btn = document.createElement('button');
                btn.innerText = i;
                btn.className = 'pag-btn' + (i === currentPage ? ' active' : '');
                btn.onclick   = () => showPage(i);
                pagWrap.appendChild(btn);
            } else if (i === currentPage - 2 || i === currentPage + 2) {
                const dots = document.createElement('span');
                dots.className = 'pag-dots';
                dots.innerText = '...';
                pagWrap.appendChild(dots);
            }
        }

        const next = document.createElement('button');
        next.innerHTML = '&#8250;';
        next.className = 'pag-btn arrow';
        next.disabled  = currentPage === totalPages;
        next.onclick   = () => showPage(currentPage + 1);
        pagWrap.appendChild(next);
    }

    showPage(1);
})();
</script>

<!-- ══ SEARCH ══ -->
<script>
    const navInput   = document.getElementById('navSearchInput');
    const navBtn     = document.getElementById('navSearchBtn');
    const sheetInput = document.getElementById('sheetInput');

    function goSearch(query) {
        const q = query.trim();
        if (!q) return;
        window.location.href = 'search.php?q=' + encodeURIComponent(q);
    }

    navBtn.addEventListener('click', () => goSearch(navInput.value));
    navInput.addEventListener('keydown', e => { if (e.key === 'Enter') goSearch(navInput.value); });
    sheetInput.addEventListener('keydown', e => { if (e.key === 'Enter') goSearch(sheetInput.value); });

    function searchFromChip(el) { goSearch(el.textContent.trim()); }
</script>

<!-- ══ KERANJANG: ANIMASI + BADGE REALTIME ══ -->
<script>
    // Target ikon keranjang di navbar yang sedang terlihat
    function getCartTarget() {
        const isMobile = window.innerWidth < 768;
        const el = isMobile
            ? document.getElementById('cartIconMob')
            : document.querySelector('.icon-btn a[href="keranjang.php"]');
        if (!el) return { x: window.innerWidth / 2, y: 60 };
        const rect = el.getBoundingClientRect();
        return { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 };
    }

    function showToast(msg) {
        const t = document.getElementById('cart-toast');
        t.textContent = msg;
        t.classList.add('show');
        clearTimeout(t._timer);
        t._timer = setTimeout(() => t.classList.remove('show'), 2500);
    }

    function updateBadge(total) {
        ['cartBadgeDesktop', 'cartBadgeMob'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.textContent   = total > 0 ? total : '';
            el.style.display = total > 0 ? 'flex' : 'none';
            el.classList.remove('badge-bounce');
            void el.offsetWidth; // force reflow
            el.classList.add('badge-bounce');
            setTimeout(() => el.classList.remove('badge-bounce'), 500);
        });
    }

    function flyToCart(btn) {
        const rect   = btn.getBoundingClientRect();
        const target = getCartTarget();
        const foto   = btn.dataset.foto;

        const img = document.createElement('img');
        img.className = 'fly-img';
        img.src       = foto || '../gambar/default-food.png';
        img.onerror   = () => { img.src = '../gambar/default-food.png'; };

        // posisi awal (tengah tombol +)
        img.style.left = (rect.left + rect.width  / 2 - 25) + 'px';
        img.style.top  = (rect.top  + rect.height / 2 - 25) + 'px';
        document.body.appendChild(img);

        // trigger animasi ke ikon keranjang
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                img.classList.add('animate');
                img.style.left = (target.x - 25) + 'px';
                img.style.top  = (target.y - 25) + 'px';
            });
        });

        img.addEventListener('transitionend', () => img.remove(), { once: true });
    }

    function tambahKeranjang(btn) {
        const idMenu = btn.dataset.id;
        const nama   = btn.dataset.nama;

        // animasi terbang
        flyToCart(btn);

        // kirim ke server
        fetch('tambah_keranjang.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id_menu=' + encodeURIComponent(idMenu)
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                updateBadge(data.total_keranjang);
                showToast('✅ ' + nama + ' ditambahkan ke keranjang!');
            } else {
                showToast('❌ ' + (data.message || 'Gagal menambahkan'));
            }
        })
        .catch(() => showToast('❌ Gagal terhubung ke server'));
    }
    // Simpan halaman sekarang
localStorage.setItem("last_page", window.location.href);
</script>

</body>
</html>