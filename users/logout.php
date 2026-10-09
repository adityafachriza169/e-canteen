<?php
session_start();
include "koneksi.php";

if (isset($_SESSION['username'], $_COOKIE['ingat_role'])) {

    $username = $_SESSION['username'];
    $role     = $_COOKIE['ingat_role'];

    if ($role === 'user') {
        // ✅ Hapus token di tabel USERS
        $stmt = mysqli_prepare($konek, "UPDATE users SET ingat = NULL WHERE username = ?");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);

    } elseif ($role === 'penjual') {
        // ✅ Hapus token di tabel PENJUAL
        $stmt = mysqli_prepare($konek, "UPDATE penjual SET ingat = NULL WHERE username = ?");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
    }
}

// Hapus kedua cookie
setcookie("ingat_user", "", time() - 3600, "/", "", false, true);
setcookie("ingat_role", "", time() - 3600, "/", "", false, true);

// Hapus session
session_unset();
session_destroy();

header("Location: login.php");
exit;
?>