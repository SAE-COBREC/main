<?php
include '../../../config.php';
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
    header('Location: /index.php');
    exit;
}

// Calculer les informations du produit
$estEnRupture = $produit['p_stock'] <= 0;
$aUneRemise = !empty($produit['discount_percentage']) && $produit['discount_percentage'] > 0;
$prixFinal = $aUneRemise
    ? $produit['p_prix'] * (1 - $produit['discount_percentage'] / 100)
    : $produit['p_prix'];
// Gestion des avis/notes: handler POST + affichage dynamique

// Handler POST pour soumettre un avis directement sur cette page produit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_produit'])) {
    header('Content-Type: application/json; charset=utf-8');
    $pdo->exec("SET search_path TO cobrec1");

    $id_produit_post = isset($_POST['id_produit']) ? (int)$_POST['id_produit'] : 0;
    $note_post = isset($_POST['note']) ? floatval($_POST['note']) : null;
    $commentaire_post = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';

    if ($id_produit_post <= 0 || $id_produit_post !== $idProduit) {
        echo json_encode(['success' => false, 'message' => 'Produit invalide']);
        exit;
    }

    if ($note_post !== null) {
        if ($note_post < 0 || $note_post > 5) {
            echo json_encode(['success' => false, 'message' => 'Note invalide']);
            exit;
        }
        $note_post = round($note_post * 2) / 2.0;
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO _avis (id_produit, a_texte, a_pouce_bleu, a_pouce_rouge, a_timestamp_creation) VALUES (:id_produit, :texte, 0, 0, NOW()) RETURNING id_avis');
        $stmt->execute([':id_produit' => $id_produit_post, ':texte' => $commentaire_post]);
        $id_avis = $stmt->fetchColumn();

        $dataFile = __DIR__ . '/../../../src/data/reviews.json';
        $reviews = [];
        if (file_exists($dataFile)) {
            $raw = @file_get_contents($dataFile);
            $reviews = $raw ? json_decode($raw, true) : [];
            if (!is_array($reviews)) $reviews = [];
        }

        $reviews[] = [
            'id_avis' => $id_avis ?: null,
            'id_produit' => $id_produit_post,
            'note' => $note_post,
            'commentaire' => $commentaire_post,
            'created_at' => date('c')
        ];

        @file_put_contents($dataFile, json_encode($reviews, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        echo json_encode(['success' => true, 'message' => 'Avis enregistré', 'id_avis' => $id_avis]);
        exit;
    } catch (PDOException $e) {
        $dataFile = __DIR__ . '/../../../src/data/reviews.json';
        $reviews = [];
        if (file_exists($dataFile)) {
            $raw = @file_get_contents($dataFile);
            $reviews = $raw ? json_decode($raw, true) : [];
            if (!is_array($reviews)) $reviews = [];
        }

        $reviews[] = [
            'id_avis' => null,
            'id_produit' => $id_produit_post,
            'note' => $note_post,
            'commentaire' => $commentaire_post,
            'created_at' => date('c'),
            'error' => $e->getMessage()
        ];
        @file_put_contents($dataFile, json_encode($reviews, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        echo json_encode(['success' => false, 'message' => 'Erreur serveur, votre avis a été sauvegardé localement']);
        exit;
    }
}

// Contexte pour affichage: récupérer les avis textes en base + avis locaux pour ce produit
$pdo->exec("SET search_path TO cobrec1");
$avisTextes = [];
try {
    $stmtAvis = $pdo->prepare('SELECT id_avis, a_texte, a_timestamp_creation FROM _avis WHERE id_produit = :pid ORDER BY a_timestamp_creation DESC');
    $stmtAvis->execute([':pid' => $idProduit]);
    $avisTextes = $stmtAvis->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $avisTextes = [];
}

$localReviewsPath = __DIR__ . '/../../../src/data/reviews.json';
$localReviews = [];
if (file_exists($localReviewsPath)) {
    $raw = @file_get_contents($localReviewsPath);
    $localReviews = $raw ? json_decode($raw, true) : [];
    if (!is_array($localReviews)) $localReviews = [];
}

$localNotes = array_values(array_filter($localReviews, function($r) use ($idProduit){ return isset($r['id_produit']) && (int)$r['id_produit'] === $idProduit; }));

// Calcul moyenne à partir des notes locales (la page CSV contient déjà avg_rating mais on l'enrichit)
$note = $produit['avg_rating'] ? round($produit['avg_rating'], 1) : 0;
if (count($localNotes) > 0) {
    $sum = 0; $c = 0;
    foreach ($localNotes as $ln) { if (isset($ln['note']) && $ln['note'] !== null) { $sum += floatval($ln['note']); $c++; } }
    if ($c > 0) {
        $avgLocal = $sum / $c;
        // On combine en prenant la moyenne entre la note existante et la locale pour un affichage simple
        $note = $note > 0 ? round(($note + $avgLocal) / 2, 1) : round($avgLocal, 1);
    }
}
$noteEntiere = floor($note);
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title><?= htmlspecialchars($produit['p_nom']) ?> – Alizon</title>
    <link rel="stylesheet" href="/styles/ViewProduit/stylesView-Produit.css" />
    <link rel="stylesheet" href="/styles/Header/stylesHeader.css">
    <style>
        footer {
            grid-column: 1/-1;
            background: #030212;
            color: #FFFFFF;
            padding: 3rem 2rem 2rem;
            margin-top: auto;
        }

        footer>div:first-child {
            max-width: 1400px;
            margin: 0 auto;
        }

        footer>div>div:first-child {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 3rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid #7171A3;
        }

        footer>div>div:first-child>a {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.5rem;
            text-decoration: none;
            color: #FFFFFF;
            transition: all 0.2s ease;
        }

        footer>div>div:first-child>a:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: scale(1.1);
        }

        footer nav {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        footer nav h4 {
            margin-bottom: 1rem;
            font-size: 1.1rem;
            color: #FFFFFF;
        }

        footer nav ul {
            list-style: none;
        }

        footer nav li {
            margin-bottom: 0.5rem;
        }

        footer nav a {
            color: #c0c0c0;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        footer nav a:hover {
            color: #FFFFFF;
        }

        footer>div>div:last-child {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid #7171A3;
            color: #c0c0c0;
            font-size: 0.85rem;
            display: flex;
            justify-content: center;
            gap: 2rem;
        }

        footer>div>div:last-child span {
            cursor: pointer;
            transition: color 0.2s ease;
        }

        footer>div>div:last-child span:hover {
            color: #FFFFFF;
        }
    </style>
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
                    <span style="color:var(--muted)">(<?= $produit['review_count'] ?>)</span>
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
                    <span style="font-size:32px;font-weight:700;color:var(--accent)"><?= number_format($note, 1) ?></span>
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
                            Basé sur <?= count($avisTextes) + count($localNotes) ?> avis
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulaire inline pour ajouter un avis -->
            <form id="inlineReviewForm" style="margin:8px 0 16px; display:flex; gap:8px; align-items:flex-start;">
                <select name="note" id="inlineNote" aria-label="Note">
                    <?php for($i=10;$i>=0;$i--): $val = $i/2; ?>
                        <option value="<?php echo $val ?>"><?php echo $val ?> / 5</option>
                    <?php endfor; ?>
                </select>
                <textarea name="commentaire" id="inlineComment" rows="2" placeholder="Laisser un avis..." style="flex:1;"></textarea>
                <button type="button" id="inlineSubmit">Envoyer</button>
            </form>

            <!-- Liste des avis -->
            <div id="listeAvisProduit">
                <?php if (count($localNotes) === 0 && count($avisTextes) === 0): ?>
                    <p style="color:#666;">Aucun avis pour le moment. Soyez le premier !</p>
                <?php else: ?>
                    <?php foreach ($localNotes as $ln): ?>
                        <div class="review unAvisLocal" data-note="<?= isset($ln['note']) ? htmlspecialchars($ln['note']) : '' ?>" style="margin-bottom:12px;">
                            <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
                                <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(180deg,#eef1ff,#ffffff);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--accent)">A</div>
                                <div>
                                    <div style="font-weight:700">Anonyme</div>
                                    <div style="color:var(--muted);font-size:13px">Note – <strong><?= isset($ln['note']) ? htmlspecialchars($ln['note']) : '—' ?></strong></div>
                                </div>
                            </div>
                            <div style="color:var(--muted)"><?= htmlspecialchars($ln['commentaire']) ?></div>
                            <div style="font-size:12px;color:#888;margin-top:4px;"><?= htmlspecialchars(isset($ln['created_at']) ? $ln['created_at'] : '') ?></div>
                        </div>
                    <?php endforeach; ?>

                    <?php foreach ($avisTextes as $ta): ?>
                        <div class="review" style="margin-bottom:12px;">
                            <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
                                <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(180deg,#eef1ff,#ffffff);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--accent)">U</div>
                                <div>
                                    <div style="font-weight:700">Utilisateur</div>
                                    <div style="color:var(--muted);font-size:13px">Avis</div>
                                </div>
                            </div>
                            <div style="color:var(--muted)"><?= htmlspecialchars($ta['a_texte']) ?></div>
                            <div style="font-size:12px;color:#888;margin-top:4px;"><?= htmlspecialchars($ta['a_timestamp_creation']) ?></div>
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

        function postReview(productId, note, commentaire) {
            const form = new URLSearchParams();
            form.append('id_produit', productId);
            form.append('note', note);
            form.append('commentaire', commentaire);
            return fetch('index.php?id=' + encodeURIComponent(productId), {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: form
            }).then(r => r.json());
        }

        const productId = <?= (int)$idProduit ?>;
        const inlineSubmit = document.getElementById('inlineSubmit');
        const inlineNote = document.getElementById('inlineNote');
        const inlineComment = document.getElementById('inlineComment');
        const listeAvis = document.getElementById('listeAvisProduit');

        if (inlineSubmit && inlineNote && inlineComment && listeAvis) {
            inlineSubmit.addEventListener('click', () => {
                const note = inlineNote.value;
                const commentaire = inlineComment.value.trim();
                inlineSubmit.disabled = true;
                postReview(productId, note, commentaire)
                    .then(data => {
                        alert(data.message || (data.success ? 'Avis envoyé' : 'Erreur'));
                        if (data.success) {
                            const now = new Date().toISOString();
                            const noteVal = parseFloat(note) || 0;
                            const safeComment = escapeHtml(commentaire);
                            const newHtml = `<div class=\"review unAvisLocal\" data-note=\"${noteVal}\" style=\"margin-bottom:12px;\"><div style=\"display:flex;align-items:center;gap:12px;margin-bottom:8px\"><div style=\"width:40px;height:40px;border-radius:50%;background:linear-gradient(180deg,#eef1ff,#ffffff);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--accent)\">A</div><div><div style=\"font-weight:700\">Anonyme</div><div style=\"color:var(--muted);font-size:13px\">Note – <strong>${noteVal}</strong></div></div></div><div style=\"color:var(--muted)\">${safeComment}</div><div style=\"font-size:12px;color:#888;margin-top:4px;\">${now}</div></div>`;
                            if (listeAvis.textContent && listeAvis.textContent.includes('Aucun avis')) {
                                listeAvis.innerHTML = newHtml;
                            } else {
                                listeAvis.insertAdjacentHTML('afterbegin', newHtml);
                            }
                            inlineComment.value = '';
                        }
                    })
                    .catch(err => { console.error(err); alert('Erreur réseau'); })
                    .finally(() => { inlineSubmit.disabled = false; });
            });
        }
    </script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="/js/HL_import.js"></script>
</body>

</html>