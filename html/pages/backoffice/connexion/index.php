<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Créer un compte - Alizon</title>
  <link
    href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;700&family=Quicksand:wght@300;400;500;700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="../../../styles/Register/styleRegister.css">
</head>

<?php
  $interdit = "bleu";
  $interditmail = "a@a.a";
  // When the form is submitted (Terminer), display the submitted PHP variables server-side
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8');
    $mdp = $_POST['mdp'] ?? '';

      // prepare error state
      $hasError = false;
      $error_card = null;
      $error_message = '';

      if (strtolower($email) === strtolower($interditmail)) {
        $hasError = true;
        // on affiche l'erreur sur la card présente (id="4")
        $error_card = 4;
        $error_message = 'Ce mail n\'est pas autorisé.';
      }
    if (!$hasError) {
      $mdp = htmlspecialchars($mdp, ENT_QUOTES, 'UTF-8');
      $Cmdp = htmlspecialchars($Cmdp, ENT_QUOTES, 'UTF-8');

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
    background: linear-gradient(to bottom right, #CD7F32, #D4183D);
  }
</style>

<body>
  <form action="index.php" method="post" enctype="multipart/form-data" id="multiForm">

  <div class="card hidden" id="1">
    <div class="logo">
  <img src="../../../img/svg/logo-text.svg" alt="Logo Alizon">
    </div>

    <h1>Créer un compte</h1>
    <p class="subtitle">Mot de passe</p>

    

      <div>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="exemple@domaine.extension" required>
      </div>

      <div>
        <label for="Cmdp">Mot de passe</label>
        <input type="password" id="Cmdp" name="Cmdp" placeholder="**********" value="" required>
      </div>

      <div class="error">
        <?php if (isset($hasError) && $hasError && $error_card == 4): ?>
          <strong>Erreur</strong> : <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
        <?php endif; ?>
      </div>

      <div class="next-btn" role="group" aria-label="Suivant action">
        <span class="next-text">Terminer</span>
             <button type="button" onclick="finishRegistration()" id="finishBtn" class="arrow-only" aria-label="Terminer">
              <img src="../../../img/svg/fleche-gauche.svg" alt="" style="filter : invert(1) saturate(0.9)"
                 class="btn-arrow" aria-hidden="true">
            </button>
      </div>
    
      </form>

  <script>
    // Minimal script: delegate behavior to the module `js/registerPass.js` which
    // exposes the required functions as globals. Only show the server-side error
    // card if the server flagged one.
    <?php if (isset($hasError) && $hasError && $error_card): ?>
    window.addEventListener('DOMContentLoaded', function() {
      try {
        if (typeof showCard === 'function') {
          // server uses 1-based card indexes in PHP; module expects an ID
          showCard('<?php echo $error_card; ?>');
        }
      } catch (e) { /* non-blocking */ }
    });
    <?php endif; ?>
  </script>

  <script type="module" src="../../../js/registerPass.js" ></script>

</body>

</html>