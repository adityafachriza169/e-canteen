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

include "../users/koneksi.php";
$conn = $konek;
$conn->set_charset('utf8mb4');

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

$foto_profil_file = $penjual_data['profil'] ?? '';
$kemungkinan_path = [
    'profil/' . $foto_profil_file,
    '../profil/' . $foto_profil_file,
    '../admin/uploads/' . $foto_profil_file,
];
$foto_profil = 'https://ui-avatars.com/api/?name=' . urlencode($nama_penjual) . '&background=e8650a&color=fff&size=80';
foreach ($kemungkinan_path as $p) {
    if (!empty($foto_profil_file) && file_exists($p)) { $foto_profil = $p; break; }
}

// =============================================
// STATUS PRIORITY
// =============================================
$STATUS_PRIORITY = [
    'pending'  => 0,
    'dimasak'  => 1,
    'siap'     => 2,
    'selesai'  => 3,
    'lunas'    => 4,
    'ditolak'  => 5,
    'batal'    => 6,
];

// =============================================
// AJAX: Update status SEMUA transaksi dalam grup
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action   = $_POST['action'] ?? '';
    $groupIds = json_decode($_POST['group_ids'] ?? '[]', true);

    if (empty($groupIds) && isset($_POST['id_transaksi'])) {
        $groupIds = [(int)$_POST['id_transaksi']];
    }

    $groupIds = array_map('intval', (array)$groupIds);
    $groupIds = array_filter($groupIds);

    $map = [
        'masak'   => 'dimasak',
        'siap'    => 'siap',
        'selesai' => 'lunas',
        'tolak'   => 'ditolak',
    ];

    if (!array_key_exists($action, $map)) {
        echo json_encode(['success' => false, 'message' => 'Aksi tidak valid']);
        exit;
    }

    $new_status = $map[$action];

    $validFrom = [
        'masak'   => ['pending'],
        'siap'    => ['dimasak'],
        'selesai' => ['siap'],
        'tolak'   => ['pending'],
    ];

    $conn->begin_transaction();
    try {
        $updated = 0;
        foreach ($groupIds as $idTr) {
            $check = $conn->prepare("
                SELECT t.id_transaksi, t.status, t.jumlah, t.id_menu
                FROM transaksi t
                JOIN menu_req m ON t.id_menu = m.id
                WHERE t.id_transaksi = ? AND m.id_kantin = ?
            ");
            $check->bind_param("ii", $idTr, $id_kantin);
            $check->execute();
            $row = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$row) continue;
            if (!in_array($row['status'], $validFrom[$action])) continue;

            $upd = $conn->prepare("UPDATE transaksi SET status = ?, is_read = 0 WHERE id_transaksi = ?");
            $upd->bind_param("si", $new_status, $idTr);
            $upd->execute();
            $upd->close();
            $updated++;

            if ($action === 'tolak') {
                $rst = $conn->prepare("UPDATE menu_req SET stok=stok+?, terjual=GREATEST(0,terjual-?) WHERE id=?");
                $rst->bind_param("iii", $row['jumlah'], $row['jumlah'], $row['id_menu']);
                $rst->execute();
                $rst->close();
            }
        }

        $conn->commit();
        echo json_encode(['success' => true, 'new_status' => $new_status, 'updated' => $updated]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// =============================================
// AJAX GET: Notif count
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_notif_count') {
    header('Content-Type: application/json');
    $q = $conn->prepare("
        SELECT
            SUM(CASE WHEN t.status = 'pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN t.status = 'selesai' THEN 1 ELSE 0 END) AS selesai
        FROM transaksi t
        JOIN menu_req m ON t.id_menu = m.id
        WHERE m.id_kantin = ?
    ");
    $q->bind_param("i", $id_kantin);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    echo json_encode([
        'pending' => (int)($row['pending'] ?? 0),
        'selesai' => (int)($row['selesai'] ?? 0),
    ]);
    exit;
}

// =============================================
// AMBIL SEMUA TRANSAKSI
// =============================================
$rawOrders = [];
$sql = $conn->prepare("
    SELECT
        t.id_transaksi,
        t.tanggal,
        t.total,
        t.metode,
        t.nama_makanan,
        t.jumlah,
        t.status,
        t.antrian,
        t.catatan,
        t.id_user,
        u.nama AS nama_user,
        u.username,
        u.pp AS foto_user
    FROM transaksi t
    JOIN users u ON t.id_user = u.id_user
    JOIN menu_req m ON t.id_menu = m.id
    WHERE m.id_kantin = ?
    ORDER BY t.tanggal DESC
");
$sql->bind_param("i", $id_kantin);
$sql->execute();
$result = $sql->get_result();
while ($row = $result->fetch_assoc()) {
    $row['bukti_pembayaran'] = '';
    $rawOrders[] = $row;
}
$sql->close();

// =============================================
// GROUPING LOGIC — Window: 5 menit (300 detik)
// =============================================
$WINDOW = 300;

function buildGroups(array $orders, int $window): array {
    $groups = [];
    foreach ($orders as $o) {
        $ts   = strtotime($o['tanggal']);
        $slot = floor($ts / $window);
        $key  = $o['id_user'] . '_' . $slot;

        if (!isset($groups[$key])) {
            $groups[$key] = [
                'group_key'   => $key,
                'id_user'     => $o['id_user'],
                'nama_user'   => $o['nama_user'],
                'username'    => $o['username'],
                'foto_user'   => $o['foto_user'],
                'tanggal'     => $o['tanggal'],
                'items'       => [],
                'ids'         => [],
                'total_group' => 0,
                'metode'      => $o['metode'],
                'metodes'     => [],
                'antrian'     => $o['antrian'],
            ];
        }
        $groups[$key]['items'][]     = $o;
        $groups[$key]['ids'][]       = $o['id_transaksi'];
        $groups[$key]['total_group'] += (float)$o['total'];

        if (!in_array($o['metode'], $groups[$key]['metodes'])) {
            $groups[$key]['metodes'][] = $o['metode'];
        }
    }
    return array_values($groups);
}

$groups = buildGroups($rawOrders, $WINDOW);

function getGroupStatus(array $group, array $priority): string {
    $minPrio   = PHP_INT_MAX;
    $minStatus = 'lunas';
    foreach ($group['items'] as $item) {
        $p = $priority[$item['status']] ?? 99;
        if ($p < $minPrio) {
            $minPrio   = $p;
            $minStatus = $item['status'];
        }
    }
    return $minStatus;
}

foreach ($groups as &$g) {
    $g['status'] = getGroupStatus($g, $STATUS_PRIORITY);
}
unset($g);

$count = ['pending' => 0, 'dimasak' => 0, 'siap' => 0, 'selesai' => 0, 'lunas' => 0, 'ditolak' => 0];
foreach ($groups as $g) {
    $s = $g['status'];
    if (isset($count[$s])) $count[$s]++;
}
$total_orders = count($groups);
$notif_count  = $count['pending'];

// =============================================
// HELPERS
// =============================================
function getInitials($nama) {
    $parts = explode(' ', trim($nama));
    $init  = '';
    foreach (array_slice($parts, 0, 2) as $p) $init .= strtoupper($p[0]);
    return $init;
}

function timeAgo($datetime) {
    $tz   = new DateTimeZone('Asia/Jakarta');
    $now  = new DateTime('now', $tz);
    $past = new DateTime($datetime, $tz);
    $diff = $now->getTimestamp() - $past->getTimestamp();
    if ($diff < 0)     return 'baru saja';
    if ($diff < 60)    return $diff . ' detik lalu';
    if ($diff < 3600)  return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
    return $past->format('d M Y');
}

$avatarColors = [
    ['#f97316','#dc2626'],
    ['#f59e0b','#f97316'],
    ['#3b82f6','#6366f1'],
    ['#ec4899','#f97316'],
    ['#10b981','#3b82f6'],
];

$statusMap = [
    'pending'  => ['label'=>'Menunggu',     'class'=>'badge-menunggu', 'icon'=>'fa-regular fa-clock',      'card'=>'status-menunggu'],
    'dimasak'  => ['label'=>'Dimasak',      'class'=>'badge-dimasak',  'icon'=>'fa-solid fa-fire-burner',  'card'=>'status-dimasak'],
    'siap'     => ['label'=>'Siap Diambil', 'class'=>'badge-siap',     'icon'=>'fa-solid fa-bell',         'card'=>'status-siap'],
    'selesai'  => ['label'=>'Selesai',      'class'=>'badge-lunas',    'icon'=>'fa-solid fa-circle-check', 'card'=>'status-lunas'],
    'lunas'    => ['label'=>'Selesai',      'class'=>'badge-lunas',    'icon'=>'fa-solid fa-check-double', 'card'=>'status-lunas'],
    'ditolak'  => ['label'=>'Ditolak',      'class'=>'badge-tolak',    'icon'=>'fa-solid fa-xmark',        'card'=>'status-tolak'],
    'batal'    => ['label'=>'Dibatalkan',   'class'=>'badge-tolak',    'icon'=>'fa-solid fa-ban',          'card'=>'status-tolak'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Pesanan Masuk — E-Canteen</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Righteous&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

    :root {
      --orange:             #E8714A;
      --orange-light:       #F5A87B;
      --orange-dark:        #C84B11;
      --orange-vivid:       #E8640A;
      --sidebar-bg:         #FDE8DC;
      --sidebar-active-bg:  #FFDABF;
      --sidebar-active-text:#E1715D;
      --sidebar-text:       #E8714A;
      --cream:              #FDF6EE;
      --brown-dark:         #3A1A00;
      --topbar-h: 36px;
      --navbar-h: 90px;
      --header-h: 126px;
    }

    a { text-decoration:none; color:inherit; }
    body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--cream); color:var(--brown-dark); overflow-x:hidden; }

    .orange-bg {
      background:
        radial-gradient(ellipse 55% 130% at 85% 40%, rgba(255,220,100,.28) 0%, transparent 55%),
        radial-gradient(ellipse 45% 110% at 5% 60%, rgba(200,90,0,.3) 0%, transparent 60%),
        linear-gradient(112deg, #d45500 0%, #e86510 22%, #f57c1a 45%, #f98e20 65%, #fcaa38 82%, #ffc55a 100%);
    }

    /* ── TOPBAR ── */
    .topbar { background:#b84a00; padding:0 52px; height:var(--topbar-h); display:flex; align-items:center; justify-content:space-between; font-size:12px; color:rgba(255,255,255,.8); position:fixed; top:0; left:0; width:100%; z-index:1000; }
    .topbar span { color:rgba(255,255,255,.6); }

    /* ── NAVBAR ── */
    .navbar { padding:0 52px; height:var(--navbar-h); display:flex; align-items:center; gap:24px; position:fixed; top:var(--topbar-h); left:0; width:100%; overflow:hidden; z-index:999; }
    .navbar::after { content:''; position:absolute; bottom:0; left:0; right:0; height:2.5px; background:linear-gradient(90deg,transparent,rgba(255,200,80,.6) 25%,rgba(255,250,200,.9) 50%,rgba(255,200,80,.6) 75%,transparent); }
    .logo { display:flex; align-items:center; gap:11px; flex-shrink:0; position:relative; z-index:1; }
    .logo img { width:60px; filter:drop-shadow(0 3px 10px rgba(0,0,0,.3)); }
    .logo-title { font-family:'Righteous',cursive; font-size:22px; color:#fff; line-height:1.1; }
    .logo-sub { font-size:10px; color:rgba(255,240,200,.85); letter-spacing:2px; text-transform:uppercase; font-weight:600; }
    .navbar-right { margin-left:auto; display:flex; align-items:center; gap:14px; position:relative; z-index:1; }
    .hamburger-btn { display:none; width:40px; height:40px; border-radius:10px; background:rgba(255,255,255,.18); border:1.5px solid rgba(255,255,255,.3); color:#fff; font-size:18px; cursor:pointer; align-items:center; justify-content:center; }

    /* ── NOTIF ── */
    .navbar-notif-wrap { margin-left:auto; position:relative; z-index:1; display:flex; align-items:center; gap:10px; }
    .notif-bell-btn { position:relative; width:42px; height:42px; background:rgba(255,255,255,.18); border:1.5px solid rgba(255,255,255,.3); border-radius:12px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:18px; cursor:pointer; transition:.2s; text-decoration:none; }
    .notif-bell-btn:hover { background:rgba(255,255,255,.3); }
    .notif-bell-btn.has-notif { animation: bellShake 1.2s ease-in-out infinite; }
    @keyframes bellShake { 0%,100%{transform:rotate(0)} 15%{transform:rotate(12deg)} 30%{transform:rotate(-10deg)} 45%{transform:rotate(8deg)} 60%{transform:rotate(-6deg)} 75%{transform:rotate(4deg)} }
    .notif-count-badge { position:absolute; top:-7px; right:-7px; min-width:20px; height:20px; background:#ef4444; color:#fff; border-radius:10px; font-size:11px; font-weight:800; display:flex; align-items:center; justify-content:center; padding:0 5px; border:2px solid #fff; box-shadow:0 2px 8px rgba(239,68,68,.5); line-height:1; }
    .notif-tooltip { position:absolute; top:calc(100% + 10px); right:0; background:#1e293b; color:#fff; font-size:12px; font-weight:600; padding:7px 12px; border-radius:8px; white-space:nowrap; pointer-events:none; opacity:0; transform:translateY(-4px); transition:.2s; box-shadow:0 4px 16px rgba(0,0,0,.25); }
    .notif-tooltip::before { content:''; position:absolute; top:-5px; right:12px; border-left:5px solid transparent; border-right:5px solid transparent; border-bottom:5px solid #1e293b; }
    .notif-bell-btn:hover .notif-tooltip { opacity:1; transform:translateY(0); }
    .icon-actions { display:flex; align-items:center; gap:10px; flex-shrink:0; }
    .icon-btn { position:relative; width:42px; height:42px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:21px; cursor:pointer; border-radius:12px; background:rgba(255,255,255,.15); border:1.5px solid rgba(255,255,255,.3); transition:all .22s; }
    .icon-btn:hover { background:rgba(255,255,255,.28); transform:translateY(-2px); }
    .icon-badge { position:absolute; top:-6px; right:-6px; min-width:20px; height:20px; background:#ef4444; color:#fff; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:10.5px; font-weight:700; padding:0 5px; border:1.5px solid rgba(255,150,30,.2); }

    /* ── PAGE ── */
    .page-wrapper { display:flex; padding-top:var(--header-h); min-height:100vh; }

    /* ── SIDEBAR ── */
    .sidebar { width:240px; min-width:220px; background:var(--sidebar-bg); padding:28px 0 20px; position:fixed; top:var(--header-h); left:0; height:calc(100vh - var(--header-h)); overflow-y:auto; display:flex; flex-direction:column; box-shadow:4px 0 24px rgba(200,75,17,.07); z-index:100; }
    .sidebar-profile { padding:0 20px 20px; border-bottom:1px solid rgba(232,113,74,.15); margin-bottom:12px; display:flex; align-items:center; gap:14px; }
    .sidebar-avatar { width:52px; height:52px; border-radius:50%; background:linear-gradient(135deg,#e8650a,#f97316); display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:800; color:#fff; flex-shrink:0; overflow:hidden; }
    .sidebar-avatar img { width:100%; height:100%; object-fit:cover; }
    .sidebar-profile-info h3 { font-size:14px; font-weight:700; color:#3a1a00; }
    .sidebar-profile-info a { font-size:12px; color:var(--orange); display:flex; align-items:center; gap:4px; margin-top:3px; }
    .sidebar-nav-area { padding:0 12px; display:flex; flex-direction:column; gap:2px; }
    .sidebar-nav-area a { display:flex; align-items:center; gap:12px; padding:12px 14px; border-radius:12px; font-size:14px; font-weight:600; color:var(--sidebar-text); transition:.2s; }
    .sidebar-nav-area a:hover { background:rgba(232,113,74,.12); }
    .sidebar-nav-area a.active { background:var(--sidebar-active-bg); color:var(--sidebar-active-text); }
    .sidebar-divider { height:1px; background:rgba(232,113,74,.15); margin:14px 20px; }
    .sidebar-bottom { padding:0 12px; display:flex; flex-direction:column; gap:2px; }
    .sidebar-bottom a { display:flex; align-items:center; gap:12px; padding:11px 14px; border-radius:12px; font-size:14px; font-weight:600; color:var(--sidebar-text); transition:.2s; }
    .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:99; backdrop-filter:blur(2px); }
    .sidebar-overlay.show { display:block; }

    /* ── MAIN ── */
    .main { flex:1; margin-left:240px; padding:28px 32px; display:flex; flex-direction:column; gap:22px; }

    /* ── SUMMARY ── */
    .summary-strip { display:flex; gap:12px; flex-wrap:wrap; }
    .summary-card { flex:1; min-width:130px; background:#fff; border-radius:14px; padding:14px 18px; display:flex; align-items:center; gap:14px; box-shadow:0 2px 12px rgba(200,75,17,.08); border:1px solid rgba(232,113,74,.1); }
    .summary-icon { width:40px; height:40px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:17px; flex-shrink:0; }
    .summary-info .num { font-size:1.35rem; font-weight:800; color:#1e293b; }
    .summary-info .lbl { font-size:11px; color:#94a3b8; font-weight:600; }

    /* ── TAB BAR ── */
    .tab-bar { display:flex; gap:8px; flex-wrap:wrap; }
    .tab { padding:9px 18px; border-radius:50px; font-size:13px; font-weight:700; cursor:pointer; border:1.5px solid rgba(232,113,74,.25); background:#fff; color:#a07050; transition:.2s; display:flex; align-items:center; gap:6px; user-select:none; }
    .tab:hover { border-color:var(--orange); color:var(--orange); }
    .tab.active { background:var(--orange); color:#fff; border-color:var(--orange); }
    .tab-badge { background:#fff; color:var(--orange); border-radius:50px; font-size:11px; font-weight:800; padding:1px 7px; min-width:20px; text-align:center; }
    .tab.active .tab-badge { background:rgba(255,255,255,.3); color:#fff; }

    /* ── SEARCH ── */
    .search-bar { background:#fff; border:1.5px solid rgba(232,113,74,.2); border-radius:14px; padding:12px 18px; display:flex; align-items:center; gap:10px; box-shadow:0 2px 8px rgba(200,75,17,.06); }
    .search-bar i { color:#94a3b8; }
    .search-bar input { flex:1; border:none; outline:none; font-size:14px; font-family:'Plus Jakarta Sans',sans-serif; color:#1e293b; background:transparent; }
    .search-bar input::placeholder { color:#94a3b8; }

    /* ── ORDER CARD ── */
    .orders-list { display:flex; flex-direction:column; gap:14px; }
    .order-card { background:#fff; border-radius:16px; padding:16px 20px; box-shadow:0 2px 12px rgba(200,75,17,.07); border:1.5px solid rgba(232,113,74,.1); border-left:4px solid #e8714a; transition:.25s; cursor:pointer; }
    .order-card:hover { box-shadow:0 6px 24px rgba(200,75,17,.13); transform:translateY(-1px); }
    .status-menunggu { border-left-color:#f97316; }
    .status-dimasak  { border-left-color:#f59e0b; }
    .status-siap     { border-left-color:#3b82f6; }
    .status-lunas    { border-left-color:#22c55e; }
    .status-tolak    { border-left-color:#ef4444; }

    .order-top { display:flex; align-items:flex-start; gap:14px; margin-bottom:12px; }
    .order-avatar { width:48px; height:48px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:16px; font-weight:800; color:#fff; flex-shrink:0; position:relative; overflow:hidden; }
    .order-id-badge { position:absolute; bottom:0; left:50%; transform:translateX(-50%); background:#fff; border:1.5px solid rgba(232,113,74,.3); color:#e8650a; font-size:9px; font-weight:800; padding:1px 6px; border-radius:50px; white-space:nowrap; }
    .order-info { flex:1; }
    .order-name { font-size:15px; font-weight:700; color:#1e293b; }
    .order-time { font-size:12px; color:#94a3b8; margin-top:2px; display:flex; align-items:center; gap:4px; }
    .order-meta { text-align:right; flex-shrink:0; }
    .order-price { font-size:16px; font-weight:800; color:#e8650a; display:block; margin-top:6px; }

    /* ── ITEM LIST di kartu ── */
    .order-items-list { display:flex; flex-direction:column; gap:5px; margin-bottom:10px; }
    .order-item-row { display:flex; align-items:center; gap:8px; background:#fff8f3; border-radius:8px; padding:6px 10px; font-size:12.5px; }
    .order-item-row .qty { font-weight:800; color:#e8650a; min-width:22px; }
    .order-item-row .name { flex:1; font-weight:600; color:#1e293b; }
    .order-item-row .price { color:#64748b; font-weight:600; }

    .order-tags { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:10px; }
    .item-tag { background:#fff5ee; color:#c84b11; font-size:12px; font-weight:600; padding:4px 10px; border-radius:50px; display:flex; align-items:center; gap:5px; }

    /* ── ACTION BUTTONS ── */
    .order-actions { display:flex; align-items:center; gap:10px; padding-top:12px; border-top:1px solid rgba(232,113,74,.1); flex-wrap:wrap; }
    .btn { padding:9px 18px; border-radius:10px; font-size:13px; font-weight:700; cursor:pointer; border:none; display:flex; align-items:center; gap:6px; transition:.2s; font-family:'Plus Jakarta Sans',sans-serif; }
    .btn:hover { filter:brightness(1.1); }
    .btn:disabled { opacity:.6; cursor:not-allowed; }
    .btn-masak   { background:linear-gradient(135deg,#f97316,#ea580c); color:#fff; }
    .btn-tolak   { padding:9px 18px; border-radius:10px; font-size:13px; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:6px; transition:.2s; font-family:'Plus Jakarta Sans',sans-serif; background:#fef2f2; color:#ef4444; border:1.5px solid #fecaca; }
    .btn-siap    { padding:9px 18px; border-radius:10px; font-size:13px; font-weight:700; cursor:pointer; border:none; display:flex; align-items:center; gap:6px; transition:.2s; font-family:'Plus Jakarta Sans',sans-serif; background:linear-gradient(135deg,#3b82f6,#2563eb); color:#fff; }
    .btn-siap:hover { filter:brightness(1.1); }
    .btn-selesai { background:linear-gradient(135deg,#22c55e,#16a34a); color:#fff; }

    /* ── TOMBOL ANTRIAN INLINE ── */
    .btn-antrian-inline {
      display:inline-flex; align-items:center; gap:7px;
      padding:8px 14px; border-radius:10px;
      border:2px dashed #f97316; background:#fff7ed;
      font-size:13px; font-weight:700; color:#c2410c;
      cursor:pointer; font-family:'Plus Jakarta Sans',sans-serif;
      transition:.2s; flex-shrink:0;
    }
    .btn-antrian-inline:hover { background:#fed7aa; border-color:#ea580c; }
    .btn-antrian-inline .ant-num {
      background:#f97316; color:#fff; border-radius:6px;
      font-size:11px; font-weight:800; padding:1px 8px; line-height:1.6;
    }

    /* ── BADGE ── */
    .status-badge { display:inline-flex; align-items:center; gap:5px; padding:5px 12px; border-radius:50px; font-size:12px; font-weight:700; }
    .badge-menunggu { background:#fff7ed; color:#c2410c; }
    .badge-dimasak  { background:#fffbeb; color:#b45309; }
    .badge-siap     { background:#eff6ff; color:#1d4ed8; }
    .badge-lunas    { background:#f0fdf4; color:#15803d; }
    .badge-tolak    { background:#fef2f2; color:#b91c1c; }

    /* ── EMPTY STATE ── */
    .empty-state { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:60px 20px; color:#94a3b8; gap:12px; }
    .empty-state i { font-size:3rem; }
    .empty-state .title { font-size:16px; font-weight:700; color:#64748b; }
    .empty-state .sub   { font-size:13px; }

    /* ── MODAL DETAIL ── */
    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:2000; align-items:center; justify-content:center; backdrop-filter:blur(4px); }
    .modal-overlay.open { display:flex; }
    .detail-modal-box { background:#fff; border-radius:20px; padding:0; width:92%; max-width:560px; box-shadow:0 20px 60px rgba(0,0,0,.22); position:relative; overflow:hidden; max-height:90vh; display:flex; flex-direction:column; }
    .detail-modal-header { padding:20px 24px 16px; border-bottom:1px solid #f1f5f9; flex-shrink:0; }
    .detail-modal-header h3 { font-size:17px; font-weight:800; color:#1e293b; display:flex; align-items:center; gap:8px; }
    .detail-modal-header .modal-sub { font-size:12px; color:#94a3b8; margin-top:3px; }
    .detail-modal-body { padding:20px 24px; overflow-y:auto; flex:1; display:flex; flex-direction:column; gap:14px; }
    .detail-close-btn { position:absolute; top:16px; right:16px; background:#f1f5f9; border:none; width:32px; height:32px; border-radius:50%; cursor:pointer; color:#64748b; font-size:15px; display:flex; align-items:center; justify-content:center; transition:.2s; z-index:1; }
    .detail-close-btn:hover { background:#e2e8f0; color:#1e293b; }

    /* user card di modal */
    .detail-user-card { display:flex; align-items:center; gap:14px; background:linear-gradient(135deg,#fff7ed,#fef3e2); border:1px solid rgba(232,113,74,.2); border-radius:14px; padding:14px 16px; }
    .detail-user-avatar { width:52px; height:52px; border-radius:50%; overflow:hidden; flex-shrink:0; background:linear-gradient(135deg,#f97316,#ea580c); display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:800; color:#fff; border:3px solid #fff; box-shadow:0 4px 12px rgba(232,113,74,.25); }
    .detail-user-avatar img { width:100%; height:100%; object-fit:cover; }
    .detail-user-name { font-size:15px; font-weight:800; color:#1e293b; }
    .detail-user-username { font-size:12px; color:#94a3b8; margin-top:2px; }

    /* items table di modal */
    .modal-items-section { background:#f8fafc; border-radius:12px; overflow:hidden; border:1px solid #e2e8f0; }
    .modal-items-header { padding:10px 14px; background:#f1f5f9; font-size:11px; font-weight:800; color:#475569; letter-spacing:.6px; text-transform:uppercase; display:flex; justify-content:space-between; }
    .modal-item-row { display:flex; align-items:flex-start; gap:10px; padding:10px 14px; border-bottom:1px solid #e2e8f0; }
    .modal-item-row:last-child { border-bottom:none; }
    .modal-item-num { width:24px; height:24px; background:linear-gradient(135deg,#f97316,#ea580c); color:#fff; border-radius:7px; font-size:11px; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:1px; }
    .modal-item-detail { flex:1; }
    .modal-item-name { font-size:13.5px; font-weight:700; color:#1e293b; }
    .modal-item-qty  { font-size:12px; color:#64748b; margin-top:2px; }
    .modal-item-note { font-size:11px; color:#92400e; background:#fffbeb; border:1px solid #fef3c7; border-radius:5px; padding:3px 8px; margin-top:5px; display:inline-flex; align-items:center; gap:4px; }
    .modal-item-price { font-size:14px; font-weight:800; color:#e8650a; flex-shrink:0; }

    /* total baris di modal */
    .modal-total-row { display:flex; justify-content:space-between; align-items:center; padding:12px 14px; background:#fff7ed; border:1px solid rgba(232,113,74,.2); border-radius:10px; }
    .modal-total-row .lbl { font-size:13px; color:#6b7280; font-weight:600; }
    .modal-total-row .val { font-size:17px; font-weight:800; color:#e8650a; }

    /* info rows */
    .modal-info-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
    .modal-info-cell { background:#f8fafc; border-radius:10px; padding:10px 12px; }
    .modal-info-cell .lbl { font-size:10px; color:#94a3b8; font-weight:700; text-transform:uppercase; letter-spacing:.5px; }
    .modal-info-cell .val { font-size:13px; font-weight:700; color:#1e293b; margin-top:3px; }

    .modal-footer { padding:16px 24px; border-top:1px solid #f1f5f9; flex-shrink:0; }
    .modal-cancel-btn { width:100%; padding:11px; border-radius:10px; border:1.5px solid #e2e8f0; background:#fff; font-size:14px; font-weight:700; color:#64748b; cursor:pointer; transition:.2s; font-family:'Plus Jakarta Sans',sans-serif; }
    .modal-cancel-btn:hover { background:#f8fafc; }

    /* ── CHIP METODE BAYAR ── */
    .metode-chip { display:inline-flex; align-items:center; gap:7px; padding:7px 14px; border-radius:10px; font-size:13px; font-weight:700; }
    .metode-chip.cash { background:#f0fdf4; color:#15803d; border:1.5px solid #bbf7d0; }
    .metode-chip.qris { background:#f5f3ff; color:#6d28d9; border:1.5px solid #ddd6fe; }
    .metode-chip.cash i { color:#16a34a; }
    .metode-chip.qris i { color:#7c3aed; }

    /* ── CATATAN SECTION DI MODAL ── */
    .modal-catatan-section { background:linear-gradient(135deg,#fffbeb,#fef9c3); border:2px solid #fde68a; border-radius:14px; overflow:hidden; box-shadow:0 2px 8px rgba(234,179,8,.12); }
    .modal-catatan-header { background:linear-gradient(90deg,#fef08a,#fef9c3); padding:10px 16px; font-size:11px; font-weight:800; color:#92400e; letter-spacing:.6px; text-transform:uppercase; display:flex; align-items:center; gap:8px; border-bottom:1.5px solid #fde68a; }
    .modal-catatan-header i { font-size:14px; color:#f59e0b; }
    .modal-catatan-row { display:flex; align-items:flex-start; gap:10px; padding:12px 16px; border-bottom:1px solid #fef3c7; }
    .modal-catatan-row:last-child { border-bottom:none; }
    .modal-catatan-menu-chip { display:inline-flex; align-items:center; background:#fff; border:1.5px solid #fde68a; color:#b45309; font-size:11px; font-weight:700; padding:2px 9px; border-radius:50px; white-space:nowrap; flex-shrink:0; margin-top:1px; }
    .modal-no-catatan { display:flex; align-items:center; gap:8px; background:#f8fafc; border:1.5px dashed #e2e8f0; border-radius:10px; padding:12px 14px; font-size:12.5px; color:#94a3b8; font-weight:600; }

    /* ── TOAST ── */
    .toast { position:fixed; bottom:24px; right:24px; background:#1e293b; color:#fff; padding:12px 20px; border-radius:12px; font-size:14px; font-weight:600; z-index:9999; transform:translateY(80px); opacity:0; transition:.3s; box-shadow:0 8px 24px rgba(0,0,0,.2); }
    .toast.show { transform:translateY(0); opacity:1; }
    .toast.success { background:#16a34a; }
    .toast.error   { background:#dc2626; }

    /* ── POPUP ANTRIAN ── */
    .antrian-popup-overlay {
      display:none; position:fixed; inset:0; background:rgba(0,0,0,.55);
      z-index:3000; align-items:center; justify-content:center;
      backdrop-filter:blur(6px); padding:20px;
    }
    .antrian-popup-overlay.open { display:flex; }
    .antrian-popup-box {
      background:#fff8f3; border-radius:24px; width:92%; max-width:420px;
      box-shadow:0 24px 60px rgba(200,75,17,.25); overflow:hidden;
      max-height:90vh; display:flex; flex-direction:column;
    }
    .antrian-popup-header {
      background:linear-gradient(112deg,#d45500,#f98e20);
      padding:18px 20px 16px; display:flex; align-items:center; gap:12px; flex-shrink:0;
    }
    .antrian-popup-header-icon {
      width:40px; height:40px; border-radius:12px; background:rgba(255,255,255,.2);
      display:flex; align-items:center; justify-content:center; flex-shrink:0;
    }
    .antrian-popup-header-icon i { color:#fff; font-size:18px; }
    .antrian-popup-title { font-size:16px; font-weight:800; color:#fff; }
    .antrian-popup-subtitle { font-size:12px; color:rgba(255,255,255,.8); margin-top:2px; }
    .antrian-popup-close {
      margin-left:auto; width:32px; height:32px; border-radius:50%;
      background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px;
      cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0;
    }
    .antrian-popup-body {
      padding:16px; overflow-y:auto; flex:1;
      display:flex; flex-direction:column; gap:12px;
    }
    .antrian-card {
      background:#fff; border-radius:16px; border:2px solid rgba(232,113,74,.2);
      padding:20px; text-align:center; position:relative; overflow:hidden;
    }
    .antrian-card-topbar {
      position:absolute; top:0; left:0; right:0; height:4px;
      background:linear-gradient(90deg,#f97316,#ea580c); border-radius:0;
    }
    .antrian-card-item-label {
      font-size:10px; font-weight:800; color:#94a3b8; letter-spacing:1px;
      text-transform:uppercase; margin-bottom:6px;
    }
    .antrian-card-nomor-label {
      font-size:11px; font-weight:700; color:#94a3b8; letter-spacing:.5px;
      text-transform:uppercase; margin-bottom:4px;
    }
    .antrian-card-nomor {
      font-size:56px; font-weight:900; color:#e8650a; line-height:1;
      font-family:'Righteous',cursive,sans-serif;
    }
    .antrian-card-divider {
      height:1px; background:rgba(232,113,74,.15); margin:14px 0;
    }
    .antrian-card-kode-label {
      font-size:10px; font-weight:700; color:#94a3b8; letter-spacing:.5px;
      text-transform:uppercase; margin-bottom:8px;
    }
    .antrian-kode-box {
      display:inline-flex; align-items:center; gap:8px;
      background:#fff7ed; border:2px dashed #f97316;
      border-radius:10px; padding:9px 16px; cursor:pointer;
      transition:.2s;
    }
    .antrian-kode-box:hover { background:#fed7aa; }
    .antrian-kode-box i.ticket-icon { color:#ea580c; font-size:14px; }
    .antrian-kode-text {
      font-family:monospace; font-size:14px; font-weight:800;
      color:#c2410c; letter-spacing:.5px;
    }
    .antrian-kode-copy-icon { color:#f97316; font-size:13px; }
    .antrian-kode-hint { font-size:11px; color:#94a3b8; margin-top:8px; }

    /* Grid multi antrian */
    .antrian-multi-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .antrian-multi-grid .antrian-card-nomor { font-size:42px; }
    .antrian-multi-grid .antrian-kode-text { font-size:11px; letter-spacing:.2px; }
    .antrian-multi-grid .antrian-kode-box { padding:7px 10px; gap:5px; }

    .antrian-popup-footer {
      padding:14px 16px; border-top:1px solid rgba(232,113,74,.15); flex-shrink:0;
    }
    .antrian-popup-footer button {
      width:100%; padding:11px; border-radius:12px;
      border:1.5px solid rgba(232,113,74,.3); background:#fff;
      font-size:14px; font-weight:700; color:#e8650a; cursor:pointer;
      font-family:'Plus Jakarta Sans',sans-serif; transition:.2s;
    }
    .antrian-popup-footer button:hover { background:#fff7ed; }

    /* ── TOMBOL ANTRIAN DI MODAL DETAIL ── */
    .antrian-trigger-btn {
      display:flex; align-items:center; gap:10px; padding:10px 14px;
      border-radius:12px; border:2px dashed #f97316; background:#fff7ed;
      cursor:pointer; width:100%; font-family:'Plus Jakarta Sans',sans-serif;
      transition:.2s; text-align:left;
    }
    .antrian-trigger-btn:hover { background:#fed7aa; border-color:#ea580c; }
    .antrian-trigger-icon {
      width:38px; height:38px; border-radius:10px;
      background:linear-gradient(135deg,#f97316,#ea580c);
      display:flex; align-items:center; justify-content:center; flex-shrink:0;
    }
    .antrian-trigger-icon i { color:#fff; font-size:16px; }
    .antrian-trigger-main { font-size:14px; font-weight:800; color:#c2410c; display:block; }
    .antrian-trigger-sub  { font-size:11px; color:#ea580c; display:block; margin-top:2px; }

    /* ── RESPONSIVE ── */
    @media (max-width:768px) {
      .topbar { padding:0 16px; }
      .navbar  { padding:0 16px; }
      .hamburger-btn { display:flex !important; }
      .sidebar { left:-260px; transition:.3s; }
      .sidebar.open { left:0; }
      .main { margin-left:0; padding:16px; }
      .summary-strip { gap:8px; }
      .summary-card { min-width:calc(50% - 4px); }
      .modal-info-grid { grid-template-columns:1fr; }
      .antrian-card-nomor { font-size:44px; }
      .antrian-multi-grid { grid-template-columns:1fr 1fr; gap:8px; }
      .antrian-multi-grid .antrian-card-nomor { font-size:36px; }
    }
  </style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
  <span><?= htmlspecialchars($nama_kantin) ?> · Panel Penjual</span>
  <span><?= date('d M Y, H:i') ?> WIB</span>
</div>

<!-- NAVBAR -->
<div class="navbar orange-bg">
  <button class="hamburger-btn" id="hamburgerBtn"><i class="fa-solid fa-bars"></i></button>
  <div class="logo">
    <img src="../gambar/logo.png" alt="E-Canteen">
    <div>
      <div class="logo-title">E-Canteen</div>
      <div class="logo-sub">Esemkita</div>
    </div>
  </div>
  <div class="navbar-notif-wrap">
    <div class="notif-bell-btn <?= $notif_count > 0 ? 'has-notif' : '' ?>" id="notifBellBtn"
         onclick="filterOrders('pending', document.querySelector('.tab:nth-child(2)'))">
      <i class="fa-solid fa-bell"></i>
      <span class="notif-count-badge" id="notifBadge" <?= $notif_count === 0 ? 'style="display:none"' : '' ?>><?= $notif_count ?></span>
      <div class="notif-tooltip" id="notifTooltip">
        <?= $notif_count > 0 ? $notif_count . ' pesanan perlu dikonfirmasi' : 'Tidak ada pesanan baru' ?>
      </div>
    </div>
    <div class="icon-actions">
      <button onclick="openChatPopup()" class="icon-btn">
        <i class="fa-solid fa-comment-dots"></i>
        <span class="icon-badge" id="chatBadge" style="display:none"></span>
      </button>
    </div>
  </div>
</div>

<!-- SIDEBAR OVERLAY -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
  <div class="sidebar-profile">
    <div class="sidebar-avatar">
      <img src="<?= htmlspecialchars($foto_profil) ?>"
           onerror="this.style.display='none';this.parentNode.textContent='<?= getInitials($nama_penjual) ?>'"
           alt="<?= htmlspecialchars($nama_penjual) ?>">
    </div>
    <div class="sidebar-profile-info">
      <h3><?= htmlspecialchars($nama_penjual) ?></h3>
      <small style="color:#aaa;font-size:.75rem;"><?= htmlspecialchars($nama_kantin) ?></small><br>
      <a href="profil/profile.php">✏️ Ubah Profil</a>
    </div>
  </div>
  <nav class="sidebar-nav-area">
    <a href="dasbord.php">
      <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24"><path fill="currentColor" d="M6 19h3v-6h6v6h3v-9l-6-4.5L6 10zm-2 2V9l8-6l8 6v12h-7v-6h-2v6z"/></svg>
      Dashboard
    </a>
    <a href="produk.php">
      <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24"><path fill="currentColor" d="M19.78 12.08v4.5H4.26v-4.51H4a6.4 6.4 0 0 1-3-.73v10.24a2 2 0 0 0 2 2h18a2 2 0 0 0 2-2V11.35a6.3 6.3 0 0 1-3 .73Zm-11 9.5a.75.75 0 0 1-1.5 0v-3a.75.75 0 0 1 1.5 0Zm8 0a.75.75 0 0 1-1.5 0v-3a.75.75 0 0 1 1.5 0Zm-3.04-21h-3.45L9.23 4.83h5.58zm9.51 4.25l-1.14-3.56a1.09 1.09 0 0 0-1-.69h-5.82l1.06 4.25Zm.48 1.5H.29L0 7.21a3 3 0 0 0 1.15 2.44l.12.1l.31.19l.13.07l.17.08H2l.16.07l.14.05l.18.06H4.2a4.13 4.13 0 0 0 3.21-1.19a.74.74 0 0 1 1.19 0c1.49 2 5.33 2 6.81 0a.74.74 0 0 1 1.19 0a4.17 4.17 0 0 0 3.24 1.5h.82a2.5 2.5 0 0 0 .38-.06h.15l.37-.11l.15-.05l.35-.16l.15-.08l.32-.2l.14-.1A3 3 0 0 0 24 7.22ZM8.74.58h-5.8a1.1 1.1 0 0 0-1 .69L.78 4.83h6.9Z"/></svg>
      Produk Saya
    </a>
    <a href="pesanan.php" style="position:relative;" class="active">
      <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24"><path fill="currentColor" d="M19 4h-2V3a1 1 0 0 0-2 0v1H9V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3m1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7h16Zm0-9H4V7a1 1 0 0 1 1-1h2v1a1 1 0 0 0 2 0V6h6v1a1 1 0 0 0 2 0V6h2a1 1 0 0 1 1 1Z"/></svg>
      Pesanan Masuk
      <span id="sidebarPesananBadge" style="position:absolute;top:50%;right:12px;transform:translateY(-50%);background:#ef4444;color:#fff;border-radius:10px;font-size:11px;font-weight:800;min-width:20px;height:20px;display:none;align-items:center;justify-content:center;padding:0 5px;border:2px solid #fff;"></span>
    </a>
    <a href="pendapatan.php">
      <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 512 512"><path fill="currentColor" d="M32 376a56 56 0 0 0 56 56h336a56 56 0 0 0 56-56V222H32Zm66-76a30 30 0 0 1 30-30h48a30 30 0 0 1 30 30v20a30 30 0 0 1-30 30h-48a30 30 0 0 1-30-30ZM424 80H88a56 56 0 0 0-56 56v26h448v-26a56 56 0 0 0-56-56"/></svg>
      Pendapatan
    </a>
  </nav>
  <div class="sidebar-divider"></div>
  <div class="sidebar-bottom">
    <a href="suport.php">
      <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24"><path fill="currentColor" d="M22 17.002a6 6 0 0 1-4.713 5.86l-.638-1.914A4 4 0 0 0 19.465 19H17a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h2.938a8.001 8.001 0 0 0-15.876 0H7a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-5C2 6.477 6.477 2 12 2s10 4.477 10 10z"/></svg>
      Support
    </a>
    <a href="pengaturan.php">
      <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24"><path fill="currentColor" d="m9.25 22l-.4-3.2q-.325-.125-.612-.3t-.563-.375L4.7 19.375l-2.75-4.75l2.575-1.95Q4.5 12.5 4.5 12.338v-.675q0-.163.025-.338L1.95 9.375l2.75-4.75l2.975 1.25q.275-.2.575-.375t.6-.3l.4-3.2h5.5l.4 3.2q.325.125.613.3t.562.375l2.975-1.25l2.75 4.75l-2.575 1.95q.025.175.025.338v.674q0 .163-.05.338l2.575 1.95l-2.75 4.75l-2.95-1.25q-.275.2-.575.375t-.6.3l-.4 3.2z"/></svg>
      Pengaturan
    </a>
  </div>
</div>

<!-- PAGE WRAPPER -->
<div class="page-wrapper">
  <main class="main">

    <!-- SUMMARY -->
    <div class="summary-strip">
      <div class="summary-card">
        <div class="summary-icon" style="background:#fff7ed;color:#ea580c"><i class="fa-regular fa-clock"></i></div>
        <div class="summary-info">
          <div class="num"><?= $count['pending'] ?></div>
          <div class="lbl">Menunggu</div>
        </div>
      </div>
      <div class="summary-card">
        <div class="summary-icon" style="background:#fffbeb;color:#b45309"><i class="fa-solid fa-fire-burner"></i></div>
        <div class="summary-info">
          <div class="num"><?= $count['dimasak'] ?></div>
          <div class="lbl">Dimasak</div>
        </div>
      </div>
      <div class="summary-card">
        <div class="summary-icon" style="background:#eff6ff;color:#1d4ed8"><i class="fa-solid fa-bell"></i></div>
        <div class="summary-info">
          <div class="num"><?= $count['siap'] ?></div>
          <div class="lbl">Siap Diambil</div>
        </div>
      </div>
      <div class="summary-card">
        <div class="summary-icon" style="background:#f0fdf4;color:#15803d"><i class="fa-solid fa-check-double"></i></div>
        <div class="summary-info">
          <div class="num"><?= $count['selesai'] + $count['lunas'] ?></div>
          <div class="lbl">Selesai</div>
        </div>
      </div>
    </div>

    <!-- TAB BAR -->
    <div class="tab-bar">
      <div class="tab active" onclick="filterOrders('semua',this)">
        Semua <span class="tab-badge"><?= $total_orders ?></span>
      </div>
      <div class="tab" onclick="filterOrders('pending',this)">
        Menunggu <span class="tab-badge"><?= $count['pending'] ?></span>
      </div>
      <div class="tab" onclick="filterOrders('dimasak',this)">
        Dimasak <span class="tab-badge"><?= $count['dimasak'] ?></span>
      </div>
      <div class="tab" onclick="filterOrders('siap',this)">
        Siap Diambil <span class="tab-badge"><?= $count['siap'] ?></span>
      </div>
      <div class="tab" onclick="filterOrders('selesai',this)">
        Selesai
        <?php if (($count['selesai'] + $count['lunas']) > 0): ?>
          <span class="tab-badge"><?= $count['selesai'] + $count['lunas'] ?></span>
        <?php endif; ?>
      </div>
      <div class="tab" onclick="filterOrders('ditolak',this)">
        Dibatalkan
        <?php if (($count['ditolak'] + ($count['batal'] ?? 0)) > 0): ?>
          <span class="tab-badge"><?= $count['ditolak'] + ($count['batal'] ?? 0) ?></span>
        <?php endif; ?>
      </div>
    </div>

    <!-- SEARCH -->
    <div class="search-bar">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="text" id="searchInput" placeholder="Cari nama pembeli atau menu..." oninput="filterSearch()">
    </div>

    <!-- ORDERS LIST -->
    <div class="orders-list" id="ordersList">

      <?php if (empty($groups)): ?>
        <div class="empty-state">
          <i class="fa-solid fa-bowl-food"></i>
          <p class="title">Belum ada pesanan masuk</p>
          <p class="sub">Pesanan dari pelanggan akan muncul di sini</p>
        </div>
      <?php else: ?>

        <?php foreach ($groups as $gi => $group):
          $status       = $group['status'];
          $si           = $statusMap[$status] ?? $statusMap['pending'];
          $color        = $avatarColors[$gi % count($avatarColors)];
          $waktu        = timeAgo($group['tanggal']);
          $totalFmt     = 'Rp ' . number_format($group['total_group'], 0, ',', '.');
          $groupKey     = htmlspecialchars($group['group_key']);
          $idsJson      = htmlspecialchars(json_encode($group['ids']));
          $itemCount    = count($group['items']);
          $firstAntrian = $group['items'][0]['antrian'] ?? '—';
          $metodeUnik   = array_unique(array_column($group['items'], 'metode'));
          $allMenuNames = implode(' ', array_column($group['items'], 'nama_makanan'));
        ?>

        <div class="order-card <?= $si['card'] ?>"
             id="card-<?= $groupKey ?>"
             onclick="bukaModalDetail('<?= $groupKey ?>')"
             data-status="<?= htmlspecialchars($status) ?>"
             data-nama="<?= htmlspecialchars($group['nama_user']) ?>"
             data-group-key="<?= $groupKey ?>"
             data-group-ids="<?= $idsJson ?>"
             data-search-text="<?= htmlspecialchars(strtolower($group['nama_user'] . ' ' . $allMenuNames)) ?>">

          <div class="order-top">
            <?php $fotoUser = $group['foto_user'] ?? ''; ?>
            <div class="order-avatar" style="background:linear-gradient(135deg,<?= $color[0] ?>,<?= $color[1] ?>);padding:0;">
              <img src="<?= !empty($fotoUser) ? '../gambar/profil/' . htmlspecialchars($fotoUser) : 'https://ui-avatars.com/api/?name=' . urlencode($group['nama_user']) . '&background=' . ltrim($color[0],'#') . '&color=fff&size=96&bold=true' ?>"
                   style="width:100%;height:100%;object-fit:cover;display:block;"
                   onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($group['nama_user']) ?>&background=<?= ltrim($color[0],'#') ?>&color=fff&size=96&bold=true'"
                   alt="<?= htmlspecialchars($group['nama_user']) ?>">
              <span class="order-id-badge">#<?= htmlspecialchars($firstAntrian) ?></span>
            </div>
            <div class="order-info">
              <div class="order-name"><?= htmlspecialchars($group['nama_user']) ?></div>
              <div class="order-time">
                <i class="fa-regular fa-clock"></i> <?= $waktu ?>
                <?php if ($itemCount > 1): ?>
                  &nbsp;·&nbsp; <i class="fa-solid fa-utensils"></i> <?= $itemCount ?> menu
                <?php endif; ?>
              </div>
            </div>
            <div class="order-meta">
              <span class="status-badge <?= $si['class'] ?>" id="badge-<?= $groupKey ?>">
                <i class="<?= $si['icon'] ?>"></i> <?= $si['label'] ?>
              </span>
              <span class="order-price"><?= $totalFmt ?></span>
            </div>
          </div>

          <!-- ITEM LIST ringkas -->
          <div class="order-items-list">
            <?php foreach ($group['items'] as $item): ?>
              <div class="order-item-row">
                <span class="qty"><?= (int)$item['jumlah'] ?>×</span>
                <span class="name"><?= htmlspecialchars($item['nama_makanan']) ?></span>
                <span class="price">Rp <?= number_format($item['total'], 0, ',', '.') ?></span>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- METODE TAG -->
          <div class="order-tags">
            <?php foreach ($metodeUnik as $m):
              $mIcon = strtolower($m) === 'cash' ? 'money-bill-wave' : 'qrcode';
              $mStyle = strtolower($m) === 'cash'
                ? 'background:#f0fdf4;color:#15803d;border:1.5px solid #bbf7d0;'
                : 'background:#f5f3ff;color:#6d28d9;border:1.5px solid #ddd6fe;';
            ?>
            <span class="item-tag" style="<?= $mStyle ?>">
              <i class="fa-solid fa-<?= $mIcon ?>"></i> <?= htmlspecialchars($m) ?>
            </span>
            <?php endforeach; ?>
            <?php if (count($metodeUnik) > 1): ?>
            <span class="item-tag" style="background:#fef9c3;color:#92400e;border:1.5px solid #fde68a;">
              <i class="fa-solid fa-shuffle"></i> Pembayaran Campuran
            </span>
            <?php endif; ?>
          </div>

          <!-- ACTION BUTTONS -->
          <div class="order-actions" id="actions-<?= $groupKey ?>">

            <!-- ★ TOMBOL ANTRIAN — selalu tampil di semua status ★ -->
            <button class="btn-antrian-inline"
                    onclick="event.stopPropagation();bukaPopupAntrian('<?= $groupKey ?>')">
              <i class="fa-solid fa-ticket"></i>
              Antrian
              <span class="ant-num">#<?= htmlspecialchars($firstAntrian) ?></span>
            </button>

            <?php if ($status === 'pending'): ?>
              <button class="btn btn-masak" onclick="event.stopPropagation();updateGroupStatus('<?= $groupKey ?>','masak',this)">
                <i class="fa-solid fa-fire-burner"></i> Masak
              </button>
              <button class="btn-tolak" onclick="event.stopPropagation();updateGroupStatus('<?= $groupKey ?>','tolak',this)">
                <i class="fa-solid fa-xmark"></i> Tolak
              </button>
            <?php elseif ($status === 'dimasak'): ?>
              <button class="btn-siap" onclick="event.stopPropagation();updateGroupStatus('<?= $groupKey ?>','siap',this)">
                <i class="fa-solid fa-bell"></i> Siap Diambil
              </button>
            <?php elseif ($status === 'siap'): ?>
              <button class="btn btn-selesai" onclick="event.stopPropagation();updateGroupStatus('<?= $groupKey ?>','selesai',this)">
                <i class="fa-solid fa-circle-check"></i> Selesai
              </button>
            <?php elseif ($status === 'selesai' || $status === 'lunas'): ?>
              <span style="font-size:13px;color:#15803d;font-weight:700;display:flex;align-items:center;gap:6px;">
                <i class="fa-solid fa-check-double"></i> Pesanan Selesai
              </span>
            <?php elseif ($status === 'ditolak' || $status === 'batal'): ?>
              <span style="font-size:13px;color:#ef4444;font-weight:700;display:flex;align-items:center;gap:6px;">
                <i class="fa-solid fa-ban"></i> Pesanan Ditolak
              </span>
            <?php endif; ?>

          </div>
        </div>

        <?php endforeach; ?>
      <?php endif; ?>

    </div><!-- /orders-list -->
  </main>
</div>

<!-- ==========================================
     MODAL DETAIL PESANAN
     ========================================== -->
<div class="modal-overlay" id="detail-modal" onclick="if(event.target===this)tutupModalDetail()">
  <div class="detail-modal-box">
    <div class="detail-modal-header">
      <button class="detail-close-btn" onclick="tutupModalDetail()"><i class="fa-solid fa-xmark"></i></button>
      <h3><i class="fa-solid fa-clipboard-list" style="color:#e8650a"></i> Detail Pesanan</h3>
      <p class="modal-sub" id="detail-subtitle">Informasi lengkap dari pembeli</p>
    </div>
    <div class="detail-modal-body">
      <div class="detail-user-card">
        <div class="detail-user-avatar" id="detail-avatar"></div>
        <div class="detail-user-info">
          <div class="detail-user-name" id="detail-nama">—</div>
          <div class="detail-user-username" id="detail-username"></div>
          <div style="margin-top:5px;" id="detail-badge-wrap"></div>
        </div>
        <div style="text-align:right;flex-shrink:0;">
          <div style="font-size:11px;color:#94a3b8;margin-bottom:3px;" id="detail-waktu"></div>
          <div style="font-size:16px;font-weight:800;color:#e8650a;" id="detail-total-top"></div>
        </div>
      </div>
      <div class="modal-info-grid" id="detail-info-grid"></div>
      <div class="modal-items-section" id="detail-items-section">
        <div class="modal-items-header">
          <span>Item Pesanan</span>
          <span id="detail-items-count"></span>
        </div>
        <div id="detail-items-body"></div>
      </div>
      <div id="detail-catatan-section"></div>
      <div class="modal-total-row">
        <span class="lbl">Total Pembayaran</span>
        <span class="val" id="detail-grand-total">—</span>
      </div>
    </div>
    <div class="modal-footer">
      <button class="modal-cancel-btn" onclick="tutupModalDetail()">Tutup</button>
    </div>
  </div>
</div>

<!-- ==========================================
     POPUP NOMOR ANTRIAN & KODE ANTRIAN
     ========================================== -->
<div class="antrian-popup-overlay" id="popup-antrian" onclick="if(event.target===this)tutupPopupAntrian()">
  <div class="antrian-popup-box">

    <!-- Header -->
    <div class="antrian-popup-header">
      <div class="antrian-popup-header-icon">
        <i class="fa-solid fa-ticket"></i>
      </div>
      <div>
        <div class="antrian-popup-title">Nomor &amp; Kode Antrian</div>
        <div class="antrian-popup-subtitle" id="popup-antrian-nama">—</div>
      </div>
      <button class="antrian-popup-close" onclick="tutupPopupAntrian()">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <!-- Body — diisi JS -->
    <div class="antrian-popup-body" id="popup-antrian-body"></div>

    <!-- Footer -->
    <div class="antrian-popup-footer">
      <button onclick="tutupPopupAntrian()">Tutup</button>
    </div>

  </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<script>
// ── Data grup dari PHP ──────────────────────────────────────────
const GROUPS = <?php
  $jsGroups = [];
  foreach ($groups as $g) {
      $jsGroups[$g['group_key']] = [
          'group_key'   => $g['group_key'],
          'nama_user'   => $g['nama_user'],
          'username'    => $g['username'],
          'foto_user'   => $g['foto_user'] ?? '',
          'tanggal'     => $g['tanggal'],
          'status'      => $g['status'],
          'ids'         => $g['ids'],
          'total_group' => $g['total_group'],
          'metode'      => $g['metode'],
          'metodes'     => $g['metodes'] ?? [$g['metode']],
          'antrian'     => $g['items'][0]['antrian'] ?? '—',
          'items'       => array_map(fn($it) => [
              'id_transaksi' => $it['id_transaksi'],
              'nama_makanan' => $it['nama_makanan'],
              'jumlah'       => (int)$it['jumlah'],
              'total'        => (float)$it['total'],
              'catatan'      => $it['catatan'] ?? '',
              'metode'       => $it['metode'],
              'status'       => $it['status'],
              'antrian'      => $it['antrian'],
          ], $g['items']),
      ];
  }
  echo json_encode($jsGroups, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);
?>;

// ── ID kantin dari PHP ──────────────────────────────────────────
const ID_KANTIN = <?= (int)$id_kantin ?>;

// ── Status config ───────────────────────────────────────────────
const STATUS_CONFIG = {
    dimasak: {
        cardClass  : 'order-card status-dimasak',
        badgeClass : 'status-badge badge-dimasak',
        badgeHTML  : '<i class="fa-solid fa-fire-burner"></i> Dimasak',
        actionsHTML: (k, ant) => `
            <button class="btn-antrian-inline" onclick="event.stopPropagation();bukaPopupAntrian('${k}')">
                <i class="fa-solid fa-ticket"></i> Antrian <span class="ant-num">#${ant}</span>
            </button>
            <button class="btn-siap" onclick="event.stopPropagation();updateGroupStatus('${k}','siap',this)"><i class="fa-solid fa-bell"></i> Siap Diambil</button>`,
    },
    siap: {
        cardClass  : 'order-card status-siap',
        badgeClass : 'status-badge badge-siap',
        badgeHTML  : '<i class="fa-solid fa-bell"></i> Siap Diambil',
        actionsHTML: (k, ant) => `
            <button class="btn-antrian-inline" onclick="event.stopPropagation();bukaPopupAntrian('${k}')">
                <i class="fa-solid fa-ticket"></i> Antrian <span class="ant-num">#${ant}</span>
            </button>
            <button class="btn btn-selesai" onclick="event.stopPropagation();updateGroupStatus('${k}','selesai',this)"><i class="fa-solid fa-circle-check"></i> Selesai</button>`,
    },
    lunas: {
        cardClass  : 'order-card status-lunas',
        badgeClass : 'status-badge badge-lunas',
        badgeHTML  : '<i class="fa-solid fa-check-double"></i> Selesai',
        actionsHTML: (k, ant) => `
            <button class="btn-antrian-inline" onclick="event.stopPropagation();bukaPopupAntrian('${k}')">
                <i class="fa-solid fa-ticket"></i> Antrian <span class="ant-num">#${ant}</span>
            </button>
            <span style="font-size:13px;color:#15803d;font-weight:700;display:flex;align-items:center;gap:6px;"><i class="fa-solid fa-check-double"></i> Pesanan Selesai</span>`,
    },
    ditolak: {
        cardClass  : 'order-card status-tolak',
        badgeClass : 'status-badge badge-tolak',
        badgeHTML  : '<i class="fa-solid fa-xmark"></i> Ditolak',
        actionsHTML: (k, ant) => `
            <button class="btn-antrian-inline" onclick="event.stopPropagation();bukaPopupAntrian('${k}')">
                <i class="fa-solid fa-ticket"></i> Antrian <span class="ant-num">#${ant}</span>
            </button>
            <span style="font-size:13px;color:#ef4444;font-weight:700;display:flex;align-items:center;gap:6px;"><i class="fa-solid fa-ban"></i> Pesanan Ditolak</span>`,
    },
};
STATUS_CONFIG['selesai'] = STATUS_CONFIG['lunas'];

// ── Notif Chat ──────────────────────────────────────────────────
async function loadNotifChat() {
    try {
        const res  = await fetch('../users/chat_api.php?action=jumlah_belum_baca');
        const data = await res.json();
        const badge = document.getElementById('chatBadge');
        badge.style.display = data.belum_baca > 0 ? 'flex' : 'none';
        if (data.belum_baca > 0) badge.textContent = data.belum_baca;
    } catch(e) {}
}
loadNotifChat();
setInterval(loadNotifChat, 10000);

// ── Sidebar mobile ──────────────────────────────────────────────
const hamburgerBtn   = document.getElementById('hamburgerBtn');
const sidebar        = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
hamburgerBtn.addEventListener('click', () => {
    sidebar.classList.add('open');
    sidebarOverlay.classList.add('show');
    document.body.style.overflow = 'hidden';
});
sidebarOverlay.addEventListener('click', () => {
    sidebar.classList.remove('open');
    sidebarOverlay.classList.remove('show');
    document.body.style.overflow = '';
});

// ── Toast ───────────────────────────────────────────────────────
function showToast(msg, type = '') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast' + (type ? ' ' + type : '');
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3200);
}

// ── Filter tab ──────────────────────────────────────────────────
let currentFilter = 'semua';

function filterOrders(status, el) {
    currentFilter = status;
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    document.querySelectorAll('.order-card').forEach(card => {
        const s = card.dataset.status;
        const match = status === 'semua'
            || s === status
            || (status === 'selesai' && (s === 'selesai' || s === 'lunas'))
            || (status === 'ditolak' && (s === 'ditolak' || s === 'batal'));
        card.style.display = match ? '' : 'none';
    });
    checkEmpty();
}

function filterSearch() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('.order-card').forEach(card => {
        const statusOk = currentFilter === 'semua' || card.dataset.status === currentFilter
            || (currentFilter === 'selesai' && (card.dataset.status === 'selesai' || card.dataset.status === 'lunas'))
            || (currentFilter === 'ditolak' && (card.dataset.status === 'ditolak' || card.dataset.status === 'batal'));
        const textOk = !q || (card.dataset.searchText || '').includes(q);
        card.style.display = (statusOk && textOk) ? '' : 'none';
    });
    checkEmpty();
}

function checkEmpty() {
    const list    = document.getElementById('ordersList');
    const visible = [...list.querySelectorAll('.order-card')].some(c => c.style.display !== 'none');
    let empty = list.querySelector('.empty-state-js');
    if (!visible) {
        if (!empty) {
            empty = document.createElement('div');
            empty.className = 'empty-state empty-state-js';
            empty.innerHTML = '<i class="fa-solid fa-bowl-food"></i><p class="title">Tidak ada pesanan</p><p class="sub">Coba pilih tab lain atau ubah kata pencarian</p>';
            list.appendChild(empty);
        }
    } else {
        if (empty) empty.remove();
    }
}

// ── Update grup status (AJAX) ───────────────────────────────────
function updateGroupStatus(groupKey, action, btn) {
    const group = GROUPS[groupKey];
    if (!group) return;

    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>'; }

    const actionMap = { masak:'dimasak', siap:'siap', selesai:'lunas', tolak:'ditolak' };
    const newStatus = actionMap[action];
    if (!newStatus) return;

    const formData = new FormData();
    formData.append('action', action);
    formData.append('group_ids', JSON.stringify(group.ids));

    fetch('pesanan.php', { method:'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                group.status = data.new_status;
                group.items.forEach(it => { it.status = data.new_status; });
                applyGroupStatusChange(groupKey, data.new_status);
                const msgMap = {
                    dimasak : '🔥 Semua item mulai dimasak!',
                    siap    : '🔔 Semua item siap diambil!',
                    lunas   : '✅ Pesanan selesai!',
                    ditolak : '❌ Pesanan ditolak.',
                };
                showToast(msgMap[data.new_status] || 'Status diperbarui', data.new_status === 'ditolak' ? 'error' : 'success');
            } else {
                showToast('Gagal: ' + (data.message || 'Error'), 'error');
                if (btn) { btn.disabled = false; location.reload(); }
            }
        })
        .catch(err => {
            showToast('Error: ' + err, 'error');
            if (btn) btn.disabled = false;
        });
}

function applyGroupStatusChange(groupKey, newStatus) {
    const cfg  = STATUS_CONFIG[newStatus];
    if (!cfg) return;
    const group   = GROUPS[groupKey];
    const ant     = group ? (group.antrian || group.items[0]?.antrian || '—') : '—';
    const card    = document.getElementById('card-' + groupKey);
    const badge   = document.getElementById('badge-' + groupKey);
    const actions = document.getElementById('actions-' + groupKey);
    if (!card) return;
    card.className      = cfg.cardClass;
    card.dataset.status = newStatus;
    badge.className     = cfg.badgeClass;
    badge.innerHTML     = cfg.badgeHTML;
    actions.innerHTML   = cfg.actionsHTML(groupKey, ant);
    if (currentFilter !== 'semua'
        && currentFilter !== newStatus
        && !(currentFilter === 'selesai' && (newStatus === 'selesai' || newStatus === 'lunas'))
        && !(currentFilter === 'ditolak' && (newStatus === 'ditolak' || newStatus === 'batal'))) {
        card.style.display = 'none';
    }
    card.style.transition = 'box-shadow .3s';
    card.style.boxShadow  = newStatus === 'lunas' ? '0 0 0 3px #22c55e55' : '0 0 0 3px #e8650a44';
    setTimeout(() => { card.style.boxShadow = ''; card.style.transition = ''; }, 1500);
    checkEmpty();
}

// ── Helpers ─────────────────────────────────────────────────────
function formatRp(num) {
    return 'Rp ' + Number(num).toLocaleString('id-ID');
}

function getInitialsJS(nama) {
    return (nama||'').trim().split(' ').slice(0,2).map(p=>(p[0]||'').toUpperCase()).join('');
}

function timeAgoJS(dateStr) {
    const now  = new Date();
    const past = new Date(dateStr);
    const diff = Math.floor((now - past) / 1000);
    if (diff < 60)    return diff + ' detik lalu';
    if (diff < 3600)  return Math.floor(diff/60) + ' menit lalu';
    if (diff < 86400) return Math.floor(diff/3600) + ' jam lalu';
    return past.toLocaleDateString('id-ID', {day:'numeric',month:'short',year:'numeric'});
}

function metodeChipHTML(metode, small = false) {
    const isCash = metode.toUpperCase() === 'CASH';
    const cls    = isCash ? 'cash' : 'qris';
    const icon   = isCash ? 'fa-solid fa-money-bill-wave' : 'fa-solid fa-qrcode';
    if (small) {
        return `<span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;
            padding:2px 8px;border-radius:50px;
            ${isCash ? 'background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;'
                     : 'background:#f5f3ff;color:#6d28d9;border:1px solid #ddd6fe;'}">
            <i class="${icon}" style="font-size:9px"></i> ${metode.toUpperCase()}
        </span>`;
    }
    return `<span class="metode-chip ${cls}"><i class="${icon}"></i> ${metode.toUpperCase()}</span>`;
}

// ── Status badge map ────────────────────────────────────────────
const STATUS_BADGE_MAP = {
    pending  : ['badge-menunggu', 'Menunggu Konfirmasi'],
    dimasak  : ['badge-dimasak',  'Sedang Dimasak'],
    siap     : ['badge-siap',     'Siap Diambil'],
    selesai  : ['badge-lunas',    'Selesai'],
    lunas    : ['badge-lunas',    'Lunas'],
    ditolak  : ['badge-tolak',    'Ditolak'],
    batal    : ['badge-tolak',    'Dibatalkan'],
};

// ── Modal Detail ────────────────────────────────────────────────
function bukaModalDetail(groupKey) {
    const g = GROUPS[groupKey];
    if (!g) return;

    // Avatar
    const avatarEl = document.getElementById('detail-avatar');
    if (g.foto_user) {
        avatarEl.innerHTML = `<img src="../gambar/profil/${g.foto_user}"
            style="width:100%;height:100%;object-fit:cover;border-radius:50%"
            onerror="this.style.display='none';this.parentNode.textContent='${getInitialsJS(g.nama_user)}'"
            alt="${g.nama_user}">`;
    } else {
        avatarEl.textContent = getInitialsJS(g.nama_user);
    }

    document.getElementById('detail-nama').textContent      = g.nama_user;
    document.getElementById('detail-username').textContent  = g.username ? '@' + g.username : '';
    document.getElementById('detail-waktu').textContent     = timeAgoJS(g.tanggal);
    document.getElementById('detail-total-top').textContent = formatRp(g.total_group);
    document.getElementById('detail-subtitle').textContent  = g.items.length + ' item dari ' + g.nama_user;

    const [badgeClass, badgeLabel] = STATUS_BADGE_MAP[g.status] || ['', g.status];
    document.getElementById('detail-badge-wrap').innerHTML =
        `<span class="status-badge ${badgeClass}" style="font-size:11px;padding:3px 10px">${badgeLabel}</span>`;

    // ── Kumpulkan nomor antrian unik ─────────────────────────────
    const allAntrianList = [];
    const seenAntrian    = new Set();
    g.items.forEach(it => {
        if (it.antrian && !seenAntrian.has(it.antrian)) {
            seenAntrian.add(it.antrian);
            allAntrianList.push(it.antrian);
        }
    });

    // ── Metode bayar ─────────────────────────────────────────────
    const metodes     = g.metodes || [g.metode];
    const adaCampuran = metodes.length > 1;

    const itemsByMetode = {};
    metodes.forEach(m => { itemsByMetode[m.toUpperCase()] = []; });
    g.items.forEach(it => {
        const key = (it.metode || g.metode).toUpperCase();
        if (!itemsByMetode[key]) itemsByMetode[key] = [];
        itemsByMetode[key].push(it);
    });

    const metodeHTMLArr = metodes.map(m => {
        const isCash      = m.toUpperCase() === 'CASH';
        const cls         = isCash ? 'cash' : 'qris';
        const icon        = isCash ? 'fa-solid fa-money-bill-wave' : 'fa-solid fa-qrcode';
        const itemList    = itemsByMetode[m.toUpperCase()] || [];
        const totalMetode = itemList.reduce((sum, it) => sum + (parseFloat(it.total) || 0), 0);
        const produkHTML  = itemList.map(it => {
            const safeName = it.nama_makanan.replace(/</g,'&lt;').replace(/>/g,'&gt;');
            return `<div style="display:flex;align-items:center;gap:5px;font-size:11px;
                        color:${isCash ? '#15803d' : '#6d28d9'};font-weight:600;
                        padding:4px 0 4px 6px;
                        border-left:2px solid ${isCash ? '#86efac' : '#c4b5fd'};">
                        <i class="fa-solid fa-utensils" style="font-size:9px;opacity:.7;flex-shrink:0;"></i>
                        <span style="flex:1;">${it.jumlah}× ${safeName}</span>
                        <span style="opacity:.75;flex-shrink:0;">${formatRp(it.total)}</span>
                    </div>`;
        }).join('');
        return `<div style="border-radius:10px;border:1.5px solid ${isCash ? '#bbf7d0' : '#ddd6fe'};overflow:hidden;flex:1;min-width:0;">
                    <div class="metode-chip ${cls}" style="border-radius:0;border:none;width:100%;justify-content:space-between;display:flex;align-items:center;">
                        <span style="display:flex;align-items:center;gap:7px;"><i class="${icon}"></i>${m.toUpperCase()}</span>
                        <span style="font-size:10px;opacity:.75;font-weight:700;">${itemList.length} item</span>
                    </div>
                    ${produkHTML ? `<div style="padding:6px 10px;background:${isCash ? '#f0fdf4' : '#f5f3ff'};display:flex;flex-direction:column;gap:3px;">${produkHTML}</div>` : ''}
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 10px;background:${isCash ? '#dcfce7' : '#ede9fe'};border-top:1.5px solid ${isCash ? '#86efac' : '#c4b5fd'};">
                        <span style="font-size:10px;font-weight:800;color:${isCash ? '#15803d' : '#6d28d9'};text-transform:uppercase;letter-spacing:.4px;">Total ${m.toUpperCase()}</span>
                        <span style="font-size:12px;font-weight:800;color:${isCash ? '#15803d' : '#6d28d9'};">${formatRp(totalMetode)}</span>
                    </div>
                </div>`;
    });

    // ── Label tombol antrian ──────────────────────────────────────
    const antrianCount = allAntrianList.length;
    const antrianLabel = antrianCount > 1
        ? `${antrianCount} nomor antrian`
        : (allAntrianList[0] ? `#${allAntrianList[0]}` : '—');

    // ── Render info grid: tombol antrian + metode bayar ───────────
    document.getElementById('detail-info-grid').innerHTML = `
        <div class="modal-info-cell" style="display:flex;flex-direction:column;gap:8px;">
            <div class="lbl">No. Antrian &amp; Kode</div>
            <button class="antrian-trigger-btn" onclick="event.stopPropagation();bukaPopupAntrian('${groupKey}')">
                <span class="antrian-trigger-icon">
                    <i class="fa-solid fa-ticket"></i>
                </span>
                <span style="flex:1;">
                    <span class="antrian-trigger-main">${antrianLabel}</span>
                    <span class="antrian-trigger-sub">Tap untuk lihat kode antrian</span>
                </span>
                <i class="fa-solid fa-chevron-right" style="color:#f97316;font-size:13px;flex-shrink:0;"></i>
            </button>
        </div>
        <div class="modal-info-cell" ${adaCampuran ? 'style="grid-column:1/-1;"' : ''}>
            <div class="lbl">Metode Bayar${adaCampuran ? ' <span style="color:#f59e0b;font-size:9px;">(campuran)</span>' : ''}</div>
            <div style="margin-top:6px;display:flex;flex-direction:${adaCampuran ? 'row' : 'column'};gap:8px;align-items:stretch;">
                ${metodeHTMLArr.join('')}
            </div>
        </div>
    `;

    // ── Items ─────────────────────────────────────────────────────
    document.getElementById('detail-items-count').textContent = g.items.length + ' item';
    document.getElementById('detail-items-body').innerHTML = g.items.map((it, idx) => {
        const itemMetode = it.metode || g.metode;
        const metodeTag  = adaCampuran
            ? `<div style="margin-top:4px;">${metodeChipHTML(itemMetode, true)}</div>`
            : '';
        return `
        <div class="modal-item-row">
            <div class="modal-item-num">${idx + 1}</div>
            <div class="modal-item-detail">
                <div class="modal-item-name">${it.nama_makanan.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</div>
                <div class="modal-item-qty">${it.jumlah}× · Antrian #${it.antrian}</div>
                ${metodeTag}
            </div>
            <div class="modal-item-price">${formatRp(it.total)}</div>
        </div>`;
    }).join('');

    // ── Catatan & Varian ──────────────────────────────────────────
    const itemsWithNotes = g.items.filter(it => it.catatan && it.catatan.trim() !== '');
    const catatanSection = document.getElementById('detail-catatan-section');

    if (itemsWithNotes.length > 0) {
        catatanSection.innerHTML = `
            <div class="modal-catatan-section">
                <div class="modal-catatan-header">
                    <i class="fa-solid fa-note-sticky"></i>
                    Varian &amp; Catatan Pembeli
                    <span style="margin-left:auto;background:#f59e0b;color:#fff;font-size:10px;padding:1px 8px;border-radius:50px;font-weight:800;">
                        ${itemsWithNotes.length} catatan
                    </span>
                </div>
                ${itemsWithNotes.map(it => {
                    const safeName = it.nama_makanan.replace(/</g,'&lt;').replace(/>/g,'&gt;');
                    const rawNote  = it.catatan || '';
                    let varianText  = '';
                    let catatanText = '';
                    const m = rawNote.match(/^Varian:\s*([^|]+?)(?:\s*\|\s*(.+))?$/i);
                    if (m) {
                        varianText  = m[1].trim();
                        catatanText = (m[2] || '').trim();
                    } else {
                        catatanText = rawNote;
                    }
                    const safeVarian  = varianText.replace(/</g,'&lt;').replace(/>/g,'&gt;');
                    const safeCatatan = catatanText.replace(/</g,'&lt;').replace(/>/g,'&gt;');
                    return `
                    <div class="modal-catatan-row">
                        <div style="flex:1;min-width:0;">
                            <span class="modal-catatan-menu-chip">
                                <i class="fa-solid fa-utensils" style="font-size:9px;margin-right:3px;"></i>
                                ${safeName} · ${it.jumlah}×
                            </span>
                            <div style="margin-top:8px;display:flex;flex-direction:column;gap:7px;">
                                ${safeVarian ? `
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <span style="display:inline-flex;align-items:center;gap:5px;background:#ede9fe;color:#6d28d9;
                                        font-size:11px;font-weight:700;padding:3px 10px;border-radius:50px;
                                        white-space:nowrap;flex-shrink:0;border:1.5px solid #ddd6fe;">
                                        <i class="fa-solid fa-layer-group" style="font-size:9px;"></i> Varian
                                    </span>
                                    <span style="font-size:13px;color:#4c1d95;font-weight:600;">${safeVarian}</span>
                                </div>` : ''}
                                ${safeCatatan ? `
                                <div style="display:flex;align-items:flex-start;gap:8px;">
                                    <span style="display:inline-flex;align-items:center;gap:5px;background:#fef9c3;color:#92400e;
                                        font-size:11px;font-weight:700;padding:3px 10px;border-radius:50px;
                                        white-space:nowrap;flex-shrink:0;border:1.5px solid #fde68a;margin-top:1px;">
                                        <i class="fa-solid fa-pen-nib" style="font-size:9px;"></i> Catatan
                                    </span>
                                    <span style="font-size:13px;color:#78350f;font-weight:500;line-height:1.5;">${safeCatatan}</span>
                                </div>` : ''}
                            </div>
                        </div>
                    </div>`;
                }).join('')}
            </div>`;
    } else {
        catatanSection.innerHTML = `
            <div class="modal-no-catatan">
                <i class="fa-regular fa-note-sticky" style="font-size:16px;"></i>
                Pembeli tidak menambahkan catatan
            </div>`;
    }

    document.getElementById('detail-grand-total').textContent = formatRp(g.total_group);
    document.getElementById('detail-modal').classList.add('open');
}

function tutupModalDetail() {
    document.getElementById('detail-modal').classList.remove('open');
}

// ── Popup Antrian ────────────────────────────────────────────────
function bukaPopupAntrian(groupKey) {
    const g = GROUPS[groupKey];
    if (!g) return;

    // Kumpulkan antrian unik
    const allAntrianList = [];
    const seenAntrian    = new Set();
    g.items.forEach(it => {
        if (it.antrian && !seenAntrian.has(it.antrian)) {
            seenAntrian.add(it.antrian);
            allAntrianList.push(it.antrian);
        }
    });

    const isMulti = allAntrianList.length > 1;

    // Buat kartu per nomor antrian
    const cardsHTML = allAntrianList.map((ant, idx) => {
        const kode = '#KANTIN' + ID_KANTIN + 'KNVV' + String(ant).padStart(3, '0');
        const itemLabel = isMulti
            ? `<div class="antrian-card-item-label">Item ${idx + 1}</div>`
            : '';
        return `
        <div class="antrian-card">
            <div class="antrian-card-topbar"></div>
            ${itemLabel}
            <div class="antrian-card-nomor-label">Nomor Antrian</div>
            <div class="antrian-card-nomor">#${ant}</div>
            <div class="antrian-card-divider"></div>
            <div class="antrian-card-kode-label">Kode Antrian</div>
            <div class="antrian-kode-box" onclick="salinKode(this,'${kode}',${idx})" title="Klik untuk menyalin">
                <i class="fa-solid fa-ticket ticket-icon"></i>
                <span class="antrian-kode-text">${kode}</span>
                <i class="fa-regular fa-copy antrian-kode-copy-icon" id="copy-icon-${idx}"></i>
            </div>
            <div class="antrian-kode-hint">Klik kode untuk menyalin</div>
        </div>`;
    }).join('');

    // Update subtitle popup
    const subtitleText = isMulti
        ? g.nama_user + ' · ' + allAntrianList.length + ' nomor antrian'
        : g.nama_user;

    document.getElementById('popup-antrian-nama').textContent = subtitleText;

    // Wrap dengan grid jika multi
    document.getElementById('popup-antrian-body').innerHTML = isMulti
        ? `<div class="antrian-multi-grid">${cardsHTML}</div>`
        : cardsHTML;

    document.getElementById('popup-antrian').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function tutupPopupAntrian() {
    document.getElementById('popup-antrian').classList.remove('open');
    document.body.style.overflow = '';
}

function salinKode(el, kode, idx) {
    navigator.clipboard.writeText(kode).then(() => {
        const icon = document.getElementById('copy-icon-' + idx);
        if (icon) {
            icon.className = 'fa-solid fa-check antrian-kode-copy-icon';
            icon.style.color = '#16a34a';
            setTimeout(() => {
                icon.className = 'fa-regular fa-copy antrian-kode-copy-icon';
                icon.style.color = '';
            }, 2000);
        }
        showToast('✅ Kode antrian disalin!', 'success');
    }).catch(() => {
        showToast('Gagal menyalin kode', 'error');
    });
}

// ── Auto-refresh notif badge ────────────────────────────────────
function refreshNotifBadge() {
    fetch('pesanan.php?action=get_notif_count')
        .then(r => r.json())
        .then(data => {
            const badge   = document.getElementById('notifBadge');
            const bellBtn = document.getElementById('notifBellBtn');
            const tooltip = document.getElementById('notifTooltip');
            const total   = data.pending || 0;
            badge.style.display = total > 0 ? 'flex' : 'none';
            badge.textContent   = total;
            bellBtn.classList.toggle('has-notif', total > 0);
            tooltip.textContent = total > 0 ? total + ' pesanan perlu dikonfirmasi' : 'Tidak ada pesanan baru';
        }).catch(()=>{});
}
setInterval(refreshNotifBadge, 15000);

function loadSidebarPesananBadge() {
    fetch('pesanan.php?action=get_notif_count')
        .then(r => r.json())
        .then(data => {
            const badge = document.getElementById('sidebarPesananBadge');
            if (!badge) return;
            const pending = data.pending || 0;
            badge.textContent   = pending;
            badge.style.display = pending > 0 ? 'flex' : 'none';
        }).catch(()=>{});
}
loadSidebarPesananBadge();
setInterval(loadSidebarPesananBadge, 5000);
</script>

<?php include 'chat_popup_penjual.php'; ?>

</body>
</html>