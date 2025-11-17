<?php
session_start();
include '../../../selectBDD.php'; 

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
            a.a_complement AS complement
        FROM cobrec1._compte c
        LEFT JOIN cobrec1._vendeur v ON c.id_compte = v.id_compte
        LEFT JOIN cobrec1._adresse a ON v.id_compte = a.id_compte
        LEFT JOIN cobrec1._represente_compte r ON c.id_compte = r.id_compte
        LEFT JOIN cobrec1._image i ON r.id_image = i.id_image  
        WHERE c.id_compte = :id_compte
    ";


    $stmt = $pdo->prepare($query);
    $stmt->execute(['id_compte' => $compte_id]);
    $vendeur = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur lors de la récupération des informations : " . htmlspecialchars($e->getMessage()));
}

function safe($array, $key, $default = "NULL") {
    return htmlspecialchars(isset($array[$key]) && $array[$key] !== "" ? $array[$key] : $default);
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

      <div class="profil-card">
        <h2 class="profil-card__title">Informations du compte</h2>
        <div class="profil-photo">
          <img src="<?php echo htmlspecialchars($vendeur['image']); ?>" alt="Photo du vendeur">
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
          <button class="btn btn--primary">Modifier mes informations</button>
        </div>
      </div>
    </main>
  </div>
</body>
</html>
