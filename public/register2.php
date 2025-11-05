<?php
session_start();
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nom']) && isset($_POST['prenom']) && isset($_POST['pseudo']) && !isset($_POST['email'])) {
  $_SESSION['register_step1'] = [
    'nom' => strtoupper(trim($_POST['nom'])),
    'prenom' => preg_replace_callback('/-(\w)/', function($matches){ return '-' . strtoupper($matches[1]); }, (strlen(trim($_POST['prenom'])) ? strtoupper(substr(trim($_POST['prenom']),0,1)) . substr(trim($_POST['prenom']),1) : '')),
    'pseudo' => trim($_POST['pseudo']),
  ];
} 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && isset($_SESSION['register_step1'])) {
  $email = trim($_POST['email'] ?? '');
  $telephone = trim($_POST['telephone'] ?? '');
  $naissance = trim($_POST['naissance'] ?? '');
  $step1 = $_SESSION['register_step1'];
  $lineData = [$step1['nom'], $step1['prenom'], $step1['pseudo'], $email, $telephone, $naissance];
  $csvPath = __DIR__ . '/partials/data.csv';

  $fp = @fopen($csvPath, 'a');
  if ($fp) {
    fputcsv($fp, $lineData, ';');
    fclose($fp);
    $message = "<p style='color:green;'>Inscription terminée et enregistrée.</p>";
    unset($_SESSION['register_step1']);
  } else {
    $message = "<p style='color:red;'>Impossible d'écrire le fichier de données. Vérifiez permissions.</p>";
  }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Créer un compte - Alizon</title>
  <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;700&family=Quicksand:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../src/styles/styleRegister.css">
</head>
<body>
  <div class="card">
    <div class="logo">
      <img src="../src/img/svg/logo-text.svg" alt="Logo Alizon">
    </div>

    <h1>Créer un compte</h1>
    <p class="subtitle">Coordonnées</p>

  <form action="register2.php" method="POST">
      <div>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="exemple@domaine.extension" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
      </div>

      <div>
        <label for="telephone">Numéro de téléphone</label>
        <input type="text" id="telephone" name="telephone" placeholder="ex: 0645893548" value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>" required>
      </div>

      <div>
        <label for="naissance">Date de naissance</label>
        <input type="text" id="naissance" name="naissance" placeholder="JJ/MM/AAAA" value="<?= htmlspecialchars($_POST['naissance'] ?? '') ?>" required>
      </div>

      <div class="error">
        <?= $message ?: (isset($_SESSION['register_step1']) ? '<p> </p>' : '<p> </p>') ?>
      </div>

      <div class="step">étape 2 / 2</div>

      <!-- Bouton Précédent centré (même place que dans register.php), utilise double flèche -->
      <div class="next-btn prev-btn" role="group" aria-label="Précédent action">
        <span class="next-text">Précédent</span>
        <button type="button" class="arrow-only" aria-label="Précédent" onclick="location.href='register.php';">
          <img src="../src/img/svg/fleche-gauche.svg" alt="Précédent" style="filter : invert(1) saturate(0.9)" class="btn-arrow-left" aria-hidden="true">
        </button>
      </div>

      <div class="nav-actions" role="group" aria-label="Navigation">
          <!-- Bouton Suivant / Terminer : flèche à gauche pointant vers la droite + texte -->
          <button type="submit" class="btn-next" aria-label="Terminer">
            <img src="../src/img/svg/fleche-gauche.svg" alt="Suivant" class="nav-arrow right" aria-hidden="true">
            <span class="nav-label">Suivant</span>
          </button>
        </div>
    </form>
  </div>
</body>
</html>