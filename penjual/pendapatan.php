<?php
// =============================================
// SESSION & AUTH CHECK
// =============================================
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['username']) || (int)($_SESSION['role'] ?? 0) !== 1) {
    header('Location: ../users/login.php');
    exit;
}

// =============================================
// KONEKSI
// =============================================
include "../users/koneksi.php";
include 'chat_popup_penjual.php';
$conn = $konek; 
$conn->set_charset('utf8mb4');

// =============================================
// AMBIL DATA PENJUAL
// =============================================
$ses_username = $_SESSION['username'];

$stmt = $conn->prepare("
    SELECT p.id_penjual, p.id_kantin, p.nama, p.username, p.profil,
           k.nama_kantin
    FROM penjual p
    LEFT JOIN kantin k ON k.id_kantin = p.id_kantin
    WHERE p.username = ?
    LIMIT 1
");
$stmt->bind_param("s", $ses_username);
$stmt->execute();
$penjual_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$penjual_data) {
    session_destroy();
    header('Location: ../users/login.php');
    exit;
}

$id_kantin    = (int) $penjual_data['id_kantin'];
$nama_penjual = $penjual_data['nama']        ?? 'Penjual';
$user_penjual = $penjual_data['username']    ?? 'penjual';
$nama_kantin  = $penjual_data['nama_kantin'] ?? 'Kantin';

// Foto profil
$foto_profil_file = $penjual_data['profil'] ?? '';
$kemungkinan_path = ['profil/' . $foto_profil_file, '../profil/' . $foto_profil_file, '../admin/uploads/' . $foto_profil_file];
$foto_profil = 'https://ui-avatars.com/api/?name=' . urlencode($nama_penjual) . '&background=e8650a&color=fff&size=80';
foreach ($kemungkinan_path as $p) {
    if (!empty($foto_profil_file) && file_exists($p)) { $foto_profil = $p; break; }
}

// =============================================
// STATISTIK PENDAPATAN
// =============================================

// ── Pendapatan HARI INI ──
$res_hari = $conn->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN LOWER(metode) LIKE '%qris%' THEN total ELSE 0 END), 0) AS qris_hari,
        COALESCE(SUM(CASE WHEN LOWER(metode) NOT LIKE '%qris%' THEN total ELSE 0 END), 0) AS cash_hari
    FROM transaksi
    WHERE id_kantin = ?
      AND DATE(tanggal) = CURDATE()
      AND status IN ('selesai','lunas')
");
$res_hari->bind_param("i", $id_kantin);
$res_hari->execute();
$row_hari = $res_hari->get_result()->fetch_assoc();
$res_hari->close();

$pendapatan_harian_qris = (float)$row_hari['qris_hari'];
$pendapatan_harian_cash = (float)$row_hari['cash_hari'];

// ── Pendapatan BULAN INI ──
$res_bulan = $conn->prepare("
    SELECT
        COUNT(*) AS jml_bulan,
        COALESCE(SUM(CASE WHEN LOWER(metode) LIKE '%qris%' THEN total ELSE 0 END), 0) AS qris_bulan,
        COALESCE(SUM(CASE WHEN LOWER(metode) NOT LIKE '%qris%' THEN total ELSE 0 END), 0) AS cash_bulan
    FROM transaksi
    WHERE id_kantin = ?
      AND MONTH(tanggal) = MONTH(CURDATE())
      AND YEAR(tanggal)  = YEAR(CURDATE())
      AND status IN ('selesai','lunas')
");
$res_bulan->bind_param("i", $id_kantin);
$res_bulan->execute();
$row_bulan = $res_bulan->get_result()->fetch_assoc();
$res_bulan->close();

$jml_bulan              = (int)$row_bulan['jml_bulan'];
$pendapatan_bulanan_qris = (float)$row_bulan['qris_bulan'];
$pendapatan_bulanan_cash = (float)$row_bulan['cash_bulan'];

// ── Transaksi terbaru (50 terakhir, SEMUA metode) ──
$res3 = $conn->prepare("
    SELECT t.id_transaksi, t.tanggal, t.total, t.status, t.metode,
           t.nama_makanan, t.jumlah,
           u.nama AS nama_pembeli
    FROM transaksi t
    JOIN menu_req mr ON t.id_menu = mr.id
    LEFT JOIN users u ON u.id_user = t.id_user
    WHERE mr.id_kantin = ?
      AND t.status IN ('selesai', 'lunas')
    ORDER BY t.tanggal DESC
    LIMIT 50
");
$res3->bind_param("i", $id_kantin);
$res3->execute();
$res3_result    = $res3->get_result();
$transaksi_list = [];
while ($r3 = $res3_result->fetch_assoc()) {
    $r3['tanggal_fmt']  = date('d M Y, H:i', strtotime($r3['tanggal']));
    $r3['is_qris']      = (stripos($r3['metode'], 'qris') !== false) ? 1 : 0;
    $transaksi_list[]   = $r3;
}
$res3->close();

// ── Jumlah pesanan pending ──
$pending_orders = 0;
{
    $__q = $conn->prepare("SELECT COUNT(*) AS jml FROM transaksi WHERE id_kantin = ? AND status = 'pending'");
    $__q->bind_param('i', $id_kantin);
    $__q->execute();
    $pending_orders = (int) $__q->get_result()->fetch_assoc()['jml'];
    $__q->close();
}

$conn->close();

function rupiah(float $n): string {
    return 'Rp ' . number_format($n, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>E-Canteen - Pendapatan</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Righteous&family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="pendapatan.css">
</head>
<body>

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

<!-- ── NAVBAR ── -->
<div class="navbar">
  <div class="navbar-texture"></div>
  <div class="orb1"></div>
  <div class="orb2"></div>

  <button class="hamburger-btn" id="hamburgerBtn" aria-label="Buka menu" onclick="toggleSidebar()">
    <span></span><span></span><span></span>
  </button>

  <div class="logo">
    <div class="logo-img-wrap">
      <img src="../gambar/logo.png" alt="Logo E-Canteen">
    </div>
    <div class="logo-text">
      <span class="logo-title">E-Canteen</span>
      <span class="logo-sub">Esemkita</span>
    </div>
  </div>

  <div class="search-wrap">
    <div class="search-box">
      <input type="text" id="navSearchInput" placeholder="Cari makanan dan minuman...">
      <button onclick="openSearch()" aria-label="Cari"><i class="fa-solid fa-magnifying-glass"></i></button>
    </div>
  </div>

  <div class="icon-actions">
    <div class="icon-btn" title="Pesan">
      <button onclick="openChatPopup()" class="icon-btn chat-trigger-btn notif-chat-btn">
        <i class="fa-solid fa-comment-dots"></i>
        <span class="icon-badge" id="chatBadge" style="display:none"></span>
      </button>
    </div>
  </div>
</div>

<!-- ── SIDEBAR OVERLAY ── -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ── SEARCH OVERLAY ── -->
<div class="search-overlay" id="searchOverlay" onclick="if(event.target===this)closeSearch()">
  <div class="search-sheet">
    <div class="search-sheet-handle"></div>
    <h3>Cari Makanan &amp; Minuman</h3>
    <div class="search-sheet-box">
      <input id="sheetInput" type="text" placeholder="Ketik nama menu...">
      <button onclick="doSearch()" aria-label="Cari"><i class="fa-solid fa-magnifying-glass"></i></button>
    </div>
    <div style="margin-top:14px">
      <p style="font-size:11px;color:#aaa;font-weight:600;letter-spacing:.5px;text-transform:uppercase;margin-bottom:8px">Populer</p>
      <div class="suggestion-chips">
        <div class="chip" onclick="searchChip(this)">Nasi Goreng</div>
        <div class="chip" onclick="searchChip(this)">Mie Ayam</div>
        <div class="chip" onclick="searchChip(this)">Es Teh</div>
        <div class="chip" onclick="searchChip(this)">Bakso</div>
        <div class="chip" onclick="searchChip(this)">Soto Ayam</div>
        <div class="chip" onclick="searchChip(this)">Jus Alpukat</div>
      </div>
    </div>
  </div>
</div>

<!-- ── PAGE WRAPPER ── -->
<div class="page-wrapper">

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-profile">
      <div class="sidebar-avatar">
        <img src="<?= htmlspecialchars($foto_profil) ?>" alt="Avatar"
             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($nama_penjual) ?>&background=e8650a&color=fff&size=80'" />
      </div>
      <div class="sidebar-profile-info">
        <h3><?= htmlspecialchars($nama_penjual) ?></h3>
        <small style="color:#aaa;font-size:.72rem;"><?= htmlspecialchars($nama_kantin) ?></small><br>
        <a href="profil/profile.php">✏️ Ubah Profil</a>
      </div>
    </div>

    <nav class="sidebar-nav-area">
      <a href="dasbord.php">
        <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24">
          <path fill="currentColor" d="M6 19h3v-6h6v6h3v-9l-6-4.5L6 10zm-2 2V9l8-6l8 6v12h-7v-6h-2v6zm8-8.75"/>
        </svg>
        Dashboard
      </a>
      <a href="produk.php">
        <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24">
          <path fill="currentColor" d="M19 4h-2V3a1 1 0 0 0-2 0v1H9V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3m1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7h16Zm0-9H4V7a1 1 0 0 1 1-1h2v1a1 1 0 0 0 2 0V6h6v1a1 1 0 0 0 2 0V6h2a1 1 0 0 1 1 1Z"/>
        </svg>
        Produk Saya
      </a>
      <a href="pesanan.php" style="position:relative;">
        <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24">
          <path fill="currentColor" d="M19 4h-2V3a1 1 0 0 0-2 0v1H9V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3m1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7h16Zm0-9H4V7a1 1 0 0 1 1-1h2v1a1 1 0 0 0 2 0V6h6v1a1 1 0 0 0 2 0V6h2a1 1 0 0 1 1 1Z"/>
        </svg>
        Pesanan Masuk
<span id="sidebarPesananBadge" style="
    position:absolute;top:50%;right:12px;transform:translateY(-50%);
    background:#ef4444;color:#fff;border-radius:10px;font-size:11px;font-weight:800;
    min-width:20px;height:20px;display:none;
    align-items:center;justify-content:center;
    padding:0 5px;border:2px solid #fff;
    box-shadow:0 2px 8px rgba(239,68,68,.45);line-height:1;">
  </span>
      </a>
      <a href="pendapatan.php" class="active">
        <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 512 512">
          <path fill="currentColor" d="M32 376a56 56 0 0 0 56 56h336a56 56 0 0 0 56-56V222H32Zm66-76a30 30 0 0 1 30-30h48a30 30 0 0 1 30 30v20a30 30 0 0 1-30 30h-48a30 30 0 0 1-30-30ZM424 80H88a56 56 0 0 0-56 56v26h448v-26a56 56 0 0 0-56-56"/>
        </svg>
        Pendapatan
      </a>
    </nav>

    <div class="sidebar-divider"></div>

    <div class="sidebar-bottom">
      <a href="suport.php">
        <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24">
          <path fill="currentColor" d="M22 17.002a6 6 0 0 1-4.713 5.86l-.638-1.914A4 4 0 0 0 19.465 19H17a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h2.938a8.001 8.001 0 0 0-15.876 0H7a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-5C2 6.477 6.477 2 12 2s10 4.477 10 10z"/>
        </svg>
        Support
      </a>
      <a href="pengaturan.php">
        <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24">
          <path fill="currentColor" d="m9.25 22l-.4-3.2q-.325-.125-.612-.3t-.563-.375L4.7 19.375l-2.75-4.75l2.575-1.95Q4.5 12.5 4.5 12.338v-.675q0-.163.025-.338L1.95 9.375l2.75-4.75l2.975 1.25q.275-.2.575-.375t.6-.3l.4-3.2h5.5l.4 3.2q.325.125.613.3t.562.375l2.975-1.25l2.75 4.75l-2.575 1.95q.025.175.025.338v.674q0 .163-.05.338l2.575 1.95l-2.75 4.75l-2.95-1.25q-.275.2-.575.375t-.6.3l-.4 3.2z"/>
        </svg>
        Pengaturan
      </a>
    </div>
  </aside>

  <!-- ── MAIN CONTENT ── -->
  <main class="main">

    <!-- ══ 4 STAT CARDS ══ -->
    <div class="stats-row">

      <!-- 1. Pendapatan Harian QRIS -->
      <div class="stat-card">
        <div class="stat-icon green"><i class="fa-solid fa-qrcode"></i></div>
        <div class="stat-label">Pendapatan Harian (QRIS)</div>
        <div class="stat-value"><?= rupiah($pendapatan_harian_qris) ?></div>
        <div class="stat-badge"><?= date('d M Y') ?></div>
      </div>

      <!-- 2. Pendapatan Harian Cash -->
      <div class="stat-card">
        <div class="stat-icon orange"><i class="fa-solid fa-money-bill-wave"></i></div>
        <div class="stat-label">Pendapatan Harian (Cash)</div>
        <div class="stat-value"><?= rupiah($pendapatan_harian_cash) ?></div>
        <div class="stat-badge"><?= date('d M Y') ?></div>
      </div>

      <!-- 3. Pendapatan Bulanan QRIS -->
      <div class="stat-card">
        <div class="stat-icon blue"><i class="fa-solid fa-calendar-check"></i></div>
        <div class="stat-label">Pendapatan Bulanan (QRIS)</div>
        <div class="stat-value"><?= rupiah($pendapatan_bulanan_qris) ?></div>
        <div class="stat-badge"><?= date('M Y') ?> · <?= $jml_bulan ?> transaksi</div>
      </div>

      <!-- 4. Pendapatan Bulanan Cash -->
      <div class="stat-card">
        <div class="stat-icon purple"><i class="fa-solid fa-coins"></i></div>
        <div class="stat-label">Pendapatan Bulanan (Cash)</div>
        <div class="stat-value"><?= rupiah($pendapatan_bulanan_cash) ?></div>
        <div class="stat-badge"><?= date('M Y') ?></div>
      </div>

    </div>

    <!-- ══ TRANSAKSI TERBARU ══ -->
    <div class="transaksi-card">

      <!-- Header -->
      <div class="trx-header-row">
        <div class="section-title" style="margin-bottom:0">Transaksi Terbaru</div>
        <div style="display:flex;align-items:center;gap:8px">
          <span class="filter-active-badge" id="filterActiveBadge" style="display:none">
            <i class="fa-solid fa-filter"></i> Filter aktif
          </span>
          <div class="dot-menu-wrap">
            <button class="dot-menu-btn" id="dotMenuBtn" onclick="toggleDotMenu()" title="Opsi lainnya" aria-label="Menu opsi">
              <i class="fa-solid fa-ellipsis-vertical"></i>
            </button>
            <div class="dot-menu-dropdown" id="dotMenu">
              <div class="dot-menu-section">
                <div class="dot-menu-label">🖨️ Cetak</div>
                <button class="dot-menu-item" onclick="cetakHariIni()"><i class="fa-solid fa-calendar-day"></i> Cetak Hari Ini</button>
                <button class="dot-menu-item" onclick="cetakFiltered()"><i class="fa-solid fa-filter"></i> Cetak Sesuai Filter</button>
                <button class="dot-menu-item" onclick="cetakSemuanya()"><i class="fa-solid fa-list"></i> Cetak Semua Transaksi</button>
              </div>
              <div class="dot-menu-section">
                <div class="dot-menu-label">⬇️ Download Rekap (CSV)</div>
                <button class="dot-menu-item download-item" onclick="downloadRekap('hari')"><i class="fa-solid fa-file-csv"></i> Rekap Hari Ini</button>
                <button class="dot-menu-item download-item" onclick="downloadRekap('filter')"><i class="fa-solid fa-file-export"></i> Rekap Sesuai Filter</button>
                <button class="dot-menu-item download-item" onclick="downloadRekap('semua')"><i class="fa-solid fa-file-arrow-down"></i> Rekap Semua Transaksi</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Filter pencarian teks + status -->
      <div class="trx-filter-row" style="margin-top:14px">
        <input type="text" id="trxSearch" placeholder="🔍 Cari ID, nama pelanggan, tanggal..."
               oninput="filterTrx()"
               onfocus="this.style.borderColor='var(--orange)'"
               onblur="this.style.borderColor='#ddd'">
        <select id="trxFilter" onchange="filterTrx()">
          <option value="semua">Semua Status</option>
          <option value="selesai">Selesai</option>
          
        </select>
      </div>

      <!-- Filter metode pembayaran -->
      <div class="trx-filter-row" style="margin-top:8px">
        <select id="trxMetodeFilter" onchange="filterTrx()" style="flex:1;max-width:220px">
          <option value="semua">Semua Metode</option>
          <option value="qris">QRIS saja</option>
          <option value="cash">Cash saja</option>
        </select>
        <span style="font-size:12px;color:#94a3b8;display:flex;align-items:center;gap:4px;">
          <i class="fa-solid fa-circle-info"></i> Cash tidak masuk saldo
        </span>
      </div>

      <!-- Filter rentang tanggal -->
      <div class="trx-date-filter-row">
        <div class="date-filter-label">
          <i class="fa-regular fa-calendar-range"></i>
          Rentang Tanggal
        </div>
        <div class="date-filter-inputs">
          <div class="date-input-wrap">
            <span>Dari</span>
            <input type="date" id="trxTglDari" onchange="filterTrx()" title="Tanggal mulai">
          </div>
          <span class="date-sep">—</span>
          <div class="date-input-wrap">
            <span>Sampai</span>
            <input type="date" id="trxTglSampai" onchange="filterTrx()" title="Tanggal akhir">
          </div>
        </div>
        <div class="date-filter-actions">
          <button class="btn-filter-reset" onclick="resetFilterTanggal()" title="Reset filter tanggal">
            <i class="fa-solid fa-xmark"></i> Reset
          </button>
        </div>
      </div>

      <!-- Desktop Table -->
      <div class="trx-table-wrap">
        <table class="trx-table">
          <thead>
            <tr>
              <th>ID Transaksi</th>
              <th>Tanggal</th>
              <th>Order ID</th>
              <th>Pelanggan</th>
              <th>Metode</th>
              <th>Total</th>
              <th>Saldo</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody id="trxBody"></tbody>
        </table>
      </div>

      <!-- Mobile Card List -->
      <div class="trx-mobile-list" id="trxMobileList"></div>

      <div class="pagination" id="paginationWrap"></div>
    </div>

    <!-- Pendapatan Chart + List -->
    <div class="pendapatan-card">
      <div class="pendapatan-header">
        <div>
          <div class="section-title" id="judulPendapatan" style="margin-bottom:4px">Pendapatan QRIS 7 Hari Terakhir</div>
          <div class="period-info" id="periodInfo">
            <i class="fa-regular fa-calendar-check"></i>
            <span id="periodInfoText">—</span>
          </div>
        </div>
        <div class="header-controls">
          <div class="kalender-picker" id="pickerContainer">
            <i class="fa-regular fa-calendar"></i>
            <input type="date" id="datePicker" onchange="onPickerChange()">
          </div>
          <!-- FIX: Filter Semua / QRIS / Cash -->
          <div class="tab-group chart-filter-group" style="margin-right:6px;">
            <button class="tab-btn chart-filter-btn active" onclick="setChartFilter(this,'semua')">Semua</button>
            <button class="tab-btn chart-filter-btn" onclick="setChartFilter(this,'qris')">QRIS</button>
            <button class="tab-btn chart-filter-btn" onclick="setChartFilter(this,'cash')">Cash</button>
          </div>
          <div class="tab-group">
            <button class="tab-btn active" id="tabMinggu" onclick="setTab(this,'Minggu')">Minggu</button>
            <button class="tab-btn"        id="tabBulan"  onclick="setTab(this,'Bulan')">Bulan</button>
            <button class="tab-btn"        id="tabTahun"  onclick="setTab(this,'Tahun')">Tahun</button>
          </div>
        </div>
      </div>
      <div id="barChart" style="display:flex;align-items:flex-end;gap:4px;height:110px;margin-bottom:16px;padding:0 2px"></div>
      <div class="day-rows" id="dayRows"></div>
    </div>

  </main>
</div>

<!-- ── TOAST ── -->
<div class="toast" id="toast"></div>

<script>
// ── Data dari PHP ke JS ──
window.transaksiData = <?= json_encode(array_map(function($t) {
    return [
        'id'          => $t['id_transaksi'],
        'tanggal'     => $t['tanggal'],
        'tanggal_fmt' => $t['tanggal_fmt'],
        'total'       => (float)$t['total'],
        'status'      => $t['status'],
        'metode'      => $t['metode'],
        'produk'      => $t['nama_makanan'],
        'jumlah'      => $t['jumlah'],
        'pembeli'     => $t['nama_pembeli'] ?? 'Anonim',
        'is_qris'     => $t['is_qris'],
    ];
}, $transaksi_list), JSON_UNESCAPED_UNICODE) ?>;

window.namaKantin = <?= json_encode($nama_kantin, JSON_UNESCAPED_UNICODE) ?>;
window.todayWIB   = "<?= date('Y-m-d') ?>";
// FIX: saldoAwal = total QRIS semua waktu (agar loadSaldo() bisa jalan)
window.saldoAwal  = <?= array_sum(array_column(array_filter($transaksi_list, fn($t) => $t['is_qris'] == 1), 'total')) ?>;
</script>

<script>
async function loadNotifChat() {
    try {
        const res = await fetch('../users/chat_api.php?action=jumlah_belum_baca');
        const data = await res.json();
        const badge = document.getElementById('chatBadge');
        if (data.belum_baca > 0) {
            badge.style.display = 'flex';
            badge.textContent = data.belum_baca;
        } else {
            badge.style.display = 'none';
        }
    } catch (err) {
        console.error('Gagal ambil notif chat:', err);
    }
}
loadNotifChat();
setInterval(loadNotifChat, 1000);
</script>
<!-- SheetJS untuk download Excel rapi -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="pendapatan.js"></script>
  

<script>
// ── Badge Pesanan Masuk sidebar (poll tiap 5 detik) ──
function loadSidebarPesananBadge() {
    fetch('pesanan.php?action=get_notif_count')
        .then(r => r.json())
        .then(data => {
            const badge = document.getElementById('sidebarPesananBadge');
            if (!badge) return;
            const pending = data.pending || 0;
            if (pending > 0) {
                badge.textContent   = pending;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        })
        .catch(() => {});
}
loadSidebarPesananBadge();
setInterval(loadSidebarPesananBadge, 5000);
</script>

</body>
</html>