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
    //récupère le curseur de prix (slider)
    const priceRange = document.getElementById('priceRange');
    //récupère le champ numérique de prix
    const priceInput = document.getElementById('priceValue');

    //vérifie que les deux éléments existent
    if (priceRange && priceInput) {
        //fonction pour synchroniser le curseur et le champ numérique
        const setAll = (v) => {
            //arrondit le prix à 2 décimales
            const num = Math.round((parseFloat(v) || 0) * 100) / 100;
            //met à jour la valeur du curseur
            priceRange.value = num;
            //met à jour la valeur du champ numérique
            priceInput.value = num;
        };

        //synchronise les valeurs au chargement de la page
        setAll(priceInput.value);

        //écoute quand l'utilisateur bouge le curseur
        priceRange.addEventListener('input', function() {
            //met à jour le champ numérique en temps réel
            setAll(this.value);
        });

        //écoute quand l'utilisateur relâche le curseur
        priceRange.addEventListener('change', function() {
            //soumet le formulaire pour appliquer le filtre de prix
            document.getElementById('filterForm').submit();
        });

        //écoute quand l'utilisateur tape dans le champ numérique
        priceInput.addEventListener('input', function() {
            //récupère le prix maximum autorisé
            const max = parseFloat(this.max) || parseFloat(priceRange.max) || 0;
            //récupère la valeur tapée par l'utilisateur
            let v = parseFloat(this.value) || 0;
            //empêche les valeurs négatives
            if (v < 0) v = 0;
            //empêche de dépasser le prix maximum
            if (v > max) v = max;
            //met à jour le curseur avec la nouvelle valeur
            setAll(v);
        });
        //écoute quand l'utilisateur a fini de taper
        priceInput.addEventListener('change', function() {
            //soumet le formulaire pour appliquer le filtre
            document.getElementById('filterForm').submit();
        });
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
            //récupère le prix maximum possible
            const maxVal = document.getElementById('priceRange').max;
            //remet le curseur de prix au maximum
            document.getElementById('priceRange').value = maxVal;
            //récupère le champ numérique de prix
            const priceEl = document.getElementById('priceValue');
            //vérifie que le champ existe
            if (priceEl) {
                //remet le prix au maximum
                priceEl.value = maxVal;
            }
            //vide la barre de recherche vendeur
            document.getElementById('searchVendeur').value = '';
            //réinitialise le champ de recherche par nom
            document.getElementById('nomChercher').value = '';
            //décoche la case "en stock uniquement"
            document.getElementById('stockOnlyCheckbox').checked = false;
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
            body: formData
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