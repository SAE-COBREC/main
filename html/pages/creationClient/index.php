<?php
session_start();
// connexion a la bdd
include '../../selectBDD.php';
$pdo->exec("SET search_path TO cobrec1");

// Gestion de la vérification de l'email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_email') {
    $email = htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8');
    try {
        //regarde si le mail saisi est déja dans la bdd
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

      //regarde si le numéro saisi est déja dans la bdd
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM cobrec1._compte WHERE num_telephone = :telephone');
        $stmt->execute(['telephone' => $telephone]);
        $count = $stmt->fetchColumn();
        
        echo json_encode(['exists' => ($count > 0)]);
    } catch (Exception $e) {
        echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Gestion de la vérification du pseudo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_pseudo') {
    $pseudo = htmlspecialchars($_POST['pseudo'] ?? '', ENT_QUOTES, 'UTF-8');
    try {

      //regarde si le numéro saisi est déja dans la bdd
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM cobrec1._client WHERE c_pseudo = :pseudo');
        $stmt->execute(['pseudo' => $pseudo]);
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
  <title>Créer un compte - Alizon</title>
  <link rel="icon" type="image/png" href="../../../img/favicon.svg">
  <link rel="stylesheet" href="../../styles/Connexion_Creation/styleCoCrea.css">
</head>

<?php

//récuperation des données du formulaire
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

  //inintialisation des données d'erreur
  $hasError = false;
  $error_card = null;
  $error_message = '';
  if (!$hasError && isset($_POST['action']) === false) {
    try {
      //insertion dans la bdd des données de compte
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

        //insérer les informations client
        $sqlClient = 'INSERT INTO cobrec1._client(id_compte, c_pseudo, c_prenom, c_nom, c_datenaissance)
                VALUES (:id_compte, :pseudo, :prenom, :nom, :datenaissance)';
        $stmtClient = $pdo->prepare($sqlClient);
        $stmtClient->execute([
          'id_compte' => $id_compte,
          'pseudo'    => $pseudo,
          'prenom'    => $prenom,
          'nom'       => $nom,
          'datenaissance' => $naissance
        ]);

          //recuperation idClient
      $clientStmt = $pdo->prepare("SELECT id_client FROM _client WHERE id_compte = :id");
        $clientStmt->execute([':id' => $id_compte]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
        if ($client) {
          $clientId = (int)$client['id_client'];
        }
      $_SESSION['idClient'] = $clientId; 
      $sqlAdrss = 'INSERT INTO cobrec1._adresse(id_compte, a_adresse, a_ville, a_code_postal)
                VALUES (:id_compte, :adresse, :ville, :code_postal)';
        $stmtAdrss = $pdo->prepare($sqlAdrss);
        $stmtAdrss->execute([
          'id_compte' => $id_compte,
          'adresse' => $rue,
          'ville'    => $commune,
          'code_postal'    => $codeP
        ]);

    //definition du message d'erreur en cas d'erreur d'insertion 
    } catch (Exception $e) {
      $hasError = true;
      $error_card = 4; 
      $error_message = 'Une erreur est survenue lors de la création du compte.';
      $bdd_errors[] = [$e->getMessage()];
    }

    if (!empty($bdd_errors)) {
      $fp = fopen('file.csv', 'w');
      foreach ($bdd_errors as $fields) {
      }
      fclose($fp);
      }
      //redirige sur la page d'acceuil
      $url = '../../index.php';
      echo '<!doctype html><html><head><meta http-equiv="refresh" content="0;url='.$url.'">';
      exit;
    }
  }
?>

<style>
  body {
    background: linear-gradient(to bottom right, #7171A3, #030212);
  }

  /* style pour entourer en rouge les champs qui ont une erreur */
  .field-error {
    border-color: #dc143c !important;
    box-shadow: 0 0 0 4px rgba(220,20,60,0.18);
    transition: box-shadow 120ms ease-in-out, border-color 120ms ease-in-out;
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
        <input type="text" id="nom" name="nom" placeholder="Votre nom" maxlength="99" required>
      </div>

      <div>
        <label for="prenom">Prénom</label>
        <input type="text" id="prenom" name="prenom" placeholder="Votre prénom" maxlength="99" required>
      </div>

      <div>
        <label for="pseudo">Pseudonyme</label>
        <input type="text" id="pseudo" name="pseudo" placeholder="Votre pseudonyme" maxlength="99" required>
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
        <input type="email" id="email" name="email" placeholder="exemple@domaine.extension" maxlength="254" pattern="^[^\s@]+@[^\s@]+\.[^\s@]+$" required title="Veuillez saisir une adresse e-mail valide.">
      </div>

      <div>
        <label for="telephone">Numéro de téléphone</label>
        <input type="text" id="telephone" name="telephone" inputmode="numeric" pattern="(0|\\+33|0033)[1-9][0-9]{8}"
          maxlength="10" placeholder="ex: 0615482649" required title="Le numéro de télephone doit contenir 10 chiffres" oninput="this.value=this.value.replace(/\D/g,'').slice(0,10)">
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
          <input type="text" id="codeP" name="codeP" inputmode="numeric" pattern="^(?:0[1-9]|[1-8]\d|9[0-8])\d{3}$" maxlength="5"
            placeholder="ex: 22300" required title="Le code postal doit contenir 5 chiffres" oninput="this.value=this.value.replace(/\D/g,'').slice(0,5)">
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
        <input type="password" id="mdp" name="mdp" placeholder="***********" value="" pattern="^(?=.*\d)(?=.*[A-Z])(?=.*[a-z])(?=.*[^A-Za-z0-9]).{8,16}$" required>
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
    function currentCard() { return document.querySelector('.card:not(.hidden)'); }
    
    function showCardByIndex(idx) {
      const cards = Array.from(document.querySelectorAll('.card'));
      cards.forEach((c, i) => c.classList.toggle('hidden', i !== idx));
    }

    // Fonction de validation d'âge
    function validateAge(dateInput) {
      const dobStr = (dateInput.value || '').trim();
      if (!dobStr) {
        dateInput.setCustomValidity('');
        return true;
      }
      
      const dob = new Date(dobStr);
      if (isNaN(dob.getTime())) {
        dateInput.setCustomValidity('Date de naissance invalide.');
        return false;
      }
      
      const today = new Date();
      let age = today.getFullYear() - dob.getFullYear();
      const m = today.getMonth() - dob.getMonth();
      if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
        age--;
      }
      
      if (age < 18) {
        dateInput.setCustomValidity('Vous devez avoir au moins 18 ans.');
        return false;
      }
      
      dateInput.setCustomValidity('');
      return true;
    }

    // gestion de la validation des différents champs
    function getFieldValidationMessage(el) {
      try {
        if (el && el.validity) {
          //cas ou aucune valeur n'est entrée
          if (el.validity.valueMissing) return 'Ce champ est requis.';

          // Vérification d'âge
          if (el.id === 'naissance') {
            if (el.validity.customError) {
              return el.validationMessage;
            }
          }
          //verification du mdp
          if (el.id === 'mdp') {
            var val = (el.value || '').trim();
            if (val.length === 0) return 'Ce champ est requis.';
            if (val.length < 9) return 'Le mot de passe doit contenir au moins 9 caractères.';
            if (val.length > 16) return 'Le mot de passe doit contenir au maximum 16 caractères.';
            if (!/[0-9]/.test(val)) return 'Le mot de passe doit contenir au moins un chiffre.';
            if (!/[A-Z]/.test(val)) return 'Le mot de passe doit contenir au moins une lettre majuscule.';
            if (!/[a-z]/.test(val)) return 'Le mot de passe doit contenir au moins une lettre minuscule.';
            if (!/[^A-Za-z0-9]/.test(val)) return 'Le mot de passe doit contenir au moins un caractère spécial.';
            if (el.validity.patternMismatch) return 'Le mot de passe ne respecte pas le format requis.';
          }
          //verif des REGEX
          if (el.validity.patternMismatch) {
            if (el.type === 'email') return 'Veuillez saisir une adresse e-mail valide.';
            if (el.id === 'telephone') return 'Le numéro de téléphone n\'a pas le bon format.';
            if (el.id === 'codeP') return 'Le code postal est incorrecte.';
            return 'Le format de ce champ est invalide.';
          }
        }
      } catch (e) { /* ignore */ }
      return el && el.validationMessage ? el.validationMessage : 'Veuillez remplir ce champ correctement.';
    }

    //verif des mail
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
    //verif du téléphone
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
    //verif du pseudo
    async function checkPseudoExists(pseudo) {
      try {
        const formData = new FormData();
        formData.append('action', 'check_pseudo');
        formData.append('pseudo', pseudo);
        
        const response = await fetch('index.php', {
          method: 'POST',
          body: formData
        });
        
        if (!response.ok) return false;
        const data = await response.json();
        return data.exists;
      } catch (e) {
        console.error('Erreur lors de la vérification du pseudo:', e);
        return false;
      }
    }
    //fonction pour passage a la suite du formulaire
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

      // Vérification du pseudo à la card 1
      if (visible === 0) {
        const pseudoInput = document.getElementById('pseudo');
        if (pseudoInput && pseudoInput.value.trim()) {
          const pseudoExists = await checkPseudoExists(pseudoInput.value.trim());
          if (pseudoExists) {
            if (errorEl) { 
              errorEl.classList.remove('hidden'); 
              errorEl.innerHTML = '<strong>Erreur</strong> : Ce pseudonyme est déjà utilisé.'; 
            }
            return;
          }
        }
      }

      // Vérification du mail à la card 2
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
        
        // Vérification du téléphone à la card 2
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

    // fonction bouton page précédante
    window.showPreviousCard = function () {
      const cards = Array.from(document.querySelectorAll('.card'));
      const visible = cards.findIndex(c => !c.classList.contains('hidden'));
      if (visible > 0) showCardByIndex(visible - 1);
    };

    // fonction pour la bouton de confirmation
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

    //affichage des message d'erreur
    <?php if (isset($hasError) && $hasError && $error_card): ?>
      window.addEventListener('DOMContentLoaded', function () {
        showCardByIndex(<?php echo $error_card - 1; ?>);
      });
    <?php endif; ?>

    document.addEventListener('DOMContentLoaded', function () {
      // Validation d'âge sur le champ de date de naissance
      const naissanceInput = document.getElementById('naissance');
      if (naissanceInput) {
        naissanceInput.addEventListener('change', function() {
          validateAge(this);
        });
        naissanceInput.addEventListener('blur', function() {
          validateAge(this);
        });
        
      }

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

        // Global Enter handler as a fallback: advance on Enter when focus is inside the form
        document.addEventListener('keydown', function (ev) {
          if (ev.key !== 'Enter') return;
          if (ev.shiftKey || ev.ctrlKey || ev.altKey || ev.metaKey) return;
          const active = document.activeElement;
          if (!active || !active.closest) return;
          if (!active.closest('#multiForm')) return;
          const tag = (active.tagName || '').toLowerCase();
          if (tag === 'textarea') return;
          if (active.type === 'submit' || active.type === 'button') return;
          ev.preventDefault();
          try {
            const cards = Array.from(document.querySelectorAll('.card'));
            const visible = cards.findIndex(c => !c.classList.contains('hidden'));
            if (visible === -1) return;
            if (visible >= cards.length - 1) {
              if (typeof finishRegistration === 'function') finishRegistration();
            } else {
              if (typeof showNextCard === 'function') showNextCard();
            }
          } catch (e) { /* ignore */ }
        });
      }
    });
  </script>
</body>
</html>