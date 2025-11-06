$(function () {
    /**
     * Load header and footer partials
     * @returns {void}
     * @description Loads the header and footer HTML partials into the respective elements.
     * @example
     * // This function is called on document ready
     * <link rel="stylesheet" href="path/to/this/style_for_header_and_for_footer.css">
     * <script src="path/to/this/script.js"></script>
     * <div id="header"></div>
     * <div id="footer"></div>
     */
    $("#header").load("/public/partials/header.html");
    $("#footer").load("/public/partials/footer.html");
});