//attend que la page HTML soit complètement chargée avant d'exécuter le code
document.addEventListener('DOMContentLoaded', () => {
    //récupère l'élément select pour le tri
    const triSelect = document.getElementById('triSelect');
    //vérifie que l'élément existe sur la page
    if (triSelect) {
        //écoute quand l'utilisateur change le tri
        triSelect.addEventListener('change', function() {
            //soumet le formulaire pour recharger la page avec le nouveau tri
            document.getElementById('filterForm').submit();
        });
    }

    //récupère l'élément select pour les catégories
    const categorieSelect = document.getElementById('categorieSelect');
    //vérifie que l'élément existe sur la page
    if (categorieSelect) {
        //écoute quand l'utilisateur change la catégorie
        categorieSelect.addEventListener('change', function() {
            //soumet le formulaire pour recharger la page avec la nouvelle catégorie
            document.getElementById('filterForm').submit();
        });
    }
});

//attend que la page HTML soit complètement chargée
document.addEventListener('DOMContentLoaded', () => {

    const rangeMin = document.getElementById("rangeMin");
    const rangeMax = document.getElementById("rangeMax");
    const inputMin = document.getElementById("inputMin");
    const inputMax = document.getElementById("inputMax");
    const sliderTrackActive = document.getElementById("sliderTrackActive");

    if (rangeMin && rangeMax && inputMin && inputMax && sliderTrackActive) {

        let minGap = 0; // Ecart minimum
        const sliderMaxValue = Number(rangeMax.max) || 1;
        const sliderMinValue = Number(rangeMin.min) || 0;

        const toNumber = (v, fallback = 0) => {
            const n = Number(v);
            return Number.isFinite(n) ? n : fallback;
        };

        // Met à jour la barre colorée
        function updateTrack() {
            const maxVal = sliderMaxValue || 1;
            const percent1 = (toNumber(rangeMin.value, sliderMinValue) / maxVal) * 100;
            const percent2 = (toNumber(rangeMax.value, maxVal) / maxVal) * 100;
            sliderTrackActive.style.left = Math.max(0, Math.min(100, percent1)) + "%";
            sliderTrackActive.style.width = Math.max(0, Math.min(100, percent2 - percent1)) + "%";
        }

        // Helpers pour setter et clamp
        function setMinValue(v) {
            let minVal = toNumber(v, sliderMinValue);
            const maxVal = toNumber(rangeMax.value, sliderMaxValue);
            if (minVal < sliderMinValue) minVal = sliderMinValue;
            if (minVal > maxVal) minVal = maxVal;
            rangeMin.value = minVal;
            inputMin.value = minVal;
        }

        function setMaxValue(v) {
            let maxVal = toNumber(v, sliderMaxValue);
            const minVal = toNumber(rangeMin.value, sliderMinValue);
            if (maxVal > sliderMaxValue) maxVal = sliderMaxValue;
            if (maxVal < minVal) maxVal = minVal;
            rangeMax.value = maxVal;
            inputMax.value = maxVal;
        }

        // Gestion Range Min
        rangeMin.addEventListener("input", function() {
            setMinValue(rangeMin.value);
            updateTrack();
        });

        // Gestion Range Max
        rangeMax.addEventListener("input", function() {
            setMaxValue(rangeMax.value);
            updateTrack();
        });

        // Gestion Input Min
        inputMin.addEventListener("input", function() {
            const v = inputMin.value === '' ? sliderMinValue : inputMin.value;
            setMinValue(v);
            updateTrack();
        });

         // Gestion Input Max
         inputMax.addEventListener("input", function() {
            const v = inputMax.value === '' ? sliderMaxValue : inputMax.value;
            setMaxValue(v);
            updateTrack();
        });

        // Submission du formulaire au changement final (relâchement de souris ou entrée)
        [rangeMin, rangeMax, inputMin, inputMax].forEach(el => {
            el.addEventListener("change", () => {
                document.getElementById('filterForm').submit();
            });
        });

        // Initialisation : s'assurer que valeurs sont valides
        setMinValue(rangeMin.value);
        setMaxValue(rangeMax.value);
        updateTrack();
    }
});

//attend que la page HTML soit complètement chargée
document.addEventListener('DOMContentLoaded', () => {
    //récupère la case à cocher pour le filtre "en stock"
    const stockOnlyCheckbox = document.getElementById('stockOnlyCheckbox');
    //vérifie que l'élément existe
    if (stockOnlyCheckbox) {
        //écoute quand l'utilisateur coche ou décoche la case
        stockOnlyCheckbox.addEventListener('change', function() {
            //soumet le formulaire pour appliquer le filtre
            document.getElementById('filterForm').submit();
        });
    }

    //récupère le bouton pour effacer tous les filtres
    const clearBtn = document.getElementById('clearFiltersBtn');
    //vérifie que le bouton existe
    if (clearBtn) {
        //écoute quand l'utilisateur clique sur le bouton
        clearBtn.addEventListener('click', (e) => {
            //empêche le comportement par défaut du bouton
            e.preventDefault();
            //remet la catégorie sur "tous les produits"
            document.getElementById('categorieSelect').value = 'all';
            
            //reset les valeurs des sliders de prix
            const inputMin = document.getElementById("inputMin");
            const inputMax = document.getElementById("inputMax");
            const rangeMin = document.getElementById("rangeMin");
            const rangeMax = document.getElementById("rangeMax");

            if (rangeMin && rangeMax && inputMin && inputMax) {
                const maxVal = Number(rangeMax.max) || 0;
                const minVal = Number(rangeMin.min) || 0;
                rangeMin.value = minVal;
                rangeMax.value = maxVal;
                inputMin.value = minVal;
                inputMax.value = maxVal;
                // forcer mise à jour visuelle du track
                rangeMin.dispatchEvent(new Event('input'));
                rangeMax.dispatchEvent(new Event('input'));
            }

            //vide la barre de recherche vendeur
            const searchVendeurEl = document.getElementById('searchVendeur');
            if (searchVendeurEl) searchVendeurEl.value = '';
            //réinitialise le champ de recherche par nom (protégé)
            const nomChercherEl = document.getElementById('nomChercher');
            if (nomChercherEl) nomChercherEl.value = '';
            const inputNoteMin = document.getElementById('inputNoteMin');
            if (inputNoteMin) {
                inputNoteMin.value = '0'; // remet la note minimale à 0
            }
            //décoche la case "en stock uniquement" (protégé)
            const stockEl = document.getElementById('stockOnlyCheckbox');
            if (stockEl) stockEl.checked = false;
            //récupère tous les boutons d'étoiles
            const btns = document.querySelectorAll('.star-btn');
            //parcourt chaque bouton d'étoile
            btns.forEach(b => {
                //remet l'image d'étoile vide
                b.querySelector('img').src = '/img/svg/star-empty.svg';
            });
            //soumet le formulaire pour afficher tous les produits sans filtres
            document.getElementById('filterForm').submit();
        });
    }

    //récupère le widget des étoiles pour le filtre de note
    const widget = document.getElementById('starFilterWidget');
    //récupère le champ caché qui stocke la note sélectionnée
    const input = document.getElementById('inputNoteMin');
    //récupère la valeur de note déjà sauvegardée (0 par défaut)
    let selectedValue = input ? parseInt(input.value) : 0;

    //vérifie que le widget existe
    if (widget) {
        //récupère tous les boutons d'étoiles
        const btns = widget.querySelectorAll('.star-btn');

        //fonction pour mettre à jour l'affichage des étoiles
        const updateStars = (val) => {
            //parcourt chaque bouton d'étoile
            btns.forEach(b => {
                //récupère la valeur de l'étoile (1 à 5)
                const v = parseInt(b.dataset.value);
                //récupère l'image de l'étoile
                const img = b.querySelector('img');
                //change l'image selon si l'étoile doit être pleine ou vide
                img.src = v <= val ? '/img/svg/star-full.svg' : '/img/svg/star-empty.svg';
            });
        };

        //affiche les étoiles avec la valeur déjà sauvegardée
        updateStars(selectedValue);

        //parcourt chaque bouton d'étoile
        btns.forEach(btn => {
            //écoute quand la souris passe sur une étoile
            btn.addEventListener('mouseenter', () => updateStars(btn.dataset.value));

            //écoute quand l'utilisateur clique sur une étoile
            btn.addEventListener('click', (e) => {
                //empêche le comportement par défaut
                e.preventDefault();
                //sauvegarde la note sélectionnée
                selectedValue = parseInt(btn.dataset.value);
                //met à jour le champ caché avec la nouvelle note
                if (input) input.value = selectedValue;
                //met à jour l'affichage des étoiles
                updateStars(selectedValue);

                //soumet le formulaire pour appliquer le filtre de note
                document.getElementById('filterForm').submit();
            });
        });

        //écoute quand la souris quitte le widget d'étoiles
        widget.addEventListener('mouseleave', () => updateStars(selectedValue));
    }
});

//fonction pour ajouter un produit au panier
function ajouterAuPanier(idProduit) {
    //crée un objet pour envoyer les données au serveur
    const formData = new FormData();
    //ajoute l'action "ajouter au panier"
    formData.append('action', 'ajouter_panier');
    //ajoute l'ID du produit à ajouter
    formData.append('idProduit', idProduit);
    //ajoute la quantité (1 par défaut)
    formData.append('quantite', 1);

    //envoie la requête AJAX au serveur
    fetch('index.php', {
            //type de requête POST
            method: 'POST',
            //données à envoyer
            body: formData,
            noLoader: true
        })
        //attend la réponse et la convertit en JSON
        .then(response => response.json())
        //traite la réponse du serveur
        .then(data => {
            //récupère le message de succès ou d'erreur
            const message = data.success ? data.message : data.message;
            //détermine le type de notification (succès ou erreur)
            const type = data.success ? 'success' : 'error';
            //affiche la notification (ou une alerte si la fonction notify n'existe pas)
            window.notify ? notify(message, type) : alert((data.success ? '✓ ' : '✗ ') + message);
        })
        //capture les erreurs de la requête
        .catch(error => {
            //affiche l'erreur dans la console du navigateur
            console.error('Erreur:', error);
            //affiche un message d'erreur à l'utilisateur
            window.notify ? notify('Erreur lors de l\'ajout au panier', 'error') : alert(
                'Erreur lors de l\'ajout au panier');
        });
}

//attend que la page HTML soit complètement chargée
document.addEventListener('DOMContentLoaded', () => {
    //récupère la barre de recherche vendeur
    const searchInput = document.getElementById('searchVendeur');

    //vérifie que l'élément existe
    if (searchInput) {
        //variable pour stocker le timer de recherche
        let timeoutId;
        //écoute chaque fois que l'utilisateur tape dans la barre
        searchInput.addEventListener('input', function() {
            //annule le timer précédent
            clearTimeout(timeoutId);
            //crée un nouveau timer de 500ms (0.5 seconde)
            timeoutId = setTimeout(() => {
                //soumet le formulaire après 500ms sans nouvelle frappe
                document.getElementById('filterForm').submit();
            }, 500);
        });

        //écoute quand l'utilisateur appuie sur une touche
        searchInput.addEventListener('keypress', function(e) {
            //vérifie si c'est la touche Entrée
            if (e.key === 'Enter') {
                //empêche le comportement par défaut
                e.preventDefault();
                //soumet le formulaire immédiatement
                document.getElementById('filterForm').submit();
            }
        });
    }
});

//pour gérer l'ouverture et la fermeture des filtres sur les petits écrans
document.addEventListener('DOMContentLoaded', function () {
    const aside = document.querySelector('aside');

    if (aside) {
        aside.addEventListener('click', function () {
            this.classList.toggle('open');
        });
    }
});

/* ============================================================ */
/*  LOGIQUE DU CAROUSEL                                         */
/* ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
    const track = document.querySelector('.carousel-track');
    if (!track) return;

    const slides = Array.from(track.children);
    const nextButton = document.querySelector('.next-btn');
    const prevButton = document.querySelector('.prev-btn');
    const dotsNav = document.querySelector('.carousel-nav');
    const dots = Array.from(dotsNav.children);

    const moveToSlide = (track, currentSlide, targetSlide) => {
        track.style.transform = 'translateX(-' + targetSlide.offsetLeft + 'px)';
        currentSlide.classList.remove('active');
        targetSlide.classList.add('active');
    }

    const updateDots = (currentDot, targetDot) => {
        currentDot.classList.remove('active');
        targetDot.classList.add('active');
    }

    // Bouton suivant (droite)
    nextButton.addEventListener('click', () => {
        const currentSlide = track.querySelector('.active') || slides[0];
        let nextSlide = currentSlide.nextElementSibling;
        let currentDot = dotsNav.querySelector('.active') || dots[0];
        let nextDot = currentDot.nextElementSibling;

        // Boucle infini vers le début
        if (!nextSlide) {
            nextSlide = slides[0];
            nextDot = dots[0];
        }

        moveToSlide(track, currentSlide, nextSlide);
        updateDots(currentDot, nextDot);
    });

    // Bouton précédent (gauche)
    prevButton.addEventListener('click', () => {
        const currentSlide = track.querySelector('.active') || slides[0];
        let prevSlide = currentSlide.previousElementSibling;
        let currentDot = dotsNav.querySelector('.active') || dots[0];
        let prevDot = currentDot.previousElementSibling;

        // Boucle infini vers la fin
        if (!prevSlide) {
            prevSlide = slides[slides.length - 1];
            prevDot = dots[dots.length - 1];
        }

        moveToSlide(track, currentSlide, prevSlide);
        updateDots(currentDot, prevDot);
    });

    // Navigation par points
    dotsNav.addEventListener('click', e => {
        const targetDot = e.target.closest('button');
        if (!targetDot) return;

        const currentSlide = track.querySelector('.active') || slides[0];
        const currentDot = dotsNav.querySelector('.active') || dots[0];
        const targetIndex = dots.findIndex(dot => dot === targetDot);
        const targetSlide = slides[targetIndex];

        moveToSlide(track, currentSlide, targetSlide);
        updateDots(currentDot, targetDot);
    });

    // Redimensionnement de la fenêtre
    window.addEventListener('resize', () => {
        const currentSlide = track.querySelector('.active') || slides[0];
        track.style.transform = 'translateX(-' + currentSlide.offsetLeft + 'px)';
    });

    // --- AUTO SCROLL - AUTOMATISATION DU DEFILEMENT ---
    let autoPlayInterval;
    const intervalTime = 5000; // 5 secondes

    const startAutoPlay = () => {
        // Évite d'avoir plusieurs intervalles en même temps
        clearInterval(autoPlayInterval);
        autoPlayInterval = setInterval(() => {
            // Simule un clic sur le bouton suivant
            nextButton.click();
        }, intervalTime);
    };

    const stopAutoPlay = () => {
        clearInterval(autoPlayInterval);
    };

    // Démarre le défilement automatique
    startAutoPlay();

    // Arrête le défilement au survol de la souris
    const carouselContainer = document.querySelector('.carousel-container');
    if (carouselContainer) {
        carouselContainer.addEventListener('mouseenter', stopAutoPlay);
        carouselContainer.addEventListener('mouseleave', startAutoPlay);
    }
});