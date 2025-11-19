<?php
session_start();
include '../../../selectBDD.php'; 

if (!isset($_SESSION['vendeur_id'])) {
    die("Vous n'êtes pas connecté.");
}

$compte_id = $_SESSION['vendeur_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        // Update vendeur
        $sqlVendeur = "
            UPDATE cobrec1._vendeur
            SET denomination = :pseudo,
                raison_sociale = :rsociale,
                siren = :siren
            WHERE id_compte = :id
        ";
        $stmt = $pdo->prepare($sqlVendeur);
        $stmt->execute([
            'pseudo' => $_POST['pseudo'],
            'rsociale' => $_POST['rsociale'],
            'siren' => $_POST['siren'],
            'id' => $compte_id
        ]);

        // Update compte
        $sqlCompte = "
            UPDATE cobrec1._compte
            SET email = :email,
                num_telephone = :tel
            WHERE id_compte = :id
        ";
        $stmt = $pdo->prepare($sqlCompte);
        $stmt->execute([
            'email' => $_POST['email'],
            'tel' => $_POST['telephone'],
            'id' => $compte_id
        ]);

        // Update adresse
        $sqlAdresse = "
            UPDATE cobrec1._adresse
            SET a_adresse = :adresse,
                a_code_postal = :codep,
                a_ville = :ville,
                a_complement = :complement
            WHERE id_compte = :id
        ";
        $stmt = $pdo->prepare($sqlAdresse);
        $stmt->execute([
            'adresse' => $_POST['adresse'],
            'codep' => $_POST['codep'],
            'ville' => $_POST['ville'],
            'complement' => $_POST['complement'],
            'id' => $compte_id
        ]);

    } catch (PDOException $e) {
        die("Erreur lors de la mise à jour : " . htmlspecialchars($e->getMessage()));
    }

    header("Location: profil.php?success=1");
    exit;
}

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
    $vendeur = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur lors de la récupération des informations : " . htmlspecialchars($e->getMessage()));
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
          <img src="<?= str_replace("/img/photo", "../../../img/photo" , htmlspecialchars($vendeur['image']))?>" alt="Photo vendeur">
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
