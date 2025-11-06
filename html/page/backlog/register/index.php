<!DOCTYPE html>
<html lang="fr">
<?php 
    print_r($_POST);
?>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Créer un compte - Alizon</title>
  <link
    href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;700&family=Quicksand:wght@300;400;500;700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="../src/styles/Register/styleRegister.css">
</head>
<style>
  body {
    background: linear-gradient(to bottom right, #CD7F32, #D4183D);
  }
  .card[id="3"],
  .card[id="2"] {
    height: 620px !important;
  }
</style>
<body>
  <div class="card" id="1">
    <div class="logo">
      <img src="../src/img/svg/logo-text.svg" alt="Logo Alizon">
    </div>

    <h1>Créer un compte</h1>
    <p class="subtitle">Identifiants</p>

    <form action="register.php" method="post" enctype="multipart/form-data">
      <div>
        <label for="nom">Raison sociale</label>
        <input type="text" id="nom" name="nom" placeholder="Votre nom" required>
      </div>

      <div>
        <label for="prenom">Adresse postale du siège</label>
        <input type="text" id="prenom" name="prenom" placeholder="Votre prénom" required>
      </div>

      <div>
        <label for="pseudo">N° SIREN</label>
        <input type="text" id="pseudo" name="pseudo" placeholder="Votre pseudonyme" required>
      </div>

      <div class="error">
        
      </div>

      <div class="step">étape 1 / 3</div>

      <div class="next-btn" role="group" aria-label="Suivant action">
        <span class="next-text">Suivant</span>
        <button type="button" class="arrow-only" aria-label="Suivant" onclick="showNextCard()">
          <img src="../src/img/svg/fleche-gauche.svg" alt="" style="filter : invert(1) saturate(0.9)" class="btn-arrow"
            aria-hidden="true">
        </button>
      </div>
    </form>
  </div>

        <!-- Card2 -->


  <div class="card hidden" id="2">
    <div class="logo">
      <img src="../src/img/svg/logo-text.svg" alt="Logo Alizon">
    </div>

    <h1>Créer un compte</h1>
    <p class="subtitle">Coordonnées</p>

    <form>

      <div>
        <label for="telephone">Numéro de téléphone</label>
        <input type="tel" id="telephone" name="telephone" placeholder="ex: 0645893548" value="" required>
      </div>

      <div>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="exemple@domaine.extension" value="" required>
      </div>

      <div class="error">
      </div>

      <div class="step">étape 2 / 3</div>

      <!-- Bouton Précédent centré (même place que dans register.php), utilise double flèche -->
      <div class="inline-flex">
        <div class="next-btn" role="group" aria-label="Précédent action">
          <span class="next-text">Précédent</span>
          <button type="button" class="arrow-only" aria-label="Précédent" onclick="showPreviousCard()">
            <img src="../src/img/svg/fleche-gauche.svg" alt="Précédent" style="filter : invert(1) saturate(0.9)"
              class="btn-arrow-left" aria-hidden="true">
          </button>
        </div>
        <div class="next-btn" role="group" aria-label="Suivant action">
          <span class="next-text">Suivant</span>
          <button type="button" class="arrow-only" aria-label="Suivant" onclick="showNextCard()">
            <img src="../src/img/svg/fleche-gauche.svg" alt="" style="filter : invert(1) saturate(0.9)"
              class="btn-arrow" aria-hidden="true">
          </button>
        </div>
      </div>
    </form>
  </div>

        <!-- Card3 -->

  <div class="card hidden" id="3">
    <div class="logo">
      <img src="../src/img/svg/logo-text.svg" alt="Logo Alizon">
    </div>

    <h1>Créer un compte</h1>
    <p class="subtitle">Mot de passe</p>

    <form>

      <div>
        <label for="mdp">Mot de passe</label>
        <input type="password" id="mdp" name="mdp" placeholder="***********" value="" required>
      </div>

      <div>
        <label for="Cmdp">Confirmer le mot de passe</label>
        <input type="password" id="Cmdp" name="Cmdp" placeholder="**********" value="" required>
      </div>

      <div class="error">
      </div>

      <div class="step">étape 3 / 3</div>

      <!-- Bouton Précédent centré (même place que dans register.php), utilise double flèche -->
      <div class="inline-flex">
        <div class="next-btn" role="group" aria-label="Précédent action">
          <span class="next-text">Précédent</span>
          <button type="button" class="arrow-only" aria-label="Précédent" onclick="showPreviousCard()">
            <img src="../src/img/svg/fleche-gauche.svg" alt="Précédent" style="filter : invert(1) saturate(0.9)"
              class="btn-arrow-left" aria-hidden="true">
          </button>
        </div>
        <div class="next-btn" role="group" aria-label="Suivant action">
          <span class="next-text">Terminer</span>
          <button type="submit" id="finishBtn" class="arrow-only" aria-label="Terminer">
            <img src="../src/img/svg/fleche-gauche.svg" alt="" style="filter : invert(1) saturate(0.9)"
              class="btn-arrow" aria-hidden="true">
          </button>
        </div>
      </div>
    </form>
  <script src="../src/js/registerPass.js" ></script>
  </script>
</body>
</html>

