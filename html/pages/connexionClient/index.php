<?php 
include '../../selectBDD.php';

$pdo->exec("SET search_path TO cobrec1");
session_start();
 ?>
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
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8');
    $mdp = $_POST['mdp'] ?? '';

    $hasError = false;
    $error_card = null;
    $error_message = '';
    
    // Récupérer l'entrée correspondant à l'email soumis et vérifier le mot de passe
    try {
      $stmt = $pdo->prepare("SELECT mdp FROM _compte WHERE email = :email LIMIT 1");
      $stmt->execute([':email' => $email]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$row) {
        $hasError = true;
        $error_card = 1;
        $error_message = 'Adresse mail ou mot de passe incorrecte.';
      } else {
        $stored = $row['mdp'];
        $passwordOk = false;
        if (function_exists('password_verify')) {
          $passwordOk = password_verify($mdp, $stored);
        }
        if (!$passwordOk && $stored === $mdp) {
          $passwordOk = true;
        }
        if (!$passwordOk) {
          $hasError = true;
          $error_card = 1;
          $error_message = 'Adresse mail ou mot de passe incorrecte.';
        } else {
          // Si authentification OK, stocker l'id du compte en session
          $_SESSION['id'] = (int)$row['id'];
        }
      }
    } catch (Exception $e) {
      $hasError = true;
      $error_card = 1;
      $error_message = 'Erreur lors de la vérification des identifiants.';
    }

    if (!$hasError) {


      echo "<div class=\"server-summary\" style=\"max-width:700px;margin:24px auto;padding:20px;background:#fff;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,0.12);\">";
      echo "<h2 style=\"margin-top:0;\">Récapitulatif (côté serveur)</h2>";
      echo "<dl style=\"display:grid;grid-template-columns:120px 1fr;gap:8px 16px;\">";
      echo "<dt>Email</dt><dd>{$email}</dd>";
      echo "<dt>mdp</dt><dd>{$mdp}</dd>";
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
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
  }
    
  .footer{
    margin-left: 40px;
    margin-bottom: 10px;
    width: 55%;
    display: flex;
    justify-content: space-between;
    flex-direction: row;
    font-size: 20px;
    > p{
      font-size: 20px;
      color: #7171A3;
    }
  }
      /* ensure the card is centered inside the flex body */
      .card {
        margin: 0 auto;
      }

  .debutant {
    font-size: 20px;
    margin-left: 40px;
    margin-bottom: 10px;
    margin-top: 10px;
    text-align: left;
    a {
      margin-left: 10px;
      color: #7171A3;
      outline: none;
      text-decoration: none;
    }


  }

</style>

<body>
  <form action="index.php" method="post" enctype="multipart/form-data" id="multiForm">
    <div class="card" id="1">
      <div class="logo">
        <img src="../../img/svg/logo-text.svg" alt="Logo Alizon">
      </div>

      <h1>Connexion</h1>

      <div>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="exemple@domaine.extension" required>
      </div>

      <div>
        <label for="mdp">Mot de passe</label>
  <input type="password" id="mdp" name="mdp" placeholder="***********" required>
      </div>
      <div class="forgot" onclick="showNextCard()">mot de passe oublié ?</div>

      <div class="error">
        <?php if (isset($hasError) && $hasError && $error_card == 1): ?>
          <strong>Erreur</strong> : <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
        <?php endif; ?>
      </div>


      <div class="connex-btn" role="group" aria-label="Suivant action">
          <button type="button" onclick="finishRegistration()" id="finishBtn" class="arrow-only" aria-label="Terminer">
            Terminer
          </button>
      </div>
      <div class= "debutant" > Débutant sur Alizon ? <a href= "../creationClient/index.php"><strong >Démarrer →</strong></a></div>
      <div class= "footer">
        <p>Aide</p><p>Confidentialité</p><p>Conditions</p>
      </div>
    </div>

      <div class="error">
        <?php if (isset($hasError) && $hasError && $error_card == 2): ?>
          <strong>Erreur</strong> : <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
        <?php endif; ?>
      </div>

  </form>

  <script>

    // Retourne un message de validation en français pour l'élément fourni
    function getFieldValidationMessage(el) {
      if (!el) return 'Veuillez remplir ce champ correctement.';
      try {
        if (el.validity) {
          if (el.validity.valueMissing) return 'Ce champ est requis.';
          if (el.validity.typeMismatch) {
            if (el.type === 'email') return 'Adresse mail ou mot de passe incorrecte.';
            if (el.type === 'url') return 'Adresse mail ou mot de passe incorrecte.';
            return 'Adresse mail ou mot de passe incorrecte.';
          }
        }
      } catch (e) {}
      return el.validationMessage || 'Veuillez remplir ce champ correctement.';
    }

    window.finishRegistration = function () {
      console.log('[register] finishRegistration called');
      var form = document.getElementById('multiForm');
      if (!form) return;
      if (!form.checkValidity()) {
        var invalid = form.querySelector(':invalid');
        if (invalid) {
          var card = invalid.closest('.card');
          var cards = Array.from(document.querySelectorAll('.card'));
          var idx = card ? cards.indexOf(card) : 0;
          if (typeof showCardByIndex === 'function') showCardByIndex(idx);
          var errDiv = card ? card.querySelector('.error') : null;
          var message = getFieldValidationMessage(invalid);
          try { invalid.setCustomValidity(message); } catch (e) {}
          if (errDiv) {
            errDiv.textContent = message;
            errDiv.classList.remove('hidden');
          } else {
            alert(message);
          }
          invalid.focus();
        }
        return;
      }
      try {
        window.__allow_submit = true;
        try { window.__submission_confirmed = false; } catch (e) {}
        console.log('[register] calling requestSubmit (or form.submit fallback)');
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit();
        } else {
          form.submit();
        }
        setTimeout(function () {
          try {
            if (!window.__submission_confirmed) {
              console.warn('[register] no submit event detected within timeout — using fallback form.submit()');
              window.__allow_submit = true;
              form.submit();
            }
          } catch (e) { console.error('[register] fallback submit failed', e); }
        }, 600);
      } catch (e) {
        window.__allow_submit = false;
        form.submit();
      }
    }
  </script>

  <script type="module" src="../../js/registerPass.js"></script>
</body>

</html>