<?php
session_start();
include '../../../selectBDD.php';

// Exemple : ID du vendeur connecté (à récupérer depuis $_SESSION normalement)
//$vendeur_id = $_SESSION['id'];

$compte_id = 2;

try {
    $query = "
        SELECT 
            v.raison_sociale AS Rsociale,
            v.siren AS SIREN,
            c.email AS email,
            c.num_telephone AS telephone,
            a.a_adresse AS adresse,
            a.a_code_postal AS codeP,
            a.a_ville AS ville
        FROM cobrec1._vendeur v
        LEFT JOIN cobrec1._compte c on v.id_compte = c.id_compte
        LEFT JOIN cobrec1._adresse a ON c.id_compte = a.id_compte
        WHERE v.$compte_id = :$compte_id
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['id_compte' => $compte_id]);
    $vendeur = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vendeur) {
        die("Vendeur non trouvé.");
    }

} catch (PDOException $e) {
    die("Erreur lors de la récupération des informations : " . htmlspecialchars($e->getMessage()));
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=1440, height=1024">
  <title>Profil Vendeur - Alizon</title>
  <link rel="stylesheet" href="../../../styles/ProfilVendeur/profilVendeur.css">
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
        <dl class="profil-details">
          <dt>Raison sociale</dt><dd><?= htmlspecialchars($vendeur['Rsociale']); ?></dd>
          <dt>Numéro de SIREN</dt><dd><?= htmlspecialchars($vendeur['SIREN']); ?></dd>
          <dt>Email</dt><dd><?= htmlspecialchars($vendeur['email']); ?></dd>
          <dt>Téléphone</dt><dd><?= htmlspecialchars($vendeur['telephone']); ?></dd>
          <dt>Rue</dt><dd><?= htmlspecialchars($vendeur['ville']); ?></dd>
          <dt>Code Postal</dt><dd><?= htmlspecialchars($vendeur['codeP']); ?></dd>
          <dt>Commune</dt><dd><?= htmlspecialchars($vendeur['commune']); ?></dd>
        </dl>

        <div class="profil-actions">
          <button class="btn btn--primary">Modifier mes informations</button>
        </div>
      </div>
    </main>
  </div>
</body>
</html>
