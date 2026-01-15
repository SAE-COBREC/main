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
                    if (!/[^A-Za-z0-9]/.test(val))
                        return 'Le mot de passe doit contenir au moins un caractère spécial.';
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
        } catch (e) {
            /* ignore */
        }
        return el && el.validationMessage ? el.validationMessage : 'Veuillez remplir ce champ correctement.';
    }
    // Ajoute un comportement Entrée : soumet le formulaire quand Enter est pressé dans un champ (sauf textarea)
    document.addEventListener('DOMContentLoaded', function() {
        var form = document.getElementById('multiForm');
        if (!form) return;
        var elems = form.querySelectorAll('input, textarea, select');
        elems.forEach(function(el) {
            el.addEventListener('keydown', function(ev) {
                if (ev.key !== 'Enter') return;
                if (ev.shiftKey || ev.ctrlKey || ev.altKey || ev.metaKey) return;
                var tag = (el.tagName || '').toLowerCase();
                if (tag === 'textarea') return; // laisser Entrée dans textarea
                // empêcher le comportement par défaut (soumission réelle du formulaire par le navigateur)
                ev.preventDefault();
                // appeler la logique existante qui gère la validation & la soumission
                try {
                    if (typeof finishRegistration === 'function') finishRegistration();
                } catch (e) {
                    form.submit();
                }
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
      $error_message = 'Adresse mail, pseudo ou mot de passe incorrecte.';
    } else {


      //verification que le mdp corespondant a ce mail existe
      if (!password_verify($mdp, $row['mdp'])) {
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

.footer {
    >p {
        color: #7171A3;
    }
}

.connex-btn {
    >button {
        &:hover {
            background: #7171A3;
            color: #030212;
        }
    }
}
</style>

<body>
    <form action="index.php" method="post" enctype="multipart/form-data" id="multiForm">
        <div class="card" id="1">
            <div class="logo">
                <img src="../../img/svg/logo-text.svg" alt="Logo Alizon"
                    onclick="window.location.href='../../index.php'">
            </div>

            <h1>Connexion</h1>

            <div>
                <label for="email">Email/Pseudonyme</label>
                <input type="text" id="email" name="email" placeholder="exemple@domaine.extension" required>
            </div>

            <div>
                <label for="mdp">Mot de passe</label>
                <input type="password" id="mdp" name="mdp" placeholder="***********" required>
            </div>
            <div class="forgot" onclick="window.location.href='../MDPoublieClient/index.php'">Mot de passe oublié ?
            </div>
            <!-- affichage des erreurs de saisi -->
            <div class="error">
                <?php if (isset($hasError) && $hasError && $error_card == 1): ?>
                <strong>Erreur</strong> : <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </div>


            <div class="connex-btn" role="group" aria-label="Suivant action">
                <button type="button" onclick="finishRegistration()" id="finishBtn" class="arrow-only"
                    aria-label="Terminer">
                    Terminer
                </button>
            </div>
            <div class="debutant"> Débutant sur Alizon ? <a href="../creationClient/index.php"><strong>Démarrer
                        →</strong></a></div>
            <div class="footer">
                <p>Aide</p>
                <p>Confidentialité</p>
                <p>Conditions</p>
            </div>
        </div>
    </form>

    <script>
    window.finishRegistration = function() {
        console.log('[register] finishRegistration called');
        var form = document.getElementById('multiForm');
        if (!form) return; // Ne fini le formulaire que si il existe
        if (!form.checkValidity()) {
            // Ne termine l'inscription que si le formulaire est valide (Tout les champs sont correctement remplis)
            var invalid = form.querySelector(':invalid');
            if (invalid) {
                // Trouve la carte parente de l'élément invalide
                var card = invalid.closest('.card');
                var cards = Array.from(document.querySelectorAll('.card'));
                var idx = card ? cards.indexOf(card) : 0;
                if (typeof showCardByIndex === 'function') showCardByIndex(idx);
                var errDiv = card ? card.querySelector('.error') : null;
                var message = getFieldValidationMessage(invalid);
                try {
                    invalid.setCustomValidity(message);
                } catch (e) {}
                if (errDiv) {
                    // Affiche le message d'erreur dans la carte parente
                    errDiv.textContent = message;
                    errDiv.classList.remove('hidden');
                } else {
                    // Fallback: popup d'erreur standard
                    if (window.showError) {
                        showError('Champ invalide', message);
                    } else {
                        alert(message);
                    }
                }
                invalid.focus(); // Met le focus sur le champ invalide
            }
            return;
        }
        try { // Soumet le formulaire
            window.__allow_submit = true;
            try {
                window.__submission_confirmed = false;
            } catch (e) {} // Réinitialise le suivi de soumission
            console.log('[register] calling requestSubmit (or form.submit fallback)');
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(); // Préférer requestSubmit pour déclencher les événements de soumission
            } else {
                form.submit(); // Fallback si requestSubmit n'est pas disponible
            }
            setTimeout(function() { // Fallback en cas d'absence d'événement de soumission
                try {
                    if (!window.__submission_confirmed) { // Vérifie si la soumission a été confirmée
                        console.warn(
                            '[register] no submit event detected within timeout — using fallback form.submit()'
                        );
                        window.__allow_submit = true;
                        form.submit();
                    }
                } catch (e) {
                    console.error('[register] fallback submit failed', e);
                }
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