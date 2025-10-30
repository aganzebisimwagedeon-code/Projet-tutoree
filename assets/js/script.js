document.addEventListener("DOMContentLoaded", function() {
    // Ton code existant
    const buttons = document.querySelectorAll(".btn");
    buttons.forEach(button => {
        if (!button.classList.contains("btn-danger") && !button.classList.contains("btn-success")) {
            button.addEventListener("click", function(event) {
                // event.preventDefault();
                // console.log("Bouton cliqué: " + this.textContent);
            });
        }
    });

    // Lightbox pour la galerie
    const images = Array.from(document.querySelectorAll(".galerie-image"));
    const lightbox = document.getElementById("lightbox");
    const lightboxImg = document.getElementById("lightbox-img");
    const btnPrev = document.getElementById("lb-prev");
    const btnNext = document.getElementById("lb-next");

    if (images.length > 0 && lightbox && lightboxImg && btnPrev && btnNext) {
        let currentIndex = 0;

        function openLightbox(index) {
            currentIndex = index;
            lightboxImg.src = images[index].src;
            lightbox.style.display = "flex";
            document.body.style.overflow = "hidden";
        }

        function closeLightbox() {
            lightbox.style.display = "none";
            document.body.style.overflow = "";
        }

        function showNext() {
            currentIndex = (currentIndex + 1) % images.length;
            lightboxImg.src = images[currentIndex].src;
        }

        function showPrev() {
            currentIndex = (currentIndex - 1 + images.length) % images.length;
            lightboxImg.src = images[currentIndex].src;
        }

        // Ouverture
        images.forEach((img, index) => {
            img.addEventListener("click", () => openLightbox(index));
        });

        // Fermeture au clic sur le fond
        lightbox.addEventListener("click", (e) => {
            if (e.target === lightbox) {
                closeLightbox();
            }
        });

        // Boutons navigation
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