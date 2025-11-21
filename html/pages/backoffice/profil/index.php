<?php
session_start();
include '../../../selectBDD.php'; 

if (!isset($_SESSION['vendeur_id'])) {
    die("Vous n'êtes pas connecté.");
}

$vendeur_id = $_SESSION['vendeur_id'];

try {
    $query = "
        SELECT
            v.denomination AS pseudo,
            v.raison_sociale AS rsociale,
            v.siren AS siren,
            c.id_compte AS compte,
            c.email AS email,
            c.num_telephone AS telephone,
            a.a_adresse AS adresse,
            a.a_code_postal AS codep,
            a.a_ville AS ville,
            a.a_complement AS complement,
            i.id_image,
            i.i_lien AS image
        FROM cobrec1._compte c
        LEFT JOIN cobrec1._vendeur v ON c.id_compte = v.id_compte
        LEFT JOIN cobrec1._adresse a ON v.id_compte = a.id_compte
        LEFT JOIN cobrec1._represente_compte r ON c.id_compte = r.id_compte
        LEFT JOIN cobrec1._image i ON r.id_image = i.id_image
        WHERE v.id_vendeur = :id
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $vendeur_id]);
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

    /* ---------------------------------------------------------
       1. Mise à jour table _vendeur
    --------------------------------------------------------- */

    // PSEUDO
    if (isset($_POST['pseudo']) && $_POST['pseudo'] !== $old['pseudo']) {
      $stmt = $pdo->prepare("
            UPDATE cobrec1._vendeur
            SET denomination = :val
            WHERE id_vendeur = :id
        ");
        $stmt->execute([
            'val' => trim($_POST['pseudo']),
            'id'  => $vendeur_id
        ]);
    }

    // RAISON SOCIALE
    if (isset($_POST['rsociale']) && $_POST['rsociale'] !== $old['rsociale']) {
        $stmt = $pdo->prepare("
            UPDATE cobrec1._vendeur
            SET raison_sociale = :val
            WHERE id_vendeur = :id
        ");
        $stmt->execute([
            'val' => trim($_POST['rsociale']),
            'id'  => $vendeur_id
        ]);
    }

    // SIREN
    if (isset($_POST['siren']) && $_POST['siren'] !== $old['siren']) {
        $stmt = $pdo->prepare("
            UPDATE cobrec1._vendeur
            SET siren = :val
            WHERE id_vendeur = :id
        ");
        $stmt->execute([
            'val' => trim($_POST['siren']),
            'id'  => $vendeur_id
        ]);
    }



    /* ---------------------------------------------------------
       2. Mise à jour table _compte
    --------------------------------------------------------- */

    // EMAIL
    if (isset($_POST['email']) && $_POST['email'] !== $old['email']) {
        $stmt = $pdo->prepare("
            UPDATE cobrec1._compte
            SET email = :val
            WHERE id_compte = :id
        ");
        $stmt->execute([
            'val' => trim($_POST['email']),
            'id'  => $vendeur['compte']
        ]);
    }

    // TÉLÉPHONE
    if (isset($_POST['telephone']) && $_POST['telephone'] !== $old['telephone']) {
        $stmt = $pdo->prepare("
            UPDATE cobrec1._compte
            SET num_telephone = :val
            WHERE id_compte = :id
        ");
        $stmt->execute([
            'val' => trim($_POST['telephone']),
            'id'  => $vendeur['compte']
        ]);
    }



    /* ---------------------------------------------------------
       3. Mise à jour table _adresse
    --------------------------------------------------------- */

    // ADRESSE
    if (isset($_POST['adresse']) && $_POST['adresse'] !== $old['adresse']) {
        $stmt = $pdo->prepare("
            UPDATE cobrec1._adresse
            SET a_adresse = :val
            WHERE id_compte = :id
        ");
        $stmt->execute([
            'val' => trim($_POST['adresse']),
            'id'  => $vendeur['compte']
        ]);
    }

    // VILLE
    if (isset($_POST['ville']) && $_POST['ville'] !== $old['ville']) {
        $stmt = $pdo->prepare("
            UPDATE cobrec1._adresse
            SET a_ville = :val
            WHERE id_compte = :id
        ");
        $stmt->execute([
            'val' => trim($_POST['ville']),
            'id'  => $vendeur['compte']
        ]);
    }
    

    // CODE POSTAL
    if (isset($_POST['codep']) && $_POST['codep'] !== $old['codep']) {
        $stmt = $pdo->prepare("
            UPDATE cobrec1._adresse
            SET a_code_postal = :val
            WHERE id_compte = :id
        ");
        $stmt->execute([
            'val' => trim($_POST['codep']),
            'id'  => $vendeur['compte']
        ]);
    }

    // COMPLEMENT
    if (isset($_POST['complement']) && $_POST['complement'] !== $old['complement']) {
        $stmt = $pdo->prepare("
            UPDATE cobrec1._adresse
            SET a_complement = :val
            WHERE id_compte = :id
        ");
        $stmt->execute([
            'val' => trim($_POST['complement']),
            'id'  => $vendeur['compte']
        ]);
    }


    /* ---------------------------------------------------------
      4. Mise à jour de l'image du vendeur
      --------------------------------------------------------- */
      if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {

          // Dossier cible
          $uploadDir = "../../../img/photo/";
          
          // Nom unique
          $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
          $filename = "vendeur_" . $vendeur['compte'] . "_" . time() . "." . $ext;

          // Chemin final
          $filePath = $uploadDir . $filename;

          // Déplace le fichier
          if (move_uploaded_file($_FILES['image']['tmp_name'], $filePath)) {

              // Nettoyage pour le chemin dans la BDD
              $dbPath = "html/img/photo/" . $filename;
              $ImageId = $vendeur['id_image'];

              /* ---- 1) Insérer l'image dans _image ---- */
              $stmt = $pdo->prepare("
                  UPDATE cobrec1._image
                  SET id_image = :lien
                  WHERE id_image = :img
              ");

              $stmt->execute([
                    'img' => $ImageId,
                    'lien' => $dbPath
              ]);
      }
    }

    /* ---------------------------------------------------------
       Redirection
    --------------------------------------------------------- */
    header("Location: index.php?success=1");
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

        <form id="edit-form" class="edit-form" action="" method="POST" enctype="multipart/form-data">

          <div class="form-row">
            <label>Changer l'image</label>
            <input type="file" name="image" accept="image/*">
          </div>

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
