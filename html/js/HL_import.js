document.addEventListener('DOMContentLoaded', () => {
    /**
     * Charge les partials d'en-tête et de pied de page
     * @returns {void}
     * @description Insère les fichiers HTML partiels du header et du footer
     * dans les éléments correspondant de la page.
     * @example
     * // Cette fonction est appelée au chargement du document (document ready)
     * // Exemples d'éléments où les partials seront insérés :
     * // <div id="header"></div>
     * // <div id="footer"></div>
     */
    const loadPartial = async (target, url) => {
        const element = typeof target === 'string' ? document.querySelector(target) : target;
        if (!element) return;
        try {
            const response = await fetch(url);
            if (response.ok) {
                element.innerHTML = await response.text();
            }
        } catch (error) {
            console.error('Erreur chargement partial:', url, error);
        }
    };

    loadPartial("#header", "/partials/header.php");
    loadPartial("#footer", "/partials/footer.html");

    // Charger les partials de notifications (toasts + modale)
    try {
        if (!document.getElementById('toast-container')) {
            const tMount = document.createElement('div');
            tMount.id = 'toast-partials';
            tMount.setAttribute('aria-hidden', 'true');
            document.body.appendChild(tMount);
            loadPartial(tMount, '/partials/toast.html');
        }
        if (!document.getElementById('modal-overlay')) {
            const mMount = document.createElement('div');
            mMount.id = 'modal-partials';
            mMount.setAttribute('aria-hidden', 'true');
            document.body.appendChild(mMount);
            loadPartial(mMount, '/partials/modal.html');
        }
    } catch (e) { /* silencieux */ }
});