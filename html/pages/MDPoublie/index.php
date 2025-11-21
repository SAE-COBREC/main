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
    
    // CORRECTION: Si le compte n'existe PAS
    if (!$row) {
      $hasError = true;
      $error_card = 1;
      $error_message = 'Adresse mail ou pseudo introuvable.';
    } else {
      // OPTION A: Si vos mots de passe SONT hachés (recommandé)
      // Décommentez ces lignes et commentez l'OPTION B
      /*
      if (password_verify($mdp, $row['mdp'])) {
        $hasError = true;
        $error_card = 1;
        $error_message = 'Veuillez saisir un nouveau mot de passe différent de l\'ancien.';
      } else {
        $mdp_hache = password_hash($mdp, PASSWORD_DEFAULT);
        $sql = 'UPDATE cobrec1._compte SET mdp = :mdp WHERE email = :email';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
          ':mdp' => $mdp_hache,
          ':email' => $login
        ]);
      */
      
      // OPTION B: Si vos mots de passe NE SONT PAS hachés (non sécurisé)
      // Commentez ces lignes si vous utilisez l'OPTION A
      if ($mdp === $row['mdp']) {
        $hasError = true;
        $error_card = 1;
        $error_message = 'Veuillez saisir un nouveau mot de passe différent de l\'ancien.';
      } else {
        // ATTENTION: Stockage en clair = NON SÉCURISÉ !
        // Utilisez password_hash() dès que possible
        $sql = 'UPDATE _compte SET mdp = :mdp WHERE email = :email';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
          ':mdp' => $mdp,
          ':email' => $login
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
        <img src="../../img/logo-text.svg" alt="Logo Alizon">
      </div>

      <h1>Récupération mdp</h1>

      <div>
        <label for="email">Email ou pseudo</label>
        <input type="text" id="email" name="email" placeholder="exemple@domaine.extension" required>
      </div>

      <div>
        <label for="mdp">Nouveau mot de passe</label>
        <input type="password" id="mdp" name="mdp" placeholder="***********" required>
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
      
      <div class="debutant">Débutant sur Alizon ? <a href="../creationClient/index.php"><strong>Démarrer →</strong></a></div>
      
      <div class="footer">
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
            if (window.showError) {
              showError('Champ invalide', message);
            } else {
              alert(message);
            }
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