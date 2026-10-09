<?php
// auth_check.php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once 'database.php';

// Cek session dulu
if (isset($_SESSION['is_login']) && $_SESSION['is_login'] === true) {
    // Sudah login, lanjut
}
// Kalau belum, cek cookie
elseif (isset($_COOKIE['admin_remember'])) {
    $token = $_COOKIE['admin_remember'];
    $stmt = mysqli_prepare($db, "SELECT * FROM akun_admin WHERE ingat = ?");
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);

    if ($data) {
        $_SESSION['username'] = $data['username'];
        $_SESSION['is_login'] = true;
        // Lanjut
    } else {
        // Token tidak valid, hapus cookie
        setcookie('admin_remember', '', time() - 3600, '/', '', false, true);
        header("Location: login_admin.php");
        exit;
    }
} else {
    header("Location: index.php");
    exit;
}
?>