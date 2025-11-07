<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Panier</title>
        <link rel="stylesheet" href="/styles/Panier/stylesPanier.css">
        <link rel="stylesheet" href="/styles/Header/stylesHeader.css">
        <link rel="stylesheet" href="/styles/Footer/stylesFooter.css">
    </head>

    <div id="header"></div>
    <body>

        
        <!-- BLOCK AVEC TOUS LES ARTICLES DANS LE PANIER ET LE RECAP DE LA COMMANDE-->
        <section class="articlesPrixP">
            
        <?php if (0==0):?>
            
            <!-- CETTE DIV CONTIENT UNIQUEMENT LES ARTICLES PAS LE RECAP !! -->
            <div>
                
                <!--UN ARTICLE DANS LE PANIER-->    
                <article class="unArticleP" data-prix="29.99">
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
                                <button class="btn_moins">-</button>
                                <input type="text" class="quantite_input_entre" value="1">
                                <button class="btn_plus">+</button>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="unArticleP" data-prix="40.02">
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
                                <button class="btn_moins">-</button>
                                <input type="text" class="quantite_input_entre" value="1">
                                <button class="btn_plus">+</button>
                            </div>
                        </div>
                    </div>
                </article>


                <article class="unArticleP" data-prix="40.02">
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
                                <button class="btn_moins">-</button>
                                <input type="text" class="quantite_input_entre" value="1">
                                <button class="btn_plus">+</button>
                            </div>
                        </div>
                    </div>
                </article>

            </div>

            <!-- BLOCK DU RECAP DE LA COMMANDE -->
            <aside class="recapCommande">
                <div class="recapTete">
                    <h3 id="totalArticles"></h3> <!--es tremplit avec le js-->
                    <div id="listeProduits"></div> <!--es tremplit avec le js-->
                </div>
                <div class="recapTotal">
                    <h3>Prix total :</h3> <!--es tremplit avec le js-->
                    <h3 class="prixTotal" id="prixTotal"></h3>
                </div>
                <button class="finaliserCommande">Finaliser commande</button>
            </aside>

        <?php else:?>

            <img id="panierVide" src="/img/svg/panier-empty.svg"/> 
            
        <?php endif;?>  
        </section>

        
    </body>
    <div id="footer"></div>

    <script>

        //fonction pour mettre a jour le recap de la commande
        function updateRecap() {
            const articles = document.querySelectorAll('.unArticleP'); //recupere tous les articles et les mets dans une liste
            let PrixTotal = 0; 
            let nbArticles = 0;
            let produitEnHTML = ''; //initialisation du texte qui sera dans le recap de la commande (les titres d'articles)
            
            articles.forEach(article => { //pour chaque articles de la liste des articles
                const prix = parseFloat(article.dataset.prix); //récupère le prix et le mettre en float
                const quantiteEntre = article.querySelector('.quantite_input_entre'); //récupère l'input
                console.log(article);
                console.log(quantiteEntre);
                const quantite = parseInt(quantiteEntre.value); //converti en int et récupère la valeur dans l'input
                const titre = article.querySelector('.articleTitreP').textContent; //récupère le titre pour pouvoir l'affiché dans le récap de la commande
                

                if (quantite > 0) {
                    PrixTotal += prix * quantite; 
                    console.log(PrixTotal);
                    nbArticles += quantite; //pour le nombre de produit total
                    produitEnHTML += `<p>${titre} <span>x${quantite}</span></p>`; //pour ajouter dans le récap
                }
            });
            
            //remplit le prix total et arrondit à 2 après la virgule
            document.getElementById('prixTotal').textContent = PrixTotal.toFixed(2) + '€';

            //remplit le nombre d'articles total, et gère le petit s si il y a plusieurs articles
            document.getElementById('totalArticles').textContent = `Récapitulatif (${nbArticles} produit${nbArticles > 1 ? 's' : ''}) :`; 
            
            //remplit la liste des produits dans le récap
            document.getElementById('listeProduits').innerHTML = produitEnHTML;
        }

        //gestion des boutons + et -
        document.querySelectorAll('.unArticleP').forEach(article => {
            const input = article.querySelector('.quantite_input_entre');
            const btnPlus = article.querySelector('.btn_plus');
            const btnMoins = article.querySelector('.btn_moins');
            
            //bouton +
            btnPlus.addEventListener('click', () => { //quand le plus est cliqué
                let value = parseInt(input.value); 
                input.value = value + 1;
                updateRecap(); //appel de la fonction updateRecap
            });
            
            //bouton -
            btnMoins.addEventListener('click', () => { //same que bouton plus
                let value = parseInt(input.value);
                if (value > 0) { //pour ne pas passer en dessous de 0
                    input.value = value - 1;
                    updateRecap();
                }
            });
            

            //gere les cas ou le texte change autrement genre du copier colle
            input.addEventListener('input', (event) => { //event est l'évenement qui vient de se passer dans l'input

                //supprime tout ce qui n'est pas un chiffre
                // le g dans le regex sert pour dire que c'est partout dans le input g = général
                let value = event.target.value.replace(/[^0-9]/g, '');
                
                //convertit en nombre
                value = parseInt(value) || 0;
                
                event.target.value = value; //met a jour la valeur de l'input
                updateRecap(); //appele la fonction update récap
            });
            
            //empeche l’utilisateur de taper des caractères interdits comme les lettres directement.
            input.addEventListener('keypress', (event) => {
                if (!/[0-9]/.test(event.key) && event.key !== 'Backspace' && event.key !== 'Delete' && event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
                    event.preventDefault();
                }
            });
        });

        //initialisation du récap sinon il y a rien au début.
        updateRecap();
    </script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="/js/HL_import.js"></script>
</html>