<?php
session_start();
include 'database.php';

// Hapus token dari database
if (isset($_SESSION['username'])) {
    $stmt = mysqli_prepare($db, "UPDATE akun_admin SET ingat = NULL WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $_SESSION['username']);
    mysqli_stmt_execute($stmt);
}

// Hapus cookie
setcookie('admin_remember', '', time() - 3600, '/', '', false, true);

// Hapus session
session_destroy();

header("Location: index.php");
exit;
?>