document.addEventListener('DOMContentLoaded', () => {
    const themeSelector = document.getElementById('colorblind-mode');
    const htmlElement = document.documentElement;

    // --- NOUVEAU : Appliquer le thème au chargement ---
    // On regarde d'abord le localStorage, sinon on prend l'attribut déjà mis par PHP
    const savedTheme = localStorage.getItem('site-theme') || htmlElement.getAttribute('data-theme') || 'default';
    
    if (savedTheme !== 'default') {
        htmlElement.setAttribute('data-theme', savedTheme);
    }

    if (themeSelector) {
        // Synchronise le menu déroulant avec le thème actif
        themeSelector.value = savedTheme;

        themeSelector.addEventListener('change', () => {
            const selectedTheme = themeSelector.value;

            // 1. Appliquer localement pour l'instantanéité
            if (selectedTheme === 'default') {
                htmlElement.removeAttribute('data-theme');
            } else {
                htmlElement.setAttribute('data-theme', selectedTheme);
            }

            // 2. Sauvegarder dans LocalStorage
            localStorage.setItem('site-theme', selectedTheme);

            // 3. Envoyer à la session PHP
            const formData = new FormData();
            formData.append('theme', selectedTheme);

            fetch('/js/set_theme.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => console.log('Session mise à jour :', data))
            .catch(error => console.error('Erreur session :', error));
        });
    }
});