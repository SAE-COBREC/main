<?php
session_start();
require_once '../../selectBDD.php';
require_once '../../pages/fonctions.php';

$pdo->exec("SET search_path TO cobrec1");

// ── Paramètre ──────────────────────────────────────────────────────────────
$denomination = trim($_GET['denomination'] ?? '');

if ($denomination === '') {
    header('Location: /');
    exit;
}

// ── Infos vendeur ──────────────────────────────────────────────────────────
$stmtVendeur = $pdo->prepare("
    SELECT
        v.id_vendeur,
        v.denomination,
        v.raison_sociale,
        v.siren,
        c.email,
        c.num_telephone AS telephone,
        a.a_ville        AS ville,
        a.a_code_postal  AS code_postal,
        a.a_adresse      AS adresse,
        a.a_numero       AS numero,
        i.i_lien         AS image
    FROM cobrec1._vendeur v
    LEFT JOIN cobrec1._compte            c  ON v.id_compte   = c.id_compte
    LEFT JOIN cobrec1._adresse           a  ON v.id_compte   = a.id_compte
    LEFT JOIN cobrec1._represente_compte rc ON v.id_compte   = rc.id_compte
    LEFT JOIN cobrec1._image             i  ON rc.id_image   = i.id_image
    WHERE v.denomination ILIKE :denom
    LIMIT 1
");
$stmtVendeur->execute([':denom' => $denomination]);
$vendeur = $stmtVendeur->fetch(PDO::FETCH_ASSOC);

// Vendeur introuvable → page 404
if (!$vendeur) {
    http_response_code(404);
    include __DIR__ . '/../produit/not-found.php';
    exit;
}

// ── Produits du vendeur ────────────────────────────────────────────────────
$produitsVendeur = ProduitDenominationVendeur($pdo, $vendeur['denomination']);

// ── Statistiques rapides ───────────────────────────────────────────────────
$nbProduits = count($produitsVendeur);

$noteMoyenne = 0.0;
$nbAvisTotal = 0;
if ($nbProduits > 0) {
    $stmtStats = $pdo->prepare("
        SELECT
            ROUND(COALESCE(AVG(a.a_note), 0)::numeric, 1) AS note,
            COUNT(a.id_avis) AS nb
        FROM cobrec1._avis a
        INNER JOIN cobrec1._produit p ON a.id_produit = p.id_produit
        WHERE p.id_vendeur = :id AND a.a_note IS NOT NULL
    ");
    $stmtStats->execute([':id' => $vendeur['id_vendeur']]);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
    $noteMoyenne = (float)($stats['note'] ?? 0);
    $nbAvisTotal = (int)($stats['nb'] ?? 0);
}

// ── Panier (pour le bouton « Ajouter au panier ») ─────────────────────────
$idClient = $_SESSION['idClient'] ?? null;

if ($idClient === null) {
    if (!isset($_SESSION['panierTemp'])) {
        $_SESSION['panierTemp'] = [];
    }
} else {
    $sqlPanierClient = "
        SELECT id_panier FROM _panier_commande
        WHERE timestamp_commande IS NULL AND id_client = :idClient
    ";
    $stmtPanier = $pdo->prepare($sqlPanierClient);
    $stmtPanier->execute([':idClient' => $idClient]);
    $panier = $stmtPanier->fetch(PDO::FETCH_ASSOC);

    if ($panier) {
        $_SESSION['panierEnCours'] = (int)$panier['id_panier'];
    } else {
        $stmtCreate = $pdo->prepare("
            INSERT INTO _panier_commande (id_client, timestamp_commande)
            VALUES (:idClient, NULL) RETURNING id_panier
        ");
        $stmtCreate->execute([':idClient' => $idClient]);
        $_SESSION['panierEnCours'] = (int)$stmtCreate->fetchColumn();
    }

    transfererPanierTempVersBDD($pdo, $_SESSION['panierEnCours']);
}

// ── Helpers d'affichage ────────────────────────────────────────────────────
function renderStarsVendeur(float $note, int $size = 16): string {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($note >= $i)       $s = 'full';
        elseif ($note >= $i - 0.5) $s = 'alf';
        else                   $s = 'empty';
        $html .= '<img src="/img/svg/star-' . $s . '.svg" alt="Etoile" width="' . $size . '">';
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($vendeur['denomination']) ?> – Alizon</title>
    <link rel="icon" type="image/png" href="/img/favicon.svg">
    <link rel="stylesheet" href="/styles/Header/stylesHeader.css">
    <link rel="stylesheet" href="/styles/Footer/stylesFooter.css">
    <link rel="stylesheet" href="/styles/Vendeur/style.css">
</head>
<body>
<?php include __DIR__ . '/../../partials/header.php'; ?>

<div class="vendeur-page">

    <a href="/" class="btn-retour">
        <img src="/img/svg/back.svg" alt="" width="16" onerror="this.style.display='none'"> Retour à l'accueil
    </a>

    <!-- ── Carte profil vendeur ───────────────────────────────────────── -->
    <div class="vendeur-profil-card">
        <?php if (!empty($vendeur['image'])): ?>
            <img class="vendeur-profil-avatar"
                 src="<?= htmlspecialchars($vendeur['image']) ?>"
                 alt="<?= htmlspecialchars($vendeur['denomination']) ?>">
        <?php else: ?>
            <div class="vendeur-profil-avatar-placeholder">
                <img src="/img/svg/market.svg" alt="Vendeur">
            </div>
        <?php endif; ?>

        <div class="vendeur-profil-info">
            <h1><?= htmlspecialchars($vendeur['denomination']) ?></h1>
            <?php if (!empty($vendeur['raison_sociale'])): ?>
                <p class="vendeur-profil-raison"><?= htmlspecialchars($vendeur['raison_sociale']) ?></p>
            <?php endif; ?>

            <?php if ($noteMoyenne > 0): ?>
                <div class="vendeur-profil-note">
                    <?= renderStarsVendeur($noteMoyenne, 18) ?>
                    <strong><?= number_format($noteMoyenne, 1, ',', '') ?>/5</strong>
                    <span style="color:#9ca3af">(<?= $nbAvisTotal ?> avis)</span>
                </div>
            <?php endif; ?>

            <?php if (!empty($vendeur['ville'])): ?>
                <div class="vendeur-profil-location">
                    <img src="/img/svg/location.svg" alt="" width="14" onerror="this.style.display='none'">
                    <?php if (!empty($vendeur['code_postal'])): ?>
                        <?= htmlspecialchars($vendeur['code_postal']) ?> –
                    <?php endif; ?>
                    <?= htmlspecialchars($vendeur['ville']) ?>
                </div>
            <?php endif; ?>

            <div class="vendeur-profil-stats">
                <div class="vendeur-stat-chip">
                    <img src="/img/svg/market.svg" alt="">
                    <?= $nbProduits ?> produit<?= $nbProduits > 1 ? 's' : '' ?> en ligne
                </div>
                <?php if (!empty($vendeur['siren'])): ?>
                <div class="vendeur-stat-chip">
                    SIREN : <?= htmlspecialchars($vendeur['siren']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Grille des produits ────────────────────────────────────────── -->
    <p class="vendeur-produits-titre">
        Produits de <?= htmlspecialchars($vendeur['denomination']) ?>
        <span>(<?= $nbProduits ?>)</span>
    </p>

    <?php if (empty($produitsVendeur)): ?>
        <div class="vendeur-no-products">
            <p>Ce vendeur n'a aucun produit en ligne pour le moment.</p>
        </div>
    <?php else: ?>
    <div class="product-grid">
        <?php foreach ($produitsVendeur as $prod):
            $estEnRupture = $prod['p_stock'] <= 0;
            $discount     = (float)($prod['pourcentage_reduction'] ?? 0);
            $hasDiscount  = $discount > 0;
            $prixDiscount = $hasDiscount ? $prod['p_prix'] * (1 - $discount / 100) : $prod['p_prix'];
            $prixFinal    = calcPrixTVA($prod['tva'], $prixDiscount);
            $prixOrigTTC  = calcPrixTVA($prod['tva'], $prod['p_prix']);
            $noteArrondie = (int)round($prod['note_moyenne'] ?? 0);
            $estEnPromo   = !empty($prod['estenpromo']);
            $imageUrl     = str_replace('html/img/photo', '/img/photo', $prod['image_url'] ?? '/img/default-product.jpg');
            $origineProd  = recupOrigineProduit($pdo, $prod['id_produit']);
        ?>
        <article
            class="<?= $estEnRupture ? 'produit-rupture' : '' ?> <?= $estEnPromo ? 'produit-promotion' : '' ?>"
            onclick="window.location.href='/pages/produit/index.php?id=<?= $prod['id_produit'] ?>'">
            <div>
                <div>
                    <img src="<?= htmlspecialchars($imageUrl) ?>"
                         alt="<?= htmlspecialchars($prod['p_nom']) ?>"
                         class="<?= $estEnRupture ? 'image-rupture' : '' ?>">
                </div>
                <?php if ($hasDiscount): ?>
                    <span class="badge-reduction">-<?= round($discount) ?>%</span>
                <?php endif; ?>
                <?php if ($origineProd === 'Bretagne'): ?>
                    <span class="badge-bretagne"><img src="/img/png/badge-bretagne.png" alt="Bretagne"></span>
                <?php endif; ?>
                <?php if ($estEnRupture): ?>
                    <div class="rupture-stock">Rupture de stock</div>
                <?php endif; ?>
            </div>
            <div>
                <h3><?= htmlspecialchars($prod['p_nom']) ?></h3>
                <div>
                    <span>
                        <?php for ($i = 1; $i <= 5; $i++):
                            if ($noteArrondie >= $i)          $s = 'full';
                            elseif ($noteArrondie >= $i - 0.5) $s = 'alf';
                            else                               $s = 'empty';
                        ?>
                        <img src="/img/svg/star-<?= $s ?>.svg" alt="Etoile" width="20">
                        <?php endfor; ?>
                    </span>
                    <span>(<?= $prod['nombre_avis'] ?? 0 ?>)</span>
                </div>
                <div>
                    <span>
                        <?php if ($hasDiscount): ?>
                            <span style="text-decoration:line-through;color:#999;margin-right:5px;font-size:1.2em;">
                                <?= number_format($prixOrigTTC, 2, ',', ' ') ?>€
                            </span>
                        <?php endif; ?>
                    </span>
                    <span><?= number_format($prixFinal, 2, ',', ' ') ?>€</span>
                </div>
                <div class="product-bottom">
                    <div class="vendeur-info" style="cursor:default;">
                        <img src="/img/svg/market.svg" alt="Vendeur">
                        <span><?= htmlspecialchars($vendeur['denomination']) ?></span>
                    </div>
                    <button <?= $estEnRupture ? 'disabled' : '' ?>
                        onclick="event.stopPropagation(); ajouterAuPanier(<?= $prod['id_produit'] ?>)">
                        <?= $estEnRupture
                            ? 'Indisponible'
                            : '<img src="/img/svg/panier.svg" alt="Panier" class="panier-icon"> Ajouter au panier' ?>
                    </button>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div><!-- /vendeur-page -->

<?php
include __DIR__ . '/../../partials/footer.html';
include __DIR__ . '/../../partials/toast.html';
include __DIR__ . '/../../partials/modal.html';
?>

<script src="/js/notifications.js"></script>
<script>
// Même fonction panier que sur les autres pages
function ajouterAuPanier(idProduit) {
    fetch('/pages/produit/index.php?id=' + idProduit, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=ajouter_panier&idProduit=' + idProduit + '&quantite=1'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') showToast('Produit ajouté au panier !', 'success');
        } else {
            if (typeof showToast === 'function') showToast(data.message || 'Erreur lors de l\'ajout.', 'error');
        }
    })
    .catch(() => {
        if (typeof showToast === 'function') showToast('Erreur réseau.', 'error');
    });
}
</script>
</body>
</html>
