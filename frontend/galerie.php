<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/functions.php";

start_secure_session();

include __DIR__ . "/../includes/header.php";
?>

<style>
/* Styles améliorés pour la galerie */
.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 30px;
}

.gallery-item {
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    height: 250px;
    position: relative;
}

.gallery-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 15px rgba(0,0,0,0.15);
}

.image-wrapper {
    width: 100%;
    height: 100%;
    overflow: hidden;
    position: relative;
}

.galerie-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    cursor: pointer;
    transition: transform 0.5s ease;
}

.galerie-image:hover {
    transform: scale(1.05);
}

/* Lightbox améliorée */
.lightbox {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.92);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.lightbox.active {
    display: flex;
    opacity: 1;
}

.lightbox-content {
    position: relative;
    max-width: 90vw;
    max-height: 90vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

#lightbox-img {
    max-width: 100%;
    max-height: 85vh;
    border-radius: 4px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    animation: fadeIn 0.4s ease-out;
}

.lb-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0,0,0,0.6);
    color: white;
    border: none;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    font-size: 30px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.3s ease;
    z-index: 10;
}

.lb-btn:hover {
    background: rgba(0,0,0,0.8);
}

#lb-prev {
    left: 20px;
}

#lb-next {
    right: 20px;
}

#lb-close {
    position: absolute;
    top: 20px;
    right: 20px;
    background: none;
    border: none;
    color: white;
    font-size: 40px;
    cursor: pointer;
    z-index: 11;
    transition: transform 0.2s ease;
}

#lb-close:hover {
    transform: scale(1.2);
}

.lightbox-counter {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    color: white;
    background: rgba(0,0,0,0.5);
    padding: 8px 20px;
    border-radius: 20px;
    font-size: 16px;
}

@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

@media (max-width: 768px) {
    .gallery-grid {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    }
    
    .lb-btn {
        width: 50px;
        height: 50px;
        font-size: 24px;
    }
    
    #lb-close {
        font-size: 35px;
    }
}
</style>

<section class="page-title">
    <div class="container">
        <h1>Notre Galerie Photo</h1>
    </div>
</section>

<section class="gallery">
    <div class="container">
        <p class="intro-text">
            Découvrez certaines de nos réalisations au salon King and Qween.
            Cliquez sur une image pour l’agrandir et naviguer.
        </p>

        <div class="gallery-grid">
            <?php
            $dir = __DIR__ . "/../Images/";
            if (is_dir($dir)) {
                $files = array_diff(scandir($dir), ['.', '..']);
                rsort($files); // plus récentes en premier
                foreach ($files as $file) {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                        $path = BASE_URL . "Images/" . $file;
                        echo '<div class="gallery-item">';
                        echo '    <div class="image-wrapper">';
                        echo '        <img src="' . htmlspecialchars($path) . '" alt="Coiffure" class="galerie-image">';
                        echo '    </div>';
                        echo '</div>';
                    }
                }
            }
            ?>
        </div>
    </div>
</section>

<!-- Lightbox améliorée -->
<div id="lightbox" class="lightbox">
    <button id="lb-close">&times;</button>
    <button id="lb-prev" class="lb-btn">&#10094;</button>
    <div class="lightbox-content">
        <img src="" alt="Aperçu" id="lightbox-img">
    </div>
    <button id="lb-next" class="lb-btn">&#10095;</button>
    <div id="lightbox-counter" class="lightbox-counter"></div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Lightbox améliorée
    const images = Array.from(document.querySelectorAll(".galerie-image"));
    const lightbox = document.getElementById("lightbox");
    const lightboxImg = document.getElementById("lightbox-img");
    const btnPrev = document.getElementById("lb-prev");
    const btnNext = document.getElementById("lb-next");
    const btnClose = document.getElementById("lb-close");
    const lightboxCounter = document.getElementById("lightbox-counter");

    if (images.length > 0 && lightbox && lightboxImg && btnPrev && btnNext && btnClose) {
        let currentIndex = 0;
        let touchStartX = 0;
        let touchEndX = 0;

        function openLightbox(index) {
            currentIndex = index;
            lightboxImg.src = images[index].src;
            lightbox.classList.add("active");
            document.body.style.overflow = "hidden";
            updateCounter();
        }

        function closeLightbox() {
            lightbox.classList.remove("active");
            document.body.style.overflow = "";
        }

        function showNext() {
            currentIndex = (currentIndex + 1) % images.length;
            lightboxImg.src = images[currentIndex].src;
            updateCounter();
            animateTransition('next');
        }

        function showPrev() {
            currentIndex = (currentIndex - 1 + images.length) % images.length;
            lightboxImg.src = images[currentIndex].src;
            updateCounter();
            animateTransition('prev');
        }

        function updateCounter() {
            lightboxCounter.textContent = `${currentIndex + 1} / ${images.length}`;
        }
        
        function animateTransition(direction) {
            lightboxImg.style.animation = 'none';
            setTimeout(() => {
                lightboxImg.style.animation = 'fadeIn 0.4s ease-out';
            }, 10);
        }

        // Navigation tactile
        lightbox.addEventListener('touchstart', e => {
            touchStartX = e.changedTouches[0].screenX;
        });

        lightbox.addEventListener('touchend', e => {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        });

        function handleSwipe() {
            const diff = touchStartX - touchEndX;
            if (diff > 50) showNext();       // Swipe gauche
            else if (diff < -50) showPrev();  // Swipe droit
        }

        // Ouverture
        images.forEach((img, index) => {
            img.addEventListener("click", () => openLightbox(index));
        });

        // Fermeture
        btnClose.addEventListener("click", closeLightbox);
        lightbox.addEventListener("click", (e) => {
            if (e.target === lightbox) closeLightbox();
        });

        // Navigation
        btnNext.addEventListener("click", (e) => {
            e.stopPropagation();
            showNext();
        });

        btnPrev.addEventListener("click", (e) => {
            e.stopPropagation();
            showPrev();
        });

        // Navigation clavier
        document.addEventListener("keydown", (e) => {
            if (lightbox.classList.contains("active")) {
                if (e.key === "ArrowRight") showNext();
                if (e.key === "ArrowLeft") showPrev();
                if (e.key === "Escape") closeLightbox();
            }
        });
    }
});
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>