<?php
// Produit dynamique: lit l'id depuis ?id= et récupère les données dans ../../src/data/mls.csv
// Chemin CSV relatif au fichier
$csvPath = __DIR__ . 'mls.csv';

// Lire le paramètre id
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Parse CSV en tableau associatif
function parse_csv_assoc($path)
{
    if (!file_exists($path)) {
        return [];
    }
    $rows = [];
    if (($handle = fopen($path, 'r')) !== false) {
        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            return [];
        }
        while (($data = fgetcsv($handle)) !== false) {
            $item = [];
            foreach ($headers as $i => $h) {
                $item[$h] = isset($data[$i]) ? $data[$i] : null;
            }
            $rows[] = $item;
        }
        fclose($handle);
    }
    return $rows;
}

$products = parse_csv_assoc($csvPath);

// Trouver le produit
$product = null;
foreach ($products as $p) {
    // certains CSV utilisent id_produit ou id
    if ((isset($p['id_produit']) && (int) $p['id_produit'] === $id) || (isset($p['id']) && (int) $p['id'] === $id)) {
        $product = $p;
        break;
    }
}

if (!$product) {
    // si id invalide, afficher message simple
    http_response_code(404);
    ?>
    <!doctype html>
    <html lang="fr">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Produit introuvable</title>
        <link rel="stylesheet" href="/styles/ViewProduit/stylesView-Produit.css">
    </head>

    <body
        style="display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f3f4f6;margin:0;padding:24px;">
        <div class="product-row"
            style="max-width:900px;width:100%;padding:24px;background:#fff;border-radius:8px;box-shadow:0 8px 30px rgba(0,0,0,.06);display:flex;gap:24px;align-items:center;justify-content:center;">
            <aside class="thumbs" aria-hidden="true" style="display:flex;align-items:center;justify-content:center;">
                <img src="/img/svg/logo.svg" alt="" style="width:100%;border-radius:8px;object-fit:cover">
            </aside>
            <aside class="summary" style="flex:1;display:flex;flex-direction:column;gap:8px;align-items:center;">
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <div class="title" style="font-size:1.4rem;font-weight:700">Produit introuvable</div>
                    <p class="meta" style="color:#6b7280;margin:0">Aucun produit trouvé pour l'identifiant
                        <strong><?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?></strong>.
                    </p>
                    <p style="color:#374151;margin:0 0 8px">Vérifiez l'URL ou retournez à la liste des produits.</p>
                    <div style="display:flex;gap:12px;margin-top:8px;justify-content:center;"></div>
                    <div>
                        <a class="btn" href="/pages/produit/" style="text-decoration:none;max-width:120px;">Retour à la
                            liste</a>
                        <a class="ghost" href="/"
                            style="text-decoration:none;padding:8px 12px;border-radius:6px;background:transparent;border:1px solid #e5e7eb;color:#374151;max-width:100px;">Accueil</a>
                    </div>
                </div>
        </div>
        </aside>
        </div>
    </body>

    </html>
    <?php
    exit;
}

// Helpers
function h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

$name = $product['p_nom'] ?? $product['name'] ?? 'Produit';
$description = $product['p_description'] ?? '';
$price = isset($product['p_prix']) ? (float) $product['p_prix'] : 0.0;
$percent = isset($product['pourcentage_reduction']) ? (float) $product['pourcentage_reduction'] : 0.0;
$oldPrice = $percent > 0 ? round($price / (1 - $percent / 100), 2) : null;
$rating = isset($product['note_moyenne']) && $product['note_moyenne'] !== '' ? (float) $product['note_moyenne'] : (isset($product['p_note']) ? (float) $product['p_note'] : 0.0);
$reviews = isset($product['nombre_avis']) ? (int) $product['nombre_avis'] : 0;

// images: supporter plusieurs URLs séparées par , ou ; sinon utiliser image_url et fallback samples
$images = [];
if (!empty($product['image_url'])) {
    // splitter par , ou ;
    $parts = preg_split('/[,;]+\s*/', $product['image_url']);
    foreach ($parts as $u) {
        $u = trim($u);
        if ($u !== '')
            $images[] = $u;
    }
}

// si pas d'images, fallback sur images locales
if (empty($images)) {
    $images = ['/img/produit-rouge.jpg', '/img/sample-1.jpg', '/img/sample-2.jpg', '/img/sample-3.jpg'];
}

// garantir que les chemins absolus ou relatifs conviennent au serveur (ici on laisse tels quels)

?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title><?= h($name) ?> — Produit</title>
    <link rel="stylesheet" href="/styles/ViewProduit/stylesView-Produit.css" />
</head>

<body>
    <main class="container">
        <div class="product-row">
            <!-- Vignettes -->
            <aside class="thumbs" aria-hidden="true">
                <?php foreach ($images as $img): ?>
                    <img src="<?= h($img) ?>" alt="vignette" loading="lazy" />
                <?php endforeach; ?>
            </aside>

            <!-- Image principale -->
            <section class="main-image">
                <img src="<?= h($images[0]) ?>" alt="Image principale du produit" />
            </section>

            <!-- Colonne droite - résumé produit -->
            <aside class="summary">
                <div class="title"><?= h($name) ?></div>
                <div class="rating">
                    <span class="stars" aria-hidden="true">
                        <?php
                        $full = floor($rating);
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= $full) {
                                echo '<img src="/img/svg/star-full.svg" alt="Etoile" width="20">';
                            } else {
                                echo '<img src="/img/svg/star-empty.svg" alt="Etoile" width="20">';
                            }
                        }
                        ?>
                    </span>
                    <span class="score"><?= number_format($rating, 1) ?></span>
                    <span class="count">(<?= h($reviews) ?>)</span>
                </div>
                <div class="price">€ <?= number_format($price, 2, ',', ' ') ?><?php if ($oldPrice): ?> <span
                            class="old">€ <?= number_format($oldPrice, 2, ',', ' ') ?></span><?php endif; ?></div>

                <div class="qty">
                    <button class="ghost" id="qty-decrease" aria-label="Réduire quantité">−</button>
                    <input type="number" id="qtyInput" min="1" step="1" value="1" aria-label="Quantité" />
                    <button class="ghost" id="qty-increase" aria-label="Augmenter quantité">+</button>
                    <button class="btn">Ajouter au panier</button>
                </div>

                <div class="actions-inline">
                    <button class="ghost">Ajouter aux favoris</button>
                    <button class="ghost">Partager</button>
                </div>

                <div class="meta">Livraison prévue : <strong>3-5 jours ouvrés</strong></div>

                <div class="section features">
                    <h3>Caractéristiques</h3>
                    <ul>
                        <li>Prix : € <?= number_format($price, 2, ',', ' ') ?></li>
                        <li>Stock : <?= h($product['p_stock'] ?? 'N/A') ?></li>
                        <li>Catégorie : <?= h($product['category'] ?? '') ?></li>
                    </ul>
                </div>

                <div class="section contact">
                    <strong>Contact</strong>
                    <div>Service client • <a href="mailto:contact@exemple.com">contact@exemple.com</a></div>
                </div>
            </aside>
        </div>

        <!-- Description et avis -->
        <section class="section">
            <h3>Description</h3>
            <div class="badges-container">
                <?php // si la catégorie ou tags existent, on peut afficher des badges
                if (!empty($product['category'])): ?>
                    <span class="badge"><?= h($product['category']) ?></span>
                <?php endif; ?>
            </div>
            <p><?= nl2br(h($description)) ?></p>
        </section>

        <section class="section reviews">
            <h3>Avis</h3>
            <!-- Exemple d'avis statique ; idéalement les avis seraient dans une source séparée -->
            <div class="review">
                <div class="review-header">
                    <div class="avatar">T</div>
                    <div>
                        <div class="reviewer-name">Exemple</div>
                        <div class="reviewer-meta">Commentaire d'exemple —
                            <strong><?= number_format($rating, 1) ?></strong>
                        </div>
                    </div>
                </div>
                <div class="review-text">Produit conforme à la description. Note moyenne
                    <?= number_format($rating, 1) ?>.
                </div>
            </div>
        </section>
    </main>

    <!-- Script minimal pour incrémenter/décrémenter la quantité -->
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
            // Empêcher la molette de la souris de modifier la valeur par accident
            input.addEventListener('wheel', function (e) { e.preventDefault(); }, { passive: false });
        });
    </script>
</body>

</html>