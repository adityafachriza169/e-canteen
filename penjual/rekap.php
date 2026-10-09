<?php
/**
 * rekap.php — Laporan Rekap Pendapatan E-Canteen
 * Sheet 1: Ringkasan (kartu summary + rekap metode + produk terlaris)
 * Sheet 2: Detail Transaksi
 * Sheet 3: Rekap Per Tanggal
 * Format: SpreadsheetML — bisa dibuka Excel / LibreOffice
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../koneksi.php';
/* ══════════════════════════════════════════════
   1. PARAMETER & SESSION
   ══════════════════════════════════════════════ */
$mode      = $_GET['mode']   ?? 'semua';
$tglDari   = $_GET['dari']   ?? '';
$tglSampai = $_GET['sampai'] ?? '';
$idPenjual   = $_SESSION['id_penjual'] ?? $_SESSION['id']         ?? 0;
$idKantin    = $_SESSION['id_kantin']  ?? $_SESSION['kantin_id']  ?? 0;
$namaPenjual = $_SESSION['nama']       ?? $_SESSION['username']   ?? 'Penjual';
// Ambil nama kantin dari DB
$namaKantin = 'E-Canteen';
if ($idKantin > 0) {
    $rk = mysqli_query($konek, "SELECT nama_kantin FROM kantin WHERE id_kantin=$idKantin LIMIT 1");
    if ($rk && $row_k = mysqli_fetch_assoc($rk)) $namaKantin = $row_k['nama_kantin'];
} elseif ($idPenjual > 0) {
    $rp = mysqli_query($konek, "SELECT p.id_kantin, k.nama_kantin FROM penjual p
                                 LEFT JOIN kantin k ON k.id_kantin=p.id_kantin
                                 WHERE p.id_penjual=$idPenjual LIMIT 1");
    if ($rp && $row_p = mysqli_fetch_assoc($rp)) {
        $idKantin   = (int)$row_p['id_kantin'];
        $namaKantin = $row_p['nama_kantin'] ?? 'E-Canteen';
    }
}
$dicetak  = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('d/m/Y H:i') . ' WIB';
/* ══════════════════════════════════════════════
   2. QUERY
   ══════════════════════════════════════════════ */
// Bangun filter kantin/penjual
if ($idKantin > 0) {
    $filterBase = "t.id_kantin = $idKantin";
} elseif ($idPenjual > 0) {
    $filterBase = "m.id_penjual = $idPenjual";
} else {
    $filterBase = "1=1";
}
// Filter tanggal + nama file
if ($mode === 'hari') {
    $today    = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
    $filterTgl = "AND DATE(t.tanggal) = '$today'";
    $periode   = (new DateTime($today))->format('d F Y');
    $namaFile  = 'rekap_pendapatan_' . $today;
} elseif ($mode === 'filter' && $tglDari && $tglSampai) {
    $filterTgl = "AND DATE(t.tanggal) BETWEEN '$tglDari' AND '$tglSampai'";
    $dari_fmt  = date('d/m/Y', strtotime($tglDari));
    $smp_fmt   = date('d/m/Y', strtotime($tglSampai));
    $periode   = "$dari_fmt s/d $smp_fmt";
    $namaFile  = 'rekap_pendapatan_' . $tglDari . '_' . $tglSampai;
} else {
    $filterTgl = "";
    $periode   = 'Seluruh Periode';
    $namaFile  = 'rekap_pendapatan_semua_' . date('Ymd_His');
}
$joinStr = ($idKantin <= 0 && $idPenjual > 0) ? 'INNER' : 'LEFT';
$sql = "SELECT t.id_transaksi, t.tanggal, t.nama_makanan, t.jumlah, t.total,
               t.metode, t.status, t.id_kantin,
               u.nama AS pembeli,
               IF(UPPER(t.metode)='QRIS',1,0) AS is_qris
        FROM transaksi t
        LEFT JOIN users u ON u.id_user = t.id_user
        $joinStr JOIN menu_req m ON m.id = t.id_menu
        WHERE $filterBase AND t.status = 'lunas'
        $filterTgl
        ORDER BY t.tanggal DESC, t.id_transaksi DESC";
$res   = mysqli_query($konek, $sql);
$transaksi = [];
while ($r = mysqli_fetch_assoc($res)) $transaksi[] = $r;
if (empty($transaksi)) {
    $dbg = json_encode([
        'mode'=>$mode,'id_kantin'=>$idKantin,'id_penjual'=>$idPenjual,
        'filter'=>$filterBase,'filterTgl'=>$filterTgl,
        'session_keys'=>array_keys($_SESSION)
    ], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
    die('<div style="font-family:Arial;text-align:center;margin-top:60px;color:#888">
         <p style="font-size:16px">Tidak ada transaksi <b>lunas</b> untuk periode ini.</p>
         <pre style="text-align:left;display:inline-block;background:#f5f5f5;padding:16px;border-radius:8px;font-size:12px;color:#333">'
         .htmlspecialchars($dbg).'</pre></div>');
}
/* ══════════════════════════════════════════════
   3. HITUNG SUMMARY
   ══════════════════════════════════════════════ */
$totalSemua = array_sum(array_column($transaksi,'total'));
$qrisRows   = array_filter($transaksi, fn($t)=>$t['is_qris']==1);
$cashRows   = array_filter($transaksi, fn($t)=>$t['is_qris']!=1);
$totalQris  = array_sum(array_column(array_values($qrisRows),'total'));
$totalCash  = array_sum(array_column(array_values($cashRows),'total'));
$nQris      = count($qrisRows);
$nCash      = count($cashRows);
$nTotal     = count($transaksi);
// Rekap per metode pembayaran
$pctQris = $totalSemua>0 ? number_format($totalQris/$totalSemua*100,1).'%' : '0%';
$pctCash = $totalSemua>0 ? number_format($totalCash/$totalSemua*100,1).'%' : '0%';
$avgQris = $nQris>0 ? round($totalQris/$nQris) : 0;
$avgCash = $nCash>0 ? round($totalCash/$nCash) : 0;
$avgAll  = $nTotal>0 ? round($totalSemua/$nTotal) : 0;
// Top 5 produk terlaris
$prodMap = [];
foreach ($transaksi as $t) {
    $p = $t['nama_makanan'] ?: '-';
    if (!isset($prodMap[$p])) $prodMap[$p] = ['jml'=>0,'nom'=>0];
    $prodMap[$p]['jml'] += $t['jumlah'] ?? 1;
    $prodMap[$p]['nom'] += $t['total'];
}
uasort($prodMap, fn($a,$b)=>$b['jml']-$a['jml']);
$top5 = array_slice($prodMap,0,5,true);
// Rekap per tanggal (untuk sheet rekap harian)
$perTanggal = [];
foreach ($transaksi as $t) {
    $tgl = date('Y-m-d', strtotime($t['tanggal']));
    if (!isset($perTanggal[$tgl])) $perTanggal[$tgl] = ['total'=>0,'qris'=>0,'cash'=>0,'n'=>0];
    $perTanggal[$tgl]['total'] += $t['total'];
    $perTanggal[$tgl]['n']++;
    if ($t['is_qris']==1) $perTanggal[$tgl]['qris'] += $t['total'];
    else                  $perTanggal[$tgl]['cash'] += $t['total'];
}
krsort($perTanggal);
/* ══════════════════════════════════════════════
   4. HELPERS
   ══════════════════════════════════════════════ */
function x($v)  { return htmlspecialchars((string)$v, ENT_XML1, 'UTF-8'); }
function cell(string $sid, $val, bool $isNum=false): string {
    $type = $isNum ? 'Number' : 'String';
    $v    = $isNum ? (float)$val : x($val);
    return "<Cell ss:StyleID=\"$sid\"><Data ss:Type=\"$type\">$v</Data></Cell>";
}
function eCell(string $sid='s_plain'): string {
    return "<Cell ss:StyleID=\"$sid\"><Data ss:Type=\"String\"></Data></Cell>";
}
function mCell(string $sid, $val, int $across, bool $isNum=false): string {
    $type = $isNum ? 'Number' : 'String';
    $v    = $isNum ? (float)$val : x($val);
    return "<Cell ss:StyleID=\"$sid\" ss:MergeAcross=\"$across\"><Data ss:Type=\"$type\">$v</Data></Cell>";
}
function row(string $cells, int $h=22): string {
    return "<Row ss:Height=\"$h\">$cells</Row>\n";
}
/* ══════════════════════════════════════════════
   5. STYLES
   ══════════════════════════════════════════════ */
$styles = <<<XML
<Styles>
 <Style ss:ID="s_plain">
  <Font ss:FontName="Calibri" ss:Size="10"/>
  <Alignment ss:Vertical="Center" ss:WrapText="1"/>
 </Style>
 <!-- ══ BANNER ══ -->
 <Style ss:ID="s_banner">
  <Font ss:FontName="Calibri" ss:Size="16" ss:Bold="1" ss:Color="#FFFFFF"/>
  <Interior ss:Color="#E8640A" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
 </Style>
 <!-- ══ SUB-INFO ══ -->
 <Style ss:ID="s_info">
  <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#555555"/>
  <Interior ss:Color="#F9F9F9" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Left" ss:Vertical="Center" ss:Indent="1" ss:WrapText="1"/>
  <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#EEEEEE"/></Borders>
 </Style>
 <Style ss:ID="s_info_r">
  <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#888888"/>
  <Interior ss:Color="#F9F9F9" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Right" ss:Vertical="Center" ss:Indent="1" ss:WrapText="1"/>
  <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#EEEEEE"/></Borders>
 </Style>
 <!-- ══ KARTU SUMMARY ══ -->
 <Style ss:ID="s_card_or_lbl">
  <Font ss:FontName="Calibri" ss:Size="9" ss:Bold="1" ss:Color="#E8640A"/>
  <Interior ss:Color="#FDE8D8" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F5C8A8"/>
  <Border ss:Position="Left"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F5C8A8"/>
  <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F5C8A8"/>
  <Border ss:Position="Top"   ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#E8640A"/></Borders>
 </Style>
 <Style ss:ID="s_card_or_val">
  <Font ss:FontName="Calibri" ss:Size="15" ss:Bold="1" ss:Color="#E8640A"/>
  <Interior ss:Color="#FDE8D8" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  <NumberFormat ss:Format="#,##0"/>
  <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F5C8A8"/>
  <Border ss:Position="Left"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F5C8A8"/>
  <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F5C8A8"/>
  <Border ss:Position="Top"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F5C8A8"/></Borders>
 </Style>
 <Style ss:ID="s_card_or_sub">
  <Font ss:FontName="Calibri" ss:Size="9" ss:Color="#BCA090"/>
  <Interior ss:Color="#FDE8D8" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#E8640A"/>
  <Border ss:Position="Left"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F5C8A8"/>
  <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F5C8A8"/>
  <Border ss:Position="Top"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F5C8A8"/></Borders>
 </Style>
 <Style ss:ID="s_card_bl_lbl">
  <Font ss:FontName="Calibri" ss:Size="9" ss:Bold="1" ss:Color="#1D4ED8"/>
  <Interior ss:Color="#DBEAFE" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BFDBFE"/>
  <Border ss:Position="Left"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BFDBFE"/>
  <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BFDBFE"/>
  <Border ss:Position="Top"   ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#1D4ED8"/></Borders>
 </Style>
 <Style ss:ID="s_card_bl_val">
  <Font ss:FontName="Calibri" ss:Size="15" ss:Bold="1" ss:Color="#1D4ED8"/>
  <Interior ss:Color="#DBEAFE" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  <NumberFormat ss:Format="#,##0"/>
  <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BFDBFE"/>
  <Border ss:Position="Left"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BFDBFE"/>
  <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BFDBFE"/>
  <Border ss:Position="Top"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BFDBFE"/></Borders>
 </Style>
 <Style ss:ID="s_card_bl_sub">
  <Font ss:FontName="Calibri" ss:Size="9" ss:Color="#93B4E0"/>
  <Interior ss:Color="#DBEAFE" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#1D4ED8"/>
  <Border ss:Position="Left"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BFDBFE"/>
  <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BFDBFE"/>
  <Border ss:Position="Top"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BFDBFE"/></Borders>
 </Style>
 <Style ss:ID="s_card_gr_lbl">
  <Font ss:FontName="Calibri" ss:Size="9" ss:Bold="1" ss:Color="#15803D"/>
  <Interior ss:Color="#DCFCE7" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BBF7D0"/>
  <Border ss:Position="Left"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BBF7D0"/>
  <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BBF7D0"/>
  <Border ss:Position="Top"   ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#15803D"/></Borders>
 </Style>
 <Style ss:ID="s_card_gr_val">
  <Font ss:FontName="Calibri" ss:Size="15" ss:Bold="1" ss:Color="#15803D"/>
  <Interior ss:Color="#DCFCE7" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  <NumberFormat ss:Format="#,##0"/>
  <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BBF7D0"/>
  <Border ss:Position="Left"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BBF7D0"/>
  <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BBF7D0"/>
  <Border ss:Position="Top"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BBF7D0"/></Borders>
 </Style>
 <Style ss:ID="s_card_gr_sub">
  <Font ss:FontName="Calibri" ss:Size="9" ss:Color="#5E9E75"/>
  <Interior ss:Color="#DCFCE7" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#15803D"/>
  <Border ss:Position="Left"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BBF7D0"/>
  <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BBF7D0"/>
  <Border ss:Position="Top"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BBF7D0"/></Borders>
 </Style>
 <Style ss:ID="s_card_pu_lbl">
  <Font ss:FontName="Calibri" ss:Size="9" ss:Bold="1" ss:Color="#6D28D9"/>
  <Interior ss:Color="#EDE9FE" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDD6FE"/>
  <Border ss:Position="Left"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDD6FE"/>
  <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDD6FE"/>
  <Border ss:Position="Top"   ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#6D28D9"/></Borders>
 </Style>
 <Style ss:ID="s_card_pu_val">
  <Font ss:FontName="Calibri" ss:Size="15" ss:Bold="1" ss:Color="#6D28D9"/>
  <Interior ss:Color="#EDE9FE" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDD6FE"/>
  <Border ss:Position="Left"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDD6FE"/>
  <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDD6FE"/>
  <Border ss:Position="Top"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDD6FE"/></Borders>
 </Style>
 <Style ss:ID="s_card_pu_sub">
  <Font ss:FontName="Calibri" ss:Size="9" ss:Color="#9980C8"/>
  <Interior ss:Color="#EDE9FE" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#6D28D9"/>
  <Border ss:Position="Left"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDD6FE"/>
  <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDD6FE"/>
  <Border ss:Position="Top"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDD6FE"/></Borders>
 </Style>
 <!-- ══ SECTION HEADER gelap ══ -->
 <Style ss:ID="s_sec">
  <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>
  <Interior ss:Color="#1A1A2E" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Left" ss:Vertical="Center" ss:Indent="1" ss:WrapText="1"/>
 </Style>
 <!-- ══ HEADER KOLOM orange ══ -->
 <Style ss:ID="s_hdr">
  <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/>
  <Interior ss:Color="#E8640A" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  <Borders>
   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#9A3D00"/>
   <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#9A3D00"/>
   <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#9A3D00"/>
   <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#9A3D00"/>
  </Borders>
 </Style>
 <Style ss:ID="s_hdr_l">
  <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/>
  <Interior ss:Color="#E8640A" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Left" ss:Vertical="Center" ss:Indent="1" ss:WrapText="1"/>
  <Borders>
   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#9A3D00"/>
   <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#9A3D00"/>
   <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#9A3D00"/>
   <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#9A3D00"/>
  </Borders>
 </Style>
 <!-- ══ ROW DATA ══ -->
 <Style ss:ID="s_row">
  <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1A1A1A"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  <Borders>
   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
  </Borders>
 </Style>
 <Style ss:ID="s_row_l">
  <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1A1A1A"/>
  <Alignment ss:Horizontal="Left" ss:Vertical="Center" ss:Indent="1" ss:WrapText="1"/>
  <Borders>
   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
  </Borders>
 </Style>
 <Style ss:ID="s_row_r">
  <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1A1A1A"/>
  <Alignment ss:Horizontal="Right" ss:Vertical="Center" ss:Indent="1" ss:WrapText="1"/>
  <NumberFormat ss:Format="#,##0"/>
  <Borders>
   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
  </Borders>
 </Style>
 <!-- ══ ROW ALT (zebra) ══ -->
 <Style ss:ID="s_alt">
  <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1A1A1A"/>
  <Interior ss:Color="#FFF4EE" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  <Borders>
   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
  </Borders>
 </Style>
 <Style ss:ID="s_alt_l">
  <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1A1A1A"/>
  <Interior ss:Color="#FFF4EE" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Left" ss:Vertical="Center" ss:Indent="1" ss:WrapText="1"/>
  <Borders>
   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
  </Borders>
 </Style>
 <Style ss:ID="s_alt_r">
  <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1A1A1A"/>
  <Interior ss:Color="#FFF4EE" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Right" ss:Vertical="Center" ss:Indent="1" ss:WrapText="1"/>
  <NumberFormat ss:Format="#,##0"/>
  <Borders>
   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
  </Borders>
 </Style>
 <!-- ══ TOTAL ROW ══ -->
 <Style ss:ID="s_total">
  <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/>
  <Interior ss:Color="#E8640A" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  <Borders>
   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#9A3D00"/>
   <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#9A3D00"/>
   <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#9A3D00"/>
   <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#9A3D00"/>
  </Borders>
 </Style>
 <Style ss:ID="s_total_l">
  <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/>
  <Interior ss:Color="#E8640A" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Left" ss:Vertical="Center" ss:Indent="1" ss:WrapText="1"/>
  <Borders>
   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#9A3D00"/>
   <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#9A3D00"/>
   <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#9A3D00"/>
   <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#9A3D00"/>
  </Borders>
 </Style>
 <Style ss:ID="s_total_r">
  <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/>
  <Interior ss:Color="#E8640A" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Right" ss:Vertical="Center" ss:Indent="1" ss:WrapText="1"/>
  <NumberFormat ss:Format="#,##0"/>
  <Borders>
   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#9A3D00"/>
   <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#9A3D00"/>
   <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#9A3D00"/>
   <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#9A3D00"/>
  </Borders>
 </Style>
 <!-- ══ BADGE metode ══ -->
 <Style ss:ID="s_qris">
  <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#1D4ED8"/>
  <Interior ss:Color="#DBEAFE" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  <Borders>
   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
  </Borders>
 </Style>
 <Style ss:ID="s_qris_alt">
  <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#1D4ED8"/>
  <Interior ss:Color="#DBEAFE" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  <Borders>
   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
  </Borders>
 </Style>
 <Style ss:ID="s_cash">
  <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#15803D"/>
  <Interior ss:Color="#DCFCE7" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  <Borders>
   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
  </Borders>
 </Style>
 <!-- ══ SALDO masuk ══ -->
 <Style ss:ID="s_saldo_in">
  <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#15803D"/>
  <Interior ss:Color="#DCFCE7" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Right" ss:Vertical="Center" ss:Indent="1" ss:WrapText="1"/>
  <NumberFormat ss:Format="#,##0"/>
  <Borders>
   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
  </Borders>
 </Style>
 <Style ss:ID="s_saldo_cash">
  <Font ss:FontName="Calibri" ss:Size="9" ss:Color="#AAAAAA"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  <Borders>
   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
  </Borders>
 </Style>
 <Style ss:ID="s_saldo_cash_alt">
  <Font ss:FontName="Calibri" ss:Size="9" ss:Color="#AAAAAA"/>
  <Interior ss:Color="#FFF4EE" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  <Borders>
   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
  </Borders>
 </Style>
 <!-- ══ STATUS selesai ══ -->
 <Style ss:ID="s_done">
  <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#15803D"/>
  <Interior ss:Color="#DCFCE7" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  <Borders>
   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
  </Borders>
 </Style>
 <!-- ══ CATATAN kaki ══ -->
 <Style ss:ID="s_note">
  <Font ss:FontName="Calibri" ss:Size="9" ss:Italic="1" ss:Color="#888888"/>
  <Interior ss:Color="#FAFAFA" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Left" ss:Vertical="Center" ss:Indent="1" ss:WrapText="1"/>
  <Borders><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/></Borders>
 </Style>
 <!-- ══ PRODUK label lebar ══ -->
 <Style ss:ID="s_prod_l">
  <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#1A1A1A"/>
  <Alignment ss:Horizontal="Left" ss:Vertical="Center" ss:Indent="1" ss:WrapText="1"/>
  <Borders>
   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
  </Borders>
 </Style>
 <Style ss:ID="s_prod_l_alt">
  <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#1A1A1A"/>
  <Interior ss:Color="#FFF4EE" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Left" ss:Vertical="Center" ss:Indent="1" ss:WrapText="1"/>
  <Borders>
   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
   <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8E8E8"/>
  </Borders>
 </Style>
</Styles>
XML;
/* ══════════════════════════════════════════════
   6. SHEET 1 — RINGKASAN
   9 kolom: A(50) B(140) C(140) D(140) E(140) F(140) G(140) H(140) I(10)
   ══════════════════════════════════════════════ */
ob_start();
?>
  <Worksheet ss:Name="Ringkasan">
   <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
    <Selected/>
   </WorksheetOptions>
   <Table ss:DefaultRowHeight="22">
    <Column ss:Width="50"/>   <!-- A: No/label kecil -->
    <Column ss:Width="140"/>  <!-- B -->
    <Column ss:Width="140"/>  <!-- C -->
    <Column ss:Width="140"/>  <!-- D -->
    <Column ss:Width="140"/>  <!-- E -->
    <Column ss:Width="140"/>  <!-- F -->
    <Column ss:Width="140"/>  <!-- G -->
    <Column ss:Width="140"/>  <!-- H -->
    <Column ss:Width="10"/>   <!-- I: spacer -->
    <!-- ══ Baris 1: Banner ══ -->
    <?= row(mCell('s_banner','LAPORAN REKAP PENDAPATAN — E-CANTEEN',8), 48) ?>
    <!-- ══ Baris 2: Info kiri & kanan ══ -->
    <Row ss:Height="26">
     <?= mCell('s_info', '  Periode: ' . x($periode), 4) ?>
     <?= mCell('s_info_r', 'Dicetak: ' . x($dicetak) . '  ', 3) ?>
    </Row>
    <!-- ══ Baris 3: spacer ══ -->
    <?= row(mCell('s_plain','',8), 10) ?>
    <!-- ══ Baris 4: Label kartu ══ -->
    <Row ss:Height="32">
     <?= eCell('s_plain') ?>
     <?= mCell('s_card_or_lbl', 'Total Semua Transaksi', 1) ?>
     <?= mCell('s_card_bl_lbl', 'Total QRIS (Masuk Saldo)', 1) ?>
     <?= mCell('s_card_gr_lbl', 'Total Cash', 1) ?>
     <?= mCell('s_card_pu_lbl', 'Status Selesai', 1) ?>
    </Row>
    <!-- ══ Baris 5: Nilai kartu ══ -->
    <Row ss:Height="40">
     <?= eCell('s_plain') ?>
     <?= mCell('s_card_or_val', $totalSemua, 1, true) ?>
     <?= mCell('s_card_bl_val', $totalQris,  1, true) ?>
     <?= mCell('s_card_gr_val', $totalCash,  1, true) ?>
     <?= mCell('s_card_pu_val', $nTotal,     1, true) ?>
    </Row>
    <!-- ══ Baris 6: Sub kartu ══ -->
    <Row ss:Height="24">
     <?= eCell('s_plain') ?>
     <?= mCell('s_card_or_sub', $nTotal.' transaksi', 1) ?>
     <?= mCell('s_card_bl_sub', $nQris.' transaksi',  1) ?>
     <?= mCell('s_card_gr_sub', $nCash.' transaksi',  1) ?>
     <?= mCell('s_card_pu_sub', 'Semua lunas',        1) ?>
    </Row>
    <!-- ══ Spacer ══ -->
    <?= row(mCell('s_plain','',8), 12) ?>
    <!-- ══ Section: Rekap Per Metode ══ -->
    <?= row(mCell('s_sec','  REKAP PER METODE PEMBAYARAN',8), 30) ?>
    <!-- Header tabel metode -->
    <Row ss:Height="30">
     <?= cell('s_hdr','No') ?>
     <?= cell('s_hdr_l','Metode') ?>
     <?= cell('s_hdr','Jumlah Trx') ?>
     <?= cell('s_hdr','Total Nominal') ?>
     <?= cell('s_hdr','% dari Total') ?>
     <?= cell('s_hdr','Rata-rata / Trx') ?>
     <?= eCell('s_hdr') ?>
     <?= eCell('s_hdr') ?>
     <?= eCell('s_hdr') ?>
    </Row>
<?php
$metodeRows = [
    ['QRIS', $nQris,  $totalQris,  $pctQris, $avgQris, false],
    ['Cash', $nCash,  $totalCash,  $pctCash, $avgCash, false],
    ['TOTAL',$nTotal, $totalSemua, '100%',   $avgAll,  true ],
];
$metodeIdx = 1;
foreach ($metodeRows as $ri => [$m,$j,$nom,$pct,$avg,$isTotal]):
    $s  = $isTotal ? 's_total_l' : ($ri%2===0 ? 's_alt_l' : 's_row_l');
    $sc = $isTotal ? 's_total'   : ($ri%2===0 ? 's_alt'   : 's_row');
    $sr = $isTotal ? 's_total_r' : ($ri%2===0 ? 's_alt_r' : 's_row_r');
    $sn = $isTotal ? 's_total'   : ($ri%2===0 ? 's_alt'   : 's_row');
?>
    <Row ss:Height="24">
     <?= cell($sn, $isTotal ? '' : $metodeIdx) ?>
     <?= cell($s,  $m) ?>
     <?= cell($sc, $j,   true) ?>
     <?= cell($sr, $nom, true) ?>
     <?= cell($sc, $pct) ?>
     <?= cell($sr, $avg, true) ?>
     <?= eCell($sc) ?>
     <?= eCell($sc) ?>
     <?= eCell($sc) ?>
    </Row>
<?php if (!$isTotal) $metodeIdx++; endforeach; ?>
    <!-- ══ Spacer ══ -->
    <?= row(mCell('s_plain','',8), 12) ?>
    <!-- ══ Section: Produk Terlaris ══ -->
    <?= row(mCell('s_sec','  PRODUK TERLARIS',8), 30) ?>
    <!-- Header tabel produk -->
    <Row ss:Height="30">
     <?= cell('s_hdr','#') ?>
     <?= mCell('s_hdr_l','Nama Produk',2) ?>
     <?= cell('s_hdr','Jumlah Terjual') ?>
     <?= cell('s_hdr','Total Nominal') ?>
     <?= eCell('s_hdr') ?>
     <?= eCell('s_hdr') ?>
     <?= eCell('s_hdr') ?>
     <?= eCell('s_hdr') ?>
    </Row>
<?php
$rank  = 1;
$medal = ['#1','#2','#3','#4','#5'];
foreach ($top5 as $prod => $val):
    $isAlt = $rank%2===0;
    $sc  = $isAlt ? 's_alt'   : 's_row';
    $sl  = $isAlt ? 's_prod_l_alt' : 's_prod_l';
    $sr  = $isAlt ? 's_alt_r' : 's_row_r';
?>
    <Row ss:Height="24">
     <?= cell($sc, $medal[$rank-1]) ?>
     <?= mCell($sl, x($prod), 2) ?>
     <?= cell($sc, $val['jml'], true) ?>
     <?= cell($sr, $val['nom'], true) ?>
     <?= eCell($sc) ?>
     <?= eCell($sc) ?>
     <?= eCell($sc) ?>
     <?= eCell($sc) ?>
    </Row>
<?php $rank++; endforeach; ?>
    <!-- ══ Spacer + note ══ -->
    <?= row(mCell('s_plain','',8), 8) ?>
    <?= row(mCell('s_note','* Cash tidak masuk ke saldo QRIS — hanya tercatat sebagai rekap pendapatan.',8), 24) ?>
   </Table>
  </Worksheet>
<?php $sheet1 = ob_get_clean();
/* ══════════════════════════════════════════════
   7. SHEET 2 — DETAIL TRANSAKSI
   9 kolom: No | ID Trx | Tanggal | Pelanggan | Produk | Metode | Total | Masuk Saldo | Status
   ══════════════════════════════════════════════ */
ob_start();
?>
  <Worksheet ss:Name="Detail Transaksi">
   <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
    <FreezePanes/>
    <FrozenNoSplit/>
    <SplitHorizontal>2</SplitHorizontal>
    <TopRowBottomPane>2</TopRowBottomPane>
    <ActivePane>2</ActivePane>
   </WorksheetOptions>
   <Table ss:DefaultRowHeight="22">
    <Column ss:Width="42"/>   <!-- No -->
    <Column ss:Width="100"/>  <!-- ID Trx -->
    <Column ss:Width="130"/>  <!-- Tanggal -->
    <Column ss:Width="140"/>  <!-- Pelanggan -->
    <Column ss:Width="180"/>  <!-- Produk -->
    <Column ss:Width="75"/>   <!-- Metode -->
    <Column ss:Width="120"/>  <!-- Total -->
    <Column ss:Width="120"/>  <!-- Masuk Saldo -->
    <Column ss:Width="85"/>   <!-- Status -->
    <!-- ══ Banner ══ -->
    <?= row(mCell('s_banner','DETAIL TRANSAKSI — E-CANTEEN  |  Periode: '.x($periode),8), 48) ?>
    <!-- ══ Header ══ -->
    <Row ss:Height="32">
     <?= cell('s_hdr','No') ?>
     <?= cell('s_hdr','ID Transaksi') ?>
     <?= cell('s_hdr','Tanggal') ?>
     <?= cell('s_hdr','Pelanggan') ?>
     <?= cell('s_hdr_l','Produk') ?>
     <?= cell('s_hdr','Metode') ?>
     <?= cell('s_hdr','Total') ?>
     <?= cell('s_hdr','Masuk Saldo') ?>
     <?= cell('s_hdr','Status') ?>
    </Row>
<?php foreach ($transaksi as $idx => $t):
    $isAlt  = $idx%2===0;
    $sc     = $isAlt ? 's_alt'   : 's_row';
    $sl     = $isAlt ? 's_alt_l' : 's_row_l';
    $sr     = $isAlt ? 's_alt_r' : 's_row_r';
    $isQris = $t['is_qris']==1;
    $trxId  = 'TRX-'.str_pad($t['id_transaksi'],4,'0',STR_PAD_LEFT);
    $tglFmt = date('d/m/Y H:i', strtotime($t['tanggal']));
    $saldoS = $isAlt ? 's_saldo_cash_alt' : 's_saldo_cash';
?>
    <Row ss:Height="24">
     <?= cell($sc,  $idx+1, true) ?>
     <?= cell($sc,  $trxId) ?>
     <?= cell($sc,  $tglFmt) ?>
     <?= cell($sl,  $t['pembeli'] ?: 'Anonim') ?>
     <?= cell($sl,  $t['nama_makanan'] ?: '-') ?>
     <?= cell($isQris ? 's_qris' : 's_cash', $isQris ? 'QRIS' : 'Cash') ?>
     <?= cell($sr,  $t['total'], true) ?>
     <?= $isQris ? cell('s_saldo_in', $t['total'], true) : cell($saldoS, '— Cash') ?>
     <?= cell('s_done', 'Selesai') ?>
    </Row>
<?php endforeach; ?>
    <!-- ══ Total row ══ -->
    <Row ss:Height="30">
     <?= mCell('s_total_l','TOTAL — '.$nTotal.' Transaksi', 5) ?>
     <?= cell('s_total_r', $totalSemua, true) ?>
     <?= cell('s_total_r', $totalQris,  true) ?>
     <?= eCell('s_total') ?>
    </Row>
    <?= row(mCell('s_plain','',8), 8) ?>
    <?= row(mCell('s_note',"* Kolom 'Masuk Saldo' hanya mencatat QRIS. Cash tetap tercatat di rekap pendapatan.",8), 24) ?>
   </Table>
  </Worksheet>
<?php $sheet2 = ob_get_clean();
/* ══════════════════════════════════════════════
   8. SHEET 3 — REKAP PER TANGGAL
   ══════════════════════════════════════════════ */
ob_start();
?>
  <Worksheet ss:Name="Rekap Per Tanggal">
   <Table ss:DefaultRowHeight="22">
    <Column ss:Width="50"/>   <!-- No -->
    <Column ss:Width="120"/>  <!-- Tanggal -->
    <Column ss:Width="80"/>   <!-- Trx -->
    <Column ss:Width="140"/>  <!-- Total Pendapatan -->
    <Column ss:Width="130"/>  <!-- QRIS -->
    <Column ss:Width="130"/>  <!-- Cash -->
    <Column ss:Width="10"/>   <!-- spacer -->
    <?= row(mCell('s_banner','REKAP PER TANGGAL — E-CANTEEN',6), 44) ?>
    <Row ss:Height="30">
     <?= cell('s_hdr','No') ?>
     <?= cell('s_hdr','Tanggal') ?>
     <?= cell('s_hdr','Jml Trx') ?>
     <?= cell('s_hdr','Total Pendapatan') ?>
     <?= cell('s_hdr','QRIS') ?>
     <?= cell('s_hdr','Cash') ?>
     <?= eCell('s_hdr') ?>
    </Row>
<?php $idx=0; foreach ($perTanggal as $tgl=>$d):
    $isAlt=$idx%2===0;
    $sc=$isAlt?'s_alt':'s_row';
    $sr=$isAlt?'s_alt_r':'s_row_r';
?>
    <Row ss:Height="24">
     <?= cell($sc, $idx+1, true) ?>
     <?= cell($sc, date('d/m/Y', strtotime($tgl))) ?>
     <?= cell($sc, $d['n'], true) ?>
     <?= cell($sr, $d['total'], true) ?>
     <?= cell($sr, $d['qris'],  true) ?>
     <?= cell($sr, $d['cash'],  true) ?>
     <?= eCell($sc) ?>
    </Row>
<?php $idx++; endforeach; ?>
    <Row ss:Height="28">
     <?= mCell('s_total_l','TOTAL',1) ?>
     <?= cell('s_total',$nTotal,true) ?>
     <?= cell('s_total_r',$totalSemua,true) ?>
     <?= cell('s_total_r',$totalQris,true) ?>
     <?= cell('s_total_r',$totalCash,true) ?>
     <?= eCell('s_total') ?>
    </Row>
   </Table>
  </Worksheet>
<?php $sheet3 = ob_get_clean();
/* ══════════════════════════════════════════════
   9. OUTPUT
   ══════════════════════════════════════════════ */
$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
$xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:x="urn:schemas-microsoft-com:office:excel">' . "\n";
$xml .= $styles . "\n";
$xml .= $sheet1;
$xml .= $sheet2;
$xml .= $sheet3;
$xml .= '</Workbook>';
$namaFileClean = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $namaFile);
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $namaFileClean . '.xls"');
header('Cache-Control: max-age=0');
echo $xml;
exit;
