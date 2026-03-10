document.addEventListener('DOMContentLoaded', () => {
    const themeSelector = document.getElementById('colorblind-mode');
    const htmlElement = document.documentElement; // Cible la balise <html>

    // 1. Récupérer le thème sauvegardé (si existant)
    const savedTheme = localStorage.getItem('site-theme') || 'default';
    
    // Fonction pour appliquer le thème
    const applyTheme = (theme) => {
        if (theme === 'default') {
            htmlElement.removeAttribute('data-theme');
        } else {
            htmlElement.setAttribute('data-theme', theme);
        }
    };

    // 2. Appliquer le thème immédiatement au chargement
    applyTheme(savedTheme);

    // 3. Si le sélecteur existe sur la page actuelle (ex: page profil)
    if (themeSelector) {
        themeSelector.value = savedTheme; // Synchronise le menu avec le stockage

        themeSelector.addEventListener('change', () => {
            const selectedTheme = themeSelector.value;
            applyTheme(selectedTheme);
            // Sauvegarder le choix pour les autres pages
            localStorage.setItem('site-theme', selectedTheme);
        });
    }
});