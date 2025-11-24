<?php
session_start();
require_once '../../selectBDD.php';
require_once '../../pages/fonctions.php';

$pdo->exec("SET search_path TO cobrec1");

// --- LOGIQUE PRINCIPALE ---

// 1. Gestion Session / Panier
$idClient = isset($_SESSION['idClient']) ? (int)$_SESSION['idClient'] : null;

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
}

// 4. Chargement Données
$produit = ($idProduit > 0) ? chargerProduitBDD($pdo, $idProduit) : null;

if (!$produit || $produit['p_statut'] != 'En ligne') {
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

    <?php
    //inclure le pied de page du site
    include __DIR__ . '/../../partials/footer.html';
    ?>

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
