<?php
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
            $stmt = $pdo->prepare('INSERT INTO _avis (id_produit, a_texte, a_pouce_bleu, a_pouce_rouge, a_timestamp_creation) VALUES (:id_produit, :texte, 0, 0, NOW()) RETURNING id_avis, a_timestamp_creation');
            $stmt->execute([':id_produit' => $id_produit_post, ':texte' => $commentaire_post]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'message' => 'Avis enregistré', 'id_avis' => $row['id_avis'], 'created_at' => $row['a_timestamp_creation']]);
            exit;
        } elseif ($action === 'vote') {
            $id_avis = isset($_POST['id_avis']) ? (int) $_POST['id_avis'] : 0;
            $value = isset($_POST['value']) ? $_POST['value'] : '';
            $prev = isset($_POST['prev']) ? $_POST['prev'] : '';
            if ($id_avis <= 0 || !in_array($value, ['plus', 'minus'], true)) {
                echo json_encode(['success' => false, 'message' => 'Paramètres de vote invalides']);
                exit;
            }
            if ($prev === $value) {
                $stmt = $pdo->prepare('SELECT a_pouce_bleu, a_pouce_rouge FROM _avis WHERE id_avis = :id AND id_produit = :pid');
                $stmt->execute([':id' => $id_avis, ':pid' => $id_produit_post]);
                $counts = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'message' => 'Aucun changement', 'counts' => $counts]);
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
$avisTextes = [];
try {
    $stmtAvis = $pdo->prepare('SELECT id_avis, a_texte, a_timestamp_creation, a_pouce_bleu, a_pouce_rouge FROM _avis WHERE id_produit = :pid ORDER BY a_timestamp_creation DESC');
    $stmtAvis->execute([':pid' => $idProduit]);
    $avisTextes = $stmtAvis->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $avisTextes = [];
}

// Moyenne de notes issues des achats vérifiés (table _commentaire)
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
    if ($note <= 0 && isset($produit['avg_rating'])) {
        $note = round((float) $produit['avg_rating'], 1);
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
                    <span class="stars" aria-hidden="true">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= $noteEntiere): ?>
                                <img src="/img/svg/star-full.svg" alt="Etoile" width="20">
                            <?php else: ?>
                                <img src="/img/svg/star-empty.svg" alt="Etoile" width="20">
                            <?php endif; ?>
                        <?php endfor; ?>
                    </span>
                    <span style="color:var(--muted);font-weight:600"><?= number_format($note, 1) ?></span>
                    <span style="color:var(--muted)">(<?= (int) $nbAvis ?>)</span>
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
                    <span
                        style="font-size:32px;font-weight:700;color:var(--accent)"><?= number_format($note, 1) ?></span>
                    <div>
                        <div class="stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= $noteEntiere): ?>
                                    <img src="/img/svg/star-full.svg" alt="Etoile" width="16">
                                <?php else: ?>
                                    <img src="/img/svg/star-empty.svg" alt="Etoile" width="16">
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <div style="font-size:13px;color:var(--muted);margin-top:4px">
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
                        <div class="review" data-avis-id="<?= (int) $ta['id_avis'] ?>" style="margin-bottom:12px;">
                            <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
                                <div
                                    style="width:40px;height:40px;border-radius:50%;background:linear-gradient(180deg,#eef1ff,#ffffff);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--accent)">
                                    U</div>
                                <div>
                                    <div style="font-weight:700">Utilisateur</div>
                                    <div style="color:var(--muted);font-size:13px">Avis</div>
                                </div>
                            </div>
                            <div style="color:var(--muted)"><?= htmlspecialchars($ta['a_texte']) ?></div>
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
                                    style="font-size:12px;color:#888;margin-left:auto;"><?= htmlspecialchars($ta['a_timestamp_creation']) ?></span>
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

        function postAvis(productId, commentaire) {
            const form = new URLSearchParams();
            form.append('id_produit', productId);
            form.append('action', 'add_avis');
            form.append('commentaire', commentaire);
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
                if (!commentaire) { alert('Veuillez saisir un commentaire.'); return; }
                inlineSubmit.disabled = true;
                postAvis(productId, commentaire)
                    .then(data => {
                        if (!data || !data.success) {
                            alert((data && data.message) || 'Erreur');
                            return;
                        }
                        const safeComment = escapeHtml(commentaire);
                        const when = data.created_at || new Date().toISOString();
                        const newHtml = `<div class=\"review\" data-avis-id=\"${data.id_avis}\" style=\"margin-bottom:12px;\">\n                            <div style=\"display:flex;align-items:center;gap:12px;margin-bottom:8px\">\n                                <div style=\"width:40px;height:40px;border-radius:50%;background:linear-gradient(180deg,#eef1ff,#ffffff);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--accent)\">U</div>\n                                <div>\n                                    <div style=\"font-weight:700\">Utilisateur</div>\n                                    <div style=\"color:var(--muted);font-size:13px\">Avis</div>\n                                </div>\n                            </div>\n                            <div style=\"color:var(--muted)\">${safeComment}</div>\n                            <div class=\"review-votes\" style=\"display:flex;align-items:center;gap:10px;margin-top:8px\">\n                                <button class=\"ghost btn-vote\" data-type=\"plus\" aria-label=\"Vote plus\"><img src=\"/img/svg/plus.svg\" alt=\"Plus\" width=\"16\" height=\"16\"> <span class=\"like-count\">0</span></button>\n                                <button class=\"ghost btn-vote\" data-type=\"minus\" aria-label=\"Vote moins\"><img src=\"/img/svg/minus.svg\" alt=\"Moins\" width=\"16\" height=\"16\"> <span class=\"dislike-count\">0</span></button>\n                                <span style=\"font-size:12px;color:#888;margin-left:auto;\">${when}</span>\n                            </div>\n                        </div>`;

                        // Si liste vide (paragraphe "Aucun avis"), remplacer; sinon prepend
                        const first = listeAvis.firstElementChild;
                        if (first && first.tagName.toLowerCase() === 'p') {
                            listeAvis.innerHTML = newHtml;
                        } else {
                            listeAvis.insertAdjacentHTML('afterbegin', newHtml);
                        }
                        inlineComment.value = '';
                        // reset visuel note (non soumise côté serveur)
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
                if (current === newVal) return; // pas de changement

                // Pré-maj UI optimiste
                const likeSpan = reviewEl.querySelector('.like-count');
                const dislikeSpan = reviewEl.querySelector('.dislike-count');
                const likeN = likeSpan ? parseInt(likeSpan.textContent, 10) || 0 : 0;
                const dislikeN = dislikeSpan ? parseInt(dislikeSpan.textContent, 10) || 0 : 0;

                let newLike = likeN, newDislike = dislikeN;
                if (!current) {
                    if (newVal === 'plus') newLike = likeN + 1; else newDislike = dislikeN + 1;
                } else if (current === 'plus' && newVal === 'minus') {
                    newLike = Math.max(0, likeN - 1); newDislike = dislikeN + 1;
                } else if (current === 'minus' && newVal === 'plus') {
                    newDislike = Math.max(0, dislikeN - 1); newLike = likeN + 1;
                }
                if (likeSpan) likeSpan.textContent = newLike;
                if (dislikeSpan) dislikeSpan.textContent = newDislike;

                const buttons = reviewEl.querySelectorAll('.btn-vote');
                buttons.forEach(b => b.setAttribute('aria-pressed', b === t ? 'true' : 'false'));

                t.disabled = true;
                postVote(productId, avisId, newVal, current)
                    .then(data => {
                        if (data && data.success && data.counts) {
                            if (likeSpan) likeSpan.textContent = data.counts.a_pouce_bleu;
                            if (dislikeSpan) dislikeSpan.textContent = data.counts.a_pouce_rouge;
                            setStoredVote(productId, avisId, newVal);
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
        }
    </script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="/js/HL_import.js"></script>
</body>

</html>