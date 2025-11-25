<?php 
session_start();
//connexion a la bdd 
include '../../selectBDD.php';
$pdo->exec("SET search_path TO cobrec1");
 ?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script>
    // Ajoute un comportement Entrée : soumet le formulaire quand Enter est pressé dans un champ (sauf textarea)
    document.addEventListener('DOMContentLoaded', function () {
      var form = document.getElementById('multiForm');
      if (!form) return;
      var elems = form.querySelectorAll('input, textarea, select');
      elems.forEach(function (el) {
        el.addEventListener('keydown', function (ev) {
          if (ev.key !== 'Enter') return;
          if (ev.shiftKey || ev.ctrlKey || ev.altKey || ev.metaKey) return;
          var tag = (el.tagName || '').toLowerCase();
          if (tag === 'textarea') return; // laisser Entrée dans textarea
          // empêcher le comportement par défaut (soumission réelle du formulaire par le navigateur)
          ev.preventDefault();
          // appeler la logique existante qui gère la validation & la soumission
          try { if (typeof finishRegistration === 'function') finishRegistration(); } catch (e) { form.submit(); }
        });
      });
    });
  </script>
  <title>Connexion - Alizon</title>
  <link rel="icon" type="image/png" href="../../img/favicon.svg">
  <link rel="stylesheet" href="../../styles/Connexion_Creation/styleCoCrea.css">
</head>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $login = trim($_POST['email'] ?? '');
  $mdp = $_POST['mdp'] ?? '';

  $hasError = false;
  $error_card = null;
  $error_message = '';
  
  try {

    //récuperation des données de compte
    $stmt = $pdo->prepare("SELECT id_compte, mdp FROM _compte WHERE email = :login");
    $stmt->execute([':login' => $login]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    //verification que l'aresse mail existe dans la bdd
    if (!$row) {
      $stmt = $pdo->prepare("SELECT c.id_compte, c.mdp FROM _compte c JOIN _client cl ON c.id_compte = cl.id_compte WHERE cl.c_pseudo = :login");
      $stmt->execute([':login' => $login]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    //verification que le pseudo existe dans la bdd
    if (!$row) {
      $hasError = true;
      $error_card = 1;
      $error_message = 'Adresse mail, pseudo ou mot de passe .';
    } else {


      //verification que le mdp corespondant a ce mail existe
      if (!($row['mdp'] === $mdp)) {
        $hasError = true;
        $error_card = 1;
        $error_message = 'Adresse mail, pseudo ou mot de passe incorrecte.';
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
          $error_message = ' mail, pseudo ou mot de passe incorrecte.';
        } else {
          //ajout des identifiant a la session

          $_SESSION['idClient'] = $clientId;
          $_SESSION['idCompte'] = (int)$row['id_compte'];
          // Redirection sans header() (serveur peut bloquer header)
          $url = '../../index.php';
          echo '<!doctype html><html><head><meta http-equiv="refresh" content="0;url='.$url.'">';
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
  }
  .debutant {
    a {
      color: #7171A3;
    }
  }
  .footer{
    > p{
      color: #7171A3;
    }
  }
  .connex-btn {
    > button {
      &:hover {
        background: #7171A3;
        color: black;
      }
    }
  }
</style>

<body>
  <form action="index.php" method="post" enctype="multipart/form-data" id="multiForm">
    <div class="card" id="1">
      <div class="logo">
        <img  src="../../img/svg/logo-text.svg" alt="Logo Alizon" onclick="window.location.href='../../index.php'">
      </div>

      <h1>Connexion</h1>

      <div>
        <label for="email">Email/Pseudonyme</label>
        <input type="text" id="email" name="email" placeholder="exemple@domaine.extension" required>
      </div>

      <div class="input-with-icon">
        <label for="mdp">Mot de passe</label>
        <div>
        <input class="with-icon" type="password" id="mdp" name="mdp" placeholder="***********" required pattern="^(?=.*\\d)(?=.*[A-Z])(?=.*[a-z])(?=.*[^A-Za-z0-9]).{9,16}$" title="Le mot de passe doit contenir entre 9 et 16 caractères, au moins une majuscule, une minuscule, un chiffre et un caractère spécial.">
        <button type="button" class="toggle-password" data-target="mdp" >
          <img src="../../img/svg/oeil.svg" alt="Afficher/Masquer" width="24" height="24">
        </button>
        </div>
      </div>
      <!-- affichage des erreurs de saisi -->
      <div class="error">
        <?php if (isset($hasError) && $hasError && $error_card == 1): ?>
          <strong>Erreur</strong> : <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
        <?php endif; ?>
      </div>

      <div class="forgot" onclick="window.location.href='../MDPoublieClient/index.php'">Mot de passe oublié ?</div>

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
    // 1. DÉFINIR LES CONSTANTES (Elles manquaient dans votre dernier code)
    const PATH_OEIL_OUVERT = '../../img/svg/oeil.svg';
    const PATH_OEIL_FERME  = '../../img/svg/oeil-barre.svg';

    function initPasswordToggles() {
      const buttons = document.querySelectorAll('.toggle-password');
      
      buttons.forEach(btn => {
        btn.addEventListener('click', function (ev) {
          ev.preventDefault(); 
          
          const targetId = btn.getAttribute('data-target');
          const input = document.getElementById(targetId);
          const imgIcon = btn.querySelector('img');

          // Vérification de sécurité
          if (!input || !imgIcon) {
              console.error("Input ou Image introuvable pour le toggle mdp");
              return;
          }

          const isPassword = input.type === 'password';

          // Bascule input
          input.type = isPassword ? 'text' : 'password';

          // Bascule image
          imgIcon.src = isPassword ? PATH_OEIL_FERME : PATH_OEIL_OUVERT;
        });
      });
    }

    // 2. INITIALISER AU CHARGEMENT
    document.addEventListener('DOMContentLoaded', function() {
        initPasswordToggles();
    });

    // ... (Le reste de votre code finishRegistration reste inchangé ci-dessous) ...
    window.finishRegistration = function () {
      // ... Votre code existant pour finishRegistration ...
      console.log('[register] finishRegistration called');
      var form = document.getElementById('multiForm');
      if (!form) return; 
      if (!form.checkValidity()) {
        var invalid = form.querySelector(':invalid');
        if (invalid) {
          var card = invalid.closest('.card');
          var errDiv = card ? card.querySelector('.error') : null;
          // Note: getFieldValidationMessage doit être défini quelque part ou retiré si inutile
          var message = invalid.validationMessage; 
          
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
      form.submit();
    }
  </script>

  <script type="module" src="../../js/registerPass.js"></script>
</body>

</html>
