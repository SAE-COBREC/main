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

<?php
  $interdit = "bleu";
  // When the form is submitted (Terminer), display the submitted PHP variables server-side
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = htmlspecialchars($_POST['nom'] ?? '', ENT_QUOTES, 'UTF-8');
    $prenom = htmlspecialchars($_POST['prenom'] ?? '', ENT_QUOTES, 'UTF-8');
    $pseudo = htmlspecialchars($_POST['pseudo'] ?? '', ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8');
    $telephone = htmlspecialchars($_POST['telephone'] ?? '', ENT_QUOTES, 'UTF-8');
    $naissance = htmlspecialchars($_POST['naissance'] ?? '', ENT_QUOTES, 'UTF-8');
    $rue = htmlspecialchars($_POST['rue'] ?? '', ENT_QUOTES, 'UTF-8');
    $codeP = htmlspecialchars($_POST['codeP'] ?? '', ENT_QUOTES, 'UTF-8');
    $commune = htmlspecialchars($_POST['commune'] ?? '', ENT_QUOTES, 'UTF-8');
    $mdp = $_POST['mdp'] ?? '';
    $Cmdp = $_POST['Cmdp'] ?? '';

      // prepare error state
      $hasError = false;
      $error_card = null;
      $error_message = '';

      // Server-side pseudo check
      if (strtolower($pseudo) === strtolower($interdit)) {
        $hasError = true;
        $error_card = 1; // show error on card 1
        $error_message = 'Ce pseudonyme n\'est pas autorisé.';
      }

      // Server-side password confirmation check
      if (!$hasError && $mdp !== $Cmdp) {
        $hasError = true;
        $error_card = 4; // show error on card 4
        $error_message = 'Les mots de passe ne correspondent pas. Veuillez vérifier la saisie.';
      }

    // If passed validation, continue with normal processing (placeholder)
    // For now we simply show a recap; further processing (save to CSV/DB) can go here.
    if (!$hasError) {
      $mdp = htmlspecialchars($mdp, ENT_QUOTES, 'UTF-8');
      $Cmdp = htmlspecialchars($Cmdp, ENT_QUOTES, 'UTF-8');

      echo "<div class=\"server-summary\" style=\"max-width:700px;margin:24px auto;padding:20px;background:#fff;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,0.12);\">";
      echo "<h2 style=\"margin-top:0;\">Récapitulatif (côté serveur)</h2>";
      echo "<dl style=\"display:grid;grid-template-columns:120px 1fr;gap:8px 16px;\">";
      echo "<dt>Nom</dt><dd>{$nom}</dd>";
      echo "<dt>Prénom</dt><dd>{$prenom}</dd>";
      echo "<dt>Pseudonyme</dt><dd>{$pseudo}</dd>";
      echo "<dt>Email</dt><dd>{$email}</dd>";
      echo "<dt>Téléphone</dt><dd>{$telephone}</dd>";
      echo "<dt>Naissance</dt><dd>{$naissance}</dd>";
      echo "<dt>Rue</dt><dd>{$rue}</dd>";
      echo "<dt>Code Postal</dt><dd>{$codeP}</dd>";
      echo "<dt>Commune</dt><dd>{$commune}</dd>";
      echo "<dt>mdp</dt><dd>{$mdp}</dd>";
      echo "<dt>Confirmation mdp</dt><dd>{$Cmdp}</dd>";
      echo "</dl>";
      echo "<div style=\"margin-top:16px;display:flex;gap:12px;justify-content:flex-end;\">";
      echo "<a href=\"index.php\" style=\"display:inline-block;padding:8px 12px;border-radius:8px;border:1px solid #030212;color:#030212;text-decoration:none;\">Retour</a>";
      echo "</div>";
      echo "</div>";

      exit;
    }
  }
?>

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
  <form action="index.php" method="post" enctype="multipart/form-data" id="multiForm">
  <div class="card" id="1">
    <div class="logo">
      <img src="../../img/svg/logo-text.svg" alt="Logo Alizon">
    </div>

    <h1>Créer un compte</h1>
    <p class="subtitle">Identifiants</p>

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
        <?php if (isset($hasError) && $hasError && $error_card == 1): ?>
          <strong>Erreur</strong> : <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
        <?php endif; ?>
      </div>

      <div class="step">étape 1 / 4</div>

      <div class="next-btn" role="group" aria-label="Suivant action">
        <span class="next-text">Suivant</span>
        <button type="button" class="arrow-only" aria-label="Suivant" onclick="showNextCard()">
          <img src="../../img/svg/fleche-gauche.svg" alt="" style="filter : invert(1) saturate(0.9)" class="btn-arrow"
            aria-hidden="true">
        </button>
      </div>
  </div>

  <!-- Card2 -->


  <div class="card hidden" id="2">
    <div class="logo">
      <img src="../../img/svg/logo-text.svg" alt="Logo Alizon">
    </div>

    <h1>Créer un compte</h1>
    <p class="subtitle">Coordonnées</p>

    
      <div>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="exemple@domaine.extension" required>
      </div>

      <div>
        <label for="telephone">Numéro de téléphone</label>
        <input type="tel" id="telephone" name="telephone" placeholder="ex: 0645893548" required>
      </div>

      <div>
        <label for="naissance">Date de naissance</label>
        <input type="date" id="naissance" name="naissance" placeholder="JJ/MM/AAAA" required>
      </div>


      <div class="error">
      </div>

      <div class="step">étape 2 / 4</div>

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
    
  </div>

  <!-- Card3 -->

  <div class="card hidden" id="3">
    <div class="logo">
      <img src="../../img/svg/logo-text.svg" alt="Logo Alizon">
    </div>

    <h1>Créer un compte</h1>
    <p class="subtitle">Coordonnées</p>

    
      <div>
        <label for="rue">Rue</label>
        <input type="text" id="rue" name="rue" placeholder="ex: 19 rue Hant koz" value="" required>
      </div>

      <div class="inline-flex">
        <div class="culumn-flex" id="div_codeP">
          <label for="codeP">Code Postal</label>
          <input type="number" id="codeP" name="codeP" placeholder="ex: 22300"  required>
        </div>

        <div class="culumn-flex">
          <label for="commune">Commune</label>
          <input type="text" id="commune" name="commune" placeholder="ex:lannion"  required>
        </div>
      </div>

      <div class="error">
      </div>

      <div class="step">étape 3 / 4</div>

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
    
  </div>

  <!-- Card4 -->

  <div class="card hidden" id="4">
    <div class="logo">
      <img src="../../img/svg/logo-text.svg" alt="Logo Alizon">
    </div>

    <h1>Créer un compte</h1>
    <p class="subtitle">Mot de passe</p>

    

      <div>
        <label for="mdp">Mot de passe</label>
        <input type="password" id="mdp" name="mdp" placeholder="***********" value="" required>
      </div>

      <div>
        <label for="Cmdp">Confirmer le mot de passe</label>
        <input type="password" id="Cmdp" name="Cmdp" placeholder="**********" value="" required>
      </div>

      <div class="error">
        <?php if (isset($hasError) && $hasError && $error_card == 4): ?>
          <strong>Erreur</strong> : <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
        <?php endif; ?>
      </div>

      <div class="step">étape 4 / 4</div>

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
              <button type="button" onclick="finishRegistration()" id="finishBtn" class="arrow-only" aria-label="Terminer">
                <img src="../../img/svg/fleche-gauche.svg" alt="" style="filter : invert(1) saturate(0.9)"
                  class="btn-arrow" aria-hidden="true">
              </button>
        </div>
      </div>
    
      </form>

  <script>
    function currentCard() {
      return document.querySelector('.card:not(.hidden)');
    }
    function showCardByIndex(idx) {
      const cards = Array.from(document.querySelectorAll('.card'));
      cards.forEach((c, i) => {
        c.classList.toggle('hidden', i !== idx);
      });
    }
    window.showNextCard = function() {
      const cards = Array.from(document.querySelectorAll('.card'));
      const visible = cards.findIndex(c => !c.classList.contains('hidden'));
      
      // Vérification du pseudo interdit sur la card 1
      if (visible === 0) {
        const pseudoInput = document.getElementById('pseudo');
        const interdit = '<?php echo $interdit; ?>';
        if (pseudoInput && pseudoInput.value.toLowerCase() === interdit.toLowerCase()) {
          const err = document.querySelector('.card#\\31  .error');
          if (err) {
            err.classList.remove('hidden');
            err.innerHTML = '<strong>Erreur</strong> : Ce pseudonyme n\'est pas autorisé.';
          } else {
            alert('Erreur : Ce pseudonyme n\'est pas autorisé.');
          }
          return;
        }
      }
      
      if (visible < cards.length - 1) showCardByIndex(visible + 1);
    }
    window.showPreviousCard = function() {
      const cards = Array.from(document.querySelectorAll('.card'));
      const visible = cards.findIndex(c => !c.classList.contains('hidden'));
      if (visible > 0) showCardByIndex(visible - 1);
    }
    window.finishRegistration = function() {
      // Client-side password confirmation check
      const mdpEl = document.getElementById('mdp');
      const cmdpEl = document.getElementById('Cmdp');
      const mdp = mdpEl ? (mdpEl.value || '') : '';
      const cmdp = cmdpEl ? (cmdpEl.value || '') : '';
      if (mdp !== cmdp) {
        // Show card 4 and display error
        showCardByIndex(3);
        const err = document.querySelector('.card#\\34  .error');
        if (err) {
          err.classList.remove('hidden');
          err.innerHTML = '<strong>Erreur</strong> : Les mots de passe ne correspondent pas.';
        } else {
          alert('Erreur : Les mots de passe ne correspondent pas.');
        }
        return;
      }

      // Submit the form to the server
      document.getElementById('multiForm').submit();
    }
    
    // Si une erreur PHP est détectée, afficher la carte concernée au chargement
    <?php if (isset($hasError) && $hasError && $error_card): ?>
      window.addEventListener('DOMContentLoaded', function() {
        showCardByIndex(<?php echo $error_card - 1; ?>); // -1 car les index commencent à 0
      });
    <?php endif; ?>
  </script>

  <script type="module" src="../../js/registerPass.js" ></script>
</body>

</html>