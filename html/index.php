<?php
include '../../config.php';

$pdo->exec("SET search_path TO cobrec1");

function chargerProduitsBDD($pdo)
{
    $produits = [];
    $categories = [];

    try {
        $sql = "
            SELECT 
                p.id_produit,
                p.p_nom,
                p.p_description,
                p.p_prix,
                p.p_stock,
                p.p_note as note_moyenne,
                p.p_nb_ventes,
                p.p_statut,
                COALESCE(r.reduction_pourcentage, 0) as pourcentage_reduction,
                COALESCE(avis.nombre_avis, 0) as nombre_avis,
                cp.nom_categorie as category,
                i.i_lien as image_url
            FROM _produit p
            LEFT JOIN _fait_partie_de fpd ON p.id_produit = fpd.id_produit
            LEFT JOIN _categorie_produit cp ON fpd.id_categorie = cp.id_categorie
            LEFT JOIN _en_reduction er ON p.id_produit = er.id_produit
            LEFT JOIN _reduction r ON er.id_reduction = r.id_reduction 
                AND CURRENT_TIMESTAMP BETWEEN r.reduction_debut AND r.reduction_fin
            LEFT JOIN (
                SELECT id_produit, COUNT(*) as nombre_avis 
                FROM _avis 
                GROUP BY id_produit
            ) avis ON p.id_produit = avis.id_produit
            LEFT JOIN _represente_produit rp ON p.id_produit = rp.id_produit
            LEFT JOIN _image i ON rp.id_image = i.id_image
            WHERE p.p_statut IN ('En ligne', 'En rupture')
            GROUP BY p.id_produit, cp.nom_categorie, r.reduction_pourcentage, avis.nombre_avis, i.i_lien
        ";

        $stmt = $pdo->query($sql);
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sqlCategories = "
            SELECT cp.nom_categorie as category, COUNT(*) as count
            FROM _produit p
            JOIN _fait_partie_de fpd ON p.id_produit = fpd.id_produit
            JOIN _categorie_produit cp ON fpd.id_categorie = cp.id_categorie
            WHERE p.p_statut IN ('En ligne', 'En rupture')
            GROUP BY cp.nom_categorie
        ";

        $stmtCategories = $pdo->query($sqlCategories);
        $categoriesResult = $stmtCategories->fetchAll(PDO::FETCH_ASSOC);

        foreach ($categoriesResult as $cat) {
            $categories[$cat['category']] = $cat['count'];
        }

    } catch (Exception $e) {
        echo "<p style='color: red;'>Erreur lors du chargement des produits : " . $e->getMessage() . "</p>";
    }

    return ['produits' => $produits, 'categories' => $categories];
}

function filtrerProduits($produits, $filtres)
{
    $produits_filtres = [];

    foreach ($produits as $produit) {
        if ($produit['p_prix'] > $filtres['prixMaximum']) {
            continue;
        }

        if ($filtres['categorieFiltre'] !== 'all' && $produit['category'] !== $filtres['categorieFiltre']) {
            continue;
        }

        if ($filtres['enStockSeulement'] && $produit['p_stock'] <= 0) {
            continue;
        }

        if ($produit['note_moyenne'] < $filtres['noteMinimum']) {
            continue;
        }

        $produits_filtres[] = $produit;
    }

    return $produits_filtres;
}

function trierProduits($produits, $tri_par)
{
    switch ($tri_par) {
        case 'meilleures_ventes':
            usort($produits, function ($a, $b) {
                return $b['p_nb_ventes'] - $a['p_nb_ventes'];
            });
            break;
        case 'prix_croissant':
            usort($produits, function ($a, $b) {
                return $a['p_prix'] - $b['p_prix'];
            });
            break;
        case 'prix_decroissant':
            usort($produits, function ($a, $b) {
                return $b['p_prix'] - $a['p_prix'];
            });
            break;
        case 'note':
            usort($produits, function ($a, $b) {
                return $b['note_moyenne'] - $a['note_moyenne'];
            });
            break;
    }

    return $produits;
}

function preparercategories_affichage($categories)
{
    $categories_affichage = [];
    $total_produits = 0;

    foreach ($categories as $nomCategorie => $compte) {
        $categories_affichage[] = [
            'category' => $nomCategorie,
            'count' => $compte
        ];
        $total_produits += $compte;
    }

    array_unshift($categories_affichage, [
        'category' => 'all',
        'count' => $total_produits
    ]);

    return $categories_affichage;
}

$donnees = chargerProduitsBDD($pdo);
$produits = $donnees['produits'];
$categories = $donnees['categories'];

$categorieFiltre = $_POST['category'] ?? 'all';
$noteMinimum = $_POST['note'] ?? 0;
$prixMaximum = $_POST['price'] ?? 3000;
$enStockSeulement = isset($_POST['in_stock']);
$tri_par = $_POST['sort'] ?? 'meilleures_ventes';

$filtres = [
    'categorieFiltre' => $categorieFiltre,
    'noteMinimum' => $noteMinimum,
    'prixMaximum' => $prixMaximum,
    'enStockSeulement' => $enStockSeulement
];

$produits_filtres = filtrerProduits($produits, $filtres);
$produits = trierProduits($produits_filtres, $tri_par);
$categories_affichage = preparercategories_affichage($categories);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alizon - E-commerce</title>
    <link rel="stylesheet" href="/styles/Index/style.css">
    <link rel="stylesheet" href="/styles/Header/stylesHeader.css">
</head>

<body>
    <?php
    include __DIR__ . '/partials/header.html';
    ?>

    <div class="container">
        <aside>
            <form method="POST" action="" id="filterForm">
                <div>
                    <span>Tri par :</span>
                    <select name="sort" onchange="document.getElementById('filterForm').submit()">
                        <option value="meilleures_ventes" <?= $tri_par === 'meilleures_ventes' ? 'selected' : '' ?>>
                            Meilleures ventes
                        </option>
                        <option value="prix_croissant" <?= $tri_par === 'prix_croissant' ? 'selected' : '' ?>>Prix
                            croissant</option>
                        <option value="prix_decroissant" <?= $tri_par === 'prix_decroissant' ? 'selected' : '' ?>>Prix
                            décroissant
                        </option>
                        <option value="note" <?= $tri_par === 'note' ? 'selected' : '' ?>>Mieux notés</option>
                    </select>
                </div>

                <div>
                    <h3>Filtres</h3>
                    <button type="button" onclick="reinitialiserFiltres()">Effacer</button>
                </div>

                <section>
                    <h4>Catégories</h4>
                    <div onclick="definirCategorie('all')">
                        <span>Tous les produits</span>
                        <span><?= $categories_affichage[0]['count'] ?></span>
                    </div>
                    <?php foreach ($categories_affichage as $categorie): ?>
                        <?php if ($categorie['category'] !== 'all'): ?>
                            <div onclick="definirCategorie('<?= htmlspecialchars($categorie['category']) ?>')">
                                <span><?= htmlspecialchars($categorie['category']) ?></span>
                                <span><?= $categorie['count'] ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </section>

                <section>
                    <h4>Prix</h4>
                    <div>
                        <input type="range" name="price" min="0" max="3000" value="<?= $prixMaximum ?>"
                            oninput="mettreAJourAffichagePrix(this.value)"
                            onchange="document.getElementById('filterForm').submit()">
                    </div>
                    <div>
                        <span>0€</span>
                        <span id="affichagePrixMax" ondblclick="activerEditionPrix()"><?= $prixMaximum ?>€</span>
                    </div>
                </section>

                <section>
                    <h4>Note minimum</h4>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <div onclick="definirNote(<?= $i ?>)">
                            <span><?= str_repeat('★', $i) . str_repeat('☆', 5 - $i) ?></span>
                            <span><?= $i ?> et plus</span>
                        </div>
                    <?php endfor; ?>
                </section>

                <section>
                    <h4>Disponibilité</h4>
                    <label>
                        <input type="checkbox" name="in_stock" <?= $enStockSeulement ? 'checked' : '' ?>
                            onchange="document.getElementById('filterForm').submit()">
                        <span>En stock uniquement</span>
                    </label>
                </section>

                <input type="hidden" name="category" id="champCategorie"
                    value="<?= htmlspecialchars($categorieFiltre) ?>">
                <input type="hidden" name="note" id="champNote" value="<?= $noteMinimum ?>">
            </form>
        </aside>

        <main>
            <div>
                <?php if (empty($produits)): ?>
                    <p>Aucun produit ne correspond à vos critères de recherche.</p>
                <?php else: ?>
                    <?php foreach ($produits as $produit): ?>
                        <?php
                        $estEnRupture = $produit['p_stock'] <= 0;
                        $aUneRemise = !empty($produit['pourcentage_reduction']) && $produit['pourcentage_reduction'] > 0;
                        $prixFinal = $aUneRemise
                            ? $produit['p_prix'] * (1 - $produit['pourcentage_reduction'] / 100)
                            : $produit['p_prix'];
                        $note = $produit['note_moyenne'] ? round($produit['note_moyenne']) : 0;
                        ?>
                        <article class="<?= $estEnRupture ? 'produit-rupture' : '' ?>"
                            onclick="window.location.href='produit.php?id=<?= $produit['id_produit'] ?>'">
                            <div>
                                <div>
                                    <img src="<?= htmlspecialchars($produit['image_url'] ?? '/img/default-product.jpg') ?>"
                                        alt="<?= htmlspecialchars($produit['p_nom']) ?>"
                                        class="<?= $estEnRupture ? 'image-rupture' : '' ?>">
                                </div>
                                <?php if ($aUneRemise): ?>
                                    <span>-<?= round($produit['pourcentage_reduction']) ?>%</span>
                                <?php endif; ?>
                                <?php if ($estEnRupture): ?>
                                    <div class="rupture-stock">Rupture de stock</div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h3><?= htmlspecialchars($produit['p_nom']) ?></h3>
                                <div>
                                    <span><?= str_repeat('★', $note) . str_repeat('☆', 5 - $note) ?></span>
                                    <span>(<?= $produit['nombre_avis'] ?>)</span>
                                </div>
                                <div>
                                    <span>
                                        <?php if ($aUneRemise): ?>
                                            <?= number_format($produit['p_prix'], 0, ',', ' ') ?>€
                                        <?php endif; ?>
                                    </span>
                                    <span><?= number_format($prixFinal, 0, ',', ' ') ?>€</span>
                                </div>
                                <button <?= $estEnRupture ? 'disabled' : '' ?>
                                    onclick="event.stopPropagation(); ajouterAuPanier(<?= $produit['id_produit'] ?>)">
                                    <?= $estEnRupture ? 'Indisponible' : '<img src="/img/svg/panier.svg" alt="Panier" class="panier-icon"> Ajouter au panier' ?>
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <?php
    include __DIR__ . '/partials/footer.html';
    ?>

    <script>
        function definirCategorie(categorie) {
            document.getElementById('champCategorie').value = categorie;
            document.getElementById('filterForm').submit();
        }

        function definirNote(note) {
            document.getElementById('champNote').value = note;
            document.getElementById('filterForm').submit();
        }

        function mettreAJourAffichagePrix(valeur) {
            document.getElementById('affichagePrixMax').textContent = valeur + '€';
        }

        function ajouterAuPanier(idProduit) {
            alert('Produit ' + idProduit + ' ajouté au panier !');
        }

        function reinitialiserFiltres() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'index.php';
            document.body.appendChild(form);
            form.submit();
        }

        function activerEditionPrix() {
            const affichagePrix = document.getElementById('affichagePrixMax');
            const prixActuel = affichagePrix.textContent.replace('€', '');

            const inputPrix = document.createElement('input');
            inputPrix.type = 'number';
            inputPrix.value = prixActuel;
            inputPrix.min = 0;
            inputPrix.max = 3000;
            inputPrix.style.width = '60px';

            affichagePrix.replaceWith(inputPrix);
            inputPrix.focus();
            inputPrix.select();

            inputPrix.addEventListener('blur', sauvegarderPrix);
            inputPrix.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    sauvegarderPrix();
                }
            });

            function sauvegarderPrix() {
                const nouveauPrix = parseInt(inputPrix.value) || 0;
                const prixValide = Math.min(Math.max(nouveauPrix, 0), 3000);

                document.querySelector('input[name="price"]').value = prixValide;

                const nouveauSpan = document.createElement('span');
                nouveauSpan.id = 'affichagePrixMax';
                nouveauSpan.textContent = prixValide + '€';
                nouveauSpan.ondblclick = activerEditionPrix;

                inputPrix.replaceWith(nouveauSpan);

                document.getElementById('filterForm').submit();
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const aside = document.querySelector('aside');

            if (aside) {
                aside.addEventListener('click', function () {
                    this.classList.toggle('open');
                });
            }
        });
    </script>
</body>

</html>