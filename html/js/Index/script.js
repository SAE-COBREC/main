
// Gestion du changement de tri
document.addEventListener('DOMContentLoaded', () => {
    const triSelect = document.getElementById('triSelect');
    if (triSelect) {
        triSelect.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    }

    // Gestion du changement de catégorie
    const categorieSelect = document.getElementById('categorieSelect');
    if (categorieSelect) {
        categorieSelect.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    }
});

// Gestion du slider de prix et du champ numérique (le champ `name="price"`)
document.addEventListener('DOMContentLoaded', () => {
    const priceRange = document.getElementById('priceRange');
    const priceInput = document.getElementById('priceValue'); // now an <input name="price">

    if (priceRange && priceInput) {
        const setAll = (v) => {
            const num = Math.round((parseFloat(v) || 0) * 100) / 100;
            priceRange.value = num;
            priceInput.value = num;
        };

        // initial sync
        setAll(priceInput.value);

        // slider -> input
        priceRange.addEventListener('input', function() {
            setAll(this.value);
        });

        // submit on change (end of interaction)
        priceRange.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });

        // input -> slider, submit on change
        priceInput.addEventListener('input', function() {
            // keep inside bounds
            const max = parseFloat(this.max) || parseFloat(priceRange.max) || 0;
            let v = parseFloat(this.value) || 0;
            if (v < 0) v = 0;
            if (v > max) v = max;
            setAll(v);
        });
        priceInput.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    }
});

// Gestion du checkbox "En stock uniquement"
document.addEventListener('DOMContentLoaded', () => {
    const stockOnlyCheckbox = document.getElementById('stockOnlyCheckbox');
    if (stockOnlyCheckbox) {
        stockOnlyCheckbox.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    }
});

// Gestion du bouton Effacer les filtres
document.addEventListener('DOMContentLoaded', () => {
    const clearBtn = document.getElementById('clearFiltersBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', (e) => {
            e.preventDefault();
            // Réinitialiser tous les filtres
            document.getElementById('categorieSelect').value = 'all';
            const maxVal = document.getElementById('priceRange').max;
            document.getElementById('priceRange').value = maxVal;
            const priceEl = document.getElementById('priceValue');
            if (priceEl) {
                priceEl.value = maxVal;
            }
            document.getElementById('searchVendeur').value = '';
            document.getElementById('nomChercher').value = maxVal;
            document.getElementById('inputNoteMin').value = '0';
            document.getElementById('triSelect').value = 'meilleures_ventes';
            document.getElementById('stockOnlyCheckbox').checked = false;
            // Réinitialiser les étoiles
            const btns = document.querySelectorAll('.star-btn');
            btns.forEach(b => {
                b.querySelector('img').src = '/img/svg/star-empty.svg';
            });
            // Soumettre le formulaire
            document.getElementById('filterForm').submit();
        });
    }
});

// Gestion du sélecteur d'étoiles (Filtres)
document.addEventListener('DOMContentLoaded', () => {
    const widget = document.getElementById('starFilterWidget');
    const input = document.getElementById('inputNoteMin');
    let selectedValue = input ? parseInt(input.value) : 0;

    if (widget) {
        const btns = widget.querySelectorAll('.star-btn');

        const updateStars = (val) => {
            btns.forEach(b => {
                const v = parseInt(b.dataset.value);
                const img = b.querySelector('img');
                // Change l'image selon la valeur (full ou empty)
                img.src = v <= val ? '/img/svg/star-full.svg' : '/img/svg/star-empty.svg';
            });
        };

        // Initialiser l'affichage avec la valeur sauvegardée
        updateStars(selectedValue);

        btns.forEach(btn => {
            // Survol : affiche les étoiles jusqu'au curseur
            btn.addEventListener('mouseenter', () => updateStars(btn.dataset.value));

            // Clic : sélectionne la note
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                selectedValue = parseInt(btn.dataset.value);
                if (input) input.value = selectedValue;
                updateStars(selectedValue);

                // Soumettre le formulaire pour appliquer le filtre
                document.getElementById('filterForm').submit();
            });
        });

        // Sortie de souris : revient à la valeur sélectionnée
        widget.addEventListener('mouseleave', () => updateStars(selectedValue));
    }
});

//fonction pour ajouter au panier avec requête AJAX vers la base de données
function ajouterAuPanier(idProduit) {
    const formData = new FormData();
    formData.append('action', 'ajouter_panier');
    formData.append('idProduit', idProduit);
    formData.append('quantite', 1);

    fetch('index.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            const message = data.success ? data.message : data.message;
            const type = data.success ? 'success' : 'error';
            window.notify ? notify(message, type) : alert((data.success ? '✓ ' : '✗ ') + message);
        })
        .catch(error => {
            console.error('Erreur:', error);
            window.notify ? notify('Erreur lors de l\'ajout au panier', 'error') : alert(
                'Erreur lors de l\'ajout au panier');
        });
}

// Gestion de la recherche vendeur
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchVendeur');

    if (searchInput) {
        // Recherche en temps réel (500ms après la dernière frappe)
        let timeoutId;
        searchInput.addEventListener('input', function() {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, 500);
        });

        // Recherche sur Enter
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('filterForm').submit();
            }
        });
    }
});