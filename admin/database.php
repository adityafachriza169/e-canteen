<?php
$db = mysqli_connect("localhost", "root", "", "db_canteen");

if (!$db) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>