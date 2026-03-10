// Fonction qui applique le thème sauvegardé dès le chargement
function applySavedTheme() {
    const savedMode = localStorage.getItem('theme-dalto') || 'default';
    document.documentElement.setAttribute('data-theme', savedMode);
    
    // Si le sélecteur existe sur la page actuelle, on met sa valeur à jour
    const selectDalto = document.getElementById('colorblind-mode');
    if (selectDalto) {
        selectDalto.value = savedMode;
    }
}

// On l'exécute immédiatement
applySavedTheme();

// On ajoute l'écouteur pour le changement (si le bouton est présent sur la page)
document.addEventListener('DOMContentLoaded', () => {
    const selectDalto = document.getElementById('colorblind-mode');
    if (selectDalto) {
        selectDalto.addEventListener('change', (e) => {
            const selectedMode = e.target.value;
            document.documentElement.setAttribute('data-theme', selectedMode);
            localStorage.setItem('theme-dalto', selectedMode);
        });
    }
});