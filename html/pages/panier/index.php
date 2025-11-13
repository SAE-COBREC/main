<?php 
include '../../../config.php';

session_start();
$id_client = $_SESSION['id'] ;


$pdo->exec("SET search_path TO cobrec1");

$requetePanier = "SELECT p_nom, p_description, p_prix, i_lien, id_client
                FROM _contient
                JOIN _produit ON _produit.id_produit = _contient.id_produit
                JOIN _represente_produit ON _produit.id_produit = _represente_produit.id_produit
                JOIN _image ON _represente_produit.id_image = _image.id_image
                JOIN _panier_commande ON _panier_commande.id_panier = _contient.id_panier
                WHERE _contient.id_client = ". $id_client . ";"; //gestion de la requête en fonction du login à terminer

$stmt = $pdo->query($requetePanier);

$articles = $stmt->fetchAll(PDO::FETCH_ASSOC); //récup les données et les stock dans une liste
?>
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

    <?php
    include __DIR__ . '/../../partials/header.html';
    ?>
    <body>

        
        <!-- BLOCK AVEC TOUS LES ARTICLES DANS LE PANIER ET LE RECAP DE LA COMMANDE-->
        <section class="articlesPrixP">
            
        <?php if (count($articles) > 0):?>
            
            <!-- CETTE DIV CONTIENT UNIQUEMENT LES ARTICLES PAS LE RECAP !! -->
            <div>
                
                <!--UN ARTICLE DANS LE PANIER-->
                <?php foreach ($articles as $article): ?> 
                    <article class="unArticleP" data-prix="<?php echo number_format($article['p_prix'], 2, '.')?>"
                                                data-stock="<?php echo intval($article['p_stock'])?>">
                        <div class="imageArticleP">
                            <img src="<?php echo htmlspecialchars($article['i_lien']) ?>"
                                alt="<?php echo htmlspecialchars($article['p_nom']) ?>" 
                                title="<?php echo htmlspecialchars($article['p_nom'])?>">
                        </div>
                        <div class="articleDetailP">
                            <h2 class="articleTitreP"><?php echo htmlspecialchars($article['p_nom'])?></h2>
                            <p class="articleDescP"><?php echo htmlspecialchars($article['p_description'])?></p>
                            <div class="basArticleP">
                                <p class="articlePrix"><?php echo  number_format($article['p_prix'], 2, '.')?>€</p>
                                <div class="quantite">

                                    <!-- FORMULAIRE POUR SUPPRIMER UN ARTICLE DU PANIER-->
                                    <form class="suppArt" method="POST" action="/pages/panier/supprimerArticle.php">
                                        <input type="hidden" name="id_produit" value="<?php echo $article['id_produit']; ?>"> <!--stock l'id du produit pour la suppression-->
                                        <input type="hidden" name="id_panier" value="<?php echo $id_panier; ?>"> <!--stock l'id du panier pour la suppression-->
                                        <!--bouton pour envoyer le formulaire-->
                                        <button type="submit" id="supprimerArticle" class="supprimerArticle"><img src="/img/svg/poubelle.svg" alt="Supprimer"/></button>
                                    </form>
                                    <button class="btn_moins">-</button>
                                    <input type="text" class="quantite_input_entre" value="1">
                                    <button class="btn_plus">+</button>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach;?>
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
                
                <!--FORMULAIRE POUR VIDER LE PANIER-->
                <form id="formViderPanier" method="POST" action="/pages/panier/viderPanier.php">
                    <input type="hidden" name="id_panier_a_vider" value="<?php echo $id_panier; ?>">
                    <button type="submit" id="viderPanier" class>Vider le panier</button>
                </form>
            </aside>

        <?php else:?>

        <div id="panierVide">
            <img id="panierVide" src="/img/svg/panier-empty.svg"/> 
            <a href="/" id="retourAchat">Continuer mes achats</a>
        </div>

        <?php endif;?>  
        </section>

        
    </body>
    <?php
    include __DIR__ . '/../../partials/footer.html';
    ?>

    <!--vérifie qu'il y ait minimun un élément dans le panier pour envoyer le javascript ça permet d'éviter les erreurs de truc non trouvé-->
    <?php if (count($articles) > 0):?>
        <script>
            function verifieStockMax(val, stock){
                console.log(val, stock);
                if (val > stock){
                    alert(`la quantité que vous avez saisie est supérieur au stock (${stock}).`);
                    return stock;
                }
                return val;
            }
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
                const formSupp = article.querySelector('.suppArt');
                const stockMax = parseInt(article.dataset.stock); //récup la quantité max en stock
                

                //bouton +
                btnPlus.addEventListener('click', () => { //quand le plus est cliqué
                    let value = parseInt(input.value); 
                    value += 1;
                    value = verifieStockMax(value, stockMax);
                    input.value = value;
                    updateRecap(); //appel de la fonction updateRecap
                });
                
                //bouton -
                btnMoins.addEventListener('click', () => { //same que bouton plus
                    let value = parseInt(input.value);
                    if  (value > 1){ //l'utilisateur ne dépasse pas 1 il sera jamais a 0
                        input.value = value - 1;
                        updateRecap();
                    }
                });
                
                formSupp.addEventListener('submit', (event) => {
                    if (!confirm(`Souhaitez-vous vraiment supprimer l'article du panier ?`)) {
                        event.preventDefault(); //empeche la soumission du formulaire
                    }
                });

                //gere les cas ou le texte change autrement genre du copier colle
                input.addEventListener('input', (event) => { //event est l'évenement qui vient de se passer dans l'input

                    //supprime tout ce qui n'est pas un chiffre
                    // le g dans le regex sert pour dire que c'est partout dans le input g = général
                    let value = event.target.value.replace(/[^0-9]/g, '');
                    
                    //convertit en nombre ou met a 1 si rien si l'entré est vide ou invalide
                    value = parseInt(value) || 1;
                    //vérifie si quantité ne dépasse pas le max du stock
                    value = verifieStockMax(value, stockMax);
                    
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
            
            //confirmation pour vider le panier
            const formViderPanier = document.getElementById('formViderPanier');

            formViderPanier.addEventListener('submit', (event) => {
                if (!confirm(`Souhaitez-vous vraiment vider votre panier ?`)) {
                    event.preventDefault(); //empeche l'envoi du formulaire si annuler est cliqué
                }
            });

            //initialisation du récap sinon il y a rien au début.
            updateRecap();
        </script>
    <?php endif;?>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="/js/HL_import.js"></script>
</html>