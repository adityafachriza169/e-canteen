<?php
session_start();
include 'database.php';

// Jika sudah login via session
if (isset($_SESSION['is_login']) && $_SESSION['is_login'] === true) {
    header("Location: admin_beranda.php");
    exit;
}

// Jika sudah login via cookie
if (isset($_COOKIE['admin_remember'])) {
    $token = $_COOKIE['admin_remember'];
    $stmt = mysqli_prepare($db, "SELECT * FROM akun_admin WHERE ingat = ?");
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);

    if ($data) {
        $_SESSION['username'] = $data['username'];
        $_SESSION['is_login'] = true;
        header("Location: admin_beranda.php");
        exit;
    }
}


$login_gagal = "";

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = mysqli_prepare($db, "SELECT * FROM akun_admin WHERE username=?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);

    if ($data && $password === $data['password']) {
        $_SESSION['username'] = $data['username'];
        $_SESSION['is_login'] = true;

        $token = bin2hex(random_bytes(32));

        $stmt2 = mysqli_prepare($db, "UPDATE akun_admin SET ingat = ? WHERE username = ?");
        mysqli_stmt_bind_param($stmt2, "ss", $token, $username);
        mysqli_stmt_execute($stmt2);

        setcookie('admin_remember', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);

        header("Location: admin_beranda.php");
        exit;
    } else {
        $login_gagal = "Username atau password salah";
    }
}
?>



<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg:       #f2ede8;
  --card:     #ffffff;
  --text:     #1c1814;
  --muted:    #9a8f87;
  --border:   #e8e0d8;
  --accent:   #e05c2a;
  --field-bg: #f7f3ef;
}
[data-theme="dark"] {
  --bg:       #111009;
  --card:     #1c1a16;
  --text:     #ede8e0;
  --muted:    #6b6258;
  --border:   #2a2620;
  --accent:   #f07040;
  --field-bg: #231f1a;
}

body {
  font-family: 'Plus Jakarta Sans', sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  transition: background 0.35s, color 0.35s;
  position: relative;
  overflow: hidden;
}

/* Background dot pattern */
body::before {
  content: '';
  position: fixed;
  inset: 0;
  pointer-events: none;
  background-image: radial-gradient(circle, rgba(224,92,42,0.15) 1.2px, transparent 1.2px);
  background-size: 28px 28px;
  z-index: 0;
}
[data-theme="dark"] body::before {
  background-image: radial-gradient(circle, rgba(240,112,64,0.18) 1.2px, transparent 1.2px);
}

/* Soft warm blob */
body::after {
  content: '';
  position: fixed;
  width: 500px; height: 500px;
  border-radius: 50%;
  background: rgba(224,92,42,0.08);
  top: 50%; left: 50%;
  transform: translate(-50%, -55%);
  pointer-events: none;
  z-index: 0;
}
[data-theme="dark"] body::after {
  background: rgba(240,112,64,0.10);
}

.toggle {
  position: fixed;
  top: 20px; right: 20px;
  width: 40px; height: 40px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--card);
  cursor: pointer;
  display: grid; place-items: center;
  color: var(--muted);
  transition: all 0.2s;
  z-index: 10;
}
.toggle:hover { color: var(--accent); border-color: var(--accent); }
.toggle svg { width: 18px; height: 18px; }
.icon-moon { display: block; }
.icon-sun  { display: none; }
[data-theme="dark"] .icon-moon { display: none; }
[data-theme="dark"] .icon-sun  { display: block; }

.card {
  position: relative;
  z-index: 1;
  width: 100%; max-width: 380px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 40px 36px;
  animation: up 0.5s cubic-bezier(0.22,1,0.36,1) both;
}
@keyframes up {
  from { opacity:0; transform: translateY(16px); }
  to   { opacity:1; transform: translateY(0); }
}

/* Logo dengan border ring */
.logo-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 24px;
}
.logo-ring {
  position: relative;
  width: 84px; height: 84px;
  border-radius: 22px;
  display: grid; place-items: center;
}
/* Ring luar */
.logo-ring::before {
  content: '';
  position: absolute;
  inset: -6px;
  border-radius: 27px;
  border: 1.5px solid rgba(224,92,42,0.28);
  background: rgba(224,92,42,0.05);
}
/* Ring dalam / frame */
.logo-ring::after {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: 22px;
  border: 1.5px solid rgba(224,92,42,0.45);
  background: rgba(224,92,42,0.06);
}
[data-theme="dark"] .logo-ring::before {
  border-color: rgba(240,112,64,0.22);
  background: rgba(240,112,64,0.07);
}
[data-theme="dark"] .logo-ring::after {
  border-color: rgba(240,112,64,0.40);
  background: rgba(240,112,64,0.10);
}

.logo-ring img {
  width: 56px; height: 56px;
  object-fit: contain;
  border-radius: 12px;
  position: relative;
  z-index: 1;
}

h1 {
  font-size: 22px;
  font-weight: 600;
  letter-spacing: -0.4px;
  margin-bottom: 4px;
  text-align: center;
}
.sub {
  font-size: 14px;
  color: var(--muted);
  margin-bottom: 32px;
  text-align: center;
}

.field { margin-bottom: 14px; }
label {
  display: block;
  font-size: 13px;
  font-weight: 500;
  color: var(--muted);
  margin-bottom: 6px;
}
.field-wrap { position: relative; }
input {
  width: 100%;
  background: var(--field-bg);
  border: 1.5px solid transparent;
  border-radius: 12px;
  padding: 13px 16px;
  font-family: inherit;
  font-size: 15px;
  color: var(--text);
  outline: none;
  transition: border-color 0.2s;
}
input::placeholder { color: var(--muted); }
input:focus {
  border-color: transparent;
  box-shadow: none;
}
input[type="password"] { padding-right: 44px; }

.eye {
  position: absolute;
  right: 12px; top: 50%;
  transform: translateY(-50%);
  background: none; border: none;
  cursor: pointer; padding: 4px;
  color: var(--muted);
  display: grid; place-items: center;
  transition: color 0.2s;
}
.eye:hover { color: var(--accent); }
.eye svg { width: 17px; height: 17px; }

.error {
  font-size: 13px;
  color: var(--accent);
  margin-bottom: 16px;
  display: flex; align-items: center; gap: 6px;
}

.btn {
  width: 100%; margin-top: 8px;
  padding: 14px;
  background: var(--accent);
  color: #fff;
  border: none; border-radius: 12px;
  font-family: inherit;
  font-size: 15px; font-weight: 600;
  cursor: pointer;
  transition: opacity 0.2s, transform 0.15s;
}
.btn:hover  { opacity: 0.88; }
.btn:active { transform: scale(0.98); }

.foot {
  margin-top: 24px;
  display: flex; align-items: center; justify-content: center; gap: 6px;
  font-size: 12px; color: var(--muted);
}
.dot {
  width: 6px; height: 6px; border-radius: 50%;
  background: #4caf75;
  animation: blink 2.5s ease infinite;
}
@keyframes blink { 50% { opacity: 0.3; } }

@media (max-width: 440px) {
  .card { padding: 32px 24px; }
}
</style>
</head>
<body>

<button class="toggle" id="themeBtn" aria-label="Toggle dark mode">
  <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1111.21 3a7 7 0 009.79 9.79z"/></svg>
  <svg class="icon-sun"  viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
</button>

<div class="card">
  <div class="logo-wrap">
    <div class="logo-ring">
      <img src="../gambar/logo.png" alt="Logo">
    </div>
  </div>

  <h1>Login Admin</h1>
  <p class="sub">Masuk ke Halaman Admin E-canteen</p>

  <?php if (!empty($login_gagal)): ?>
  <div class="error" id="errMsg">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?php echo htmlspecialchars($login_gagal); ?>
  </div>
  <?php endif; ?>

  <form method="POST">
    <div class="field">
      <label for="u">Username</label>
      <input id="u" type="text" name="username"
             placeholder="Masukkan username"
             value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
             required autocomplete="username">
    </div>

    <div class="field">
      <label for="p">Password</label>
      <div class="field-wrap">
        <input id="p" type="password" name="password"
               placeholder="••••••••"
               required autocomplete="current-password">
        <button type="button" class="eye" id="eyeBtn" aria-label="Tampilkan password">
          <svg id="eyeIco" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
    </div>

    <button type="submit" name="login" class="btn">Masuk</button>
  </form>

  <div class="foot">
    <span class="dot"></span>
    Koneksi aman
  </div>
</div>

<script>
const html = document.documentElement;
html.setAttribute('data-theme', localStorage.getItem('theme') || 'light');
document.getElementById('themeBtn').onclick = () => {
  const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-theme', next);
  localStorage.setItem('theme', next);
};

const pw = document.getElementById('p');
const ico = document.getElementById('eyeIco');
document.getElementById('eyeBtn').onclick = () => {
  const show = pw.type === 'password';
  pw.type = show ? 'text' : 'password';
  ico.innerHTML = show
    ? `<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`
    : `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
};

const err = document.getElementById('errMsg');
if (err) setTimeout(() => { err.style.transition='opacity .4s'; err.style.opacity=0; setTimeout(()=>err.remove(),400); }, 4000);
</script>
</body>
</html>