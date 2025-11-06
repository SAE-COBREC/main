<?php 
$prixArt1 = 29.99;
$prixArt2 = 40.02;
?>
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Panier</title>
        <link rel="stylesheet" href="/src/styles/Panier/stylesPanier.css">
    </head>
    <body>
        <!-- BLOCK AVEC TOUS LES ARTICLES DANS LE PANIER ET LE RECAP DE LA COMMANDE-->
        <section class="articlesPrixP">
            
            <!-- CETTE DIV CONTIENT UNIQUEMENT LES ARTICLES PAS LE RECAP !! -->
            <div>
                
                <!--UN ARTICLE DANS LE PANIER-->    
                <article class="unArticleP" data-price="29.99">
                    <div class="imageArticleP">
                        <img src="https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=400" alt="Biscuits">
                    </div>
                    <div class="articleDetailP">
                        <h2 class="articleTitreP">Biscuits artisanaux</h2>
                        <p class="articleDescP">
                                                Lorem ipsum is simply dummy text of the printing and typesetting industry. Lorem ipsum 
                                                has been the industry's standard dummy text ever since the 1500s, when an unknown 
                                                printer took a galley of type and scrambled it to make a type specimen book. It has 
                                                survived not only five centuries.
                        </p>
                        <div class="basArticleP">
                            <p class="articlePrix">29.99€</p>
                            <div class="quantite">
                                <button class="btn-minus">-</button>
                                <input type="text" class="quantite-input" value="1">
                                <button class="btn-plus">+</button>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="unArticleP" data-price="40.02">
                    <div class="imageArticleP">
                        <img src="https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=400" alt="Biscuits">
                    </div>
                    <div class="articleDetailP">
                        <h2 class="articleTitreP">Cookies premium</h2>
                        <p class="articleDescP">
                                        Lorem ipsum is simply dummy text of the printing and typesetting industry. Lorem ipsum 
                                        has been the industry's standard dummy text ever since the 1500s, when an unknown 
                                        printer took a galley of type and scrambled it to make a type specimen book. It has 
                                        survived not only five centuries.
                        </p>
                        <div class="basArticleP">
                        <p class="articlePrix">40.02€</p>
                            <div class="quantite">
                                <button class="btn-minus">-</button>
                                <input type="text" class="quantite-input" value="1">
                                <button class="btn-plus">+</button>
                            </div>
                        </div>
                    </div>
                </article>

            </div>

            <!-- BLOCK DU RECAP DE LA COMMANDE -->
            <aside class="recapCommande">
                <div class="recapTete">
                    <h3 id="totalArticles">Récapitulatif (2 produits) :</h3>
                    <div id="listeProduits"></div>
                </div>
                <div class="recapTotal">
                    <h3>Prix total :</h3>
                    <h3 class="prixTotal" id="prixTotal">70.01€</h3>
                </div>
                <button class="finaliserCommande">Finaliser commande</button>
            </aside>
        </section>

        <script>

        //fonction pour mettre a jour le recap de la commande 
        function updateRecap() {
            const articles = document.querySelectorAll('.unArticleP');
            let totalPrice = 0;
            let totalItems = 0;
            let productsHTML = '';
            
            articles.forEach(article => {
                const price = parseFloat(article.dataset.price);
                const quantityInput = article.querySelector('.quantite-input');
                const quantity = parseInt(quantityInput.value) || 0;
                const titre = article.querySelector('.articleTitreP').textContent;
                
                if (quantity > 0) {
                    totalPrice += price * quantity;
                    totalItems += quantity;
                    productsHTML += `<p>${titre} <span>x${quantity}</span></p>`;
                }
            });
            
            document.getElementById('prixTotal').textContent = totalPrice.toFixed(2) + '€';
            document.getElementById('totalArticles').textContent = `Récapitulatif (${totalItems} produit${totalItems > 1 ? 's' : ''}) :`;
            document.getElementById('listeProduits').innerHTML = productsHTML;
        }

        // Gestion des boutons + et -
        document.querySelectorAll('.unArticleP').forEach(article => {
            const input = article.querySelector('.quantite-input');
            const btnPlus = article.querySelector('.btn-plus');
            const btnMinus = article.querySelector('.btn-minus');
            
            // Bouton +
            btnPlus.addEventListener('click', () => {
                let value = parseInt(input.value) || 0;
                input.value = value + 1;
                updateRecap();
            });
            
            // Bouton -
            btnMinus.addEventListener('click', () => {
                let value = parseInt(input.value) || 0;
                if (value > 0) {
                    input.value = value - 1;
                    updateRecap();
                }
            });
            
            // Gestion de la saisie manuelle
            input.addEventListener('input', (e) => {
                // Supprime tout ce qui n'est pas un chiffre
                let value = e.target.value.replace(/[^0-9]/g, '');
                
                // Convertit en nombre
                value = parseInt(value) || 0;
                
                // Empêche les valeurs négatives
                if (value < 0) value = 0;
                
                e.target.value = value;
                updateRecap();
            });
            
            // Empêche la saisie de caractères non numériques
            input.addEventListener('keypress', (e) => {
                if (!/[0-9]/.test(e.key) && e.key !== 'Backspace' && e.key !== 'Delete' && e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') {
                    e.preventDefault();
                }
            });
        });

        // Initialisation du récapitulatif au chargement
        updateRecap();
        </script>
    </body>
</html>