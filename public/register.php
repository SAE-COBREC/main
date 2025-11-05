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
    <p class="subtitle">Identifiants</p>

  <form action="register2.php" method="POST">
      <div>
        <label for="nom">Nom</label>
        <input type="text" id="nom" name="nom" placeholder="Votre nom" required>
      </div>

      <div>
        <label for="prenom">Prénom</label>
        <input type="text" id="prenom" name="prenom" placeholder="Votre prénom" required>
      </div>

      <div>
        <label for="pseudo">Pseudonyme</label>
        <input type="text" id="pseudo" name="pseudo" placeholder="Votre pseudonyme" required>
      </div>

      <div class="error">
        <strong>Erreur :</strong> description de l’erreur
      </div>

      <div class="step">étape 1 / 4</div>

      <div class="next-btn" role="group" aria-label="Suivant action">
        <span class="next-text">Suivant</span>
        <button type="submit" class="arrow-only" aria-label="Suivant">
          <img src="../src/img/svg/fleche-gauche.svg" alt="" style="filter : invert(1) saturate(0.9)" class="btn-arrow" aria-hidden="true">
        </button>
      </div>
    </form>
  </div>
</body>
</html>
<?php
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $pseudo = trim($_POST['pseudo'] ?? '');

    $nom = strtoupper($nom);
    $prenom = strlen($prenom) ? strtoupper(substr($prenom,0,1)) . substr($prenom,1) : '';
    $prenom = preg_replace_callback('/-(\w)/', function($matches) {
        return '-' . strtoupper($matches[1]);
    }, $prenom);
  }
?>