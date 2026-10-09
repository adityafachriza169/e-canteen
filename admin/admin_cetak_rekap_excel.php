<?php
/**
 * admin_cetak_rekap_excel.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Halaman HTML yang mengunduh Excel rekap lengkap 1 penjual menggunakan
 * xlsx-js-style di sisi browser agar mendukung styling sel (warna, font, border).
 *
 * Data diambil dari database dan di-inject ke JS sebagai JSON.
 * Design system Excel identik dengan admin_beranda.php (XL color tokens,
 * cell builder helpers, dll.), tapi jauh lebih lengkap dengan:
 *   Sheet 1 – Profil & Ringkasan
 *   Sheet 2 – Semua Menu (aktif + nonaktif + terhapus)
 *   Sheet 3 – Semua Transaksi
 *   Sheet 4 – Menu Terlaris
 *   Sheet 5 – Pendapatan Harian (30 hari)
 *   Sheet 6 – Ulasan Pembeli
 *
 * GET: ?id_penjual=XX
 * ─────────────────────────────────────────────────────────────────────────────
 */
session_start();
include "database.php";
require_once 'cookie_cek_login.php';
/* ── Auth ── */
if (!isset($_SESSION['username'])) {
    if (isset($_COOKIE['admin_remember']) && !empty($_COOKIE['admin_remember'])) {
        $token = $_COOKIE['admin_remember'];
        $stmt  = mysqli_prepare($db, "SELECT username FROM akun_admin WHERE ingat = ?");
        mysqli_stmt_bind_param($stmt, "s", $token);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if ($row) $_SESSION['username'] = $row['username'];
        else { header("Location: index.php"); exit; }
    } else { header("Location: index.php"); exit; }
}
$id_penjual = intval($_GET['id_penjual'] ?? 0);
if ($id_penjual <= 0) { header("Location: admin_kelola_penjual.php"); exit; }
/* ═══ AMBIL DATA — dirender ke JS sebagai JSON ═══ */
/* 1. Profil penjual */
$stmt = mysqli_prepare($db,
    "SELECT p.*,
            COALESCE(p.id_kantin, p.nonaktif_dari_kantin) AS kantin_ref,
            k.nama_kantin
     FROM penjual p
     LEFT JOIN kantin k ON k.id_kantin = COALESCE(p.id_kantin, p.nonaktif_dari_kantin)
     WHERE p.id_penjual = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id_penjual);
mysqli_stmt_execute($stmt);
$penjual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$penjual) { header("Location: admin_kelola_penjual.php"); exit; }
/* 2. QRIS */
$stmt = mysqli_prepare($db,
    "SELECT * FROM penjual_qris WHERE id_penjual = ? ORDER BY id_qris DESC LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id_penjual);
mysqli_stmt_execute($stmt);
$qris = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
/* 3. Rekap agregat transaksi */
$stmt = mysqli_prepare($db,
    "SELECT
         COUNT(*)                                                                AS total_trx,
         SUM(CASE WHEN t.status='lunas'   THEN t.total  ELSE 0 END)            AS total_pendapatan,
         SUM(CASE WHEN t.status='lunas'   THEN t.jumlah ELSE 0 END)            AS total_item,
         SUM(CASE WHEN t.status='lunas'   THEN 1 ELSE 0 END)                   AS total_lunas,
         SUM(CASE WHEN t.status='batal'   THEN 1 ELSE 0 END)                   AS total_batal,
         SUM(CASE WHEN t.status='ditolak' THEN 1 ELSE 0 END)                   AS total_ditolak,
         SUM(CASE WHEN t.status='pending' THEN 1 ELSE 0 END)                   AS total_pending,
         SUM(CASE WHEN t.status='siap'    THEN 1 ELSE 0 END)                   AS total_siap,
         SUM(CASE WHEN t.status='dimasak' THEN 1 ELSE 0 END)                   AS total_dimasak,
         SUM(CASE WHEN t.metode='CASH' AND t.status='lunas' THEN t.total ELSE 0 END) AS pdp_cash,
         SUM(CASE WHEN t.metode='QRIS' AND t.status='lunas' THEN t.total ELSE 0 END) AS pdp_qris,
         MAX(CASE WHEN t.status='lunas' THEN t.total ELSE 0 END)                AS trx_terbesar,
         MIN(t.tanggal)                                                          AS trx_pertama,
         MAX(t.tanggal)                                                          AS trx_terakhir,
         AVG(CASE WHEN t.status='lunas' THEN t.total END)                       AS rata_trx
     FROM transaksi t
     JOIN menu_req m ON m.id = t.id_menu
     WHERE m.id_penjual = ?");
mysqli_stmt_bind_param($stmt, "i", $id_penjual);
mysqli_stmt_execute($stmt);
$rekap = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
/* 4. Semua menu (aktif + nonaktif + terhapus) */
$stmt = mysqli_prepare($db,
    "SELECT m.id, m.nama_makanan, m.kategori, m.harga, m.stok, m.terjual,
            m.aktif, m.deleted_at, m.created_at, m.deskripsi, m.waktu_proses,
            k.nama_kantin,
            COALESCE((SELECT AVG(ul.bintang) FROM ulasan ul WHERE ul.id_menu = m.id), 0) AS rata_bintang,
            COALESCE((SELECT COUNT(*) FROM ulasan ul WHERE ul.id_menu = m.id), 0)         AS jumlah_ulasan,
            COALESCE((SELECT SUM(t2.jumlah) FROM transaksi t2 WHERE t2.id_menu=m.id AND t2.status='lunas'), 0) AS item_terjual_trx,
            COALESCE((SELECT SUM(t2.total)  FROM transaksi t2 WHERE t2.id_menu=m.id AND t2.status='lunas'), 0) AS omset,
            COALESCE((SELECT COUNT(*) FROM transaksi t2 WHERE t2.id_menu=m.id AND t2.status='lunas'),   0) AS trx_lunas,
            COALESCE((SELECT COUNT(*) FROM transaksi t2 WHERE t2.id_menu=m.id AND t2.status='batal'),   0) AS trx_batal,
            COALESCE((SELECT COUNT(*) FROM transaksi t2 WHERE t2.id_menu=m.id AND t2.status='ditolak'), 0) AS trx_ditolak,
            COALESCE((SELECT SUM(t2.total) FROM transaksi t2 WHERE t2.id_menu=m.id AND t2.status='lunas' AND t2.metode='CASH'), 0) AS omset_cash,
            COALESCE((SELECT SUM(t2.total) FROM transaksi t2 WHERE t2.id_menu=m.id AND t2.status='lunas' AND t2.metode='QRIS'), 0) AS omset_qris,
            COALESCE((SELECT COUNT(*) FROM varian_menu v WHERE v.id_menu=m.id), 0) AS jumlah_varian
     FROM menu_req m
     LEFT JOIN kantin k ON k.id_kantin = m.id_kantin
     WHERE m.id_penjual = ?
     ORDER BY omset DESC, m.aktif DESC, m.nama_makanan ASC");
mysqli_stmt_bind_param($stmt, "i", $id_penjual);
mysqli_stmt_execute($stmt);
$menu_list = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
/* 5. Top 10 menu terlaris */
$stmt = mysqli_prepare($db,
    "SELECT t.nama_makanan,
            SUM(t.jumlah)  AS qty,
            SUM(t.total)   AS omset,
            COUNT(*)        AS trx_total,
            SUM(CASE WHEN t.status='lunas' THEN 1 ELSE 0 END) AS lunas
     FROM transaksi t
     JOIN menu_req m ON m.id = t.id_menu
     WHERE m.id_penjual = ? AND t.status = 'lunas'
     GROUP BY t.id_menu, t.nama_makanan
     ORDER BY qty DESC
     LIMIT 10");
mysqli_stmt_bind_param($stmt, "i", $id_penjual);
mysqli_stmt_execute($stmt);
$terlaris = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
/* 6. Pendapatan harian 30 hari */
$stmt = mysqli_prepare($db,
    "SELECT DATE(t.tanggal) AS tgl,
            SUM(CASE WHEN t.status='lunas' THEN t.total  ELSE 0 END) AS pdp,
            COUNT(CASE WHEN t.status='lunas' THEN 1 END)              AS trx_lunas,
            COUNT(*)                                                    AS trx_semua,
            SUM(CASE WHEN t.status='lunas' AND t.metode='CASH' THEN t.total ELSE 0 END) AS pdp_cash,
            SUM(CASE WHEN t.status='lunas' AND t.metode='QRIS' THEN t.total ELSE 0 END) AS pdp_qris
     FROM transaksi t
     JOIN menu_req m ON m.id = t.id_menu
     WHERE m.id_penjual = ?
       AND t.tanggal >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY DATE(t.tanggal)
     ORDER BY tgl ASC");
mysqli_stmt_bind_param($stmt, "i", $id_penjual);
mysqli_stmt_execute($stmt);
$harian = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
/* 7. Semua transaksi */
$stmt = mysqli_prepare($db,
    "SELECT t.id_transaksi, t.tanggal, t.nama_makanan, t.nama_kantin,
            t.jumlah, t.total, t.metode, t.status, t.catatan, t.antrian,
            u.nama AS nama_pembeli, u.kelas AS kelas_pembeli, u.username AS username_pembeli
     FROM transaksi t
     JOIN menu_req m ON m.id = t.id_menu
     LEFT JOIN users u ON u.id_user = t.id_user
     WHERE m.id_penjual = ?
     ORDER BY t.tanggal DESC, t.id_transaksi DESC");
mysqli_stmt_bind_param($stmt, "i", $id_penjual);
mysqli_stmt_execute($stmt);
$semua_trx = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
/* 8. Semua ulasan */
$stmt = mysqli_prepare($db,
    "SELECT ul.bintang, ul.komentar, ul.created_at,
            u.nama AS nama_user, u.kelas, u.username,
            m.nama_makanan, m.kategori
     FROM ulasan ul
     JOIN menu_req m ON m.id = ul.id_menu
     LEFT JOIN users u ON u.id_user = ul.id_user
     WHERE m.id_penjual = ?
     ORDER BY ul.created_at DESC");
mysqli_stmt_bind_param($stmt, "i", $id_penjual);
mysqli_stmt_execute($stmt);
$semua_ulasan = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
/* ── Nama file ── */
$nama_file_safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $penjual['nama'] ?? 'Penjual');
$kantin_ref     = intval($penjual['kantin_ref'] ?? 0);
$kantin_label   = $kantin_ref > 0 ? "Kantin_$kantin_ref" : "NonAktif";
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mengunduh Rekap Excel — <?= htmlspecialchars($penjual['nama']) ?></title>
<!-- Menggunakan xlsx-js-style untuk mendukung styling sel di browser -->
<script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js"></script>
<style>
  *{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',sans-serif;}
  body{background:#f5f5fa;min-height:100vh;display:flex;align-items:center;justify-content:center;}
  .card{background:#fff;border-radius:20px;padding:40px 36px;max-width:460px;width:90%;text-align:center;box-shadow:0 12px 40px rgba(0,0,0,0.08);border:1px solid #eee;}
  .icon{font-size:56px;margin-bottom:18px;}
  h2{font-size:20px;font-weight:800;color:#111;margin-bottom:8px;}
  p{font-size:13.5px;color:#666;line-height:1.7;margin-bottom:20px;}
  .info{background:#eff6ff;border:1px solid #dbeafe;border-radius:12px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:#1e40af;text-align:left;}
  .info b{display:block;font-size:14px;margin-bottom:4px;}
  .progress-wrap{height:8px;background:#e5e7eb;border-radius:20px;overflow:hidden;margin-bottom:12px;}
  .progress-bar{height:100%;width:0%;background:linear-gradient(90deg,#ff6a1f,#ff9a5c);border-radius:20px;transition:width .4s ease;}
  .status-txt{font-size:12.5px;color:#6b7280;margin-bottom:20px;min-height:20px;}
  .btn-back{display:inline-block;padding:10px 22px;border-radius:10px;border:1.5px solid #e5e7eb;color:#6b7280;font-size:13.5px;font-weight:600;text-decoration:none;transition:.2s;cursor:pointer;background:none;}
  .btn-back:hover{border-color:#ff6a1f;color:#ff6a1f;}
  .done-msg{display:none;background:#d1fae5;border:1px solid #6ee7b7;border-radius:12px;padding:12px 16px;font-size:13px;color:#065f46;font-weight:600;margin-bottom:20px;}
</style>
</head>
<body>
<div class="card">
  <div class="icon">📊</div>
  <h2>Mengunduh Rekap Excel</h2>
  <p>Membangun file Excel lengkap untuk penjual berikut. Proses ini berlangsung di browser Anda.</p>
  <div class="info">
    <b>🏪 <?= htmlspecialchars($penjual['nama_kantin'] ?? ('Kantin ' . $kantin_ref)) ?></b>
    Penjual: <strong><?= htmlspecialchars($penjual['nama']) ?></strong><br>
    Username: <strong><?= htmlspecialchars($penjual['username']) ?></strong>
  </div>
  <div class="progress-wrap"><div class="progress-bar" id="pb"></div></div>
  <div class="status-txt" id="stxt">Mempersiapkan data…</div>
  <div class="done-msg" id="doneMsg">✅ File Excel berhasil diunduh!</div>
  <a href="admin_kelola_penjual.php" class="btn-back">⬅ Kembali ke Kelola Penjual</a>
</div>
<script>
/* ═══════════════════════════════════════════════════
   DATA (di-inject dari PHP)
   ═══════════════════════════════════════════════════ */
const DATA = {
  penjual      : <?= json_encode($penjual,       JSON_UNESCAPED_UNICODE) ?>,
  qris         : <?= json_encode($qris,          JSON_UNESCAPED_UNICODE) ?>,
  rekap        : <?= json_encode($rekap,         JSON_UNESCAPED_UNICODE) ?>,
  menu_list    : <?= json_encode($menu_list,     JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>,
  terlaris     : <?= json_encode($terlaris,      JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>,
  harian       : <?= json_encode($harian,        JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>,
  semua_trx    : <?= json_encode($semua_trx,     JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>,
  semua_ulasan : <?= json_encode($semua_ulasan,  JSON_UNESCAPED_UNICODE) ?>,
};
const NAMA_FILE = 'Rekap_<?= $nama_file_safe ?>_<?= $kantin_label ?>_<?= date('Y-m-d') ?>.xlsx';
/* ═══════════════════════════════════════════════════
   EXCEL DESIGN SYSTEM  (identik dgn admin_beranda.php)
   ═══════════════════════════════════════════════════ */
const XL = {
  brand:'FF1E3A5F', brandMid:'FF2563EB', brandLight:'FFDBEAFE',
  greenDark:'FF065F46', green:'FF059669', greenLight:'FFD1FAE5', greenPale:'FFF0FDF4',
  redDark:'FF991B1B', red:'FFDC2626', redLight:'FFFEE2E2', redPale:'FFFFF1F0',
  yellowDark:'FF92400E', yellow:'FFD97706', yellowLight:'FFFEF3C7', yellowPale:'FFFFFBEB',
  purpleDark:'FF5B21B6', purpleMid:'FF7C3AED', purpleLight:'FFEDE9FE',
  white:'FFFFFFFF', gray50:'FFF9FAFB', gray100:'FFF3F4F6', gray200:'FFE5E7EB',
  gray400:'FF9CA3AF', gray600:'FF4B5563', gray800:'FF1F2937', black:'FF111827',
  hdr1:'FF1E3A5F', hdr2:'FF1D4ED8', accent:'FFFF6B35',
  orangeSoft:'FFFFF7ED', orangeDark:'FFEA580C',
};
function bdr(style,color){return{style:style||'thin',color:{rgb:color||XL.gray200}};}
function fullBdr(style,color){const b=bdr(style,color);return{top:b,bottom:b,left:b,right:b};}
function noBdr(){const n=bdr('none','FFFFFFFF');return{top:n,bottom:n,left:n,right:n};}
function xlCell(value,opts){
  opts=opts||{};
  return{v:value,t:(typeof value==='number'?'n':'s'),s:{
    font:{name:'Calibri',sz:opts.sz||10,bold:!!opts.bold,italic:!!opts.italic,color:{rgb:opts.color||XL.gray800}},
    fill:{patternType:'solid',fgColor:{rgb:opts.bg||XL.white}},
    alignment:{horizontal:opts.h||'left',vertical:opts.v||'center',wrapText:!!opts.wrap,indent:opts.indent||0},
    border:opts.border||fullBdr('thin',XL.gray200)
  }};
}
function eRow(n){return Array(n).fill(xlCell('',{border:noBdr()}));}
function sepRow(n,color){return Array(n).fill(xlCell('',{bg:color||XL.gray200,sz:4,border:noBdr()}));}
/* Preset builders */
function TITLE(v,bg){return xlCell(v,{sz:16,bold:true,color:XL.white,bg:bg||XL.brand,h:'center',v:'center',border:fullBdr('medium',bg||XL.brand)});}
function SUBTITLE(v){return xlCell(v,{sz:9,italic:true,color:XL.gray600,bg:XL.gray100,h:'center',border:fullBdr('thin',XL.gray200)});}
function SECTION(v,bg,color){return xlCell(v,{sz:11,bold:true,color:color||XL.white,bg:bg||XL.brandMid,h:'left',indent:1,border:fullBdr('medium',bg||XL.brandMid)});}
function COL_HDR(v,bg,color){return xlCell(v,{sz:9,bold:true,color:color||XL.white,bg:bg||XL.hdr1,h:'center',v:'center',border:fullBdr('medium',XL.gray800)});}
function LABEL(v){return xlCell(v,{sz:10,bold:true,color:XL.brand,bg:'FFEFF6FF',h:'left',indent:1,border:fullBdr('thin',XL.gray200)});}
function VAL(v,opts){return xlCell(v,Object.assign({sz:10,color:XL.gray800,bg:XL.white,h:'left',indent:1},opts||{}));}
function BADGE(v,bg,color){return xlCell(v,{sz:9,bold:true,color:color||XL.white,bg:bg||XL.brandMid,h:'center',border:fullBdr('thin',bg||XL.brandMid)});}
function NUM(v,bg){return xlCell(v,{sz:9,color:XL.gray400,bg:bg||XL.white,h:'center',border:fullBdr('thin',XL.gray200)});}
function TOTAL_LABEL(v,bg){return xlCell(v,{sz:10,bold:true,color:XL.white,bg:bg||XL.hdr1,h:'right',indent:1,border:fullBdr('medium',bg||XL.hdr1)});}
function TOTAL_VAL(v,opts){return xlCell(v,Object.assign({sz:12,bold:true,color:XL.accent,bg:XL.brandLight,h:'center',border:fullBdr('medium',XL.brandMid)},opts||{}));}
/* Helpers */
function nowStr(){const d=new Date();return d.toLocaleDateString('id-ID',{day:'2-digit',month:'long',year:'numeric'})+', '+d.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'});}
function rupiah(n){return 'Rp '+parseInt(n||0).toLocaleString('id-ID');}
function capStr(s){return s?s.charAt(0).toUpperCase()+s.slice(1).toLowerCase():'-';}
function statusBadge(sts){
  const s=(sts||'').toLowerCase();
  if(s==='lunas')              return[XL.greenLight,XL.greenDark,'✓ Lunas'];
  if(s==='ditolak')            return[XL.redLight,XL.redDark,'✗ Ditolak'];
  if(s==='batal')              return[XL.redLight,XL.redDark,'✗ Batal'];
  if(s==='pending')            return[XL.yellowLight,XL.yellowDark,'⏳ Pending'];
  if(s==='dimasak')            return[XL.purpleLight,XL.purpleMid,'🔥 Dimasak'];
  if(s==='siap')               return[XL.brandLight,XL.brand,'✔ Siap'];
  return[XL.gray100,XL.gray600,capStr(sts)];
}
function katBadge(kat){
  const k=(kat||'').toUpperCase();
  if(k==='MINUMAN') return[XL.brandLight,XL.brand,'🥤 Minuman'];
  if(k==='SNACK')   return[XL.purpleLight,XL.purpleMid,'🍿 Snack'];
  return[XL.orangeSoft,XL.orangeDark,'🍽 Makanan'];
}
function menuStatusBadge(m){
  if(m.deleted_at) return[XL.redLight,XL.redDark,'🗑 Dihapus'];
  if(!m.aktif)     return[XL.yellowLight,XL.yellowDark,'⏸ Nonaktif'];
  return[XL.greenLight,XL.greenDark,'🟢 Aktif'];
}
function buildSheet(wb, sheetName, aoa, merges, colWidths, rowHeights){
  const ws = XLSX.utils.aoa_to_sheet(aoa, {cellStyles:true});
  ws['!merges'] = merges||[];
  ws['!cols']   = colWidths.map(w=>({wch:w}));
  ws['!rows']   = (rowHeights||[]).length
    ? rowHeights
    : aoa.map((_,i)=>i===0?{hpt:36}:i===1?{hpt:20}:{hpt:22});
  XLSX.utils.book_append_sheet(wb, ws, sheetName);
}
/* ═══════════════════════════════════════════════════
   PROGRESS helper
   ═══════════════════════════════════════════════════ */
function setProgress(pct, txt){
  document.getElementById('pb').style.width   = pct+'%';
  document.getElementById('stxt').textContent = txt;
}
/* ═══════════════════════════════════════════════════
   SHEET 1: PROFIL & RINGKASAN
   ═══════════════════════════════════════════════════ */
function buildSheetProfil(wb){
  const p  = DATA.penjual;
  const r  = DATA.rekap;
  const q  = DATA.qris;
  const C  = 8;
  const aoa=[], mrg=[];
  const kantinLabel = p.nama_kantin || (p.kantin_ref?'Kantin '+p.kantin_ref:'—');
  /* Header */
  aoa.push([TITLE('E-CANTEEN ESEMKITA — REKAP LENGKAP PENJUAL: '+p.nama.toUpperCase()),...Array(C-1).fill(TITLE(''))]);
  mrg.push({s:{r:0,c:0},e:{r:0,c:C-1}});
  aoa.push([SUBTITLE('Dicetak: '+nowStr()+'   |   Admin: '+<?= json_encode($_SESSION['username']) ?>),...Array(C-1).fill(SUBTITLE(''))]);
  mrg.push({s:{r:1,c:0},e:{r:1,c:C-1}});
  aoa.push(sepRow(C));
  /* ── PROFIL ── */
  const profStart=aoa.length;
  aoa.push([SECTION('👤  PROFIL PENJUAL',XL.brand),...Array(C-1).fill(SECTION('',XL.brand))]);
  mrg.push({s:{r:profStart,c:0},e:{r:profStart,c:C-1}});
  const profFields=[
    ['Nama Lengkap', p.nama||'-'],
    ['Username',     p.username||'-'],
    ['Email',        p.email||'-'],
    ['No. Telepon',  p.no_tlp||'-'],
    ['Kantin',       kantinLabel],
    ['Status Akun',  p.deleted_at ? '🗑 Dihapus (soft delete)' : (p.status==0 ? '🔴 Nonaktif' : '🟢 Aktif')],
    ['Status Buka',  p.buka==1 ? '● Buka' : '● Tutup'],
  ];
  profFields.forEach(([lb,vl])=>{
    const r2=aoa.length;
    const isBuka  = lb==='Status Buka';
    const isAktif = lb==='Status Akun';
    let valCell;
    if(isBuka){
      const[bg,fg,txt]=p.buka==1?[XL.greenLight,XL.greenDark,'● Buka']:[XL.redLight,XL.redDark,'● Tutup'];
      valCell=BADGE(txt,bg,fg);
    } else if(isAktif){
      const aktBg=p.deleted_at?XL.gray100:(p.status==0?XL.yellowLight:XL.greenLight);
      const aktFg=p.deleted_at?XL.gray600:(p.status==0?XL.yellowDark:XL.greenDark);
      valCell=BADGE(String(vl),aktBg,aktFg);
    } else {
      valCell=VAL(String(vl));
    }
    aoa.push([LABEL(lb),valCell,...Array(C-2).fill(xlCell('',{bg:XL.white,border:fullBdr('thin',XL.gray200)}))]);
    mrg.push({s:{r:r2,c:1},e:{r:r2,c:C-1}});
  });
  /* QRIS */
  const qrisR=aoa.length;
  const qrisTxt = q.id_qris ? (q.verified==1 ? '✅ Terverifikasi ('+( q.verified_at?q.verified_at.slice(0,10):'' )+')' : '⏳ Menunggu Verifikasi') : '— Belum Ada QRIS';
  const[qBg,qFg]=q.id_qris ? (q.verified==1?[XL.greenLight,XL.greenDark]:[XL.yellowLight,XL.yellowDark]) : [XL.gray100,XL.gray600];
  aoa.push([LABEL('Status QRIS'),BADGE(qrisTxt,qBg,qFg),...Array(C-2).fill(xlCell('',{bg:XL.white,border:fullBdr('thin',XL.gray200)}))]);
  mrg.push({s:{r:qrisR,c:1},e:{r:qrisR,c:C-1}});
  aoa.push(sepRow(C));
  /* ── RINGKASAN TRANSAKSI ── */
  if(r && r.total_trx>0){
    const sumStart=aoa.length;
    aoa.push([SECTION('📊  RINGKASAN TRANSAKSI',XL.brandMid),...Array(C-1).fill(SECTION('',XL.brandMid))]);
    mrg.push({s:{r:sumStart,c:0},e:{r:sumStart,c:C-1}});
    /* Baris statistik kotak 4 kolom */
    aoa.push([
      COL_HDR('Total Pendapatan',XL.green),COL_HDR('',XL.green),
      COL_HDR('Total Transaksi',XL.brandMid),
      COL_HDR('Item Terjual',XL.hdr1),
      COL_HDR('Trx Terbesar',XL.accent),COL_HDR('',XL.accent),
      COL_HDR('Rata-rata/Trx',XL.purpleMid),COL_HDR('',XL.purpleMid),
    ]);
    const statR=aoa.length;
    aoa.push([
      xlCell(rupiah(r.total_pendapatan),{sz:13,bold:true,color:XL.greenDark,bg:XL.greenLight,h:'center',border:fullBdr('medium',XL.green)}),
      xlCell('',{bg:XL.greenLight,border:fullBdr('medium',XL.green)}),
      xlCell(parseInt(r.total_trx||0),{sz:13,bold:true,color:XL.white,bg:XL.brandMid,h:'center',border:fullBdr('medium',XL.brand)}),
      xlCell(parseInt(r.total_item||0)+' item',{sz:12,bold:true,color:XL.white,bg:XL.hdr1,h:'center',border:fullBdr('medium',XL.hdr1)}),
      xlCell(rupiah(r.trx_terbesar),{sz:12,bold:true,color:XL.orangeDark,bg:XL.orangeSoft,h:'center',border:fullBdr('medium',XL.orangeDark)}),
      xlCell('',{bg:XL.orangeSoft,border:fullBdr('medium',XL.orangeDark)}),
      xlCell(rupiah(r.rata_trx||0),{sz:12,bold:true,color:XL.purpleDark,bg:XL.purpleLight,h:'center',border:fullBdr('medium',XL.purpleMid)}),
      xlCell('',{bg:XL.purpleLight,border:fullBdr('medium',XL.purpleMid)}),
    ]);
    [[statR-1,0],[statR-1,4],[statR-1,6],[statR,0],[statR,4],[statR,6]].forEach(([rr,ci])=>mrg.push({s:{r:rr,c:ci},e:{r:rr,c:ci+1}}));
    aoa.push(eRow(C));
    /* Status breakdown */
    aoa.push([COL_HDR('✅ Lunas','FF059669'),COL_HDR('❌ Batal',XL.red),COL_HDR('🚫 Ditolak',XL.redDark),COL_HDR('⏳ Pending',XL.yellow),COL_HDR('🔥 Dimasak',XL.purpleMid),COL_HDR('✔ Siap',XL.brandMid),COL_HDR('Success Rate','FF059669'),COL_HDR('',  'FF059669')]);
    const sr2=aoa.length;
    const trxTot=parseInt(r.total_trx||0)||1;
    const sRate=Math.round((parseInt(r.total_lunas||0)/trxTot)*100);
    aoa.push([
      xlCell(parseInt(r.total_lunas||0),  {sz:12,bold:true,color:XL.greenDark,bg:XL.greenLight,h:'center',border:fullBdr('medium','FF059669')}),
      xlCell(parseInt(r.total_batal||0),  {sz:12,bold:true,color:XL.redDark,bg:XL.redLight,h:'center',border:fullBdr('medium',XL.red)}),
      xlCell(parseInt(r.total_ditolak||0),{sz:12,bold:true,color:XL.redDark,bg:XL.redPale,h:'center',border:fullBdr('medium',XL.red)}),
      xlCell(parseInt(r.total_pending||0),{sz:12,bold:true,color:XL.yellowDark,bg:XL.yellowLight,h:'center',border:fullBdr('medium',XL.yellow)}),
      xlCell(parseInt(r.total_dimasak||0),{sz:12,bold:true,color:XL.purpleDark,bg:XL.purpleLight,h:'center',border:fullBdr('medium',XL.purpleMid)}),
      xlCell(parseInt(r.total_siap||0),   {sz:12,bold:true,color:XL.brand,bg:XL.brandLight,h:'center',border:fullBdr('medium',XL.brandMid)}),
      xlCell(sRate+'%',{sz:13,bold:true,color:XL.greenDark,bg:XL.greenLight,h:'center',border:fullBdr('medium','FF059669')}),
      xlCell('',{bg:XL.greenLight,border:fullBdr('medium','FF059669')}),
    ]);
    mrg.push({s:{r:sr2,c:6},e:{r:sr2,c:7}});
    aoa.push(eRow(C));
    /* Metode pembayaran */
    const pdpCash=parseFloat(r.pdp_cash||0), pdpQris=parseFloat(r.pdp_qris||0);
    const totalM=pdpCash+pdpQris;
    aoa.push([COL_HDR('💵 CASH',XL.green),COL_HDR('',XL.green),COL_HDR('',XL.green),COL_HDR('📲 QRIS',XL.brandMid),COL_HDR('',XL.brandMid),COL_HDR('',XL.brandMid),COL_HDR('Total Semua Metode',XL.hdr1),COL_HDR('',XL.hdr1)]);
    const m2R=aoa.length;
    aoa.push([
      xlCell(rupiah(pdpCash),{sz:12,bold:true,color:XL.greenDark,bg:XL.greenLight,h:'center',border:fullBdr('medium',XL.green)}),
      xlCell('',{bg:XL.greenLight,border:fullBdr('medium',XL.green)}),
      xlCell('',{bg:XL.greenLight,border:fullBdr('medium',XL.green)}),
      xlCell(rupiah(pdpQris),{sz:12,bold:true,color:XL.brand,bg:XL.brandLight,h:'center',border:fullBdr('medium',XL.brandMid)}),
      xlCell('',{bg:XL.brandLight,border:fullBdr('medium',XL.brandMid)}),
      xlCell('',{bg:XL.brandLight,border:fullBdr('medium',XL.brandMid)}),
      xlCell(rupiah(totalM),{sz:13,bold:true,color:XL.white,bg:XL.hdr1,h:'center',border:fullBdr('medium',XL.brand)}),
      xlCell('',{bg:XL.hdr1,border:fullBdr('medium',XL.brand)}),
    ]);
    [[m2R-1,0],[m2R-1,3],[m2R-1,6],[m2R,0],[m2R,3],[m2R,6]].forEach(([rr,ci])=>{
      const e=ci===6?1:2; mrg.push({s:{r:rr,c:ci},e:{r:rr,c:ci+e}});
    });
    /* Periode */
    aoa.push(eRow(C));
    const perR=aoa.length;
    aoa.push([LABEL('Transaksi Pertama'),VAL(r.trx_pertama?r.trx_pertama.slice(0,16):'-'),...Array(C-2).fill(xlCell('',{bg:XL.white,border:fullBdr('thin',XL.gray200)}))]);
    mrg.push({s:{r:perR,c:1},e:{r:perR,c:C-1}});
    const terR=aoa.length;
    aoa.push([LABEL('Transaksi Terakhir'),VAL(r.trx_terakhir?r.trx_terakhir.slice(0,16):'-'),...Array(C-2).fill(xlCell('',{bg:XL.white,border:fullBdr('thin',XL.gray200)}))]);
    mrg.push({s:{r:terR,c:1},e:{r:terR,c:C-1}});
  } else {
    const noR=aoa.length;
    aoa.push([xlCell('Belum ada data transaksi untuk penjual ini.',{sz:11,italic:true,color:XL.gray400,h:'center',bg:XL.gray50,border:fullBdr('thin',XL.gray200)}),...Array(C-1).fill(xlCell('',{bg:XL.gray50,border:fullBdr('thin',XL.gray200)}))]);
    mrg.push({s:{r:noR,c:0},e:{r:noR,c:C-1}});
  }
  buildSheet(wb,'Profil & Ringkasan',aoa,mrg,[20,26,24,12,18,12,18,14]);
}
/* ═══════════════════════════════════════════════════
   SHEET 2: SEMUA MENU
   ═══════════════════════════════════════════════════ */
function buildSheetMenu(wb){
  const p = DATA.penjual;
  const C = 13;
  const aoa=[], mrg=[];
  aoa.push([TITLE('DAFTAR SEMUA MENU — '+p.nama.toUpperCase()),...Array(C-1).fill(TITLE(''))]);
  mrg.push({s:{r:0,c:0},e:{r:0,c:C-1}});
  aoa.push([SUBTITLE('Mencakup menu aktif, nonaktif, dan terhapus. Dicetak: '+nowStr()),...Array(C-1).fill(SUBTITLE(''))]);
  mrg.push({s:{r:1,c:0},e:{r:1,c:C-1}});
  aoa.push(sepRow(C));
  /* Ringkasan cepat */
  const ml = DATA.menu_list;
  const aktif    = ml.filter(m=>!m.deleted_at&&m.aktif==1).length;
  const nonaktif = ml.filter(m=>!m.deleted_at&&m.aktif==0).length;
  const dihapus  = ml.filter(m=>!!m.deleted_at).length;
  const totalOmset = ml.reduce((s,m)=>s+parseFloat(m.omset||0),0);
  const totalTerjual = ml.reduce((s,m)=>s+parseInt(m.item_terjual_trx||0),0);
  const sR=aoa.length;
  aoa.push([COL_HDR('Total Menu',XL.brand),COL_HDR('',XL.brand),COL_HDR('Aktif','FF059669'),COL_HDR('Nonaktif',XL.yellow),COL_HDR('Dihapus',XL.red),COL_HDR('',XL.red),COL_HDR('Total Terjual',XL.brandMid),COL_HDR('',XL.brandMid),COL_HDR('Total Omset',XL.green),COL_HDR('',XL.green),COL_HDR('',XL.green),COL_HDR('',XL.green),COL_HDR('',XL.green)]);
  aoa.push([
    xlCell(ml.length,{sz:14,bold:true,color:XL.brand,bg:XL.brandLight,h:'center',border:fullBdr('medium',XL.brandMid)}),
    xlCell('',{bg:XL.brandLight,border:fullBdr('medium',XL.brandMid)}),
    xlCell(aktif,{sz:14,bold:true,color:XL.greenDark,bg:XL.greenLight,h:'center',border:fullBdr('medium','FF059669')}),
    xlCell(nonaktif,{sz:14,bold:true,color:XL.yellowDark,bg:XL.yellowLight,h:'center',border:fullBdr('medium',XL.yellow)}),
    xlCell(dihapus,{sz:14,bold:true,color:XL.redDark,bg:XL.redLight,h:'center',border:fullBdr('medium',XL.red)}),
    xlCell('',{bg:XL.redLight,border:fullBdr('medium',XL.red)}),
    xlCell(totalTerjual+' item',{sz:13,bold:true,color:XL.white,bg:XL.brandMid,h:'center',border:fullBdr('medium',XL.brand)}),
    xlCell('',{bg:XL.brandMid,border:fullBdr('medium',XL.brand)}),
    xlCell(rupiah(totalOmset),{sz:13,bold:true,color:XL.greenDark,bg:XL.greenLight,h:'center',border:fullBdr('medium',XL.green)}),
    xlCell('',{bg:XL.greenLight,border:fullBdr('medium',XL.green)}),xlCell('',{bg:XL.greenLight,border:fullBdr('medium',XL.green)}),xlCell('',{bg:XL.greenLight,border:fullBdr('medium',XL.green)}),xlCell('',{bg:XL.greenLight,border:fullBdr('medium',XL.green)}),
  ]);
  [[sR,0],[sR,4],[sR,6],[sR,8],[sR+1,0],[sR+1,4],[sR+1,6],[sR+1,8]].forEach(([rr,ci])=>{
    const e=ci===4?1:ci===8?4:1; mrg.push({s:{r:rr,c:ci},e:{r:rr,c:ci+e}});
  });
  aoa.push(sepRow(C));
  /* Tabel menu */
  const tS=aoa.length;
  aoa.push([SECTION('📋  DETAIL SEMUA MENU',XL.hdr1),...Array(C-1).fill(SECTION('',XL.hdr1))]);
  mrg.push({s:{r:tS,c:0},e:{r:tS,c:C-1}});
  aoa.push([
    COL_HDR('No'), COL_HDR('Nama Menu'), COL_HDR('Kategori'), COL_HDR('Status'),
    COL_HDR('Harga'), COL_HDR('Stok'), COL_HDR('Terjual\n(counter)'),
    COL_HDR('Terjual\n(trx)'), COL_HDR('Omset Lunas'),
    COL_HDR('CASH'), COL_HDR('QRIS'),
    COL_HDR('⭐ Rating'), COL_HDR('Terdaftar'),
  ]);
  ml.forEach((m,i)=>{
    const isAlt=i%2===1;
    const isDel=!!m.deleted_at; const isNon=!isDel&&m.aktif==0;
    const rowBg=isDel?XL.redPale:(isNon?XL.yellowPale:(isAlt?'FFF0F7FF':XL.white));
    const[stBg,stFg,stTxt]=menuStatusBadge(m);
    const[kBg,kFg,kTxt]=katBadge(m.kategori);
    const omset=parseFloat(m.omset||0); const terjualTrx=parseInt(m.item_terjual_trx||0);
    const rating=parseFloat(m.rata_bintang||0);
    aoa.push([
      NUM(i+1,rowBg),
      xlCell(m.nama_makanan||'-',{sz:10,bold:true,color:XL.brand,bg:rowBg,indent:1,border:fullBdr('thin',XL.gray200)}),
      BADGE(kTxt,kBg,kFg),
      BADGE(stTxt,stBg,stFg),
      xlCell(rupiah(m.harga),{sz:10,bold:true,color:XL.brand,bg:rowBg,h:'right',indent:1,border:fullBdr('thin',XL.gray200)}),
      xlCell(parseInt(m.stok||0),{sz:10,color:parseInt(m.stok||0)===0?XL.red:XL.gray800,bg:rowBg,h:'center',border:fullBdr('thin',XL.gray200)}),
      xlCell(parseInt(m.terjual||0),{sz:10,color:XL.gray600,bg:rowBg,h:'center',border:fullBdr('thin',XL.gray200)}),
      xlCell(terjualTrx,{sz:10,bold:terjualTrx>0,color:terjualTrx>0?XL.brandMid:XL.gray400,bg:rowBg,h:'center',border:fullBdr('thin',XL.gray200)}),
      xlCell(omset>0?rupiah(omset):'—',{sz:10,bold:omset>0,color:omset>0?XL.greenDark:XL.gray400,bg:rowBg,h:'right',indent:1,border:fullBdr('thin',XL.gray200)}),
      xlCell(parseFloat(m.omset_cash||0)>0?rupiah(m.omset_cash):'—',{sz:9,color:XL.green,bg:rowBg,h:'right',indent:1,border:fullBdr('thin',XL.gray200)}),
      xlCell(parseFloat(m.omset_qris||0)>0?rupiah(m.omset_qris):'—',{sz:9,color:XL.brand,bg:rowBg,h:'right',indent:1,border:fullBdr('thin',XL.gray200)}),
      xlCell(rating>0?rating.toFixed(1)+' ⭐ ('+m.jumlah_ulasan+')':'—',{sz:9,color:rating>0?XL.yellowDark:XL.gray400,bg:rowBg,h:'center',border:fullBdr('thin',XL.gray200)}),
      xlCell(m.created_at?m.created_at.slice(0,10):'-',{sz:9,color:XL.gray400,bg:rowBg,h:'center',border:fullBdr('thin',XL.gray200)}),
    ]);
  });
  const fR=aoa.length;
  aoa.push([
    TOTAL_LABEL('TOTAL',XL.hdr1),TOTAL_LABEL('',XL.hdr1),TOTAL_LABEL('',XL.hdr1),TOTAL_LABEL('',XL.hdr1),
    TOTAL_LABEL('',XL.hdr1),TOTAL_LABEL('',XL.hdr1),TOTAL_LABEL('',XL.hdr1),
    TOTAL_VAL(totalTerjual+' item'),
    TOTAL_VAL(rupiah(totalOmset),{color:XL.greenDark}),
    TOTAL_LABEL('',XL.hdr1),TOTAL_LABEL('',XL.hdr1),TOTAL_LABEL('',XL.hdr1),TOTAL_LABEL('',XL.hdr1),
  ]);
  mrg.push({s:{r:fR,c:0},e:{r:fR,c:6}});
  buildSheet(wb,'Semua Menu',aoa,mrg,[5,28,12,12,14,8,10,10,16,14,14,14,12]);
}
/* ═══════════════════════════════════════════════════
   SHEET 3: SEMUA TRANSAKSI
   ═══════════════════════════════════════════════════ */
function buildSheetTrx(wb){
  const p = DATA.penjual;
  const trxList = DATA.semua_trx;
  const C = 10;
  const aoa=[], mrg=[];
  aoa.push([TITLE('SEMUA TRANSAKSI — '+p.nama.toUpperCase()),...Array(C-1).fill(TITLE(''))]);
  mrg.push({s:{r:0,c:0},e:{r:0,c:C-1}});
  aoa.push([SUBTITLE('Total: '+trxList.length+' transaksi. Dicetak: '+nowStr()),...Array(C-1).fill(SUBTITLE(''))]);
  mrg.push({s:{r:1,c:0},e:{r:1,c:C-1}});
  aoa.push(sepRow(C));
  const tS=aoa.length;
  aoa.push([SECTION('🧾  RIWAYAT TRANSAKSI (Terbaru ke Terlama)',XL.hdr1),...Array(C-1).fill(SECTION('',XL.hdr1))]);
  mrg.push({s:{r:tS,c:0},e:{r:tS,c:C-1}});
  aoa.push([
    COL_HDR('No'),COL_HDR('Antrian'),COL_HDR('Tanggal'),COL_HDR('Nama Menu'),
    COL_HDR('Pembeli'),COL_HDR('Kelas'),COL_HDR('Qty'),COL_HDR('Total'),
    COL_HDR('Metode'),COL_HDR('Status'),
  ]);
  let grandTotal=0;
  trxList.forEach((t,i)=>{
    const isAlt=i%2===1;const rowBg=isAlt?'FFF0F7FF':XL.white;
    const[sBg,sFg,sTxt]=statusBadge(t.status);
    const tot=parseFloat(t.total||0);
    if((t.status||'').toLowerCase()==='lunas') grandTotal+=tot;
    const metBg=t.metode==='CASH'?XL.greenLight:(t.metode==='QRIS'?XL.brandLight:XL.gray100);
    const metFg=t.metode==='CASH'?XL.greenDark:(t.metode==='QRIS'?XL.brand:XL.gray600);
    aoa.push([
      NUM(i+1,rowBg),
      xlCell(t.antrian||'-',{sz:10,color:XL.gray600,bg:rowBg,h:'center',border:fullBdr('thin',XL.gray200)}),
      xlCell(t.tanggal?t.tanggal.slice(0,16):'-',{sz:9,color:XL.gray600,bg:rowBg,h:'center',border:fullBdr('thin',XL.gray200)}),
      xlCell(t.nama_makanan||'-',{sz:10,bold:true,color:XL.brand,bg:rowBg,indent:1,border:fullBdr('thin',XL.gray200)}),
      xlCell(t.nama_pembeli||'-',{sz:10,color:XL.gray800,bg:rowBg,indent:1,border:fullBdr('thin',XL.gray200)}),
      xlCell(t.kelas_pembeli||'-',{sz:9,color:XL.gray600,bg:rowBg,h:'center',border:fullBdr('thin',XL.gray200)}),
      xlCell(parseInt(t.jumlah||0),{sz:10,bold:true,color:XL.gray800,bg:rowBg,h:'center',border:fullBdr('thin',XL.gray200)}),
      xlCell(rupiah(tot),{sz:10,bold:true,color:XL.brand,bg:rowBg,h:'right',indent:1,border:fullBdr('thin',XL.gray200)}),
      BADGE(t.metode||'-',metBg,metFg),
      BADGE(sTxt,sBg,sFg),
    ]);
  });
  if(trxList.length===0){
    const noR=aoa.length;
    aoa.push([xlCell('Belum ada transaksi.',{sz:11,italic:true,color:XL.gray400,h:'center',bg:XL.gray50,border:fullBdr('thin',XL.gray200)}),...Array(C-1).fill(xlCell('',{bg:XL.gray50,border:fullBdr('thin',XL.gray200)}))]);
    mrg.push({s:{r:noR,c:0},e:{r:noR,c:C-1}});
  } else {
    const fR=aoa.length;
    aoa.push([
      TOTAL_LABEL('TOTAL PENDAPATAN (Lunas)',XL.hdr1),TOTAL_LABEL('',XL.hdr1),TOTAL_LABEL('',XL.hdr1),
      TOTAL_LABEL('',XL.hdr1),TOTAL_LABEL('',XL.hdr1),TOTAL_LABEL('',XL.hdr1),TOTAL_LABEL('',XL.hdr1),
      TOTAL_VAL(rupiah(grandTotal),{color:XL.greenDark}),
      TOTAL_LABEL('',XL.hdr1),TOTAL_LABEL('',XL.hdr1),
    ]);
    mrg.push({s:{r:fR,c:0},e:{r:fR,c:6}});
  }
  buildSheet(wb,'Semua Transaksi',aoa,mrg,[5,9,16,28,20,10,7,16,10,14]);
}
/* ═══════════════════════════════════════════════════
   SHEET 4: MENU TERLARIS
   ═══════════════════════════════════════════════════ */
function buildSheetTerlaris(wb){
  const p = DATA.penjual;
  const tl = DATA.terlaris;
  const C = 6;
  const aoa=[], mrg=[];
  aoa.push([TITLE('TOP MENU TERLARIS — '+p.nama.toUpperCase()),...Array(C-1).fill(TITLE(''))]);
  mrg.push({s:{r:0,c:0},e:{r:0,c:C-1}});
  aoa.push([SUBTITLE('Berdasarkan transaksi lunas. Dicetak: '+nowStr()),...Array(C-1).fill(SUBTITLE(''))]);
  mrg.push({s:{r:1,c:0},e:{r:1,c:C-1}});
  aoa.push(sepRow(C));
  const tS=aoa.length;
  aoa.push([SECTION('🌟  PERINGKAT MENU TERLARIS',XL.hdr1),...Array(C-1).fill(SECTION('',XL.hdr1))]);
  mrg.push({s:{r:tS,c:0},e:{r:tS,c:C-1}});
  aoa.push([COL_HDR('Peringkat'),COL_HDR('Nama Menu'),COL_HDR('Qty Terjual'),COL_HDR('Omset'),COL_HDR('Total Trx'),COL_HDR('Success Rate')]);
  if(tl.length===0){
    const noR=aoa.length;
    aoa.push([xlCell('Belum ada data penjualan.',{sz:11,italic:true,color:XL.gray400,h:'center',bg:XL.gray50,border:fullBdr('thin',XL.gray200)}),...Array(C-1).fill(xlCell('',{bg:XL.gray50,border:fullBdr('thin',XL.gray200)}))]);
    mrg.push({s:{r:noR,c:0},e:{r:noR,c:C-1}});
  } else {
    const rankColors=['FF92400E','FF6B7280','FFCD7F32'];
    const rankBgs=[XL.yellowPale,XL.gray50,'FFFFF7ED'];
    tl.forEach((m,i)=>{
      const rank=i+1;
      const rankBg=rank<=3?rankBgs[i]:XL.white;
      const rankFg=rank<=3?rankColors[i]:XL.gray600;
      const rankTxt=rank===1?'🥇 #1':rank===2?'🥈 #2':rank===3?'🥉 #3':('#'+rank);
      const sr=parseInt(m.trx_total||0)>0?Math.round((parseInt(m.lunas||0)/parseInt(m.trx_total||0))*100):0;
      const srColor=sr>=80?XL.greenDark:sr>=50?XL.yellowDark:XL.red;
      aoa.push([
        xlCell(rankTxt,{sz:11,bold:true,color:rankFg,bg:rankBg,h:'center',border:fullBdr(rank<=3?'medium':'thin',rank<=3?rankColors[i]:XL.gray200)}),
        xlCell(m.nama_makanan||'-',{sz:11,bold:true,color:XL.brand,bg:rankBg,indent:1,border:fullBdr('thin',XL.gray200)}),
        xlCell(parseInt(m.qty||0)+' item',{sz:12,bold:true,color:XL.brand,bg:rankBg,h:'center',border:fullBdr('thin',XL.gray200)}),
        xlCell(rupiah(m.omset),{sz:11,bold:true,color:XL.greenDark,bg:rankBg,h:'right',indent:1,border:fullBdr('thin',XL.gray200)}),
        xlCell(parseInt(m.trx_total||0),{sz:10,color:XL.gray600,bg:rankBg,h:'center',border:fullBdr('thin',XL.gray200)}),
        xlCell(sr+'%',{sz:11,bold:true,color:srColor,bg:rankBg,h:'center',border:fullBdr('thin',XL.gray200)}),
      ]);
    });
  }
  buildSheet(wb,'Menu Terlaris',aoa,mrg,[14,32,14,20,12,14]);
}
/* ═══════════════════════════════════════════════════
   SHEET 5: PENDAPATAN HARIAN
   ═══════════════════════════════════════════════════ */
function buildSheetHarian(wb){
  const p = DATA.penjual;
  const h = DATA.harian;
  const C = 7;
  const aoa=[], mrg=[];
  aoa.push([TITLE('PENDAPATAN HARIAN 30 HARI — '+p.nama.toUpperCase()),...Array(C-1).fill(TITLE(''))]);
  mrg.push({s:{r:0,c:0},e:{r:0,c:C-1}});
  aoa.push([SUBTITLE('Periode: 30 hari terakhir. Dicetak: '+nowStr()),...Array(C-1).fill(SUBTITLE(''))]);
  mrg.push({s:{r:1,c:0},e:{r:1,c:C-1}});
  aoa.push(sepRow(C));
  const maxPdp = h.length>0 ? Math.max(...h.map(d=>parseFloat(d.pdp||0))) : 1;
  const tS=aoa.length;
  aoa.push([SECTION('📅  PENDAPATAN PER HARI',XL.hdr1),...Array(C-1).fill(SECTION('',XL.hdr1))]);
  mrg.push({s:{r:tS,c:0},e:{r:tS,c:C-1}});
  aoa.push([COL_HDR('No'),COL_HDR('Tanggal'),COL_HDR('Hari'),COL_HDR('Pendapatan Lunas'),COL_HDR('CASH'),COL_HDR('QRIS'),COL_HDR('Trx Lunas')]);
  if(h.length===0){
    const noR=aoa.length;
    aoa.push([xlCell('Tidak ada transaksi dalam 30 hari terakhir.',{sz:11,italic:true,color:XL.gray400,h:'center',bg:XL.gray50,border:fullBdr('thin',XL.gray200)}),...Array(C-1).fill(xlCell('',{bg:XL.gray50,border:fullBdr('thin',XL.gray200)}))]);
    mrg.push({s:{r:noR,c:0},e:{r:noR,c:C-1}});
  } else {
    const hariMap=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    let grandPdp=0, grandTrx=0;
    h.forEach((d,i)=>{
      const pdp=parseFloat(d.pdp||0);
      grandPdp+=pdp; grandTrx+=parseInt(d.trx_lunas||0);
      const isAlt=i%2===1; const rowBg=isAlt?'FFF0F7FF':XL.white;
      const pct=maxPdp>0?Math.round((pdp/maxPdp)*100):0;
      const pdpColor=pct>=80?XL.greenDark:pct>=50?XL.brand:XL.gray600;
      const tglObj=new Date(d.tgl);
      const hariStr=hariMap[tglObj.getDay()];
      aoa.push([
        NUM(i+1,rowBg),
        xlCell(d.tgl||'-',{sz:10,color:XL.gray600,bg:rowBg,h:'center',border:fullBdr('thin',XL.gray200)}),
        xlCell(hariStr,{sz:10,color:XL.gray800,bg:rowBg,h:'center',border:fullBdr('thin',XL.gray200)}),
        xlCell(rupiah(pdp),{sz:11,bold:pdp>0,color:pdpColor,bg:rowBg,h:'right',indent:1,border:fullBdr('thin',XL.gray200)}),
        xlCell(parseFloat(d.pdp_cash||0)>0?rupiah(d.pdp_cash):'—',{sz:10,color:XL.green,bg:rowBg,h:'right',indent:1,border:fullBdr('thin',XL.gray200)}),
        xlCell(parseFloat(d.pdp_qris||0)>0?rupiah(d.pdp_qris):'—',{sz:10,color:XL.brand,bg:rowBg,h:'right',indent:1,border:fullBdr('thin',XL.gray200)}),
        xlCell(parseInt(d.trx_lunas||0),{sz:10,bold:true,color:XL.brandMid,bg:rowBg,h:'center',border:fullBdr('thin',XL.gray200)}),
      ]);
    });
    const fR=aoa.length;
    aoa.push([
      TOTAL_LABEL('TOTAL',XL.hdr1),TOTAL_LABEL('',XL.hdr1),TOTAL_LABEL('',XL.hdr1),
      TOTAL_VAL(rupiah(grandPdp),{color:XL.greenDark}),
      TOTAL_LABEL('',XL.hdr1),TOTAL_LABEL('',XL.hdr1),
      TOTAL_VAL(grandTrx),
    ]);
    mrg.push({s:{r:fR,c:0},e:{r:fR,c:2}});
  }
  buildSheet(wb,'Pendapatan Harian',aoa,mrg,[5,14,12,20,16,16,12]);
}
/* ═══════════════════════════════════════════════════
   SHEET 6: ULASAN PEMBELI
   ═══════════════════════════════════════════════════ */
function buildSheetUlasan(wb){
  const p = DATA.penjual;
  const ul = DATA.semua_ulasan;
  const C = 7;
  const aoa=[], mrg=[];
  aoa.push([TITLE('ULASAN PEMBELI — '+p.nama.toUpperCase()),...Array(C-1).fill(TITLE(''))]);
  mrg.push({s:{r:0,c:0},e:{r:0,c:C-1}});
  aoa.push([SUBTITLE('Total: '+ul.length+' ulasan. Dicetak: '+nowStr()),...Array(C-1).fill(SUBTITLE(''))]);
  mrg.push({s:{r:1,c:0},e:{r:1,c:C-1}});
  aoa.push(sepRow(C));
  /* Rata-rata bintang keseluruhan */
  if(ul.length>0){
    const avgBintang = (ul.reduce((s,u)=>s+parseInt(u.bintang||0),0)/ul.length).toFixed(2);
    const distrib={5:0,4:0,3:0,2:0,1:0};
    ul.forEach(u=>{ const b=parseInt(u.bintang||0); if(distrib[b]!==undefined) distrib[b]++; });
    const sumS=aoa.length;
    aoa.push([SECTION('📊  RINGKASAN ULASAN',XL.brandMid),...Array(C-1).fill(SECTION('',XL.brandMid))]);
    mrg.push({s:{r:sumS,c:0},e:{r:sumS,c:C-1}});
    const avgR=aoa.length;
    aoa.push([LABEL('Rata-rata Rating'),xlCell(avgBintang+' ⭐',{sz:14,bold:true,color:XL.yellowDark,bg:XL.yellowLight,h:'center',border:fullBdr('medium',XL.yellow)}),...Array(C-2).fill(xlCell('',{bg:XL.yellowLight,border:fullBdr('medium',XL.yellow)}))]);
    mrg.push({s:{r:avgR,c:1},e:{r:avgR,c:C-1}});
    [5,4,3,2,1].forEach(b=>{
      const stars='⭐'.repeat(b);
      const cnt=distrib[b];
      const pct=ul.length>0?Math.round((cnt/ul.length)*100):0;
      const rr=aoa.length;
      const barBg=b>=4?XL.greenLight:b===3?XL.yellowLight:XL.redLight;
      const barFg=b>=4?XL.greenDark:b===3?XL.yellowDark:XL.redDark;
      aoa.push([LABEL(stars+' ('+b+' bintang)'),xlCell(cnt+' ulasan  ('+pct+'%)',{sz:10,bold:true,color:barFg,bg:barBg,h:'left',indent:1,border:fullBdr('thin',barFg)}),...Array(C-2).fill(xlCell('',{bg:barBg,border:fullBdr('thin',barFg)}))]);
      mrg.push({s:{r:rr,c:1},e:{r:rr,c:C-1}});
    });
    aoa.push(sepRow(C));
  }
  const tS=aoa.length;
  aoa.push([SECTION('💬  DAFTAR ULASAN',XL.hdr1),...Array(C-1).fill(SECTION('',XL.hdr1))]);
  mrg.push({s:{r:tS,c:0},e:{r:tS,c:C-1}});
  aoa.push([COL_HDR('No'),COL_HDR('Menu'),COL_HDR('Pembeli'),COL_HDR('Kelas'),COL_HDR('Rating'),COL_HDR('Komentar'),COL_HDR('Tanggal')]);
  if(ul.length===0){
    const noR=aoa.length;
    aoa.push([xlCell('Belum ada ulasan.',{sz:11,italic:true,color:XL.gray400,h:'center',bg:XL.gray50,border:fullBdr('thin',XL.gray200)}),...Array(C-1).fill(xlCell('',{bg:XL.gray50,border:fullBdr('thin',XL.gray200)}))]);
    mrg.push({s:{r:noR,c:0},e:{r:noR,c:C-1}});
  } else {
    ul.forEach((u,i)=>{
      const isAlt=i%2===1; const rowBg=isAlt?'FFF0F7FF':XL.white;
      const b=parseInt(u.bintang||0);
      const bBg=b>=4?XL.greenLight:b===3?XL.yellowLight:XL.redLight;
      const bFg=b>=4?XL.greenDark:b===3?XL.yellowDark:XL.redDark;
      const stars='⭐'.repeat(b)+'☆'.repeat(5-b)+' ('+b+'/5)';
      aoa.push([
        NUM(i+1,rowBg),
        xlCell(u.nama_makanan||'-',{sz:10,bold:true,color:XL.brand,bg:rowBg,indent:1,border:fullBdr('thin',XL.gray200)}),
        xlCell(u.nama_user||'Anonim',{sz:10,color:XL.gray800,bg:rowBg,indent:1,border:fullBdr('thin',XL.gray200)}),
        xlCell(u.kelas||'-',{sz:9,color:XL.gray600,bg:rowBg,h:'center',border:fullBdr('thin',XL.gray200)}),
        BADGE(stars,bBg,bFg),
        xlCell(u.komentar||'—',{sz:10,color:XL.gray600,bg:rowBg,indent:1,wrap:true,border:fullBdr('thin',XL.gray200)}),
        xlCell(u.created_at?u.created_at.slice(0,10):'-',{sz:9,color:XL.gray400,bg:rowBg,h:'center',border:fullBdr('thin',XL.gray200)}),
      ]);
    });
  }
  buildSheet(wb,'Ulasan Pembeli',aoa,mrg,[5,26,18,10,22,40,12]);
}
/* ═══════════════════════════════════════════════════
   MAIN: BUILD & DOWNLOAD
   ═══════════════════════════════════════════════════ */
window.addEventListener('load', function(){
  setTimeout(async function(){
    try {
      const wb = XLSX.utils.book_new();
      setProgress(10,'📋 Membangun Sheet Profil & Ringkasan…');
      await new Promise(r=>setTimeout(r,60));
      buildSheetProfil(wb);
      setProgress(30,'🍽 Membangun Sheet Semua Menu…');
      await new Promise(r=>setTimeout(r,60));
      buildSheetMenu(wb);
      setProgress(50,'🧾 Membangun Sheet Semua Transaksi…');
      await new Promise(r=>setTimeout(r,60));
      buildSheetTrx(wb);
      setProgress(65,'🌟 Membangun Sheet Menu Terlaris…');
      await new Promise(r=>setTimeout(r,60));
      buildSheetTerlaris(wb);
      setProgress(78,'📅 Membangun Sheet Pendapatan Harian…');
      await new Promise(r=>setTimeout(r,60));
      buildSheetHarian(wb);
      setProgress(90,'⭐ Membangun Sheet Ulasan Pembeli…');
      await new Promise(r=>setTimeout(r,60));
      buildSheetUlasan(wb);
      setProgress(97,'💾 Menyimpan file Excel…');
      await new Promise(r=>setTimeout(r,80));
      XLSX.writeFile(wb, NAMA_FILE);
      setProgress(100,'✅ Selesai!');
      document.getElementById('doneMsg').style.display='block';
      document.getElementById('stxt').textContent='File berhasil diunduh!';
    } catch(e) {
      console.error(e);
      setProgress(100,'❌ Gagal mengunduh!');
      document.getElementById('stxt').textContent='Terjadi kesalahan: ' + e.message;
    }
  }, 500);
});
</script>
</body>
</html>
