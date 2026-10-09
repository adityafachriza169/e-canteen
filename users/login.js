document.addEventListener("DOMContentLoaded", function () {

    const container = document.getElementById("container");
    const goRegister = document.getElementById("goRegister");
    const goLogin = document.getElementById("goLogin");

    const loginForm = document.getElementById("loginForm");
    const registerForm = document.getElementById("registerForm");

    // ======================
    // DARK / LIGHT TOGGLE
    // ======================
    const themeToggle = document.getElementById("themeToggle");
    const themeIcon = document.getElementById("themeIcon");

    const savedTheme = localStorage.getItem("theme");
    if (savedTheme === "light") {
        document.body.classList.add("light");
        if (themeIcon) {
            themeIcon.classList.remove("ti-moon");
            themeIcon.classList.add("ti-sun");
        }
    }

    themeToggle?.addEventListener("click", () => {
        const isLight = document.body.classList.toggle("light");
        localStorage.setItem("theme", isLight ? "light" : "dark");
        if (themeIcon) {
            if (isLight) {
                themeIcon.classList.remove("ti-moon");
                themeIcon.classList.add("ti-sun");
            } else {
                themeIcon.classList.remove("ti-sun");
                themeIcon.classList.add("ti-moon");
            }
        }
    });

    // ======================
    // PANEL TOGGLE
    // ======================
    goRegister?.addEventListener("click", () => {
        container.classList.add("active");
    });

    goLogin?.addEventListener("click", () => {
        container.classList.remove("active");
    });

    // ======================
    // LOGIN
    // ======================
    loginForm?.addEventListener("submit", async function (e) {
        e.preventDefault();

        const usernameInput = loginForm.querySelector("[name='username']");
        const passwordInput = loginForm.querySelector("[name='password']");

        const usernameError = usernameInput.closest(".input-box").querySelector(".error-text");
        const passwordError = passwordInput.closest(".input-box").querySelector(".error-text");

        [usernameInput, passwordInput].forEach(i => i.classList.remove("error"));
        if (usernameError) usernameError.textContent = "";
        if (passwordError) passwordError.textContent = "";

        const formData = new FormData(loginForm);

        try {
            const response = await fetch("proses.php", {
                method: "POST",
                body: formData
            });

            const text = await response.text();

            let data;
            try {
                data = JSON.parse(text);
            } catch {
                console.error("Response bukan JSON:", text);
                confirmPopup({ judul: 'Server Error', ikon: '❌', pesan: 'Server tidak merespons dengan benar. Coba lagi.', tipe: 'danger', labelOK: 'Tutup' });
                setTimeout(() => { const b = document.getElementById('customConfirmCancel'); if(b) b.style.display='none'; }, 10);
                return;
            }

            if (data.status === "username_salah") {
                usernameInput.classList.add("error");
                if (usernameError) usernameError.textContent = "Username salah!";
                return;
            }

            if (data.status === "password_salah") {
                passwordInput.classList.add("error");
                if (passwordError) passwordError.textContent = "Password salah!";
                return;
            }

            if (data.status === "pending_approval") {
                // Tampilkan pesan di bawah field password
                passwordInput.classList.add("error");
                if (passwordError) {
                    passwordError.style.color = "#f59e0b";
                    passwordError.textContent = "⏳ Akun Anda masih menunggu persetujuan admin.";
                }
                return;
            }

            if (data.status === "akun_ditolak") {
                passwordInput.classList.add("error");
                if (passwordError) {
                    passwordError.style.color = "#ef4444";
                    passwordError.textContent = "❌ Akun Anda telah ditolak oleh admin.";
                }
                return;
            }

            if (data.status === "akun_nonaktif") {
                passwordInput.classList.add("error");
                if (passwordError) {
                    passwordError.style.color = "#ef4444";
                    passwordError.textContent = "🚫 Akun Anda telah dinonaktifkan.";
                }
                return;
            }

            if (data.status === "akun_nonaktif_penjual") {
                passwordInput.classList.add("error");
                if (passwordError) {
                    passwordError.style.color = "#ef4444";
                    passwordError.textContent = "🚫 Akun penjual Anda telah dinonaktifkan.";
                }
                return;
            }

            if (data.status === "sukses") {
                if (data.role === "admin") {
                    if (!data.id_kantin) {
                        confirmPopup({
                            judul: 'Gagal Login',
                            ikon: '❌',
                            pesan: 'ID kantin tidak ditemukan!',
                            tipe: 'danger',
                            labelOK: 'Tutup',
                            labelBatal: null,
                        });
                        // sembunyikan tombol batal
                        setTimeout(() => { const b = document.getElementById('customConfirmCancel'); if(b) b.style.display='none'; }, 10);
                        return;
                    }
                    window.location.href = `../penjual/dasbord.php`;
                    return;
                }
                const lastPage = localStorage.getItem("last_page");
                // Halaman-halaman yang tidak boleh jadi tujuan redirect setelah login
                const blockedPages = ['notif', 'pesan.php', 'logout', 'login'];
                const isBlocked = lastPage && blockedPages.some(p => lastPage.includes(p));
                if (lastPage && !isBlocked) {
                    localStorage.removeItem("last_page");
                    window.location.href = lastPage;
                } else {
                    localStorage.removeItem("last_page");
                    window.location.href = "user.php";
                }
            }
            else {
                confirmPopup({
                    judul: 'Respon Tidak Dikenal',
                    ikon: '⚠️',
                    pesan: 'Terjadi kesalahan tidak dikenal dari server.',
                    tipe: 'warning',
                    labelOK: 'Tutup',
                });
                setTimeout(() => { const b = document.getElementById('customConfirmCancel'); if(b) b.style.display='none'; }, 10);
            }

        } catch (err) {
            console.error(err);
            confirmPopup({ judul: 'Koneksi Gagal', ikon: '📡', pesan: 'Tidak bisa terhubung ke server. Periksa koneksi internet kamu.', tipe: 'danger', labelOK: 'Tutup' });
            setTimeout(() => { const b = document.getElementById('customConfirmCancel'); if(b) b.style.display='none'; }, 10);
        }
    });

    // ======================
    // REGISTER
    // ======================
    registerForm?.addEventListener("submit", async function (e) {
        e.preventDefault();

        const usernameInput = registerForm.querySelector("[name='username']");
        const emailInput = registerForm.querySelector("[name='email']");
        const noHpInput = registerForm.querySelector("[name='no_hp']");
        const tglLahirInput = registerForm.querySelector("[name='tanggal_lahir']");
        const jenisKelaminSelect = registerForm.querySelector("[name='gender']");

        const usernameError = usernameInput.closest(".input-box")?.querySelector(".error-text");
        const emailError = emailInput?.closest(".input-box")?.querySelector(".error-text");
        const noHpError = noHpInput?.closest(".input-box")?.querySelector(".error-text");
        const tglLahirError = tglLahirInput?.closest(".input-box")?.querySelector(".error-text");

        [usernameInput, emailInput, noHpInput, tglLahirInput].forEach(i => i?.classList.remove("error"));
        if (usernameError) usernameError.textContent = "";
        if (emailError) emailError.textContent = "";
        if (noHpError) noHpError.textContent = "";
        if (tglLahirError) tglLahirError.textContent = "";

        const formData = new FormData(registerForm);

        // VALIDASI EMAIL HARUS GMAIL
        const emailValue = emailInput.value.trim().toLowerCase();

        if (!emailValue.endsWith("@gmail.com")) {
            emailInput.classList.add("error");
            if (emailError) emailError.textContent = "Email harus menggunakan @gmail.com";
            return;
        }

        // VALIDASI NO HP
        const noHpValue = noHpInput ? noHpInput.value.trim() : '';
        if (!noHpValue || !/^08[0-9]{8,12}$/.test(noHpValue)) {
            if (noHpInput) noHpInput.classList.add("error");
            if (noHpError) noHpError.textContent = "Nomor HP tidak valid (contoh: 081234567890)";
            return;
        }

        // VALIDASI JENIS KELAMIN
        if (!jenisKelaminSelect || !jenisKelaminSelect.value) {
            confirmPopup({ judul: 'Pilih Jenis Kelamin', ikon: '⚠️', pesan: 'Pilih jenis kelamin terlebih dahulu!', tipe: 'warning', labelOK: 'Oke' });
            setTimeout(() => { const b = document.getElementById('customConfirmCancel'); if(b) b.style.display='none'; }, 10);
            return;
        }

        // VALIDASI TANGGAL LAHIR — minimal 15 tahun
        const tglLahirValue = tglLahirInput ? tglLahirInput.value : '';
        if (!tglLahirValue) {
            if (tglLahirInput) tglLahirInput.classList.add("error");
            if (tglLahirError) tglLahirError.textContent = "Tanggal lahir wajib diisi";
            return;
        }
        const birthDate = new Date(tglLahirValue);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) age--;
        if (age < 15) {
            if (tglLahirInput) tglLahirInput.classList.add("error");
            if (tglLahirError) tglLahirError.textContent = `Umur kamu ${age} tahun. Minimal harus 15 tahun untuk mendaftar.`;
            return;
        }

        // Ambil select kelas secara manual (mungkin berada di luar scope form)
        // Pastikan kelas terisi dengan benar
        const kelasSelect = document.querySelector("[name='kelas']");
        const kelasValue = kelasSelect ? kelasSelect.value.trim() : '';

        if (!kelasValue) {
            confirmPopup({ judul: 'Pilih Kelas', ikon: '⚠️', pesan: 'Pilih kelas terlebih dahulu!', tipe: 'warning', labelOK: 'Oke' });
            setTimeout(() => { const b = document.getElementById('customConfirmCancel'); if(b) b.style.display='none'; }, 10);
            return;
        }

        formData.set('kelas', kelasValue); // Override paksa, pastikan nilainya benar

        try {
            const response = await fetch("proses.php", {
                method: "POST",
                body: formData
            });

            const text = await response.text();

            let data;
            try {
                data = JSON.parse(text);
            } catch {
                console.error("Response bukan JSON:", text);
                confirmPopup({ judul: 'Server Error', ikon: '❌', pesan: 'Server tidak merespons dengan benar. Coba lagi.', tipe: 'danger', labelOK: 'Tutup' });
                setTimeout(() => { const b = document.getElementById('customConfirmCancel'); if(b) b.style.display='none'; }, 10);
                return;
            }

            if (data.status === "username_ada") {
                usernameInput.classList.add("error");
                if (usernameError) usernameError.textContent = "Username sudah dipakai!";
                return;
            }

            if (data.status === "email_ada") {
                emailInput.classList.add("error");
                if (emailError) emailError.textContent = "Email sudah dipakai!";
                return;
            }

            if (data.status === "register_sukses") {
                confirmPopup({
                    judul: 'Registrasi Berhasil! 🎉',
                    ikon: '✅',
                    pesan: 'Akun kamu sedang menunggu persetujuan admin.\nSilakan login setelah akun disetujui.',
                    tipe: 'success',
                    labelOK: 'Oke, Mengerti',
                    onOK: function() {
                        container.classList.remove("active");
                        registerForm.reset();
                    }
                });
                setTimeout(() => { const b = document.getElementById('customConfirmCancel'); if(b) b.style.display='none'; }, 10);
                return;
            }

            if (data.status === "data_kosong") {
                confirmPopup({ judul: 'Data Tidak Lengkap', ikon: '⚠️', pesan: 'Semua field harus diisi!', tipe: 'warning', labelOK: 'Oke' });
                setTimeout(() => { const b = document.getElementById('customConfirmCancel'); if(b) b.style.display='none'; }, 10);
                return;
            }

            if (data.status === "email_tidak_valid") {
                emailInput.classList.add("error");

                if (emailError) {
                    emailError.textContent = "Gunakan email @gmail.com";
                }

                return;
            }

            confirmPopup({ judul: 'Registrasi Gagal', ikon: '❌', pesan: 'Terjadi kesalahan saat registrasi. Coba lagi.', tipe: 'danger', labelOK: 'Tutup' });
            setTimeout(() => { const b = document.getElementById('customConfirmCancel'); if(b) b.style.display='none'; }, 10);

        } catch (err) {
            console.error(err);
            confirmPopup({ judul: 'Koneksi Gagal', ikon: '📡', pesan: 'Tidak bisa terhubung ke server. Periksa koneksi internet kamu.', tipe: 'danger', labelOK: 'Tutup' });
            setTimeout(() => { const b = document.getElementById('customConfirmCancel'); if(b) b.style.display='none'; }, 10);
        }
    });

    // ======================
    // RESET ERROR INPUT
    // ======================
    document.querySelectorAll("input").forEach(input => {
        input.addEventListener("input", function () {
            this.classList.remove("error");
            const errorText = this.closest(".input-box")?.querySelector(".error-text");
            if (errorText) errorText.textContent = "";
        });
    });

    // ======================
    // BLOKIR HURUF DI NO HP
    // ======================
    const noHpField = document.querySelector("[name='no_hp']");
    if (noHpField) {
        noHpField.addEventListener("keypress", function (e) {
            // Hanya izinkan angka (0-9)
            if (!/[0-9]/.test(e.key) && !["Backspace","Delete","Tab","ArrowLeft","ArrowRight"].includes(e.key)) {
                e.preventDefault();
            }
        });
        noHpField.addEventListener("input", function () {
            // Hapus semua karakter selain angka (antisipasi paste)
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        noHpField.addEventListener("paste", function (e) {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData).getData("text");
            this.value = pasted.replace(/[^0-9]/g, '').slice(0, 14);
        });
    }

});