<?php 
session_start();
include '../../selectBDD.php';
$pdo->exec("SET search_path TO cobrec1");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var form = document.getElementById('multiForm');
      if (!form) return;
      var elems = form.querySelectorAll('input, textarea, select');
      elems.forEach(function (el) {
        el.addEventListener('keydown', function (ev) {
          if (ev.key !== 'Enter') return;
          if (ev.shiftKey || ev.ctrlKey || ev.altKey || ev.metaKey) return;
          var tag = (el.tagName || '').toLowerCase();
          if (tag === 'textarea') return;
          ev.preventDefault();
          try { if (typeof finishRegistration === 'function') finishRegistration(); } catch (e) { form.submit(); }
        });
      });
    });
  </script>
  <title>MDP oublié - Alizon</title>
  <link rel="icon" type="image/png" href="../../img/favicon.svg">
  <link rel="stylesheet" href="../../styles/Connexion_Creation/styleCoCrea.css">
</head>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $login = trim($_POST['email'] ?? '');
  $mdp = $_POST['mdp'] ?? '';
  $cmdp = $_POST['Cmdp'] ?? '';

  $hasError = false;
  $error_card = null;
  $error_message = '';
  
  try {
    // Recherche du compte par email
    $stmt = $pdo->prepare("SELECT id_compte, mdp FROM _compte WHERE email = :login");
    $stmt->execute([':login' => $login]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Si pas trouvé par email, essayer par pseudo
    if (!$row) {
      $stmt = $pdo->prepare("SELECT c.id_compte, c.mdp FROM _compte c JOIN _client cl ON c.id_compte = cl.id_compte WHERE cl.c_pseudo = :login");
      $stmt->execute([':login' => $login]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$row) {
      $hasError = true;
      $error_card = 1;
      $error_message = 'Adresse mail ou pseudo introuvable.';
    } else {
      // Vérification que la confirmation correspond au nouveau mot de passe
      if ($cmdp !== $mdp) {
        $hasError = true;
        $error_card = 1;
        $error_message = 'Les mots de passe ne correspondent pas.';
      }
      if ($hasError) {
        // stop further processing
      } else {
      if ($mdp === $row['mdp']) {
        $hasError = true;
        $error_card = 1;
        $error_message = 'Veuillez saisir un nouveau mot de passe différent de l\'ancien.';
      } else {
        $sql = 'UPDATE cobrec1._compte SET mdp = :mdp WHERE id_compte = :id_compte';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
          ':mdp' => $mdp,
          ':id_compte' => $row['id_compte']
        ]);
        
        // CORRECTION: Récupérer l'ID client si nécessaire  
        $stmtClient = $pdo->prepare("SELECT id_client FROM _client WHERE id_compte = :id_compte");
        $stmtClient->execute([':id_compte' => $row['id_compte']]);
        $clientRow = $stmtClient->fetch(PDO::FETCH_ASSOC);
        
        if ($clientRow) {
          $_SESSION['idClient'] = $clientRow['id_client'];
        }
        $_SESSION['idCompte'] = (int)$row['id_compte'];
        
        // Redirection
        $url = '../connexionClient/index.php';
        echo '<!doctype html><html><head><meta http-equiv="refresh" content="0;url='.$url.'">';
        exit;
      }
      }
    }
  } catch (Exception $e) {
    $hasError = true;
    $error_card = 1;
    $error_message = 'Erreur lors de la vérification des identifiants.';
    // DÉBOGAGE - À RETIRER EN PRODUCTION
    $error_message .= ' Détails: ' . $e->getMessage();
    error_log('Erreur reset password: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
  }
}
?>

<style>
  body {
    background: linear-gradient(to bottom right, #030212, #7171A3);
  }
  .debutant a {
    color: #7171A3;
  }
  .footer > p {
    color: #7171A3;
  }
  .connex-btn > button:hover {
    background: #7171A3;
    color: #030212;
  }
</style>

<body>
  <form action="index.php" method="post" enctype="multipart/form-data" id="multiForm">
    <div class="card" id="1">
      <div class="logo">
        <img  src="../../img/svg/logo-text.svg" alt="Logo Alizon" onclick="window.location.href='../../index.php'">
      </div>

      <h1>Récupération mdp</h1>

      <div>
        <label for="email">Email de récupération</label>
        <input type="text" id="email" name="email" placeholder="exemple@domaine.extension" required>
      </div>

      <div class="input-with-icon">
        <label for="mdp">Nouveau mot de passe</label>
        <div>
        <input class="with-icon" type="password" id="mdp" name="mdp" placeholder="***********" required pattern="^(?=.*\\d)(?=.*[A-Z])(?=.*[a-z])(?=.*[^A-Za-z0-9]).{9,16}$" title="Le mot de passe doit contenir entre 9 et 16 caractères, au moins une majuscule, une minuscule, un chiffre et un caractère spécial.">
        <button type="button" class="toggle-password" data-target="mdp" >
          <img src="../../img/svg/oeil.svg" alt="Afficher/Masquer" width="24" height="24">
        </button>
        </div>
      </div>

      <div class="input-with-icon">
        <label for="Cmdp">Confirmer le mot de passe</label>
        <div>
        <input class="with-icon" type="password" id="Cmdp" name="Cmdp" placeholder="***********" required pattern="^(?=.*\\d)(?=.*[A-Z])(?=.*[a-z])(?=.*[^A-Za-z0-9]).{9,16}$" title="Veuillez saisir les mêmes mots de passe">
        <button type="button" class="toggle-password" data-target="mdp" >
          <img src="../../img/svg/oeil.svg" alt="Afficher/Masquer" width="24" height="24">
        </button>
          </div>
      </div>

      

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
      
      <div class="debutant">Retour a la page de <a href="../connexionClient/index.php"><strong>Connexion →</strong></a></div>
      
      <div class="footer">
        <p>Aide</p><p>Confidentialité</p><p>Conditions</p>
      </div>
    </div>
  </form>

  <script>
    // Chemins des images pour l'oeil
    const PATH_OEIL_OUVERT = '../../img/svg/oeil.svg';
    const PATH_OEIL_FERME  = '../../img/svg/oeil-barre.svg';

    function initPasswordToggles() {
      const buttons = document.querySelectorAll('.toggle-password');
      
      buttons.forEach(btn => {
        btn.addEventListener('click', function (ev) {
          ev.preventDefault(); // Empêche le submit du formulaire
          
          const targetId = btn.getAttribute('data-target');
          const input = document.getElementById(targetId);
          const imgIcon = btn.querySelector('img');

          if (!input || !imgIcon) return;

          const isPassword = input.type === 'password';

          // Bascule input
          input.type = isPassword ? 'text' : 'password';

          // inversion d'images
          imgIcon.src = isPassword ? PATH_OEIL_FERME : PATH_OEIL_OUVERT;
        });
      });
    }


//validation des champs
    function getFieldValidationMessage(el) {
      try {
        if (el && el.validity) {
          // Vérifications spécifiques pour le mot de passe
          if (el.id === 'mdp' || el.id === 'Cmdp') {
            var val = (el.value || '').trim();
            if (val.length === 0) return 'Ce champ est requis.';
            if (val.length < 8) return 'Le mot de passe doit contenir au moins 8 caractères.';
            if (val.length > 16) return 'Le mot de passe doit contenir au maximum 16 caractères.';
            if (!/[0-9]/.test(val)) return 'Le mot de passe doit contenir au moins un chiffre.';
            if (!/[A-Z]/.test(val)) return 'Le mot de passe doit contenir au moins une lettre majuscule.';
            if (!/[a-z]/.test(val)) return 'Le mot de passe doit contenir au moins une lettre minuscule.';
            if (!/[^A-Za-z0-9]/.test(val)) return 'Le mot de passe doit contenir au moins un caractère spécial.';
            if (el.validity.patternMismatch) return 'Le mot de passe ne respecte pas le format requis.';
          }
        }
      } catch (e) { /* ignore */ }
      return el && el.validationMessage ? el.validationMessage : 'Veuillez remplir ce champ correctement.';
    }


//validation du formulaire
    window.finishRegistration = function () {
      console.log('[register] finishRegistration called');
      var form = document.getElementById('multiForm');
      if (!form) return;

      if (!form.checkValidity()) {
        var invalid = form.querySelector(':invalid');
        if (invalid) {
          var card = invalid.closest('.card');
          var errDiv = card ? card.querySelector('.error') : null;
          var message = getFieldValidationMessage(invalid);
          
          try { invalid.setCustomValidity(message); } catch (e) {}
          
          if (errDiv) {
            errDiv.textContent = message;
            errDiv.classList.remove('hidden');
          } else {
            alert(message);
          }
          try { invalid.focus(); } catch (e) { /* ignore */ }
        }
        return;
      }

      //Vérification correspondance MDP (Script côté client)
      try {
        var mdpEl = document.getElementById('mdp');
        var cmdpEl = document.getElementById('Cmdp');
        if (mdpEl && cmdpEl) {
          if (mdpEl.value !== cmdpEl.value) {
            var cardEl = document.querySelector('.card');
            var err = cardEl ? cardEl.querySelector('.error') : null;
            var msg = 'Les mots de passe ne correspondent pas.';
            
            if (err) {
              err.textContent = msg;
              err.classList.remove('hidden');
            } else {
              alert(msg);
            }
            try { mdpEl.focus(); } catch (e) { }
            return;
          }
        }
      } catch (e) {
        console.warn('Erreur vérif correspondance', e);
      }

      // C. Soumission finale
      form.submit();
    }
    document.addEventListener('DOMContentLoaded', function () {
      // Lancer la gestion des yeux
      initPasswordToggles();

      // Gestion de la touche Entrée
      var form = document.getElementById('multiForm');
      if (!form) return;
      var elems = form.querySelectorAll('input, textarea, select');
      
      elems.forEach(function (el) {
        el.addEventListener('keydown', function (ev) {
          if (ev.key !== 'Enter') return;
          if (ev.shiftKey || ev.ctrlKey || ev.altKey || ev.metaKey) return;
          var tag = (el.tagName || '').toLowerCase();
          if (tag === 'textarea') return;
          ev.preventDefault();
          finishRegistration();
        });
      });
    });
  </script>
  <script type="module" src="../../js/registerPass.js"></script>
</body>
</html>