<?php
// ════════════════════════════════════════════════════
//  BUFFER OUTPUT — cegah HTML/warning bocor ke JSON
// ════════════════════════════════════════════════════
ob_start();

// ════════════════════════════════════════════════════
//  SESSION & AUTH CHECK
// ════════════════════════════════════════════════════
session_start();

if (!isset($_SESSION['username']) || (int)($_SESSION['role'] ?? 0) !== 1) {
    header('Location: ../users/login.php');
    exit;
}

// ════════════════════════════════════════════════════
//  HANDLE TOGGLE BUKA/TUTUP KANTIN (AJAX)
// ════════════════════════════════════════════════════
if (isset($_POST['action']) && $_POST['action'] === 'toggle_kantin') {

    ob_clean();
    header('Content-Type: application/json');

    include_once "../users/koneksi.php";
    $conn_ajax = $konek;

    if (!$conn_ajax) {
        echo json_encode(['success' => false, 'message' => 'Koneksi database gagal']);
        exit;
    }

    $conn_ajax->set_charset('utf8mb4');
    $id_p = (int) ($_POST['id_penjual'] ?? 0);

    if ($id_p <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit;
    }

    $cek = $conn_ajax->prepare("SELECT id_penjual FROM penjual WHERE id_penjual = ? LIMIT 1");
    $cek->bind_param('i', $id_p);
    $cek->execute();
    if ($cek->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Penjual tidak ditemukan']);
        exit;
    }
    $cek->close();

    $upd = $conn_ajax->prepare("UPDATE penjual SET buka = IF(buka = 1, 0, 1) WHERE id_penjual = ?");
    $upd->bind_param('i', $id_p);
    $upd->execute();
    $upd->close();

    $res = $conn_ajax->prepare("SELECT buka FROM penjual WHERE id_penjual = ? LIMIT 1");
    $res->bind_param('i', $id_p);
    $res->execute();
    $row = $res->get_result()->fetch_assoc();
    $res->close();
    $conn_ajax->close();

    echo json_encode(['success' => true, 'buka' => (int)$row['buka']]);
    exit;
}

// ════════════════════════════════════════════════════
//  HANDLE EXPORT EXCEL STOK HARIAN — PURE PHP (SpreadsheetML XML)
// ════════════════════════════════════════════════════
if (isset($_GET['action']) && $_GET['action'] === 'export_stok_excel') {
    ob_clean();

    include_once "../users/koneksi.php";
    $conn_ex = $konek;
    $conn_ex->set_charset('utf8mb4');

    // Ambil data penjual
    $ses_u  = $_SESSION['username'];
    $stmt_p = $conn_ex->prepare("SELECT id_kantin, nama FROM penjual WHERE username = ? LIMIT 1");
    $stmt_p->bind_param('s', $ses_u);
    $stmt_p->execute();
    $pj = $stmt_p->get_result()->fetch_assoc();
    $stmt_p->close();

    $id_k_ex    = (int)($pj['id_kantin'] ?? 0);
    $nama_pj_ex = $pj['nama'] ?? 'Penjual';

    // Nama kantin
    $res_k = $conn_ex->prepare("SELECT nama_kantin FROM kantin WHERE id_kantin = ? LIMIT 1");
    $res_k->bind_param('i', $id_k_ex);
    $res_k->execute();
    $row_k = $res_k->get_result()->fetch_assoc();
    $res_k->close();
    $nama_kantin_ex = $row_k['nama_kantin'] ?? 'Kantin';

    // Data terjual per hari per menu (30 hari terakhir)
    $res_tj = $conn_ex->prepare("
        SELECT
            DATE(t.tanggal)   AS tgl,
            t.nama_makanan    AS nama_menu,
            SUM(t.jumlah)     AS total_terjual,
            SUM(t.total)      AS total_rupiah,
            m.harga           AS harga_satuan
        FROM transaksi t
        LEFT JOIN menu_req m ON m.id = t.id_menu
        WHERE t.id_kantin = ?
          AND t.tanggal >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
          AND t.status IN ('selesai','lunas','diambil','completed')
          AND (m.deleted_at IS NULL OR m.id IS NULL)
        GROUP BY DATE(t.tanggal), t.id_menu, t.nama_makanan, m.harga
        ORDER BY DATE(t.tanggal) DESC, total_terjual DESC
    ");
    $res_tj->bind_param('i', $id_k_ex);
    $res_tj->execute();
    $data_terjual = $res_tj->get_result()->fetch_all(MYSQLI_ASSOC);
    $res_tj->close();

    // Data stok ditambah (dari stok_log jika ada)
    $data_stok_log = [];
    $tbl_check = $conn_ex->query("SHOW TABLES LIKE 'stok_log'");
    if ($tbl_check && $tbl_check->num_rows > 0) {
        $res_sl = $conn_ex->prepare("
            SELECT
                DATE(sl.created_at) AS tgl,
                m.nama_makanan      AS nama_menu,
                SUM(sl.jumlah)      AS total_ditambah
            FROM stok_log sl
            JOIN menu_req m ON m.id = sl.id_menu
            WHERE m.id_kantin = ?
              AND sl.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
              AND sl.tipe = 'tambah'
            GROUP BY DATE(sl.created_at), sl.id_menu, m.nama_makanan
            ORDER BY DATE(sl.created_at) DESC
        ");
        $res_sl->bind_param('i', $id_k_ex);
        $res_sl->execute();
        $data_stok_log = $res_sl->get_result()->fetch_all(MYSQLI_ASSOC);
        $res_sl->close();
    }
    $conn_ex->close();

    // ── Build stok_lookup ──
    $stok_lookup = [];
    foreach ($data_stok_log as $sl) {
        $key = $sl['tgl'] . '|' . ($sl['nama_menu'] ?? '');
        $stok_lookup[$key] = ($stok_lookup[$key] ?? 0) + (int)$sl['total_ditambah'];
    }

    // ── Hitung per-tanggal & per-menu untuk ringkasan ──
    $per_tgl  = [];
    $per_menu = [];
    foreach ($data_terjual as $item) {
        $tgl        = $item['tgl'];
        $nm         = $item['nama_menu'] ?? '-';
        $key        = $tgl . '|' . $nm;
        $terjual    = (int)($item['total_terjual'] ?? 0);
        $pendapatan = (float)($item['total_rupiah'] ?? 0);
        $ditambah   = $stok_lookup[$key] ?? 0;

        if (!isset($per_tgl[$tgl])) $per_tgl[$tgl] = ['terjual' => 0, 'pendapatan' => 0.0, 'ditambah' => 0];
        $per_tgl[$tgl]['terjual']    += $terjual;
        $per_tgl[$tgl]['pendapatan'] += $pendapatan;
        $per_tgl[$tgl]['ditambah']   += $ditambah;

        if (!isset($per_menu[$nm])) $per_menu[$nm] = ['terjual' => 0, 'pendapatan' => 0.0];
        $per_menu[$nm]['terjual']    += $terjual;
        $per_menu[$nm]['pendapatan'] += $pendapatan;
    }

    krsort($per_tgl);
    uasort($per_menu, fn($a, $b) => $b['terjual'] - $a['terjual']);

    // ── Helper: XML-safe string ──
    function xmlStr(string $s): string {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    // ── Helper: format rupiah ──
    function rpXml(float $n): string {
        return 'Rp ' . number_format($n, 0, ',', '.');
    }

    // ── Helper: XML cell builders ──
    function cellS(string $v, string $style = ''): string {
        $s = $style ? " ss:StyleID=\"$style\"" : '';
        return "<Cell$s><Data ss:Type=\"String\">" . xmlStr($v) . "</Data></Cell>";
    }
    function cellN(float $v, string $style = ''): string {
        $s = $style ? " ss:StyleID=\"$style\"" : '';
        return "<Cell$s><Data ss:Type=\"Number\">$v</Data></Cell>";
    }
    function cellM(string $v, int $span, string $style = ''): string {
        $s = $style ? " ss:StyleID=\"$style\"" : '';
        return "<Cell$s ss:MergeAcross=\"" . ($span - 1) . "\"><Data ss:Type=\"String\">" . xmlStr($v) . "</Data></Cell>";
    }
    function cellE(string $style = ''): string {
        $s = $style ? " ss:StyleID=\"$style\"" : '';
        return "<Cell$s><Data ss:Type=\"String\"></Data></Cell>";
    }

    // ════════════════════════════════════════════════════
    //  BUILD SpreadsheetML XML
    // ════════════════════════════════════════════════════
    $tgl_export = date('d/m/Y H:i');

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
    $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
    $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
    $xml .= ' xmlns:x="urn:schemas-microsoft-com:office:excel">' . "\n";

    // ── STYLES ──
    $xml .= '<Styles>' . "\n";

    $xml .= '<Style ss:ID="Default" ss:Name="Normal">';
    $xml .= '<Font ss:FontName="Calibri" ss:Size="11"/>';
    $xml .= '<Alignment ss:Vertical="Center"/>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sTitle">';
    $xml .= '<Font ss:FontName="Calibri" ss:Bold="1" ss:Size="18" ss:Color="#FFFFFF"/>';
    $xml .= '<Interior ss:Color="#C84B11" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>';
    $xml .= '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="3" ss:Color="#8B2500"/></Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sSubtitle">';
    $xml .= '<Font ss:FontName="Calibri" ss:Size="10" ss:Color="#7A4030"/>';
    $xml .= '<Interior ss:Color="#FDE8D8" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sColHead">';
    $xml .= '<Font ss:FontName="Calibri" ss:Bold="1" ss:Size="10" ss:Color="#FFFFFF"/>';
    $xml .= '<Interior ss:Color="#E8714A" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>';
    $xml .= '<Borders>';
    $xml .= '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#C84B11"/>';
    $xml .= '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#C84B11"/>';
    $xml .= '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0603A"/>';
    $xml .= '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0603A"/>';
    $xml .= '</Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sColHeadC">';
    $xml .= '<Font ss:FontName="Calibri" ss:Bold="1" ss:Size="10" ss:Color="#FFFFFF"/>';
    $xml .= '<Interior ss:Color="#E8714A" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>';
    $xml .= '<Borders>';
    $xml .= '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#C84B11"/>';
    $xml .= '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#C84B11"/>';
    $xml .= '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0603A"/>';
    $xml .= '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0603A"/>';
    $xml .= '</Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sRowOdd">';
    $xml .= '<Font ss:FontName="Calibri" ss:Size="10" ss:Color="#2C2C2C"/>';
    $xml .= '<Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Vertical="Center"/>';
    $xml .= '<Borders>';
    $xml .= '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/>';
    $xml .= '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/>';
    $xml .= '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/>';
    $xml .= '</Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sRowOddC">';
    $xml .= '<Font ss:FontName="Calibri" ss:Size="10" ss:Color="#2C2C2C"/>';
    $xml .= '<Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    $xml .= '<Borders>';
    $xml .= '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/>';
    $xml .= '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/>';
    $xml .= '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/>';
    $xml .= '</Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sRowOddR">';
    $xml .= '<Font ss:FontName="Calibri" ss:Size="10" ss:Color="#C84B11" ss:Bold="1"/>';
    $xml .= '<Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Right" ss:Vertical="Center"/>';
    $xml .= '<Borders>';
    $xml .= '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/>';
    $xml .= '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/>';
    $xml .= '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/>';
    $xml .= '</Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sRowEven">';
    $xml .= '<Font ss:FontName="Calibri" ss:Size="10" ss:Color="#2C2C2C"/>';
    $xml .= '<Interior ss:Color="#FFF5EE" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Vertical="Center"/>';
    $xml .= '<Borders>';
    $xml .= '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/>';
    $xml .= '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/>';
    $xml .= '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/>';
    $xml .= '</Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sRowEvenC">';
    $xml .= '<Font ss:FontName="Calibri" ss:Size="10" ss:Color="#2C2C2C"/>';
    $xml .= '<Interior ss:Color="#FFF5EE" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    $xml .= '<Borders>';
    $xml .= '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/>';
    $xml .= '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/>';
    $xml .= '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/>';
    $xml .= '</Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sRowEvenR">';
    $xml .= '<Font ss:FontName="Calibri" ss:Size="10" ss:Color="#C84B11" ss:Bold="1"/>';
    $xml .= '<Interior ss:Color="#FFF5EE" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Right" ss:Vertical="Center"/>';
    $xml .= '<Borders>';
    $xml .= '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/>';
    $xml .= '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/>';
    $xml .= '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/>';
    $xml .= '</Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sPlus">';
    $xml .= '<Font ss:FontName="Calibri" ss:Bold="1" ss:Size="10" ss:Color="#16A34A"/>';
    $xml .= '<Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    $xml .= '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/></Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sPlusAlt">';
    $xml .= '<Font ss:FontName="Calibri" ss:Bold="1" ss:Size="10" ss:Color="#16A34A"/>';
    $xml .= '<Interior ss:Color="#FFF5EE" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    $xml .= '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/></Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sMinus">';
    $xml .= '<Font ss:FontName="Calibri" ss:Bold="1" ss:Size="10" ss:Color="#DC2626"/>';
    $xml .= '<Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    $xml .= '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/></Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sMinusAlt">';
    $xml .= '<Font ss:FontName="Calibri" ss:Bold="1" ss:Size="10" ss:Color="#DC2626"/>';
    $xml .= '<Interior ss:Color="#FFF5EE" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    $xml .= '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/></Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sZero">';
    $xml .= '<Font ss:FontName="Calibri" ss:Size="10" ss:Color="#888888"/>';
    $xml .= '<Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    $xml .= '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/></Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sZeroAlt">';
    $xml .= '<Font ss:FontName="Calibri" ss:Size="10" ss:Color="#888888"/>';
    $xml .= '<Interior ss:Color="#FFF5EE" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    $xml .= '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0D5C8"/></Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sTotal">';
    $xml .= '<Font ss:FontName="Calibri" ss:Bold="1" ss:Size="11" ss:Color="#2C2C2C"/>';
    $xml .= '<Interior ss:Color="#FFDABF" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    $xml .= '<Borders>';
    $xml .= '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#C84B11"/>';
    $xml .= '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#C84B11"/>';
    $xml .= '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#C84B11"/>';
    $xml .= '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#C84B11"/>';
    $xml .= '</Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sTotalR">';
    $xml .= '<Font ss:FontName="Calibri" ss:Bold="1" ss:Size="11" ss:Color="#C84B11"/>';
    $xml .= '<Interior ss:Color="#FFDABF" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Right" ss:Vertical="Center"/>';
    $xml .= '<Borders>';
    $xml .= '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#C84B11"/>';
    $xml .= '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#C84B11"/>';
    $xml .= '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#C84B11"/>';
    $xml .= '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#C84B11"/>';
    $xml .= '</Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sTotalPlus">';
    $xml .= '<Font ss:FontName="Calibri" ss:Bold="1" ss:Size="11" ss:Color="#16A34A"/>';
    $xml .= '<Interior ss:Color="#FFDABF" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    $xml .= '<Borders>';
    $xml .= '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#C84B11"/>';
    $xml .= '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#C84B11"/>';
    $xml .= '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#C84B11"/>';
    $xml .= '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#C84B11"/>';
    $xml .= '</Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sTotalMinus">';
    $xml .= '<Font ss:FontName="Calibri" ss:Bold="1" ss:Size="11" ss:Color="#DC2626"/>';
    $xml .= '<Interior ss:Color="#FFDABF" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    $xml .= '<Borders>';
    $xml .= '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#C84B11"/>';
    $xml .= '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#C84B11"/>';
    $xml .= '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#C84B11"/>';
    $xml .= '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#C84B11"/>';
    $xml .= '</Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sNote">';
    $xml .= '<Font ss:FontName="Calibri" ss:Italic="1" ss:Size="9" ss:Color="#999999"/>';
    $xml .= '<Interior ss:Color="#FFFAF7" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Vertical="Center"/>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sSheetHead2">';
    $xml .= '<Font ss:FontName="Calibri" ss:Bold="1" ss:Size="16" ss:Color="#FFFFFF"/>';
    $xml .= '<Interior ss:Color="#1E3A5F" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sSheetHead3">';
    $xml .= '<Font ss:FontName="Calibri" ss:Bold="1" ss:Size="16" ss:Color="#FFFFFF"/>';
    $xml .= '<Interior ss:Color="#1A5276" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sColHead2">';
    $xml .= '<Font ss:FontName="Calibri" ss:Bold="1" ss:Size="10" ss:Color="#FFFFFF"/>';
    $xml .= '<Interior ss:Color="#2E86C1" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>';
    $xml .= '<Borders>';
    $xml .= '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#1A5276"/>';
    $xml .= '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#1A5276"/>';
    $xml .= '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1A5276"/>';
    $xml .= '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1A5276"/>';
    $xml .= '</Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sRowOdd2">';
    $xml .= '<Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1C2833"/>';
    $xml .= '<Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    $xml .= '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D6EAF8"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D6EAF8"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D6EAF8"/></Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sRowEven2">';
    $xml .= '<Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1C2833"/>';
    $xml .= '<Interior ss:Color="#EBF5FB" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    $xml .= '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D6EAF8"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D6EAF8"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D6EAF8"/></Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sRowOdd2R">';
    $xml .= '<Font ss:FontName="Calibri" ss:Bold="1" ss:Size="10" ss:Color="#1A5276"/>';
    $xml .= '<Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Right" ss:Vertical="Center"/>';
    $xml .= '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D6EAF8"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D6EAF8"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D6EAF8"/></Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sRowEven2R">';
    $xml .= '<Font ss:FontName="Calibri" ss:Bold="1" ss:Size="10" ss:Color="#1A5276"/>';
    $xml .= '<Interior ss:Color="#EBF5FB" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Right" ss:Vertical="Center"/>';
    $xml .= '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D6EAF8"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D6EAF8"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D6EAF8"/></Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sTotal2">';
    $xml .= '<Font ss:FontName="Calibri" ss:Bold="1" ss:Size="11" ss:Color="#1C2833"/>';
    $xml .= '<Interior ss:Color="#AED6F1" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    $xml .= '<Borders>';
    $xml .= '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#1A5276"/>';
    $xml .= '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#1A5276"/>';
    $xml .= '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1A5276"/>';
    $xml .= '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1A5276"/>';
    $xml .= '</Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sColHead3">';
    $xml .= '<Font ss:FontName="Calibri" ss:Bold="1" ss:Size="10" ss:Color="#FFFFFF"/>';
    $xml .= '<Interior ss:Color="#1E8449" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>';
    $xml .= '<Borders>';
    $xml .= '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#145A32"/>';
    $xml .= '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#145A32"/>';
    $xml .= '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#145A32"/>';
    $xml .= '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#145A32"/>';
    $xml .= '</Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sRowOdd3">';
    $xml .= '<Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1C2833"/>';
    $xml .= '<Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    $xml .= '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D5F5E3"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D5F5E3"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D5F5E3"/></Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sRowEven3">';
    $xml .= '<Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1C2833"/>';
    $xml .= '<Interior ss:Color="#EAFAF1" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    $xml .= '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D5F5E3"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D5F5E3"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D5F5E3"/></Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sRowOdd3L">';
    $xml .= '<Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1C2833"/>';
    $xml .= '<Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Left" ss:Vertical="Center" ss:Indent="1"/>';
    $xml .= '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D5F5E3"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D5F5E3"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D5F5E3"/></Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sRowEven3L">';
    $xml .= '<Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1C2833"/>';
    $xml .= '<Interior ss:Color="#EAFAF1" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Left" ss:Vertical="Center" ss:Indent="1"/>';
    $xml .= '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D5F5E3"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D5F5E3"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D5F5E3"/></Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sRowOdd3R">';
    $xml .= '<Font ss:FontName="Calibri" ss:Bold="1" ss:Size="10" ss:Color="#1E8449"/>';
    $xml .= '<Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Right" ss:Vertical="Center"/>';
    $xml .= '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D5F5E3"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D5F5E3"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D5F5E3"/></Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sRowEven3R">';
    $xml .= '<Font ss:FontName="Calibri" ss:Bold="1" ss:Size="10" ss:Color="#1E8449"/>';
    $xml .= '<Interior ss:Color="#EAFAF1" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Right" ss:Vertical="Center"/>';
    $xml .= '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D5F5E3"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D5F5E3"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D5F5E3"/></Borders>';
    $xml .= '</Style>';

    $xml .= '<Style ss:ID="sTotal3">';
    $xml .= '<Font ss:FontName="Calibri" ss:Bold="1" ss:Size="11" ss:Color="#145A32"/>';
    $xml .= '<Interior ss:Color="#A9DFBF" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    $xml .= '<Borders>';
    $xml .= '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#145A32"/>';
    $xml .= '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#145A32"/>';
    $xml .= '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#145A32"/>';
    $xml .= '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#145A32"/>';
    $xml .= '</Borders>';
    $xml .= '</Style>';

    $xml .= '</Styles>' . "\n";

    // ════════════════════════════════════════
    //  SHEET 1 — Rekap Stok Harian (Detail)
    // ════════════════════════════════════════
    $xml .= '<Worksheet ss:Name="Rekap Stok Harian">' . "\n";
    $xml .= '<Table ss:DefaultRowHeight="20">' . "\n";

    $xml .= '<Column ss:Width="35"/>';
    $xml .= '<Column ss:Width="90"/>';
    $xml .= '<Column ss:Width="200"/>';
    $xml .= '<Column ss:Width="80"/>';
    $xml .= '<Column ss:Width="130"/>';
    $xml .= '<Column ss:Width="120"/>';
    $xml .= '<Column ss:Width="80"/>';
    $xml .= '<Column ss:Width="80"/>';

    $xml .= '<Row ss:Height="42">';
    $xml .= cellM('📦  REKAP STOK HARIAN — E-CANTEEN ESEMKITA', 8, 'sTitle');
    $xml .= '</Row>';

    $xml .= '<Row ss:Height="22">';
    $xml .= cellM('Kantin: ' . $nama_kantin_ex . '   •   Penjual: ' . $nama_pj_ex . '   •   Diekspor: ' . $tgl_export, 8, 'sSubtitle');
    $xml .= '</Row>';

    $xml .= '<Row ss:Height="6"><Cell><Data ss:Type="String"></Data></Cell></Row>';

    $xml .= '<Row ss:Height="30">';
    $xml .= cellS('No', 'sColHead');
    $xml .= cellS('Tanggal', 'sColHead');
    $xml .= cellS('Nama Menu', 'sColHead');
    $xml .= cellS('Stok Terjual', 'sColHeadC');
    $xml .= cellS('Total Pendapatan', 'sColHeadC');
    $xml .= cellS('Harga Satuan', 'sColHeadC');
    $xml .= cellS('Stok Ditambah', 'sColHeadC');
    $xml .= cellS('Selisih', 'sColHeadC');
    $xml .= '</Row>';

    $grand_terjual    = 0;
    $grand_pendapatan = 0.0;
    $grand_ditambah   = 0;
    $no = 1;

    foreach ($data_terjual as $item) {
        $tgl        = $item['tgl'] ?? '';
        $nm         = $item['nama_menu'] ?? '-';
        $key        = $tgl . '|' . $nm;
        $terjual    = (int)($item['total_terjual'] ?? 0);
        $pendapatan = (float)($item['total_rupiah'] ?? 0);
        $harga      = (float)($item['harga_satuan'] ?? 0);
        $ditambah   = $stok_lookup[$key] ?? 0;
        $selisih    = $ditambah - $terjual;
        $isEven     = ($no % 2 === 0);

        $sBg    = $isEven ? 'sRowEven'  : 'sRowOdd';
        $sBgC   = $isEven ? 'sRowEvenC' : 'sRowOddC';
        $sBgR   = $isEven ? 'sRowEvenR' : 'sRowOddR';

        if ($selisih > 0)      $sSelisih = $isEven ? 'sPlusAlt'  : 'sPlus';
        elseif ($selisih < 0)  $sSelisih = $isEven ? 'sMinusAlt' : 'sMinus';
        else                   $sSelisih = $isEven ? 'sZeroAlt'  : 'sZero';

        $selPrefix = $selisih > 0 ? '+' : '';

        $xml .= '<Row ss:Height="20">';
        $xml .= cellS((string)$no, $sBgC);
        $xml .= cellS($tgl, $sBg);
        $xml .= cellS($nm, $sBg);
        $xml .= cellS((string)$terjual, $sBgC);
        $xml .= cellS(rpXml($pendapatan), $sBgR);
        $xml .= cellS(rpXml($harga), $sBgR);
        $xml .= cellS($ditambah > 0 ? (string)$ditambah : '—', $sBgC);
        $xml .= cellS($selPrefix . $selisih, $sSelisih);
        $xml .= '</Row>';

        $grand_terjual    += $terjual;
        $grand_pendapatan += $pendapatan;
        $grand_ditambah   += $ditambah;
        $no++;
    }

    $grand_selisih = $grand_ditambah - $grand_terjual;
    $gSelStyle  = $grand_selisih > 0 ? 'sTotalPlus' : ($grand_selisih < 0 ? 'sTotalMinus' : 'sTotal');
    $gSelPrefix = $grand_selisih > 0 ? '+' : '';

    $xml .= '<Row ss:Height="28">';
    $xml .= cellE('sTotal');
    $xml .= cellE('sTotal');
    $xml .= cellS('TOTAL KESELURUHAN', 'sTotal');
    $xml .= cellS((string)$grand_terjual, 'sTotal');
    $xml .= cellS(rpXml($grand_pendapatan), 'sTotalR');
    $xml .= cellE('sTotal');
    $xml .= cellS((string)$grand_ditambah, 'sTotal');
    $xml .= cellS($gSelPrefix . $grand_selisih, $gSelStyle);
    $xml .= '</Row>';

    $xml .= '<Row ss:Height="6"><Cell><Data ss:Type="String"></Data></Cell></Row>';
    $xml .= '<Row ss:Height="18">';
    $xml .= '<Cell ss:MergeAcross="7" ss:StyleID="sNote"><Data ss:Type="String">';
    $xml .= '★ Keterangan: Selisih = Stok Ditambah - Stok Terjual.  Hijau = surplus (stok lebih banyak ditambah), Merah = defisit (stok kurang dari terjual).';
    $xml .= '</Data></Cell>';
    $xml .= '</Row>';

    $xml .= '</Table>';
    $xml .= '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">';
    $xml .= '<FreezePanes/><FrozenNoSplit/><SplitHorizontal>4</SplitHorizontal><TopRowBottomPane>4</TopRowBottomPane>';
    $xml .= '</WorksheetOptions>';
    $xml .= '</Worksheet>' . "\n";

    // ════════════════════════════════════════
    //  SHEET 2 — Rekap Per Tanggal
    // ════════════════════════════════════════
    $xml .= '<Worksheet ss:Name="Rekap Per Tanggal">' . "\n";
    $xml .= '<Table ss:DefaultRowHeight="20">' . "\n";

    $xml .= '<Column ss:Width="40"/>';
    $xml .= '<Column ss:Width="100"/>';
    $xml .= '<Column ss:Width="120"/>';
    $xml .= '<Column ss:Width="140"/>';
    $xml .= '<Column ss:Width="120"/>';
    $xml .= '<Column ss:Width="90"/>';

    $xml .= '<Row ss:Height="38">';
    $xml .= cellM('📅  REKAP PER TANGGAL — ' . $nama_kantin_ex, 6, 'sSheetHead2');
    $xml .= '</Row>';
    $xml .= '<Row ss:Height="22">';
    $xml .= cellM('Penjual: ' . $nama_pj_ex . '   •   Diekspor: ' . $tgl_export, 6, 'sSubtitle');
    $xml .= '</Row>';
    $xml .= '<Row ss:Height="6"><Cell><Data ss:Type="String"></Data></Cell></Row>';

    $xml .= '<Row ss:Height="28">';
    $xml .= cellS('No', 'sColHead2');
    $xml .= cellS('Tanggal', 'sColHead2');
    $xml .= cellS('Total Item Terjual', 'sColHead2');
    $xml .= cellS('Total Pendapatan', 'sColHead2');
    $xml .= cellS('Total Stok Ditambah', 'sColHead2');
    $xml .= cellS('Selisih', 'sColHead2');
    $xml .= '</Row>';

    $no2 = 1;
    $g2_terjual = 0;
    $g2_pend    = 0.0;
    $g2_tambah  = 0;

    foreach ($per_tgl as $tgl => $v) {
        $sel    = (int)$v['ditambah'] - (int)$v['terjual'];
        $isEven = ($no2 % 2 === 0);
        $sBg2   = $isEven ? 'sRowEven2'  : 'sRowOdd2';
        $sBg2R  = $isEven ? 'sRowEven2R' : 'sRowOdd2R';

        if ($sel > 0)      $sS2 = 'sPlus';
        elseif ($sel < 0)  $sS2 = 'sMinus';
        else               $sS2 = 'sZero';

        $selPfx2 = $sel > 0 ? '+' : '';

        $xml .= '<Row ss:Height="20">';
        $xml .= cellS((string)$no2, $sBg2);
        $xml .= cellS($tgl, $sBg2);
        $xml .= cellS((string)(int)$v['terjual'], $sBg2);
        $xml .= cellS(rpXml((float)$v['pendapatan']), $sBg2R);
        $xml .= cellS((string)(int)$v['ditambah'], $sBg2);
        $xml .= cellS($selPfx2 . $sel, $sS2);
        $xml .= '</Row>';

        $g2_terjual += (int)$v['terjual'];
        $g2_pend    += (float)$v['pendapatan'];
        $g2_tambah  += (int)$v['ditambah'];
        $no2++;
    }

    $g2_sel   = $g2_tambah - $g2_terjual;
    $g2SelPfx = $g2_sel > 0 ? '+' : '';

    $xml .= '<Row ss:Height="28">';
    $xml .= cellE('sTotal2');
    $xml .= cellS('TOTAL', 'sTotal2');
    $xml .= cellS((string)$g2_terjual, 'sTotal2');
    $xml .= cellS(rpXml($g2_pend), 'sTotal2');
    $xml .= cellS((string)$g2_tambah, 'sTotal2');
    $xml .= cellS($g2SelPfx . $g2_sel, $g2_sel >= 0 ? 'sTotalPlus' : 'sTotalMinus');
    $xml .= '</Row>';

    $xml .= '</Table>';
    $xml .= '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">';
    $xml .= '<FreezePanes/><FrozenNoSplit/><SplitHorizontal>4</SplitHorizontal><TopRowBottomPane>4</TopRowBottomPane>';
    $xml .= '</WorksheetOptions>';
    $xml .= '</Worksheet>' . "\n";

    // ════════════════════════════════════════
    //  SHEET 3 — Top Menu Terlaris
    // ════════════════════════════════════════
    $xml .= '<Worksheet ss:Name="Top Menu Terlaris">' . "\n";
    $xml .= '<Table ss:DefaultRowHeight="20">' . "\n";

    $xml .= '<Column ss:Width="40"/>';
    $xml .= '<Column ss:Width="210"/>';
    $xml .= '<Column ss:Width="110"/>';
    $xml .= '<Column ss:Width="140"/>';
    $xml .= '<Column ss:Width="100"/>';

    $xml .= '<Row ss:Height="38">';
    $xml .= cellM('🏆  TOP MENU TERLARIS 30 HARI — ' . $nama_kantin_ex, 5, 'sSheetHead3');
    $xml .= '</Row>';
    $xml .= '<Row ss:Height="22">';
    $xml .= cellM('Penjual: ' . $nama_pj_ex . '   •   Diekspor: ' . $tgl_export, 5, 'sSubtitle');
    $xml .= '</Row>';
    $xml .= '<Row ss:Height="6"><Cell><Data ss:Type="String"></Data></Cell></Row>';

    $xml .= '<Row ss:Height="28">';
    $xml .= cellS('Rank', 'sColHead3');
    $xml .= cellS('Nama Menu', 'sColHead3');
    $xml .= cellS('Total Terjual', 'sColHead3');
    $xml .= cellS('Total Pendapatan', 'sColHead3');
    $xml .= cellS('% Share', 'sColHead3');
    $xml .= '</Row>';

    $top15    = array_slice($per_menu, 0, 15, true);
    $totalAll = array_sum(array_column(array_values($top15), 'terjual'));
    if ($totalAll === 0) $totalAll = 1;

    $no3 = 1;
    $g3_terjual = 0;
    $g3_pend    = 0.0;

    foreach ($top15 as $nm => $v) {
        $isEven = ($no3 % 2 === 0);
        $sBg3   = $isEven ? 'sRowEven3'  : 'sRowOdd3';
        $sBg3L  = $isEven ? 'sRowEven3L' : 'sRowOdd3L';
        $sBg3R  = $isEven ? 'sRowEven3R' : 'sRowOdd3R';
        $pct    = round(($v['terjual'] / $totalAll) * 100, 1);
        $medal  = $no3 === 1 ? '🥇 ' : ($no3 === 2 ? '🥈 ' : ($no3 === 3 ? '🥉 ' : ''));

        $xml .= '<Row ss:Height="22">';
        $xml .= cellS((string)$no3, $sBg3);
        $xml .= cellS($medal . $nm, $sBg3L);
        $xml .= cellS((string)$v['terjual'], $sBg3);
        $xml .= cellS(rpXml((float)$v['pendapatan']), $sBg3R);
        $xml .= cellS($pct . '%', $sBg3);
        $xml .= '</Row>';

        $g3_terjual += $v['terjual'];
        $g3_pend    += $v['pendapatan'];
        $no3++;
    }

    $xml .= '<Row ss:Height="28">';
    $xml .= cellE('sTotal3');
    $xml .= cellS('TOTAL (Top 15)', 'sTotal3');
    $xml .= cellS((string)$g3_terjual, 'sTotal3');
    $xml .= cellS(rpXml($g3_pend), 'sTotal3');
    $xml .= cellS('100%', 'sTotal3');
    $xml .= '</Row>';

    $xml .= '<Row ss:Height="6"><Cell><Data ss:Type="String"></Data></Cell></Row>';
    $xml .= '<Row ss:Height="18">';
    $xml .= '<Cell ss:MergeAcross="4" ss:StyleID="sNote"><Data ss:Type="String">';
    $xml .= '★ Data berdasarkan transaksi berstatus selesai/lunas/diambil/completed dalam 30 hari terakhir.';
    $xml .= '</Data></Cell>';
    $xml .= '</Row>';

    $xml .= '</Table>';
    $xml .= '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">';
    $xml .= '<FreezePanes/><FrozenNoSplit/><SplitHorizontal>4</SplitHorizontal><TopRowBottomPane>4</TopRowBottomPane>';
    $xml .= '</WorksheetOptions>';
    $xml .= '</Worksheet>' . "\n";

    $xml .= '</Workbook>';

    // ── Output file ──
    $filename = 'Rekap_Stok_Harian_' . date('Ymd_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    ob_end_clean();
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    echo $xml;
    exit;
}

// ════════════════════════════════════════════════════
//  KONEKSI DATABASE
// ════════════════════════════════════════════════════
include "../users/koneksi.php";
include 'chat_popup_penjual.php';

$conn = $konek;
$conn->set_charset('utf8mb4');

// ════════════════════════════════════════════════════
//  AMBIL DATA PENJUAL
// ════════════════════════════════════════════════════
$ses_username = $_SESSION['username'];

$stmt = $conn->prepare("
    SELECT p.id_penjual, p.nama, p.email, p.username, p.profil,
           p.id_kantin, k.nama_kantin, COALESCE(p.buka, 0) AS buka
    FROM   penjual p
    LEFT JOIN kantin k ON k.id_kantin = p.id_kantin
    WHERE  p.username = ?
    LIMIT  1
");
$stmt->bind_param('s', $ses_username);
$stmt->execute();
$penjual = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$penjual) {
    session_destroy();
    header('Location: ../users/login.php');
    exit;
}

if (!isset($_SESSION['id_kantin']))  $_SESSION['id_kantin']  = $penjual['id_kantin'];
if (!isset($_SESSION['id_penjual'])) $_SESSION['id_penjual'] = $penjual['id_penjual'];

$user_penjual = $penjual['nama'];
$id_penjual   = (int) $penjual['id_penjual'];
$id_kantin    = (int) $penjual['id_kantin'];
$nama_kantin  = $penjual['nama_kantin'] ?? 'Kantin';
$kantin_buka  = (int) ($penjual['buka'] ?? 0);

$foto_profil_file = $penjual['profil'];
$kemungkinan_path = [
    'profil/' . $foto_profil_file,
    '../profil/' . $foto_profil_file,
    'uploads/' . $foto_profil_file,
    '../admin/uploads/' . $foto_profil_file,
];
$foto_profil = 'https://ui-avatars.com/api/?name=' . urlencode($user_penjual) . '&background=e8650a&color=fff&size=80';
foreach ($kemungkinan_path as $path) {
    if (!empty($foto_profil_file) && file_exists($path)) {
        $foto_profil = $path;
        break;
    }
}

// ════════════════════════════════════════════════════
//  STATISTIK
// ════════════════════════════════════════════════════

// 1a. Penjualan hari ini
$res = $conn->prepare("
    SELECT COALESCE(SUM(total), 0) AS penjualan_hari_ini
    FROM   transaksi
    WHERE  id_kantin = ?
      AND  DATE(tanggal) = CURDATE()
      AND  status IN ('selesai','lunas','diambil','completed')
");
$res->bind_param('i', $id_kantin);
$res->execute();
$penjualan_hari_ini = (float) $res->get_result()->fetch_assoc()['penjualan_hari_ini'];
$res->close();

// 1b. Pesanan hari ini
$res = $conn->prepare("
    SELECT COUNT(*) AS pesanan_hari_ini
    FROM   transaksi
    WHERE  id_kantin = ?
      AND  DATE(tanggal) = CURDATE()
      AND  status NOT IN ('batal','ditolak')
");
$res->bind_param('i', $id_kantin);
$res->execute();
$pesanan_hari_ini = (int) $res->get_result()->fetch_assoc()['pesanan_hari_ini'];
$res->close();

// 2. Produk aktif
$res = $conn->prepare("SELECT COUNT(*) AS jml FROM menu_req WHERE id_kantin = ? AND aktif = 1 AND deleted_at IS NULL");
$res->bind_param('i', $id_kantin);
$res->execute();
$produk_aktif = (int) $res->get_result()->fetch_assoc()['jml'];
$res->close();

// 3. Stok menipis
$res = $conn->prepare("SELECT COUNT(*) AS jml FROM menu_req WHERE id_kantin = ? AND aktif = 1 AND deleted_at IS NULL AND stok <= 5");
$res->bind_param('i', $id_kantin);
$res->execute();
$stok_menipis = (int) $res->get_result()->fetch_assoc()['jml'];
$res->close();

// 4. Pending orders
$res = $conn->prepare("SELECT COUNT(*) AS jml FROM transaksi WHERE id_kantin = ? AND status = 'pending'");
$res->bind_param('i', $id_kantin);
$res->execute();
$pending_orders = (int) $res->get_result()->fetch_assoc()['jml'];
$res->close();

// 5. Produk terlaris minggu ini
$res = $conn->prepare("
    SELECT t.nama_makanan, SUM(t.jumlah) AS total_terjual,
           t.id_menu, m.harga, m.foto
    FROM   transaksi t
    LEFT JOIN menu_req m ON m.id = t.id_menu
    WHERE  t.id_kantin = ?
      AND  t.tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
      AND  m.deleted_at IS NULL
      AND  m.aktif = 1
    GROUP  BY t.id_menu, t.nama_makanan, m.harga, m.foto
    ORDER  BY total_terjual DESC
    LIMIT  3
");
$res->bind_param('i', $id_kantin);
$res->execute();
$produk_terlaris = $res->get_result()->fetch_all(MYSQLI_ASSOC);
$res->close();

$max_terjual = !empty($produk_terlaris) ? (int) $produk_terlaris[0]['total_terjual'] : 1;

// ════════════════════════════════════════════════════
//  STOK HARIAN: terjual + ditambah per hari (14 hari)
// ════════════════════════════════════════════════════

$res = $conn->prepare("
    SELECT
        DATE(t.tanggal)  AS tgl,
        t.nama_makanan   AS nama_menu,
        SUM(t.jumlah)    AS terjual,
        SUM(t.total)     AS pendapatan
    FROM transaksi t
    LEFT JOIN menu_req m ON m.id = t.id_menu
    WHERE t.id_kantin = ?
      AND t.tanggal >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
      AND t.status IN ('selesai','lunas','diambil','completed')
      AND (m.deleted_at IS NULL OR m.id IS NULL)
    GROUP BY DATE(t.tanggal), t.id_menu, t.nama_makanan
    ORDER BY DATE(t.tanggal) DESC, terjual DESC
");
$res->bind_param('i', $id_kantin);
$res->execute();
$stok_terjual_harian = $res->get_result()->fetch_all(MYSQLI_ASSOC);
$res->close();

// ── FIX: GROUP BY menggunakan nama kolom asli m.nama_makanan ──
$stok_ditambah_harian = [];
$tbl_ada = $conn->query("SHOW TABLES LIKE 'stok_log'");
if ($tbl_ada && $tbl_ada->num_rows > 0) {
    $res = $conn->prepare("
        SELECT
            DATE(sl.created_at) AS tgl,
            m.nama_makanan      AS nama_menu,
            SUM(sl.jumlah)      AS ditambah
        FROM stok_log sl
        JOIN menu_req m ON m.id = sl.id_menu
        WHERE m.id_kantin = ?
          AND sl.created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
          AND sl.tipe = 'tambah'
        GROUP BY DATE(sl.created_at), sl.id_menu, m.nama_makanan
        ORDER BY DATE(sl.created_at) DESC
    ");
    $res->bind_param('i', $id_kantin);
    $res->execute();
    $stok_ditambah_harian = $res->get_result()->fetch_all(MYSQLI_ASSOC);
    $res->close();
}

// Rekapan pembelian 50 terakhir
$res = $conn->prepare("
    SELECT t.id_transaksi, t.nama_makanan, t.tanggal, t.total,
           t.jumlah, t.status, t.metode, t.antrian,
           u.nama AS nama_pembeli
    FROM   transaksi t
    LEFT JOIN users u ON u.id_user = t.id_user
    WHERE  t.id_kantin = ?
    ORDER  BY t.tanggal DESC
    LIMIT  50
");
$res->bind_param('i', $id_kantin);
$res->execute();
$rekap_transaksi = $res->get_result()->fetch_all(MYSQLI_ASSOC);
$res->close();

$conn->close();

function rupiah(float $n): string {
    return 'Rp ' . number_format($n, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>E-Canteen – Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Righteous&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <link rel="stylesheet" href="dasbord.css" />
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

  <!-- ═══════════════ NAVBAR ═══════════════ -->
  <div class="navbar orange-bg">
    <div class="navbar-texture"></div>
    <div class="orb1"></div>
    <div class="orb2"></div>
    <button class="hamburger-btn" id="hamburgerBtn" aria-label="Buka menu navigasi" onclick="toggleSidebar()">
      <i class="fa-solid fa-bars"></i>
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
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <div class="search-wrap">
      <div class="search-box">
        <input
          id="navSearchInput"
          type="text"
          placeholder="Cari rekapan penjualan..."
          oninput="onNavSearchInput()"
          onkeydown="if(event.key==='Enter') triggerNavSearch()"
          autocomplete="off"
        />
        <button onclick="triggerNavSearch()">
          <i class="fa-solid fa-magnifying-glass"></i>
        </button>
      </div>
    </div>

    <div class="icon-actions">
      <button onclick="openChatPopup()" class="icon-btn chat-trigger-btn notif-chat-btn">
        <i class="fa-solid fa-comment-dots"></i>
        <span class="chat-badge" id="chatBadge" style="display:none"></span>
      </button>
      <a href="pesanan.php" class="icon-btn" id="pesananNotifBtn" style="position:relative;text-decoration:none;" title="Pesanan perlu dikonfirmasi">
        <i class="fa-solid fa-bell"></i>
        <span id="pesananNotifBadge" style="display:none;position:absolute;top:-7px;right:-7px;min-width:20px;height:20px;background:#ef4444;color:#fff;border-radius:10px;font-size:11px;font-weight:800;align-items:center;justify-content:center;padding:0 5px;border:2px solid #fff;box-shadow:0 2px 8px rgba(239,68,68,.5);line-height:1;">0</span>
      </a>
    </div>
  </div>

  <!-- ═══════════════ REKAP DROPDOWN PANEL ═══════════════ -->
  <div class="rekap-dropdown-panel" id="rekapDropdownPanel">
    <div class="rekap-dropdown-inner">
      <div class="rdp-category-row">
        <span class="rdp-label"><i class="fa-solid fa-layer-group"></i> Kategori Rekap:</span>
        <div class="rdp-tabs">
          <button class="rdp-tab active" onclick="selectRdpCategory('harian', this)">Harian</button>
          <button class="rdp-tab" onclick="selectRdpCategory('mingguan', this)">Mingguan</button>
          <button class="rdp-tab" onclick="selectRdpCategory('bulanan', this)">Bulanan</button>
          <button class="rdp-tab" onclick="selectRdpCategory('semua', this)">Semua</button>
        </div>
        <button class="rdp-close" onclick="closeRekapPanel()"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="rdp-date-row" id="rdpDateRow"></div>
      <div class="rdp-action-row">
        <button class="rdp-btn-show" onclick="generateRdpRecap()">
          <i class="fa-solid fa-chart-bar"></i> Tampilkan Rekap
        </button>
      </div>
      <div id="rdpOutput" class="rdp-output"></div>
      <div class="rdp-bottom-actions" id="rdpBottomActions" style="display:none;">
        <button class="rdp-act-btn primary" onclick="printRdpRecap()">
          <i class="fa-solid fa-print"></i> Print Rekap
        </button>
        <button class="rdp-act-btn secondary" onclick="downloadRdpPNG()">
          <i class="fa-solid fa-image"></i> Download PNG
        </button>
      </div>
    </div>
  </div>

  <!-- ═══════════════ PAGE WRAPPER ═══════════════ -->
  <div class="page-wrapper">

    <!-- ── SIDEBAR ── -->
    <aside class="sidebar">
      <div class="sidebar-profile">
        <div class="sidebar-avatar">
          <img src="<?= htmlspecialchars($foto_profil) ?>" alt="Avatar"
               onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user_penjual) ?>&background=e8650a&color=fff&size=80'" />
        </div>
        <div class="sidebar-profile-info">
          <h3><?= htmlspecialchars($user_penjual) ?></h3>
          <small style="color:#aaa;font-size:.75rem;"><?= htmlspecialchars($nama_kantin) ?></small><br>
          <a href="profil/profile.php">✏️ Ubah Profil</a>
        </div>
      </div>

      <div class="sidebar-nav-area">
        <a href="dasbord.php" class="active">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
            <path fill="currentColor" d="M6 19h3v-6h6v6h3v-9l-6-4.5L6 10zm-2 2V9l8-6l8 6v12h-7v-6h-2v6zm8-8.75"/>
          </svg>
          Dashboard
        </a>
        <a href="produk.php">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
            <path fill="currentColor" d="M19 4h-2V3a1 1 0 0 0-2 0v1H9V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3m1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7h16Zm0-9H4V7a1 1 0 0 1 1-1h2v1a1 1 0 0 0 2 0V6h6v1a1 1 0 0 0 2 0V6h2a1 1 0 0 1 1 1Z"/>
          </svg>
          Produk Saya
        </a>
        <a href="pesanan.php" style="position:relative;">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
            <path fill="currentColor" d="M19 4h-2V3a1 1 0 0 0-2 0v1H9V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3m1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7h16Zm0-9H4V7a1 1 0 0 1 1-1h2v1a1 1 0 0 0 2 0V6h6v1a1 1 0 0 0 2 0V6h2a1 1 0 0 1 1 1Z"/>
          </svg>
          Pesanan Masuk
          <?php if ($pending_orders > 0): ?>
          <span id="sidebarPesananBadge" style="position:absolute;top:50%;right:12px;transform:translateY(-50%);background:#ef4444;color:#fff;border-radius:10px;font-size:11px;font-weight:800;min-width:20px;height:20px;display:flex;align-items:center;justify-content:center;padding:0 5px;border:2px solid #fff;box-shadow:0 2px 8px rgba(239,68,68,.45);line-height:1;"><?= $pending_orders ?></span>
          <?php else: ?>
          <span id="sidebarPesananBadge" style="display:none;position:absolute;top:50%;right:12px;transform:translateY(-50%);background:#ef4444;color:#fff;border-radius:10px;font-size:11px;font-weight:800;min-width:20px;height:20px;align-items:center;justify-content:center;padding:0 5px;border:2px solid #fff;box-shadow:0 2px 8px rgba(239,68,68,.45);line-height:1;">0</span>
          <?php endif; ?>
        </a>
        <a href="pendapatan.php">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 512 512">
            <path fill="currentColor" d="M32 376a56 56 0 0 0 56 56h336a56 56 0 0 0 56-56V222H32Zm66-76a30 30 0 0 1 30-30h48a30 30 0 0 1 30 30v20a30 30 0 0 1-30 30h-48a30 30 0 0 1-30-30ZM424 80H88a56 56 0 0 0-56 56v26h448v-26a56 56 0 0 0-56-56"/>
          </svg>
          Pendapatan
        </a>
      </div>

      <div class="sidebar-divider"></div>

      <div class="sidebar-bottom">
        <a href="suport.php">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
            <path fill="currentColor" d="M22 17.002a6 6 0 0 1-4.713 5.86l-.638-1.914A4 4 0 0 0 19.465 19H17a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h2.938a8.001 8.001 0 0 0-15.876 0H7a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-5C2 6.477 6.477 2 12 2s10 4.477 10 10zM20 17v-4h-3v4zM4 13v4h3v-4z"/>
          </svg>
          Support
        </a>
        <a href="pengaturan.php">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
            <path fill="currentColor" d="m9.25 22l-.4-3.2q-.325-.125-.612-.3t-.563-.375L4.7 19.375l-2.75-4.75l2.575-1.95Q4.5 12.5 4.5 12.338v-.675q0-.163.025-.338L1.95 9.375l2.75-4.75l2.975 1.25q.275-.2.575-.375t.6-.3l.4-3.2h5.5l.4 3.2q.325.125.613.3t.562.375l2.975-1.25l2.75 4.75l-2.575 1.95q.025.175.025.338v.674q0 .163-.05.338l2.575 1.95l-2.75 4.75l-2.95-1.25q-.275.2-.575.375t-.6.3l-.4 3.2zM11 20h1.975l.35-2.65q.775-.2 1.438-.587t1.212-.938l2.475 1.025l.975-1.7l-2.15-1.625q.125-.35.175-.737T17.5 12t-.05-.787t-.175-.738l2.15-1.625l-.975-1.7l-2.475 1.05q-.55-.575-1.212-.962t-1.438-.588L13 4h-1.975l-.35 2.65q-.775.2-1.437.588t-1.213.937L5.55 7.15l-.975 1.7l2.15 1.6q-.125.375-.175.75t-.05.8q0 .4.05.775t.175.75l-2.15 1.625l.975 1.7l2.475-1.05q.55.575 1.213.963t1.437.587zm1.05-4.5q1.45 0 2.475-1.025T15.55 12t-1.025-2.475T12.05 8.5q-1.475 0-2.488 1.025T8.55 12t1.013 2.475T12.05 15.5M12 12"/>
          </svg>
          Pengaturan
        </a>
      </div>
    </aside>

    <!-- ── MAIN CONTENT ── -->
    <main class="main">
      <h2 class="main-title">Dashboard E-Canteen</h2>

      <!-- Tombol Buka/Tutup Kantin -->
      <div class="kantin-status-bar" id="kantinStatusBar">
        <div class="kantin-status-info">
          <span class="kantin-status-dot <?= $kantin_buka ? 'open' : 'closed' ?>" id="kantinStatusDot"></span>
          <span class="kantin-status-label" id="kantinStatusLabel">
            Status Kantin: <strong id="kantinStatusText"><?= $kantin_buka ? 'Sedang Buka' : 'Sedang Tutup' ?></strong>
          </span>
        </div>
        <button
          class="kantin-toggle-btn <?= $kantin_buka ? 'btn-tutup' : 'btn-buka' ?>"
          id="kantinToggleBtn"
          data-id="<?= $id_penjual ?>"
          data-open="<?= $kantin_buka ?>"
          onclick="toggleKantin(this)">
          <i class="fa-solid <?= $kantin_buka ? 'fa-door-closed' : 'fa-door-open' ?>" id="kantinToggleIcon"></i>
          <span id="kantinToggleLabel"><?= $kantin_buka ? 'Tutup Kantin' : 'Buka Kantin' ?></span>
        </button>
      </div>

      <!-- Stat Cards -->
      <div class="stat-cards">
        <div class="stat-card">
          <div class="stat-top">
            <span class="stat-label">Penjualan Hari Ini</span>
            <span class="stat-icon" style="background:#e3f6ff;font-size:.72rem;font-weight:800;color:#2196f3;">Rp</span>
          </div>
          <div class="stat-value" style="font-size:1.6rem"><?= rupiah($penjualan_hari_ini) ?></div>
          <div class="stat-growth positive">Transaksi selesai hari ini</div>
        </div>
        <div class="stat-card">
          <div class="stat-top">
            <span class="stat-label">Pesanan Hari Ini</span>
            <span class="stat-icon" style="background:#e3ffeb;">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                <path fill="#27ae60" d="M17 18a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2c0-1.11.89-2 2-2M1 2h3.27l.94 2H20a1 1 0 0 1 1 1c0 .17-.05.34-.12.5l-3.58 6.47c-.34.61-1 1.03-1.75 1.03H8.1l-.9 1.63l-.03.12a.25.25 0 0 0 .25.25H19v2H7a2 2 0 0 1-2-2c0-.35.09-.68.24-.96l1.36-2.45L3 4H1zm6 16a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2c0-1.11.89-2 2-2m9-7l2.78-5H6.14l2.36 5z"/>
              </svg>
            </span>
          </div>
          <div class="stat-value"><?= $pesanan_hari_ini ?></div>
          <div class="stat-growth positive">Pesanan selesai hari ini</div>
        </div>
        <div class="stat-card">
          <div class="stat-top">
            <span class="stat-label">Produk Aktif</span>
            <span class="stat-icon" style="background:#f7e5ff;">
              <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 16 16">
                <path fill="currentColor" d="M10.9 8.6c.6-.1 1.2-.4 1.6-.9l3.1-3.1c.4-.4.4-1 0-1.4l-.1-.2l-3 3c-.2.2-.6.2-.9 0s-.2-.6 0-.9l2.6-2.6c.2-.2.2-.6 0-.9c-.2-.2-.6-.2-.9 0l-2.6 2.6c-.2.2-.6.2-.9 0c-.2-.2-.2-.6 0-.9l3-3l-.1-.1c-.4-.4-1-.4-1.4 0L8.2 3.5c-.4.4-.7 1-.8 1.6L2.5.3c-.4-.4-1-.3-1.3 0L1 .5C-.4 1.9.1 4.7 2.5 7.1l.8.8c.4.4.9.7 1.5.8c-.5.4-.8.8-.8.8L0.6 12.9c-.7.7-.7 1.9 0 2.6s1.9.7 2.6 0L6.5 12c.2-.2.7-.8 1.3-1.5c.3.4.5.6.5.6l4.3 4.3c.7.7 1.9.7 2.6 0s.7-1.9 0-2.6z"/>
              </svg>
            </span>
          </div>
          <div class="stat-value"><?= $produk_aktif ?></div>
          <div class="stat-growth <?= $stok_menipis > 0 ? 'warn' : 'positive' ?>">
            <?= $stok_menipis > 0 ? $stok_menipis . ' Stok Menipis' : 'Semua stok aman' ?>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-top">
            <span class="stat-label">Pending Orders</span>
            <span class="stat-icon" style="background:#fff1e5;">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                <path fill="#e87722" d="M21.7 7.3c-.4-.4-1-.4-1.4 0L14 13.6L9.7 9.3C9.5 9.1 9.3 9 9 9s-.5.1-.7.3l-6 6c-.2.2-.3.4-.3.7c0 .6.4 1 1 1c.3 0 .5-.1.7-.3L9 11.4l4.3 4.3c.1.1.2.2.3.2c.1.1.3.1.4.1c.2 0 .5-.1.6-.3h.1l7-7c.4-.4.4-1 0-1.4"/>
              </svg>
            </span>
          </div>
          <div class="stat-value"><?= $pending_orders ?></div>
          <div class="stat-growth <?= $pending_orders > 0 ? 'warn' : 'positive' ?>">
            <?= $pending_orders > 0 ? 'Butuh Perhatian' : 'Tidak ada pending' ?>
          </div>
        </div>
      </div>

      <!-- Produk Terlaris -->
      <div class="section-card">
        <div class="section-card-title">Produk Terlaris Minggu Ini — <?= htmlspecialchars($nama_kantin) ?></div>
        <div class="top-list">
          <?php if (empty($produk_terlaris)): ?>
            <p style="color:#aaa;padding:1rem;">Belum ada data penjualan minggu ini.</p>
          <?php else: ?>
            <?php foreach ($produk_terlaris as $item):
              $foto_menu = !empty($item['foto']) ? 'uploads/' . $item['foto'] : 'gambar/default_food.png';
              $pct = $max_terjual > 0 ? round(($item['total_terjual'] / $max_terjual) * 100) : 0;
            ?>
            <div class="top-item">
              <div class="top-item-img">
                <img src="<?= htmlspecialchars($foto_menu) ?>" alt="<?= htmlspecialchars($item['nama_makanan']) ?>"
                     onerror="this.src='gambar/default_food.png'" />
              </div>
              <div class="top-info">
                <div class="top-name"><?= htmlspecialchars($item['nama_makanan']) ?></div>
                <div class="top-sold"><?= (int)$item['total_terjual'] ?> Terjual</div>
              </div>
              <div class="top-right-col">
                <div class="top-price"><?= rupiah((float)$item['harga']) ?></div>
                <div class="top-bar"><div class="top-bar-fill" style="width:<?= $pct ?>%"></div></div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- STOK HARIAN SECTION -->
      <div class="section-card">
        <div class="section-card-header">
          <div class="section-card-title">
            <i class="fa-solid fa-boxes-stacking" style="color:#e8714a;margin-right:7px;"></i>
            Rekap Stok Harian — <?= htmlspecialchars($nama_kantin) ?>
          </div>
          <div style="display:flex;align-items:center;gap:8px;">
            <div class="stok-filter-wrap">
              <label class="stok-filter-label">Tampilkan:</label>
              <select id="stokHarianFilter" class="stok-filter-select" onchange="filterStokHarian(this.value)">
                <option value="7">7 Hari</option>
                <option value="14" selected>14 Hari</option>
              </select>
            </div>
            <div class="table-menu" id="stokMenuWrap">
              <button class="menu-btn" onclick="toggleStokMenu(event)" title="Opsi">
                <i class="fa-solid fa-ellipsis-vertical"></i>
              </button>
              <div class="table-dropdown" id="stokDropdown">
                <button onclick="exportStokExcel()">
                  <i class="fa-solid fa-file-excel" style="color:#217346;"></i> Download Excel
                </button>
                <button onclick="printStokHarian()">
                  <i class="fa-solid fa-print"></i> Print Rekap Stok
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="stok-summary-cards" id="stokSummaryCards"></div>

        <div class="table-wrap" id="stokTableWrap">
          <table class="orders-table stok-harian-table" id="stokHarianTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Tanggal</th>
                <th>Nama Menu</th>
                <th style="text-align:center;">Stok Terjual</th>
                <th style="text-align:center;">Stok Ditambah</th>
                <th style="text-align:center;">Selisih</th>
                <th style="text-align:right;">Pendapatan</th>
              </tr>
            </thead>
            <tbody id="stokHarianBody"></tbody>
          </table>
          <div class="pagination" id="stokPaginationWrap"></div>
        </div>
      </div>

      <!-- Tabel Rekapan Pembelian -->
      <div class="section-card">
        <div class="section-card-header">
          <div class="section-card-title">Rekapan Pembelian — <?= htmlspecialchars($nama_kantin) ?></div>
          <div class="table-menu">
            <button class="menu-btn" onclick="toggleTableMenu(event)">
              <i class="fa-solid fa-ellipsis-vertical"></i>
            </button>
            <div class="table-dropdown" id="tableDropdown">
              <button onclick="refreshTable()">
                <i class="fa-solid fa-rotate-right"></i> Refresh Data
              </button>
              <button onclick="printFullTable()">
                <i class="fa-solid fa-print"></i> Print Semua Data
              </button>
            </div>
          </div>
        </div>
        <div class="table-wrap" id="mainTableWrap">
          <table class="orders-table" id="mainTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Produk</th>
                <th>Order ID</th>
                <th>Tanggal</th>
                <th>Harga</th>
                <th>Nama Pembeli</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="mainTableBody"></tbody>
          </table>
          <div class="pagination" id="paginationWrap"></div>
        </div>
      </div>

      <!-- DATA INJECTION -->
      <script>
        window.rekapData = <?= json_encode(array_map(function($t) {
          return [
            'id'      => $t['id_transaksi'],
            'produk'  => $t['nama_makanan'],
            'tanggal' => $t['tanggal'],
            'total'   => $t['total'],
            'jumlah'  => $t['jumlah'],
            'metode'  => $t['metode'],
            'status'  => $t['status'],
            'antrian' => $t['antrian'],
            'pembeli' => $t['nama_pembeli'] ?? 'Anonim',
          ];
        }, $rekap_transaksi), JSON_UNESCAPED_UNICODE) ?>;

        window.stokTerjualData = <?= json_encode(array_map(function($r) {
          return [
            'tgl'        => $r['tgl'],
            'nama_menu'  => $r['nama_menu'],
            'terjual'    => (int)$r['terjual'],
            'pendapatan' => (float)$r['pendapatan'],
          ];
        }, $stok_terjual_harian), JSON_UNESCAPED_UNICODE) ?>;

        window.stokDitambahData = <?= json_encode(array_map(function($r) {
          return [
            'tgl'       => $r['tgl'],
            'nama_menu' => $r['nama_menu'],
            'ditambah'  => (int)$r['ditambah'],
          ];
        }, $stok_ditambah_harian), JSON_UNESCAPED_UNICODE) ?>;
      </script>

    </main>
  </div>

  <!-- ═══════════════ STRUK POPUP ═══════════════ -->
  <div class="struk-popup" id="strukPopup">
    <div class="struk-popup-content">
      <button class="close-popup" onclick="closeStrukPopup()">&#x2715;</button>
      <div id="strukPreviewArea"></div>
      <div class="popup-actions">
        <button class="popup-btn secondary" onclick="closeStrukPopup()">Kembali</button>
        <button class="popup-btn primary" onclick="downloadCurrentStruk()">&#x2193; Download Struk</button>
      </div>
    </div>
  </div>

  <!-- ═══════════════ EXCEL LOADING OVERLAY ═══════════════ -->
  <div id="excelLoadingOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);backdrop-filter:blur(4px);z-index:99999;align-items:center;justify-content:center;flex-direction:column;gap:16px;">
    <div style="background:#fff;border-radius:20px;padding:32px 40px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.3);">
      <div style="font-size:2.5rem;margin-bottom:12px;">📊</div>
      <div style="font-size:1rem;font-weight:700;color:#222;margin-bottom:6px;">Membuat File Excel...</div>
      <div style="font-size:.85rem;color:#888;">Mohon tunggu, sedang memproses data stok</div>
      <div style="margin-top:16px;display:flex;gap:6px;justify-content:center;">
        <span style="width:8px;height:8px;border-radius:50%;background:#e8714a;animation:dotpulse .9s infinite .0s;display:inline-block;"></span>
        <span style="width:8px;height:8px;border-radius:50%;background:#e8714a;animation:dotpulse .9s infinite .2s;display:inline-block;"></span>
        <span style="width:8px;height:8px;border-radius:50%;background:#e8714a;animation:dotpulse .9s infinite .4s;display:inline-block;"></span>
      </div>
    </div>
  </div>

  <script src="dasbord.js"></script>

  <script>
  /* ── Toggle Buka / Tutup Kantin ── */
  async function toggleKantin(btn) {
    btn.disabled = true;
    const idPenjual = btn.dataset.id;
    const label     = document.getElementById('kantinToggleLabel');
    const icon      = document.getElementById('kantinToggleIcon');
    const dot       = document.getElementById('kantinStatusDot');
    const statusTx  = document.getElementById('kantinStatusText');

    label.textContent = 'Memproses...';

    try {
      const fd = new FormData();
      fd.append('action', 'toggle_kantin');
      fd.append('id_penjual', idPenjual);
      const res  = await fetch('dasbord.php', { method: 'POST', body: fd });
      const data = await res.json();

      if (data.success) {
        const isOpen = data.buka === 1;
        btn.dataset.open   = isOpen ? '1' : '0';
        btn.className      = 'kantin-toggle-btn ' + (isOpen ? 'btn-tutup' : 'btn-buka');
        icon.className     = 'fa-solid ' + (isOpen ? 'fa-door-closed' : 'fa-door-open');
        label.textContent  = isOpen ? 'Tutup Kantin' : 'Buka Kantin';
        dot.className      = 'kantin-status-dot ' + (isOpen ? 'open' : 'closed');
        statusTx.textContent = isOpen ? 'Sedang Buka' : 'Sedang Tutup';
        showKantinToast(isOpen
          ? '🟢 Kantin berhasil DIBUKA! Pelanggan sudah bisa memesan.'
          : '🔴 Kantin ditutup. Pesanan masuk ditangguhkan.');
      } else {
        showKantinToast('❌ Gagal: ' + (data.message || 'Error'));
      }
    } catch(e) {
      showKantinToast('❌ Koneksi bermasalah, coba lagi.');
    }
    btn.disabled = false;
  }

  function showKantinToast(msg) {
    let toast = document.getElementById('kantinToast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'kantinToast';
      toast.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#333;color:#fff;padding:12px 22px;border-radius:12px;font-size:.9rem;font-weight:600;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.35);transition:opacity .3s;white-space:nowrap;';
      document.body.appendChild(toast);
    }
    toast.textContent  = msg;
    toast.style.opacity = '1';
    clearTimeout(toast._t);
    toast._t = setTimeout(() => { toast.style.opacity = '0'; }, 3500);
  }
  </script>

  <script>
  /* ── Notif Chat ── */
  async function loadNotifChat() {
    try {
      const res  = await fetch('../users/chat_api.php?action=jumlah_belum_baca');
      const data = await res.json();
      const badge = document.getElementById('chatBadge');
      if (data.belum_baca > 0) {
        badge.style.display = 'flex';
        badge.textContent   = data.belum_baca;
      } else {
        badge.style.display = 'none';
      }
    } catch (err) {}
  }
  loadNotifChat();
  setInterval(loadNotifChat, 10000);
  </script>

  <script>
  /* ── Notif Pesanan Masuk ── */
  function loadNotifPesanan() {
    fetch('pesanan.php?action=get_notif_count')
      .then(r => r.json())
      .then(data => {
        const badge        = document.getElementById('pesananNotifBadge');
        const btn          = document.getElementById('pesananNotifBtn');
        const sidebarBadge = document.getElementById('sidebarPesananBadge');
        const pending      = data.pending || 0;
        const total        = pending + (data.selesai || 0);

        if (badge) {
          if (total > 0) {
            badge.textContent   = total;
            badge.style.display = 'flex';
            if (btn) btn.title  = total + ' pesanan perlu dikonfirmasi';
          } else {
            badge.style.display = 'none';
            if (btn) btn.title  = 'Pesanan Masuk';
          }
        }
        if (sidebarBadge) {
          if (pending > 0) {
            sidebarBadge.textContent   = pending;
            sidebarBadge.style.display = 'flex';
          } else {
            sidebarBadge.style.display = 'none';
          }
        }
      })
      .catch(() => {});
  }
  loadNotifPesanan();
  setInterval(loadNotifPesanan, 5000);
  </script>

  <!-- ═══ TABEL REKAP PEMBELIAN ═══ -->
  <script>
  (function () {
    const ROWS_PER_PAGE = 10;
    let currentPage = 1;
    let currentData = [];

    function formatRupiah(n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); }
    function formatTanggal(dt) {
      if (!dt) return '-';
      return new Date(dt.replace(' ','T')).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
    }
    function formatWaktu(dt) { return dt ? dt.substring(11, 16) : '-'; }
    function badgeStatus(status) {
      const map = {
        'selesai':'background:#d4edda;color:#155724','lunas':'background:#d4edda;color:#155724',
        'diambil':'background:#d4edda;color:#155724','completed':'background:#d4edda;color:#155724',
        'dimasak':'background:#fff3cd;color:#856404','siap':'background:#cce5ff;color:#004085',
        'pending':'background:#fff3cd;color:#856404','batal':'background:#f8d7da;color:#721c24',
        'ditolak':'background:#f8d7da;color:#721c24',
      };
      const style = map[status?.toLowerCase()] || 'background:#e2e3e5;color:#383d41';
      return `<span style="padding:2px 8px;border-radius:20px;font-size:.75rem;font-weight:600;${style}">${status ?? '-'}</span>`;
    }

    function renderTable(data, page) {
      currentData = data;
      currentPage = page || 1;
      const tbody = document.getElementById('mainTableBody');
      const wrap  = document.getElementById('paginationWrap');
      if (!tbody) return;

      const totalPages = Math.ceil(data.length / ROWS_PER_PAGE);
      const start = (currentPage - 1) * ROWS_PER_PAGE;
      const slice = data.slice(start, start + ROWS_PER_PAGE);

      if (!slice.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#aaa;padding:2rem;">Tidak ada data.</td></tr>';
        if (wrap) wrap.innerHTML = '';
        return;
      }

      tbody.innerHTML = slice.map((t, i) => `
        <tr onclick="showStruk(${t.id})" style="cursor:pointer">
          <td>${start + i + 1}</td>
          <td>${t.produk ?? '-'}</td>
          <td><span style="font-size:.75rem;color:#aaa">#${t.id}</span></td>
          <td>${formatTanggal(t.tanggal)}<br><small style="color:#999">${formatWaktu(t.tanggal)}</small></td>
          <td><strong style="color:#c84b11">${formatRupiah(t.total)}</strong><br><small style="color:#999">${t.jumlah}x · ${t.metode ?? '-'}</small></td>
          <td>${t.pembeli ?? 'Anonim'}</td>
          <td>${badgeStatus(t.status)}</td>
        </tr>`).join('');

      if (wrap) {
        if (totalPages <= 1) { wrap.innerHTML = ''; return; }
        let html = `<button class="page-btn nav-btn" onclick="goPage(${currentPage-1})" ${currentPage===1?'disabled':''}>&lsaquo; Prev</button>`;
        for (let p = 1; p <= totalPages; p++) {
          html += `<button class="page-btn${p===currentPage?' active':''}" onclick="goPage(${p})">${p}</button>`;
        }
        html += `<button class="page-btn nav-btn" onclick="goPage(${currentPage+1})" ${currentPage===totalPages?'disabled':''}>Next &rsaquo;</button>`;
        wrap.innerHTML = html;
      }
    }

    window._renderTable = renderTable;
    window.goPage = function(p) {
      const totalPages = Math.ceil(currentData.length / ROWS_PER_PAGE);
      if (p < 1 || p > totalPages) return;
      renderTable(currentData, p);
    };
    window.refreshTable = function() { location.reload(); };
    renderTable(window.rekapData || [], 1);
  })();
  </script>

  <style>
  @keyframes dotpulse {
    0%, 80%, 100% { transform: scale(0.6); opacity:.4; }
    40%           { transform: scale(1);   opacity:1;  }
  }
  </style>
</body>
</html>