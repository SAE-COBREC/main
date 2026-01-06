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
            COALESCE(t.montant_tva, 0) as tva,
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
        LEFT JOIN _tva t ON p.id_tva = t.id_tva
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
            denomination,
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
        LEFT JOIN _vendeur ON _vendeur.id_vendeur = p.id_vendeur
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
                'p_nom' => $produitCourant['p_nom'],
                'p_description' => $produitCourant['p_description'],
                'i_lien' => str_replace("html/img/photo", "/img/photo", $produitCourant['image_url'] ?? '/img/default-product.jpg'),
                'i_alt' => $produitCourant['image_alt'],
                'i_title' => $produitCourant['image_title'],
                'quantite' => $aAjouter,
                'p_prix' => $prixUnitaire,
                'p_stock' => $stock,
                'p_frais_de_port' => $fraisDePort,
                'montant_tva' => $tva,
                'denomination' => $produitCourant['denomination']
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

//fonction pour vérifier l'unicité du pseudo
function verifierUnicitePseudo($connexionBaseDeDonnees, $pseudo, $idClientExclure = null)
{
    try {
        $requeteSQL = "SELECT COUNT(*) FROM cobrec1._client WHERE c_pseudo = ?";
        $params = [$pseudo];

        //exclure l'id du client actuel si fourni (pour les mises à jour)
        if ($idClientExclure !== null) {
            $requeteSQL .= " AND id_client != ?";
            $params[] = $idClientExclure;
        }

        $requetePreparee = $connexionBaseDeDonnees->prepare($requeteSQL);
        $requetePreparee->execute($params);
        $count = $requetePreparee->fetchColumn();

        return $count == 0; //retourne true si le pseudo est unique
    } catch (Exception $erreurException) {
        return false;
    }
}

//fonction pour vérifier l'unicité de l'email
function verifierUniciteEmail($connexionBaseDeDonnees, $email, $idCompteExclure = null)
{
    try {
        $requeteSQL = "SELECT COUNT(*) FROM cobrec1._compte WHERE email = ?";
        $params = [$email];

        //exclure l'id du compte actuel si fourni (pour les mises à jour)
        if ($idCompteExclure !== null) {
            $requeteSQL .= " AND id_compte != ?";
            $params[] = $idCompteExclure;
        }

        $requetePreparee = $connexionBaseDeDonnees->prepare($requeteSQL);
        $requetePreparee->execute($params);
        $count = $requetePreparee->fetchColumn();

        return $count == 0; //retourne true si l'email est unique
    } catch (Exception $erreurException) {
        return false;
    }
}

//fonction pour vérifier l'unicité du numéro de téléphone
function verifierUniciteTelephone($connexionBaseDeDonnees, $telephone, $idCompteExclure = null)
{
    try {
        $requeteSQL = "SELECT COUNT(*) FROM cobrec1._compte WHERE num_telephone = ?";
        $params = [$telephone];

        //exclure l'id du compte actuel si fourni (pour les mises à jour)
        if ($idCompteExclure !== null) {
            $requeteSQL .= " AND id_compte != ?";
            $params[] = $idCompteExclure;
        }

        $requetePreparee = $connexionBaseDeDonnees->prepare($requeteSQL);
        $requetePreparee->execute($params);
        $count = $requetePreparee->fetchColumn();

        return $count == 0; //retourne true si le téléphone est unique
    } catch (Exception $erreurException) {
        return false;
    }
}

//fonction pour valider l'email selon le regex de la BDD
function validerFormatEmail($email)
{
    $regexEmail = '/^[A-Za-z0-9._%-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/';
    return preg_match($regexEmail, $email) === 1;
}

//fonction pour valider le numéro de téléphone selon le regex de la BDD
function validerFormatTelephone($telephone)
{
    $regexTelephone = '/^0[3-7]([0-9]{2}){4}$|^0[3-7]([-. ]?[0-9]{2}){4}$/';
    return preg_match($regexTelephone, $telephone) === 1;
}

//fonction pour valider le mot de passe selon le regex de la BDD
function validerFormatMotDePasse($motDePasse)
{
    $regexMotDePasse = '/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[A-Za-z0-9.!@#$%^&*]).{8,16}$/';
    return preg_match($regexMotDePasse, $motDePasse) === 1;
}

//fonction pour récupérer les informations complètes du client
function recupererInformationsCompletesClient($connexionBaseDeDonnees, $identifiantClient)
{
    try {
        $requeteSQL = "
            SELECT co.nom, co.prenom, cl.c_pseudo, co.email, co.num_telephone, co.civilite
            FROM cobrec1._client cl
            INNER JOIN cobrec1._compte co ON cl.id_compte = co.id_compte
            WHERE cl.id_client = ?
        ";
        $requetePreparee = $connexionBaseDeDonnees->prepare($requeteSQL);
        $requetePreparee->execute([$identifiantClient]);
        $donneesClient = $requetePreparee->fetch(PDO::FETCH_ASSOC);
        return $donneesClient ?: null;
    } catch (Exception $erreurException) {
        return null;
    }
}

//fonction pour récupérer l'identifiant du compte associé au client
function recupererIdentifiantCompteClient($connexionBaseDeDonnees, $identifiantClient)
{
    try {
        $requeteSQL = "SELECT id_compte FROM cobrec1._client WHERE id_client = ?";
        $requetePreparee = $connexionBaseDeDonnees->prepare($requeteSQL);
        $requetePreparee->execute([$identifiantClient]);
        $identifiantCompte = $requetePreparee->fetchColumn();
        return $identifiantCompte !== false ? (int) $identifiantCompte : null;
    } catch (Exception $erreurException) {
        return null;
    }
}

//fonction pour récupérer toutes les adresses du client
function recupererToutesAdressesClient($connexionBaseDeDonnees, $identifiantCompte) {
    try {
        $requeteSQL = "
            SELECT id_adresse, a_numero, a_adresse, a_ville, a_code_postal, a_complement
            FROM cobrec1._adresse
            WHERE id_compte = ?
            ORDER BY id_adresse DESC
        ";
        $requetePreparee = $connexionBaseDeDonnees->prepare($requeteSQL);
        $requetePreparee->execute([$identifiantCompte]);

        return $requetePreparee->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $erreurException) {
        return [];
    }
}


//fonction pour mettre à jour une adresse existante
function mettreAJourAdresse($connexionBaseDeDonnees, $idAdresse, $idCompte, $numero, $adresse, $ville, $codePostal, $complement = '') {
    try {
        //vérifier que l'adresse appartient bien au compte
        $requeteVerification = "SELECT id_adresse FROM cobrec1._adresse WHERE id_adresse = ? AND id_compte = ?";
        $requetePrepareeVerification = $connexionBaseDeDonnees->prepare($requeteVerification);
        $requetePrepareeVerification->execute([$idAdresse, $idCompte]);

        if (!$requetePrepareeVerification->fetch()) {
            return ['success' => false, 'message' => "Adresse non trouvée ou non autorisée."];
        }

        //mise à jour de l'adresse
        $requeteMiseAJour = "
            UPDATE cobrec1._adresse 
            SET a_numero = ?, a_adresse = ?, a_ville = ?, a_code_postal = ?, a_complement = ?
            WHERE id_adresse = ? AND id_compte = ?
        ";
        $requetePrepareeMiseAJour = $connexionBaseDeDonnees->prepare($requeteMiseAJour);
        $requetePrepareeMiseAJour->execute([$numero, $adresse, $ville, $codePostal, $complement, $idAdresse, $idCompte]);

        return ['success' => true, 'message' => "Adresse mise à jour avec succès."];
    } catch (Exception $erreurException) {
        return ['success' => false, 'message' => "Erreur lors de la mise à jour de l'adresse."];
    }
}

//fonction pour supprimer une adresse
function supprimerAdresse($connexionBaseDeDonnees, $idAdresse, $idCompte)
{
    try {
        $requeteSQL = "DELETE FROM cobrec1._adresse WHERE id_adresse = ? AND id_compte = ?";
        $requetePreparee = $connexionBaseDeDonnees->prepare($requeteSQL);
        $requetePreparee->execute([$idAdresse, $idCompte]);

        return ['success' => true, 'message' => "Adresse supprimée avec succès."];
    } catch (Exception $erreurException) {
        return ['success' => false, 'message' => "Erreur lors de la suppression."];
    }
}

//fonction pour ajouter une nouvelle adresse
function ajouterNouvelleAdresse($connexionBaseDeDonnees, $idCompte, $numero, $adresse, $ville, $codePostal, $complement = '') {
    try {
        $requeteSQL = "
            INSERT INTO cobrec1._adresse (id_compte, a_numero, a_adresse, a_ville, a_code_postal, a_complement)
            VALUES (?, ?, ?, ?, ?, ?)
        ";
        $requetePreparee = $connexionBaseDeDonnees->prepare($requeteSQL);
        $requetePreparee->execute([$idCompte, $numero, $adresse, $ville, $codePostal, $complement]);

        return ['success' => true, 'message' => "Adresse ajoutée avec succès."];
    } catch (Exception $erreurException) {
        return ['success' => false, 'message' => "Erreur lors de l'ajout de l'adresse."];
    }
}


//fonction pour récupérer l'historique des dernières commandes
function recupererHistoriqueCommandesRecentes($connexionBaseDeDonnees, $identifiantClient)
{
    try {
        $requeteSQL = "
            SELECT p.id_panier, p.timestamp_commande,
                COALESCE(f.f_total_ttc, 0) as montant_total,
                COALESCE(l.etat_livraison, 'En attente') as statut
            FROM cobrec1._panier_commande p
            LEFT JOIN cobrec1._facture f ON p.id_panier = f.id_panier
            LEFT JOIN cobrec1._livraison l ON f.id_facture = l.id_facture
            WHERE p.id_client = ? AND p.timestamp_commande IS NOT NULL
            ORDER BY p.timestamp_commande DESC
            LIMIT 5
        ";
        $requetePreparee = $connexionBaseDeDonnees->prepare($requeteSQL);
        $requetePreparee->execute([$identifiantClient]);
        return $requetePreparee->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $erreurException) {
        return [];
    }
}

//fonction pour récupérer l'image de profil du compte
function recupererImageProfilCompte($connexionBaseDeDonnees, $identifiantCompte)
{
    try {
        $requeteSQL = "
            SELECT i.id_image, i.i_lien, i.i_title, i.i_alt
            FROM cobrec1._image i
            INNER JOIN cobrec1._represente_compte rc ON i.id_image = rc.id_image
            WHERE rc.id_compte = ?
        ";
        $requetePreparee = $connexionBaseDeDonnees->prepare($requeteSQL);
        $requetePreparee->execute([$identifiantCompte]);
        return $requetePreparee->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $erreurException) {
        return null;
    }
}

//fonction pour mettre à jour le profil complet (client, compte et image)
function mettreAJourProfilCompletClient($connexionBaseDeDonnees, $identifiantClient, $identifiantCompte, $nomFamille, $prenomClient, $pseudonymeClient, $adresseEmail, $numeroTelephone, $civilite, $cheminLienImage = null, $titreImage = null, $texteAlternatifImage = null) {
    try {
        //validation du pseudo unique
        if (!verifierUnicitePseudo($connexionBaseDeDonnees, $pseudonymeClient, $identifiantClient)) {
            return ['success' => false, 'message' => "Ce pseudo est déjà utilisé par un autre compte."];
        }

        //validation du format de l'email
        if (!validerFormatEmail($adresseEmail)) {
            return ['success' => false, 'message' => "Format d'email invalide."];
        }

        //validation de l'unicité de l'email
        if (!verifierUniciteEmail($connexionBaseDeDonnees, $adresseEmail, $identifiantCompte)) {
            return ['success' => false, 'message' => "Cette adresse email est déjà utilisée par un autre compte."];
        }

        //validation du format du numéro de téléphone
        if (!validerFormatTelephone($numeroTelephone)) {
            return ['success' => false, 'message' => "Format de numéro de téléphone invalide (ex: 0612345678 ou 06 12 34 56 78)."];
        }

        //validation de l'unicité du numéro de téléphone
        if (!verifierUniciteTelephone($connexionBaseDeDonnees, $numeroTelephone, $identifiantCompte)) {
            return ['success' => false, 'message' => "Ce numéro de téléphone est déjà utilisé par un autre compte."];
        }

        //mise à jour du pseudo dans la table _client
        $requeteMiseAJourClient = "
            UPDATE cobrec1._client 
            SET c_pseudo = ?
            WHERE id_client = ?
        ";
        $requetePrepareeClient = $connexionBaseDeDonnees->prepare($requeteMiseAJourClient);
        $requetePrepareeClient->execute([$pseudonymeClient, $identifiantClient]);

        //mise à jour des informations du compte dans la table _compte (nom, prenom, email, telephone, civilite)
        $requeteMiseAJourCompte = "
            UPDATE cobrec1._compte 
            SET nom = ?, prenom = ?, email = ?, num_telephone = ?, civilite = ?
            WHERE id_compte = ?
        ";
        $requetePrepareeCompte = $connexionBaseDeDonnees->prepare($requeteMiseAJourCompte);
        $requetePrepareeCompte->execute([$nomFamille, $prenomClient, $adresseEmail, $numeroTelephone, $civilite, $identifiantCompte]);

        //si une image est fournie, la mettre à jour ou l'insérer
        if ($cheminLienImage !== null && $cheminLienImage !== '') {
            $requeteVerificationImageExistante = "SELECT id_image FROM cobrec1._represente_compte WHERE id_compte = ?";
            $requetePrepareeVerification = $connexionBaseDeDonnees->prepare($requeteVerificationImageExistante);
            $requetePrepareeVerification->execute([$identifiantCompte]);
            $donneesImageExistante = $requetePrepareeVerification->fetch(PDO::FETCH_ASSOC);

            if ($donneesImageExistante) {
                $requeteModificationImage = "
                    UPDATE cobrec1._image 
                    SET i_lien = ?, i_title = ?, i_alt = ?
                    WHERE id_image = ?
                ";
                $requetePrepareeModification = $connexionBaseDeDonnees->prepare($requeteModificationImage);
                $requetePrepareeModification->execute([
                    $cheminLienImage,
                    $titreImage,
                    $texteAlternatifImage,
                    $donneesImageExistante['id_image']
                ]);
            } else {
                $requeteInsertionNouvelleImage = "
                    INSERT INTO cobrec1._image (i_lien, i_title, i_alt)
                    VALUES (?, ?, ?) RETURNING id_image
                ";
                $requetePrepareeInsertion = $connexionBaseDeDonnees->prepare($requeteInsertionNouvelleImage);
                $requetePrepareeInsertion->execute([$cheminLienImage, $titreImage, $texteAlternatifImage]);
                $identifiantNouvelleImage = $requetePrepareeInsertion->fetchColumn();

                $requeteLiaisonImageCompte = "INSERT INTO cobrec1._represente_compte (id_image, id_compte) VALUES (?, ?)";
                $requetePrepareeLiaison = $connexionBaseDeDonnees->prepare($requeteLiaisonImageCompte);
                $requetePrepareeLiaison->execute([$identifiantNouvelleImage, $identifiantCompte]);
            }
        }

        return ['success' => true, 'message' => "Profil mis à jour avec succès."];
    } catch (Exception $erreurException) {
        return ['success' => false, 'message' => "Erreur lors de la mise à jour: " . $erreurException->getMessage()];
    }
}

//fonction pour modifier le mot de passe du compte
function modifierMotDePasseCompte($connexionBaseDeDonnees, $identifiantCompte, $motDePasseActuel, $nouveauMotDePasse, $confirmationNouveauMotDePasse)
{
    try {
        //récupérer le mot de passe actuellement enregistré
        $requeteRecuperationMotDePasse = "SELECT mdp FROM cobrec1._compte WHERE id_compte = ?";
        $requetePrepareeRecuperation = $connexionBaseDeDonnees->prepare($requeteRecuperationMotDePasse);
        $requetePrepareeRecuperation->execute([$identifiantCompte]);
        $motDePasseStockeHashe = $requetePrepareeRecuperation->fetchColumn();

        //vérifier que le compte existe
        if ($motDePasseStockeHashe === false) {
            return ['success' => false, 'message' => "Compte introuvable."];
        }

        //vérifier que le mot de passe actuel correspond
        if ($motDePasseActuel != $motDePasseStockeHashe) {
            return ['success' => false, 'message' => "Mot de passe actuel incorrect."];
        }

        //vérifier que la confirmation correspond au nouveau mot de passe
        if ($nouveauMotDePasse !== $confirmationNouveauMotDePasse) {
            return ['success' => false, 'message' => "Les mots de passe ne correspondent pas."];
        }

        //validation du format du nouveau mot de passe
        if (!validerFormatMotDePasse($nouveauMotDePasse)) {
            return ['success' => false, 'message' => "Le mot de passe doit contenir entre 8 et 16 caractères, au moins une majuscule, une minuscule et un caractère spécial."];
        }

        //mettre à jour le nouveau mot de passe
        $nouveauMotDePasseHashe = $nouveauMotDePasse;
        $requeteMiseAJourMotDePasse = "UPDATE cobrec1._compte SET mdp = ? WHERE id_compte = ?";
        $requetePrepareeMiseAJour = $connexionBaseDeDonnees->prepare($requeteMiseAJourMotDePasse);
        $requetePrepareeMiseAJour->execute([$nouveauMotDePasseHashe, $identifiantCompte]);

        return ['success' => true, 'message' => "Mot de passe modifié avec succès."];
    } catch (Exception $erreurException) {
        return [
            'success' => false,
            'message' => "Erreur lors du changement de mot de passe"
        ];
    }
}

//charge les informations d'un produit et ses images
function chargerProduitBDD($pdo, $idProduit) {
    try {
        $stmtProd = $pdo->prepare("SELECT 
                p.id_produit,
                p.p_nom,
                p.p_prix,
                p.p_stock,
                p.p_statut,
                p.p_description,
                COALESCE(p.p_nb_ventes, 0) AS p_nb_ventes,
                COALESCE(p.p_note, 0) AS p_note,
                COALESCE(r.reduction_pourcentage, 0) AS pourcentage_reduction,
                COALESCE(t.montant_tva, 0) as tva,
                v.raison_sociale AS vendeur_nom,
                c.email AS vendeur_email,
                (SELECT STRING_AGG(cp.nom_categorie, ', ')
                FROM _fait_partie_de fpd
                JOIN _categorie_produit cp ON fpd.id_categorie = cp.id_categorie
                WHERE fpd.id_produit = p.id_produit) AS categories
            FROM _produit p
            LEFT JOIN _en_reduction er ON p.id_produit = er.id_produit
            LEFT JOIN _reduction r ON er.id_reduction = r.id_reduction
            LEFT JOIN _tva t ON p.id_tva = t.id_tva
            LEFT JOIN _vendeur v ON p.id_vendeur = v.id_vendeur
            LEFT JOIN _compte c ON v.id_compte = c.id_compte
            WHERE p.id_produit = :pid
            LIMIT 1");
        $stmtProd->execute([':pid' => $idProduit]);
        $produit = $stmtProd->fetch(PDO::FETCH_ASSOC);

        if (!$produit) return null;

        //pour les images
        $stmtImgs = $pdo->prepare("SELECT i.i_lien
                                    FROM _represente_produit rp
                                    JOIN _image i ON rp.id_image = i.id_image
                                    WHERE rp.id_produit = :pid
                                ORDER BY rp.id_image ASC");
        $stmtImgs->execute([':pid' => $idProduit]);
        $images = $stmtImgs->fetchAll(PDO::FETCH_COLUMN) ?: [];
        
        //nettoyage URLs images
        $images = array_values(array_unique(array_map(function ($u) {
            if (!is_string($u) || $u === '') return '/img/Photo/default.png';
            $u = trim($u);
            if (preg_match('#^https?://#i', $u) || strpos($u, '/') === 0) return $u;
            return '/' . ltrim($u, '/');
        }, $images)));

        $produit['images'] = $images;
        return $produit;
    } catch (Exception $e) {
        return null;
    }
}

//charge les avis et réponses pour un produit
function chargerAvisBDD($pdo, $idProduit, $idClient = null) {
    $avis = [];
    $reponses = [];
    
    try {
        //avis
        $sql = "
            SELECT 
                a.id_avis,
                a.a_texte,
                a.a_timestamp_creation,
                TO_CHAR(a.a_timestamp_creation,'YYYY-MM-DD HH24:MI') AS a_timestamp_fmt,
                a.a_pouce_bleu,
                a.a_pouce_rouge,
                a.a_note,
                a.a_owner_token,
                a.id_client,
                cl.c_prenom,
                cl.c_nom,
                cl.c_pseudo,
                i.i_lien as client_image,
                a.a_note AS avis_note
                " . ($idClient ? ", (SELECT CASE WHEN vote_type = 'like' THEN 'plus' WHEN vote_type = 'dislike' THEN 'minus' END FROM _vote_avis va WHERE va.id_avis = a.id_avis AND va.id_client = :cid LIMIT 1) as user_vote" : "") . "
            FROM _avis a
            LEFT JOIN _client cl ON a.id_client = cl.id_client
            LEFT JOIN _compte co ON cl.id_compte = co.id_compte
            LEFT JOIN _represente_compte rc ON co.id_compte = rc.id_compte
            LEFT JOIN _image i ON rc.id_image = i.id_image
            WHERE a.id_produit = :pid
            GROUP BY a.id_avis, a.a_texte, a.a_timestamp_creation, a.a_pouce_bleu, a.a_pouce_rouge, a.a_note, a.a_owner_token, a.id_client, cl.c_prenom, cl.c_nom, cl.c_pseudo, i.i_lien
            ORDER BY a.a_timestamp_creation DESC
        ";
        
        $stmtAvis = $pdo->prepare($sql);
        $params = [':pid' => $idProduit];
        if ($idClient) {
            $params[':cid'] = $idClient;
        }
        $stmtAvis->execute($params);
        $avis = $stmtAvis->fetchAll(PDO::FETCH_ASSOC);

        //réponses
        $stmtRep = $pdo->prepare("SELECT r.id_avis_parent, a.id_avis, a.a_texte, TO_CHAR(a.a_timestamp_creation,'YYYY-MM-DD HH24:MI') AS a_timestamp_fmt FROM _reponse r JOIN _avis a ON r.id_avis = a.id_avis WHERE a.id_produit = :pid");
        $stmtRep->execute([':pid' => $idProduit]);
        $rowsRep = $stmtRep->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rowsRep as $r) {
            $reponses[(int)$r['id_avis_parent']] = $r;
        }
    } catch (Exception $e) {
        echo "<!-- Erreur chargerAvisBDD: " . $e->getMessage() . " -->";
    }
    
    return ['avis' => $avis, 'reponses' => $reponses];
}

//gestion des actions AJAX pour les avis
function gererActionsAvis($pdo, $idClient, $idProduit) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? 'add_avis';
    $idProduitPost = isset($_POST['id_produit']) ? (int)$_POST['id_produit'] : 0;
    
    if ($idProduitPost <= 0 || $idProduitPost !== $idProduit) {
        echo json_encode(['success' => false, 'message' => 'Produit invalide']);
        exit;
    }

    try {
        if ($action === 'add_avis') {
            if (!$idClient) { echo json_encode(['success' => false, 'message' => 'Connexion requise']); exit; }
            
            //vérif achat
            $stmtVerif = $pdo->prepare("SELECT 1 FROM _contient c JOIN _panier_commande pc ON c.id_panier = pc.id_panier WHERE pc.id_client = :cid AND c.id_produit = :pid AND pc.timestamp_commande IS NOT NULL LIMIT 1");
            $stmtVerif->execute([':cid' => $idClient, ':pid' => $idProduitPost]);
            if (!$stmtVerif->fetchColumn()) { echo json_encode(['success' => false, 'message' => 'Achat requis']); exit; }
            
            $stmtCheck = $pdo->prepare("SELECT 1 FROM _avis WHERE id_produit = :pid AND id_client = :cid");
            $stmtCheck->execute([':pid' => $idProduitPost, ':cid' => $idClient]);
            if ($stmtCheck->fetchColumn()) {
                echo json_encode(['success' => false, 'message' => 'Vous avez déjà publié un avis sur ce produit.']);
                exit;
            }

            $texte = trim($_POST['commentaire'] ?? '');
            $note = (float)($_POST['note'] ?? 0.0);
            if (!in_array($note, [0.5, 1.0, 1.5, 2.0, 2.5, 3.0, 3.5, 4.0, 4.5, 5.0], true)) $note = 0.0;
            if ($note <= 0) { echo json_encode(['success' => false, 'message' => 'Note invalide']); exit; }

            $ownerToken = $_COOKIE['alizon_owner'] ?? bin2hex(random_bytes(16));
            if (!isset($_COOKIE['alizon_owner'])) setcookie('alizon_owner', $ownerToken, time() + 3600*24*365, '/');

            //insertion
            try {
                $stmt = $pdo->prepare("INSERT INTO _avis (id_produit, id_client, a_texte, a_pouce_bleu, a_pouce_rouge, a_timestamp_creation, a_note, a_owner_token) VALUES (:pid, :cid, :txt, 0, 0, NOW(), :note, :owner) RETURNING id_avis, a_timestamp_creation, TO_CHAR(a_timestamp_creation,'YYYY-MM-DD HH24:MI') AS created_at_fmt, a_note");
                $stmt->execute([':pid' => $idProduitPost, ':cid' => $idClient, ':txt' => $texte, ':note' => $note, ':owner' => $ownerToken]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                //fallback si colonnes manquantes (ne devrait pas arriver si BDD à jour)
                $stmt = $pdo->prepare("INSERT INTO _avis (id_produit, a_texte, a_pouce_bleu, a_pouce_rouge, a_timestamp_creation) VALUES (:pid, :txt, 0, 0, NOW()) RETURNING id_avis, a_timestamp_creation, TO_CHAR(a_timestamp_creation,'YYYY-MM-DD HH24:MI') AS created_at_fmt");
                $stmt->execute([':pid' => $idProduitPost, ':txt' => $texte]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $row['a_note'] = 0.0;
            }

            //Stats
            $stmtAvg = $pdo->prepare('SELECT ROUND(COALESCE(AVG(a_note),0)::numeric,1) FROM _avis WHERE id_produit = :pid AND a_note IS NOT NULL');
            $stmtAvg->execute([':pid' => $idProduitPost]);
            $newAvg = (float)$stmtAvg->fetchColumn();
            
            $stmtCnt = $pdo->prepare('SELECT COUNT(*) FROM _avis WHERE id_produit = :pid AND a_note IS NOT NULL');
            $stmtCnt->execute([':pid' => $idProduitPost]);
            $newCnt = (int)$stmtCnt->fetchColumn();

            echo json_encode([
                'success' => true, 'message' => 'Avis enregistré',
                'id_avis' => $row['id_avis'], 'created_at_fmt' => $row['created_at_fmt'],
                'note' => (float)$row['a_note'], 'avg' => $newAvg, 'countAvis' => $newCnt
            ]);
            exit;

        } elseif ($action === 'vote') {
            $idAvis = (int)($_POST['id_avis'] ?? 0);
            $val = $_POST['value'] ?? '';
            
            if (!$idClient) { echo json_encode(['success' => false, 'message' => 'Connexion requise']); exit; }
            if ($idAvis <= 0 || !in_array($val, ['plus', 'minus'])) { echo json_encode(['success' => false]); exit; }
            
            //map frontend value to DB value
            $dbVal = ($val === 'plus') ? 'like' : 'dislike';

            //check existing vote
            $stmtCheck = $pdo->prepare("SELECT vote_type FROM _vote_avis WHERE id_client = :cid AND id_avis = :aid");
            $stmtCheck->execute([':cid' => $idClient, ':aid' => $idAvis]);
            $existingVoteDb = $stmtCheck->fetchColumn();
            
            $pdo->beginTransaction();
            try {
                if ($existingVoteDb === $dbVal) {
                    //remove vote
                    $pdo->prepare("DELETE FROM _vote_avis WHERE id_client = :cid AND id_avis = :aid")->execute([':cid' => $idClient, ':aid' => $idAvis]);
                    if ($val === 'plus') {
                        $pdo->prepare("UPDATE _avis SET a_pouce_bleu = GREATEST(a_pouce_bleu - 1, 0) WHERE id_avis = :aid")->execute([':aid' => $idAvis]);
                    } else {
                        $pdo->prepare("UPDATE _avis SET a_pouce_rouge = GREATEST(a_pouce_rouge - 1, 0) WHERE id_avis = :aid")->execute([':aid' => $idAvis]);
                    }
                } else {
                    if ($existingVoteDb) {
                        //change vote
                        $pdo->prepare("UPDATE _vote_avis SET vote_type = :val WHERE id_client = :cid AND id_avis = :aid")->execute([':val' => $dbVal, ':cid' => $idClient, ':aid' => $idAvis]);
                        if ($existingVoteDb === 'like' && $dbVal === 'dislike') {
                            $pdo->prepare("UPDATE _avis SET a_pouce_bleu = GREATEST(a_pouce_bleu - 1, 0), a_pouce_rouge = a_pouce_rouge + 1 WHERE id_avis = :aid")->execute([':aid' => $idAvis]);
                        } elseif ($existingVoteDb === 'dislike' && $dbVal === 'like') {
                            $pdo->prepare("UPDATE _avis SET a_pouce_rouge = GREATEST(a_pouce_rouge - 1, 0), a_pouce_bleu = a_pouce_bleu + 1 WHERE id_avis = :aid")->execute([':aid' => $idAvis]);
                        }
                    } else {
                        //new vote
                        $pdo->prepare("INSERT INTO _vote_avis (id_client, id_avis, vote_type) VALUES (:cid, :aid, :val)")->execute([':cid' => $idClient, ':aid' => $idAvis, ':val' => $dbVal]);
                        if ($val === 'plus') {
                            $pdo->prepare("UPDATE _avis SET a_pouce_bleu = a_pouce_bleu + 1 WHERE id_avis = :aid")->execute([':aid' => $idAvis]);
                        } else {
                            $pdo->prepare("UPDATE _avis SET a_pouce_rouge = a_pouce_rouge + 1 WHERE id_avis = :aid")->execute([':aid' => $idAvis]);
                        }
                    }
                }
                
                $pdo->commit();
                
                $stmt = $pdo->prepare("SELECT a_pouce_bleu, a_pouce_rouge FROM _avis WHERE id_avis = :id");
                $stmt->execute([':id' => $idAvis]);
                echo json_encode(['success' => true, 'counts' => $stmt->fetch(PDO::FETCH_ASSOC), 'user_vote' => ($existingVoteDb === $dbVal ? null : $val)]);
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
                exit;
            }

        } elseif ($action === 'edit_avis') {
            $idAvis = (int)($_POST['id_avis'] ?? 0);
            $txt = trim($_POST['commentaire'] ?? '');
            $note = isset($_POST['note']) ? (float)$_POST['note'] : null;
            $owner = $_COOKIE['alizon_owner'] ?? '';
            
            if (!$idClient) { echo json_encode(['success' => false, 'message' => 'Connexion requise']); exit; }

            $set = ['a_texte = :txt', 'a_timestamp_modification = NOW()'];
            $params = [':txt' => $txt, ':id' => $idAvis, ':pid' => $idProduitPost, ':owner' => $owner, ':cid' => $idClient];
            if ($note !== null) { $set[] = 'a_note = :note'; $params[':note'] = $note; }
            
            $stmt = $pdo->prepare('UPDATE _avis SET ' . implode(', ', $set) . ' WHERE id_avis = :id AND id_produit = :pid AND ((id_client IS NOT NULL AND id_client = :cid) OR (id_client IS NULL AND a_owner_token = :owner)) RETURNING TO_CHAR(a_timestamp_modification,\'YYYY-MM-DD HH24:MI\') AS fmt, a_note');
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) echo json_encode(['success' => true, 'updated_at_fmt' => $row['fmt'], 'note' => $row['a_note']]);
            else echo json_encode(['success' => false, 'message' => 'Non autorisé']);
            exit;

        } elseif ($action === 'delete_avis') {
            $idAvis = (int)($_POST['id_avis'] ?? 0);
            $owner = $_COOKIE['alizon_owner'] ?? '';

            if (!$idClient) { echo json_encode(['success' => false, 'message' => 'Connexion requise']); exit; }

            $stmt = $pdo->prepare('DELETE FROM _avis WHERE id_avis = :id AND id_produit = :pid AND ((id_client IS NOT NULL AND id_client = :cid) OR (id_client IS NULL AND a_owner_token = :owner)) RETURNING id_avis');
            $stmt->execute([':id' => $idAvis, ':pid' => $idProduitPost, ':owner' => $owner, ':cid' => $idClient]);
            if ($stmt->fetch()) echo json_encode(['success' => true]);
            else echo json_encode(['success' => false, 'message' => 'Non autorisé']);
            exit;

        } elseif ($action === 'get_rating') {
            $stmtAvg = $pdo->prepare('SELECT ROUND(COALESCE(AVG(a_note),0)::numeric,1) FROM _avis WHERE id_produit = :pid AND a_note IS NOT NULL');
            $stmtAvg->execute([':pid' => $idProduitPost]);
            $avg = (float)$stmtAvg->fetchColumn();
            $stmtCnt = $pdo->prepare('SELECT COUNT(*) FROM _avis WHERE id_produit = :pid AND a_note IS NOT NULL');
            $stmtCnt->execute([':pid' => $idProduitPost]);
            echo json_encode(['success' => true, 'avg' => $avg, 'countAvis' => (int)$stmtCnt->fetchColumn()]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
        exit;
    }
}

//fonction pour récupérer toutes les données du profil client en une seule requête optimisée
function recupererProfilCompletClientOptimise($connexionBaseDeDonnees, $identifiantClient) {
    try {
        //requête SQL unique avec sous-requêtes JSON pour agréger toutes les données
        $requeteSQL = "
            SELECT 
                cl.id_client,
                cl.c_nom,
                cl.c_prenom,
                cl.c_pseudo,
                
                co.id_compte,
                co.email,
                co.num_telephone,
                
                -- image de profil (agrégée en JSON)
                (SELECT json_build_object(
                    'id_image', i.id_image,
                    'i_lien', i.i_lien,
                    'i_title', i.i_title,
                    'i_alt', i.i_alt
                )
                FROM cobrec1._image i
                INNER JOIN cobrec1._represente_compte rc ON i.id_image = rc.id_image
                WHERE rc.id_compte = co.id_compte
                LIMIT 1) as image_profil,
                
                (SELECT json_agg(
                    json_build_object(
                        'id_adresse', a.id_adresse,
                        'a_adresse', a.a_adresse,
                        'a_ville', a.a_ville,
                        'a_code_postal', a.a_code_postal,
                        'a_complement', a.a_complement
                    ) ORDER BY a.id_adresse DESC
                )
                FROM cobrec1._adresse a
                WHERE a.id_compte = co.id_compte) as adresses,
                
                (SELECT json_agg(
                    json_build_object(
                        'id_panier', p.id_panier,
                        'timestamp_commande', TO_CHAR(p.timestamp_commande, 'YYYY-MM-DD HH24:MI:SS'),
                        'montant_total', COALESCE(f.f_total_ttc, 0),
                        'statut', COALESCE(l.etat_livraison, 'En attente')
                    ) ORDER BY p.timestamp_commande DESC
                )
                FROM (
                    SELECT id_panier, id_client, timestamp_commande FROM cobrec1._panier_commande
                    WHERE id_client = cl.id_client 
                    AND timestamp_commande IS NOT NULL
                    ORDER BY timestamp_commande DESC
                    LIMIT 5
                ) p
                LEFT JOIN cobrec1._facture f ON p.id_panier = f.id_panier
                LEFT JOIN cobrec1._livraison l ON f.id_facture = l.id_facture
                ) as commandes_recentes
                
            FROM cobrec1._client cl
            INNER JOIN cobrec1._compte co ON cl.id_compte = co.id_compte
            WHERE cl.id_client = :identifiantClient
            LIMIT 1
        ";
        
        $requetePreparee = $connexionBaseDeDonnees->prepare($requeteSQL);
        $requetePreparee->execute(['identifiantClient' => $identifiantClient]);
        
        $donnees = $requetePreparee->fetch(PDO::FETCH_ASSOC);
        
        if (!$donnees) {
            return null;
        }
        
        //décoder les JSON agrégés
        $donnees['image_profil'] = $donnees['image_profil'] ? json_decode($donnees['image_profil'], true) : null;
        $donnees['adresses'] = $donnees['adresses'] ? json_decode($donnees['adresses'], true) : [];
        $donnees['commandes_recentes'] = $donnees['commandes_recentes'] ? json_decode($donnees['commandes_recentes'], true) : [];
        
        return $donnees;
        
    } catch (Exception $erreurException) {
        return null;
    }
}


//fonction pour récupérer le profil complet avec cache en session en mettant les données en cache pour 5 minutes
function recupererProfilCompletClientAvecCache($connexionBaseDeDonnees, $identifiantClient, $forceRefresh = false) {
    //clé de cache unique pour ce client
    $cacheKey = 'profil_client_' . $identifiantClient;
    
    //si pas de force refresh et cache existe et est récent (< 5 minutes)
    if (!$forceRefresh && 
        isset($_SESSION[$cacheKey]) && 
        isset($_SESSION[$cacheKey . '_timestamp']) &&
        (time() - $_SESSION[$cacheKey . '_timestamp']) < 300) {
        
        return $_SESSION[$cacheKey];
    }
    
    //sinon, récupérer depuis la BDD
    $donnees = recupererProfilCompletClientOptimise($connexionBaseDeDonnees, $identifiantClient);
    
    if ($donnees !== null) {
        //mettre en cache
        $_SESSION[$cacheKey] = $donnees;
        $_SESSION[$cacheKey . '_timestamp'] = time();
    }
    
    return $donnees;
}


//fonction pour invalider le cache du profil client
function invaliderCacheProfilClient($identifiantClient) {
    $cacheKey = 'profil_client_' . $identifiantClient;
    unset($_SESSION[$cacheKey]);
    unset($_SESSION[$cacheKey . '_timestamp']);
}

function calcPrixTVA($identifiantProduit, $TVA, $prixHT) {
    $resultat = $prixHT;
    if ($TVA > 0) {
        $resultat += ($prixHT * ($TVA / 100));
    }

    return $resultat;
}//fonction pour transférer le panier temporaire vers la BDD lors de la connexion
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