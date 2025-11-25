<?php
session_start();
include '../../../selectBDD.php'; 

if(empty($_SESSION['vendeur_id']) === false){
  $vendeur_id = $_SESSION['vendeur_id'];
}else{
?>

<script>
    alert("Vous n'êtes pas connecté. Vous allez être redirigé vers la page de connexion.");
    document.location.href = "/pages/backoffice/connexionVendeur/index.php";
</script>
<?php
}

function check_same_string($a, $b) {
    return $a === $b;
}

function valueExists($pdo, $table, $column, $value, $excludeId = null, $excludeColumn = null) {
    $sql = "SELECT COUNT(*) FROM $table WHERE $column = :value";

    // exclure l'utilisateur courant (évite les faux positifs)
    if ($excludeId !== null && $excludeColumn !== null) {
        $sql .= " AND $excludeColumn != :excludeId";
    }

    $stmt = $pdo->prepare($sql);

    $params = ['value' => $value];

    if ($excludeId !== null && $excludeColumn !== null) {
        $params['excludeId'] = $excludeId;
    }

    $stmt->execute($params);
    return $stmt->fetchColumn() > 0;
}


$vendeur_id = $_SESSION['vendeur_id'];

try {
    $query = "
        SELECT
            v.denomination AS pseudo,
            v.raison_sociale AS rsociale,
            v.siren AS siren,
            c.id_compte AS compte,
            c.mdp,
            c.email AS email,
            c.num_telephone AS telephone,
            a.a_adresse AS adresse,
            a.a_code_postal AS codep,
            a.a_ville AS ville,
            a.a_complement AS complement,
            i.id_image,
            i.i_lien AS image
        FROM cobrec1._compte c
        LEFT JOIN cobrec1._vendeur v ON c.id_compte = v.id_compte
        LEFT JOIN cobrec1._adresse a ON v.id_compte = a.id_compte
        LEFT JOIN cobrec1._represente_compte r ON c.id_compte = r.id_compte
        LEFT JOIN cobrec1._image i ON r.id_image = i.id_image
        WHERE v.id_vendeur = :id
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $vendeur_id]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC); // récupère la ligne unique

    if (!$old) {
        die("Impossible de récupérer les informations du vendeur.");
    }

    $vendeur = $old; // utiliser pour préremplir le formulaire


} catch (PDOException $e) {
    die("Erreur lors de la récupération des informations : " . htmlspecialchars($e->getMessage()));
}

/* ---------------------------------------------------------
   TRAITEMENT DES FORMULAIRES
--------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ---------------------------------------------------------
    TRAITEMENT DU CHANGEMENT DE MDP 
    --------------------------------------------------------- */
    if (isset($_POST['change_password'])) {

        $old = trim($_POST['old_password']);
        $new = trim($_POST['new_password']);
        $confirm = trim($_POST['confirm_password']);

        // Récupération du mot de passe actuel en BDD
        $stmt = $pdo->prepare("SELECT mdp FROM cobrec1._compte WHERE id_compte = :id");
        $stmt->execute(['id' => $vendeur['compte']]);
        $oldPasswordDB = $stmt->fetchColumn();

        // Vérifier que l'ancien mot de passe est correct
        if (!check_same_string($old, $oldPasswordDB)) {
            header("Location: index.php?password_error=" . urlencode("L'ancien mot de passe est incorrect."));
            exit;
        }

        // Vérifier que les nouveaux mots de passe correspondent
        if ($new !== $confirm) {
            header("Location: index.php?password_error=" . urlencode("Les nouveaux mots de passe ne correspondent pas."));
            exit;
        }

        // Mise à jour du mot de passe en clair
        $stmt = $pdo->prepare("
            UPDATE cobrec1._compte
            SET mdp = :new
            WHERE id_compte = :id
        ");
        $stmt->execute([
            'new' => $new,
            'id'  => $vendeur['compte']
        ]);

        header("Location: index.php?password_success=1");
        exit;

    }

    /* ---------------------------------------------------------
       2. Mise à jour table _compte
    --------------------------------------------------------- */

    // EMAIL
    if (isset($_POST['email']) && $_POST['email'] !== $old['email']) {
        
        if (valueExists($pdo, "cobrec1._compte", "email", trim($_POST['email']), $vendeur['compte'], "id_compte")) {
            header("Location: index.php?error_email=" . urlencode("Cet email est déjà utilisé."));
            exit;
        }

        
        $stmt = $pdo->prepare("
            UPDATE cobrec1._compte
            SET email = :val
            WHERE id_compte = :id
        ");
        $stmt->execute([
            'val' => trim($_POST['email']),
            'id'  => $vendeur['compte']
        ]);
    }

    // TÉLÉPHONE
    if (isset($_POST['telephone']) && $_POST['telephone'] !== $old['telephone']) {
        
        if (valueExists($pdo, "cobrec1._compte", "num_telephone", trim($_POST['telephone']), $vendeur['compte'], "id_compte")) {
            header("Location: index.php?error_num=" . urlencode("Ce numéro de téléphone est déjà utilisé."));
            exit;
        }
        
        $stmt = $pdo->prepare("
            UPDATE cobrec1._compte
            SET num_telephone = :val
            WHERE id_compte = :id
        ");
        $stmt->execute([
            'val' => trim($_POST['telephone']),
            'id'  => $vendeur['compte']
        ]);
    }



    /* ---------------------------------------------------------
       3. Mise à jour table _adresse
    --------------------------------------------------------- */

    // ADRESSE
    if (isset($_POST['adresse']) && $_POST['adresse'] !== $old['adresse']) {
        $stmt = $pdo->prepare("
            UPDATE cobrec1._adresse
            SET a_adresse = :val
            WHERE id_compte = :id
        ");
        $stmt->execute([
            'val' => trim($_POST['adresse']),
            'id'  => $vendeur['compte']
        ]);
    }

    // VILLE
    if (isset($_POST['ville']) && $_POST['ville'] !== $old['ville']) {
        $stmt = $pdo->prepare("
            UPDATE cobrec1._adresse
            SET a_ville = :val
            WHERE id_compte = :id
        ");
        $stmt->execute([
            'val' => trim($_POST['ville']),
            'id'  => $vendeur['compte']
        ]);
    }
    

    // CODE POSTAL
    if (isset($_POST['codep']) && $_POST['codep'] !== $old['codep']) {
        $stmt = $pdo->prepare("
            UPDATE cobrec1._adresse
            SET a_code_postal = :val
            WHERE id_compte = :id
        ");
        $stmt->execute([
            'val' => trim($_POST['codep']),
            'id'  => $vendeur['compte']
        ]);
    }

    // COMPLEMENT
    if (isset($_POST['complement']) && $_POST['complement'] !== $old['complement']) {
        $stmt = $pdo->prepare("
            UPDATE cobrec1._adresse
            SET a_complement = :val
            WHERE id_compte = :id
        ");
        $stmt->execute([
            'val' => trim($_POST['complement']),
            'id'  => $vendeur['compte']
        ]);
    }


    /* ---------------------------------------------------------
      4. Mise à jour de l'image du vendeur
    --------------------------------------------------------- */
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {

        // Dossier cible
        $uploadDir = "../../../img/photo/";

        // Nom unique
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = "vendeur_id_" . $vendeur['compte'] . "." . $ext;

        // Chemin final disque
        $filePath = $uploadDir . $filename;

        // Déplace le fichier
        if (move_uploaded_file($_FILES['image']['tmp_name'], $filePath)) {

            // Chemin à enregistrer dans la BDD
            $dbPath = "html/img/photo/" . $filename;

            // Récupérer ancienne image pour la supprimer
            $oldImagePathDB = $vendeur['image'];  
            $oldImagePathDisk = str_replace("html/img/photo", "../../../img/photo", $oldImagePathDB);

            // Supprimer l'ancienne image si elle existe ET si ce n’est pas une image par défaut
            if (!empty($oldImagePathDB) && file_exists($oldImagePathDisk)) {
                unlink($oldImagePathDisk);
            }

            // Mise à jour du lien dans _image
            $stmt = $pdo->prepare("
                UPDATE cobrec1._image
                SET i_lien = :lien
                WHERE id_image = :img
            ");
            $stmt->execute([
                'lien' => $dbPath,
                'img'  => $vendeur['id_image']
            ]);
        }
    }

    /* ---------------------------------------------------------
       1. Mise à jour table _vendeur
    --------------------------------------------------------- */

    // PSEUDO
    if (isset($_POST['pseudo']) && $_POST['pseudo'] !== $old['pseudo']) {

        if (valueExists($pdo, "cobrec1._vendeur", "denomination", trim($_POST['pseudo']), $vendeur_id, "id_vendeur")) {
            header("Location: index.php?error_pseudo=" . urlencode("Ce pseudo est déjà utilisé."));
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE cobrec1._vendeur
            SET denomination = :val
            WHERE id_vendeur = :id
        ");
        $stmt->execute([
            'val' => trim($_POST['pseudo']),
            'id'  => $vendeur_id
        ]);
    }

    // RAISON SOCIALE
    if (isset($_POST['rsociale']) && $_POST['rsociale'] !== $old['rsociale']) {

        if (valueExists($pdo, "cobrec1._vendeur", "raison_sociale", trim($_POST['rsociale']), $vendeur_id, "id_vendeur")) {
            header("Location: index.php?error_rsociale=" . urlencode("Cette raison sociale existe déjà."));
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE cobrec1._vendeur
            SET raison_sociale = :val
            WHERE id_vendeur = :id
        ");
        $stmt->execute([
            'val' => trim($_POST['rsociale']),
            'id'  => $vendeur_id
        ]);
    }

    // SIREN
    if (isset($_POST['siren']) && $_POST['siren'] !== $old['siren']) {

        if (valueExists($pdo, "cobrec1._vendeur", "siren", trim($_POST['siren']), $vendeur_id, "id_vendeur")) {
            header("Location: index.php?error_siren=" . urlencode("Ce numéro SIREN est déjà enregistré."));
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE cobrec1._vendeur
            SET siren = :val
            WHERE id_vendeur = :id
        ");
        $stmt->execute([
            'val' => trim($_POST['siren']),
            'id'  => $vendeur_id
        ]);
    }

    /* ---------------------------------------------------------
       Redirection
    --------------------------------------------------------- */
    header("Location: index.php?success=1");
    exit;
}

function safe($array, $key, $default = "") {
    return htmlspecialchars(isset($array[$key]) ? $array[$key] : $default);
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=1440, height=1024">
  <title>Profil Vendeur - Alizon</title>
  <link rel="icon" type="image/png" href="../../../img/favicon.svg">
  <link rel="stylesheet" href="/styles/ProfilVendeur/profil.css">
</head>
<body>
  <div class="app">

    <?php include __DIR__ . '/../../../partials/aside.html'; ?>

    <main class="main">
    <div class="page-header">
        <h1 class="page-title">Mon Compte</h1>

        <a href="../connexionVendeur/index.php" class="logout-btn">Déconnexion</a>
    </div>


      <?php if (isset($_GET['success'])): ?>
        <div class="alert-success">Informations mises à jour avec succès !</div>
      <?php endif; ?>

      <div class="profil-card">

        <h2 class="profil-card__title">Modifier mes informations</h2>

        <div class="profil-photo">
          <img src="<?= str_replace("html/img/photo", "../../../img/photo" , htmlspecialchars($vendeur['image']))?>" alt="Photo vendeur">
        </div>

        <form id="edit-form" class="edit-form" action="" method="POST" enctype="multipart/form-data">

          <div class="form-row">
            <label>Changer l'image</label>
            <input type="file" name="image" accept="image/*">
          </div>

          <div class="form-row">
            <label>Dénomination</label>
            <input type="text" id="pseudo" name="pseudo" value="<?= safe($vendeur, 'pseudo') ?>" required>
            <span class="error" id="error-pseudo"></span>
          </div>

          <div class="form-row">
            <label>Raison sociale</label>
            <input type="text" id="rsociale" name="rsociale" value="<?= safe($vendeur, 'rsociale') ?>" required>
            <span class="error" id="error-rsociale"></span>
          </div>

          <div class="form-row">
            <label>SIREN</label>
            <input type="text" id="siren" name="siren" value="<?= safe($vendeur, 'siren') ?>" required>
            <span class="error" id="error-siren"></span>
          </div>

          <div class="form-row">
            <label>Email</label>
            <input type="email" id="email" name="email" value="<?= safe($vendeur, 'email') ?>" required>
            <span class="error" id="error-email"></span>
          </div>

          <div class="form-row">
            <label>Téléphone</label>
            <input type="text" id="telephone" name="telephone" value="<?= safe($vendeur, 'telephone') ?>" required>
            <span class="error" id="error-telephone"></span>
          </div>

          <div class="form-row">
            <label>Adresse</label>
            <input type="text" id="adresse" name="adresse" value="<?= safe($vendeur, 'adresse') ?>" required>
            <span class="error" id="error-adresse"></span>
          </div>

          <div class="form-row">
            <label>Ville</label>
            <input type="text" id="ville" name="ville" value="<?= safe($vendeur, 'ville') ?>" required>
            <span class="error" id="error-ville"></span>
          </div>

          <div class="form-row">
            <label>Code postal</label>
            <input type="text" id="codep" name="codep" value="<?= safe($vendeur, 'codep') ?>" required>
            <span class="error" id="error-codep"></span>
          </div>

          <div class="form-row">
            <label>Complément d'adresse</label>
            <input type="text" id="complement" name="complement" value="<?= safe($vendeur, 'complement') ?>">
            <span class="error" id="error-complement"></span>
          </div>

          <button class="btn btn--primary" type="submit">Enregistrer</button>
        </form>
      </div>

      <!-- ===== FORMULAIRE DE MODIFICATION DU MOT DE PASSE ===== -->

      <!-- POPUP DE SUCCÈS -->
    <div id="popup-success" class="popup-success">
    Mot de passe modifié avec succès !
    </div>
    
    <!-- POPUP DE SUCCÈS -->
    <div id="popup-error" class="popup-error">
        <span id="popup-error-text"></span>
    </div>

    <div class="profil-card mt-4">

    <h2 class="profil-card__title">Modifier mon mot de passe</h2>

    <form id="form-password" method="POST" class="edit-form">

        <div class="form-row password-field">
            <label for="old_password">Ancien mot de passe</label>
            <input type="password" id="old_password" name="old_password" required>
            <span class="toggle-pwd" onclick="togglePassword('old_password', this)">
                <img src="../../../img/svg/oeil.svg" alt="Voir/Masquer" />
            </span>
        </div>

        <div class="form-row password-field">
            <label for="new_password">Nouveau mot de passe</label>
            <input type="password" id="new_password" name="new_password" required>
            <span class="toggle-pwd" onclick="togglePassword('new_password', this)">
                <img src="../../../img/svg/oeil.svg" alt="Voir/Masquer" />
            </span>
        </div>

        <div class="form-row password-field">
            <label for="confirm_password">Confirmer le mot de passe</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
            <span class="toggle-pwd" onclick="togglePassword('confirm_password', this)">
                <img src="../../../img/svg/oeil.svg" alt="Voir/Masquer" />
            </span>
        </div>



        <button class="btn btn--primary" type="submit" name="change_password">Modifier mon mot de passe</button>

    </form>
    </div>
    </main>
  </div>
  <script>
    // REGEX
    const regex = {
        pseudo: /^[a-zA-Z0-9 _-]{2,30}$/,
        rsociale: /^[a-zA-Z0-9À-ÿ' -]{2,50}$/,
        siren: /^[0-9]{9}$/,
        email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
        telephone: /^(?:\+33|0)[1-9](?:[0-9]{8})$/,
        adresse: /^[0-9a-zA-ZÀ-ÿ,' .-]{3,80}$/,
        ville: /^[a-zA-ZÀ-ÿ' -]{2,50}$/,
        codep: /^((0[1-9])|([1-8][0-9])|(9[0-7])|(2A)|(2B)) *([0-9]{3})?$/,
        complement: /^[0-9a-zA-ZÀ-ÿ,' .-]{0,80}$/
    };

    // Fonction de validation pour un champ
    function validateField(id) {
        const field = document.getElementById(id);
        const error = document.getElementById("error-" + id);
        const pattern = regex[id];

        if (!field) return;

        const value = field.value.trim();

        // Si champ vide → pas d’erreur visuelle
        if (value === "") {
            field.classList.remove("invalid", "valid");
            error.textContent = "";
            return;
        }

        // Si correspond
        if (pattern.test(value)) {
            field.classList.remove("invalid");
            field.classList.add("valid");
            error.textContent = "";
        } 
        else {
            field.classList.remove("valid");
            field.classList.add("invalid");
            error.textContent = "Valeur invalide pour " + id;
        }
    }

    // Liste des champs à vérifier
    const fields = [
        "pseudo",
        "rsociale",
        "siren",
        "email",
        "telephone",
        "adresse",
        "ville",
        "codep",
        "complement"
    ];

    // Activation de la validation live
    fields.forEach(id => {
        let field = document.getElementById(id);
        if (field) {
            field.addEventListener("input", () => validateField(id));
        }
    });

    // REGEX pour mot de passe sécurisé : 1 maj, 1 min, 1 chiffre, 8+ caractères
    const regexPwd = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/; 

    // Validation du nouveau mot de passe
    document.getElementById("new_password").addEventListener("input", function () {
        const field = this;
        const error = document.getElementById("error-new_password");

        if (regexPwd.test(field.value)) {
            field.classList.add("valid");
            field.classList.remove("invalid");
            error.textContent = "";
        } else {
            field.classList.remove("valid");
            field.classList.add("invalid");
            error.textContent =
                "Min 8 caractères, 1 majuscule, 1 minuscule, 1 chiffre et un caractère spéciale.";
        }
    });

    // Validation de la confirmation
    document.getElementById("confirm_password").addEventListener("input", function () {
        const newPwd = document.getElementById("new_password").value;
        const field = this;
        const error = document.getElementById("error-confirm_password");

        if (field.value === newPwd && field.value !== "") {
            field.classList.add("valid");
            field.classList.remove("invalid");
            error.textContent = "";
        } else {
            field.classList.remove("valid");
            field.classList.add("invalid");
            error.textContent = "Les mots de passe ne correspondent pas.";
        }
    });

    const urlParams = new URLSearchParams(window.location.search);

    if (urlParams.has("password_success")) {
        const popup = document.getElementById("popup-success");

        popup.classList.add("show");

        // Disparaît après 1 seconde
        setTimeout(() => {
            popup.classList.remove("show");
        }, 3000);

        // Nettoyage de l'URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    function togglePassword(id, iconSpan) {
        const field = document.getElementById(id);
        const img = iconSpan.querySelector("img");

        if (field.type === "password") {
            field.type = "text";
            img.src = "../../../img/svg/oeil-barre.svg"; // icône oeil barré
        } else {
            field.type = "password";
            img.src = "../../../img/svg/oeil.svg"; // icône oeil normal
        }
    }

    // POPUP ERREUR
        const urlParams2 = new URLSearchParams(window.location.search);

        if (urlParams2.has("password_error")) {
            const popup = document.getElementById("popup-error");
            const text = document.getElementById("popup-error-text");

            text.textContent = urlParams2.get("password_error");
            popup.classList.add("show");

            // Disparaît après 3 sec
            setTimeout(() => {
                popup.classList.remove("show");
            }, 3000);

            // Nettoyage URL
            window.history.replaceState({}, document.title, window.location.pathname);
        }

    // ALERT VALEUR EXISTANTE
    if (urlParams.has("error_siren")) {
        alert("Erreur SIREN déjà existant.");
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    if (urlParams.has("error_rsociale")) {
        alert("Erreur Raison sociale déjà existant.");
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    if (urlParams.has("error_pseudo")) {
        alert("Erreur Pseudo déjà existant.");
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    if (urlParams.has("error_email")) {
        alert("Erreur Email déjà existant.");
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    if (urlParams.has("error_num")) {
        alert("Erreur Numéro de téléphone déjà existant.");
        window.history.replaceState({}, document.title, window.location.pathname);
    }
  </script>
</body>
</html>
