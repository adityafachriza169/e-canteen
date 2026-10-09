<?php
/**
 * simpan_sesi_co.php
 * Menyimpan data pesanan QRIS ke session.
 * TIDAK menyentuh database / stok sama sekali.
 * Dipanggil dari co.php sebelum redirect ke bayar_qris.php.
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_user']) || (int)($_SESSION['role'] ?? -1) !== 2) {
    echo json_encode(['status' => 'error', 'msg' => 'Unauthorized']);
    exit;
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!$body || empty($body['items'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Data tidak valid']);
    exit;
}

// Simpan ke session — belum ada transaksi di DB, stok belum berkurang
$_SESSION['pending_qris'] = [
    'items'    => $body['items'],
    'total'    => (float)($body['total']    ?? 0),
    'shipping' => (float)($body['shipping'] ?? 0),
    'catatan'  => $body['catatan'] ?? '',
    'created'  => time(),
    'done'     => [],   // kantin yang sudah upload bukti
];

echo json_encode(['status' => 'ok']);