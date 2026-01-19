<?php

//fonction pour charger tous les produits depuis la base de données
function chargerProduitsBDD($pdo)
{
    $listeProduits = [];
    $listeCategories = [];

    try {
        //requête SQL pour récupérer tous les produits avec leurs informations
        $requeteSQL = "
        SELECT DISTINCT ON (p.id_produit)
            p.id_produit,
            p.p_nom,
            p.p_description,
            p.p_prix,
            p.p_stock,
            p.p_nb_ventes,
            r.reduction_pourcentage,
            t.montant_tva as tva,
            pr.id_produit as estEnpromo,
            (SELECT COUNT(*) FROM cobrec1._avis av2 WHERE av2.id_produit = p.id_produit AND av2.a_note IS NOT NULL) as nombre_avis,
            (SELECT ROUND(COALESCE(AVG(av3.a_note), 0)::numeric, 1) FROM cobrec1._avis av3 WHERE av3.id_produit = p.id_produit AND av3.a_note IS NOT NULL) as note_moyenne,
            (SELECT COALESCE(i2.i_lien, '/img/photo/smartphone_xpro.jpg') FROM cobrec1._represente_produit rp2 LEFT JOIN cobrec1._image i2 ON rp2.id_image = i2.id_image WHERE rp2.id_produit = p.id_produit LIMIT 1) as image_url,
            STRING_AGG(DISTINCT cp.nom_categorie, ', ') as categories
        FROM cobrec1._produit p
        LEFT JOIN cobrec1._reduction r ON p.id_produit = r.id_produit 
            AND CURRENT_TIMESTAMP BETWEEN r.reduction_debut AND r.reduction_fin
        LEFT JOIN cobrec1._promotion pr ON p.id_produit = pr.id_produit 
            AND CURRENT_TIMESTAMP BETWEEN pr.promotion_debut AND pr.promotion_fin
        LEFT JOIN cobrec1._tva t ON p.id_tva = t.id_tva
        LEFT JOIN cobrec1._fait_partie_de fpd ON p.id_produit = fpd.id_produit
        LEFT JOIN cobrec1._categorie_produit cp ON fpd.id_categorie = cp.id_categorie
        WHERE p.p_statut = 'En ligne'
        GROUP BY p.id_produit, p.p_nom, p.p_description, p.p_prix, p.p_stock, 
                p.p_note, p.p_nb_ventes, p.p_statut, r.reduction_pourcentage, 
                pr.id_produit, t.montant_tva
        ORDER BY p.id_produit;
    ";

        $requetePrepare = $pdo->query($requeteSQL);
        $listeProduits = $requetePrepare->fetchAll(PDO::FETCH_ASSOC);

        //requête pour compter les produits par catégorie
        $sqlCategories = "
        SELECT cp.nom_categorie as category, 
                COUNT(DISTINCT p.id_produit) as count
        FROM cobrec1._produit p
        JOIN cobrec1._fait_partie_de fpd ON p.id_produit = fpd.id_produit
        JOIN cobrec1._categorie_produit cp ON fpd.id_categorie = cp.id_categorie
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
            SELECT DISTINCT ON (p.id_produit)
                p.p_prix, 
                p.p_frais_de_port, 
                p.p_stock,
                t.montant_tva as tva,
                COALESCE(r.reduction_pourcentage, 0) as pourcentage_reduction
            FROM cobrec1._produit p
            JOIN cobrec1._tva t ON p.id_tva = t.id_tva
            LEFT JOIN cobrec1._reduction r ON p.id_produit = r.id_produit
                AND r.reduction_debut <= CURRENT_TIMESTAMP 
                AND r.reduction_fin >= CURRENT_TIMESTAMP
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
        $remiseUnitaire = $produitCourant['pourcentage_reduction'];
        $fraisDePort = $produitCourant['p_frais_de_port'];
        $tva = $produitCourant['tva'];
        $quantiteEnStock = (int) ($produitCourant['p_stock'] ?? 0);

        //vérifier si l'article existe déjà dans le panier
        $sqlCheck = "SELECT quantite FROM cobrec1._contient WHERE id_produit = :idProduit AND id_panier = :idPanier";
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
            $sqlUpdate = "UPDATE cobrec1._contient SET quantite = quantite + :quantite WHERE id_produit = :idProduit AND id_panier = :idPanier";
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
                INSERT INTO cobrec1._contient 
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
            SELECT DISTINCT ON (p.id_produit)
                p.p_nom,
                p.p_description,
                p.p_prix, 
                p.p_frais_de_port, 
                p.p_stock,
                v.denomination,
                t.montant_tva as tva,
                COALESCE(r.reduction_pourcentage, 0) as pourcentage_reduction,
                COALESCE(i.i_lien, '/img/photo/smartphone_xpro.jpg') as image_url,
                i.i_alt as image_alt,
                i.i_title as image_title,
                r.reduction_debut,
                r.reduction_fin
            FROM cobrec1._produit p
            JOIN cobrec1._tva t ON p.id_tva = t.id_tva
            JOIN cobrec1._vendeur v ON v.id_vendeur = p.id_vendeur
            LEFT JOIN cobrec1._reduction r ON p.id_produit = r.id_produit
                AND r.reduction_debut <= CURRENT_TIMESTAMP 
                AND r.reduction_fin >= CURRENT_TIMESTAMP
            LEFT JOIN cobrec1._represente_produit rp ON p.id_produit = rp.id_produit
            LEFT JOIN cobrec1._image i ON rp.id_image = i.id_image
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
                'tva' => $produitCourant['tva'],
                'pourcentage_reduction' => $produitCourant['pourcentage_reduction'],
                'reduction_debut' => $produitCourant['reduction_debut'],
                'reduction_fin' => $produitCourant['reduction_fin'],
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

// Rendu HTML des avis (extrait de pages/produit/index.php pour réutilisation)
if (!function_exists('renderAvisHtml')) {
    function renderAvisHtml($avisTextes, $reponsesMap, $idClient, $ownerTokenServer) {
        if (empty($avisTextes)) {
            echo '<p style="color:#666;">Aucun avis pour le moment. Soyez le premier !</p>';
            return;
        }

        foreach ($avisTextes as $ta) {
            $aNote = (float)($ta['a_note'] ?? 0);
            $aTitre = $ta['a_titre'] ?? '';
            $aNoteEntiere = (int)floor($aNote);

            // Determine display name
            $displayName = 'Utilisateur';
            if (!empty($ta['c_pseudo'])) {
                $displayName = $ta['c_pseudo'];
            } elseif (!empty($ta['prenom']) || !empty($ta['nom'])) {
                $displayName = trim(($ta['prenom'] ?? '') . ' ' . ($ta['nom'] ?? ''));
            }

            // Determine avatar
            $avatarUrl = $ta['client_image'] ?? null;
            ?>
<div class="review" data-avis-id="<?= (int)$ta['id_avis'] ?>" data-note="<?= $aNote ?>"
    data-title="<?= htmlspecialchars($aTitre) ?>" style="margin-bottom:12px;position:relative;padding-right:44px;">
    <?php if (!($idClient && ( ($ta['id_client'] && $ta['id_client'] == $idClient) || (!$ta['id_client'] && $ownerTokenServer && isset($ta['a_owner_token']) && $ta['a_owner_token'] === $ownerTokenServer) ))): ?>
    <button class="ghost btn-report-trigger" aria-label="Options avis"
        style="position:absolute;right:3em;top:8px;width:34px;height:34px;border-radius:6px;display:flex;align-items:center;justify-content:center">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="5" r="1.5"></circle>
            <circle cx="12" cy="12" r="1.5"></circle>
            <circle cx="12" cy="19" r="1.5"></circle>
        </svg>
    </button>
    <div class="report-dropdown"
        style="display:none;position:absolute;right:8px;top:44px;background:#fff;border:1px solid #e0e0e0;border-radius:6px;z-index:60;min-width:160px;box-shadow:0 6px 18px rgba(0,0,0,.06)">
        <?php if (isset($ta['user_reported']) && $ta['user_reported']): ?>
        <button class="btn-unreport-action"
            style="width:100%;text-align:left;padding:10px;border:none;background:transparent;border-radius:6px">Annuler
            le signalement</button>
        <?php else: ?>
        <button class="btn-report-action"
            style="width:100%;text-align:left;padding:10px;border:none;background:transparent;border-radius:6px">Signaler
            l'avis</button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
        <?php if ($avatarUrl): ?>
        <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="Avatar"
            style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
        <?php else: ?>
        <div
            style="width:40px;height:40px;border-radius:50%;background:linear-gradient(180deg,#eef1ff,#ffffff);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--accent)">
            <?= strtoupper(substr($displayName, 0, 1)) ?></div>
        <?php endif; ?>
        <div>
            <div style="font-weight:700"><?= htmlspecialchars($displayName) ?></div>
            <div style="color:var(--muted);font-size:13px">Avis</div>
            <div style="display:flex;align-items:center;gap:6px;margin-top:4px">
                <span class="stars" aria-hidden="true">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <img src="/img/svg/star-<?= $i <= $aNoteEntiere ? 'full' : 'empty' ?>.svg" alt="Etoile" width="16">
                    <?php endfor; ?>
                </span>
                <span class="review-rating-value"
                    style="color:var(--muted);font-weight:600;"><?= number_format($aNote, 1) ?></span>
            </div>
        </div>
    </div>
    <?php if (!empty($aTitre)): ?>
    <strong
        style="display:block;margin-bottom:8px;color:var(--text);font-size:16px;"><?= htmlspecialchars($aTitre) ?></strong>
    <?php endif; ?>
    <div class="review-content" style="color:var(--muted)"><?= htmlspecialchars($ta['a_texte']) ?></div>
    <div class="review-votes">
        <div class="vote-section">
            <span class="vote-label">Évaluer ce commentaire :</span>
            <div class="vote-buttons">
                <button type="button" class="ghost btn-vote" data-type="J'aime" aria-label="Vote plus"
                    <?= (isset($ta['user_vote']) && $ta['user_vote'] === 'plus') ? 'aria-pressed="true"' : '' ?>>
                    <img src="/img/svg/PouceHaut.svg" alt="J'aime" width="16" height="16"> <span
                        class="like-count"><?= (int)$ta['a_pouce_bleu'] ?></span>
                </button>
                <button type="button" class="ghost btn-vote" data-type="Je n'aime pas" aria-label="Vote moins"
                    <?= (isset($ta['user_vote']) && $ta['user_vote'] === 'minus') ? 'aria-pressed="true"' : '' ?>>
                    <img src="/img/svg/PouceBas.svg" alt="Je n'aime pas" width="16" height="16"> <span
                        class="dislike-count"><?= (int)$ta['a_pouce_rouge'] ?></span>
                </button>
            </div>
        </div>
        <span class="review-date"><?= htmlspecialchars($ta['a_timestamp_fmt'] ?? '') ?></span>
        <?php if ($idClient && ( ($ta['id_client'] && $ta['id_client'] == $idClient) || (!$ta['id_client'] && $ownerTokenServer && isset($ta['a_owner_token']) && $ta['a_owner_token'] === $ownerTokenServer) )): ?>
        <div class="review-actions">
            <button class="ghost btn-edit-review desktop-only">Modifier</button>
            <button class="ghost btn-delete-review desktop-only">Supprimer</button>

            <div class="mobile-menu-container mobile-only">
                <button class="ghost btn-menu-trigger" aria-label="Options">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="1"></circle>
                        <circle cx="19" cy="12" r="1"></circle>
                        <circle cx="5" cy="12" r="1"></circle>
                    </svg>
                </button>
                <div class="mobile-menu-dropdown">
                    <button class="btn-edit-review">Modifier</button>
                    <button class="btn-delete-review">Supprimer</button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php if(isset($reponsesMap[(int)$ta['id_avis']])): $rep = $reponsesMap[(int)$ta['id_avis']]; ?>
    <div class="review"
        style="margin:12px 0 4px 48px;padding:10px 12px;background:#fff6e6;border:1px solid #ffe0a3;border-radius:8px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
            <div
                style="width:32px;height:32px;border-radius:50%;background:#ffc860;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#7a4d00">
                V</div>
            <div style="font-weight:600;color:#7a4d00">Réponse du vendeur</div>
            <span
                style="margin-left:auto;font-size:11px;color:#b07200;"><?= htmlspecialchars($rep['a_timestamp_fmt'] ?? '') ?></span>
        </div>
        <div style="font-size:13px;color:#7a4d00;line-height:1.4"><?= htmlspecialchars($rep['a_texte']) ?></div>
    </div>
    <?php endif; ?>
</div>
<?php
        }
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
            RETURNING id_adresse
        ";
        $requetePreparee = $connexionBaseDeDonnees->prepare($requeteSQL);
        $requetePreparee->execute([$idCompte, $numero, $adresse, $ville, $codePostal, $complement]);
        $idAdresse = (int) $requetePreparee->fetchColumn();
        return ['success' => true, 'message' => "Adresse ajoutée avec succès.", 'id_adresse' => $idAdresse];
    } catch (Exception $erreurException) {
        return ['success' => false, 'message' => "Erreur lors de l'ajout de l'adresse.", 'id_adresse' => $idAdresse];
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
        if (password_verify($motDePasseActuel, $motDePasseStockeHashe) === false) {
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
        $nouveauMotDePasseHashe = password_hash($nouveauMotDePasse, PASSWORD_DEFAULT);
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
        $stmtProd = $pdo->prepare("
            SELECT DISTINCT ON (p.id_produit)
                p.id_produit,
                p.p_nom,
                p.p_prix,
                p.p_stock,
                p.p_statut,
                p.p_description,
                p.p_nb_ventes,
                COALESCE(p.p_nb_ventes, 0) AS p_nb_ventes,
                COALESCE(p.p_note, 0) AS p_note,
                COALESCE(r.reduction_pourcentage, 0) AS pourcentage_reduction,
                t.montant_tva as tva,
                v.raison_sociale AS vendeur_nom,
                c.email AS vendeur_email,
                STRING_AGG(DISTINCT cp.nom_categorie, ', ') AS categories
            FROM cobrec1._produit p
            JOIN cobrec1._tva t ON p.id_tva = t.id_tva
            JOIN cobrec1._vendeur v ON p.id_vendeur = v.id_vendeur
            JOIN cobrec1._compte c ON v.id_compte = c.id_compte
            LEFT JOIN cobrec1._reduction r ON p.id_produit = r.id_produit
                AND r.reduction_debut <= CURRENT_TIMESTAMP 
                AND r.reduction_fin >= CURRENT_TIMESTAMP
            LEFT JOIN cobrec1._fait_partie_de fpd ON p.id_produit = fpd.id_produit
            LEFT JOIN cobrec1._categorie_produit cp ON fpd.id_categorie = cp.id_categorie
            WHERE p.id_produit = :pid
            GROUP BY p.id_produit, p.p_nom, p.p_prix, p.p_stock, p.p_statut, 
                     p.p_description, p.p_nb_ventes, p.p_note, r.reduction_pourcentage,
                     t.montant_tva, v.raison_sociale, c.email
            LIMIT 1
        ");
        $stmtProd->execute([':pid' => $idProduit]);
        $produit = $stmtProd->fetch(PDO::FETCH_ASSOC);

        if (!$produit) return null;

        //pour les images
        $stmtImgs = $pdo->prepare("
            SELECT DISTINCT i.i_lien
            FROM cobrec1._represente_produit rp
            JOIN cobrec1._image i ON rp.id_image = i.id_image
            WHERE rp.id_produit = :pid
            ORDER BY i.i_lien ASC
        ");
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
        // Sélection simple et explicite
        $sql = "
            SELECT 
                a.id_avis,
                a.a_texte,
                a.a_titre,
                a.a_timestamp_creation,
                a.a_note,
                a.a_pouce_bleu,
                a.a_pouce_rouge,
                a.id_client,
                a.a_owner_token,
                TO_CHAR(a.a_timestamp_creation,'YYYY-MM-DD HH24:MI') AS a_timestamp_fmt,
                co.prenom,
                co.nom,
                cl.c_pseudo,
                i.i_lien as client_image,
                " . ($idClient ? "(SELECT CASE WHEN vote_type = 'like' THEN 'plus' WHEN vote_type = 'dislike' THEN 'minus' END FROM _vote_avis va WHERE va.id_avis = a.id_avis AND va.id_client = :cid LIMIT 1) as user_vote" : "NULL as user_vote") . "
            FROM _avis a
            LEFT JOIN _client cl ON a.id_client = cl.id_client
            LEFT JOIN _compte co ON cl.id_compte = co.id_compte
            LEFT JOIN _represente_compte rc ON co.id_compte = rc.id_compte
            LEFT JOIN _image i ON rc.id_image = i.id_image
            WHERE a.id_produit = :pid
            ORDER BY a.a_timestamp_creation DESC
        ";
        
        $stmtAvis = $pdo->prepare($sql);
        $params = [':pid' => $idProduit];
        if ($idClient) $params[':cid'] = $idClient;
        
        $stmtAvis->execute($params);
        $avis = $stmtAvis->fetchAll(PDO::FETCH_ASSOC);

        // Réponses
        $stmtRep = $pdo->prepare("SELECT r.id_avis_parent, a.id_avis, a.a_texte, TO_CHAR(a.a_timestamp_creation,'YYYY-MM-DD HH24:MI') AS a_timestamp_fmt FROM _reponse r JOIN _avis a ON r.id_avis = a.id_avis WHERE a.id_produit = :pid");
        $stmtRep->execute([':pid' => $idProduit]);
        $rowsRep = $stmtRep->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rowsRep as $r) {
            $reponses[(int)$r['id_avis_parent']] = $r;
        }
    } catch (Exception $e) {
        error_log("Erreur chargerAvisBDD: " . $e->getMessage());
    }
    
    return ['avis' => $avis, 'reponses' => $reponses];
}

//gestion des actions AJAX pour les avis

function gererActionsAvis($pdo, $idClient, $idProduit) {
    header('Content-Type: application/json; charset=utf-8');
    
    $action = $_POST['action'] ?? '';
    
    if (empty($action)) {
        echo json_encode(['success' => false, 'message' => 'Action manquante']);
        exit;
    }

    try {
        if ($action === 'add_avis') {
            if (!$idClient) throw new Exception('Connexion requise.');
            
            $idProduitPost = (int)($_POST['id_produit'] ?? 0);
            if ($idProduitPost !== $idProduit) throw new Exception('Produit incohérent.');

            // Achat ?
            $sqlAchat = "SELECT 1 FROM _contient c JOIN _panier_commande pc ON c.id_panier = pc.id_panier WHERE pc.id_client = ? AND c.id_produit = ? AND pc.timestamp_commande IS NOT NULL LIMIT 1";
            $stmtAchat = $pdo->prepare($sqlAchat);
            $stmtAchat->execute([$idClient, $idProduit]);
            if (!$stmtAchat->fetchColumn()) throw new Exception('Vous devez avoir acheté ce produit.');

            // Déjà avis ?
            $stmtCheck = $pdo->prepare("SELECT 1 FROM _avis WHERE id_produit = ? AND id_client = ?");
            $stmtCheck->execute([$idProduit, $idClient]);
            if ($stmtCheck->fetchColumn()) throw new Exception('Vous avez déjà donné votre avis.');

            $titre = trim($_POST['titre'] ?? '');
            $texte = trim($_POST['commentaire'] ?? '');
            $note = (float)($_POST['note'] ?? 0);

            if (empty($titre)) throw new Exception('Le titre est obligatoire.');
            if (empty($texte)) throw new Exception('Le commentaire est obligatoire.');
            if ($note < 0.5 || $note > 5) throw new Exception('La note est invalide.');

            $ownerToken = $_COOKIE['alizon_owner'] ?? bin2hex(random_bytes(16));
            if (!isset($_COOKIE['alizon_owner'])) setcookie('alizon_owner', $ownerToken, time() + 3600*24*365, '/');

            // Insertion
            $sqlInsert = "
                INSERT INTO _avis (id_produit, id_client, a_titre, a_texte, a_note, a_timestamp_creation, a_pouce_bleu, a_pouce_rouge, a_owner_token) 
                VALUES (:pid, :cid, :titre, :txt, :note, NOW(), 0, 0, :owner) 
                RETURNING id_avis, a_titre, a_texte, a_note, TO_CHAR(a_timestamp_creation,'YYYY-MM-DD HH24:MI') as created_at_fmt
            ";
            
            $stmt = $pdo->prepare($sqlInsert);
            $stmt->execute([
                ':pid' => $idProduit,
                ':cid' => $idClient,
                ':titre' => $titre,
                ':txt' => $texte,
                ':note' => $note,
                ':owner' => $ownerToken
            ]);
            
            $newReview = $stmt->fetch(PDO::FETCH_ASSOC);

            // Stats
            $stmtAvg = $pdo->prepare('SELECT ROUND(COALESCE(AVG(a_note),0)::numeric,1) as avg, COUNT(*) as cnt FROM _avis WHERE id_produit = ? AND a_note IS NOT NULL');
            $stmtAvg->execute([$idProduit]);
            $stats = $stmtAvg->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'message' => 'Avis publié avec succès',
                'avis' => $newReview, 
                'avg' => $stats['avg'],
                'countAvis' => $stats['cnt']
            ]);
            exit;

        } elseif ($action === 'edit_avis') {
            if (!$idClient) throw new Exception('Connexion requise.');
            
            $idAvis = (int)($_POST['id_avis'] ?? 0);

            $stmtExist = $pdo->prepare("SELECT 1 FROM _avis WHERE id_avis = ?");
            $stmtExist->execute([$idAvis]);
            if (!$stmtExist->fetchColumn()) throw new Exception("Cet avis n’existe plus");

            $titre = trim($_POST['titre'] ?? '');
            $texte = trim($_POST['commentaire'] ?? '');
            $note = isset($_POST['note']) ? (float)$_POST['note'] : null;
            
            if (empty($titre)) throw new Exception('Titre requis.');
            if (empty($texte)) throw new Exception('Commentaire requis.');

            $owner = $_COOKIE['alizon_owner'] ?? '';
            $checkSql = "SELECT 1 FROM _avis WHERE id_avis = ? AND id_produit = ? AND ((id_client = ?) OR (a_owner_token = ? AND id_client IS NULL))";
            $stmtC = $pdo->prepare($checkSql);
            $stmtC->execute([$idAvis, $idProduit, $idClient, $owner]);
            if (!$stmtC->fetchColumn()) throw new Exception('Action non autorisée.');

            $updateSql = "UPDATE _avis SET a_titre = :titre, a_texte = :txt, a_timestamp_modification = NOW()";
            $params = [':titre' => $titre, ':txt' => $texte, ':id' => $idAvis];
            
            if ($note !== null) {
                $updateSql .= ", a_note = :note";
                $params[':note'] = $note;
            }
            
            $updateSql .= " WHERE id_avis = :id RETURNING a_titre, a_texte, a_note, TO_CHAR(a_timestamp_modification,'YYYY-MM-DD HH24:MI') AS fmt";
            
            $stmtUp = $pdo->prepare($updateSql);
            $stmtUp->execute($params);
            $updated = $stmtUp->fetch(PDO::FETCH_ASSOC);

            // Recalculer stats globales après modification
            $stmtAvg = $pdo->prepare('SELECT ROUND(COALESCE(AVG(a_note),0)::numeric,1) as avg, COUNT(*) as cnt FROM _avis WHERE id_produit = ? AND a_note IS NOT NULL');
            $stmtAvg->execute([$idProduit]);
            $stats = $stmtAvg->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'updated_at_fmt' => $updated['fmt'], 'avis' => $updated, 'avg' => (float)$stats['avg'], 'countAvis' => (int)$stats['cnt']]);
            exit;

        } elseif ($action === 'delete_avis') {
             if (!$idClient) throw new Exception('Connexion requise.');
             $idAvis = (int)($_POST['id_avis'] ?? 0);

             $stmtExist = $pdo->prepare("SELECT 1 FROM _avis WHERE id_avis = ?");
             $stmtExist->execute([$idAvis]);
             if (!$stmtExist->fetchColumn()) throw new Exception("Cet avis n’existe plus");

             $owner = $_COOKIE['alizon_owner'] ?? '';

             // Supprimer aussi l'éventuelle réponse du vendeur
             $stmtRep = $pdo->prepare("SELECT id_avis FROM cobrec1._reponse WHERE id_avis_parent = ?");
             $stmtRep->execute([$idAvis]);
             $reponsesIds = $stmtRep->fetchAll(PDO::FETCH_COLUMN);

             if (!empty($reponsesIds)) {
                 $placeholdersRep = implode(',', array_fill(0, count($reponsesIds), '?'));
                 $pdo->prepare("DELETE FROM _avis WHERE id_avis IN ($placeholdersRep)")->execute($reponsesIds);
             }

             $sqlDel = "DELETE FROM _avis WHERE id_avis = ? AND id_produit = ? AND ((id_client = ?) OR (a_owner_token = ? AND id_client IS NULL))";
             $stmtDel = $pdo->prepare($sqlDel);
             $stmtDel->execute([$idAvis, $idProduit, $idClient, $owner]);
             
             if ($stmtDel->rowCount() > 0) {
                 // Recalculer stats (note moyenne et nombre d'avis) après suppression
                 $stmtAvg = $pdo->prepare('SELECT ROUND(COALESCE(AVG(a_note),0)::numeric,1) as avg, COUNT(*) as cnt FROM _avis WHERE id_produit = ? AND a_note IS NOT NULL');
                 $stmtAvg->execute([$idProduit]);
                 $stats = $stmtAvg->fetch(PDO::FETCH_ASSOC);
                 echo json_encode(['success' => true, 'avg' => (float)$stats['avg'], 'countAvis' => (int)$stats['cnt']]);
             } else {
                 throw new Exception('Impossible de supprimer cet avis.');
             }
             exit;

        } elseif ($action === 'vote') {
             if (!$idClient) throw new Exception('Connexion requise.');
             $idAvis = (int)($_POST['id_avis'] ?? 0);

             $stmtExist = $pdo->prepare("SELECT 1 FROM _avis WHERE id_avis = ?");
             $stmtExist->execute([$idAvis]);
             if (!$stmtExist->fetchColumn()) throw new Exception("Cette avis n’existe plus");

             $val = $_POST['value'] ?? '';
             $dbTyp = ($val === 'plus') ? 'like' : (($val === 'minus') ? 'dislike' : null);
             
             if (!$dbTyp) throw new Exception('Vote invalide.');

             $stmtExist = $pdo->prepare("SELECT vote_type FROM _vote_avis WHERE id_client = ? AND id_avis = ?");
             $stmtExist->execute([$idClient, $idAvis]);
             $existing = $stmtExist->fetchColumn();

             $pdo->beginTransaction();
             try {
                 if ($existing === $dbTyp) {
                     $pdo->prepare("DELETE FROM _vote_avis WHERE id_client = ? AND id_avis = ?")->execute([$idClient, $idAvis]);
                     $finalStatus = null;
                 } else {
                     if ($existing) {
                         $pdo->prepare("DELETE FROM _vote_avis WHERE id_client = ? AND id_avis = ?")->execute([$idClient, $idAvis]);
                     }
                     $pdo->prepare("INSERT INTO _vote_avis (id_client, id_avis, vote_type) VALUES (?, ?, ?)")->execute([$idClient, $idAvis, $dbTyp]);
                     $finalStatus = $val;
                 }
                 
                 $stmtCounts = $pdo->prepare("SELECT (SELECT COUNT(*) FROM _vote_avis WHERE id_avis = ? AND vote_type = 'like') as likes, (SELECT COUNT(*) FROM _vote_avis WHERE id_avis = ? AND vote_type = 'dislike') as dislikes");
                 $stmtCounts->execute([$idAvis, $idAvis]);
                 $counts = $stmtCounts->fetch(PDO::FETCH_ASSOC);

                 $pdo->prepare("UPDATE _avis SET a_pouce_bleu = ?, a_pouce_rouge = ? WHERE id_avis = ?")->execute([$counts['likes'], $counts['dislikes'], $idAvis]);
                 
                 $pdo->commit();
                 echo json_encode([
                     'success' => true, 
                     'user_vote' => $finalStatus,
                     'counts' => ['a_pouce_bleu' => $counts['likes'], 'a_pouce_rouge' => $counts['dislikes']]
                 ]);
             } catch (Exception $e) {
                 $pdo->rollBack();
                 throw $e;
             }
             exit;
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

//fonction pour calculer le prix TTC à partir du prix HT et du taux de TVA
function calcPrixTVA($TVA, $prixHT) {
    $resultat = $prixHT;
    if ($TVA > 0) {
        $resultat = $prixHT * (1 + $TVA / 100);  
    }
    return $resultat;
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

//fonction pour inserer une facture
function insererFacture($pdo, $panierEnCours, $nom, $prenom, $f_total_ht, $f_total_remise, $f_total_ttc, $id_adresse) {
    $requeteFacture = "
        INSERT INTO _facture 
        (id_panier, id_adresse, nom_destinataire, prenom_destinataire, f_total_ht, f_total_remise, f_total_ttc)
        
        VALUES
        (
            :id_panier,
            :id_adresse,
            :nom_destinataire,
            :prenom_destinataire,
            :f_total_ht,
            :f_total_remise,
            :f_total_ttc
        )
    ";

    $stmt = $pdo->prepare($requeteFacture);
    $stmt->execute([
        ':id_panier' => $panierEnCours,
        ':id_adresse' => $id_adresse,
        ':nom_destinataire' => $nom,
        ':prenom_destinataire' => $prenom,
        ':f_total_ht' => $f_total_ht,
        ':f_total_remise' => $f_total_remise,
        ':f_total_ttc' => $f_total_ttc
    ]);
}



//fonction pour préparer les catégories pour l'affichage
function preparercategories_affichage($listeCategories)
{
    $categories_affichage = [];
    $total_produits = 0;
    foreach ($listeCategories as $nomCategorie => $compte) {
        $categories_affichage[] = [
            'category' => $nomCategorie,
            'count' => $compte
        ];
    }
    array_unshift($categories_affichage, [
        'category' => 'all',
        'count' => $total_produits
    ]);
    return $categories_affichage;
}

//fonction pour trier les produits selon le critère choisi
function trierProduits($listeProduits, $tri_par)
{
    if (empty($listeProduits)) {
        return $listeProduits;
    }

    switch ($tri_par) {
        case 'prix_croissant':
            usort($listeProduits, function ($a, $b) {
                // Calculer le prix HT après réduction
                $discountA = (float)($a['reduction_pourcentage'] ?? 0);
                $prixHT_A = $a['p_prix'] * (1 - $discountA/100);
                
                $discountB = (float)($b['reduction_pourcentage'] ?? 0);
                $prixHT_B = $b['p_prix'] * (1 - $discountB/100);
                
                // Ajouter la TVA pour obtenir le prix TTC
                $tvaA = (float)($a['tva'] ?? 0);
                $prixTTC_A = $prixHT_A * (1 + $tvaA/100);
                
                $tvaB = (float)($b['tva'] ?? 0);
                $prixTTC_B = $prixHT_B * (1 + $tvaB/100);
                
                // Comparer les prix TTC
                $comparison = $prixTTC_A <=> $prixTTC_B;
                
                // Critère secondaire en cas d'égalité
                if ($comparison === 0) {
                    return $a['id_produit'] <=> $b['id_produit'];
                }
                
                return $comparison;
            });
            break;

        case 'en_promotion':
            usort($listeProduits, function ($a, $b) {
                $isPromoA = (isset($a['estenpromo']) && $a['estenpromo'] > 0) ? 1 : 0;
                $isPromoB = (isset($b['estenpromo']) && $b['estenpromo'] > 0) ? 1 : 0;
                
                $comparison = $isPromoB <=> $isPromoA; // Inversé pour avoir les promos en premier
                
                if ($comparison === 0) {
                    return $a['id_produit'] <=> $b['id_produit'];
                }
                
                return $comparison;
            });
            break;

        case 'en_reduction':
            usort($listeProduits, function ($a, $b) {
                $reductionA = (float)($a['reduction_pourcentage'] ?? 0);
                $reductionB = (float)($b['reduction_pourcentage'] ?? 0);
                
                $comparison = $reductionB <=> $reductionA;
                
                if ($comparison === 0) {
                    return $a['id_produit'] <=> $b['id_produit'];
                }
                
                return $comparison;
            });
            break;
            
        case 'prix_decroissant':
            usort($listeProduits, function ($a, $b) {
                // Calculer le prix HT après réduction
                $discountA = (float)($a['reduction_pourcentage'] ?? 0);
                $prixHT_A = $a['p_prix'] * (1 - $discountA/100);
                
                $discountB = (float)($b['reduction_pourcentage'] ?? 0);
                $prixHT_B = $b['p_prix'] * (1 - $discountB/100);
                
                // Ajouter la TVA pour obtenir le prix TTC
                $tvaA = (float)($a['tva'] ?? 0);
                $prixTTC_A = $prixHT_A * (1 + $tvaA/100);
                
                $tvaB = (float)($b['tva'] ?? 0);
                $prixTTC_B = $prixHT_B * (1 + $tvaB/100);
                
                // Inverser pour décroissant
                $comparison = $prixTTC_B <=> $prixTTC_A;
                
                // Critère secondaire
                if ($comparison === 0) {
                    return $a['id_produit'] <=> $b['id_produit'];
                }
                
                return $comparison;
            });
            break;
            
        case 'note':
            usort($listeProduits, function ($a, $b) {
                $noteA = (float)($a['note_moyenne'] ?? 0);
                $noteB = (float)($b['note_moyenne'] ?? 0);
                
                $comparison = $noteB <=> $noteA;
                
                if ($comparison === 0) {
                    return $a['id_produit'] <=> $b['id_produit'];
                }
                
                return $comparison;
            });
            break;
        
        case 'note_moins':
            usort($listeProduits, function ($a, $b) {
                $noteA = (float)($a['note_moyenne'] ?? 0);
                $noteB = (float)($b['note_moyenne'] ?? 0);
                
                $comparison = $noteA <=> $noteB;
                
                if ($comparison === 0) {
                    return $a['id_produit'] <=> $b['id_produit'];
                }
                
                return $comparison;
            });
            break;

        case 'pires_ventes':
            usort($listeProduits, function ($produitA, $produitB) {
                $nombreVentesA = (int)($produitA['p_nb_ventes'] ?? 0);
                $nombreVentesB = (int)($produitB['p_nb_ventes'] ?? 0);
                
                $comparison = $nombreVentesA <=> $nombreVentesB;
                
                if ($comparison === 0) {
                    return $produitA['id_produit'] <=> $produitB['id_produit'];
                }
                
                return $comparison;
            });
            break;
        
        case 'nouveaute':
            usort($listeProduits, function ($produitA, $produitB) {
                return $produitB['id_produit'] <=> $produitA['id_produit'];
            });
            break;
        
        case 'anciens':
            usort($listeProduits, function ($produitA, $produitB) {
                return $produitA['id_produit'] <=> $produitB['id_produit'];
            });
            break;

        case 'z_a':
            usort($listeProduits, function ($a, $b) {
                $comparison = strcmp($b['p_nom'], $a['p_nom']);
                
                if ($comparison === 0) {
                    return $a['id_produit'] <=> $b['id_produit'];
                }
                
                return $comparison;
            });
            break;
        case 'a_z':
            usort($listeProduits, function ($a, $b) {
                $comparison = strcmp($a['p_nom'], $b['p_nom']);
                
                if ($comparison === 0) {
                    return $a['id_produit'] <=> $b['id_produit'];
                }
                
                return $comparison;
            });
            break;
            
        case 'meilleures_ventes':
        default:
            usort($listeProduits, function ($produitA, $produitB) {
                $nombreVentesA = (int)($produitA['p_nb_ventes'] ?? 0);
                $nombreVentesB = (int)($produitB['p_nb_ventes'] ?? 0);
                
                $comparison = $nombreVentesB <=> $nombreVentesA;
                
                if ($comparison === 0) {
                    return $produitA['id_produit'] <=> $produitB['id_produit'];
                }
                
                return $comparison;
            });
            break;
    }

    return $listeProduits;
}

//fonction pour filtrer les produits selon les critères choisis
function filtrerProduits($listeProduits, $filtres)
{
    $produits_filtres = [];
    foreach ($listeProduits as $produitCourant) {
        // Calculer le prix final (avec réduction et TVA)
        $prixBase = (float)($produitCourant['p_prix'] ?? 0);
        $reduction = (float)($produitCourant['reduction_pourcentage'] ?? 0);
        $tva = (float)($produitCourant['tva'] ?? 0);

        // Prix HT après réduction
        $prixHT = $reduction > 0 ? $prixBase * (1 - $reduction / 100) : $prixBase;

        // Prix TTC (avec TVA)
        $prixFinal = $prixHT * (1 + $tva / 100);

        
        // Filtrer par prix (utiliser le prix avec réduction si applicable)
        // Comparaison en centimes pour éviter problèmes d'arrondi
        $minFilter = isset($filtres['prixMinimum']) ? (float) $filtres['prixMinimum'] : 0.0;
        $maxFilter = isset($filtres['prixMaximum']) ? (float) $filtres['prixMaximum'] : INF;

        $prixFinalCents = (int) round($prixFinal * 100);
        $minFilterCents = (int) round($minFilter * 100);
        $maxFilterCents = is_infinite($maxFilter) ? PHP_INT_MAX : (int) round($maxFilter * 100);

        if ($prixFinalCents > $maxFilterCents) continue;
        if ($prixFinalCents < $minFilterCents) continue;
        if ($filtres['categorieFiltre'] !== 'all') {
            $categoriesProduit = explode(', ', $produitCourant['categories'] ?? '');
            if (!in_array($filtres['categorieFiltre'], $categoriesProduit))
                continue;
        }
        if ($filtres['enStockSeulement'] && ($produitCourant['p_stock'] ?? 0) <= 0)
            continue;
        if (($produitCourant['note_moyenne'] ?? 0) < $filtres['noteMinimum'])
            continue;
        $produits_filtres[] = $produitCourant;
    }
    return $produits_filtres;
}

function genererUrlTri($type_tri) {
    $params = $_GET;
    $params['tri'] = $type_tri;
    return "?" . http_build_query($params);
}

function recupInfoPourFactureArticle($pdo, $id_produit){
    //calcul le prix d'un produit apres les remises et la tva ne calcul pas avec la quantite
    $reqFacture = "
                SELECT p_prix, montant_tva, reduction_pourcentage 
                FROM _produit 
                JOIN _tva ON _produit.id_tva = _tva.id_tva
                JOIN _reduction ON _produit.id_produit = _reduction.id_produit
                WHERE _produit.id_produit = :id_produit;
    ";
    $stmt = $pdo->prepare($reqFacture);
    $stmt->execute([':id_produit' => $id_produit]);
    $donnees = $stmt->fetch();
    return $donnees;
}     

function ProduitDenominationVendeur($pdo, $denomination) {
    $reqDenomination = "
        SELECT DISTINCT ON (p.id_produit)
            p.id_produit,
            p.p_nom,
            p.p_description,
            p.p_prix,
            p.p_stock,
            p.p_nb_ventes,
            r.reduction_pourcentage,
            t.montant_tva as tva,
            pr.id_produit as estEnpromo,
            (SELECT COUNT(*) FROM cobrec1._avis av2 WHERE av2.id_produit = p.id_produit AND av2.a_note IS NOT NULL) as nombre_avis,
            (SELECT ROUND(COALESCE(AVG(av3.a_note), 0)::numeric, 1) FROM cobrec1._avis av3 WHERE av3.id_produit = p.id_produit AND av3.a_note IS NOT NULL) as note_moyenne,
            (SELECT COALESCE(i2.i_lien, '/img/photo/smartphone_xpro.jpg') FROM cobrec1._represente_produit rp2 LEFT JOIN cobrec1._image i2 ON rp2.id_image = i2.id_image WHERE rp2.id_produit = p.id_produit LIMIT 1) as image_url,
            STRING_AGG(DISTINCT cp.nom_categorie, ', ') as categories,
            v.denomination,
            v.raison_sociale AS vendeur_nom
        FROM cobrec1._produit p
        INNER JOIN cobrec1._vendeur v ON p.id_vendeur = v.id_vendeur
        LEFT JOIN cobrec1._reduction r ON p.id_produit = r.id_produit 
            AND CURRENT_TIMESTAMP BETWEEN r.reduction_debut AND r.reduction_fin
        LEFT JOIN cobrec1._promotion pr ON p.id_produit = pr.id_produit 
            AND CURRENT_TIMESTAMP BETWEEN pr.promotion_debut AND pr.promotion_fin
        LEFT JOIN cobrec1._tva t ON p.id_tva = t.id_tva
        LEFT JOIN cobrec1._fait_partie_de fpd ON p.id_produit = fpd.id_produit
        LEFT JOIN cobrec1._categorie_produit cp ON fpd.id_categorie = cp.id_categorie
        WHERE v.denomination ILIKE :denomination
            AND p.p_statut = 'En ligne'
        GROUP BY p.id_produit, p.p_nom, p.p_description, p.p_prix, p.p_stock, 
                 p.p_statut, r.reduction_pourcentage, pr.id_produit, t.montant_tva,
                 v.denomination, v.raison_sociale
        ORDER BY p.id_produit
    ";
    
    $stmt = $pdo->prepare($reqDenomination);
    $stmt->execute(['denomination' => '%' . $denomination . '%']);
    $donnees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $donnees;
}

function chercherProduitsNom($pdo, $nomProduit) {
    $reqNomProduit = "
        SELECT 
            p.id_produit,
            p.p_nom,
            p.p_description,
            p.p_prix,
            p.p_stock,
            p.p_nb_ventes,
            r.reduction_pourcentage,
            t.montant_tva as tva,
            pr.id_produit as estEnpromo,
            (SELECT COUNT(*) 
             FROM cobrec1._avis av2 
             WHERE av2.id_produit = p.id_produit AND av2.a_note IS NOT NULL) as nombre_avis,
            (SELECT ROUND(COALESCE(AVG(av3.a_note), 0)::numeric, 1) 
             FROM cobrec1._avis av3 
             WHERE av3.id_produit = p.id_produit AND av3.a_note IS NOT NULL) as note_moyenne,
            (SELECT COALESCE(i2.i_lien, '/img/photo/smartphone_xpro.jpg') 
             FROM cobrec1._represente_produit rp2 
             LEFT JOIN cobrec1._image i2 ON rp2.id_image = i2.id_image 
             WHERE rp2.id_produit = p.id_produit 
             LIMIT 1) as image_url,
            STRING_AGG(DISTINCT cp.nom_categorie, ', ') as categories,
            v.denomination,
            v.raison_sociale AS vendeur_nom
        FROM cobrec1._produit p
        INNER JOIN cobrec1._vendeur v ON p.id_vendeur = v.id_vendeur
        LEFT JOIN cobrec1._reduction r ON p.id_produit = r.id_produit 
            AND CURRENT_TIMESTAMP BETWEEN r.reduction_debut AND r.reduction_fin
        LEFT JOIN cobrec1._promotion pr ON p.id_produit = pr.id_produit 
            AND CURRENT_TIMESTAMP BETWEEN pr.promotion_debut AND pr.promotion_fin
        LEFT JOIN cobrec1._tva t ON p.id_tva = t.id_tva
        LEFT JOIN cobrec1._fait_partie_de fpd ON p.id_produit = fpd.id_produit
        LEFT JOIN cobrec1._categorie_produit cp ON fpd.id_categorie = cp.id_categorie
        WHERE p.p_nom ILIKE :nomProduit
            AND p.p_statut = 'En ligne'
        GROUP BY p.id_produit, p.p_nom, p.p_description, p.p_prix, p.p_stock, 
                 p.p_statut, r.reduction_pourcentage, pr.id_produit, t.montant_tva,
                 v.denomination, v.raison_sociale
        ORDER BY p.p_nom ASC
    ";

    $stmt = $pdo->prepare($reqNomProduit);
    $stmt->execute(['nomProduit' => "%$nomProduit%"]);
    $donnees = $stmt->fetchAll();

    return $donnees;
}


function recupNomVendeurIdProduit($pdo, $id_produit){
    $reqVendeur = "
                SELECT v.denomination 
                FROM _produit p
                JOIN _vendeur v ON p.id_vendeur = v.id_vendeur
                WHERE p.id_produit = :id_produit;
    ";
    $stmt = $pdo->prepare($reqVendeur);
    $stmt->execute([':id_produit' => $id_produit]);
    $donnees = $stmt->fetch();
    return $donnees['denomination'];
}

function recupOrigineProduit($pdo, $id_produit){
    $reqOrigine = "
                SELECT p.p_origine 
                FROM _produit p
                WHERE p.id_produit = :id_produit;
    ";
    $stmt = $pdo->prepare($reqOrigine);
    $stmt->execute([':id_produit' => $id_produit]);
    $donnees = $stmt->fetch();
    return $donnees['p_origine'];
}

function calcul_f_total_ttc($pdo, $id_panier, $vendeur=-1){
    if ($vendeur == -1){
        $sql = '
        SELECT quantite, prix_unitaire, remise_unitaire, TVA FROM cobrec1._contient
        WHERE id_panier = :panier_commande;'
        ;
        $stmt = $pdo->prepare($sql);
        $params = [
            'panier_commande' => $id_panier
        ];
    }else{
        $sql = '
        SELECT id_vendeur, quantite, prix_unitaire, remise_unitaire, TVA FROM cobrec1._contient
        INNER JOIN cobrec1._produit ON _contient.id_produit = _produit.id_produit
        WHERE id_panier = :panier_commande AND id_vendeur = :id_vendeur;'
        ;
        $stmt = $pdo->prepare($sql);
        $params = [
            'panier_commande' => $id_panier,
            'id_vendeur' => $vendeur
        ];
    }
    $stmt->execute($params);
    $contient = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalTTC = 0;
    foreach ($contient as $key => $value) {
        $totalTTC += round((($value["quantite"] * $value["prix_unitaire"]) - (($value['remise_unitaire'] / 100) * $value["prix_unitaire"]  * $value["quantite"])) * (1 + $value["tva"]/100), 2);
    }
    return $totalTTC;
}