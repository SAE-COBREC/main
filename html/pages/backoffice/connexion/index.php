<?php
session_start();
//connexion a la bdd 
include '../../../selectBDD.php';
$pdo->exec("SET search_path TO cobrec1");
$_SESSION[creeArticle]=[];
 ?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connexion - Alizon</title>
  <link rel="icon" type="image/png" href="../../img/favicon.svg">
  <link
    href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;700&family=Quicksand:wght@300;400;500;700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="../../../styles/Register/styleRegister.css">
</head>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $email = htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8');
  $mdp = $_POST['mdp'] ?? '';

  $hasError = false;
  $error_card = null;
  $error_message = '';

  try {
    //récuperation des données de compte
    $stmt = $pdo->prepare("SELECT id_compte, mdp FROM _compte WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    //verification que l'aresse mail existe dans la bdd
    if (!$row) {
      $hasError = true;
      $error_card = 1;
      $error_message = 'Adresse mail ou mot de passe incorrecte.';
    } else {

      //verification que le mdp corespondant a ce mail existe
      if (!($row['mdp'] === $mdp)) {
        $hasError = true;
        $error_card = 1;
        $error_message = 'Adresse mail ou mot de passe incorrecte.';
      } else {

        //récuperation des données vendeur
        $vendeurStmt = $pdo->prepare("SELECT id_vendeur FROM _vendeur WHERE id_compte = :id");
        $vendeurStmt->execute([':id' => (int)$row['id_compte']]);
        $vendeur = $vendeurStmt->fetch(PDO::FETCH_ASSOC);
        if ($vendeur) {
          $vendeurId = (int)$vendeur['id_vendeur'];
        }

        //verification que le compte est un compte vendeur
        if (!$vendeurId) {
          $hasError = true;
          $error_card = 1;
          $error_message = 'Adresse mail ou mot de passe incorrecte.';
        } else {

          //ajout des identifiant a la session
          $_SESSION['vendeur_id'] = $vendeurId;
          
          //redirige sur la page d'acceuil
          header('Location: ../index.php');
          exit;
        }
      }
    }
    //message en cas de probleme de verif dans le code
  } catch (Exception $e) {
    $hasError = true;
    $error_card = 1;
    $error_message = 'Erreur lors de la vérification des identifiants.';
  }
}
?>
<style>
  body {
    background: linear-gradient(to bottom right, #CD7F32, #D4183D);
  }
  .debutant {
    a {
      color: #CD7F32;
    }
  }
  .footer{
    > p{
      color: #CD7F32;
    }
  }
  .connex-btn {
    > button {
      &:hover {
        background: #CD7F32;
        color: black;
      }
    }
  }
</style>

<body>
  <form action="index.php" method="post" enctype="multipart/form-data" id="multiForm">
    <div class="card" id="1">
      <div class="logo">
        <img src="../../../img/svg/logo-text.svg" alt="Logo Alizon">
      </div>

      <h1>Connexion</h1>

      <div>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="exemple@domaine.extension" required>
      </div>

      <div>
        <label for="mdp">Mot de passe</label>
  <input type="password" id="mdp" name="mdp" placeholder="***********" required >
      </div>
      <div class="forgot" onclick="showNextCard()">mot de passe oublié ?</div>

      <!-- affichage des erreurs de saisi -->
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
      <div class= "debutant" > Débutant sur Alizon ? <a href= "../register/index.php"><strong >Démarrer →</strong></a></div>
      <div class= "footer">
        <p>Aide</p><p>Confidentialité</p><p>Conditions</p>
      </div>
    </div>

  </form>

  <script>
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