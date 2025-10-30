
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
            lightbox.style.display = "flex";
            document.body.style.overflow = "hidden";
            updateCounter();
            
            // Animation d'ouverture
            lightbox.style.opacity = 0;
            let opacity = 0;
            const fadeIn = setInterval(() => {
                opacity += 0.05;
                lightbox.style.opacity = opacity;
                if (opacity >= 1) clearInterval(fadeIn);
            }, 20);
        }

        function closeLightbox() {
            // Animation de fermeture
            let opacity = 1;
            const fadeOut = setInterval(() => {
                opacity -= 0.05;
                lightbox.style.opacity = opacity;
                if (opacity <= 0) {
                    clearInterval(fadeOut);
                    lightbox.style.display = "none";
                    document.body.style.overflow = "";
                }
            }, 20);
        }

        function showNext() {
            currentIndex = (currentIndex + 1) % images.length;
            transitionImage(currentIndex, 'next');
        }

        function showPrev() {
            currentIndex = (currentIndex - 1 + images.length) % images.length;
            transitionImage(currentIndex, 'prev');
        }

        function updateCounter() {
            lightboxCounter.textContent = `${currentIndex + 1} / ${images.length}`;
        }

        function transitionImage(index, direction) {
            // Animation de transition
            lightboxImg.style.opacity = 0;
            lightboxImg.style.transform = `translateX(${direction === 'next' ? '30px' : '-30px'})`;
            
            setTimeout(() => {
                lightboxImg.src = images[index].src;
                updateCounter();
                
                lightboxImg.style.transition = 'all 0.4s ease';
                lightboxImg.style.opacity = 1;
                lightboxImg.style.transform = 'translateX(0)';
                
                // Réinitialiser après l'animation
                setTimeout(() => {
                    lightboxImg.style.transition = '';
                }, 400);
            }, 200);
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
            if (lightbox.style.display === "flex") {
                if (e.key === "ArrowRight") showNext();
                if (e.key === "ArrowLeft") showPrev();
                if (e.key === "Escape") closeLightbox();
            }
        });
    }
});