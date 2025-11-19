<?php
session_start();
require_once '../../selectBDD.php';

$pdo->exec("SET search_path TO cobrec1");

// --- FONCTIONS ---

/**
 * Charge les informations d'un produit et ses images
 */
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
                (SELECT STRING_AGG(cp.nom_categorie, ', ')
                   FROM _fait_partie_de fpd
                   JOIN _categorie_produit cp ON fpd.id_categorie = cp.id_categorie
                  WHERE fpd.id_produit = p.id_produit) AS categories
            FROM _produit p
            LEFT JOIN _en_reduction er ON p.id_produit = er.id_produit
            LEFT JOIN _reduction r ON er.id_reduction = r.id_reduction
            WHERE p.id_produit = :pid
            LIMIT 1");
        $stmtProd->execute([':pid' => $idProduit]);
        $produit = $stmtProd->fetch(PDO::FETCH_ASSOC);

        if (!$produit) return null;

        // Images
        $stmtImgs = $pdo->prepare("SELECT i.i_lien
                                     FROM _represente_produit rp
                                     JOIN _image i ON rp.id_image = i.id_image
                                    WHERE rp.id_produit = :pid
                                 ORDER BY rp.id_image ASC");
        $stmtImgs->execute([':pid' => $idProduit]);
        $images = $stmtImgs->fetchAll(PDO::FETCH_COLUMN) ?: [];
        
        // Nettoyage URLs images
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

/**
 * Charge les avis et réponses pour un produit
 */
function chargerAvisBDD($pdo, $idProduit) {
    $avis = [];
    $reponses = [];
    
    try {
        // Avis
        $stmtAvis = $pdo->prepare("
            SELECT 
                a.id_avis,
                a.a_texte,
                a.a_timestamp_creation,
                TO_CHAR(a.a_timestamp_creation,'YYYY-MM-DD HH24:MI') AS a_timestamp_fmt,
                a.a_pouce_bleu,
                a.a_pouce_rouge,
                a.a_note,
                a.a_owner_token,
                COALESCE(ROUND(AVG(c.a_note)::numeric, 1), a.a_note, 0) AS avis_note
            FROM _avis a
            LEFT JOIN _commentaire c ON c.id_avis = a.id_avis
            WHERE a.id_produit = :pid
            GROUP BY a.id_avis, a.a_texte, a.a_timestamp_creation, a.a_pouce_bleu, a.a_pouce_rouge, a.a_note, a.a_owner_token
            ORDER BY a.a_timestamp_creation DESC
        ");
        $stmtAvis->execute([':pid' => $idProduit]);
        $avis = $stmtAvis->fetchAll(PDO::FETCH_ASSOC);

        // Réponses
        $stmtRep = $pdo->prepare("SELECT r.id_avis_parent, a.id_avis, a.a_texte, TO_CHAR(a.a_timestamp_creation,'YYYY-MM-DD HH24:MI') AS a_timestamp_fmt FROM _reponse r JOIN _avis a ON r.id_avis = a.id_avis WHERE a.id_produit = :pid");
        $stmtRep->execute([':pid' => $idProduit]);
        $rowsRep = $stmtRep->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rowsRep as $r) {
            $reponses[(int)$r['id_avis_parent']] = $r;
        }
    } catch (Exception $e) {
        // Silencieux
    }
    
    return ['avis' => $avis, 'reponses' => $reponses];
}

/**
 * Ajoute un article au panier (BDD)
 */
function ajouterArticleBDD($pdo, $idProduit, $idPanier, $quantite = 1) {
    try {
        $sqlProduit = "SELECT p.p_prix, p.p_frais_de_port, p.p_stock, COALESCE(t.montant_tva,0) AS tva, COALESCE(r.reduction_pourcentage,0) AS pourcentage_reduction
                       FROM _produit p
                       LEFT JOIN _tva t ON p.id_tva = t.id_tva
                       LEFT JOIN _en_reduction er ON p.id_produit = er.id_produit
                       LEFT JOIN _reduction r ON er.id_reduction = r.id_reduction
                       WHERE p.id_produit = :idProduit";
        $stmtProduit = $pdo->prepare($sqlProduit);
        $stmtProduit->execute([':idProduit' => $idProduit]);
        $produit = $stmtProduit->fetch(PDO::FETCH_ASSOC);
        
        if (!$produit) return ['success' => false, 'message' => 'Produit introuvable'];
        
        $quantite = max(1, (int) $quantite);
        $prixUnitaire = $produit['p_prix'];
        $remiseUnitaire = ($produit['pourcentage_reduction'] / 100) * $prixUnitaire;
        $fraisDePort = $produit['p_frais_de_port'];
        $tva = $produit['tva'];
        $stock = (int) ($produit['p_stock'] ?? 0);

        $stmtCheck = $pdo->prepare("SELECT quantite FROM _contient WHERE id_produit = :idProduit AND id_panier = :idPanier");
        $stmtCheck->execute([':idProduit' => $idProduit, ':idPanier' => $idPanier]);
        $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        $quantiteExistante = $existe ? (int) $existe['quantite'] : 0;
        $disponible = max(0, $stock - $quantiteExistante);
        
        if ($disponible <= 0) return ['success' => false, 'message' => 'Stock insuffisant: quantité maximale déjà atteinte dans votre panier'];
        
        $aAjouter = min($quantite, $disponible);
        
        if ($existe) {
            $stmtUpdate = $pdo->prepare("UPDATE _contient SET quantite = quantite + :q WHERE id_produit = :idProduit AND id_panier = :idPanier");
            $stmtUpdate->execute([':q' => $aAjouter, ':idProduit' => $idProduit, ':idPanier' => $idPanier]);
        } else {
            $stmtInsert = $pdo->prepare("INSERT INTO _contient (id_produit, id_panier, quantite, prix_unitaire, remise_unitaire, frais_de_port, tva) VALUES (:idProduit, :idPanier, :q, :pu, :ru, :fdp, :tva)");
            $stmtInsert->execute([
                ':idProduit' => $idProduit, ':idPanier' => $idPanier, ':q' => $aAjouter,
                ':pu' => $prixUnitaire, ':ru' => $remiseUnitaire, ':fdp' => $fraisDePort, ':tva' => $tva
            ]);
        }
        
        if ($aAjouter < $quantite) {
            return ['success' => true, 'message' => 'Seuls ' . $aAjouter . ' article(s) ajoutés (stock limité).'];
        }
        return ['success' => true, 'message' => 'Article ajouté au panier'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

/**
 * Gestion des actions AJAX pour les avis
 */
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
            
            // Vérif achat
            $stmtVerif = $pdo->prepare("SELECT 1 FROM _contient c JOIN _panier_commande pc ON c.id_panier = pc.id_panier WHERE pc.id_client = :cid AND c.id_produit = :pid AND pc.timestamp_commande IS NOT NULL LIMIT 1");
            $stmtVerif->execute([':cid' => $idClient, ':pid' => $idProduitPost]);
            if (!$stmtVerif->fetchColumn()) { echo json_encode(['success' => false, 'message' => 'Achat requis']); exit; }
            
            $texte = trim($_POST['commentaire'] ?? '');
            $note = (float)($_POST['note'] ?? 0.0);
            if (!in_array($note, [0.5, 1.0, 1.5, 2.0, 2.5, 3.0, 3.5, 4.0, 4.5, 5.0], true)) $note = 0.0;
            if ($note <= 0) { echo json_encode(['success' => false, 'message' => 'Note invalide']); exit; }

            $ownerToken = $_COOKIE['alizon_owner'] ?? bin2hex(random_bytes(16));
            if (!isset($_COOKIE['alizon_owner'])) setcookie('alizon_owner', $ownerToken, time() + 3600*24*365, '/');

            // Insertion
            try {
                $pdo->exec('ALTER TABLE _avis ADD COLUMN IF NOT EXISTS a_note numeric(2,1)');
                $pdo->exec('ALTER TABLE _avis ADD COLUMN IF NOT EXISTS a_owner_token text');
                $stmt = $pdo->prepare("INSERT INTO _avis (id_produit, a_texte, a_pouce_bleu, a_pouce_rouge, a_timestamp_creation, a_note, a_owner_token) VALUES (:pid, :txt, 0, 0, NOW(), :note, :owner) RETURNING id_avis, a_timestamp_creation, TO_CHAR(a_timestamp_creation,'YYYY-MM-DD HH24:MI') AS created_at_fmt, a_note");
                $stmt->execute([':pid' => $idProduitPost, ':txt' => $texte, ':note' => $note, ':owner' => $ownerToken]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                // Fallback si colonnes manquantes (ne devrait pas arriver si BDD à jour)
                $stmt = $pdo->prepare("INSERT INTO _avis (id_produit, a_texte, a_pouce_bleu, a_pouce_rouge, a_timestamp_creation) VALUES (:pid, :txt, 0, 0, NOW()) RETURNING id_avis, a_timestamp_creation, TO_CHAR(a_timestamp_creation,'YYYY-MM-DD HH24:MI') AS created_at_fmt");
                $stmt->execute([':pid' => $idProduitPost, ':txt' => $texte]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $row['a_note'] = 0.0;
            }

            // Stats
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
            $prev = $_POST['prev'] ?? '';
            if ($idAvis <= 0 || !in_array($val, ['plus', 'minus'])) { echo json_encode(['success' => false]); exit; }
            
            $sql = "";
            if ($prev === $val) { // Retrait vote
                $sql = ($val === 'plus') ? 'UPDATE _avis SET a_pouce_bleu = GREATEST(a_pouce_bleu - 1, 0) WHERE id_avis = :id' : 'UPDATE _avis SET a_pouce_rouge = GREATEST(a_pouce_rouge - 1, 0) WHERE id_avis = :id';
            } else { // Nouveau vote ou changement
                if ($prev === 'plus' && $val === 'minus') $sql = 'UPDATE _avis SET a_pouce_bleu = GREATEST(a_pouce_bleu - 1, 0), a_pouce_rouge = a_pouce_rouge + 1 WHERE id_avis = :id';
                elseif ($prev === 'minus' && $val === 'plus') $sql = 'UPDATE _avis SET a_pouce_rouge = GREATEST(a_pouce_rouge - 1, 0), a_pouce_bleu = a_pouce_bleu + 1 WHERE id_avis = :id';
                elseif ($val === 'plus') $sql = 'UPDATE _avis SET a_pouce_bleu = a_pouce_bleu + 1 WHERE id_avis = :id';
                else $sql = 'UPDATE _avis SET a_pouce_rouge = a_pouce_rouge + 1 WHERE id_avis = :id';
            }
            $sql .= ' RETURNING a_pouce_bleu, a_pouce_rouge';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $idAvis]);
            echo json_encode(['success' => true, 'counts' => $stmt->fetch(PDO::FETCH_ASSOC)]);
            exit;

        } elseif ($action === 'edit_avis') {
            $idAvis = (int)($_POST['id_avis'] ?? 0);
            $txt = trim($_POST['commentaire'] ?? '');
            $note = isset($_POST['note']) ? (float)$_POST['note'] : null;
            $owner = $_COOKIE['alizon_owner'] ?? '';
            
            $set = ['a_texte = :txt', 'a_timestamp_modification = NOW()'];
            $params = [':txt' => $txt, ':id' => $idAvis, ':pid' => $idProduitPost, ':owner' => $owner];
            if ($note !== null) { $set[] = 'a_note = :note'; $params[':note'] = $note; }
            
            $stmt = $pdo->prepare('UPDATE _avis SET ' . implode(', ', $set) . ' WHERE id_avis = :id AND id_produit = :pid AND a_owner_token = :owner RETURNING TO_CHAR(a_timestamp_modification,\'YYYY-MM-DD HH24:MI\') AS fmt, a_note');
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) echo json_encode(['success' => true, 'updated_at_fmt' => $row['fmt'], 'note' => $row['a_note']]);
            else echo json_encode(['success' => false, 'message' => 'Non autorisé']);
            exit;

        } elseif ($action === 'delete_avis') {
            $idAvis = (int)($_POST['id_avis'] ?? 0);
            $owner = $_COOKIE['alizon_owner'] ?? '';
            $stmt = $pdo->prepare('DELETE FROM _avis WHERE id_avis = :id AND id_produit = :pid AND a_owner_token = :owner RETURNING id_avis');
            $stmt->execute([':id' => $idAvis, ':pid' => $idProduitPost, ':owner' => $owner]);
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
        echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
        exit;
    }
}

// --- LOGIQUE PRINCIPALE ---

// 1. Gestion Session / Panier
$idClient = isset($_SESSION['id']) ? (int)$_SESSION['id'] : null;
if ($idClient) {
    try {
        $stmt = $pdo->prepare("SELECT id_panier FROM _panier_commande WHERE timestamp_commande IS NULL AND id_client = :id");
        $stmt->execute([':id' => $idClient]);
        $panier = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($panier) {
            $_SESSION['panierEnCours'] = (int)$panier['id_panier'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO _panier_commande (id_client) VALUES (:id) RETURNING id_panier");
            $stmt->execute([':id' => $idClient]);
            $_SESSION['panierEnCours'] = (int)$stmt->fetchColumn();
        }
    } catch (Exception $e) {}
}

// 2. Récupération ID Produit
$idProduit = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 3. Traitement POST (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'ajouter_panier') {
        header('Content-Type: application/json');
        $idPanier = $_SESSION['panierEnCours'] ?? null;
        if (!$idPanier) { echo json_encode(['success' => false, 'message' => 'Connexion requise']); exit; }
        
        $idP = (int)($_POST['idProduit'] ?? 0);
        $qty = (int)($_POST['quantite'] ?? 1);
        echo json_encode(ajouterArticleBDD($pdo, $idP, $idPanier, $qty));
        exit;
    }
    
    if (isset($_POST['id_produit'])) {
        gererActionsAvis($pdo, $idClient, $idProduit);
    }
}

// 4. Chargement Données
$produit = ($idProduit > 0) ? chargerProduitBDD($pdo, $idProduit) : null;

if (!$produit) {
    include __DIR__ . '/not-found.php';
    exit;
}

$donneesAvis = chargerAvisBDD($pdo, $idProduit);
$avisTextes = $donneesAvis['avis'];
$reponsesMap = $donneesAvis['reponses'];

// Calculs affichage
$estEnRupture = ($produit['p_stock'] <= 0);
$discount = (float)$produit['pourcentage_reduction'];
$prixFinal = ($discount > 0) ? $produit['p_prix'] * (1 - $discount/100) : $produit['p_prix'];

// Note moyenne (recalculée pour être sûr)
try {
    $stmt = $pdo->prepare('SELECT ROUND(COALESCE(AVG(a_note),0)::numeric,1) as avg, COUNT(*) as cnt FROM _avis WHERE id_produit = :pid AND a_note IS NOT NULL');
    $stmt->execute([':pid' => $idProduit]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $note = (float)$stats['avg'];
    $nbAvis = (int)$stats['cnt'];
} catch (Exception $e) { $note = 0.0; $nbAvis = 0; }

$noteEntiere = (int)floor($note);
$images = $produit['images'];
$mainImage = $images[0] ?? '/img/Photo/default.png';
$hasMultipleImages = count($images) > 1;

// Vérif achat pour formulaire avis
$clientAachete = false;
if ($idClient) {
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM _contient c JOIN _panier_commande pc ON c.id_panier = pc.id_panier WHERE pc.id_client = :cid AND c.id_produit = :pid AND pc.timestamp_commande IS NOT NULL LIMIT 1");
        $stmt->execute([':cid' => $idClient, ':pid' => $idProduit]);
        $clientAachete = (bool)$stmt->fetchColumn();
    } catch (Exception $e) {}
}
$ownerTokenServer = $_COOKIE['alizon_owner'] ?? '';

?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title><?= htmlspecialchars($produit['p_nom']) ?> – Alizon</title>
    <link rel="icon" type="image/png" href="../../img/favicon.svg">
    <link rel="stylesheet" href="/styles/ViewProduit/stylesView-Produit.css" />
    <link rel="stylesheet" href="/styles/Header/stylesHeader.css">
    <link rel="stylesheet" href="/styles/Footer/stylesFooter.css">
</head>
<body>
    <div id="header"></div>

    <nav class="page-breadcrumb">
        <a class="btn btn-retour-catalogue back-link" href="/index.php" onclick="if (history.length>1) { history.back(); return false; }" aria-label="Retour au catalogue">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span>Retour au catalogue</span>
        </a>
    </nav>

    <main class="container">
        <div class="product-row <?= $hasMultipleImages ? '' : 'no-thumbs' ?>">
            <!-- Vignettes -->
            <?php if ($hasMultipleImages): ?>
            <aside class="thumbs" aria-label="Vignettes du produit">
                <?php foreach ($images as $idx => $imgUrl): ?>
                    <img class="thumb <?= $idx === 0 ? 'is-active' : '' ?>" src="<?= htmlspecialchars($imgUrl) ?>" alt="Vignette <?= $idx + 1 ?>"
                         loading="lazy" data-src="<?= htmlspecialchars($imgUrl) ?>" role="button" tabindex="0" aria-label="Afficher l'image <?= $idx + 1 ?>" />
                <?php endforeach; ?>
            </aside>
            <?php endif; ?>

            <!-- Image principale -->
            <section class="main-image">
                <img id="productMainImage" src="<?= htmlspecialchars($mainImage) ?>" alt="<?= htmlspecialchars($produit['p_nom']) ?>" />
            </section>

            <!-- Colonne droite - résumé produit -->
            <aside class="summary">
                <div class="title"><?= htmlspecialchars($produit['p_nom']) ?></div>
                <div class="rating">
                    <span class="stars" id="summaryStars" aria-hidden="true">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <img src="/img/svg/star-<?= $i <= $noteEntiere ? 'full' : 'empty' ?>.svg" alt="Etoile" width="20">
                        <?php endfor; ?>
                    </span>
                    <span id="summaryRatingValue" style="color:var(--muted);font-weight:600"><?= number_format($note, 1) ?></span>
                    <span id="summaryRatingCount" style="color:var(--muted)">(<?= $nbAvis ?>)</span>
                </div>
                <div class="price">
                    <?= number_format($prixFinal, 2, ',', ' ') ?> €
                    <?php if ($discount > 0): ?>
                        <span class="old"><?= number_format($produit['p_prix'], 2, ',', ' ') ?> €</span>
                        <span style="background:#D4183D;color:#fff;padding:6px 10px;border-radius:24px;font-size:13px; margin-left: 1em;">-<?= round($discount) ?>%</span>
                    <?php endif; ?>
                </div>

                <div class="qty">
                    <div class="qty-control <?= $estEnRupture ? 'disabled' : '' ?>" role="group" aria-label="Choisir la quantité">
                        <button type="button" class="ghost" onclick="updateQty(-1)" aria-label="Réduire quantité">−</button>
                        <input type="number" id="qtyInput" min="1" step="1" max="<?= (int)$produit['p_stock'] ?>" value="<?= $estEnRupture ? 0 : 1 ?>" aria-label="Quantité" <?= $estEnRupture ? 'disabled' : '' ?> />
                        <button type="button" class="ghost" onclick="updateQty(1)" aria-label="Augmenter quantité" <?= $estEnRupture ? 'disabled' : '' ?>>+</button>
                    </div>
                    <button class="btn <?= $estEnRupture ? 'disabled' : '' ?>" <?= $estEnRupture ? 'disabled' : '' ?> onclick="ajouterAuPanier(<?= $produit['id_produit'] ?>)">
                        <?= $estEnRupture ? 'Rupture de stock' : 'Ajouter au panier' ?>
                    </button>
                </div>

                <div class="summary-actions">
                    <button class="ghost">Ajouter aux favoris</button>
                    <button class="ghost">Partager</button>
                </div>

                <div class="meta" style="margin-top:12px">
                    Stock : <strong><?= $estEnRupture ? 'Rupture' : $produit['p_stock'] . ' disponible(s)' ?></strong>
                </div>
                <div class="meta">Livraison prévue : <strong>3-5 jours ouvrés</strong></div>

                <div class="section features">
                    <h3>Caractéristiques</h3>
                    <ul>
                        <li>Catégorie : <?= htmlspecialchars(explode(', ', $produit['categories'])[0] ?? 'Général') ?></li>
                        <li>Référence : #<?= $produit['id_produit'] ?></li>
                        <li>Statut : <?= htmlspecialchars($produit['p_statut']) ?></li>
                    </ul>
                </div>

                <div class="section contact" style="font-size:13px;color:var(--muted)">
                    <strong>Contact</strong>
                    <div style="margin-top:6px">Service client • <a href="mailto:contact@alizon.com">contact@alizon.com</a></div>
                </div>
            </aside>
        </div>

        <!-- Description et avis -->
        <section class="section">
            <h3>Description</h3>
            <div style="display:flex;gap:8px;margin:8px 0 14px 0">
                <span style="background:#f3f5ff;color:var(--accent);padding:6px 10px;border-radius:24px;font-size:13px">
                    <?= htmlspecialchars(explode(', ', $produit['categories'])[0] ?? 'Général') ?>
                </span>
                <?php if ($produit['p_nb_ventes'] > 100): ?>
                    <span style="background:#d4edda;color:#155724;padding:6px 10px;border-radius:24px;font-size:13px">Populaire</span>
                <?php endif; ?>
            </div>
            <p style="color:var(--muted);line-height:1.6">
                <?= htmlspecialchars($produit['p_description'] ?? 'Description non disponible.') ?>
            </p>
        </section>

        <section class="section reviews">
            <h3>Avis clients</h3>
            <div style="margin-bottom:20px;padding:15px;background:#f8f9fa;border-radius:8px">
                <div style="font-size:14px;color:var(--muted);margin-bottom:8px">Note moyenne</div>
                <div style="display:flex;align-items:center;gap:10px">
                    <span id="reviewsRatingValue" style="font-size:32px;font-weight:700;color:var(--accent)"><?= number_format($note, 1) ?></span>
                    <div>
                        <div class="stars" id="reviewsStars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <img src="/img/svg/star-<?= $i <= $noteEntiere ? 'full' : 'empty' ?>.svg" alt="Etoile" width="16">
                            <?php endfor; ?>
                        </div>
                        <div id="reviewsRatingCount" style="font-size:13px;color:var(--muted);margin-top:4px">Basé sur <?= $nbAvis ?> avis</div>
                    </div>
                </div>
            </div>

            <!-- Formulaire avis -->
            <?php if ($idClient && $clientAachete): ?>
                <div class="review new-review-card" id="newReviewCard">
                    <div class="review-head">
                        <div class="review-head-left">
                            <div class="avatar">V</div>
                            <div class="review-head-texts">
                                <div class="review-author">Vous</div>
                                <div class="review-subtitle">Laisser un avis</div>
                            </div>
                        </div>
                        <div class="review-head-right">
                            <div class="star-input" id="inlineStarInput" title="Sélectionnez une note">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <button type="button" data-value="<?= $i ?>" aria-label="<?= $i ?> étoiles"><img src="/img/svg/star-empty.svg" alt=""></button>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" id="inlineNote" name="note" value="0">
                        </div>
                    </div>
                    <form id="inlineReviewForm" class="review-form">
                        <textarea name="commentaire" id="inlineComment" rows="3" class="review-textarea" placeholder="Partagez votre avis..."></textarea>
                        <div class="review-actions">
                            <small class="review-hint">Merci de rester courtois.</small>
                            <button type="button" class="btn" id="inlineSubmit">Publier</button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="review new-review-card" style="background:#f3f4f7;opacity:.85">
                    <div style="padding:12px 16px;font-size:14px;color:#555">
                        <?= !$idClient ? 'Connectez-vous pour laisser un avis.' : 'Vous devez avoir acheté ce produit pour laisser un avis.' ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Liste des avis -->
            <div id="listeAvisProduit">
                <?php if (empty($avisTextes)): ?>
                    <p style="color:#666;">Aucun avis pour le moment. Soyez le premier !</p>
                <?php else: ?>
                    <?php foreach ($avisTextes as $ta): 
                        $aNote = (float)($ta['avis_note'] ?? 0);
                        $aNoteEntiere = (int)floor($aNote);
                    ?>
                        <div class="review" data-avis-id="<?= (int)$ta['id_avis'] ?>" style="margin-bottom:12px;">
                            <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
                                <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(180deg,#eef1ff,#ffffff);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--accent)">U</div>
                                <div>
                                    <div style="font-weight:700">Utilisateur</div>
                                    <div style="color:var(--muted);font-size:13px">Avis</div>
                                    <div style="display:flex;align-items:center;gap:6px;margin-top:4px">
                                        <span class="stars" aria-hidden="true">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <img src="/img/svg/star-<?= $i <= $aNoteEntiere ? 'full' : 'empty' ?>.svg" alt="Etoile" width="16">
                                            <?php endfor; ?>
                                        </span>
                                        <span style="color:var(--muted);font-weight:600;"><?= number_format($aNote, 1) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="review-content" style="color:var(--muted)"><?= htmlspecialchars($ta['a_texte']) ?></div>
                            <div class="review-votes" style="display:flex;align-items:center;gap:10px;margin-top:8px">
                                <button class="ghost btn-vote" data-type="plus" aria-label="Vote plus">
                                    <img src="/img/svg/plus.svg" alt="Plus" width="16" height="16"> <span class="like-count"><?= (int)$ta['a_pouce_bleu'] ?></span>
                                </button>
                                <button class="ghost btn-vote" data-type="minus" aria-label="Vote moins">
                                    <img src="/img/svg/minus.svg" alt="Moins" width="16" height="16"> <span class="dislike-count"><?= (int)$ta['a_pouce_rouge'] ?></span>
                                </button>
                                <span style="font-size:12px;color:#888;margin-left:auto;"><?= htmlspecialchars($ta['a_timestamp_fmt'] ?? '') ?></span>
                                <?php if ($ownerTokenServer && isset($ta['a_owner_token']) && $ta['a_owner_token'] === $ownerTokenServer): ?>
                                    <button class="ghost btn-edit-review" style="margin-left:8px;font-size:12px;padding:4px 8px;">Modifier</button>
                                    <button class="ghost btn-delete-review" style="margin-left:4px;font-size:12px;padding:4px 8px;color:#b00020;border-color:#f3d3d8;">Supprimer</button>
                                <?php endif; ?>
                            </div>
                            <?php if(isset($reponsesMap[(int)$ta['id_avis']])): $rep = $reponsesMap[(int)$ta['id_avis']]; ?>
                                <div class="review" style="margin:12px 0 4px 48px;padding:10px 12px;background:#fff6e6;border:1px solid #ffe0a3;border-radius:8px">
                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                                        <div style="width:32px;height:32px;border-radius:50%;background:#ffc860;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#7a4d00">V</div>
                                        <div style="font-weight:600;color:#7a4d00">Réponse du vendeur</div>
                                        <span style="margin-left:auto;font-size:11px;color:#b07200;"><?= htmlspecialchars($rep['a_timestamp_fmt'] ?? '') ?></span>
                                    </div>
                                    <div style="font-size:13px;color:#7a4d00;line-height:1.4"><?= htmlspecialchars($rep['a_texte']) ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <div id="footer"></div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="/js/HL_import.js"></script>
    <script src="/js/notifications.js"></script>
    <script>
        // Fonctions utilitaires
        async function fetchJson(url, options) {
            const resp = await fetch(url, options || {});
            if (!resp.ok) throw new Error('Erreur réseau');
            return resp.json();
        }

        function escapeHtml(str) {
            return (str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        // Gestion Quantité (Globale pour éviter les doubles bindings)
        function updateQty(delta) {
            const input = document.getElementById('qtyInput');
            if (!input || input.disabled) return;
            
            let v = parseInt(input.value) || 1;
            let max = parseInt(input.max) || 999;
            let newV = v + delta;
            
            if (newV >= 1 && newV <= max) {
                input.value = newV;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Vignettes
            const mainImg = document.getElementById('productMainImage');
            document.querySelectorAll('.thumb').forEach(t => {
                t.onclick = () => {
                    const src = t.dataset.src || t.src;
                    if (mainImg.src !== src) {
                        mainImg.style.opacity = 0;
                        setTimeout(() => { mainImg.src = src; mainImg.style.opacity = 1; }, 150);
                        document.querySelectorAll('.thumb').forEach(x => x.classList.remove('is-active'));
                        t.classList.add('is-active');
                    }
                };
            });
        });

        // Ajout Panier
        function ajouterAuPanier(id) {
            const btn = document.querySelector(`button[onclick="ajouterAuPanier(${id})"]`);
            if (btn) btn.disabled = true;

            const qty = document.getElementById('qtyInput')?.value || 1;
            const fd = new FormData();
            fd.append('action', 'ajouter_panier');
            fd.append('idProduit', id);
            fd.append('quantite', qty);
            
            fetchJson(window.location.href, { method: 'POST', body: fd })
                .then(d => {
                    if (d.success) { if(window.notify) notify(d.message, 'success'); }
                    else { if(window.showError) showError('Erreur', d.message); }
                })
                .catch(e => console.error(e))
                .finally(() => { if (btn) btn.disabled = false; });
        }

        // Gestion Avis (Vote, Ajout, Edit, Delete)
        const productId = <?= (int)$idProduit ?>;
        
        // Vote
        const listAvis = document.getElementById('listeAvisProduit');
        if (listAvis && !listAvis.dataset.bound) {
            listAvis.dataset.bound = "true"; // Empêche le double attachement
            listAvis.addEventListener('click', (e) => {
                const btn = e.target.closest('.btn-vote');
                if (!btn) return;
                const rev = btn.closest('.review');
                const aid = rev.dataset.avisId;
                const type = btn.dataset.type;
                const key = `vote:${productId}:${aid}`;
                const prev = localStorage.getItem(key) || '';
                
                btn.disabled = true;
                const fd = new FormData();
                fd.append('action', 'vote');
                fd.append('id_produit', productId);
                fd.append('id_avis', aid);
                fd.append('value', type);
                fd.append('prev', prev);
                
                fetchJson(window.location.href, { method: 'POST', body: fd })
                    .then(d => {
                        if (d.success && d.counts) {
                            rev.querySelector('.like-count').textContent = d.counts.a_pouce_bleu;
                            rev.querySelector('.dislike-count').textContent = d.counts.a_pouce_rouge;
                            localStorage.setItem(key, prev === type ? '' : type);
                            // Update UI state
                            rev.querySelectorAll('.btn-vote').forEach(b => b.setAttribute('aria-pressed', 'false'));
                            if (prev !== type) btn.setAttribute('aria-pressed', 'true');
                        }
                    })
                    .finally(() => btn.disabled = false);
            });
        }

        // Ajout Avis
        const submitBtn = document.getElementById('inlineSubmit');
        if (submitBtn) {
            // Star widget logic
            const starInput = document.getElementById('inlineStarInput');
            const noteInput = document.getElementById('inlineNote');
            if (starInput) {
                // Reset listeners via cloneNode ou juste en réassignant si on utilisait onclick (mais ici c'est addEventListener)
                // On va utiliser un flag sur le container
                if (!starInput.dataset.bound) {
                    starInput.dataset.bound = "true";
                    starInput.querySelectorAll('button').forEach(b => {
                        b.addEventListener('mouseenter', () => updateStars(b.dataset.value));
                        b.addEventListener('click', () => {
                            noteInput.value = b.dataset.value;
                            updateStars(b.dataset.value);
                        });
                    });
                    starInput.addEventListener('mouseleave', () => updateStars(noteInput.value));
                }
                
                function updateStars(v) {
                    starInput.querySelectorAll('button img').forEach((img, i) => {
                        img.src = (i < v) ? '/img/svg/star-full.svg' : '/img/svg/star-empty.svg';
                    });
                }
            }

            // Submit button
            submitBtn.onclick = (e) => {
                e.preventDefault();
                const txt = document.getElementById('inlineComment').value;
                const note = document.getElementById('inlineNote').value;
                
                if (!txt.trim()) return alert('Commentaire vide');
                if (note == 0) return alert('Note requise');
                
                submitBtn.disabled = true;
                const fd = new FormData();
                fd.append('action', 'add_avis');
                fd.append('id_produit', productId);
                fd.append('commentaire', txt);
                fd.append('note', note);
                
                fetchJson(window.location.href, { method: 'POST', body: fd })
                    .then(d => {
                        if (d.success) {
                            location.reload(); 
                        } else {
                            alert(d.message);
                            submitBtn.disabled = false;
                        }
                    })
                    .catch(() => submitBtn.disabled = false);
            };
        }

        // Suppression
        if (listAvis && !listAvis.dataset.boundDelete) {
            listAvis.dataset.boundDelete = "true";
            listAvis.addEventListener('click', (e) => {
                if (!e.target.closest('.btn-delete-review')) return;
                if (!confirm('Supprimer ?')) return;
                const rev = e.target.closest('.review');
                const fd = new FormData();
                fd.append('action', 'delete_avis');
                fd.append('id_produit', productId);
                fd.append('id_avis', rev.dataset.avisId);
                
                fetchJson(window.location.href, { method: 'POST', body: fd })
                    .then(d => {
                        if (d.success) location.reload();
                    });
            });
        }

        // Edition (simplifiée : prompt)
        if (listAvis && !listAvis.dataset.boundEdit) {
            listAvis.dataset.boundEdit = "true";
            listAvis.addEventListener('click', (e) => {
                if (!e.target.closest('.btn-edit-review')) return;
                const rev = e.target.closest('.review');
                const content = rev.querySelector('.review-content');
                const newTxt = prompt('Modifier votre avis :', content.textContent.trim());
                if (newTxt !== null && newTxt !== content.textContent.trim()) {
                    const fd = new FormData();
                    fd.append('action', 'edit_avis');
                    fd.append('id_produit', productId);
                    fd.append('id_avis', rev.dataset.avisId);
                    fd.append('commentaire', newTxt);
                    
                    fetchJson(window.location.href, { method: 'POST', body: fd })
                        .then(d => {
                            if (d.success) location.reload();
                        });
                }
            });
        }

        // Init votes UI
        document.querySelectorAll('.review').forEach(r => {
            const aid = r.dataset.avisId;
            const val = localStorage.getItem(`vote:${productId}:${aid}`);
            if (val) r.querySelector(`.btn-vote[data-type="${val}"]`)?.setAttribute('aria-pressed', 'true');
        });
    </script>
</body>
</html>
