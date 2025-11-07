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
});