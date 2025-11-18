<?php
session_start();
include '../../../selectBDD.php'; 

if (!isset($_SESSION['vendeur_id'])) {
    die("Vous n'êtes pas connecté.");
}

$compte_id = $_SESSION['vendeur_id'];
$_SESSION['creerArticle'] = [];


// -----------------------
// 1) TRAITEMENT DU FORMULAIRE
// -----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        // Mise à jour table _vendeur
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

        // Mise à jour table _compte
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


        // Mise à jour table _adresse
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

    // Rafraîchit la page pour afficher les nouvelles valeurs
    header("Location: profil.php?success=1");
    exit;
}


// -----------------------
// 2) RÉCUPÉRATION DES INFORMATIONS
// -----------------------
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
        WHERE v.id_vendeur = :id_compte
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['id_compte' => $compte_id]);
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
        <h2 class="profil-card__title">Informations du compte</h2>

        <div class="profil-photo">
          <img src="<?= safe($vendeur, 'image', '/img/default.png') ?>" alt="Photo du vendeur">
        </div>

        <dl class="profil-details">
          <dt>Pseudo</dt><dd><?= safe($vendeur, 'pseudo'); ?></dd>
          <dt>Raison sociale</dt><dd><?= safe($vendeur, 'rsociale'); ?></dd>
          <dt>Numéro de SIREN</dt><dd><?= safe($vendeur, 'siren'); ?></dd>
          <dt>Email</dt><dd><?= safe($vendeur, 'email'); ?></dd>
          <dt>Téléphone</dt><dd><?= safe($vendeur, 'telephone'); ?></dd>
          <dt>Adresse</dt><dd><?= safe($vendeur, 'adresse'); ?></dd>
          <dt>Ville</dt><dd><?= safe($vendeur, 'ville'); ?></dd>
          <dt>Code Postal</dt><dd><?= safe($vendeur, 'codep'); ?></dd>
          <dt>Complément</dt><dd><?= safe($vendeur, 'complement'); ?></dd>
        </dl>

        <div class="profil-actions">
          <button class="btn btn--primary" id="edit-btn">Modifier mes informations</button>
        </div>

        <!-- FORMULAIRE DE MODIFICATION -->
        <form id="edit-form" class="edit-form" action="" method="POST" style="display:none;">

          <h3>Modifier mes informations</h3>

          <label>Pseudo</label>
          <input type="text" name="pseudo" value="<?= safe($vendeur, 'pseudo') ?>">

          <label>Raison sociale</label>
          <input type="text" name="rsociale" value="<?= safe($vendeur, 'rsociale') ?>">

          <label>SIREN</label>
          <input type="text" name="siren" value="<?= safe($vendeur, 'siren') ?>">

          <label>Email</label>
          <input type="email" name="email" value="<?= safe($vendeur, 'email') ?>">

          <label>Téléphone</label>
          <input type="text" name="telephone" value="<?= safe($vendeur, 'telephone') ?>">

          <label>Adresse</label>
          <input type="text" name="adresse" value="<?= safe($vendeur, 'adresse') ?>">

          <label>Ville</label>
          <input type="text" name="ville" value="<?= safe($vendeur, 'ville') ?>">

          <label>Code postal</label>
          <input type="text" name="codep" value="<?= safe($vendeur, 'codep') ?>">

          <label>Complément</label>
          <input type="text" name="complement" value="<?= safe($vendeur, 'complement') ?>">

          <button class="btn btn--primary" type="submit">Enregistrer</button>
        </form>

      </div>
    </main>
  </div>

  <script>
    document.getElementById("edit-btn").addEventListener("click", function() {
        document.getElementById("edit-form").style.display = "block";
    });
  </script>

</body>
</html>
