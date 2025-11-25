<?php
session_start();

// connexion a la bdd
include '../../../selectBDD.php';
$pdo->exec("SET search_path TO cobrec1");

// Gestion de la vérification de l'email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_email') {
    $email = htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8');
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM cobrec1._compte WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $count = $stmt->fetchColumn();
        
        echo json_encode(['exists' => ($count > 0)]);
    } catch (Exception $e) {
        echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Gestion de la vérification du numéro de téléphone
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_phone') {
    $telephone = htmlspecialchars($_POST['telephone'] ?? '', ENT_QUOTES, 'UTF-8');
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM cobrec1._compte WHERE num_telephone = :telephone');
        $stmt->execute(['telephone' => $telephone]);
        $count = $stmt->fetchColumn();
        
        echo json_encode(['exists' => ($count > 0)]);
    } catch (Exception $e) {
        echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Gestion de la vérification de la raison sociale
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_Rsociale') {
    $Rsociale = htmlspecialchars($_POST['Rsociale'] ?? '', ENT_QUOTES, 'UTF-8');
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM cobrec1._vendeur WHERE raison_sociale = :Rsociale');
        $stmt->execute(['Rsociale' => $Rsociale]);
        $count = $stmt->fetchColumn();
        
        echo json_encode(['exists' => ($count > 0)]);
    } catch (Exception $e) {
        echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Gestion de la vérification du SIREN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_SIREN') {
    $SIREN = htmlspecialchars($_POST['SIREN'] ?? '', ENT_QUOTES, 'UTF-8');
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM cobrec1._vendeur WHERE siren = :siren');
        $stmt->execute(['siren' => $SIREN]);
        $count = $stmt->fetchColumn();
        
        echo json_encode(['exists' => ($count > 0)]);
    } catch (Exception $e) {
        echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Gestion de la vérification de la dénomination
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_Denomination') {
    $Denomination = htmlspecialchars($_POST['Denomination'] ?? '', ENT_QUOTES, 'UTF-8');
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM cobrec1._vendeur WHERE denomination = :denomination');
        $stmt->execute(['denomination' => $Denomination]);
        $count = $stmt->fetchColumn();
        
        echo json_encode(['exists' => ($count > 0)]);
    } catch (Exception $e) {
        echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Créer un compte vendeur - Alizon</title>
  <link rel="icon" type="image/png" href="../../../img/favicon.svg">
  <style>
    /* Local fonts: Baloo 2 and Quicksand */
    @font-face { font-family: 'Baloo 2'; src: url('../../fonts/baloo.regular.ttf') format('truetype'); font-weight: 400; font-style: normal; font-display: swap; }
    @font-face { font-family: 'Quicksand'; src: url('../../fonts/quicksand.light-regular.otf') format('opentype'); font-weight: 300; font-style: normal; font-display: swap; }
    @font-face { font-family: 'Quicksand'; src: url('../../fonts/quicksand.light-regular.otf') format('opentype'); font-weight: 400; font-style: normal; font-display: swap; }
  </style>
  <link rel="stylesheet" href="../../../styles/Connexion_Creation/styleCoCrea.css">
</head>

<?php

// Récupération des données du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $Rsociale = htmlspecialchars($_POST['Rsociale'] ?? '', ENT_QUOTES, 'UTF-8');
  $SIREN = htmlspecialchars($_POST['SIREN'] ?? '', ENT_QUOTES, 'UTF-8');
  $Denomination = htmlspecialchars($_POST['Denomination'] ?? '', ENT_QUOTES, 'UTF-8');
  $email = htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8');
  $telephone = htmlspecialchars($_POST['telephone'] ?? '', ENT_QUOTES, 'UTF-8');
  $rue = htmlspecialchars($_POST['rue'] ?? '', ENT_QUOTES, 'UTF-8');
  $codeP = htmlspecialchars($_POST['codeP'] ?? '', ENT_QUOTES, 'UTF-8');
  $commune = htmlspecialchars($_POST['commune'] ?? '', ENT_QUOTES, 'UTF-8');
  $mdp = $_POST['mdp'] ?? '';
  $Cmdp = $_POST['Cmdp'] ?? '';

  // Initialisation du message d'erreur
  $hasError = false;
  $error_card = null;
  $error_message = '';
  //$bdd_errors = [];
  if (!$hasError && isset($_POST['action']) === false) {
    try {

      // Insertion dans la bdd des données de compte
      $sql = 'INSERT INTO cobrec1._compte(email, num_telephone, mdp, timestamp_inscription)
              VALUES (:email, :telephone, :mdp, CURRENT_TIMESTAMP)';
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        'email' => $email,
        'telephone' => $telephone,
        'mdp' => $mdp
      ]);

      // Récupérer l'id du compte créé
      try {
        $id_compte = $pdo->lastInsertId();
      } catch (Exception $e) {
        $id_compte = null;
      }
      $_SESSION['idCompte'] = $id_compte;

      // Nettoyer le SIREN (enlever les espaces)
      $SIREN_clean = str_replace(' ', '', $SIREN);

      // Insérer les informations vendeur
      $sqlVendeur = 'INSERT INTO cobrec1._vendeur(id_compte, raison_sociale,denomination, siren )
              VALUES (:id_compte, :raison_sociale, :denomination, :siren)';
      $stmtVendeur = $pdo->prepare($sqlVendeur);
      $stmtVendeur->execute([
        'id_compte' => $id_compte,
        'raison_sociale' => $Rsociale,
        'siren' => $SIREN_clean,
        'denomination' => $Denomination
      ]);

      // Récupération idVendeur
      $vendeurStmt = $pdo->prepare("SELECT id_vendeur FROM _vendeur WHERE id_compte = :id");
      $vendeurStmt->execute([':id' => $id_compte]);
      $vendeur = $vendeurStmt->fetch(PDO::FETCH_ASSOC);
      if ($vendeur) {
        $vendeurId = (int)$vendeur['id_vendeur'];
      }
      $_SESSION['vendeur_id'] = $vendeurId;
      
      $sqlAdrss = 'INSERT INTO cobrec1._adresse(id_compte, a_adresse, a_ville, a_code_postal)
                VALUES (:id_compte, :adresse, :ville, :code_postal)';
        $stmtAdrss = $pdo->prepare($sqlAdrss);
        $stmtAdrss->execute([
          'id_compte' => $id_compte,
          'adresse' => $rue,
          'ville'    => $commune,
          'code_postal'    => $codeP
        ]);
    } catch (Exception $e) {
      $hasError = true;
      $error_card = 4; 
      $error_message = 'Une erreur est survenue lors de la création du compte.';
    }


    // Redirection vers la page d'accueil uniquement si pas d'erreur
    if (!$hasError) {
      $url = '../index.php';
      echo '<!doctype html><html><head><meta http-equiv="refresh" content="0;url='.$url.'">';
      echo '<script>window.location.href="'.$url.'";</script></head><body>Si vous n\'êtes pas redirigé automatiquement, <a href="'.$url.'">cliquez ici</a>.</body></html>';
      exit;
    }
  }
}
?>

<style>
  body {
    background: linear-gradient(to bottom right, #CD7F32, #D4183D);
  }
  .debutant a {
    color: #CD7F32;
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
    <!-- Card 1 -->
    <div class="card" id="1">
      <div class="logo">
        <img src="../../../img/svg/logo-text.svg" alt="Logo Alizon">
      </div>

      <h1>Créer un compte</h1>
      <p class="subtitle">Identifiants</p>

      <div>
        <label for="Rsociale">Raison sociale</label>
        <input type="text" id="Rsociale" name="Rsociale" placeholder="Votre Raison sociale" maxlength="254" required>
      </div>

       <div>
        <label for="Denomination">Denomination</label>
        <input type="text" id="Denomination" name="Denomination" placeholder="Votre Denomination" maxlength="254" required>
      </div>

      <div>
        <label for="SIREN">N° SIREN</label>
        <input type="text" id="SIREN" name="SIREN" placeholder="numéro SIREN" pattern="^(?:[0-9]{9}|[0-9]{3} [0-9]{3} [0-9]{3})$" inputmode="numeric" maxlength="11" title="9 chiffres ou format 123 456 789" required>
      </div>

     <div class="debutant">Retour a la page de <a href="../connexionVendeur/index.php"><strong>Connexion →</strong></a></div>

      <div class="error">
        <?php if (isset($hasError) && $hasError && $error_card == 1): ?>
          <strong>Erreur</strong> : <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
        <?php endif; ?>
      </div>

      <div class="step">étape 1 / 4</div>

      <div class="next-btn" role="group" aria-label="Suivant action">
        <span class="next-text">Suivant</span>
        <button type="button" class="arrow-only" aria-label="Suivant" onclick="showNextCard()">
          <img src="../../../img/svg/fleche-gauche.svg" alt="" style="filter : invert(1) saturate(0.9)" class="btn-arrow" aria-hidden="true">
        </button>
      </div>
    </div>

    <!-- Card 2 -->
    <div class="card hidden" id="2">
      <div class="logo">
        <img src="../../../img/svg/logo-text.svg" alt="Logo Alizon">
      </div>

      <h1>Créer un compte</h1>
      <p class="subtitle">Coordonnées</p>

      <div>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="exemple@domaine.extension" maxlength="254" pattern="^[^\s@]+@[^\s@]+\.[^\s@]+$" required title="Veuillez saisir une adresse e-mail valide.">
      </div>

      <div>
        <label for="telephone">Numéro de téléphone</label>
        <input type="text" id="telephone" name="telephone" inputmode="numeric" pattern="(0|\\+33|0033)[1-9][0-9]{8}" maxlength="10" placeholder="ex: 0615482649" required title="Le numéro de télephone doit contenir 10 chiffres" oninput="this.value=this.value.replace(/\D/g,'').slice(0,10)">
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
            <img src="../../../img/svg/fleche-gauche.svg" alt="Précédent" style="filter : invert(1) saturate(0.9)" class="btn-arrow-left" aria-hidden="true">
          </button>
        </div>
        <div class="next-btn" role="group" aria-label="Suivant action">
          <span class="next-text">Suivant</span>
          <button type="button" class="arrow-only" aria-label="Suivant" onclick="showNextCard()">
            <img src="../../../img/svg/fleche-gauche.svg" alt="" style="filter : invert(1) saturate(0.9)" class="btn-arrow" aria-hidden="true">
          </button>
        </div>
      </div>
    </div>

    <!-- Card 3 -->
    <div class="card hidden" id="3">
      <div class="logo">
        <img src="../../../img/svg/logo-text.svg" alt="Logo Alizon">
      </div>

      <h1>Créer un compte</h1>
      <p class="subtitle">Adresse</p>

      <div>
        <label for="rue">Rue</label>
        <input type="text" id="rue" name="rue" placeholder="ex: 19 rue Hant koz" required>
      </div>

      <div class="inline-flex address-row">
        <div class="culumn-flex" id="div_codeP">
          <label for="codeP">Code Postal</label>
          <input type="text" id="codeP" name="codeP" inputmode="numeric" pattern="^((0[1-9])|([1-8][0-9])|(9[0-7])|(2A)|(2B)) *([0-9]{3})?$" maxlength="5" placeholder="ex: 22300" required title="Le code postal doit contenir 5 chiffres">
        </div>

        <div class="culumn-flex">
          <label for="commune">Commune</label>
          <input type="text" id="commune" name="commune" placeholder="ex:lannion" required>
        </div>
      </div>

      <div class="error"></div>

      <div class="step">étape 3 / 4</div>

      <div class="inline-flex">
        <div class="next-btn" role="group" aria-label="Précédent action">
          <span class="next-text">Précédent</span>
          <button type="button" class="arrow-only" aria-label="Précédent" onclick="showPreviousCard()">
            <img src="../../../img/svg/fleche-gauche.svg" alt="Précédent" style="filter : invert(1) saturate(0.9)" class="btn-arrow-left" aria-hidden="true">
          </button>
        </div>
        <div class="next-btn" role="group" aria-label="Suivant action">
          <span class="next-text">Suivant</span>
          <button type="button" class="arrow-only" aria-label="Suivant" onclick="showNextCard()">
            <img src="../../../img/svg/fleche-gauche.svg" alt="" style="filter : invert(1) saturate(0.9)" class="btn-arrow" aria-hidden="true">
          </button>
        </div>
      </div>
    </div>

    <!-- Card 4 -->
    <div class="card hidden" id="4">
      <div class="logo">
        <img src="../../../img/svg/logo-text.svg" alt="Logo Alizon">
      </div>

      <h1>Créer un compte</h1>
      <p class="subtitle">Mot de passe</p>

      <div>
        <label for="mdp">Mot de passe</label>
        <input type="password" id="mdp" name="mdp" placeholder="***********" pattern="^(?=.*\d)(?=.*[A-Z])(?=.*[a-z])(?=.*[^A-Za-z0-9]).{8,16}$" required>
      </div>

      <div>
        <label for="Cmdp">Confirmer le mot de passe</label>
        <input type="password" id="Cmdp" name="Cmdp" placeholder="**********" required>
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
            <img src="../../../img/svg/fleche-gauche.svg" alt="Précédent" style="filter : invert(1) saturate(0.9)" class="btn-arrow-left" aria-hidden="true">
          </button>
        </div>
        <div class="next-btn" role="group" aria-label="Suivant action">
          <span class="next-text">Terminer</span>
          <button type="button" onclick="finishRegistration()" id="finishBtn" class="arrow-only" aria-label="Terminer">
            <img src="../../../img/svg/fleche-gauche.svg" alt="" style="filter : invert(1) saturate(0.9)" class="btn-arrow" aria-hidden="true">
          </button>
        </div>
      </div>
    </div>
  </form>

  <script>
    function currentCard() { return document.querySelector('.card:not(.hidden)'); }
    
    //fonction pour afficher les card
    function showCardByIndex(idx) {
      const cards = Array.from(document.querySelectorAll('.card'));
      cards.forEach((c, i) => c.classList.toggle('hidden', i !== idx));
    }

    // Gestion de la validation des différents champs
    function getFieldValidationMessage(el) {
      try {
        if (el && el.validity) {
          if (el.validity.valueMissing) return 'Ce champ est requis.';

          // Vérification du mot de passe
          if (el.id === 'mdp') {
            var val = (el.value || '').trim();
            if (val.length < 9) return 'Le mot de passe doit contenir au moins 9 caractères.';
            if (val.length > 16) return 'Le mot de passe doit contenir au maximum 16 caractères.';
            if (!/[0-9]/.test(val)) return 'Le mot de passe doit contenir au moins un chiffre.';
            if (!/[A-Z]/.test(val)) return 'Le mot de passe doit contenir au moins une lettre majuscule.';
            if (!/[a-z]/.test(val)) return 'Le mot de passe doit contenir au moins une lettre minuscule.';
            if (!/[^A-Za-z0-9]/.test(val)) return 'Le mot de passe doit contenir au moins un caractère spécial.';
            if (el.validity.patternMismatch) return 'Le mot de passe ne respecte pas le format requis.';
          }

          // Vérification des REGEX
          if (el.validity.patternMismatch) {
            if (el.type === 'email') return 'Veuillez saisir une adresse e-mail valide.';
            if (el.id === 'telephone') return 'Le numéro de téléphone n\'a pas le bon format.';
            if (el.id === 'codeP') return 'Le code postal est incorrecte.';
            if (el.id === 'SIREN') return 'Le SIREN doit contenir 9 chiffres.';
            return 'Le format de ce champ est invalide.';
          }
        }
      } catch (e) { /* ignore */ }
      return el && el.validationMessage ? el.validationMessage : 'Veuillez remplir ce champ correctement.';
    }

    // Vérification de l'email
    async function checkEmailExists(email) {
      try {
        const formData = new FormData();
        formData.append('action', 'check_email');
        formData.append('email', email);
        
        const response = await fetch('index.php', {
          method: 'POST',
          body: formData
        });
        
        if (!response.ok) return false;
        const data = await response.json();
        return data.exists;
      } catch (e) {
        console.error('Erreur lors de la vérification de l\'email:', e);
        return false;
      }
    }

    // Vérification du téléphone
    async function checkPhoneExists(telephone) {
      try {
        const formData = new FormData();
        formData.append('action', 'check_phone');
        formData.append('telephone', telephone);
        
        const response = await fetch('index.php', {
          method: 'POST',
          body: formData
        });
        
        if (!response.ok) return false;
        const data = await response.json();
        return data.exists;
      } catch (e) {
        console.error('Erreur lors de la vérification du téléphone:', e);
        return false;
      }
    }

    // Vérification de la raison sociale
    async function checkRsocialeExists(Rsociale) {
      try {
        const formData = new FormData();
        formData.append('action', 'check_Rsociale');
        formData.append('Rsociale', Rsociale);
        
        const response = await fetch('index.php', {
          method: 'POST',
          body: formData
        });
        
        if (!response.ok) return false;
        const data = await response.json();
        return data.exists;
      } catch (e) {
        console.error('Erreur lors de la vérification de la raison sociale:', e);
        return false;
      }
    }

    // Vérification de la dénomination
    async function checkDenominationExists(Denomination) {
      try {
        const formData = new FormData();
        formData.append('action', 'check_Denomination');
        formData.append('Denomination', Denomination);
        
        const response = await fetch('index.php', {
          method: 'POST',
          body: formData
        });
        
        if (!response.ok) return false;
        const data = await response.json();
        return data.exists;
      } catch (e) {
        console.error('Erreur lors de la vérification de la dénomination:', e);
        return false;
      }
    }

    // Vérification du SIREN
    async function checkSIRENExists(SIREN) {
      try {
        const formData = new FormData();
        formData.append('action', 'check_SIREN');
        formData.append('SIREN', SIREN.replace(/\s/g, '')); // Enlever les espaces
        
        const response = await fetch('index.php', {
          method: 'POST',
          body: formData
        });
        
        if (!response.ok) return false;
        const data = await response.json();
        return data.exists;
      } catch (e) {
        console.error('Erreur lors de la vérification du SIREN:', e);
        return false;
      }
    }

    // Fonction pour passage à la suite du formulaire
    window.showNextCard = async function () {
      const cards = Array.from(document.querySelectorAll('.card'));
      const visible = cards.findIndex(c => !c.classList.contains('hidden'));
      const activeCard = cards[visible];
      const errorEl = activeCard ? activeCard.querySelector('.error') : null;

      const invalid = activeCard ? activeCard.querySelector(':invalid') : null;
      if (invalid) {
        const message = getFieldValidationMessage(invalid);
        if (errorEl) { 
          errorEl.innerHTML = '<strong>Erreur</strong> : ' + message; 
          errorEl.classList.remove('hidden'); 
        }
        invalid.focus();
        return;
      }

      // Vérification de la raison sociale, dénomination et du SIREN à la card 1
      if (visible === 0) {
        const rsocialeInput = document.getElementById('Rsociale');
        const denominationInput = document.getElementById('Denomination');
        const sirenInput = document.getElementById('SIREN');
        
        if (rsocialeInput && rsocialeInput.value.trim()) {
          const rsocialeExists = await checkRsocialeExists(rsocialeInput.value.trim());
          if (rsocialeExists) {
            if (errorEl) { 
              errorEl.classList.remove('hidden'); 
              errorEl.innerHTML = '<strong>Erreur</strong> : Cette raison sociale est déjà utilisée.'; 
            }
            return;
          }
        }
        
        if (denominationInput && denominationInput.value.trim()) {
          const denominationExists = await checkDenominationExists(denominationInput.value.trim());
          if (denominationExists) {
            if (errorEl) { 
              errorEl.classList.remove('hidden'); 
              errorEl.innerHTML = '<strong>Erreur</strong> : Cette dénomination est déjà utilisée.'; 
            }
            return;
          }
        }
        
        if (sirenInput && sirenInput.value.trim()) {
          const sirenExists = await checkSIRENExists(sirenInput.value.trim());
          if (sirenExists) {
            if (errorEl) { 
              errorEl.classList.remove('hidden'); 
              errorEl.innerHTML = '<strong>Erreur</strong> : Ce numéro SIREN est déjà utilisé.'; 
            }
            return;
          }
        }
      }

      // Vérification de l'email et du téléphone à la card 2
      if (visible === 1) {
        const emailInput = document.getElementById('email');
        const phoneInput = document.getElementById('telephone');
        
        if (emailInput && emailInput.value.trim()) {
          const emailExists = await checkEmailExists(emailInput.value.trim());
          if (emailExists) {
            if (errorEl) { 
              errorEl.classList.remove('hidden'); 
              errorEl.innerHTML = '<strong>Erreur</strong> : Cette adresse email est déjà utilisée.'; 
            }
            return;
          }
        }
        
        if (phoneInput && phoneInput.value.trim()) {
          const phoneExists = await checkPhoneExists(phoneInput.value.trim());
          if (phoneExists) {
            if (errorEl) { 
              errorEl.classList.remove('hidden'); 
              errorEl.innerHTML = '<strong>Erreur</strong> : Ce numéro de téléphone est déjà utilisé.'; 
            }
            return;
          }
        }
      }

      if (visible < cards.length - 1) showCardByIndex(visible + 1);
    };

    // Fonction bouton page précédente
    window.showPreviousCard = function () {
      const cards = Array.from(document.querySelectorAll('.card'));
      const visible = cards.findIndex(c => !c.classList.contains('hidden'));
      if (visible > 0) showCardByIndex(visible - 1);
    };

    // Fonction pour le bouton de confirmation
    window.finishRegistration = function () {
      const form = document.getElementById('multiForm');
      if (!form) return;
      
      if (!form.checkValidity()) {
        const invalid = form.querySelector(':invalid');
        if (invalid) {
          const card = invalid.closest('.card');
          const cards = Array.from(document.querySelectorAll('.card'));
          const idx = card ? cards.indexOf(card) : 0;
          showCardByIndex(idx);
          const errDiv = card ? card.querySelector('.error') : null;
          const message = getFieldValidationMessage(invalid);
          if (errDiv) { 
            errDiv.innerHTML = '<strong>Erreur</strong> : ' + message;
            errDiv.classList.remove('hidden'); 
          }
          invalid.focus();
        }
        return;
      }

      const mdpEl = document.getElementById('mdp');
      const cmdpEl = document.getElementById('Cmdp');
      const mdp = mdpEl ? mdpEl.value : '';
      const cmdp = cmdpEl ? cmdpEl.value : '';
      
      if (mdp !== cmdp) {
        showCardByIndex(3);
        const err = document.querySelector('.card#\\34  .error');
        if (err) {
          err.classList.remove('hidden');
          err.innerHTML = '<strong>Erreur</strong> : Les mots de passe ne correspondent pas.';
        }
        return;
      }
      form.submit();
    };

    // Affichage des messages d'erreur
    <?php if (isset($hasError) && $hasError && $error_card): ?>
      window.addEventListener('DOMContentLoaded', function () {
        showCardByIndex(<?php echo $error_card - 1; ?>);
      });
    <?php endif; ?>

    document.addEventListener('DOMContentLoaded', function () {
      const form = document.getElementById('multiForm');
      if (form) {
        const elems = form.querySelectorAll('input, textarea, select');
        elems.forEach(function (el) {
          el.addEventListener('invalid', function (ev) {
            ev.preventDefault();
          });
          el.addEventListener('input', function () {
            const card = el.closest('.card');
            const errorDiv = card ? card.querySelector('.error') : null;
            if (errorDiv) errorDiv.classList.add('hidden');
          });

          // Avancer à la carte suivante quand l'utilisateur appuie sur Entrée
          el.addEventListener('keydown', function (ev) {
            if (ev.key !== 'Enter') return;
            const tag = (el.tagName || '').toLowerCase();
            if (tag === 'textarea') return; // laisser Entrée dans textarea
            // empêcher le comportement par défaut (soumission)
            ev.preventDefault();
            try {
              const cards = Array.from(document.querySelectorAll('.card'));
              const visible = cards.findIndex(c => !c.classList.contains('hidden'));
              if (visible === -1) return;
              if (visible >= cards.length - 1) {
                // dernière carte -> valider / terminer
                if (typeof finishRegistration === 'function') finishRegistration();
              } else {
                if (typeof showNextCard === 'function') showNextCard();
              }
            } catch (e) { /* ignore */ }
          });
        });
      }

      // Formatage automatique du SIREN : ajouter un espace toutes les 3 chiffres
      (function () {
        const sirenEl = document.getElementById('SIREN');
        if (!sirenEl) return;

        function formatSirenDigits(digits) {
          // limite à 9 chiffres
          digits = digits.replace(/\D/g, '').slice(0, 9);
          return digits.replace(/(\d{3})(?=\d)/g, '$1 ').trim();
        }

        function setCaretFromDigitCount(el, digitsBefore) {
          const val = el.value;
          let seen = 0;
          for (let i = 0; i < val.length; i++) {
            if (/\d/.test(val[i])) seen++;
            if (seen >= digitsBefore) {
              // place cursor after this character
              const pos = i + 1;
              el.setSelectionRange(pos, pos);
              return;
            }
          }
          // otherwise put at end
          el.setSelectionRange(val.length, val.length);
        }

        sirenEl.addEventListener('input', function (ev) {
          const el = ev.target;
          const raw = el.value;
          const selStart = el.selectionStart || 0;
          // count digits before caret
          const digitsBefore = (raw.slice(0, selStart).match(/\d/g) || []).length;
          const formatted = formatSirenDigits(raw);
          if (formatted !== raw) {
            el.value = formatted;
          }
          // restore caret position according to digit count
          setCaretFromDigitCount(el, digitsBefore);
        });

        // Ensure on paste we format correctly
        sirenEl.addEventListener('paste', function (ev) {
          setTimeout(function () {
            const formatted = formatSirenDigits(sirenEl.value);
            sirenEl.value = formatted;
          }, 0);
        });

        // On form submit, strip spaces so server receives only digits
        if (form) {
          form.addEventListener('submit', function () {
            if (sirenEl && sirenEl.value) {
              sirenEl.value = (sirenEl.value || '').replace(/\s+/g, '');
            }
          });
        }
      })();
    });
  </script>
</body>
</html>