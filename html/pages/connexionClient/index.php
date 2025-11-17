<?php 
//connexion a la bdd 
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
  <link rel="icon" type="image/png" href="../../img/favicon.svg">
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

        //récuperation des données client
        $clientStmt = $pdo->prepare("SELECT id_client FROM _client WHERE id_compte = :id");
        $clientStmt->execute([':id' => (int)$row['id_compte']]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
        if ($client) {
          $clientId = (int)$client['id_client'];
        }
        
        //verification que le compte est un compte client
        if (!$clientId) {
          $hasError = true;
          $error_card = 1;
          $error_message = 'Adresse mail ou mot de passe incorrecte.';
        } else {

          //ajout des identifiant a la session
          $_SESSION['id'] = $clientId;
          $_SESSION['compte_id'] = $compteId;
          
          //redirige sur la page d'acceuil
          header('Location: ../../index.php');
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
      <div class= "debutant" > Débutant sur Alizon ? <a href= "../creationClient/index.php"><strong >Démarrer →</strong></a></div>
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
