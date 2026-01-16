<?php
session_start();
require_once '../../selectBDD.php';
require_once '../../pages/fonctions.php';

$pdo->exec("SET search_path TO cobrec1");

// --- LOGIQUE PRINCIPALE ---

// 1. Gestion Session / Panier
$idClient = isset($_SESSION['idClient']) ? (int)$_SESSION['idClient'] : null;

// Récupération infos client courant pour l'affichage (avatar, pseudo)
$currentUser = null;
$currentUserImage = null;
if ($idClient) {
    $currentUser = recupererInformationsCompletesClient($pdo, $idClient);
    $idCompte = recupererIdentifiantCompteClient($pdo, $idClient);
    if ($idCompte) {
        $imgData = recupererImageProfilCompte($pdo, $idCompte);
        if ($imgData && !empty($imgData['i_lien'])) {
            $currentUserImage = $imgData['i_lien'];
        }
    }
}

if ($idClient === null) {
    //si l'utilisateur n'est pas connecté, on utilise un panier temporaire en SESSION
    if (!isset($_SESSION['panierTemp'])) {
        $_SESSION['panierTemp'] = array();
    }
    $panier = null; //pas de panier en BDD
} else {
    //sinon on récupère l'id de son panier courant (celui qui est en train d'être rempli)
    $sqlPanierClient = "
        SELECT id_panier
        FROM _panier_commande
        WHERE timestamp_commande IS NULL
        AND id_client = :idClient
    ";
    $stmtPanier = $pdo->prepare($sqlPanierClient);
    $stmtPanier->execute([":idClient" => $idClient]);
    $panier = $stmtPanier->fetch(PDO::FETCH_ASSOC);

    if ($panier) {
        $idPanier = (int) $panier['id_panier'];
    } else {
        $sqlCreatePanier = "
            INSERT INTO _panier_commande (id_client, timestamp_commande)
            VALUES (:idClient, NULL)
            RETURNING id_panier
        ";
        $stmtCreate = $pdo->prepare($sqlCreatePanier);
        $stmtCreate->execute([":idClient" => $idClient]);
        $idPanier = (int) $stmtCreate->fetchColumn();
    }

    $_SESSION["panierEnCours"] = $idPanier;

    transfererPanierTempVersBDD($pdo, $idPanier);
}

// 2. Récupération ID Produit
$idProduit = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 3. Traitement POST (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Si PHP n'a pas rempli $_POST (ex: requête JSON ou autre Content-Type), essayer de parser le corps
    if (empty($_POST)) {
        $rawBody = file_get_contents('php://input');
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if ($rawBody) {
            if (stripos($ct, 'application/json') !== false) {
                $decoded = json_decode($rawBody, true);
                if (is_array($decoded)) $_POST = $decoded;
            } elseif (stripos($ct, 'application/x-www-form-urlencoded') !== false) {
                parse_str($rawBody, $parsed);
                if (is_array($parsed)) $_POST = $parsed;
            }
        }
    }
    if (isset($_POST['action']) && $_POST['action'] === 'ajouter_panier') {
        header('Content-Type: application/json');
        
        $idP = (int)($_POST['idProduit'] ?? 0);
        $qty = (int)($_POST['quantite'] ?? 1);

        if ($idClient === null) {
            echo json_encode(ajouterArticleSession($pdo, $idP, $qty));
        } else {
            $idPanier = $_SESSION['panierEnCours'] ?? null;
            if (!$idPanier) { echo json_encode(['success' => false, 'message' => 'Erreur panier']); exit; }
            echo json_encode(ajouterArticleBDD($pdo, $idP, $idPanier, $qty));
        }
        exit;
    }
    
    if (isset($_POST['id_produit'])) {
        gererActionsAvis($pdo, $idClient, $idProduit);
    }
    // Si aucun handler POST n'a répondu (pour éviter de renvoyer la page HTML complète),
    // retourner un JSON d'erreur utile pour le debug.
    header('Content-Type: application/json');
    $raw = file_get_contents('php://input');
    $hdrs = function_exists('getallheaders') ? getallheaders() : [];
    // journaliser côté serveur pour debug
    error_log('POST non traité dans produit/index.php - _POST: ' . json_encode(array_keys($_POST)) . ' CONTENT_TYPE:' . ($_SERVER['CONTENT_TYPE'] ?? '') );
    echo json_encode([
        'success' => false,
        'message' => 'Aucun handler POST exécuté',
        '_post_keys' => array_keys($_POST),
        '_post' => $_POST,
        '_raw' => $raw,
        '_content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
        '_headers' => $hdrs
    ]);
    exit;
}

// 4. Chargement Données
$produit = ($idProduit > 0) ? chargerProduitBDD($pdo, $idProduit) : null;

if (!$produit || $produit['p_statut'] != 'En ligne') {
    include __DIR__ . '/not-found.php';
    exit;
}

$tri = isset($_GET['tri']) ? trim($_GET['tri']) : 'date_desc';
$allowedTri = ['date_desc','date_asc','note_desc','note_asc','popular'];
if (!in_array($tri, $allowedTri)) $tri = 'date_desc';

// Token "propriétaire" (si jamais avis anonymes / session navigateur)
$ownerTokenServer = $_COOKIE['alizon_owner'] ?? '';

// Chargement des avis (requête SQL directe pour un tri fiable)
switch ($tri) {
    case 'date_asc':
        $orderClauseAvis = 'a.a_timestamp_creation ASC, a.id_avis ASC';
        break;
    case 'note_asc':
        $orderClauseAvis = 'a.a_note ASC NULLS LAST, a.a_timestamp_creation DESC, a.id_avis DESC';
        break;
    case 'note_desc':
        $orderClauseAvis = 'a.a_note DESC NULLS LAST, a.a_timestamp_creation DESC, a.id_avis DESC';
        break;
    case 'popular':
        $orderClauseAvis = '(a.a_pouce_bleu - a.a_pouce_rouge) DESC, a.a_timestamp_creation DESC, a.id_avis DESC';
        break;
    default:
        $orderClauseAvis = 'a.a_timestamp_creation DESC, a.id_avis DESC';
}

// Épingler l'avis de l'utilisateur en haut, quel que soit le filtre
$pinOrderPrefix = '';
if ($idClient) {
    $pinOrderPrefix = 'CASE WHEN a.id_client = :cid THEN 0 ELSE 1 END, ';
} elseif (!empty($ownerTokenServer)) {
    $pinOrderPrefix = 'CASE WHEN a.a_owner_token IS NOT NULL AND a.a_owner_token = :owner_token THEN 0 ELSE 1 END, ';
}

$sqlAvis = "
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
        " . ($idClient ? "(SELECT CASE WHEN vote_type = 'like' THEN 'plus' WHEN vote_type = 'dislike' THEN 'minus' END FROM cobrec1._vote_avis va WHERE va.id_avis = a.id_avis AND va.id_client = :cid LIMIT 1) as user_vote, (SELECT CASE WHEN EXISTS(SELECT 1 FROM cobrec1._signale_avis sa JOIN cobrec1._envoie_signalement es ON es.id_signalement = sa.id_signalement JOIN cobrec1._client c ON es.id_compte = c.id_compte WHERE sa.id_avis = a.id_avis AND c.id_client = :cid) THEN true ELSE false END) as user_reported" : "NULL as user_vote, NULL as user_reported") . "
    FROM cobrec1._avis a
    LEFT JOIN cobrec1._client cl ON a.id_client = cl.id_client
    LEFT JOIN cobrec1._compte co ON cl.id_compte = co.id_compte
    LEFT JOIN cobrec1._represente_compte rc ON co.id_compte = rc.id_compte
    LEFT JOIN cobrec1._image i ON rc.id_image = i.id_image
    WHERE a.id_produit = :pid
    AND a.id_avis NOT IN (SELECT id_avis FROM cobrec1._reponse)
    ORDER BY $pinOrderPrefix$orderClauseAvis
";

$stmtAvis = $pdo->prepare($sqlAvis);
$paramsAvis = [':pid' => $idProduit];
if ($idClient) {
    $paramsAvis[':cid'] = $idClient;
} elseif (!empty($ownerTokenServer)) {
    $paramsAvis[':owner_token'] = $ownerTokenServer;
}
$stmtAvis->execute($paramsAvis);
$avisTextes = $stmtAvis->fetchAll(PDO::FETCH_ASSOC);

$stmtRep = $pdo->prepare("SELECT r.id_avis_parent, a.id_avis, a.a_texte, TO_CHAR(a.a_timestamp_creation,'YYYY-MM-DD HH24:MI') AS a_timestamp_fmt FROM cobrec1._reponse r JOIN cobrec1._avis a ON r.id_avis = a.id_avis WHERE a.id_produit = :pid");
$stmtRep->execute([':pid' => $idProduit]);
$rowsRep = $stmtRep->fetchAll(PDO::FETCH_ASSOC);
$reponsesMap = [];
foreach ($rowsRep as $r) {
    $reponsesMap[(int)$r['id_avis_parent']] = $r;
}

// Calculs affichage
$estEnRupture = ($produit['p_stock'] <= 0);
$discount = (float)$produit['pourcentage_reduction'];
$prixDiscount = ($discount > 0) ? $produit['p_prix'] * (1 - $discount/100) : $produit['p_prix'];
$prixFinal = calcPrixTVA($produit['tva'], $prixDiscount);

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
$dejaAvis = false;
if ($idClient) {
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM _contient c JOIN _panier_commande pc ON c.id_panier = pc.id_panier WHERE pc.id_client = :cid AND c.id_produit = :pid AND pc.timestamp_commande IS NOT NULL LIMIT 1");
        $stmt->execute([':cid' => $idClient, ':pid' => $idProduit]);
        $clientAachete = (bool)$stmt->fetchColumn();

        // Vérif si déjà avis
        $stmtCheck = $pdo->prepare("SELECT 1 FROM _avis WHERE id_produit = :pid AND id_client = :cid");
        $stmtCheck->execute([':pid' => $idProduit, ':cid' => $idClient]);
        $dejaAvis = (bool)$stmtCheck->fetchColumn();
    } catch (Exception $e) {}
}


// Render function moved to pages/fonctions.php (renderAvisHtml)

// Mode fragment: renvoyer uniquement la liste HTML des avis (sans recharger la page)
if (isset($_GET['partial']) && $_GET['partial'] === 'reviews') {
    header('Content-Type: application/json; charset=utf-8');
    ob_start();
    renderAvisHtml($avisTextes, $reponsesMap, $idClient, $ownerTokenServer);
    $html = ob_get_clean();
    $optionsForLabel = [
        'popular' => 'Pertinence (pouces)',
        'date_desc' => 'Les plus récentes',
        'date_asc' => 'Les plus anciennes',
        'note_desc' => 'Note décroissante',
        'note_asc' => 'Note croissante'
    ];
    echo json_encode([
        'success' => true,
        'tri' => $tri,
        'label' => $optionsForLabel[$tri] ?? $tri,
        'html' => $html
    ]);
    exit;
}

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
    <style>
        .vote-section {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .vote-label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
            margin: 0;
        }
        .vote-buttons {
            display: flex;
            gap: 8px;
        }
        @media (max-width: 768px) {
            .vote-section {
                flex-direction: column;
                align-items: flex-start;
                gap: 6px;
            }
            .vote-label {
                font-size: 13px;
            }
        }

        /* Dropdown filtre avis (style “YouTube”) */
        .filters-wrap { position: relative; display: inline-flex; align-items: center; gap: 10px; }
        .filters-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 999px;
            background: #fff;
            cursor: pointer;
            font-weight: 600;
        }
        .filters-btn .label { color: #666; font-weight: 600; }
        .filters-btn .value { color: #111; font-weight: 700; }
        .filters-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            min-width: 240px;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            box-shadow: 0 10px 24px rgba(0,0,0,.10);
            padding: 8px;
            display: none;
            z-index: 200;
        }
        .filters-menu.is-open { display: block; }
        .filters-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 10px;
            border-radius: 10px;
            text-decoration: none;
            color: inherit;
        }
        .filters-item:hover { background: #f4f5f7; }
        .filters-item.is-active { background: #111; color: #fff; }
        .filters-item small { opacity: .8; font-weight: 500; }

        .product-ref {
            font-size: 12px;
            color: #8a8f98;
            font-weight: 700;
            letter-spacing: .02em;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        @keyframes slideInReview {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeOutReview {
            from { opacity: 1; transform: scale(1); max-height: 500px; margin-bottom: 12px; padding: 12px; }
            to { opacity: 0; transform: scale(0.95); max-height: 0; margin-bottom: 0; padding: 0; overflow: hidden; }
        }
        .review.entering {
            animation: slideInReview 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }
        .review.leaving {
            animation: fadeOutReview 0.4s ease-in forwards;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <?php
    //inclure l'en-tête du site
    include __DIR__ . '/../../partials/header.php';
    ?>

    <nav class="page-breadcrumb">
        <a class="btn btn-retour-catalogue back-link" href="/index.php" aria-label="Retour au catalogue">
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
                <div class="product-ref">Ref : #<?= (int)$produit['id_produit'] ?></div>
                <div class="title"><?= htmlspecialchars($produit['p_nom']) ?></div>
                <div class="rating">
                    <span class="stars" id="summaryStars" aria-hidden="true">
                        <?php for ($i = 1; $i <= 5; $i++): 
                            if ($note >= $i) $s = 'full';
                            elseif ($note >= $i - 0.5) $s = 'alf';
                            else $s = 'empty';
                        ?>
                            <img src="/img/svg/star-<?= $s ?>.svg" alt="Etoile" width="20">
                        <?php endfor; ?>
                    </span>
                    <span id="summaryRatingValue" style="color:var(--muted);font-weight:600"><?= number_format($note, 1) ?></span>
                    <span id="summaryRatingCount" style="color:var(--muted)">(<?= $nbAvis ?>)</span>
                </div>
                <div class="price">
                    <?= number_format($prixFinal, 2, ',', ' ') ?> €
                    <?php if ($discount > 0): ?>
                        <span class="old"><?= number_format(calcPrixTVA($produit['tva'], $produit['p_prix']), 2, ',', ' ') ?> €</span>
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

                <!--<div class="summary-actions">
                    <button class="ghost">Ajouter aux favoris</button>
                    <button class="ghost">Partager</button>
                </div>-->

                <div class="meta" style="margin-top:12px">
                    Stock : <strong><?= $estEnRupture ? 'Rupture' : $produit['p_stock'] . ' disponible(s)' ?></strong>
                </div>
                <div class="meta">Livraison prévue : <strong>3-5 jours ouvrés</strong></div>

                <div class="section features">
                    <h3>Caractéristiques</h3>
                    <ul>
                        <li>Catégorie : <?= htmlspecialchars(explode(', ', $produit['categories'])[0] ?? 'Général') ?></li>
                        <li>Statut : <?= htmlspecialchars($produit['p_statut']) ?></li>
                        <li>Vendu par : <?= htmlspecialchars($produit['vendeur_nom'] ?? 'Alizon') ?> <div class="smaller">(<a href="mailto:<?= htmlspecialchars($produit['vendeur_email'] ?? 'contact@alizon.com') ?>"><?= htmlspecialchars($produit['vendeur_email'] ?? 'contact@alizon.com') ?></a>)</div></li>
                        <li>Origine : <?= htmlspecialchars($produit['p_origine'] ?? 'Non spécifiée') ?></li>
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
                            <?php for ($i = 1; $i <= 5; $i++): 
                            if ($note >= $i) $s = 'full';
                            elseif ($note >= $i - 0.5) $s = 'alf';
                            else $s = 'empty';
                        ?>
                            <img src="/img/svg/star-<?= $s ?>.svg" alt="Etoile" width="20">
                        <?php endfor; ?>
                        </div>
                        <div id="reviewsRatingCount" style="font-size:13px;color:var(--muted);margin-top:4px">Basé sur <?= $nbAvis ?> avis</div>
                    </div>
                </div>
            </div>

            <!-- Formulaire avis -->
            <?php if ($idClient && $clientAachete && !$dejaAvis): ?>
                <div class="review new-review-card" id="newReviewCard">
                    <div class="review-head">
                        <div class="review-head-left">
                            <div class="avatar">
                                <?php if ($currentUserImage): ?>
                                    <img src="<?= htmlspecialchars($currentUserImage) ?>" alt="Avatar" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                                <?php else: ?>
                                    <?php 
                                        $initial = 'U';
                                        if ($currentUser) {
                                            if (!empty($currentUser['c_pseudo'])) $initial = substr($currentUser['c_pseudo'], 0, 1);
                                            elseif (!empty($currentUser['prenom'])) $initial = substr($currentUser['prenom'], 0, 1);
                                        }
                                    ?>
                                    <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(180deg,#eef1ff,#ffffff);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--accent)"><?= strtoupper($initial) ?></div>
                                <?php endif; ?>
                            </div>
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
                        <input type="text" name="titre" id="inlineTitle" class="review-title-input" placeholder="Titre de votre avis" maxlength="255" required>
                        <textarea name="commentaire" id="inlineComment" rows="3" class="review-textarea" placeholder="Partagez votre avis..." required></textarea>
                        <div class="review-actions">
                            <small class="review-hint">Merci de rester courtois.</small>
                            <button type="button" class="btn" id="inlineSubmit">Publier</button>
                        </div>
                    </form>
                </div>
            <?php elseif ($idClient && $dejaAvis): ?>
                <div class="review new-review-card" style="background:#f3f4f7;opacity:.85">
                    <div style="padding:12px 16px;font-size:14px;color:#555">
                        Vous avez déjà publié un avis sur ce produit.
                    </div>
                </div>
            <?php else: ?>
                <div class="review new-review-card" style="background:#f3f4f7;opacity:.85">
                    <div style="padding:12px 16px;font-size:14px;color:#555">
                        <?= !$idClient ? 'Connectez-vous pour laisser un avis.' : 'Vous devez avoir acheté ce produit pour laisser un avis.' ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Filtrer les avis (dropdown style YouTube) -->
            <?php
                $baseHref = '/pages/produit/index.php?id=' . urlencode($idProduit);
                $options = [
                    'date_desc' => 'Les plus récentes',
                    'date_asc' => 'Les plus anciennes',
                    'note_desc' => 'Note décroissante',
                    'note_asc' => 'Note croissante',
                    'popular' => 'Pertinence (pouces)'
                ];
                $activeLabel = $options[$tri] ?? 'Filtre';
            ?>
            <div class="filters-wrap" style="margin:10px 0 16px;">
                <button type="button" class="filters-btn" id="reviewsFilterBtn" aria-haspopup="true" aria-expanded="false">
                    <span class="label">Filtrer</span>
                    <span class="value">• <?= htmlspecialchars($activeLabel) ?></span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                </button>
                <div class="filters-menu" id="reviewsFilterMenu" role="menu" aria-label="Filtres des avis">
                    <?php foreach ($options as $k => $label):
                        $isActive = ($tri === $k);
                        $href = $baseHref . '&tri=' . urlencode($k);
                    ?>
                        <a class="filters-item <?= $isActive ? 'is-active' : '' ?>" role="menuitem" href="<?= htmlspecialchars($href) ?>">
                            <span><?= htmlspecialchars($label) ?></span>
                            <?php if ($isActive): ?><small>Actif</small><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div id="listeAvisProduit">
                <?php renderAvisHtml($avisTextes, $reponsesMap, $idClient, $ownerTokenServer); ?>
            </div>
        </section>
    </main>

    <?php
    //inclure le pied de page du site
    include __DIR__ . '/../../partials/footer.html';
    ?>

    <!-- Modal Edition Avis -->
    <div id="editReviewModal" class="modal-overlay">
        <div class="modal-dialog">
            <h3>Modifier votre avis</h3>
            <div class="star-input" id="editStarInput" title="Sélectionnez une note">
                <?php for($i=1; $i<=5; $i++): ?>
                    <button type="button" data-value="<?= $i ?>" aria-label="<?= $i ?> étoiles"><img src="/img/svg/star-empty.svg" alt=""></button>
                <?php endfor; ?>
            </div>
            <input type="text" id="editReviewTitle" name="titre" class="review-title-input" placeholder="Titre de votre avis" maxlength="255" required>
            <input type="hidden" id="editNote" name="note" value="0">
            <textarea id="editReviewText" class="review-textarea" rows="5" placeholder="Votre avis..." required></textarea>
            <div class="modal-actions">
                <button class="btn-secondary" id="cancelEditReview">Annuler</button>
                <button class="btn-primary" id="confirmEditReview">Enregistrer</button>
            </div>
        </div>
    </div>

    <!-- Modal Signalement Avis -->
    <div id="reportModal" class="modal-overlay" style="display:none;">
        <div class="modal-dialog">
            <h3>Signaler cet avis</h3>
            <input type="hidden" id="reportAvisId" value="0">
            <label style="display:block;margin:8px 0 4px;font-weight:600">Motif</label>
            <select id="reportMotif" style="width:100%;padding:8px;margin-bottom:8px">
                <option value="Contenu haineux">Contenu haineux</option>
                <option value="Spam / Publicité">Spam / Publicité</option>
                <option value="Inapproprié">Inapproprié</option>
                <option value="Autre">Autre</option>
            </select>
            <label style="display:block;margin:8px 0 4px;font-weight:600">Commentaire (optionnel)</label>
            <textarea id="reportCommentaire" rows="4" style="width:100%;padding:8px" placeholder="Décrivez si besoin..."></textarea>
            <div class="modal-actions" style="margin-top:12px">
                <button class="btn-secondary" id="cancelReport">Annuler</button>
                <button class="btn-primary" id="confirmReport">Envoyer</button>
            </div>
        </div>
    </div>

    
    <script src="/js/notifications.js"></script>
    <script>
        window.PRODUCT_ID = <?= (int)$idProduit ?>;
        window.CURRENT_ID_CLIENT = <?= $idClient ?: 'null' ?>;
        window.OWNER_TOKEN = <?= json_encode($ownerTokenServer) ?>;
    </script>
    <script src="/js/produit/utils.js"></script>
    <script src="/js/produit/main.js"></script>
    <script src="/js/produit/panier.js"></script>
    <script src="/js/produit/reviews.js"></script>
    <script src="/js/produit/filter.js"></script>


    <!-- Tri des avis: liens classiques (fiable, sans AJAX) -->
</body>
</html>
