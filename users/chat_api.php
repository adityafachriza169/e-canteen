<?php
// php/chat_api.php
// Endpoint tunggal untuk semua operasi chat
// Dipanggil via AJAX dari halaman chat

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/koneksi.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Helper ──────────────────────────────────────────────────
function resp($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function cek_login(): array {
    // Dukung session siswa (id_user) maupun penjual (id_penjual)
    if (!empty($_SESSION['id_user'])) {
        return ['role' => 'user',    'id' => (int)$_SESSION['id_user']];
    }
    if (!empty($_SESSION['id_penjual'])) {
        return ['role' => 'penjual', 'id' => (int)$_SESSION['id_penjual']];
    }
    resp(['error' => 'Belum login'], 401);
}

// ── Router ───────────────────────────────────────────────────
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_or_create_room': aksi_get_or_create_room(); break;
    case 'get_pesan':          aksi_get_pesan();           break;
    case 'kirim_pesan':        aksi_kirim_pesan();         break;
    case 'tandai_dibaca':      aksi_tandai_dibaca();       break;
    case 'daftar_room':        aksi_daftar_room();         break;
    case 'jumlah_belum_baca':  aksi_jumlah_belum_baca();  break;
    default:                   resp(['error' => 'Action tidak dikenal'], 400);
}

// ── Aksi ─────────────────────────────────────────────────────

/**
 * Siswa membuka chat dengan penjual tertentu.
 * Kalau room belum ada, otomatis dibuat.
 */
function aksi_get_or_create_room(): void {
    global $konek;
    $sesi = cek_login();

    if ($sesi['role'] !== 'user') {
        resp(['error' => 'Hanya siswa yang bisa membuka room baru'], 403);
    }

    $id_user    = $sesi['id'];
    $id_penjual = (int)($_POST['id_penjual'] ?? 0);

    if ($id_penjual <= 0) resp(['error' => 'id_penjual tidak valid'], 400);

    // Ambil id_kantin dari penjual
    $st = $konek->prepare("SELECT id_kantin FROM penjual WHERE id_penjual = ? LIMIT 1");
    $st->bind_param('i', $id_penjual);
    $st->execute();
    $penjual = $st->get_result()->fetch_assoc();
    if (!$penjual) resp(['error' => 'Penjual tidak ditemukan'], 404);

    $id_kantin = $penjual['id_kantin'];

    // Cari room yang sudah ada
    $st2 = $konek->prepare(
        "SELECT id_room FROM chat_room WHERE id_user = ? AND id_penjual = ? LIMIT 1"
    );
    $st2->bind_param('ii', $id_user, $id_penjual);
    $st2->execute();
    $room = $st2->get_result()->fetch_assoc();

    if ($room) {
        resp(['id_room' => (int)$room['id_room']]);
    }

    // Buat room baru
    $st3 = $konek->prepare(
        "INSERT INTO chat_room (id_user, id_penjual, id_kantin) VALUES (?, ?, ?)"
    );
    $st3->bind_param('iii', $id_user, $id_penjual, $id_kantin);
    $st3->execute();

    resp(['id_room' => (int)$konek->insert_id]);
}

/**
 * Ambil pesan dalam satu room.
 * Opsional: hanya pesan setelah id_pesan tertentu (polling ringan).
 */
function aksi_get_pesan(): void {
    global $konek;
    $sesi    = cek_login();
    $id_room = (int)($_GET['id_room'] ?? 0);
    $setelah = (int)($_GET['setelah'] ?? 0); // id_pesan terakhir yang sudah diterima

    if ($id_room <= 0) resp(['error' => 'id_room tidak valid'], 400);

    // Pastikan user/penjual punya akses ke room ini
    cek_akses_room($konek, $id_room, $sesi);

    $sql = "
        SELECT p.id_pesan, p.pengirim_id, p.pengirim_role, p.pesan,
               p.is_read, p.created_at,
               CASE
                 WHEN p.pengirim_role = 'user'    THEN u.nama
                 WHEN p.pengirim_role = 'penjual' THEN pj.nama
               END AS nama_pengirim
        FROM chat_pesan p
        LEFT JOIN users   u  ON p.pengirim_role = 'user'    AND p.pengirim_id = u.id_user
        LEFT JOIN penjual pj ON p.pengirim_role = 'penjual' AND p.pengirim_id = pj.id_penjual
        WHERE p.id_room = ?
    ";
    $params = [$id_room];
    $types  = 'i';

    if ($setelah > 0) {
        $sql    .= " AND p.id_pesan > ?";
        $params[] = $setelah;
        $types   .= 'i';
    }

    $sql .= " ORDER BY p.id_pesan ASC LIMIT 100";

    $st = $konek->prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);

    resp(['pesan' => $rows]);
}

/**
 * Kirim pesan baru.
 */
function aksi_kirim_pesan(): void {
    global $konek;
    $sesi    = cek_login();
    $id_room = (int)($_POST['id_room'] ?? 0);
    $pesan   = trim($_POST['pesan'] ?? '');

    if ($id_room <= 0) resp(['error' => 'id_room tidak valid'], 400);
    if ($pesan === '')  resp(['error' => 'Pesan tidak boleh kosong'], 400);
    if (mb_strlen($pesan) > 2000) resp(['error' => 'Pesan terlalu panjang'], 400);

    cek_akses_room($konek, $id_room, $sesi);

    $st = $konek->prepare(
        "INSERT INTO chat_pesan (id_room, pengirim_id, pengirim_role, pesan)
         VALUES (?, ?, ?, ?)"
    );
    $st->bind_param('iiss', $id_room, $sesi['id'], $sesi['role'], $pesan);
    $st->execute();

    $id_pesan_baru = (int)$konek->insert_id;

    // Update updated_at di room agar daftar room terurut benar
    $konek->query("UPDATE chat_room SET updated_at = NOW() WHERE id_room = $id_room");

    // Auto-reply dari penjual hanya jika pengirimnya user (siswa)
    // dan belum pernah ada pesan dari penjual di room ini
    if ($sesi['role'] === 'user') {
        // Ambil id_penjual dari room ini
        $st_room = $konek->prepare(
            "SELECT id_penjual FROM chat_room WHERE id_room = ? LIMIT 1"
        );
        $st_room->bind_param('i', $id_room);
        $st_room->execute();
        $room_data = $st_room->get_result()->fetch_assoc();

        if ($room_data) {
            $id_penjual_room = (int)$room_data['id_penjual'];

            // Cek apakah penjual sudah pernah balas di room ini
            $st_cek = $konek->prepare(
                "SELECT COUNT(*) AS jml FROM chat_pesan
                 WHERE id_room = ? AND pengirim_role = 'penjual' LIMIT 1"
            );
            $st_cek->bind_param('i', $id_room);
            $st_cek->execute();
            $cek = $st_cek->get_result()->fetch_assoc();

            // Hanya kirim auto-reply jika penjual belum pernah membalas
            if ((int)$cek['jml'] === 0) {
                $pesan_auto = 'Terima kasih sudah menghubungi kami, penjual akan segera membalas pesan Anda 😊';
                $st_auto = $konek->prepare(
                    "INSERT INTO chat_pesan (id_room, pengirim_id, pengirim_role, pesan)
                     VALUES (?, ?, 'penjual', ?)"
                );
                $st_auto->bind_param('iis', $id_room, $id_penjual_room, $pesan_auto);
                $st_auto->execute();
                $konek->query("UPDATE chat_room SET updated_at = NOW() WHERE id_room = $id_room");
            }
        }
    }

    resp(['id_pesan' => $id_pesan_baru]);
}

/**
 * Tandai semua pesan di room sebagai sudah dibaca oleh pihak yang login.
 */
function aksi_tandai_dibaca(): void {
    global $konek;
    $sesi    = cek_login();
    $id_room = (int)($_POST['id_room'] ?? 0);

    if ($id_room <= 0) resp(['error' => 'id_room tidak valid'], 400);
    cek_akses_room($konek, $id_room, $sesi);

    // Tandai pesan yang BUKAN dari saya sebagai dibaca
    $role_lawan = $sesi['role'] === 'user' ? 'penjual' : 'user';
    $st = $konek->prepare(
        "UPDATE chat_pesan SET is_read = 1
         WHERE id_room = ? AND pengirim_role = ? AND is_read = 0"
    );
    $st->bind_param('is', $id_room, $role_lawan);
    $st->execute();

    resp(['ok' => true]);
}

/**
 * Daftar room beserta pesan terakhir.
 * Untuk siswa: semua room miliknya.
 * Untuk penjual: semua room yang melibatkannya.
 */
function aksi_daftar_room(): void {
    global $konek;
    $sesi = cek_login();

    if ($sesi['role'] === 'user') {
        $kolom_filter = 'r.id_user';
    } else {
        $kolom_filter = 'r.id_penjual';
    }

    $sql = "
        SELECT r.id_room, r.id_user, r.id_penjual, r.id_kantin,
               u.nama  AS nama_user,
               u.pp    AS foto_user,
               pj.nama AS nama_penjual,
               pj.profil AS foto_penjual,
               k.nama_kantin,
               last_p.pesan        AS pesan_terakhir,
               last_p.created_at   AS waktu_terakhir,
               last_p.pengirim_role AS pengirim_terakhir,
               (
                 SELECT COUNT(*) FROM chat_pesan cp
                 WHERE cp.id_room = r.id_room
                   AND cp.is_read = 0
                   AND cp.pengirim_role != ?
               ) AS belum_baca
        FROM chat_room r
        JOIN users   u  ON r.id_user    = u.id_user
        JOIN penjual pj ON r.id_penjual = pj.id_penjual
        JOIN kantin  k  ON r.id_kantin  = k.id_kantin
        LEFT JOIN chat_pesan last_p ON last_p.id_pesan = (
            SELECT MAX(id_pesan) FROM chat_pesan WHERE id_room = r.id_room
        )
        WHERE $kolom_filter = ?
        ORDER BY r.updated_at DESC
    ";

    $st = $konek->prepare($sql);
    $st->bind_param('si', $sesi['role'], $sesi['id']);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);

    resp(['rooms' => $rows]);
}

/**
 * Jumlah total pesan belum dibaca (untuk badge notifikasi di navbar).
 */
function aksi_jumlah_belum_baca(): void {
    global $konek;
    $sesi = cek_login();

    if ($sesi['role'] === 'user') {
        $kolom_room = 'r.id_user';
    } else {
        $kolom_room = 'r.id_penjual';
    }

    $role_lawan = $sesi['role'] === 'user' ? 'penjual' : 'user';

    $sql = "
        SELECT COUNT(*) AS total
        FROM chat_pesan cp
        JOIN chat_room r ON cp.id_room = r.id_room
        WHERE $kolom_room = ?
          AND cp.pengirim_role = ?
          AND cp.is_read = 0
    ";

    $st = $konek->prepare($sql);
    $st->bind_param('is', $sesi['id'], $role_lawan);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();

    resp(['belum_baca' => (int)$row['total']]);
}

// ── Util ──────────────────────────────────────────────────────

function cek_akses_room($konek, int $id_room, array $sesi): void {
    if ($sesi['role'] === 'user') {
        $kolom = 'id_user';
    } else {
        $kolom = 'id_penjual';
    }

    $st = $konek->prepare(
        "SELECT id_room FROM chat_room WHERE id_room = ? AND $kolom = ? LIMIT 1"
    );
    $st->bind_param('ii', $id_room, $sesi['id']);
    $st->execute();

    if (!$st->get_result()->fetch_assoc()) {
        resp(['error' => 'Akses ditolak ke room ini'], 403);
    }
}