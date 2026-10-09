<?php
ini_set('session.cookie_samesite', 'None');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_httponly', '1');
session_start();
include "koneksi.php";

$tipe = $_POST['tipe'] ?? '';

/* ===============================
   LOGIN
================================ */
if ($tipe === 'login') {

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $ingat    = $_POST['ingat'] ?? '0';

    if (!$username || !$password) {
        echo json_encode(['status' => 'data_kosong']);
        exit;
    }

    /* ===========================
       CEK TABEL USERS (role = 0)
    ============================ */
    $stmt = mysqli_prepare($konek, "SELECT * FROM users WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user   = mysqli_fetch_assoc($result);

    if ($user) {

        if (!password_verify($password, $user['password'])) {
            echo json_encode(['status' => 'password_salah']);
            exit;
        }

        if ($user['status'] == 0) {
            echo json_encode(['status' => 'pending_approval']);
            exit;
        }

        if ($user['status'] == 2) {
            echo json_encode(['status' => 'akun_ditolak']);
            exit;
        }

        if ($user['status'] == 3) {
            echo json_encode(['status' => 'akun_nonaktif']);
            exit;
        }

        // Set session
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role'];

        // ✅ Simpan cookie → ambil/simpan token di tabel USERS
        if ($ingat == '1') {
            $token  = bin2hex(random_bytes(32));
            $expire = time() + (30 * 24 * 60 * 60); // 30 hari

            $upd = mysqli_prepare($konek, "UPDATE users SET ingat = ? WHERE username = ?");
            mysqli_stmt_bind_param($upd, "ss", $token, $user['username']);
            mysqli_stmt_execute($upd);

            setcookie("ingat_user", $token, $expire, "/", "", true, true);
setcookie("ingat_role", "user", $expire, "/", "", true, true);
        }

        echo json_encode(['status' => 'sukses', 'role' => 'user']);
        exit;
    }

    /* ==============================
       CEK TABEL PENJUAL (role = 1)
    =============================== */
    $stmt2   = mysqli_prepare($konek, "SELECT * FROM penjual WHERE username = ?");
    mysqli_stmt_bind_param($stmt2, "s", $username);
    mysqli_stmt_execute($stmt2);
    $result2 = mysqli_stmt_get_result($stmt2);
    $penjual = mysqli_fetch_assoc($result2);

    if ($penjual) {

        if (!password_verify($password, $penjual['password'])) {
            echo json_encode(['status' => 'password_salah']);
            exit;
        }

        // Set session
        $_SESSION['username']  = $penjual['username'];
        $_SESSION['role']      = $penjual['role'];
        $_SESSION['id_kantin'] = $penjual['canteen'];

        // ✅ Simpan cookie → ambil/simpan token di tabel PENJUAL
        if ($ingat == '1') {
            $token  = bin2hex(random_bytes(32));
            $expire = time() + (30 * 24 * 60 * 60); // 30 hari

            $upd = mysqli_prepare($konek, "UPDATE penjual SET ingat = ? WHERE username = ?");
            mysqli_stmt_bind_param($upd, "ss", $token, $penjual['username']);
            mysqli_stmt_execute($upd);

            setcookie("ingat_user", $token, $expire, "/", "", false, true);
            setcookie("ingat_role", "penjual", $expire, "/", "", false, true); // ✅ tandai role
        }

        echo json_encode([
            'status'    => 'sukses',
            'role'      => 'admin',
            'id_kantin' => $penjual['canteen']
        ]);
        exit;
    }

    // Username tidak ditemukan di kedua tabel
    echo json_encode(['status' => 'username_salah']);
    exit;
}

/* ===============================
   REGISTER
================================ */
if ($tipe === 'register') {

    $nama     = trim($_POST['nama']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $kelas    = trim($_POST['kelas']    ?? '');

    if (!$nama || !$email || !$username || !$password || !$kelas) {
        echo json_encode(['status' => 'data_kosong']);
        exit;
    }

    // Validasi email harus Gmail
    if (!preg_match('/@gmail\.com$/i', $email)) {
        echo json_encode(['status' => 'email_tidak_valid']);
        exit;
    }

    // Cek username sudah ada
    $cek_user = mysqli_prepare($konek, "SELECT id FROM users WHERE username = ?");
    mysqli_stmt_bind_param($cek_user, "s", $username);
    mysqli_stmt_execute($cek_user);
    mysqli_stmt_store_result($cek_user);
    if (mysqli_stmt_num_rows($cek_user) > 0) {
        echo json_encode(['status' => 'username_ada']);
        exit;
    }

    // Cek email sudah ada
    $cek_email = mysqli_prepare($konek, "SELECT id FROM users WHERE email = ?");
    mysqli_stmt_bind_param($cek_email, "s", $email);
    mysqli_stmt_execute($cek_email);
    mysqli_stmt_store_result($cek_email);
    if (mysqli_stmt_num_rows($cek_email) > 0) {
        echo json_encode(['status' => 'email_ada']);
        exit;
    }

    // Hash password
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert ke database (status=0 → pending approval)
    $ins = mysqli_prepare($konek, "INSERT INTO users (nama, email, username, password, kelas, status, role) VALUES (?, ?, ?, ?, ?, 0, 0)");
    mysqli_stmt_bind_param($ins, "sssss", $nama, $email, $username, $hash, $kelas);

    if (mysqli_stmt_execute($ins)) {
        echo json_encode(['status' => 'register_sukses']);
    } else {
        echo json_encode(['status' => 'gagal']);
    }
    exit;
}

echo json_encode(['status' => 'tipe_tidak_valid']);
exit;
?>