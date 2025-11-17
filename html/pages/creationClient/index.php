<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Créer un compte - Alizon</title>
  <link rel="icon" type="image/png" href="../../../img/favicon.svg">
  <link
    href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;700&family=Quicksand:wght@300;400;500;700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="../../styles/Register/styleRegister.css">
</head>

<?php
// database connection
include __DIR__ . '/../../selectBDD.php';
try { $pdo->exec("SET search_path TO cobrec1"); } catch (Throwable $t) { /* ignore */ }

$interdit = "bleu";

$interditmail = "a@a.a";
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
  if (strtolower($email) === strtolower($interditmail)) {
    $hasError = true;
    $error_card = 2; // show error on card 1
    $error_message = 'Ce mail n\'est pas autorisé.';
  }

  // Server-side email format check (matches DB constraint pattern)
  if (!$hasError && !preg_match('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/', $email)) {
    $hasError = true;
    $error_card = 2; // show error on card 2
    $error_message = 'Veuillez saisir une adresse e-mail valide.';
  }

  if (!$hasError && !preg_match('/(0|\\+33|0033)[1-9][0-9]{8}/', $telephone)) {
    $hasError = true;
    $error_card = 2; // show error on card 2
    $error_message = 'Veuillez saisir un numéro de téléphone valide .';
  }
  // code postal: expect exactly 5 digits server-side (client already enforces this)
  if (!$hasError && !preg_match('/^[0-9]{5}$/', $codeP)) {
    $hasError = true;
    $error_card = 3; // show error on card 3
    $error_message = 'Le code postal doit contenir exactement 5 chiffres.';
  }
  // Server-side password confirmation check: ensure grouping is correct and reject empty/weak passwords
  if (!$hasError && (empty($mdp) || $mdp !== $Cmdp || strlen($mdp) < 8)) {
    $hasError = true;
    $error_card = 4; // show error on card 4
    $error_message = 'Les mots de passe doivent correspondre et contenir au moins 8 caractères.';
  }

  if (!$hasError) {
    try {//création de l'objet produit dans la base
      $sql = '
      INSERT INTO cobrec1._compte(id_compte,email,num_telephone,mdp)
      VALUES (
      ' . "'" . $id_client ."'" .', 
      ' . $email .', 
      ' . "'" . $num_telephone ."'" .', 
      ' . "'" . $mdp ."'" .', 
      CURRENT_TIMESTAMP
      );
      ';
      $stmt = $pdo->prepare($sql);
      $stmt->execute();
  } catch (Exception $e) {
    $hasError = true;
    $error_card = 4; 
    $error_message = 'info invalide a inserer .';
  }

      echo "<div class=\"server-summary\" style=\"max-width:700px;margin:24px auto;padding:20px;background:#fff;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,0.12);\">";
      echo "<h2 style=\"margin-top:0;\">Compte créé</h2>";
      echo "<dl style=\"display:grid;grid-template-columns:120px 1fr;gap:8px 16px;\">";
      echo "<dt>ID compte</dt><dd>" . htmlspecialchars((string)$id_compte, ENT_QUOTES, 'UTF-8') . "</dd>";
      echo "<dt>ID client</dt><dd>" . htmlspecialchars((string)$id_client, ENT_QUOTES, 'UTF-8') . "</dd>";
      echo "<dt>Email</dt><dd>" . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "</dd>";
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

  .card[id="3"] {
    label {
      margin-left: 20px;

      &[for="commune"] {
        padding-left: 15px;
        margin-left: 0;
      }

      @media #{$mobile} {
        margin-left: 0;
      }
    }
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
        <input type="text" id="telephone" name="telephone" inputmode="numeric" pattern="(0|\\+33|0033)[1-9][0-9]{8}"
          maxlength="10" placeholder="ex: 0615482649" required title="Le numéro de télephone doit contenir 10 chiffres">
      </div>

      <div>
        <label for="naissance">Date de naissance</label>
        <input type="date" id="naissance" name="naissance" placeholder="JJ/MM/AAAA" required>
      </div>


      <div class="error">
        <?php if (isset($hasError) && $hasError && $error_card == 2): ?>
          <strong>Erreur</strong> : <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
        <?php endif; ?>
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
            <img src="../../img/svg/fleche-gauche.svg" alt="" style="filter : invert(1) saturate(0.9)" class="btn-arrow"
              aria-hidden="true">
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

      <div class="inline-flex address-row">
        <div class="culumn-flex" id="div_codeP">
          <label for="codeP">Code Postal</label>
          <input type="text" id="codeP" name="codeP" inputmode="numeric" pattern="^[0-9]{5}$" maxlength="5"
            placeholder="ex: 22300" required title="Le code postal doit contenir 5 chiffres">
        </div>

        <div class="culumn-flex">
          <label for="commune">Commune</label>
          <input type="text" id="commune" name="commune" placeholder="ex:lannion" required>
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
            <img src="../../img/svg/fleche-gauche.svg" alt="" style="filter : invert(1) saturate(0.9)" class="btn-arrow"
              aria-hidden="true">
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
            <img src="../../img/svg/fleche-gauche.svg" alt="" style="filter : invert(1) saturate(0.9)" class="btn-arrow"
              aria-hidden="true">
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
      // adjust body alignment depending on card height vs viewport
      try { if (typeof updateBodyAlignment === 'function') updateBodyAlignment(); } catch (e) { /* ignore */ }
    }

    // Keep the card vertically centered when it fits the viewport, otherwise align top.
    function updateBodyAlignment() {
      try {
        var card = document.querySelector('.card:not(.hidden)');
        if (!card) return;
        var rect = card.getBoundingClientRect();
        var needed = rect.height + 48; // small buffer for spacing
        if (needed <= window.innerHeight) {
          document.body.classList.add('centered');
        } else {
          document.body.classList.remove('centered');
        }
      } catch (e) { /* non-blocking */ }
    }
    // Retourne un message de validation en français pour l'élément fourni
    function getFieldValidationMessage(el) {
      if (!el) return 'Veuillez remplir ce champ correctement.';
      try {
        if (el.validity) {
          if (el.validity.valueMissing) return 'Ce champ est requis.';
          if (el.validity.typeMismatch) {
            if (el.type === 'email') return 'Veuillez saisir une adresse e-mail valide.';
            if (el.type === 'url') return 'Veuillez saisir une URL valide.';
            return 'Le format de la valeur est invalide.';
          }
          if (el.validity.patternMismatch) return el.getAttribute('title') || 'Le format saisi est incorrect.';
          if (el.validity.tooLong) return 'La valeur est trop longue.';
          if (el.validity.tooShort) return 'La valeur est trop courte.';
          if (el.validity.rangeUnderflow) return 'La valeur est trop faible.';
          if (el.validity.rangeOverflow) return 'La valeur est trop élevée.';
          if (el.validity.stepMismatch) return "La valeur n'est pas un pas valide.";
          if (el.validity.badInput) return 'Saisie invalide.';
        }
      } catch (e) { /* ignore */ }
      return el.validationMessage || 'Veuillez remplir ce champ correctement.';
    }
    window.showNextCard = function () {
      const cards = Array.from(document.querySelectorAll('.card'));
      const visible = cards.findIndex(c => !c.classList.contains('hidden'));

      const activeCard = cards[visible];
      const errorEl = activeCard ? activeCard.querySelector('.error') : null;

      // Prefer HTML5 validation for inputs inside the active card: find the first invalid field
      try {
        const invalid = activeCard ? activeCard.querySelector(':invalid') : null;
        if (invalid) {
          const message = getFieldValidationMessage(invalid);
          try { invalid.setCustomValidity(message); } catch (e) { /* ignore */ }
          if (errorEl) {
            errorEl.innerHTML = '<strong>Erreur</strong> : ' + message;
            errorEl.classList.remove('hidden');
          } else {
            if (typeof invalid.reportValidity === 'function') {
              invalid.reportValidity();
            } else {
              alert(message);
            }
          }
          try { invalid.focus(); } catch (e) { /* ignore */ }
          return; // block navigation to next card
        }
      } catch (e) {
        console.warn('Validation HTML5 non disponible ou erreur', e);
      }

      // Vérification du pseudo interdit sur la card 1 (après validation HTML5)
      if (visible === 0) {
        const pseudoInput = document.getElementById('pseudo');
        const interdit = '<?php echo $interdit; ?>';
        if (pseudoInput && pseudoInput.value.trim().toLowerCase() === interdit.toLowerCase()) {
          const err = errorEl || document.querySelector('.card#1 .error');
          if (err) {
            err.classList.remove('hidden');
            err.innerHTML = '<strong>Erreur</strong> : Ce pseudonyme n\'est pas autorisé.';
          } else {
            alert('Erreur : Ce pseudonyme n\'est pas autorisé.');
          }
          // keep user on card 1
          return;
        }
      }
      // Vérification de l'email interdit sur la card 2
      if (visible === 1) {
        const emailInput = document.getElementById('email');
        const interditMail = '<?php echo $interditmail; ?>';
        if (emailInput && emailInput.value.trim().toLowerCase() === interditMail.toLowerCase()) {
          const err = errorEl || document.querySelector('.card#2 .error');
          if (err) {
            err.classList.remove('hidden');
            err.innerHTML = '<strong>Erreur</strong> : Cet email n\'est pas autorisé.';
          } else {
            alert('Erreur : Cet email n\'est pas autorisé.');
          }
          // keep user on card 2
          return;
        }
      }

      if (visible < cards.length - 1) showCardByIndex(visible + 1);
    }
    window.showPreviousCard = function () {
      const cards = Array.from(document.querySelectorAll('.card'));
      const visible = cards.findIndex(c => !c.classList.contains('hidden'));
      if (visible > 0) showCardByIndex(visible - 1);
    }
    window.finishRegistration = function () {
      console.log('[register] finishRegistration called');
      // Validate HTML5 required fields first
      var form = document.getElementById('multiForm');
      if (!form) return;
      if (!form.checkValidity()) {
        var invalid = form.querySelector(':invalid');
        if (invalid) {
          // show the card containing the invalid field
          var card = invalid.closest('.card');
          var cards = Array.from(document.querySelectorAll('.card'));
          var idx = card ? cards.indexOf(card) : 0;
          if (typeof showCardByIndex === 'function') showCardByIndex(idx);
          var errDiv = card ? card.querySelector('.error') : null;
          var message = getFieldValidationMessage(invalid);
          try { invalid.setCustomValidity(message); } catch (e) { /* ignore */ }
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

      // All checks passed — perform a real submission that triggers HTML5 validation
      try {
        // Allow the submit handler to let this programmatic submit through
        window.__allow_submit = true;
        // mark that we are attempting submission (used by the debug fallback below)
        try { window.__submission_confirmed = false; } catch (e) { /* ignore */ }
        console.log('[register] calling requestSubmit (or form.submit fallback)');
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit();
        } else {
          form.submit();
        }
        // If no submit event completes within 600ms, attempt a direct submit as a fallback
        setTimeout(function () {
          try {
            if (!window.__submission_confirmed) {
              console.warn('[register] no submit event detected within timeout — using fallback form.submit()');
              // ensure the guard is set so submit listener allows it
              window.__allow_submit = true;
              form.submit();
            }
          } catch (e) { console.error('[register] fallback submit failed', e); }
        }, 600);
      } catch (e) {
        window.__allow_submit = false;
        // fallback to native submit
        form.submit();
      }
    }

    // Si une erreur PHP est détectée, afficher la carte concernée au chargement
    <?php if (isset($hasError) && $hasError && $error_card): ?>
      window.addEventListener('DOMContentLoaded', function () {
        showCardByIndex(<?php echo $error_card - 1; ?>); // -1 car les index commencent à 0
      });
    <?php endif; ?>
    // Intercept native form submit (enter key) to ensure we run finishRegistration()
    document.addEventListener('DOMContentLoaded', function () {
      var form = document.getElementById('multiForm');
      if (form) {
        form.addEventListener('submit', function (e) {
          console.log('[register] form submit event (allow flag =', !!window.__allow_submit, ')');
          // If this submit was allowed by the page code, mark confirmation and allow it
          if (window.__allow_submit) {
            // signal that submission actually happened so the caller's fallback doesn't re-submit
            try { window.__submission_confirmed = true; } catch (e) { /* ignore */ }
            window.__allow_submit = false;
            return; // allow native submit to proceed
          }
          // otherwise prevent default and run our validation
          e.preventDefault();
          try {
            if (typeof window.finishRegistration === 'function') window.finishRegistration();
          } catch (err) {
            console.error('finishRegistration error', err);
          }
        });
      }
    });

    <?php if (!isset($hasError) || !$hasError): ?>
      // On a fresh page load (no server validation error), clear saved draft and reset fields

      // Attach invalid/input handlers to elements to provide French messages
      document.addEventListener('DOMContentLoaded', function () {
        try {
          var formEl = document.getElementById('multiForm');
          if (formEl) {
            var elems = formEl.querySelectorAll('input, textarea, select');
            elems.forEach(function (el) {
              el.addEventListener('invalid', function (ev) {
                try { el.setCustomValidity(getFieldValidationMessage(el)); } catch (e) { /* ignore */ }
              });
              el.addEventListener('input', function () {
                try { el.setCustomValidity(''); } catch (e) { /* ignore */ }
              });
            });
          }
        } catch (e) { /* ignore */ }
      });
      document.addEventListener('DOMContentLoaded', function () {
        try {
          if (window.clearSavedRegistration) {
            window.clearSavedRegistration();
          } else if (window.localStorage) {
            Object.keys(localStorage).filter(k => k.startsWith('register:')).forEach(k => localStorage.removeItem(k));
          }
        } catch (e) { /* ignore */ }

        // Also clear any form values that the browser may have restored on reload
        var form = document.getElementById('multiForm');
        if (form) {
          Array.from(form.elements).forEach(function (el) {
            try {
              var tag = (el.tagName || '').toLowerCase();
              if (tag === 'input' || tag === 'textarea' || tag === 'select') {
                if (el.type === 'checkbox' || el.type === 'radio') {
                  el.checked = false;
                } else {
                  el.value = '';
                }
              }
            } catch (e) { /* ignore individual element errors */ }
          });
          // ensure the first card is visible
          if (typeof showCardByIndex === 'function') showCardByIndex(0);
        }
      });
    <?php endif; ?>
  </script>

  <script type="module" src="../../js/registerPass.js"></script>
</body>

</html>