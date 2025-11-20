<?php 

//fonction pour charger tous les produits depuis la base de données
function chargerProduitsBDD($pdo)
{
    $listeProduits = [];
    $listeCategories = [];

    try {
        //requête SQL pur récupérer tous les produits avec leurs informations
        $requeteSQL = "
        SELECT 
            DISTINCT ON (p.id_produit)
            p.id_produit,
            p.p_nom,
            p.p_description,
            p.p_prix,
            p.p_stock,
            p.p_note as note_moyenne,
            p.p_nb_ventes,
            p.p_statut,
            COALESCE(r.reduction_pourcentage, 0) as pourcentage_reduction,
            COALESCE(avis.nombre_avis, 0) as nombre_avis,
            (SELECT STRING_AGG(cp.nom_categorie, ', ') 
                FROM _fait_partie_de fpd 
                JOIN _categorie_produit cp ON fpd.id_categorie = cp.id_categorie
                WHERE fpd.id_produit = p.id_produit) as categories,
            (SELECT i.i_lien 
                FROM _represente_produit rp 
                JOIN _image i ON rp.id_image = i.id_image
                WHERE rp.id_produit = p.id_produit 
                LIMIT 1) as image_url
        FROM _produit p
        LEFT JOIN _en_reduction er ON p.id_produit = er.id_produit
        LEFT JOIN _reduction r ON er.id_reduction = r.id_reduction 
        LEFT JOIN (
            SELECT id_produit, COUNT(*) as nombre_avis 
            FROM _avis 
            GROUP BY id_produit
        ) avis ON p.id_produit = avis.id_produit WHERE p.p_statut = 'En ligne'
    ";

        $requetePrepare = $pdo->query($requeteSQL);
        $listeProduits = $requetePrepare->fetchAll(PDO::FETCH_ASSOC);

        //requête pour compter les produits par catégorie
        $sqlCategories = "
        SELECT cp.nom_categorie as category, 
                COUNT(DISTINCT p.id_produit) as count
        FROM _produit p
        JOIN _fait_partie_de fpd ON p.id_produit = fpd.id_produit
        JOIN _categorie_produit cp ON fpd.id_categorie = cp.id_categorie
        WHERE p.p_statut = 'En ligne'
        GROUP BY cp.nom_categorie
    ";

        $stmtCategories = $pdo->query($sqlCategories);
        $categoriesResult = $stmtCategories->fetchAll(PDO::FETCH_ASSOC);

        foreach ($categoriesResult as $cat) {
            $listeCategories[$cat['category']] = $cat['count'];
        }

    } catch (Exception $e) {
        echo "<p style='color: red;'>Erreur lors du chargement des produits : " . $e->getMessage() . "</p>";
    }

    return ['produits' => $listeProduits, 'categories' => $listeCategories];
}

//fonction pour ajouter un article au panier dans la BDD
function ajouterArticleBDD($pdo, $idProduit, $panier, $quantite = 1)
{
    try {
        //récupérer les informations du produit (prix, TVA, frais de port, remise)
        $sqlProduit = "
            SELECT 
                p.p_prix, 
                p.p_frais_de_port, 
                p.p_stock,
                COALESCE(t.montant_tva, 0) as tva,
                COALESCE(r.reduction_pourcentage, 0) as pourcentage_reduction
            FROM _produit p
            LEFT JOIN _tva t ON p.id_tva = t.id_tva
            LEFT JOIN _en_reduction er ON p.id_produit = er.id_produit
            LEFT JOIN _reduction r ON er.id_reduction = r.id_reduction
            WHERE p.id_produit = :idProduit
        ";

        $stmtProduit = $pdo->prepare($sqlProduit);
        $stmtProduit->execute([':idProduit' => $idProduit]);
        $produitCourant = $stmtProduit->fetch(PDO::FETCH_ASSOC);

        if (!$produitCourant) {
            return ['success' => false, 'message' => 'Produit introuvable'];
        }

        //normaliser la quantité demandée
        $quantite = (int) $quantite;
        if ($quantite < 1) {
            $quantite = 1;
        }

        //calculer le prix avec remise
        $prixUnitaire = $produitCourant['p_prix'];
        $remiseUnitaire = ($produitCourant['pourcentage_reduction'] / 100) * $prixUnitaire;
        $fraisDePort = $produitCourant['p_frais_de_port'];
        $tva = $produitCourant['tva'];
        $quantiteEnStock = (int) ($produitCourant['p_stock'] ?? 0);

        //vérifier si l'article existe déjà dans le panier
        $sqlCheck = "SELECT quantite FROM _contient WHERE id_produit = :idProduit AND id_panier = :idPanier";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([
            ':idProduit' => $idProduit,
            ':idPanier' => $panier
        ]);

        $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        $quantiteExistante = $existe ? (int) $existe['quantite'] : 0;
        $disponible = max(0, $quantiteEnStock - $quantiteExistante);

        if ($disponible <= 0) {
            return ['success' => false, 'message' => 'Stock insuffisant: quantité maximale déjà atteinte dans votre panier'];
        }

        //quantité réellement ajoutée (ne dépasse pas le disponible)
        $aAjouter = min($quantite, $disponible);

        if ($existe) {
            //si l'article existe déjà, augmenter la quantité
            $sqlUpdate = "UPDATE _contient SET quantite = quantite + :quantite WHERE id_produit = :idProduit AND id_panier = :idPanier";
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->execute([
                ':quantite' => $aAjouter,
                ':idProduit' => $idProduit,
                ':idPanier' => $panier
            ]);
            if ($aAjouter < $quantite) {
                return ['success' => true, 'message' => 'Seuls ' . $aAjouter . ' article(s) ont pu être ajouté(s) (stock limité).'];
            }
            return ['success' => true, 'message' => 'Quantité mise à jour dans le panier'];
        } else {
            //sinon, insérer un nouvel article avec toutes les informations
            $sqlInsert = "
                INSERT INTO _contient 
                (id_produit, id_panier, quantite, prix_unitaire, remise_unitaire, frais_de_port, tva) 
                VALUES (:idProduit, :idPanier, :quantite, :prixUnitaire, :remiseUnitaire, :fraisDePort, :tva)
            ";
            $stmtInsert = $pdo->prepare($sqlInsert);
            $stmtInsert->execute([
                ':idProduit' => $idProduit,
                ':idPanier' => $panier,
                ':quantite' => $aAjouter,
                ':prixUnitaire' => $prixUnitaire,
                ':remiseUnitaire' => $remiseUnitaire,
                ':fraisDePort' => $fraisDePort,
                ':tva' => $tva
            ]);
            if ($aAjouter < $quantite) {
                return ['success' => true, 'message' => 'Seuls ' . $aAjouter . ' article(s) ont pu être ajouté(s) (stock limité).'];
            }
            return ['success' => true, 'message' => 'Article ajouté au panier'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

//fonction pour ajouter un article au panier temporaire (SESSION) pour utilisateurs non connectés
function ajouterArticleSession($pdo, $idProduit, $quantite = 1)
{
    try {
        //récupérer les informations du produit (prix, TVA, frais de port, remise, nom, description, image)
        $sqlProduit = "
            SELECT 
            p.p_nom,
            p.p_description,
            p.p_prix, 
            p.p_frais_de_port, 
            p.p_stock,
            COALESCE(t.montant_tva, 0) as tva,
            COALESCE(r.reduction_pourcentage, 0) as pourcentage_reduction,
            (SELECT i.i_lien
                FROM _represente_produit rp 
                JOIN _image i ON rp.id_image = i.id_image
                WHERE rp.id_produit = p.id_produit 
                LIMIT 1) as image_url,
            (SELECT i.i_alt
                FROM _represente_produit rp 
                JOIN _image i ON rp.id_image = i.id_image
                WHERE rp.id_produit = p.id_produit 
                LIMIT 1) as image_alt,
            (SELECT i.i_title
                FROM _represente_produit rp 
                JOIN _image i ON rp.id_image = i.id_image
                WHERE rp.id_produit = p.id_produit 
                LIMIT 1) as image_title
        FROM _produit p
        LEFT JOIN _tva t ON p.id_tva = t.id_tva
        LEFT JOIN _en_reduction er ON p.id_produit = er.id_produit
        LEFT JOIN _reduction r ON er.id_reduction = r.id_reduction
        WHERE p.id_produit = :idProduit
        ";

        $stmtProduit = $pdo->prepare($sqlProduit);
        $stmtProduit->execute([':idProduit' => $idProduit]);
        $produitCourant = $stmtProduit->fetch(PDO::FETCH_ASSOC);

        if (!$produitCourant) {
            return ['success' => false, 'message' => 'Produit introuvable'];
        }

        //normaliser la quantité demandée
        $quantite = (int) $quantite;
        if ($quantite < 1) {
            $quantite = 1;
        }

        //calculer le prix avec remise
        $prixUnitaire = $produitCourant['p_prix'];
        $stock = $produitCourant['p_stock'];
        $fraisDePort = $produitCourant['p_frais_de_port'];
        $tva = $produitCourant['tva'];
        $quantiteEnStock = (int) ($produitCourant['p_stock'] ?? 0);

        //initialiser le panier temporaire s'il n'existe pas
        if (!isset($_SESSION['panierTemp'])) {
            $_SESSION['panierTemp'] = array();
        }

        //vérifier si l'article existe déjà dans le panier temporaire
        $existe = isset($_SESSION['panierTemp'][$idProduit]);
        
        if ($existe) {
            $quantiteExistante = (int) $_SESSION['panierTemp'][$idProduit]['quantite'];
        } else {
            $quantiteExistante = 0;
        }

        $disponible = max(0, $quantiteEnStock - $quantiteExistante);

        if ($disponible === 0) {
            return ['success' => false, 'message' => 'Stock insuffisant: quantité maximale déjà atteinte dans votre panier'];
        }

        //quantité réellement ajoutée (ne dépasse pas le disponible)
        $aAjouter = min($quantite, $disponible);

        //ajouter ou mettre à jour l'article dans le panier temporaire
        if ($existe) {
            $_SESSION['panierTemp'][$idProduit]['quantite'] += $aAjouter;
            
            if ($aAjouter < $quantite) {
                return ['success' => true, 'message' => 'Seuls ' . $aAjouter . ' article(s) ont pu être ajouté(s) (stock limité).'];
            }
            return ['success' => true, 'message' => 'Quantité mise à jour dans le panier'];
            
        } else {
            $_SESSION['panierTemp'][$idProduit] = [
                'id_produit' => $idProduit,
                'nom' => $produitCourant['p_nom'],
                'description' => $produitCourant['p_description'],
                'image_url' => str_replace("html/img/photo", "/img/photo", $produitCourant['image_url'] ?? '/img/default-product.jpg'),
                'image_alt' => $produitCourant['image_alt'],
                'image_title' => $produitCourant['image_title'],
                'quantite' => $aAjouter,
                'prix_unitaire' => $prixUnitaire,
                'stock' => $stock,
                'frais_de_port' => $fraisDePort,
                'tva' => $tva
            ];
            
            if ($aAjouter < $quantite) {
                return ['success' => true, 'message' => 'Seuls ' . $aAjouter . ' article(s) ont pu être ajouté(s) (stock limité).'];
            }
            return ['success' => true, 'message' => 'Article ajouté au panier'];
        }

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

//fonction pour transférer le panier temporaire vers la BDD lors de la connexion
function transfererPanierTempVersBDD($pdo, $idPanier)
{
    if (!isset($_SESSION['panierTemp']) || empty($_SESSION['panierTemp'])) {
        return;
    }

    foreach ($_SESSION['panierTemp'] as $article) {
        ajouterArticleBDD($pdo, $article['id_produit'], $idPanier, $article['quantite']);
    }

    //vider le panier temporaire après transfert
    unset($_SESSION['panierTemp']);
}

//fonction pour récupérer le prix maximum parmi tous les produits
function getPrixMaximum($pdo)
{
    try {
        $requeteSQL = "SELECT MAX(p_prix) AS prix_maximum 
            FROM _produit";

        $requetePrepare = $pdo->query($requeteSQL);
        $result = $requetePrepare->fetch(PDO::FETCH_ASSOC);

        return $result['prix_maximum'] ? ceil($result['prix_maximum'] / 100) * 100 : 3000;
    } catch (Exception $e) {
        return 3000;
    }
}



?>