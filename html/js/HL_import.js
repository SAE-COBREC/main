$(function () {
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
    $("#header").load("/partials/header.html");
    $("#footer").load("/partials/footer.html");

    // Charger les partials de notifications (toasts + modale)
    try {
        if (!document.getElementById('toast-container')) {
            const tMount = $('<div id="toast-partials" aria-hidden="true"></div>').appendTo('body');
            tMount.load('/partials/toast.html');
        }
        if (!document.getElementById('modal-overlay')) {
            const mMount = $('<div id="modal-partials" aria-hidden="true"></div>').appendTo('body');
            mMount.load('/partials/modal.html');
        }
    } catch (e) { /* silencieux */ }
});