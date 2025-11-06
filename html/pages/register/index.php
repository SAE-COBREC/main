<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Créer un compte - Alizon</title>
  <link
    href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;700&family=Quicksand:wght@300;400;500;700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="../../styles/Register/styleRegister.css">
</head>

<style>
  body {
    background: linear-gradient(to bottom right, #7171A3, #030212);
  }
  .card[id="3"],
  .card[id="4"] {
    height: 620px !important;
  }
</style>

<body>
  <div class="card" id="1">
    <div class="logo">
      <img src="../../img/svg/logo-text.svg" alt="Logo Alizon">
    </div>

    <h1>Créer un compte</h1>
    <p class="subtitle">Identifiants</p>

    <form action="register.php" method="post" enctype="multipart/form-data">
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

      </div>

      <div class="step">étape 1 / 4</div>

      <div class="next-btn" role="group" aria-label="Suivant action">
        <span class="next-text">Suivant</span>
        <button type="button" class="arrow-only" aria-label="Suivant" onclick="showNextCard()">
          <img src="../../img/svg/fleche-gauche.svg" alt="" style="filter : invert(1) saturate(0.9)" class="btn-arrow"
            aria-hidden="true">
        </button>
      </div>
    </form>
  </div>

  <!-- Card2 -->


  <div class="card hidden" id="2">
    <div class="logo">
      <img src="../../img/svg/logo-text.svg" alt="Logo Alizon">
    </div>

    <h1>Créer un compte</h1>
    <p class="subtitle">Coordonnées</p>

    <form>
      <div>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="exemple@domaine.extension" value="" required>
      </div>

      <div>
        <label for="telephone">Numéro de téléphone</label>
        <input type="tel" id="telephone" name="telephone" placeholder="ex: 0645893548" value="" required>
      </div>

      <div>
        <label for="naissance">Date de naissance</label>
        <input type="date" id="naissance" name="naissance" placeholder="JJ/MM/AAAA" value="" required>
      </div>

      <div class="error">
      </div>

      <div class="step">étape 2 / 4</div>

      <!-- Bouton Précédent centré (même place que dans register.php), utilise double flèche -->
      <div class="inline-flex">
        <div class="next-btn" role="group" aria-label="Précédent action">
          <span class="next-text">Précédent</span>
          <button type="button" class="arrow-only" aria-label="Précédent" onclick="showPreviousCard()">
            <img src="../../img/svg/fleche-gauche.svg" alt="Précédent" style="filter : invert(1) saturate(0.9)"
              class="btn-arrow-left" aria-hidden="true">
          </button>
        </div>
        <div class="next-btn" role="group" aria-label="Suivant action">
          <span class="next-text">Suivant</span>
          <button type="button" class="arrow-only" aria-label="Suivant" onclick="showNextCard()">
            <img src="../../img/svg/fleche-gauche.svg" alt="" style="filter : invert(1) saturate(0.9)"
              class="btn-arrow" aria-hidden="true">
          </button>
        </div>
      </div>
    </form>
  </div>

  <!-- Card3 -->

  <div class="card hidden" id="3">
    <div class="logo">
      <img src="../../img/svg/logo-text.svg" alt="Logo Alizon">
    </div>

    <h1>Créer un compte</h1>
    <p class="subtitle">Coordonnées</p>

    <form>
      <div>
        <label for="rue">Rue</label>
        <input type="text" id="rue" name="rue" placeholder="ex: 19 rue Hant koz" value="" required>
      </div>

      <div class="inline-flex">
        <div class="culumn-flex" id="div_codeP">
          <label for="codeP">Code Postal</label>
          <input type="number" id="codeP" name="codeP" placeholder="ex: 22300" value="" required>
        </div>

        <div class="culumn-flex">
          <label for="commune">Commune</label>
          <input type="text" id="commune" name="commune" placeholder="ex:lannion" value="" required>
        </div>
      </div>

      <div class="error">
      </div>

      <div class="step">étape 3 / 4</div>

      <!-- Bouton Précédent centré (même place que dans register.php), utilise double flèche -->
      <div class="inline-flex">
        <div class="next-btn" role="group" aria-label="Précédent action">
          <span class="next-text">Précédent</span>
          <button type="button" class="arrow-only" aria-label="Précédent" onclick="showPreviousCard()">
            <img src="../../img/svg/fleche-gauche.svg" alt="Précédent" style="filter : invert(1) saturate(0.9)"
              class="btn-arrow-left" aria-hidden="true">
          </button>
        </div>
        <div class="next-btn" role="group" aria-label="Suivant action">
          <span class="next-text">Suivant</span>
          <button type="button" class="arrow-only" aria-label="Suivant" onclick="showNextCard()">
            <img src="../../img/svg/fleche-gauche.svg" alt="" style="filter : invert(1) saturate(0.9)"
              class="btn-arrow" aria-hidden="true">
          </button>
        </div>
      </div>
    </form>
  </div>

  <!-- Card4 -->

  <div class="card hidden" id="4">
    <div class="logo">
      <img src="../../img/svg/logo-text.svg" alt="Logo Alizon">
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

      <div class="step">étape 4 / 4</div>

      <!-- Bouton Précédent centré (même place que dans register.php), utilise double flèche -->
      <div class="inline-flex">
        <div class="next-btn" role="group" aria-label="Précédent action">
          <span class="next-text">Précédent</span>
          <button type="button" class="arrow-only" aria-label="Précédent" onclick="showPreviousCard()">
            <img src="../../img/svg/fleche-gauche.svg" alt="Précédent" style="filter : invert(1) saturate(0.9)"
              class="btn-arrow-left" aria-hidden="true">
          </button>
        </div>
        <div class="next-btn" role="group" aria-label="Suivant action">
          <span class="next-text">Terminer</span>
          <button onclick="finishRegistration()" id="finishBtn" class="arrow-only" aria-label="Terminer">
            <img src="../../img/svg/fleche-gauche.svg" alt="" style="filter : invert(1) saturate(0.9)"
              class="btn-arrow" aria-hidden="true">
          </button>
        </div>
      </div>
    </form>
  <script type="module" src="../../js/registerPass.js" ></script>
</body>

</html>

