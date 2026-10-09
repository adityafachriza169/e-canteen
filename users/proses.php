<?php
ob_start();
ini_set('session.cookie_samesite', 'None');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_httponly', '1');
session_start();
include "koneksi.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tipe'])) {

    if ($_POST['tipe'] === "login") {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $ingat    = $_POST['ingat'] ?? '0';

        if (!$username) {
            echo json_encode(["status" => "username_salah"]);
            exit;
        }

        // ── CEK PENJUAL ──
        $stmt = mysqli_prepare($konek, "SELECT * FROM penjual WHERE username=?");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $penjual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($penjual) {
            if (md5($password) !== $penjual['password']) {
                echo json_encode(["status" => "password_salah"]);
                exit;
            }

            // ── CEK STATUS PENJUAL ──
            if (intval($penjual['status']) === 0) {
                echo json_encode(["status" => "akun_nonaktif"]);
                exit;
            }

            $_SESSION['username']  = $penjual['username'];
            $_SESSION['role']      = 1;
            $_SESSION['id_kantin'] = $penjual['id_kantin'];

            // Simpan cookie ingat penjual
            if ($ingat == '1') {
                $token  = bin2hex(random_bytes(32));
                $expire = time() + (30 * 24 * 60 * 60);

                $upd = mysqli_prepare($konek, "UPDATE penjual SET ingat = ? WHERE username = ?");
                mysqli_stmt_bind_param($upd, "ss", $token, $penjual['username']);
                mysqli_stmt_execute($upd);

                $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                setcookie("ingat_user", $token, $expire, "/", "", $secure, true);
                setcookie("ingat_role", "penjual", $expire, "/", "", $secure, true);
            }

            echo json_encode([
                "status"    => "sukses",
                "role"      => "admin",
                "id_kantin" => $penjual['id_kantin'] ?? null
            ]);
            exit;
        }

        // ── CEK USER ──
        $stmt2 = mysqli_prepare($konek, "SELECT * FROM users WHERE username=?");
        mysqli_stmt_bind_param($stmt2, "s", $username);
        mysqli_stmt_execute($stmt2);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));

        if ($user) {
            if (md5($password) !== $user['password']) {
                echo json_encode(["status" => "password_salah"]);
                exit;
            }

            $status = ($user['status'] === null) ? 2 : (int) $user['status'];

            if ($status === 2) {
                echo json_encode(["status" => "pending_approval"]);
                exit;
            }
            if ($status === 0) {
                echo json_encode(["status" => "akun_ditolak"]);
                exit;
            }
            if ($status === 3) {
                echo json_encode(["status" => "akun_nonaktif"]);
                exit;
            }

            // ✅ role dari DB (role = 2 untuk user biasa)
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = (int)$user['role'];
            $_SESSION['id_user']  = $user['id_user'];

            // Simpan cookie ingat user
            if ($ingat == '1') {
                $token  = bin2hex(random_bytes(32));
                $expire = time() + (30 * 24 * 60 * 60);

                $upd = mysqli_prepare($konek, "UPDATE users SET ingat = ? WHERE username = ?");
                mysqli_stmt_bind_param($upd, "ss", $token, $user['username']);
                mysqli_stmt_execute($upd);

                $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                setcookie("ingat_user", $token, $expire, "/", "", $secure, true);
                setcookie("ingat_role", "user", $expire, "/", "", $secure, true);
            }

            echo json_encode(["status" => "sukses", "role" => "user"]);
            exit;
        }

        echo json_encode(["status" => "username_salah"]);
        exit;
    }

    if ($_POST['tipe'] === "register") {
        $nama           = trim($_POST['nama']           ?? '');
        $email          = trim($_POST['email']          ?? '');
        $username       = trim($_POST['username']       ?? '');
        $password       = trim($_POST['password']       ?? '');
        $kelas          = trim($_POST['kelas']          ?? '');
        $no_hp          = trim($_POST['no_hp']          ?? '');
        $gender  = trim($_POST['gender']  ?? '');
        $tanggal_lahir      = trim($_POST['tanggal_lahir']      ?? '');

        if (!$nama || !$email || !$username || !$password || !$kelas || !$no_hp || !$gender || !$tanggal_lahir) {
            echo json_encode(["status" => "data_kosong"]);
            exit;
        }

        if (!preg_match('/^[a-zA-Z0-9._%+-]+@gmail\.com$/', $email)) {
            echo json_encode(["status" => "email_tidak_valid"]);
            exit;
        }

        // Validasi no HP
        if (!preg_match('/^08[0-9]{8,12}$/', $no_hp)) {
            echo json_encode(["status" => "no_hp_tidak_valid"]);
            exit;
        }

        // Validasi jenis kelamin
        if (!in_array($gender, ['Laki-laki', 'Perempuan'])) {
            echo json_encode(["status" => "jenis_kelamin_tidak_valid"]);
            exit;
        }

        // Validasi umur minimal 15 tahun
        $birth = new DateTime($tanggal_lahir);
        $now   = new DateTime();
        $age   = (int) $now->diff($birth)->y;
        if ($age < 15) {
            echo json_encode(["status" => "umur_kurang", "umur" => $age]);
            exit;
        }

        $password_md5 = md5($password);

        $cek = mysqli_prepare($konek, "SELECT id_user FROM users WHERE username=?");
        mysqli_stmt_bind_param($cek, "s", $username);
        mysqli_stmt_execute($cek);
        mysqli_stmt_store_result($cek);
        if (mysqli_stmt_num_rows($cek) > 0) {
            echo json_encode(["status" => "username_ada"]);
            exit;
        }

        $cek2 = mysqli_prepare($konek, "SELECT id_user FROM users WHERE email=?");
        mysqli_stmt_bind_param($cek2, "s", $email);
        mysqli_stmt_execute($cek2);
        mysqli_stmt_store_result($cek2);
        if (mysqli_stmt_num_rows($cek2) > 0) {
            echo json_encode(["status" => "email_ada"]);
            exit;
        }

        // status=2 → pending approval
        $stmt = mysqli_prepare($konek,
            "INSERT INTO users (username, nama, email, password, role, kelas, no_hp, gender, tanggal_lahir, status)
             VALUES (?, ?, ?, ?, 2, ?, ?, ?, ?, 2)"
        );
        mysqli_stmt_bind_param($stmt, "ssssssss", $username, $nama, $email, $password_md5, $kelas, $no_hp, $gender, $tanggal_lahir);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["status" => "register_sukses"]);
        } else {
            echo json_encode(["status" => "register_gagal", "msg" => mysqli_stmt_error($stmt)]);
        }
        exit;
    }
}

echo json_encode(["status" => "invalid_request"]);