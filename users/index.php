<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WELCOME TO E CANTEEN</title>
</head>
<style>
    html, body {
    margin: 0;
    padding: 0;
    height: 100%;
}
    body{
        margin: 0;
        overflow: hidden;
        background: black;
        cursor: pointer;
    }
    .klik {
    position: absolute;
    bottom: 40px;
    width: 100%;
    text-align: center;
    color: rgba(0, 0, 0, 0.7);
    font-family: sans-serif;
    font-size: 14px;
    letter-spacing: 1px;
    font-weight: bold;
}
    
        .container {
            position: fixed;
            width: 100%;
            height: 100%;
            transition: transform 1.2s ease, filter 1.2s ease, opacity 1.2s ease;
        }

        .container.cinema{
            transform: scale(1.1);   /* zoom */
            filter: blur(6px);       /* blur */
            opacity: 0;              /* fade */
        }

/* Default sembunyikan mobile */
.video-mobile {
    display: none;
}

/* Desktop */
.video-desktop {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Mobile */
@media (max-width: 600px) {

    .video-desktop {
        display: none;
    }

    .video-mobile {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
}
</style>
<body>
    <div class="container" id="cinem">
    <!-- Desktop -->
    <video id="video" class="video-desktop" autoplay muted loop playsinline>
        <source src="../gambar/anim.mp4" type="video/mp4">
    </video>

    <!-- Mobile -->
    <video  class="video-mobile" autoplay muted loop playsinline>
        <source id="video" src="../gambar/anim-mobile.mp4" type="video/mp4">
    </video>

    <div class="klik">Klik dimana saja untuk lanjut!!</div>
</div>
    <script>
        let clicked = false;
        const video = document.getElementById("video");

        // klik di mana saja
        document.addEventListener("click", function() {
            if (clicked) return;
            clicked = true;

            const container = document.getElementById("cinem");
            container.classList.add("cinema");

            // delay sesuai animasi
            setTimeout(function() {
                window.location.href = "login";
            }, 1200);
        });

    </script>
</body>
</html>