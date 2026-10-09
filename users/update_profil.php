<?php
session_start();
require 'koneksi.php';


header('Content-Type: application/json');

if (!isset($_SESSION['id_user'])) {

    echo json_encode([
        'status' => 'error',
        'msg' => 'Belum login'
    ]);

    exit;
}

$id_user = $_SESSION['id_user'];

$username = mysqli_real_escape_string($konek, $_POST['username']);
$nama     = mysqli_real_escape_string($konek, $_POST['nama']);
$email    = mysqli_real_escape_string($konek, $_POST['email']);
$nohp     = mysqli_real_escape_string($konek, $_POST['nohp']);
$gender   = mysqli_real_escape_string($konek, $_POST['gender']);
$tgl      = mysqli_real_escape_string($konek, $_POST['tgl']);

/* ── Validasi umur minimal 15 tahun ── */
if (!empty($tgl)) {
    $lahir   = new DateTime($tgl);
    $sekarang = new DateTime();
    $umur    = $sekarang->diff($lahir)->y;

    if ($umur < 15) {
        echo json_encode([
            'status' => 'error',
            'msg'    => 'Tanggal lahir tidak valid. Usia minimal 15 tahun.'
        ]);
        exit;
    }
}

$query = mysqli_query($konek, "

UPDATE users SET

username = '$username',
nama = '$nama',
email = '$email',
no_hp = '$nohp',
gender = '$gender',
tanggal_lahir = '$tgl'

WHERE id_user = '$id_user'

");

if ($query) {

    echo json_encode([
        'status' => 'ok'
    ]);

} else {

    echo json_encode([
        'status' => 'error',
        'msg' => mysqli_error($konek)
    ]);
}
?>