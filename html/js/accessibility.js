document.addEventListener('DOMContentLoaded', () => {
    const themeSelector = document.getElementById('colorblind-mode');
    const htmlElement = document.documentElement;

    if (themeSelector) {
        themeSelector.addEventListener('change', () => {
            const selectedTheme = themeSelector.value;

            // 1. Appliquer localement pour l'instantanéité
            if (selectedTheme === 'default') {
                htmlElement.removeAttribute('data-theme');
            } else {
                htmlElement.setAttribute('data-theme', selectedTheme);
            }

            // 2. Sauvegarder dans LocalStorage (persistance navigateur)
            localStorage.setItem('site-theme', selectedTheme);

            // 3. Envoyer à la session PHP (persistance serveur)
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