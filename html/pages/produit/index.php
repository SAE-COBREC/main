<?php
session_start();
include '../../../config.php';
$pageError = false;
$fichierCSV = realpath(__DIR__ . '/mls.csv');
$produit = null;

// Récupérer l'ID du produit depuis l'URL
$idProduit = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($idProduit > 0 && file_exists($fichierCSV)) {
    $handle = fopen($fichierCSV, 'r');
    if ($handle !== FALSE) {
        $entete = fgetcsv($handle, 1000, ',');

        while (($donnees = fgetcsv($handle, 1000, ',')) !== FALSE) {
            if (count($donnees) === count($entete)) {
                $produitTemp = array_combine($entete, $donnees);

                // Convertir les types
                $produitTemp['id_produit'] = (int) $produitTemp['id_produit'];
                $produitTemp['p_prix'] = (float) $produitTemp['p_prix'];
                $produitTemp['p_stock'] = (int) $produitTemp['p_stock'];
                $produitTemp['p_note'] = isset($produitTemp['p_note']) ? (float) $produitTemp['p_note'] : 0.0;
                $produitTemp['p_nb_ventes'] = isset($produitTemp['p_nb_ventes']) ? (int) $produitTemp['p_nb_ventes'] : 0;

                // Compatibilité avec les noms de colonnes du CSV existant
                // (certaines sources utilisent 'pourcentage_reduction', 'nombre_avis', 'note_moyenne')
                if (isset($produitTemp['pourcentage_reduction']) && !isset($produitTemp['discount_percentage'])) {
                    $produitTemp['discount_percentage'] = $produitTemp['pourcentage_reduction'];
                }
                if (isset($produitTemp['nombre_avis']) && !isset($produitTemp['review_count'])) {
                    $produitTemp['review_count'] = $produitTemp['nombre_avis'];
                }
                if (isset($produitTemp['note_moyenne']) && !isset($produitTemp['avg_rating'])) {
                    $produitTemp['avg_rating'] = $produitTemp['note_moyenne'];
                }

                // Conversions sûres (valeurs par défaut si absentes)
                $produitTemp['discount_percentage'] = isset($produitTemp['discount_percentage']) ? (float) $produitTemp['discount_percentage'] : 0.0;
                $produitTemp['review_count'] = isset($produitTemp['review_count']) ? (int) $produitTemp['review_count'] : 0;
                $produitTemp['avg_rating'] = isset($produitTemp['avg_rating']) ? (float) $produitTemp['avg_rating'] : 0.0;

                // Si c'est le produit recherché
                if ($produitTemp['id_produit'] === $idProduit) {
                    $produit = $produitTemp;
                    break;
                }
            }
        }
        fclose($handle);
    }
}

// Rediriger si produit non trouvé
if (!$produit) {
    // Afficher une page dédiée "Produit introuvable"
    include __DIR__ . '/not-found.php';
    exit;
}

// Calculer les informations du produit
$estEnRupture = $produit['p_stock'] <= 0;
$aUneRemise = !empty($produit['discount_percentage']) && $produit['discount_percentage'] > 0;
$prixFinal = $aUneRemise
    ? $produit['p_prix'] * (1 - $produit['discount_percentage'] / 100)
    : $produit['p_prix'];
// Gestion des avis/notes: handlers POST + affichage pure BDD

// Handlers POST: ajout d'avis texte et likes/dislikes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_produit'])) {
    header('Content-Type: application/json; charset=utf-8');
    $pdo->exec("SET search_path TO cobrec1");

    $action = isset($_POST['action']) ? $_POST['action'] : 'add_avis';
    $id_produit_post = isset($_POST['id_produit']) ? (int) $_POST['id_produit'] : 0;
    if ($id_produit_post <= 0 || $id_produit_post !== $idProduit) {
        echo json_encode(['success' => false, 'message' => 'Produit invalide']);
        exit;
    }

    try {
        if ($action === 'add_avis') {
            $commentaire_post = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';
            $note_post = isset($_POST['note']) ? (float) $_POST['note'] : 0.0;
            // Validation basique de la note (intervalle et pas de 0.5)
            $validSteps = [0.0,0.5,1.0,1.5,2.0,2.5,3.0,3.5,4.0,4.5,5.0];
            if (!in_array($note_post, $validSteps, true)) { $note_post = 0.0; }
            if ($note_post <= 0) {
                echo json_encode(['success' => false, 'message' => 'Veuillez sélectionner une note (au moins 0,5 étoile).']);
                exit;
            }
            // Tenter insertion avec a_note si la colonne existe, sinon fallback
            $row = null;
            try {
                // S'assurer que la colonne a_note existe (idempotent)
                $pdo->exec('ALTER TABLE _avis ADD COLUMN IF NOT EXISTS a_note numeric(2,1)');
                $pdo->exec('ALTER TABLE _avis ADD COLUMN IF NOT EXISTS a_owner_token text');
                // Générer ou récupérer un token propriétaire
                $ownerToken = isset($_COOKIE['alizon_owner']) && $_COOKIE['alizon_owner'] ? $_COOKIE['alizon_owner'] : bin2hex(random_bytes(16));
                if (!isset($_COOKIE['alizon_owner']) || !$_COOKIE['alizon_owner']) {
                    setcookie('alizon_owner', $ownerToken, time() + 3600*24*365, '/');
                }
                $stmt = $pdo->prepare("INSERT INTO _avis (id_produit, a_texte, a_pouce_bleu, a_pouce_rouge, a_timestamp_creation, a_note, a_owner_token) VALUES (:id_produit, :texte, 0, 0, NOW(), :note, :owner) RETURNING id_avis, a_timestamp_creation, TO_CHAR(a_timestamp_creation,'YYYY-MM-DD HH24:MI') AS created_at_fmt, a_note");
                $stmt->execute([':id_produit' => $id_produit_post, ':texte' => $commentaire_post, ':note' => $note_post, ':owner' => $ownerToken]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $ex) {
                // Colonne a_note absente: réessayer sans la note
                $stmt = $pdo->prepare("INSERT INTO _avis (id_produit, a_texte, a_pouce_bleu, a_pouce_rouge, a_timestamp_creation) VALUES (:id_produit, :texte, 0, 0, NOW()) RETURNING id_avis, a_timestamp_creation, TO_CHAR(a_timestamp_creation,'YYYY-MM-DD HH24:MI') AS created_at_fmt");
                $stmt->execute([':id_produit' => $id_produit_post, ':texte' => $commentaire_post]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $row['a_note'] = 0.0; // Fallback
            }
            echo json_encode([
                'success' => true,
                'message' => 'Avis enregistré',
                'id_avis' => $row['id_avis'],
                'created_at' => $row['a_timestamp_creation'],
                'created_at_fmt' => isset($row['created_at_fmt']) ? $row['created_at_fmt'] : null,
                'note' => isset($row['a_note']) ? (float)$row['a_note'] : $note_post
            ]);
            exit;
        } elseif ($action === 'vote') {
            $id_avis = isset($_POST['id_avis']) ? (int) $_POST['id_avis'] : 0;
            $value = isset($_POST['value']) ? $_POST['value'] : '';
            $prev = isset($_POST['prev']) ? $_POST['prev'] : '';
            if ($id_avis <= 0 || !in_array($value, ['plus', 'minus'], true)) {
                echo json_encode(['success' => false, 'message' => 'Paramètres de vote invalides']);
                exit;
            }
            // Si on reclique sur le même bouton: on retire le vote
            if ($prev === $value) {
                if ($value === 'plus') {
                    $sql = 'UPDATE _avis SET a_pouce_bleu = GREATEST(a_pouce_bleu - 1, 0) WHERE id_avis = :id AND id_produit = :pid RETURNING a_pouce_bleu, a_pouce_rouge';
                } else {
                    $sql = 'UPDATE _avis SET a_pouce_rouge = GREATEST(a_pouce_rouge - 1, 0) WHERE id_avis = :id AND id_produit = :pid RETURNING a_pouce_bleu, a_pouce_rouge';
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id' => $id_avis, ':pid' => $id_produit_post]);
                $counts = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'message' => 'Vote retiré', 'counts' => $counts]);
                exit;
            }
            if ($prev === 'plus' && $value === 'minus') {
                $sql = 'UPDATE _avis SET a_pouce_bleu = GREATEST(a_pouce_bleu - 1, 0), a_pouce_rouge = a_pouce_rouge + 1 WHERE id_avis = :id AND id_produit = :pid RETURNING a_pouce_bleu, a_pouce_rouge';
            } elseif ($prev === 'minus' && $value === 'plus') {
                $sql = 'UPDATE _avis SET a_pouce_rouge = GREATEST(a_pouce_rouge - 1, 0), a_pouce_bleu = a_pouce_bleu + 1 WHERE id_avis = :id AND id_produit = :pid RETURNING a_pouce_bleu, a_pouce_rouge';
            } elseif ($value === 'plus') {
                $sql = 'UPDATE _avis SET a_pouce_bleu = a_pouce_bleu + 1 WHERE id_avis = :id AND id_produit = :pid RETURNING a_pouce_bleu, a_pouce_rouge';
            } else {
                $sql = 'UPDATE _avis SET a_pouce_rouge = a_pouce_rouge + 1 WHERE id_avis = :id AND id_produit = :pid RETURNING a_pouce_bleu, a_pouce_rouge';
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id_avis, ':pid' => $id_produit_post]);
            $counts = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'message' => 'Vote pris en compte', 'counts' => $counts]);
            exit;
        } elseif ($action === 'edit_avis') {
            $id_avis = isset($_POST['id_avis']) ? (int) $_POST['id_avis'] : 0;
            $commentaire_post = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';
            if ($id_avis <= 0) {
                echo json_encode(['success' => false, 'message' => 'Avis invalide']);
                exit;
            }
            $ownerToken = isset($_COOKIE['alizon_owner']) ? $_COOKIE['alizon_owner'] : '';
            $stmt = $pdo->prepare("UPDATE _avis SET a_texte = :texte, a_timestamp_modification = NOW() WHERE id_avis = :id AND id_produit = :pid AND (a_owner_token = :owner) RETURNING TO_CHAR(a_timestamp_modification,'YYYY-MM-DD HH24:MI') AS updated_at_fmt");
            $stmt->execute([':texte' => $commentaire_post, ':id' => $id_avis, ':pid' => $id_produit_post, ':owner' => $ownerToken]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                echo json_encode(['success' => true, 'message' => 'Avis modifié', 'updated_at_fmt' => $row['updated_at_fmt']]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Non autorisé ou avis introuvable']);
            }
            exit;
        } elseif ($action === 'delete_avis') {
            $id_avis = isset($_POST['id_avis']) ? (int) $_POST['id_avis'] : 0;
            if ($id_avis <= 0) {
                echo json_encode(['success' => false, 'message' => 'Avis invalide']);
                exit;
            }
            $ownerToken = isset($_COOKIE['alizon_owner']) ? $_COOKIE['alizon_owner'] : '';
            $stmt = $pdo->prepare('DELETE FROM _avis WHERE id_avis = :id AND id_produit = :pid AND (a_owner_token = :owner) RETURNING :id AS deleted_id');
            $stmt->execute([':id' => $id_avis, ':pid' => $id_produit_post, ':owner' => $ownerToken]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && (int)$row['deleted_id'] === $id_avis) {
                echo json_encode(['success' => true, 'message' => 'Avis supprimé']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Non autorisé ou suppression non effectuée']);
            }
            exit;
        } elseif ($action === 'get_rating') {
            // Renvoie la note moyenne actuelle (vérifiée si dispo, sinon moyenne des avis) + nombre d'avis
            $avg = 0.0; $countAvis = 0;
            try {
                $stmtCnt = $pdo->prepare('SELECT COUNT(*) AS c FROM _avis WHERE id_produit = :pid');
                $stmtCnt->execute([':pid' => $id_produit_post]);
                $rc = $stmtCnt->fetch(PDO::FETCH_ASSOC);
                $countAvis = (int)($rc ? $rc['c'] : 0);

                $stmtAvg = $pdo->prepare('SELECT COALESCE(AVG(c.a_note),0) AS avg_note, COUNT(c.a_note) AS cnt FROM _avis a LEFT JOIN _commentaire c ON c.id_avis = a.id_avis WHERE a.id_produit = :pid AND c.a_note IS NOT NULL');
                $stmtAvg->execute([':pid' => $id_produit_post]);
                $row = $stmtAvg->fetch(PDO::FETCH_ASSOC);
                if ($row && (int)$row['cnt'] > 0) {
                    $avg = round((float)$row['avg_note'], 1);
                } else {
                    $stmtAvgAvis = $pdo->prepare('SELECT AVG(a.a_note) AS avg_note FROM _avis a WHERE a.id_produit = :pid AND a.a_note IS NOT NULL');
                    $stmtAvgAvis->execute([':pid' => $id_produit_post]);
                    $row2 = $stmtAvgAvis->fetch(PDO::FETCH_ASSOC);
                    if ($row2 && $row2['avg_note'] !== null) {
                        $avg = round((float)$row2['avg_note'], 1);
                    } else {
                        $avg = 0.0;
                    }
                }
                echo json_encode(['success' => true, 'avg' => $avg, 'countAvis' => $countAvis]);
                exit;
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur calcul note']);
                exit;
            }
        }
        echo json_encode(['success' => false, 'message' => 'Action inconnue']);
        exit;
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
        exit;
    }
}

// Contexte pour affichage: récupérer les avis en base pour ce produit + stats de notes vérifiées
try {
    $pdo->exec("SET search_path TO cobrec1");
} catch (Throwable $e) {
    $pageError = true;
}
$ownerTokenServer = isset($_COOKIE['alizon_owner']) ? $_COOKIE['alizon_owner'] : '';
$avisTextes = [];
try {
    // Récupère les avis et, si existantes, les notes liées (moyenne par avis)
            $stmtAvis = $pdo->prepare("
                SELECT 
                    a.id_avis,
                    a.a_texte,
                    a.a_timestamp_creation,
                    TO_CHAR(a.a_timestamp_creation,'YYYY-MM-DD HH24:MI') AS a_timestamp_fmt,
                    a.a_pouce_bleu,
                    a.a_pouce_rouge,
                    COALESCE(ROUND(AVG(c.a_note)::numeric, 1), a.a_note, 0) AS avis_note
                FROM _avis a
                LEFT JOIN _commentaire c ON c.id_avis = a.id_avis
                WHERE a.id_produit = :pid
                GROUP BY a.id_avis, a.a_texte, a.a_timestamp_creation, a.a_pouce_bleu, a.a_pouce_rouge, a.a_note
                ORDER BY a.a_timestamp_creation DESC
            ");
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
    $avisTextes = $stmtAvis->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $avisTextes = [];
}

// Moyenne de notes: priorité achats vérifiés (_commentaire), sinon moyenne des a_note de _avis
$note = 0.0;
$nbNotes = 0;
$nbAvis = is_array($avisTextes) ? count($avisTextes) : 0;
try {
    $stmtAvg = $pdo->prepare('SELECT COALESCE(AVG(c.a_note),0) AS avg_note, COUNT(c.a_note) AS cnt FROM _avis a LEFT JOIN _commentaire c ON c.id_avis = a.id_avis WHERE a.id_produit = :pid AND c.a_note IS NOT NULL');
    $stmtAvg->execute([':pid' => $idProduit]);
    $row = $stmtAvg->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $note = round((float) $row['avg_note'], 1);
        $nbNotes = (int) $row['cnt'];
    }
    if ($note <= 0) {
        // Fallback: moyenne des notes saisies sur _avis
        $stmtAvgAvis = $pdo->prepare('SELECT AVG(a.a_note) AS avg_note FROM _avis a WHERE a.id_produit = :pid AND a.a_note IS NOT NULL');
        $stmtAvgAvis->execute([':pid' => $idProduit]);
        $row2 = $stmtAvgAvis->fetch(PDO::FETCH_ASSOC);
        if ($row2 && $row2['avg_note'] !== null) {
            $note = round((float)$row2['avg_note'], 1);
        } elseif (isset($produit['avg_rating'])) {
            $note = round((float) $produit['avg_rating'], 1);
        }
    }
} catch (Exception $e) {
    if (isset($produit['avg_rating'])) {
        $note = round((float) $produit['avg_rating'], 1);
    }
}
$noteEntiere = (int) floor($note);
// Si une erreur critique est détectée, basculer sur la page produit introuvable
if ($pageError) {
    include __DIR__ . '/not-found.php';
    exit;
}
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title><?= htmlspecialchars($produit['p_nom']) ?> – Alizon</title>
    <link rel="stylesheet" href="/styles/ViewProduit/stylesView-Produit.css" />
    <link rel="stylesheet" href="/styles/Header/stylesHeader.css">
    <link rel="stylesheet" href="/styles/Footer/stylesFooter.css">
</head>

<body>
    <div id="header"></div>

    <main class="container">
        <div class="product-row">
            <!-- Vignettes -->
            <aside class="thumbs" aria-hidden="true">
                <img src="<?= htmlspecialchars($produit['image_url']) ?>" alt="vignette 1" loading="lazy" />
                <img src="<?= htmlspecialchars($produit['image_url']) ?>" alt="vignette 2" loading="lazy" />
                <img src="<?= htmlspecialchars($produit['image_url']) ?>" alt="vignette 3" loading="lazy" />
                <img src="<?= htmlspecialchars($produit['image_url']) ?>" alt="vignette 4" loading="lazy" />
            </aside>

            <!-- Image principale -->
            <section class="main-image">
                <img src="<?= htmlspecialchars($produit['image_url']) ?>"
                    alt="<?= htmlspecialchars($produit['p_nom']) ?>" />
            </section>

            <!-- Colonne droite - résumé produit -->
            <aside class="summary">
                <div class="title"><?= htmlspecialchars($produit['p_nom']) ?></div>
                <div class="rating">
                    <span class="stars" id="summaryStars" aria-hidden="true">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= $noteEntiere): ?>
                                <img src="/img/svg/star-full.svg" alt="Etoile" width="20">
                            <?php else: ?>
                                <img src="/img/svg/star-empty.svg" alt="Etoile" width="20">
                            <?php endif; ?>
                        <?php endfor; ?>
                    </span>
                    <span id="summaryRatingValue" style="color:var(--muted);font-weight:600"><?= number_format($note, 1) ?></span>
                    <span id="summaryRatingCount" style="color:var(--muted)">(<?= (int) $nbAvis ?>)</span>
                </div>
                <div class="price">
                    €<?= number_format($prixFinal, 2, ',', ' ') ?>
                    <?php if ($aUneRemise): ?>
                        <span class="old">€<?= number_format($produit['p_prix'], 2, ',', ' ') ?></span>
                    <?php endif; ?>
                </div>

                <div class="qty">
                    <button class="ghost" id="qty-decrease" aria-label="Réduire quantité">−</button>
                    <input type="number" id="qtyInput" min="1" step="1"
                        style="min-width:36px;text-align:center;background:#fff;border-radius:8px;padding:8px 10px;border:1px solid #f0f2f6"
                        value="1" aria-label="Quantité" <?= $estEnRupture ? 'disabled' : '' ?> />
                    <button class="ghost" id="qty-increase" aria-label="Augmenter quantité">+</button>
                    <button class="btn" <?= $estEnRupture ? 'disabled' : '' ?>
                        onclick="ajouterAuPanier(<?= $produit['id_produit'] ?>)">
                        <?= $estEnRupture ? 'Rupture de stock' : 'Ajouter au panier' ?>
                    </button>
                </div>

                <div style="display:flex;gap:10px;margin-top:8px">
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
                        <li>Catégorie : <?= htmlspecialchars($produit['category']) ?></li>
                        <li>Référence : #<?= $produit['id_produit'] ?></li>
                        <li>Statut : <?= htmlspecialchars($produit['p_statut']) ?></li>
                        <?php if ($aUneRemise): ?>
                            <li>Réduction : <?= round($produit['discount_percentage']) ?>%</li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="section contact" style="font-size:13px;color:var(--muted)">
                    <strong>Contact</strong>
                    <div style="margin-top:6px">Service client • <a
                            href="mailto:contact@alizon.com">contact@alizon.com</a></div>
                </div>
            </aside>
        </div>

        <!-- Description et avis -->
        <section class="section">
            <h3>Description</h3>
            <div style="display:flex;gap:8px;margin:8px 0 14px 0">
                <span style="background:#f3f5ff;color:var(--accent);padding:6px 10px;border-radius:24px;font-size:13px">
                    <?= htmlspecialchars($produit['category']) ?>
                </span>
                <?php if ($aUneRemise): ?>
                    <span style="background:#fff3cd;color:#856404;padding:6px 10px;border-radius:24px;font-size:13px">
                        -<?= round($produit['discount_percentage']) ?>% de réduction
                    </span>
                <?php endif; ?>
                <?php if ($produit['p_nb_ventes'] > 100): ?>
                    <span style="background:#d4edda;color:#155724;padding:6px 10px;border-radius:24px;font-size:13px">
                        Populaire
                    </span>
                <?php endif; ?>
            </div>
            <p style="color:var(--muted);line-height:1.6">
                <?= htmlspecialchars($produit['p_description'] ?? 'Description non disponible pour ce produit.') ?>
            </p>
        </section>

        <section class="section reviews">
            <h3>Avis clients</h3>
            <div style="margin-bottom:20px;padding:15px;background:#f8f9fa;border-radius:8px">
                <div style="font-size:14px;color:var(--muted);margin-bottom:8px">Note moyenne</div>
                <div style="display:flex;align-items:center;gap:10px">
                    <span id="reviewsRatingValue"
                        style="font-size:32px;font-weight:700;color:var(--accent)"><?= number_format($note, 1) ?></span>
                    <div>
                        <div class="stars" id="reviewsStars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= $noteEntiere): ?>
                                    <img src="/img/svg/star-full.svg" alt="Etoile" width="16">
                                <?php else: ?>
                                    <img src="/img/svg/star-empty.svg" alt="Etoile" width="16">
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <div id="reviewsRatingCount" style="font-size:13px;color:var(--muted);margin-top:4px">
                            Basé sur <?= (int) $nbAvis ?> avis
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulaire avis (carte) -->
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
                        <div class="star-input" id="inlineStarInput"
                            title="Survolez pour voir la note (la soumission de note est réservée aux achats vérifiés)">
                            <button type="button" data-value="1" aria-label="1 étoile"><img
                                    src="/img/svg/star-empty.svg" alt=""></button>
                            <button type="button" data-value="2" aria-label="2 étoiles"><img
                                    src="/img/svg/star-empty.svg" alt=""></button>
                            <button type="button" data-value="3" aria-label="3 étoiles"><img
                                    src="/img/svg/star-empty.svg" alt=""></button>
                            <button type="button" data-value="4" aria-label="4 étoiles"><img
                                    src="/img/svg/star-empty.svg" alt=""></button>
                            <button type="button" data-value="5" aria-label="5 étoiles"><img
                                    src="/img/svg/star-empty.svg" alt=""></button>
                        </div>
                        <input type="hidden" id="inlineNote" name="note" value="0">
                    </div>
                </div>
                <form id="inlineReviewForm" class="review-form">
                    <textarea name="commentaire" id="inlineComment" rows="3" class="review-textarea"
                        placeholder="Partagez votre avis (texte)..."></textarea>
                    <div class="review-actions">
                        <small class="review-hint">La notation est réservée aux achats vérifiés.</small>
                        <button type="button" class="btn" id="inlineSubmit">Publier</button>
                    </div>
                </form>
            </div>

            <!-- Liste des avis -->
            <div id="listeAvisProduit">
                <?php if (count($avisTextes) === 0): ?>
                    <p style="color:#666;">Aucun avis pour le moment. Soyez le premier !</p>
                <?php else: ?>
                    <?php foreach ($avisTextes as $ta): ?>
                        <?php 
                            $avisNote = isset($ta['avis_note']) ? (float)$ta['avis_note'] : 0.0; 
                            $avisNoteEntiere = (int) floor($avisNote);
                        ?>
                        <div class="review" data-avis-id="<?= (int) $ta['id_avis'] ?>" style="margin-bottom:12px;">
                            <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
                                <div
                                    style="width:40px;height:40px;border-radius:50%;background:linear-gradient(180deg,#eef1ff,#ffffff);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--accent)">
                                    U</div>
                                <div>
                                    <div style="font-weight:700">Utilisateur</div>
                                    <div style="color:var(--muted);font-size:13px">Avis</div>
                                    <div style="display:flex;align-items:center;gap:6px;margin-top:4px">
                                        <span class="stars" aria-hidden="true">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php if ($i <= $avisNoteEntiere): ?>
                                                    <img src="/img/svg/star-full.svg" alt="Etoile" width="16">
                                                <?php else: ?>
                                                    <img src="/img/svg/star-empty.svg" alt="Etoile" width="16">
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </span>
                                        <span style="color:var(--muted);font-weight:600;">
                                            <?= number_format($avisNote, 1) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="review-content" style="color:var(--muted)"><?= htmlspecialchars($ta['a_texte']) ?></div>
                            <div class="review-votes" style="display:flex;align-items:center;gap:10px;margin-top:8px">
                                <button class="ghost btn-vote" data-type="plus" aria-label="Vote plus">
                                    <img src="/img/svg/plus.svg" alt="Plus" width="16" height="16"> <span
                                        class="like-count"><?= (int) $ta['a_pouce_bleu'] ?></span>
                                </button>
                                <button class="ghost btn-vote" data-type="minus" aria-label="Vote moins">
                                    <img src="/img/svg/minus.svg" alt="Moins" width="16" height="16"> <span
                                        class="dislike-count"><?= (int) $ta['a_pouce_rouge'] ?></span>
                                </button>
                                <span
                                    style="font-size:12px;color:#888;margin-left:auto;"><?= htmlspecialchars($ta['a_timestamp_fmt'] ?? $ta['a_timestamp_creation']) ?></span>
                                <?php if ($ownerTokenServer && isset($ta['a_owner_token']) && $ta['a_owner_token'] === $ownerTokenServer): ?>
                                    <button class="ghost btn-edit-review" style="margin-left:8px;font-size:12px;padding:4px 8px;">Modifier</button>
                                    <button class="ghost btn-delete-review" style="margin-left:4px;font-size:12px;padding:4px 8px;color:#b00020;border-color:#f3d3d8;">Supprimer</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <div id="footer"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const dec = document.getElementById('qty-decrease');
            const inc = document.getElementById('qty-increase');
            const input = document.getElementById('qtyInput');
            if (!input) return;

            const parseStep = (v) => {
                const s = parseInt(v, 10);
                return isNaN(s) ? 1 : s;
            };

            const getMin = () => {
                const m = parseInt(input.getAttribute('min'), 10);
                return isNaN(m) ? 1 : m;
            };

            dec && dec.addEventListener('click', function (e) {
                e.preventDefault();
                let val = parseInt(input.value, 10);
                if (isNaN(val)) val = getMin();
                val = val - parseStep(input.step);
                if (val < getMin()) val = getMin();
                input.value = val;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });

            inc && inc.addEventListener('click', function (e) {
                e.preventDefault();
                let val = parseInt(input.value, 10);
                if (isNaN(val)) val = getMin();
                val = val + parseStep(input.step);
                input.value = val;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });

            input.addEventListener('wheel', function (e) { e.preventDefault(); }, { passive: false });
        });

        function ajouterAuPanier(idProduit) {
            const quantite = document.getElementById('qtyInput').value;
            alert('Produit ' + idProduit + ' ajouté au panier ! Quantité : ' + quantite);
        }

        // --- Ajout avis côté client ---
        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function postAvis(productId, commentaire, note) {
            const form = new URLSearchParams();
            form.append('id_produit', productId);
            form.append('action', 'add_avis');
            form.append('commentaire', commentaire);
            form.append('note', String(note || 0));
            return fetch('index.php?id=' + encodeURIComponent(productId), {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: form
            }).then(r => r.json());
        }

        function postVote(productId, avisId, value, prev) {
            const form = new URLSearchParams();
            form.append('id_produit', productId);
            form.append('id_avis', avisId);
            form.append('action', 'vote');
            form.append('value', value);
            form.append('prev', prev || '');
            return fetch('index.php?id=' + encodeURIComponent(productId), {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: form
            }).then(r => r.json());
        }

        function postEditAvis(productId, avisId, nouveauTexte) {
            const form = new URLSearchParams();
            form.append('id_produit', productId);
            form.append('id_avis', avisId);
            form.append('action', 'edit_avis');
            form.append('commentaire', nouveauTexte);
            return fetch('index.php?id=' + encodeURIComponent(productId), {
                method: 'POST', headers: { 'Accept': 'application/json' }, body: form
            }).then(r => r.json());
        }

        function postDeleteAvis(productId, avisId) {
            const form = new URLSearchParams();
            form.append('id_produit', productId);
            form.append('id_avis', avisId);
            form.append('action', 'delete_avis');
            return fetch('index.php?id=' + encodeURIComponent(productId), {
                method: 'POST', headers: { 'Accept': 'application/json' }, body: form
            }).then(r => r.json());
        }

        const productId = <?= (int) $idProduit ?>;
    const inlineSubmit = document.getElementById('inlineSubmit');
        const inlineNote = document.getElementById('inlineNote');
        const inlineStarInput = document.getElementById('inlineStarInput');
        const inlineComment = document.getElementById('inlineComment');
        const listeAvis = document.getElementById('listeAvisProduit');

        // Widget étoiles: hover -> remplit jusqu'à la position, clic -> sélectionne (visuel uniquement)
        (function initStarWidget() {
            if (!inlineStarInput || !inlineNote) return;
            const btns = Array.from(inlineStarInput.querySelectorAll('button[data-value]'));
            const empty = '/img/svg/star-empty.svg';
            const full = '/img/svg/star-full.svg';
            let selected = 0;

            function paint(n) {
                btns.forEach(b => {
                    const v = parseInt(b.getAttribute('data-value'), 10) || 0;
                    const img = b.querySelector('img');
                    if (!img) return;
                    img.src = v <= n ? full : empty;
                    img.alt = v <= n ? 'Etoile pleine' : 'Etoile vide';
                });
            }

            btns.forEach(b => {
                b.addEventListener('mouseenter', () => {
                    const v = parseInt(b.getAttribute('data-value'), 10) || 0;
                    paint(v);
                });
                b.addEventListener('click', () => {
                    selected = parseInt(b.getAttribute('data-value'), 10) || 0;
                    inlineNote.value = String(selected);
                    paint(selected);
                });
            });
            inlineStarInput.addEventListener('mouseleave', () => paint(selected));
            paint(selected);
        })();

        // Soumission d'un avis texte
        if (inlineSubmit && inlineComment && listeAvis) {
            inlineSubmit.addEventListener('click', () => {
                const commentaire = (inlineComment.value || '').trim();
                const selectedNote = parseFloat(inlineNote.value || '0') || 0;
                if (!commentaire) { alert('Veuillez saisir un commentaire.'); return; }
                if (selectedNote <= 0) { alert('Veuillez choisir une note (cliquez sur une étoile).'); return; }
                inlineSubmit.disabled = true;
                postAvis(productId, commentaire, selectedNote)
                    .then(data => {
                        if (!data || !data.success) {
                            alert((data && data.message) || 'Erreur');
                            return;
                        }
                        const safeComment = escapeHtml(commentaire);
                        const when = data.created_at_fmt || data.created_at || new Date().toISOString();
                        const note = typeof data.note !== 'undefined' ? (parseFloat(data.note) || 0) : selectedNote;
                        const fullCount = Math.floor(note);
                        const starImgs = Array.from({length:5}).map((_,i)=> i < fullCount ? '<img src=\"/img/svg/star-full.svg\" alt=\"Etoile\" width=\"16\">' : '<img src=\"/img/svg/star-empty.svg\" alt=\"Etoile\" width=\"16\">').join('');
                        const newHtml = `<div class=\"review\" data-avis-id=\"${data.id_avis}\" style=\"margin-bottom:12px;\">\n                            <div style=\"display:flex;align-items:center;gap:12px;margin-bottom:8px\">\n                                <div style=\"width:40px;height:40px;border-radius:50%;background:linear-gradient(180deg,#eef1ff,#ffffff);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--accent)\">U</div>\n                                <div>\n                                    <div style=\"font-weight:700\">Utilisateur</div>\n                                    <div style=\"color:var(--muted);font-size:13px\">Avis</div>\n                                    <div style=\"display:flex;align-items:center;gap:6px;margin-top:4px\">\n                                        <span class=\"stars\" aria-hidden=\"true\">${starImgs}</span>\n                                        <span style=\"color:var(--muted);font-weight:600;\">${note.toFixed(1)}</span>\n                                    </div>\n                                </div>\n                            </div>\n                            <div class=\"review-content\" style=\"color:var(--muted)\">${safeComment}</div>\n                            <div class=\"review-votes\" style=\"display:flex;align-items:center;gap:10px;margin-top:8px\">\n                                <button class=\"ghost btn-vote\" data-type=\"plus\" aria-label=\"Vote plus\" aria-pressed=\"false\"><img src=\"/img/svg/plus.svg\" alt=\"Plus\" width=\"16\" height=\"16\"> <span class=\"like-count\">0</span></button>\n                                <button class=\"ghost btn-vote\" data-type=\"minus\" aria-label=\"Vote moins\" aria-pressed=\"false\"><img src=\"/img/svg/minus.svg\" alt=\"Moins\" width=\"16\" height=\"16\"> <span class=\"dislike-count\">0</span></button>\n                                <span style=\"font-size:12px;color:#888;margin-left:auto;\">${when}</span>\n                                <button class=\"ghost btn-edit-review\" style=\"margin-left:8px;font-size:12px;padding:4px 8px;\">Modifier</button>\n                                <button class=\"ghost btn-delete-review\" style=\"margin-left:4px;font-size:12px;padding:4px 8px;color:#b00020;border-color:#f3d3d8;\">Supprimer</button>\n                            </div>\n                        </div>`;

                        // Si liste vide (paragraphe "Aucun avis"), remplacer; sinon prepend
                        const first = listeAvis.firstElementChild;
                        if (first && first.tagName.toLowerCase() === 'p') {
                            listeAvis.innerHTML = newHtml;
                        } else {
                            listeAvis.insertAdjacentHTML('afterbegin', newHtml);
                        }
                        inlineComment.value = '';
                        // reset visuel note
                        if (inlineNote) inlineNote.value = '0';
                        const starContainer = document.getElementById('inlineStarInput');
                        if (starContainer) starContainer.dispatchEvent(new Event('mouseleave'));
                        alert(data.message || 'Avis envoyé');
                    })
                    .catch(err => { console.error(err); alert('Erreur réseau'); })
                    .finally(() => { inlineSubmit.disabled = false; });
            });
        }

        // Délégation pour votes plus/minus avec bascule et mémorisation locale
        function voteKey(pid, aid) { return `vote:${pid}:${aid}`; }
        function getStoredVote(pid, aid) { try { return localStorage.getItem(voteKey(pid, aid)) || ''; } catch (_) { return ''; } }
        function setStoredVote(pid, aid, val) { try { localStorage.setItem(voteKey(pid, aid), val); } catch (_) { } }

        if (listeAvis) {
            listeAvis.addEventListener('click', (e) => {
                const t = e.target.closest('.btn-vote');
                if (!t) return;
                const reviewEl = t.closest('.review');
                const avisId = reviewEl ? parseInt(reviewEl.getAttribute('data-avis-id'), 10) : 0;
                if (!avisId) return;
                const newVal = t.getAttribute('data-type'); // 'plus' | 'minus'
                const current = getStoredVote(productId, avisId); // '' | 'plus' | 'minus'

                // Pré-maj UI optimiste
                const likeSpan = reviewEl.querySelector('.like-count');
                const dislikeSpan = reviewEl.querySelector('.dislike-count');
                const likeN = likeSpan ? parseInt(likeSpan.textContent, 10) || 0 : 0;
                const dislikeN = dislikeSpan ? parseInt(dislikeSpan.textContent, 10) || 0 : 0;

                let newLike = likeN, newDislike = dislikeN;
                if (!current) {
                    // premier vote
                    if (newVal === 'plus') newLike = likeN + 1; else newDislike = dislikeN + 1;
                } else if (current === newVal) {
                    // on retire le vote
                    if (newVal === 'plus') newLike = Math.max(0, likeN - 1); else newDislike = Math.max(0, dislikeN - 1);
                } else if (current === 'plus' && newVal === 'minus') {
                    newLike = Math.max(0, likeN - 1); newDislike = dislikeN + 1;
                } else if (current === 'minus' && newVal === 'plus') {
                    newDislike = Math.max(0, dislikeN - 1); newLike = likeN + 1;
                }
                if (likeSpan) likeSpan.textContent = newLike;
                if (dislikeSpan) dislikeSpan.textContent = newDislike;

                const buttons = reviewEl.querySelectorAll('.btn-vote');
                if (current === newVal) {
                    // unvote: aucune sélection active
                    buttons.forEach(b => b.setAttribute('aria-pressed', 'false'));
                } else {
                    buttons.forEach(b => b.setAttribute('aria-pressed', b === t ? 'true' : 'false'));
                }

                t.disabled = true;
                postVote(productId, avisId, newVal, current)
                    .then(data => {
                        if (data && data.success && data.counts) {
                            if (likeSpan) likeSpan.textContent = data.counts.a_pouce_bleu;
                            if (dislikeSpan) dislikeSpan.textContent = data.counts.a_pouce_rouge;
                            // Persister l'état local en fonction du type d'action
                            if (current === newVal) {
                                setStoredVote(productId, avisId, '');
                            } else {
                                setStoredVote(productId, avisId, newVal);
                            }
                        } else {
                            // revert
                            if (likeSpan) likeSpan.textContent = likeN;
                            if (dislikeSpan) dislikeSpan.textContent = dislikeN;
                            buttons.forEach(b => b.setAttribute('aria-pressed', b.getAttribute('data-type') === current ? 'true' : 'false'));
                            alert((data && data.message) || 'Erreur');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        if (likeSpan) likeSpan.textContent = likeN;
                        if (dislikeSpan) dislikeSpan.textContent = dislikeN;
                        buttons.forEach(b => b.setAttribute('aria-pressed', b.getAttribute('data-type') === current ? 'true' : 'false'));
                        alert('Erreur réseau');
                    })
                    .finally(() => { t.disabled = false; });
            });
            // Edition
            listeAvis.addEventListener('click', (e) => {
                const editBtn = e.target.closest('.btn-edit-review');
                if (!editBtn) return;
                const reviewEl = editBtn.closest('.review');
                if (!reviewEl) return;
                const avisId = parseInt(reviewEl.getAttribute('data-avis-id'), 10) || 0;
                if (!avisId) return;
                const contentEl = reviewEl.querySelector('.review-content');
                if (!contentEl) return;
                const original = contentEl.textContent.trim();
                // Empêcher édition multiple
                if (reviewEl.querySelector('textarea')) return;
                const ta = document.createElement('textarea');
                ta.value = original;
                ta.style.width = '100%';
                ta.style.minHeight = '90px';
                ta.style.padding = '10px 12px';
                ta.style.border = '1px solid var(--secondary-color-gris-clair, #d0d4e2)';
                ta.style.borderRadius = '8px';
                contentEl.replaceWith(ta);
                const actionsWrap = document.createElement('div');
                actionsWrap.style.display = 'flex';
                actionsWrap.style.gap = '8px';
                actionsWrap.style.marginTop = '6px';
                const btnSave = document.createElement('button');
                btnSave.textContent = 'Enregistrer';
                btnSave.className = 'btn';
                const btnCancel = document.createElement('button');
                btnCancel.textContent = 'Annuler';
                btnCancel.className = 'ghost';
                ta.after(actionsWrap);
                actionsWrap.append(btnSave, btnCancel);

                btnCancel.addEventListener('click', () => {
                    actionsWrap.remove();
                    ta.replaceWith(contentEl);
                });
                btnSave.addEventListener('click', () => {
                    const nv = ta.value.trim();
                    if (!nv) { alert('Le commentaire ne peut pas être vide'); return; }
                    btnSave.disabled = true;
                    postEditAvis(productId, avisId, nv)
                        .then(data => {
                            if (!data || !data.success) { alert((data && data.message) || 'Erreur'); return; }
                            contentEl.textContent = nv;
                            ta.replaceWith(contentEl);
                            actionsWrap.remove();
                            if (data.updated_at_fmt) {
                                const timeSpan = reviewEl.querySelector('.review-votes span[style*="font-size:12px"]');
                                if (timeSpan) timeSpan.textContent = data.updated_at_fmt + ' (modifié)';
                            }
                        })
                        .catch(err => { console.error(err); alert('Erreur réseau'); })
                        .finally(() => { btnSave.disabled = false; });
                });
            });
            // Suppression
            listeAvis.addEventListener('click', (e) => {
                const delBtn = e.target.closest('.btn-delete-review');
                if (!delBtn) return;
                const reviewEl = delBtn.closest('.review');
                if (!reviewEl) return;
                const avisId = parseInt(reviewEl.getAttribute('data-avis-id'), 10) || 0;
                if (!avisId) return;
                if (!confirm('Supprimer cet avis ?')) return;
                delBtn.disabled = true;
                postDeleteAvis(productId, avisId)
                    .then(data => {
                        if (data && data.success) {
                            reviewEl.remove();
                            // Si plus aucun avis, afficher texte
                            if (!listeAvis.querySelector('.review')) {
                                listeAvis.innerHTML = '<p style="color:#666;">Aucun avis pour le moment. Soyez le premier !</p>';
                            }
                        } else {
                            alert((data && data.message) || 'Erreur suppression');
                        }
                    })
                    .catch(err => { console.error(err); alert('Erreur réseau'); })
                    .finally(() => { delBtn.disabled = false; });
            });
            // Initialiser l'état visuel des votes en fonction des votes déjà enregistrés (localStorage)
            const reviews = Array.from(listeAvis.querySelectorAll('.review[data-avis-id]'));
            reviews.forEach((rev) => {
                const aid = parseInt(rev.getAttribute('data-avis-id'), 10);
                if (!aid) return;
                const state = getStoredVote(productId, aid);
                const buttons = rev.querySelectorAll('.btn-vote');
                buttons.forEach((b) => {
                    const type = b.getAttribute('data-type');
                    b.setAttribute('aria-pressed', state && type === state ? 'true' : 'false');
                });
            });
        }
    </script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="/js/HL_import.js"></script>
</body>

</html>