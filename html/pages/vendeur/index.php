<?php
// ============================================
// CONFIGURATION ET INITIALISATION
// ============================================

//démarre la session utilisateur
session_start();

//charge le fichier de connexion à la base de données
require_once __DIR__ . '/../../selectBDD.php';
//charge le fichier contenant toutes les fonctions personnalisées
require_once __DIR__ . '/../../pages/fonctions.php';

//crée la connexion à la base de données
$connexionBaseDeDonnees = $pdo;
//définit le schéma de base de données à utiliser
$connexionBaseDeDonnees->exec("SET search_path TO cobrec1");

// ============================================
// RÉCUPÉRATION DU PARAMÈTRE VENDEUR
// ============================================

//récupère la dénomination du vendeur passée en paramètre GET
$denominationVendeur = trim($_GET['denomination'] ?? '');

//redirige vers l'accueil si aucune dénomination n'est fournie
if ($denominationVendeur === '') {
    header('Location: /');
    exit;
}

// ============================================
// CHARGEMENT DES INFORMATIONS DU VENDEUR
// ============================================

//prépare la requête pour récupérer les informations complètes du vendeur
$sqlVendeur = "
    SELECT
        v.id_vendeur,
        v.denomination,
        v.raison_sociale,
        v.siren,
        c.email,
        c.num_telephone  AS telephone,
        a.a_ville        AS ville,
        a.a_code_postal  AS code_postal,
        a.a_adresse      AS adresse,
        a.a_numero       AS numero,
        i.i_lien         AS image
    FROM cobrec1._vendeur v
    LEFT JOIN cobrec1._compte            c  ON v.id_compte = c.id_compte
    LEFT JOIN cobrec1._adresse           a  ON v.id_compte = a.id_compte
    LEFT JOIN cobrec1._represente_compte rc ON v.id_compte = rc.id_compte
    LEFT JOIN cobrec1._image             i  ON rc.id_image = i.id_image
    WHERE v.denomination ILIKE :denomination
    LIMIT 1
";

//exécute la requête pour trouver le vendeur
$stmtVendeur = $connexionBaseDeDonnees->prepare($sqlVendeur);
$stmtVendeur->execute([':denomination' => $denominationVendeur]);
//récupère les informations du vendeur
$informationsVendeur = $stmtVendeur->fetch(PDO::FETCH_ASSOC);

//affiche une page 404 si le vendeur est introuvable
if (!$informationsVendeur) {
    http_response_code(404);
    include __DIR__ . '/../produit/not-found.php';
    exit;
}

// ============================================
// CHARGEMENT DES PRODUITS DU VENDEUR
// ============================================

//récupère tous les produits associés à ce vendeur
$listeProduits = ProduitDenominationVendeur($connexionBaseDeDonnees, $informationsVendeur['denomination']);
//compte le nombre de produits en ligne
$nombreProduits = count($listeProduits);

// ============================================
// CALCUL DES STATISTIQUES DU VENDEUR
// ============================================

//initialise la note moyenne et le nombre d'avis
$noteMoyenneVendeur = 0.0;
$nombreAvisTotal    = 0;

//calcule les statistiques seulement si le vendeur a des produits
if ($nombreProduits > 0) {
    //prépare la requête pour récupérer la note moyenne et le nombre d'avis
    $sqlStatistiques = "
        SELECT
            ROUND(COALESCE(AVG(a.a_note), 0)::numeric, 1) AS note,
            COUNT(a.id_avis)                               AS nb
        FROM cobrec1._avis a
        INNER JOIN cobrec1._produit p ON a.id_produit = p.id_produit
        WHERE p.id_vendeur = :idVendeur
          AND a.a_note IS NOT NULL
    ";

    //exécute la requête de statistiques
    $stmtStatistiques = $connexionBaseDeDonnees->prepare($sqlStatistiques);
    $stmtStatistiques->execute([':idVendeur' => $informationsVendeur['id_vendeur']]);
    //récupère les résultats
    $statistiques = $stmtStatistiques->fetch(PDO::FETCH_ASSOC);

    //extrait la note moyenne et le nombre d'avis
    $noteMoyenneVendeur = (float) ($statistiques['note'] ?? 0);
    $nombreAvisTotal    = (int)   ($statistiques['nb']   ?? 0);
}

// ============================================
// GESTION DE LA SESSION ET DU PANIER
// ============================================

//récupère l'ID du client s'il est connecté
$idClient = $_SESSION['idClient'] ?? null;
//initialise l'ID du panier
$idPanier = null;

//vérifie si le client est connecté ou pas
if ($idClient === null) {
    //client non connecté : utilise un panier temporaire
    if (!isset($_SESSION['panierTemp'])) {
        //crée un panier temporaire vide
        $_SESSION['panierTemp'] = [];
    }
} else {
    //client connecté : cherche son panier dans la base

    //prépare la requête pour trouver le panier en cours
    $sqlPanierClient = "
        SELECT id_panier
        FROM _panier_commande
        WHERE timestamp_commande IS NULL AND id_client = :idClient
    ";

    //exécute la requête pour trouver le panier
    $stmtPanier = $connexionBaseDeDonnees->prepare($sqlPanierClient);
    $stmtPanier->execute([':idClient' => $idClient]);
    //récupère le résultat
    $panier = $stmtPanier->fetch(PDO::FETCH_ASSOC);

    //vérifie si un panier existe
    if ($panier) {
        //récupère l'ID du panier existant
        $idPanier = (int) $panier['id_panier'];
    } else {
        //aucun panier trouvé : crée un nouveau panier
        $sqlCreerPanier = "
            INSERT INTO _panier_commande (id_client, timestamp_commande)
            VALUES (:idClient, NULL)
            RETURNING id_panier
        ";

        //insère le nouveau panier et récupère son ID
        $stmtCreerPanier = $connexionBaseDeDonnees->prepare($sqlCreerPanier);
        $stmtCreerPanier->execute([':idClient' => $idClient]);
        $idPanier = (int) $stmtCreerPanier->fetchColumn();
    }

    //sauvegarde l'ID du panier dans la session
    $_SESSION['panierEnCours'] = $idPanier;
    //transfère les produits du panier temporaire vers la base
    transfererPanierTempVersBDD($connexionBaseDeDonnees, $idPanier);
}

// ============================================
// TRAITEMENT AJAX : AJOUT AU PANIER
// ============================================

//vérifie si c'est une requête d'ajout au panier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter_panier') {
    //définit le type de réponse en JSON
    header('Content-Type: application/json');

    //récupère l'ID du produit à ajouter
    $idProduit = $_POST['idProduit'] ?? null;
    //récupère la quantité demandée
    $quantite = (int) ($_POST['quantite'] ?? 1);

    //vérifie que l'ID produit est présent
    if (!$idProduit) {
        echo json_encode(['success' => false, 'message' => 'ID produit manquant']);
        exit;
    }

    //essaie d'ajouter le produit au panier
    try {
        //vérifie si le client est connecté
        if ($idClient === null) {
            //ajoute le produit au panier temporaire en session
            $resultat = ajouterArticleSession($connexionBaseDeDonnees, $idProduit, $quantite);
        } else {
            //vérifie qu'un panier existe
            if (!$idPanier) {
                echo json_encode(['success' => false, 'message' => 'Aucun panier en cours']);
                exit;
            }
            //ajoute le produit au panier en base de données
            $resultat = ajouterArticleBDD($connexionBaseDeDonnees, $idProduit, $idPanier, $quantite);
        }
        //renvoie le résultat en JSON
        echo json_encode($resultat);
    } catch (Exception $e) {
        //capture les erreurs et les renvoie
        echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
    }
    //arrête le script après traitement
    exit;
}

// ============================================
// FONCTIONS D'AFFICHAGE
// ============================================

//génère le HTML des étoiles de notation pour le vendeur
function afficherEtoilesVendeur(float $note, int $taille = 16): string
{
    //initialise la chaîne HTML
    $html = '';

    //boucle sur les 5 étoiles
    for ($i = 1; $i <= 5; $i++) {
        //détermine le type d'étoile selon la note
        if ($note >= $i) {
            $typeEtoile = 'full';
        } elseif ($note >= $i - 0.5) {
            $typeEtoile = 'alf';
        } else {
            $typeEtoile = 'empty';
        }
        //ajoute l'image de l'étoile au HTML
        $html .= '<img src="/img/svg/star-' . $typeEtoile . '.svg" alt="Etoile" width="' . $taille . '">';
    }

    return $html;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($informationsVendeur['denomination']) ?> – Alizon</title>

    <!--charge l'icône du site-->
    <link rel="icon" type="image/png" href="/img/favicon.svg">

    <!--charge les feuilles de style CSS-->
    <link rel="stylesheet" href="/styles/Header/stylesHeader.css">
    <link rel="stylesheet" href="/styles/Footer/stylesFooter.css">
    <link rel="stylesheet" href="/styles/Vendeur/style.css">
</head>

<body>
    <?php
    //inclut l'en-tête de la page
    include __DIR__ . '/../../partials/header.php';
    ?>

    <!--conteneur principal de la page vendeur-->
    <div class="vendeur-page">

        <!--lien de retour vers l'accueil-->
        <a href="/" class="btn-retour">
            <img src="/img/svg/back.svg" alt="" width="16" onerror="this.style.display='none'">← Retour à l'accueil
        </a>

        <!--carte de profil du vendeur-->
        <div class="vendeur-profil-card">

            <!--affiche l'avatar du vendeur ou un placeholder-->
            <?php if (!empty($informationsVendeur['image'])): ?>
            <img class="vendeur-profil-avatar" src="<?= htmlspecialchars($informationsVendeur['image']) ?>"
                alt="<?= htmlspecialchars($informationsVendeur['denomination']) ?>">
            <?php else: ?>
            <div class="vendeur-profil-avatar-placeholder">
                <img src="/img/svg/market.svg" alt="Vendeur">
            </div>
            <?php endif; ?>

            <!--informations textuelles du vendeur-->
            <div class="vendeur-profil-info">

                <!--nom de la dénomination du vendeur-->
                <h1><?= htmlspecialchars($informationsVendeur['denomination']) ?></h1>

                <!--affiche la raison sociale si elle existe-->
                <?php if (!empty($informationsVendeur['raison_sociale'])): ?>
                <p class="vendeur-profil-raison"><?= htmlspecialchars($informationsVendeur['raison_sociale']) ?></p>
                <?php endif; ?>

                <!--affiche la note moyenne si elle existe-->
                <?php if ($noteMoyenneVendeur > 0): ?>
                <div class="vendeur-profil-note">
                    <!--affiche les étoiles de la note moyenne-->
                    <?= afficherEtoilesVendeur($noteMoyenneVendeur, 18) ?>
                    <!--affiche la valeur numérique de la note-->
                    <strong><?= number_format($noteMoyenneVendeur, 1, ',', '') ?>/5</strong>
                    <!--affiche le nombre d'avis-->
                    <span style="color:#9ca3af">(<?= $nombreAvisTotal ?> avis)</span>
                </div>
                <?php endif; ?>

                <!--affiche la localisation du vendeur si elle existe-->
                <?php if (!empty($informationsVendeur['ville'])): ?>
                <div class="vendeur-profil-location">
                    <img src="/img/svg/location.svg" alt="" width="14" onerror="this.style.display='none'">
                    <!--affiche le code postal s'il est disponible-->
                    <?php if (!empty($informationsVendeur['code_postal'])): ?>
                    <?= htmlspecialchars($informationsVendeur['code_postal']) ?> –
                    <?php endif; ?>
                    <?= htmlspecialchars($informationsVendeur['ville']) ?>
                </div>
                <?php endif; ?>

                <!--chips de statistiques rapides-->
                <div class="vendeur-profil-stats">
                    <!--chip affichant le nombre de produits en ligne-->
                    <div class="vendeur-stat-chip">
                        <img src="/img/svg/market.svg" alt="">
                        <?= $nombreProduits ?> produit<?= $nombreProduits > 1 ? 's' : '' ?> en ligne
                    </div>
                    <!--affiche le numéro SIREN si disponible-->
                    <?php if (!empty($informationsVendeur['siren'])): ?>
                    <div class="vendeur-stat-chip">
                        SIREN : <?= htmlspecialchars($informationsVendeur['siren']) ?>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <!--titre de la section produits-->
        <p class="vendeur-produits-titre">
            Produits de <?= htmlspecialchars($informationsVendeur['denomination']) ?>
            <span>(<?= $nombreProduits ?>)</span>
        </p>

        <!--affiche un message si le vendeur n'a aucun produit-->
        <?php if (empty($listeProduits)): ?>
        <div class="vendeur-no-products">
            <p>Ce vendeur n'a aucun produit en ligne pour le moment.</p>
        </div>
        <?php else: ?>

        <!--grille des produits du vendeur-->
        <div class="product-grid">
            <!--boucle sur tous les produits du vendeur-->
            <?php foreach ($listeProduits as $produitCourant):
                //vérifie si le produit est en rupture de stock
                $estEnRupture = $produitCourant['p_stock'] <= 0;
                //récupère le pourcentage de réduction
                $discount = (float) ($produitCourant['reduction_pourcentage'] ?? 0);
                //vérifie s'il y a une réduction
                $possedePourcentageRemise = $discount > 0;
                //calcule le prix après réduction
                $prixDiscount = $possedePourcentageRemise ? $produitCourant['p_prix'] * (1 - $discount / 100) : $produitCourant['p_prix'];
                //calcule le prix final TTC
                $prixFinal = calcPrixTVA($produitCourant['tva'], $prixDiscount);
                //calcule le prix original TTC
                $prixOriginalTTC = calcPrixTVA($produitCourant['tva'], $produitCourant['p_prix']);
                //arrondit la note moyenne
                $noteArrondie = (int) round($produitCourant['note_moyenne'] ?? 0);
                //vérifie si le produit est en promotion
                $estEnPromotion = !empty($produitCourant['estenpromo']);
                //construit l'URL de l'image du produit
                $urlImage = str_replace('html/img/photo', '/img/photo', $produitCourant['image_url'] ?? '/img/default-product.jpg');
                //récupère l'origine du produit
                $origineProduit = recupOrigineProduit($connexionBaseDeDonnees, $produitCourant['id_produit']);
            ?>
            <!--carte de produit cliquable-->
            <article
                class="<?= $estEnRupture ? 'produit-rupture' : '' ?> <?= $estEnPromotion ? 'produit-promotion' : '' ?>"
                onclick="window.location.href='/pages/produit/index.php?id=<?= $produitCourant['id_produit'] ?>'">

                <div>
                    <div>
                        <!--image du produit-->
                        <img src="<?= htmlspecialchars($urlImage) ?>"
                            alt="<?= htmlspecialchars($produitCourant['p_nom']) ?>"
                            class="<?= $estEnRupture ? 'image-rupture' : '' ?>">
                    </div>
                    <!--affiche le badge de réduction s'il y en a une-->
                    <?php if ($possedePourcentageRemise): ?>
                    <span class="badge-reduction">-<?= round($discount) ?>%</span>
                    <?php endif; ?>
                    <!--affiche le badge Bretagne si le produit est d'origine bretonne-->
                    <?php if ($origineProduit === 'Bretagne'): ?>
                    <span class="badge-bretagne"><img src="/img/png/badge-bretagne.png" alt="Bretagne"></span>
                    <?php endif; ?>
                    <!--affiche le message de rupture de stock si nécessaire-->
                    <?php if ($estEnRupture): ?>
                    <div class="rupture-stock">Rupture de stock</div>
                    <?php endif; ?>
                </div>

                <div>
                    <!--nom du produit-->
                    <h3><?= htmlspecialchars($produitCourant['p_nom']) ?></h3>

                    <div>
                        <span>
                            <!--affiche les étoiles de notation-->
                            <?php for ($i = 1; $i <= 5; $i++):
                                if ($noteArrondie >= $i)           $s = 'full';
                                elseif ($noteArrondie >= $i - 0.5) $s = 'alf';
                                else                               $s = 'empty';
                            ?>
                            <img src="/img/svg/star-<?= $s ?>.svg" alt="Etoile" width="20">
                            <?php endfor; ?>
                        </span>
                        <!--affiche le nombre d'avis-->
                        <span>(<?= $produitCourant['nombre_avis'] ?? 0 ?>)</span>
                    </div>

                    <div>
                        <span>
                            <!--affiche le prix barré s'il y a une réduction-->
                            <?php if ($possedePourcentageRemise): ?>
                            <span style="text-decoration:line-through;color:#999;margin-right:5px;font-size:1.2em;">
                                <?= number_format($prixOriginalTTC, 2, ',', ' ') ?>€
                            </span>
                            <?php endif; ?>
                        </span>
                        <!--affiche le prix final TTC-->
                        <span><?= number_format($prixFinal, 2, ',', ' ') ?>€</span>
                    </div>

                    <div class="product-bottom">
                        <!--informations du vendeur (non cliquable sur cette page)-->
                        <div class="vendeur-info" style="cursor:default;">
                            <img src="/img/svg/market.svg" alt="Vendeur">
                            <span><?= htmlspecialchars($informationsVendeur['denomination']) ?></span>
                        </div>
                        <!--bouton pour ajouter au panier-->
                        <button <?= $estEnRupture ? 'disabled' : '' ?>
                            onclick="event.stopPropagation(); ajouterAuPanier(<?= $produitCourant['id_produit'] ?>)">
                            <?= $estEnRupture ? 'Indisponible' : '<img src="/img/svg/panier.svg" alt="Panier" class="panier-icon"> Ajouter au panier' ?>
                        </button>
                    </div>
                </div>

            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
    <!--/vendeur-page-->

    <?php
    //inclut le pied de page
    include __DIR__ . '/../../partials/footer.html';
    //inclut le système de notifications toast
    include __DIR__ . '/../../partials/toast.html';
    //inclut les modales
    include __DIR__ . '/../../partials/modal.html';
    ?>

    <!--charge le script pour les notifications-->
    <script src="/js/notifications.js"></script>

    <script>
    //fonction d'ajout au panier — identique à la page d'accueil
    function ajouterAuPanier(idProduit) {
        //crée un objet pour envoyer les données au serveur
        var formData = new FormData();
        formData.append('action', 'ajouter_panier');
        formData.append('idProduit', idProduit);
        formData.append('quantite', 1);

        //envoie la requête AJAX au serveur
        fetch(window.location.href, {
                method: 'POST',
                body: formData,
                noLoader: true
            })
            //gère la réponse de manière robuste (texte -> tentative JSON)
            .then(async function(response) {
                var text = await response.text();
                try {
                    var data = JSON.parse(text);
                    return data;
                } catch (err) {
                    //affiche la réponse brute pour débogage si elle n'est pas du JSON valide
                    console.error('Réponse serveur non JSON:', text);
                    window.notify ? notify('Erreur serveur: réponse invalide', 'error') : alert(
                        'Erreur serveur: réponse invalide');
                    throw new Error('Invalid JSON response');
                }
            })
            //traite la réponse JSON du serveur
            .then(function(data) {
                var message = data && data.message ? data.message : 'Réponse inconnue';
                var type = data && data.success ? 'success' : 'error';
                window.notify ? notify(message, type) : alert((type === 'success' ? '✓ ' : '✗ ') + message);
            })
            .catch(function(error) {
                console.error('Erreur ajout au panier:', error);
                if (!error.message || error.message === 'Invalid JSON response') return;
                window.notify ? notify('Erreur lors de l\'ajout au panier', 'error') : alert(
                    'Erreur lors de l\'ajout au panier');
            });
    }
    </script>

</body>

</html>