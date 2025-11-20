<?php
session_start();
include '../../../selectBDD.php'; 

if (!isset($_SESSION['vendeur_id'])) {
    die("Vous n'êtes pas connecté.");
}

$compte_id = $_SESSION['vendeur_id'];

try {
    $query = "
        SELECT
            v.denomination AS pseudo,
            v.raison_sociale AS rsociale,
            v.siren AS siren,
            c.email AS email,
            c.num_telephone AS telephone,
            a.a_adresse AS adresse,
            a.a_code_postal AS codep,
            a.a_ville AS ville,
            a.a_complement AS complement,
            i.i_lien AS image
        FROM cobrec1._compte c
        LEFT JOIN cobrec1._vendeur v ON c.id_compte = v.id_compte
        LEFT JOIN cobrec1._adresse a ON v.id_compte = a.id_compte
        LEFT JOIN cobrec1._represente_compte r ON c.id_compte = r.id_compte
        LEFT JOIN cobrec1._image i ON r.id_image = i.id_image
        WHERE v.id_vendeur = :id
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $compte_id]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC); // récupère la ligne unique

    if (!$old) {
        die("Impossible de récupérer les informations du vendeur.");
    }

    $vendeur = $old; // utiliser pour préremplir le formulaire


} catch (PDOException $e) {
    die("Erreur lors de la récupération des informations : " . htmlspecialchars($e->getMessage()));
}

/* ---------------------------------------------------------
   TRAITEMENT DU FORMULAIRE
--------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Liste des champs par table
    $mapVendeur = [
        'pseudo'   => 'denomination',
        'rsociale' => 'raison_sociale',
        'siren'    => 'siren'
    ];

    $mapCompte = [
        'email'     => 'email',
        'telephone' => 'num_telephone'
    ];

    $mapAdresse = [
        'adresse'   => 'a_adresse',
        'codep'     => 'a_code_postal',
        'ville'     => 'a_ville',
        'complement'=> 'a_complement'
    ];

    // --- Fonction pour générer automatiquement les champs modifiés ---
    function getModifiedFields($post, $old, $mapping) {
        $changes = [];
        foreach ($mapping as $postKey => $dbField) {
            if (isset($post[$postKey]) && $post[$postKey] !== $old[$postKey]) {
                $changes[$dbField] = $post[$postKey];
            }
        }
        return $changes;
    }

    $chgVendeur = getModifiedFields($_POST, $old, $mapVendeur);
    $chgCompte  = getModifiedFields($_POST, $old, $mapCompte);
    $chgAdresse = getModifiedFields($_POST, $old, $mapAdresse);

    try {

        /* ----------------------------
           Vérification email unique
        ---------------------------- */
        if (isset($chgCompte['email'])) {
            $check = $pdo->prepare("
                SELECT 1 FROM cobrec1._compte 
                WHERE email = :email AND id_compte <> :id
            ");
            $check->execute([
                'email' => $chgCompte['email'],
                'id' => $compte_id
            ]);

            if ($check->fetch()) {
                die("Cet email est déjà utilisé par un autre compte.");
            }
        }

        /* ----------------------------
           UPDATE vendeur (si modifié)
        ---------------------------- */
        if (!empty($chgVendeur)) {
            $sql = "UPDATE cobrec1._vendeur SET ";
            $sql .= implode(", ", array_map(fn($f) => "$f = :$f", array_keys($chgVendeur)));
            $sql .= " WHERE id_compte = :id";

            $stmt = $pdo->prepare($sql);
            $chgVendeur['id'] = $compte_id;
            $stmt->execute($chgVendeur);
        }

        /* ----------------------------
           UPDATE compte (si modifié)
        ---------------------------- */
        if (!empty($chgCompte)) {
            $sql = "UPDATE cobrec1._compte SET ";
            $sql .= implode(", ", array_map(fn($f) => "$f = :$f", array_keys($chgCompte)));
            $sql .= " WHERE id_compte = :id";

            $stmt = $pdo->prepare($sql);
            $chgCompte['id'] = $compte_id;
            $stmt->execute($chgCompte);
        }

        /* ----------------------------
           UPDATE adresse (si modifié)
        ---------------------------- */
        if (!empty($chgAdresse)) {
            $sql = "UPDATE cobrec1._adresse SET ";
            $sql .= implode(", ", array_map(fn($f) => "$f = :$f", array_keys($chgAdresse)));
            $sql .= " WHERE id_compte = :id";

            $stmt = $pdo->prepare($sql);
            $chgAdresse['id'] = $compte_id;
            $stmt->execute($chgAdresse);
        }

    } catch (PDOException $e) {
        die("Erreur lors de la mise à jour : " . htmlspecialchars($e->getMessage()));
    }

    header("Location: index.php");
    exit;
}

function safe($array, $key, $default = "") {
    return htmlspecialchars(isset($array[$key]) ? $array[$key] : $default);
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=1440, height=1024">
  <title>Profil Vendeur - Alizon</title>
  <link rel="icon" type="image/png" href="../../../img/favicon.svg">
  <link rel="stylesheet" href="/styles/ProfilVendeur/profil.css">
</head>
<body>
  <div class="app">

    <?php include __DIR__ . '/../../../partials/aside.html'; ?>

    <main class="main">
      <div class="header">
        <h1 class="header__title">Profil du vendeur</h1>
      </div>

      <?php if (isset($_GET['success'])): ?>
        <div class="alert-success">Informations mises à jour avec succès !</div>
      <?php endif; ?>

      <div class="profil-card">

        <h2 class="profil-card__title">Modifier mes informations</h2>

        <div class="profil-photo">
          <img src="<?= str_replace("html/img/photo", "../../../img/photo" , htmlspecialchars($vendeur['image']))?>" alt="Photo vendeur">
        </div>

        <form id="edit-form" class="edit-form" action="" method="POST">

          <div class="form-row">
            <label>Pseudo</label>
            <input type="text" name="pseudo" value="<?= safe($vendeur, 'pseudo') ?>">
          </div>

          <div class="form-row">
            <label>Raison sociale</label>
            <input type="text" name="rsociale" value="<?= safe($vendeur, 'rsociale') ?>">
          </div>

          <div class="form-row">
            <label>SIREN</label>
            <input type="text" name="siren" value="<?= safe($vendeur, 'siren') ?>">
          </div>

          <div class="form-row">
            <label>Email</label>
            <input type="email" name="email" value="<?= safe($vendeur, 'email') ?>">
          </div>

          <div class="form-row">
            <label>Téléphone</label>
            <input type="text" name="telephone" value="<?= safe($vendeur, 'telephone') ?>">
          </div>

          <div class="form-row">
            <label>Adresse</label>
            <input type="text" name="adresse" value="<?= safe($vendeur, 'adresse') ?>">
          </div>

          <div class="form-row">
            <label>Ville</label>
            <input type="text" name="ville" value="<?= safe($vendeur, 'ville') ?>">
          </div>

          <div class="form-row">
            <label>Code postal</label>
            <input type="text" name="codep" value="<?= safe($vendeur, 'codep') ?>">
          </div>

          <div class="form-row">
            <label>Complément</label>
            <input type="text" name="complement" value="<?= safe($vendeur, 'complement') ?>">
          </div>

          <button class="btn btn--primary" type="submit">Enregistrer</button>
        </form>

      </div>

    </main>
  </div>
</body>
</html>
