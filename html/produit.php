<?php
$fichierCSV = realpath(__DIR__ . '/../src/data/mls.csv');
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
                $produitTemp['p_note'] = (float) $produitTemp['p_note'];
                $produitTemp['p_nb_ventes'] = (int) $produitTemp['p_nb_ventes'];
                $produitTemp['discount_percentage'] = (float) $produitTemp['discount_percentage'];
                $produitTemp['review_count'] = (int) $produitTemp['review_count'];
                $produitTemp['avg_rating'] = (float) $produitTemp['avg_rating'];

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
$note = $produit['avg_rating'] ? round($produit['avg_rating'], 1) : 0;
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
    <header class="site-header" role="banner">
        <div class="header-inner">
            <div class="logo-container">
                <a href="/" class="brand">
                    <img src="/src/img/svg/logo-text.svg" alt="Alizon" class="logo" />
                </a>
            </div>

            <div class="search-container">
                <img src="/src/img/svg/loupe.svg" alt="Loupe de recherche" class="fas fa-shopping-cart icon loupe-icon">
                <input type="text" placeholder="Rechercher des produits..." class="search-input">
            </div>

            <div class="icons-container">
                <a href="#" class="icon-link">
                    <img src="/src/img/svg/profile.svg" alt="Profile" class="fas fa-shopping-cart icon">
                </a>
                <a href="#" class="icon-link">
                    <img src="/src/img/svg/panier.svg" alt="Panier" class="fas fa-shopping-cart icon"
                        style="filter: invert(1) saturate(0.9);">
                </a>
            </div>
        </div>
    </header>

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
                                <img src="/src/img/svg/star-full.svg" alt="Etoile" width="20">
                            <?php else: ?>
                                <img src="/src/img/svg/star-empty.svg" alt="Etoile" width="20">
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
            <h3>Avis clients (<?= $produit['review_count'] ?>)</h3>
            <div style="margin-bottom:20px;padding:15px;background:#f8f9fa;border-radius:8px">
                <div style="font-size:14px;color:var(--muted);margin-bottom:8px">Note moyenne</div>
                <div style="display:flex;align-items:center;gap:10px">
                    <span
                        style="font-size:32px;font-weight:700;color:var(--accent)"><?= number_format($note, 1) ?></span>
                    <div>
                        <div class="stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= $noteEntiere): ?>
                                    <img src="/src/img/svg/star-full.svg" alt="Etoile" width="16">
                                <?php else: ?>
                                    <img src="/src/img/svg/star-empty.svg" alt="Etoile" width="16">
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <div style="font-size:13px;color:var(--muted);margin-top:4px">
                            Basé sur <?= $produit['review_count'] ?> avis
                        </div>
                    </div>
                </div>
            </div>

            <div class="review">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
                    <div
                        style="width:40px;height:40px;border-radius:50%;background:linear-gradient(180deg,#eef1ff,#ffffff);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--accent)">
                        T</div>
                    <div>
                        <div style="font-weight:700">Titouan35</div>
                        <div style="color:var(--muted);font-size:13px">Excellent produit –
                            <strong>5.0</strong>
                        </div>
                    </div>
                </div>
                <div style="color:var(--muted)">Très satisfait de mon achat. Le produit correspond parfaitement à la
                    description.</div>
            </div>

            <div class="review">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
                    <div
                        style="width:40px;height:40px;border-radius:50%;background:linear-gradient(180deg,#eef1ff,#ffffff);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--accent)">
                        M</div>
                    <div>
                        <div style="font-weight:700">Marie_P</div>
                        <div style="color:var(--muted);font-size:13px">Bon rapport qualité-prix –
                            <strong><?= number_format($note, 1) ?></strong>
                        </div>
                    </div>
                </div>
                <div style="color:var(--muted)">Livraison rapide et produit de qualité. Je recommande !</div>
            </div>
        </section>
    </main>

    <footer>
        <div>
            <div>
                <a href="#"><img src="/src/img/svg/facebook-blank.svg" style="filter: invert(1);"></a>
                <a href="#"><img src="/src/img/svg/linkedin-blank.svg" style="filter: invert(1);"></a>
                <a href="#"><img src="/src/img/svg/youtube-blank.svg" style="filter: invert(1);"></a>
                <a href="#"><img src="/src/img/svg/instagram-blank.svg" style="filter: invert(1);"></a>
                <a href="#"><img src="/src/img/svg/tiktok-blank.svg" style="filter: invert(1);"></a>
                <a href="#"><img src="/src/img/svg/pinterest-blank.svg" style="filter: invert(1);"></a>
            </div>

            <nav>
                <section>
                    <h4>Alizon</h4>
                    <ul>
                        <li><a href="#">À propos</a></li>
                        <li><a href="#">Carrières</a></li>
                        <li><a href="#">Investisseurs</a></li>
                        <li><a href="#">Presse et médias</a></li>
                        <li><a href="#">Partenaires</a></li>
                        <li><a href="#">Affiliés</a></li>
                        <li><a href="#">Mentions légales</a></li>
                        <li><a href="#">Statut du service</a></li>
                    </ul>
                </section>

                <section>
                    <h4>Produits</h4>
                    <ul>
                        <li><a href="#">Shop</a></li>
                        <li><a href="#">Shop Pay</a></li>
                        <li><a href="#">Shopify Plus</a></li>
                        <li><a href="#">Shopify pour les entreprises</a></li>
                    </ul>
                </section>

                <section>
                    <h4>Développeurs</h4>
                    <ul>
                        <li><a href="#">Alizon.dev</a></li>
                        <li><a href="#">Documentation API</a></li>
                        <li><a href="#">Dev Degree</a></li>
                    </ul>
                </section>

                <section>
                    <h4>Assistance</h4>
                    <ul>
                        <li><a href="#">Assistance aux marchands</a></li>
                        <li><a href="#">Centre d'aide de Alizon</a></li>
                        <li><a href="#">Faire appel à un partenaire</a></li>
                        <li><a href="#">Alizon Academy</a></li>
                        <li><a href="#">Communauté Alizon</a></li>
                    </ul>
                </section>

                <section>
                    <h4>Solutions</h4>
                    <ul>
                        <li><a href="#">Éditeur de boutique en ligne</a></li>
                        <li><a href="#">Outil de création de site web</a></li>
                    </ul>
                </section>
            </nav>

            <div>
                <span>Conditions d'utilisation</span>
                <span>Copyright CGRRSC All right reserved</span>
                <span>Condition de ventes</span>
            </div>
        </div>
    </footer>

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
    </script>
</body>

</html>