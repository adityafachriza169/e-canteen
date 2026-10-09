<?php
session_start();
include "koneksi.php";

/* ===============================
   JIKA SUDAH LOGIN → REDIRECT
================================ */
if (isset($_SESSION['username'])) {
    if ($_SESSION['role'] == 1 && isset($_SESSION['id_kantin'])) {
        header("Location: ../penjual/dasbord.php");
    } else {
        header("Location: user.php");
    }
    exit;
}

/* ===============================
   AUTO LOGIN DARI COOKIE
================================ */
if (isset($_COOKIE['ingat_user']) && isset($_COOKIE['ingat_role'])) {

    $token = $_COOKIE['ingat_user'];
    $role  = $_COOKIE['ingat_role'];

    if ($role === 'user') {

        /* ===== TOKEN DARI TABEL USERS ===== */
        $stmt = mysqli_prepare($konek, "SELECT username, role FROM users WHERE ingat = ? AND status = 1");
        mysqli_stmt_bind_param($stmt, "s", $token);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);

        if ($user) {
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            $last = $_COOKIE['last_page'] ?? '';
    $redirect = !empty($last) ? $last : 'user.php';
    header("Location: " . $redirect);
    exit;
        }

    } elseif ($role === 'penjual') {

        /* ===== TOKEN DARI TABEL PENJUAL ===== */
        $stmt2   = mysqli_prepare($konek, "SELECT username, role, id_kantin FROM penjual WHERE ingat = ?");
        mysqli_stmt_bind_param($stmt2, "s", $token);
        mysqli_stmt_execute($stmt2);
        $result2 = mysqli_stmt_get_result($stmt2);
        $penjual = mysqli_fetch_assoc($result2);

        if ($penjual) {
            $_SESSION['username']  = $penjual['username'];
            $_SESSION['role']      = $penjual['role'];
            $_SESSION['id_kantin'] = $penjual['id_kantin'];
            header("Location: ../penjual/dasbord.php");
            exit;
        }
    }

    // Token tidak valid → hapus kedua cookie
    setcookie("ingat_user", "", time() - 3600, "/", "", false, true);
    setcookie("ingat_role", "", time() - 3600, "/", "", false, true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="https://cdn.lineicons.com/4.0/lineicons.css">
    <link rel="stylesheet" href="font-icons/all.min.css">
    <link rel="stylesheet" href="font-icons/fontawesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="loginn.css">

    <!-- Terapkan tema SEBELUM render untuk hindari flash -->
    <script>
        (function() {
            var saved = localStorage.getItem('theme');
            if (saved === 'light') document.documentElement.classList.add('light-pre');
        })();
    </script>
    <style>
        html.light-pre body { background-color: #f0f2ff; }

        .input-box select {
            width: 100%;
            padding: 10px 40px 10px 15px;
            border-radius: 8px;
            border: none;
            background: transparent;
            color: inherit;
            font-size: 14px;
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
            outline: none;
            font-family: inherit;
        }

        .input-box select option {
            background: #1a1a2e;
            color: #fff;
        }

        body.light-mode .input-box select option {
            background: #f0f2ff;
            color: #333;
        }

        .input-box select optgroup {
            font-weight: 700;
            font-size: 12px;
        }

        .input-box .select-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            font-size: 16px;
        }

        /* ==============================
           STYLING CHECKBOX INGAT SAYA
        =============================== */
        .remember-box {
            width: 100%;
            display: flex;
            align-items: center;
            margin: -5px 0 12px 2px;
            font-size: 13px;
        }

        .remember-box label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            user-select: none;
            opacity: 0.85;
        }

        .remember-box label:hover {
            opacity: 1;
        }

        .remember-box input[type="checkbox"] {
            width: 15px;
            height: 15px;
            cursor: pointer;
            accent-color: #6c63ff;
            flex-shrink: 0;
        }
    </style>
</head>
<body>

    <!-- ══════════════════════════════════
         TOMBOL DARK / LIGHT MODE
    ═══════════════════════════════════ -->
    <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode"></button>
    <i class="theme-toggle-icon bi bi-moon-stars-fill" id="themeIcon" aria-hidden="true"></i>

    <div class="container" id="container">

        <!-- ══════════════════════════════════
             FORM LOGIN
        ═══════════════════════════════════ -->
        <div class="form-box login">
            <form id="loginForm">
                <input type="hidden" name="tipe" value="login">

                <h1 class="puasa">Login</h1>

                <div class="input-box">
                    <input type="text" name="username" placeholder="Username" required>
                    <i class="bi bi-person-fill"></i>
                    <small align="left" class="error-text"></small>
                </div>

                <div class="input-box">
                    <input type="password" name="password" placeholder="Password" required>
                    <i class="bi bi-lock-fill"></i>
                    <small align="left" class="error-text"></small>
                </div>

                <!-- ✅ CHECKBOX INGAT SAYA -->
                <!-- Jika dicentang → cookie aktif 30 hari -->
                <!-- Jika tidak dicentang → tidak ada cookie -->
                <div class="remember-box">
                    <label>
                        <input type="checkbox" name="ingat" value="1">
                        Ingat Saya
                    </label>
                </div>

                <button type="submit" class="btn btn-electric">Login</button>
                <p>or login with social platform</p>
                <div class="social-icon">
                    <a href="#"><i class="lni lni-facebook-fill"></i></a>
                    <a href="#"><i class="lni lni-google"></i></a>
                    <a href="#"><i class="lni lni-linkedin-original"></i></a>
                </div>
            </form>
        </div>

        <!-- ══════════════════════════════════
             FORM REGISTER
        ═══════════════════════════════════ -->
        <div class="form-box regist">
            <form id="registerForm">
                <input type="hidden" name="tipe" value="register">

                <h1 class="puasa">Registrasi</h1>

                <div class="input-box">
                    <input type="text" name="nama" placeholder="Nama" required>
                    <i class="bi bi-person-fill"></i>
                </div>

                <div class="input-box">
                    <input type="email" name="email" placeholder="Email" required>
                    <i class="bi bi-envelope-fill"></i>
                    <small class="error-text"></small>
                </div>

                <div class="input-box">
                    <input type="text" name="username" placeholder="Username" required>
                    <i class="bi bi-person-fill"></i>
                    <small class="error-text"></small>
                </div>

                <div class="input-box">
                    <input type="password" name="password" placeholder="Password" required>
                    <i class="bi bi-lock-fill"></i>
                </div>

                <!-- DROPDOWN KELAS -->
                <div class="input-box">
                    <select name="kelas" required>
                        <option value="" disabled selected>-- Pilih Kelas --</option>

                        <optgroup label="── Kelas 10 ──">
                            <option value="10 TKI 1">10 TKI 1</option>
                            <option value="10 TKI 2">10 TKI 2</option>
                            <option value="10 RPL 1">10 RPL 1</option>
                            <option value="10 RPL 2">10 RPL 2</option>
                            <option value="10 ULW">10 ULW</option>
                            <option value="10 MP 1">10 MP 1</option>
                            <option value="10 MP 2">10 MP 2</option>
                            <option value="10 MP 3">10 MP 3</option>
                            <option value="10 MP 4">10 MP 4</option>
                            <option value="10 AK 1">10 AK 1</option>
                            <option value="10 AK 2">10 AK 2</option>
                            <option value="10 AK 3">10 AK 3</option>
                            <option value="10 AK 4">10 AK 4</option>
                            <option value="10 DKV 1">10 DKV 1</option>
                            <option value="10 DKV 2">10 DKV 2</option>
                            <option value="10 PSPT 1">10 PSPT 1</option>
                            <option value="10 PSPT 2">10 PSPT 2</option>
                            <option value="10 TKJ 1">10 TKJ 1</option>
                            <option value="10 TKJ 2">10 TKJ 2</option>
                            <option value="10 Animasi 1">10 Animasi 1</option>
                            <option value="10 Animasi 2">10 Animasi 2</option>
                            <option value="10 BD 1">10 BD 1</option>
                            <option value="10 BD 2">10 BD 2</option>
                            <option value="10 BD 3">10 BD 3</option>
                        </optgroup>

                        <optgroup label="── Kelas 11 ──">
                            <option value="11 TKI 1">11 TKI 1</option>
                            <option value="11 TKI 2">11 TKI 2</option>
                            <option value="11 RPL 1">11 RPL 1</option>
                            <option value="11 RPL 2">11 RPL 2</option>
                            <option value="11 ULW">11 ULW</option>
                            <option value="11 MP 1">11 MP 1</option>
                            <option value="11 MP 2">11 MP 2</option>
                            <option value="11 MP 3">11 MP 3</option>
                            <option value="11 MP 4">11 MP 4</option>
                            <option value="11 AK 1">11 AK 1</option>
                            <option value="11 AK 2">11 AK 2</option>
                            <option value="11 AK 3">11 AK 3</option>
                            <option value="11 AK 4">11 AK 4</option>
                            <option value="11 DKV 1">11 DKV 1</option>
                            <option value="11 DKV 2">11 DKV 2</option>
                            <option value="11 PSPT 1">11 PSPT 1</option>
                            <option value="11 PSPT 2">11 PSPT 2</option>
                            <option value="11 TKJ 1">11 TKJ 1</option>
                            <option value="11 TKJ 2">11 TKJ 2</option>
                            <option value="11 Animasi 1">11 Animasi 1</option>
                            <option value="11 Animasi 2">11 Animasi 2</option>
                            <option value="11 BD 1">11 BD 1</option>
                            <option value="11 BD 2">11 BD 2</option>
                            <option value="11 BD 3">11 BD 3</option>
                        </optgroup>

                        <optgroup label="── Lainnya ──">
                            <option value="Guru">Guru</option>
                            <option value="Staf Karyawan">Staf karyawan</option>
                        </optgroup>
                    </select>
                    <i class="bi bi-mortarboard-fill select-icon"></i>
                </div>

                <button type="submit" class="btn btn-electric">Register</button>
            </form>
        </div>

        <!-- ══════════════════════════════════
             PANEL KANAN / KIRI
        ═══════════════════════════════════ -->
        <div class="bg-box">
            <div class="bg-panel bg-left">
                <img src="../gambar/maskot.png" alt="" class="gambar">
                <h1>Hello, welcome!!</h1>
                <p>Don't have account?</p>
                <button class="btn regist-btn" id="goRegister">Registrasi</button>
            </div>
            <div class="bg-panel bg-right">
                <img src="../gambar/maskot2.png" alt="" class="gambar2">
                <h1>Welcome back!!</h1>
                <p>Already have account?</p>
                <button class="btn login-btn" id="goLogin">Login</button>
            </div>
        </div>

    </div>

    <script src="login.js"></script>
</body>
</html>