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
$prixFinal = calcPrixTVA($produit['id_produit'], $produit['tva'], $prixDiscount);

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


// Rendu HTML de la liste d'avis (réutilisé pour le mode fragment AJAX)
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
        <div class="review" data-avis-id="<?= (int)$ta['id_avis'] ?>" data-note="<?= $aNote ?>" data-title="<?= htmlspecialchars($aTitre) ?>" style="margin-bottom:12px;position:relative;padding-right:44px;">
            <?php if (!($idClient && ( ($ta['id_client'] && $ta['id_client'] == $idClient) || (!$ta['id_client'] && $ownerTokenServer && isset($ta['a_owner_token']) && $ta['a_owner_token'] === $ownerTokenServer) ))): ?>
                <button class="ghost btn-report-trigger" aria-label="Options avis" style="position:absolute;right:3em;top:8px;width:34px;height:34px;border-radius:6px;display:flex;align-items:center;justify-content:center">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="1.5"></circle><circle cx="12" cy="12" r="1.5"></circle><circle cx="12" cy="19" r="1.5"></circle></svg>
                </button>
                <div class="report-dropdown" style="display:none;position:absolute;right:8px;top:44px;background:#fff;border:1px solid #e0e0e0;border-radius:6px;z-index:60;min-width:160px;box-shadow:0 6px 18px rgba(0,0,0,.06)">
                    <?php if (isset($ta['user_reported']) && $ta['user_reported']): ?>
                        <button class="btn-unreport-action" style="width:100%;text-align:left;padding:10px;border:none;background:transparent;border-radius:6px">Annuler le signalement</button>
                    <?php else: ?>
                        <button class="btn-report-action" style="width:100%;text-align:left;padding:10px;border:none;background:transparent;border-radius:6px">Signaler l'avis</button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
                <?php if ($avatarUrl): ?>
                    <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="Avatar" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                    <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(180deg,#eef1ff,#ffffff);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--accent)"><?= strtoupper(substr($displayName, 0, 1)) ?></div>
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
                        <span style="color:var(--muted);font-weight:600;"><?= number_format($aNote, 1) ?></span>
                    </div>
                </div>
            </div>
            <?php if (!empty($aTitre)): ?>
                <strong style="display:block;margin-bottom:8px;color:var(--text);font-size:16px;"><?= htmlspecialchars($aTitre) ?></strong>
            <?php endif; ?>
            <div class="review-content" style="color:var(--muted)"><?= htmlspecialchars($ta['a_texte']) ?></div>
            <div class="review-votes">
                <div class="vote-section">
                    <span class="vote-label">Évaluer ce commentaire :</span>
                    <div class="vote-buttons">
                        <button type="button" class="ghost btn-vote" data-type="J'aime" aria-label="Vote plus" <?= (isset($ta['user_vote']) && $ta['user_vote'] === 'plus') ? 'aria-pressed="true"' : '' ?>>
                            <img src="/img/svg/PouceHaut.svg" alt="J'aime" width="16" height="16"> <span class="like-count"><?= (int)$ta['a_pouce_bleu'] ?></span>
                        </button>
                        <button type="button" class="ghost btn-vote" data-type="Je n'aime pas" aria-label="Vote moins" <?= (isset($ta['user_vote']) && $ta['user_vote'] === 'minus') ? 'aria-pressed="true"' : '' ?>>
                            <img src="/img/svg/PouceBas.svg" alt="Je n'aime pas" width="16" height="16"> <span class="dislike-count"><?= (int)$ta['a_pouce_rouge'] ?></span>
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
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"></circle><circle cx="19" cy="12" r="1"></circle><circle cx="5" cy="12" r="1"></circle></svg>
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
        <?php
    }
}

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
    </style>
</head>
<body>
    <?php
    //inclure l'en-tête du site
    include __DIR__ . '/../../partials/header.php';
    ?>

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
                        <span class="old"><?= number_format(calcPrixTVA($produit['id_produit'], $produit['tva'], $produit['p_prix']), 2, ',', ' ') ?> €</span>
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

    <script src="/js/HL_import.js"></script>
    <script src="/js/notifications.js"></script>
    <script>
        // Fonctions utilitaires
        async function fetchJson(url, options) {
                // Debug: journaliser le contenu du FormData envoyé (utile pour voir clés envoyées)
                try {
                    if (options && options.body && typeof FormData !== 'undefined' && options.body instanceof FormData) {
                        for (const e of options.body.entries()) console.debug('AJAX POST', e[0], e[1]);
                    }
                } catch (err) { /* ignore */ }

                const resp = await fetch(url, options || {});
                // Lire le corps en texte d'abord pour diagnostiquer les erreurs côté serveur
                const txt = await resp.text();
                // Détecter redirections suivies automatiquement par fetch
                if (resp.redirected || (resp.status >= 300 && resp.status < 400)) {
                    const loc = resp.headers.get('Location') || '(location inconnue)';
                    console.warn('Requête redirigée:', resp.status, loc);
                    // fournir le HTML reçu pour debug
                    throw new Error('Requête redirigée (HTTP ' + resp.status + '). Contenu: ' + txt.slice(0, 800));
                }
                if (!resp.ok) {
                    // essayer à extraire un message JSON si présent
                    try {
                        const j = JSON.parse(txt);
                        throw new Error(j.message || 'Erreur réseau');
                    } catch (e) {
                        throw new Error(txt || 'Erreur réseau');
                    }
                }
                // tenter de parser JSON, tolérer BOM/espaces
                const trimmed = txt.trim();
                if (trimmed === '') return {};
                try {
                    return JSON.parse(trimmed);
                } catch (e) {
                    // inclure un extrait pour debug
                    const snippet = trimmed.slice(0, 300);
                    throw new Error('Réponse invalide du serveur: ' + snippet);
                }
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
            // Check for pending notification
            const pendingMsg = localStorage.getItem('pendingNotification');
            if (pendingMsg) {
                if (window.notify) notify(pendingMsg, 'success');
                localStorage.removeItem('pendingNotification');
            }

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
        const idClient = <?= $idClient ?: 'null' ?>;
        
        // Vote
        const listAvis = document.getElementById('listeAvisProduit');
        if (listAvis && !listAvis.dataset.bound) {
            listAvis.dataset.bound = "true"; // Empêche le double attachement
            listAvis.addEventListener('click', (e) => {
                const btn = e.target.closest('.btn-vote');
                if (!btn) return;
                if (!idClient) {
                    alert('Vous devez être connecté pour voter sur les commentaires.');
                    return;
                }
                const rev = btn.closest('.review');
                if (!rev || !rev.dataset.avisId) {
                    console.error('Review element not found or missing data-avis-id');
                    return;
                }
                const aid = rev.dataset.avisId;
                const type = btn.dataset.type;
                const value = (type === "J'aime") ? 'plus' : 'minus';
                
                btn.disabled = true;
                const fd = new FormData();
                fd.append('action', 'vote');
                fd.append('id_produit', productId);
                fd.append('id_avis', aid);
                fd.append('value', value);
        
                fetchJson('actions_avis.php', { method: 'POST', body: fd })
                    .then(d => {
                        if (d.success && d.counts) {
                            rev.querySelector('.like-count').textContent = d.counts.a_pouce_bleu;
                            rev.querySelector('.dislike-count').textContent = d.counts.a_pouce_rouge;
                            
                            // Update UI state
                            rev.querySelectorAll('.btn-vote').forEach(b => b.setAttribute('aria-pressed', 'false'));
                            if (d.user_vote === value) {
                                btn.setAttribute('aria-pressed', 'true');
                            }
                        } else if (!d.success && d.message) {
                            if(window.showError) showError('Erreur', d.message);
                            else alert(d.message);
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
                const titre = document.getElementById('inlineTitle').value.trim();
                const txt = document.getElementById('inlineComment').value.trim();
                const note = document.getElementById('inlineNote').value;

                if (!titre) return notify('Titre requis', 'warning');
                if (!txt) return notify('Commentaire vide', 'warning');
                if (note == 0) return notify('Note requise', 'warning');
                
                submitBtn.disabled = true;
                const fd = new FormData();
                fd.append('action', 'add_avis');
                fd.append('id_produit', productId);
                fd.append('titre', titre);
                fd.append('commentaire', txt);
                fd.append('note', note);
                
                fetchJson('actions_avis.php', { method: 'POST', body: fd })
                    .then(d => {
                        if (d.success) {
                            localStorage.setItem('pendingNotification', 'Avis publié');
                            location.reload(); 

                        } else {
                            showError('Erreur', d.message);
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
                
                showModal({
                    title: 'Suppression',
                    message: 'Voulez-vous vraiment supprimer cet avis ?',
                    okText: 'Supprimer',
                    cancelText: 'Annuler',
                    onOk: () => {
                        const rev = e.target.closest('.review');
                        const fd = new FormData();
                        fd.append('action', 'delete_avis');
                        fd.append('id_produit', productId);
                        fd.append('id_avis', rev.dataset.avisId);
                        
                        fetchJson('actions_avis.php', { method: 'POST', body: fd })
                            .then(d => {
                                if (d.success) {
                                    localStorage.setItem('pendingNotification', 'Avis supprimé');
                                    location.reload();
                                }
                                else showError('Erreur', d.message || 'Impossible de supprimer');
                            });
                    }
                });
            });
        }

        // Edition
        const editModal = document.getElementById('editReviewModal');
        const editTitle = document.getElementById('editReviewTitle');
        const editText = document.getElementById('editReviewText');
        const editCancel = document.getElementById('cancelEditReview');
        const editConfirm = document.getElementById('confirmEditReview');
        let currentEditId = null, originalTitle, originalText, originalNote;
        let updateEditStars = () => {};

        if (editModal) {
            editCancel.onclick = () => editModal.style.display = 'none';
            editModal.onclick = (e) => { if(e.target === editModal) editModal.style.display = 'none'; };
            
            const editStarInput = document.getElementById('editStarInput');
            const editNoteInput = document.getElementById('editNote');

            updateEditStars = (v) => {
                if(!editStarInput) return;
                editStarInput.querySelectorAll('button img').forEach((img, i) => {
                    img.src = (i < v) ? '/img/svg/star-full.svg' : '/img/svg/star-empty.svg';
                });
            };

            if (editStarInput && !editStarInput.dataset.bound) {
                editStarInput.dataset.bound = "true";
                editStarInput.querySelectorAll('button').forEach(b => {
                    b.addEventListener('mouseenter', () => updateEditStars(b.dataset.value));
                    b.addEventListener('click', () => {
                        editNoteInput.value = b.dataset.value;
                        updateEditStars(b.dataset.value);
                    });
                });
                editStarInput.addEventListener('mouseleave', () => updateEditStars(editNoteInput.value));
            }
            
            editConfirm.onclick = () => {
                const newTitre = editTitle.value.trim();
                const newTxt = editText.value.trim();
                const newNote = editNoteInput.value;
                if (!newTitre) return notify('Titre requis', 'warning');
                if (!newTxt) return notify('Le commentaire ne peut pas être vide', 'warning');
                if (newNote == 0) return notify('Note requise', 'warning');


                const fd = new FormData();
                fd.append('action', 'edit_avis');
                fd.append('id_produit', productId);
                fd.append('id_avis', currentEditId);
                fd.append('titre', newTitre);
                fd.append('commentaire', newTxt);
                fd.append('note', newNote);
                
                fetchJson('actions_avis.php', { method: 'POST', body: fd })
                    .then(d => {
                        if (d.success) {
                            localStorage.setItem('pendingNotification', 'Avis modifié');
                            location.reload();
                        }
                        else showError('Erreur', d.message || 'Erreur lors de la modification');
                    });
                editModal.style.display = 'none';
            };
        }

        if (listAvis && !listAvis.dataset.boundEdit) {
            listAvis.dataset.boundEdit = "true";
            listAvis.addEventListener('click', (e) => {
                // Menu trigger
                const menuBtn = e.target.closest('.btn-menu-trigger');
                if (menuBtn) {
                    const container = menuBtn.closest('.mobile-menu-container');
                    const dropdown = container.querySelector('.mobile-menu-dropdown');
                    
                    // Close others
                    document.querySelectorAll('.mobile-menu-dropdown').forEach(d => {
                        if (d !== dropdown) d.classList.remove('show');
                    });
                    
                    dropdown.classList.toggle('show');
                    e.stopPropagation();
                    return;
                }

                // Close menu when clicking outside
                if (!e.target.closest('.mobile-menu-dropdown')) {
                     document.querySelectorAll('.mobile-menu-dropdown').forEach(d => d.classList.remove('show'));
                }

                if (!e.target.closest('.btn-edit-review')) return;
                const rev = e.target.closest('.review');
                const content = rev.querySelector('.review-content');
                const titleEl = rev.querySelector('strong');
                
                currentEditId = rev.dataset.avisId;
                // Preferer le contenu du <strong>, fallback sur data-title (utile si le titre n'a pas été rendu)
                editTitle.value = titleEl ? titleEl.textContent.trim() : (rev.dataset.title || '');
                editText.value = content.textContent.trim();
                
                const currentNote = rev.dataset.note || 0;
                const editNoteInput = document.getElementById('editNote');
                if(editNoteInput) editNoteInput.value = currentNote;
                updateEditStars(currentNote);

                originalTitle = editTitle.value;
                originalText = editText.value;
                originalNote = currentNote;

                editModal.style.display = 'flex';
            });
            
            // Global click to close menus
            document.addEventListener('click', () => {
                document.querySelectorAll('.mobile-menu-dropdown').forEach(d => d.classList.remove('show'));
            });
        }

        // Signalement - dropdown & modal handling
        const reportModal = document.getElementById('reportModal');
        const reportAvisIdInput = document.getElementById('reportAvisId');
        const reportMotif = document.getElementById('reportMotif');
        const reportCommentaire = document.getElementById('reportCommentaire');
        const cancelReport = document.getElementById('cancelReport');
        const confirmReport = document.getElementById('confirmReport');

        if (listAvis && !listAvis.dataset.boundReport) {
            listAvis.dataset.boundReport = 'true';
            listAvis.addEventListener('click', (e) => {
                const trigger = e.target.closest('.btn-report-trigger');
                if (trigger) {
                    const rev = trigger.closest('.review');
                    const dropdown = rev.querySelector('.report-dropdown');

                    // close other dropdowns
                    document.querySelectorAll('.report-dropdown').forEach(d => { if (d !== dropdown) d.style.display = 'none'; });
                    dropdown.style.display = (dropdown.style.display === 'block') ? 'none' : 'block';
                    e.stopPropagation();
                    return;
                }

                if (e.target.closest('.btn-unreport-action')) {
                    if (!idClient) {
                        if (window.showError) showError('Connexion requise', 'Vous devez être connecté pour annuler un signalement.');
                        else alert('Vous devez être connecté pour annuler un signalement.');
                        document.querySelectorAll('.report-dropdown').forEach(d => d.style.display = 'none');
                        return;
                    }
                    const rev = e.target.closest('.review');
                    const aid = rev.dataset.avisId;
                    const fd = new FormData();
                    fd.append('action','unreport_avis');
                    fd.append('id_produit', productId);
                    fd.append('id_avis', aid);
                    // effectuer l'annulation
                    fetchJson('actions_avis.php', { method: 'POST', body: fd })
                        .then(d => {
                            if (d.success) {
                                notify(d.message || 'Signalement annulé.', 'success');
                                // remplacer le bouton par Signaler
                                const dropdown = rev.querySelector('.report-dropdown');
                                if (dropdown) dropdown.innerHTML = '<button class="btn-report-action" style="width:100%;text-align:left;padding:10px;border:none;background:transparent;border-radius:6px">Signaler l\'avis</button>';
                            } else {
                                const msg = d.message || 'Impossible d\'annuler le signalement';
                                if (window.showError) showError('Erreur', msg);
                                else alert(msg);
                            }
                        })
                        .catch(err => { console.error(err); if (window.showError) showError('Erreur', 'Erreur réseau'); else alert('Erreur réseau'); })
                        .finally(() => { /* nothing */ });
                    // hide dropdowns
                    document.querySelectorAll('.report-dropdown').forEach(d => d.style.display = 'none');
                    return;
                }

                if (e.target.closest('.btn-report-action')) {
                    if (!idClient) {
                        if (window.showError) showError('Connexion requise', 'Vous devez être connecté pour signaler un avis.');
                        else alert('Vous devez être connecté pour signaler un avis.');
                        // hide dropdowns
                        document.querySelectorAll('.report-dropdown').forEach(d => d.style.display = 'none');
                        return;
                    }
                    const rev = e.target.closest('.review');
                    const aid = rev.dataset.avisId;
                    if (!reportModal || !reportAvisIdInput || !reportMotif || !reportCommentaire) {
                        console.error('Signalement: éléments UI manquants (modal/inputs)');
                        return;
                    }
                    reportAvisIdInput.value = aid;
                    // Remettre sur un motif valide (sinon reportMotif.value == '' et le submit est bloqué)
                    reportMotif.selectedIndex = 0;
                    reportCommentaire.value = '';
                    reportModal.style.display = 'flex';
                    // hide dropdowns
                    document.querySelectorAll('.report-dropdown').forEach(d => d.style.display = 'none');
                    return;
                }

                // click outside to close dropdowns
                if (!e.target.closest('.report-dropdown')) {
                    document.querySelectorAll('.report-dropdown').forEach(d => d.style.display = 'none');
                }
            });

            // close report modal
            if (cancelReport) cancelReport.onclick = () => reportModal.style.display = 'none';
            if (reportModal) reportModal.onclick = (ev) => { if (ev.target === reportModal) reportModal.style.display = 'none'; };

            if (confirmReport) confirmReport.onclick = () => {
                if (!reportAvisIdInput || !reportMotif || !reportCommentaire || !reportModal) return;
                if (!idClient) {
                    if (window.showError) showError('Connexion requise', 'Vous devez être connecté pour signaler un avis.');
                    else alert('Vous devez être connecté pour signaler un avis.');
                    return;
                }
                const aid = reportAvisIdInput.value;
                const motif = reportMotif.value;
                const comm = reportCommentaire.value.trim();
                if (!motif) return notify('Sélectionnez un motif', 'warning');
                confirmReport.disabled = true;
                const fd = new FormData();
                fd.append('action', 'report_avis');
                fd.append('id_produit', productId);
                fd.append('id_avis', aid);
                fd.append('motif', motif);
                fd.append('commentaire', comm);

                fetchJson('actions_avis.php', { method: 'POST', body: fd })
                    .then(d => {
                        if (d.success) {
                            notify(d.message || 'Signalement envoyé', 'success');
                            reportModal.style.display = 'none';
                            // transformer le bouton en 'Annuler le signalement'
                            try {
                                const currentAid = reportAvisIdInput ? reportAvisIdInput.value : null;
                                const rev = currentAid ? document.querySelector('.review[data-avis-id="' + currentAid + '"]') : null;
                                if (rev) {
                                    const dropdown = rev.querySelector('.report-dropdown');
                                    if (dropdown) dropdown.innerHTML = '<button class="btn-unreport-action" style="width:100%;text-align:left;padding:10px;border:none;background:transparent;border-radius:6px">Annuler le signalement</button>';
                                }
                            } catch (e) { /* silent */ }
                        } else {
                            const msg = d.message || 'Impossible d\'envoyer le signalement';
                            if (window.showError) showError('Erreur', msg);
                            else alert(msg);
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        if (window.showError) showError('Erreur', 'Erreur réseau');
                        else alert('Erreur réseau');
                    })
                    .finally(() => { confirmReport.disabled = false; });
            };
        }


    </script>
    <script>
        // Dropdown “Filtrer” (UI uniquement, liens classiques)
        (function(){
            const btn = document.getElementById('reviewsFilterBtn');
            const menu = document.getElementById('reviewsFilterMenu');
            if (!btn || !menu) return;

            const valueEl = btn.querySelector('.value');
            const listEl = document.getElementById('listeAvisProduit');

            function closeMenu(){
                menu.classList.remove('is-open');
                btn.setAttribute('aria-expanded','false');
            }

            async function applyFilter(href) {
                if (!listEl) {
                    window.location.href = href;
                    return;
                }

                const url = new URL(href, window.location.href);
                url.searchParams.set('partial', 'reviews');

                btn.disabled = true;
                btn.style.opacity = '0.75';

                try {
                    const resp = await fetch(url.toString(), { credentials: 'same-origin' });
                    const data = await resp.json();
                    if (!data || !data.success) throw new Error('Réponse invalide');

                    listEl.innerHTML = data.html || '';

                    if (valueEl) valueEl.textContent = '• ' + (data.label || data.tri || 'Filtre');

                    // Mettre à jour l'état actif dans le menu
                    menu.querySelectorAll('.filters-item').forEach(a => {
                        const aUrl = new URL(a.getAttribute('href'), window.location.href);
                        const isActive = aUrl.searchParams.get('tri') === (data.tri || '');
                        a.classList.toggle('is-active', isActive);
                        const small = a.querySelector('small');
                        if (small) small.remove();
                        if (isActive) {
                            const s = document.createElement('small');
                            s.textContent = 'Actif';
                            a.appendChild(s);
                        }
                    });

                    // Mettre à jour l'URL sans recharger la page (garde le scroll)
                    const newUrl = new URL(window.location.href);
                    newUrl.searchParams.set('tri', data.tri);
                    newUrl.searchParams.delete('partial');
                    history.replaceState({}, '', newUrl.toString());

                    document.dispatchEvent(new CustomEvent('reviews:updated'));
                } catch (e) {
                    // Fallback si fetch/JSON KO
                    window.location.href = href;
                } finally {
                    btn.disabled = false;
                    btn.style.opacity = '1';
                }
            }

            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const isOpen = menu.classList.contains('is-open');
                if (isOpen) closeMenu();
                else {
                    menu.classList.add('is-open');
                    btn.setAttribute('aria-expanded','true');
                }
            });

            // Intercepter le clic sur un filtre (sans reload)
            menu.addEventListener('click', (e) => {
                const a = e.target.closest && e.target.closest('a.filters-item');
                if (!a) return;
                e.preventDefault();
                closeMenu();
                applyFilter(a.href);
            });

            document.addEventListener('click', (e) => {
                if (e.target.closest('#reviewsFilterBtn')) return;
                if (e.target.closest('#reviewsFilterMenu')) return;
                closeMenu();
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeMenu();
            });
        })();
    </script>
    <!-- Tri des avis: liens classiques (fiable, sans AJAX) -->
</body>
</html>
