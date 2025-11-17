<?php
//démarrer la session pour récupérer les informations du client
session_start();

//inclure le fichier de configuration pour la connexion à la base de données
include '../../selectBDD.php';

//récupérer la connexion PDO depuis le fichier de configuration
$connexionBaseDeDonnees = $pdo;

//définir le schéma de la base de données à utiliser
$connexionBaseDeDonnees->exec("SET search_path TO cobrec1");

//fonction pour vérifier l'unicité du pseudo
function verifierUnicitePseudo($connexionBaseDeDonnees, $pseudo, $idClientExclure = null)
{
    try {
        $requeteSQL = "SELECT COUNT(*) FROM cobrec1._client WHERE c_pseudo = ?";
        $params = [$pseudo];

        //exclure l'id du client actuel si fourni (pour les mises à jour)
        if ($idClientExclure !== null) {
            $requeteSQL .= " AND id_client != ?";
            $params[] = $idClientExclure;
        }

        $requetePreparee = $connexionBaseDeDonnees->prepare($requeteSQL);
        $requetePreparee->execute($params);
        $count = $requetePreparee->fetchColumn();

        return $count == 0; //retourne true si le pseudo est unique
    } catch (Exception $erreurException) {
        return false;
    }
}

//fonction pour vérifier l'unicité de l'email
function verifierUniciteEmail($connexionBaseDeDonnees, $email, $idCompteExclure = null)
{
    try {
        $requeteSQL = "SELECT COUNT(*) FROM cobrec1._compte WHERE email = ?";
        $params = [$email];

        //exclure l'id du compte actuel si fourni (pour les mises à jour)
        if ($idCompteExclure !== null) {
            $requeteSQL .= " AND id_compte != ?";
            $params[] = $idCompteExclure;
        }

        $requetePreparee = $connexionBaseDeDonnees->prepare($requeteSQL);
        $requetePreparee->execute($params);
        $count = $requetePreparee->fetchColumn();

        return $count == 0; //retourne true si l'email est unique
    } catch (Exception $erreurException) {
        return false;
    }
}

//fonction pour vérifier l'unicité du numéro de téléphone
function verifierUniciteTelephone($connexionBaseDeDonnees, $telephone, $idCompteExclure = null)
{
    try {
        $requeteSQL = "SELECT COUNT(*) FROM cobrec1._compte WHERE num_telephone = ?";
        $params = [$telephone];

        //exclure l'id du compte actuel si fourni (pour les mises à jour)
        if ($idCompteExclure !== null) {
            $requeteSQL .= " AND id_compte != ?";
            $params[] = $idCompteExclure;
        }

        $requetePreparee = $connexionBaseDeDonnees->prepare($requeteSQL);
        $requetePreparee->execute($params);
        $count = $requetePreparee->fetchColumn();

        return $count == 0; //retourne true si le téléphone est unique
    } catch (Exception $erreurException) {
        return false;
    }
}

//fonction pour valider l'email selon le regex de la BDD
function validerFormatEmail($email)
{
    $regexEmail = '/^[A-Za-z0-9._%-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/';
    return preg_match($regexEmail, $email) === 1;
}

//fonction pour valider le numéro de téléphone selon le regex de la BDD
function validerFormatTelephone($telephone)
{
    $regexTelephone = '/^0[3-7]([0-9]{2}){4}$|^0[3-7]([-. ]?[0-9]{2}){4}$/';
    return preg_match($regexTelephone, $telephone) === 1;
}

//fonction pour valider le mot de passe selon le regex de la BDD
function validerFormatMotDePasse($motDePasse)
{
    $regexMotDePasse = '/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[A-Za-z0-9.!@#$%^&*]).{8,16}$/';
    return preg_match($regexMotDePasse, $motDePasse) === 1;
}

//fonction pour récupérer les informations complètes du client
function recupererInformationsCompletesClient($connexionBaseDeDonnees, $identifiantClient)
{
    try {
        $requeteSQL = "
            SELECT cl.c_nom, cl.c_prenom, cl.c_pseudo, co.email, co.num_telephone
            FROM cobrec1._client cl
            INNER JOIN cobrec1._compte co ON cl.id_compte = co.id_compte
            WHERE cl.id_client = ?
        ";
        $requetePreparee = $connexionBaseDeDonnees->prepare($requeteSQL);
        $requetePreparee->execute([$identifiantClient]);
        $donneesClient = $requetePreparee->fetch(PDO::FETCH_ASSOC);
        return $donneesClient ?: null;
    } catch (Exception $erreurException) {
        return null;
    }
}

//fonction pour récupérer l'identifiant du compte associé au client
function recupererIdentifiantCompteClient($connexionBaseDeDonnees, $identifiantClient)
{
    try {
        $requeteSQL = "SELECT id_compte FROM cobrec1._client WHERE id_client = ?";
        $requetePreparee = $connexionBaseDeDonnees->prepare($requeteSQL);
        $requetePreparee->execute([$identifiantClient]);
        $identifiantCompte = $requetePreparee->fetchColumn();
        return $identifiantCompte !== false ? (int) $identifiantCompte : null;
    } catch (Exception $erreurException) {
        return null;
    }
}

//fonction pour récupérer toutes les adresses du client
function recupererToutesAdressesClient($connexionBaseDeDonnees, $identifiantCompte)
{
    try {
        $requeteSQL = "
            SELECT id_adresse, a_adresse, a_ville, a_code_postal, a_complement
            FROM cobrec1._adresse
            WHERE id_compte = ?
            ORDER BY id_adresse DESC
        ";
        $requetePreparee = $connexionBaseDeDonnees->prepare($requeteSQL);
        $requetePreparee->execute([$identifiantCompte]);
        return $requetePreparee->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $erreurException) {
        return [];
    }
}

//fonction pour récupérer l'historique des dernières commandes
function recupererHistoriqueCommandesRecentes($connexionBaseDeDonnees, $identifiantClient)
{
    try {
        $requeteSQL = "
            SELECT p.id_panier, p.timestamp_commande,
                   COALESCE(f.f_total_ttc, 0) as montant_total,
                   COALESCE(l.etat_livraison, 'En attente') as statut
            FROM cobrec1._panier_commande p
            LEFT JOIN cobrec1._facture f ON p.id_panier = f.id_panier
            LEFT JOIN cobrec1._livraison l ON f.id_facture = l.id_facture
            WHERE p.id_client = ? AND p.timestamp_commande IS NOT NULL
            ORDER BY p.timestamp_commande DESC
            LIMIT 5
        ";
        $requetePreparee = $connexionBaseDeDonnees->prepare($requeteSQL);
        $requetePreparee->execute([$identifiantClient]);
        return $requetePreparee->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $erreurException) {
        return [];
    }
}

//fonction pour récupérer l'image de profil du compte
function recupererImageProfilCompte($connexionBaseDeDonnees, $identifiantCompte)
{
    try {
        $requeteSQL = "
            SELECT i.id_image, i.i_lien, i.i_title, i.i_alt
            FROM cobrec1._image i
            INNER JOIN cobrec1._represente_compte rc ON i.id_image = rc.id_image
            WHERE rc.id_compte = ?
        ";
        $requetePreparee = $connexionBaseDeDonnees->prepare($requeteSQL);
        $requetePreparee->execute([$identifiantCompte]);
        return $requetePreparee->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $erreurException) {
        return null;
    }
}

//fonction pour mettre à jour le profil complet (client, compte et image)
function mettreAJourProfilCompletClient(
    $connexionBaseDeDonnees,
    $identifiantClient,
    $identifiantCompte,
    $nomFamille,
    $prenomClient,
    $pseudonymeClient,
    $adresseEmail,
    $numeroTelephone,
    $cheminLienImage = null,
    $titreImage = null,
    $texteAlternatifImage = null
) {
    try {
        //validation du pseudo unique
        if (!verifierUnicitePseudo($connexionBaseDeDonnees, $pseudonymeClient, $identifiantClient)) {
            return ['success' => false, 'message' => "Ce pseudo est déjà utilisé par un autre compte."];
        }

        //validation du format de l'email
        if (!validerFormatEmail($adresseEmail)) {
            return ['success' => false, 'message' => "Format d'email invalide."];
        }

        //validation de l'unicité de l'email
        if (!verifierUniciteEmail($connexionBaseDeDonnees, $adresseEmail, $identifiantCompte)) {
            return ['success' => false, 'message' => "Cette adresse email est déjà utilisée par un autre compte."];
        }

        //validation du format du numéro de téléphone
        if (!validerFormatTelephone($numeroTelephone)) {
            return ['success' => false, 'message' => "Format de numéro de téléphone invalide (ex: 0612345678 ou 06 12 34 56 78)."];
        }

        //validation de l'unicité du numéro de téléphone
        if (!verifierUniciteTelephone($connexionBaseDeDonnees, $numeroTelephone, $identifiantCompte)) {
            return ['success' => false, 'message' => "Ce numéro de téléphone est déjà utilisé par un autre compte."];
        }

        //mise à jour des informations du client dans la table _client
        $requeteMiseAJourClient = "
            UPDATE cobrec1._client
            SET c_nom = ?, c_prenom = ?, c_pseudo = ?
            WHERE id_client = ?
        ";
        $requetePrepareeClient = $connexionBaseDeDonnees->prepare($requeteMiseAJourClient);
        $requetePrepareeClient->execute([$nomFamille, $prenomClient, $pseudonymeClient, $identifiantClient]);

        //mise à jour des informations du compte dans la table _compte
        $requeteMiseAJourCompte = "
            UPDATE cobrec1._compte
            SET email = ?, num_telephone = ?
            WHERE id_compte = ?
        ";
        $requetePrepareeCompte = $connexionBaseDeDonnees->prepare($requeteMiseAJourCompte);
        $requetePrepareeCompte->execute([$adresseEmail, $numeroTelephone, $identifiantCompte]);

        //si une image est fournie, la mettre à jour ou l'insérer
        if ($cheminLienImage !== null && $cheminLienImage !== '') {
            $requeteVerificationImageExistante = "SELECT id_image FROM cobrec1._represente_compte WHERE id_compte = ?";
            $requetePrepareeVerification = $connexionBaseDeDonnees->prepare($requeteVerificationImageExistante);
            $requetePrepareeVerification->execute([$identifiantCompte]);
            $donneesImageExistante = $requetePrepareeVerification->fetch(PDO::FETCH_ASSOC);

            if ($donneesImageExistante) {
                $requeteModificationImage = "
                    UPDATE cobrec1._image
                    SET i_lien = ?, i_title = ?, i_alt = ?
                    WHERE id_image = ?
                ";
                $requetePrepareeModification = $connexionBaseDeDonnees->prepare($requeteModificationImage);
                $requetePrepareeModification->execute([
                    $cheminLienImage,
                    $titreImage,
                    $texteAlternatifImage,
                    $donneesImageExistante['id_image']
                ]);
            } else {
                $requeteInsertionNouvelleImage = "
                    INSERT INTO cobrec1._image (i_lien, i_title, i_alt)
                    VALUES (?, ?, ?)
                    RETURNING id_image
                ";
                $requetePrepareeInsertion = $connexionBaseDeDonnees->prepare($requeteInsertionNouvelleImage);
                $requetePrepareeInsertion->execute([$cheminLienImage, $titreImage, $texteAlternatifImage]);
                $identifiantNouvelleImage = $requetePrepareeInsertion->fetchColumn();

                $requeteLiaisonImageCompte = "INSERT INTO cobrec1._represente_compte (id_image, id_compte) VALUES (?, ?)";
                $requetePrepareeLiaison = $connexionBaseDeDonnees->prepare($requeteLiaisonImageCompte);
                $requetePrepareeLiaison->execute([$identifiantNouvelleImage, $identifiantCompte]);
            }
        }

        return ['success' => true, 'message' => "Profil mis à jour avec succès."];
    } catch (Exception $erreurException) {
        return ['success' => false, 'message' => "Erreur lors de la mise à jour" ];//. $erreurException->getMessage()];
    }
}

//fonction pour modifier le mot de passe du compte
function modifierMotDePasseCompte(
    $connexionBaseDeDonnees,
    $identifiantCompte,
    $motDePasseActuel,
    $nouveauMotDePasse,
    $confirmationNouveauMotDePasse
) {
    try {
        //récupérer le mot de passe actuellement enregistré
        $requeteRecuperationMotDePasse = "SELECT mdp FROM cobrec1._compte WHERE id_compte = ?";
        $requetePrepareeRecuperation = $connexionBaseDeDonnees->prepare($requeteRecuperationMotDePasse);
        $requetePrepareeRecuperation->execute([$identifiantCompte]);
        $motDePasseStockeHashe = $requetePrepareeRecuperation->fetchColumn();

        //vérifier que le compte existe
        if ($motDePasseStockeHashe === false) {
            return ['success' => false, 'message' => "Compte introuvable."];
        }

        //vérifier que le mot de passe actuel correspond
        if ($motDePasseActuel != $motDePasseStockeHashe) {
            return ['success' => false, 'message' => "Mot de passe actuel incorrect."];
        }

        //vérifier que la confirmation correspond au nouveau mot de passe
        if ($nouveauMotDePasse !== $confirmationNouveauMotDePasse) {
            return ['success' => false, 'message' => "Les mots de passe ne correspondent pas."];
        }

        //validation du format du nouveau mot de passe
        if (!validerFormatMotDePasse($nouveauMotDePasse)) {
            return ['success' => false, 'message' => "Le mot de passe doit contenir entre 8 et 16 caractères, au moins une majuscule, une minuscule et un caractère spécial."];
        }

        //mettre à jour le nouveau mot de passe
        $nouveauMotDePasseHashe = $nouveauMotDePasse;
        $requeteMiseAJourMotDePasse = "UPDATE cobrec1._compte SET mdp = ? WHERE id_compte = ?";
        $requetePrepareeMiseAJour = $connexionBaseDeDonnees->prepare($requeteMiseAJourMotDePasse);
        $requetePrepareeMiseAJour->execute([$nouveauMotDePasseHashe, $identifiantCompte]);

        return ['success' => true, 'message' => "Mot de passe modifié avec succès."];
    } catch (Exception $erreurException) {
        return [
            'success' => false,
            'message' => "Erreur lors du changement de mot de passe"// . $erreurException->getMessage()
        ];
    }
}

//vérifier si le client est connecté via la session
if (!isset($_SESSION['idClient'])) {
    header("Location: /pages/connexionClient/index.php");
    exit;
}

//récupérer l'identifiant du client depuis la session
$identifiantClientConnecte = (int) $_SESSION['idClient'];

//gestion de la déconnexion
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: /index.php');
    exit;
}

//initialiser la variable pour les messages d'erreur
$messageErreur = null;

//récupérer l'identifiant du compte associé au client
$identifiantCompteClient = recupererIdentifiantCompteClient($connexionBaseDeDonnees, $identifiantClientConnecte);

//vérifier que le compte existe bien
if ($identifiantCompteClient === null) {
    die("Compte introuvable pour le client : " . htmlspecialchars((string) $identifiantClientConnecte));
}

//traitement des formulaires soumis en POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //mise à jour des informations personnelles (avec image optionnelle)
    if (isset($_POST['update_info'])) {
        //récupérer et sécuriser les données du formulaire
        $nomFamilleSaisi = htmlspecialchars($_POST['nom'] ?? '');
        $prenomClientSaisi = htmlspecialchars($_POST['prenom'] ?? '');
        $pseudonymeClientSaisi = htmlspecialchars($_POST['pseudo'] ?? '');
        $adresseEmailSaisie = htmlspecialchars($_POST['email'] ?? '');
        $numeroTelephoneSaisi = htmlspecialchars($_POST['telephone'] ?? '');
        $numeroTelephoneSaisi = str_replace(' ', '', $numeroTelephoneSaisi);

        //récupération des données de l'image si fournies
        $cheminLienImageSaisi = !empty($_POST['lien_image']) ? htmlspecialchars($_POST['lien_image']) : null;

        //générer le titre et l'alt de l'image avec le prénom du client
        $titreImageGenere = "Avatar " . $prenomClientSaisi;
        $texteAlternatifImageGenere = "Photo de profil " . $prenomClientSaisi;

        //appeler la fonction de mise à jour du profil
        $resultatModificationProfil = mettreAJourProfilCompletClient(
            $connexionBaseDeDonnees,
            $identifiantClientConnecte,
            $identifiantCompteClient,
            $nomFamilleSaisi,
            $prenomClientSaisi,
            $pseudonymeClientSaisi,
            $adresseEmailSaisie,
            $numeroTelephoneSaisi,
            $cheminLienImageSaisi,
            $titreImageGenere,
            $texteAlternatifImageGenere
        );

        //rediriger avec un message de succès ou afficher une erreur
        if ($resultatModificationProfil['success']) {
            header('Location: index.php?success=info_updated');
            exit;
        } else {
            $messageErreur = $resultatModificationProfil['message'];
        }
    }

    //changement du mot de passe
    if (isset($_POST['change_password'])) {
        //récupérer les mots de passe saisis
        $motDePasseActuelSaisi = $_POST['current_password'] ?? '';
        $nouveauMotDePasseSaisi = $_POST['new_password'] ?? '';
        $confirmationMotDePasseSaisie = $_POST['confirm_password'] ?? '';

        //appeler la fonction de modification du mot de passe
        $resultatModificationMotDePasse = modifierMotDePasseCompte(
            $connexionBaseDeDonnees,
            $identifiantCompteClient,
            $motDePasseActuelSaisi,
            $nouveauMotDePasseSaisi,
            $confirmationMotDePasseSaisie
        );

        //rediriger avec un message de succès ou afficher une erreur
        if ($resultatModificationMotDePasse['success']) {
            header('Location: index.php?success=password_changed');
            exit;
        } else {
            $messageErreur = $resultatModificationMotDePasse['message'];
        }
    }
}

//chargement des données pour l'affichage de la page
$donneesInformationsClient = recupererInformationsCompletesClient($connexionBaseDeDonnees, $identifiantClientConnecte);

//vérifier que le client existe
if (!$donneesInformationsClient) {
    die("Client introuvable avec l'ID : " . htmlspecialchars((string) $identifiantClientConnecte));
}

//récupérer la liste des adresses du client
$listeAdressesClient = recupererToutesAdressesClient($connexionBaseDeDonnees, $identifiantCompteClient);

//récupérer l'historique des commandes récentes
$listeCommandesRecentes = recupererHistoriqueCommandesRecentes($connexionBaseDeDonnees, $identifiantClientConnecte);

//récupérer l'image de profil du compte
$donneesImageProfilCompte = recupererImageProfilCompte($connexionBaseDeDonnees, $identifiantCompteClient);

//requête supplémentaire pour vérifier la présence d'une image
$requeteSQLVerificationImagePresente = "
    SELECT i.i_lien
    FROM cobrec1._image i
    INNER JOIN cobrec1._represente_compte rc ON i.id_image = rc.id_image
    WHERE rc.id_compte = ?
";
$requetePrepareeVerificationImage = $connexionBaseDeDonnees->prepare($requeteSQLVerificationImagePresente);
$requetePrepareeVerificationImage->execute([$identifiantCompteClient]);
$donneesImagePresente = $requetePrepareeVerificationImage->fetch(PDO::FETCH_ASSOC) ?: null;
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - Alizon</title>
    <link rel="stylesheet" href="/styles/ProfilClient/style.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" type="image/png" href="../../img/favicon.svg">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
</head>

<body>

    <?php
    //inclure l'en-tête du site
    include __DIR__ . '/../../partials/header.html';
    ?>

    <main>
        <div>
            <button onclick="history.back()">
                ← Retour
            </button>

            <h1>Mon Profil</h1>

            <?php if (isset($messageErreur)): ?>
                <!-- Afficher le message d'erreur si présent -->
                <div class="error-message">
                    <?php echo htmlspecialchars($messageErreur); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
                <!-- Afficher le message de succès si présent -->
                <div class="success-message">
                    <?php
                    //afficher le message correspondant au type de succès
                    if ($_GET['success'] === 'info_updated')
                        echo "Informations mises à jour avec succès.";
                    if ($_GET['success'] === 'password_changed')
                        echo "Mot de passe changé avec succès.";
                    ?>
                </div>
            <?php endif; ?>

            <!-- Section : Informations personnelles -->
            <section>
                <h2>Informations personnelles</h2>

                <article>
                    <header>
                        <div>
                            <span>Profil</span>
                            <strong>Vos informations</strong>
                        </div>
                        <span data-type="profil">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </span>
                    </header>

                    <main>
                        <!-- Conteneur de l'image de profil -->
                        <div class="profile-image-container">
                            <?php if ($donneesImageProfilCompte['i_lien'] !== NULL && $donneesImageProfilCompte['i_lien'] !== ''): ?>
                                <!-- Afficher l'image de profil si elle existe -->
                                <img src="<?php echo htmlspecialchars($donneesImageProfilCompte['i_lien']); ?>"
                                    alt="<?php echo htmlspecialchars($donneesImageProfilCompte['i_alt'] ?? 'Photo de profil'); ?>"
                                    title="<?php echo htmlspecialchars($donneesImageProfilCompte['i_title'] ?? ''); ?>"
                                    class="profile-image" id="current-profile-image">
                            <?php else: ?>
                                <!-- Afficher un placeholder si aucune image -->
                                <div class="profile-image-placeholder">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="#7171A3">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                </div>
                                <p class="no-profile-image">Aucune photo de profil</p>
                            <?php endif; ?>
                        </div>

                        <!-- Formulaire de mise à jour du profil -->
                        <form method="POST" id="profile-form">
                            <!-- Zone de drag and drop pour l'image -->
                            <div id="drop-zone">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="48" height="48"
                                    style="margin-bottom: 10px;">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="17 8 12 3 7 8"></polyline>
                                    <line x1="12" y1="3" x2="12" y2="15"></line>
                                </svg>
                                <p><strong>Glissez-déposez votre image ici</strong></p>
                                <p style="font-size: 12px; margin-top: 5px;">ou cliquez pour sélectionner un fichier</p>
                                <input type="file" id="file-input" accept="image/*" style="display: none;">
                            </div>

                            <div id="preview-zone"></div>
                            <div id="upload-status" class="upload-status"></div>

                            <!-- Champ caché pour stocker le chemin de l'image -->
                            <input type="hidden" name="lien_image" id="lien_image"
                                value="<?php echo htmlspecialchars($donneesImageProfilCompte['i_lien'] ?? ''); ?>">

                            <!-- Champs du formulaire -->
                            <div>
                                <label>
                                    <span>Nom</span>
                                    <input type="text" name="nom" id="nom"
                                        value="<?php echo htmlspecialchars($donneesInformationsClient['c_nom'] ?? ''); ?>"
                                        required>
                                </label>
                            </div>

                            <div>
                                <label>
                                    <span>Prénom</span>
                                    <input type="text" name="prenom" id="prenom"
                                        value="<?php echo htmlspecialchars($donneesInformationsClient['c_prenom'] ?? ''); ?>"
                                        required>
                                </label>
                            </div>

                            <div>
                                <label>
                                    <span>Pseudo</span>
                                    <input type="text" name="pseudo"
                                        value="<?php echo htmlspecialchars($donneesInformationsClient['c_pseudo'] ?? ''); ?>"
                                        required>
                                </label>
                            </div>

                            <div>
                                <label>
                                    <span>Email</span>
                                    <input type="email" name="email"
                                        value="<?php echo htmlspecialchars($donneesInformationsClient['email'] ?? ''); ?>"
                                        required>
                                </label>
                            </div>

                            <div>
                                <label>
                                    <span>Téléphone</span>
                                    <input type="tel" name="telephone"
                                        value="<?php echo htmlspecialchars($donneesInformationsClient['num_telephone'] ?? ''); ?>">
                                </label>
                            </div>

                            <button type="submit" name="update_info">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                    <polyline points="7 3 7 8 15 8"></polyline>
                                </svg>
                                Enregistrer les modifications
                            </button>
                        </form>
                    </main>
                </article>
            </section>

            <!-- Section : Mes adresses -->
            <section>
                <div>
                    <h2>Mes adresses</h2>
                    <button type="button" onclick="location.href='ajouter-adresse.php'">
                        + Ajouter une adresse
                    </button>
                </div>

                <?php if (empty($listeAdressesClient)): ?>
                    <!-- Afficher un message si aucune adresse -->
                    <p>Aucune adresse enregistrée</p>
                <?php else: ?>
                    <!-- Boucler sur chaque adresse du client -->
                    <?php foreach ($listeAdressesClient as $adresseIndividuelle): ?>
                        <article>
                            <header>
                                <div>
                                    <span>Adresse</span>
                                    <strong>#<?php echo $adresseIndividuelle['id_adresse']; ?></strong>
                                </div>
                                <span data-type="adresse">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                        <circle cx="12" cy="10" r="3"></circle>
                                    </svg>
                                </span>
                            </header>

                            <main>
                                <div>
                                    <p>
                                        <strong><?php echo htmlspecialchars($adresseIndividuelle['a_adresse']); ?></strong><br>
                                        <?php echo htmlspecialchars($adresseIndividuelle['a_code_postal']); ?>
                                        <?php echo htmlspecialchars($adresseIndividuelle['a_ville']); ?>
                                        <?php if (!empty($adresseIndividuelle['a_complement'])): ?>
                                            <br><em><?php echo htmlspecialchars($adresseIndividuelle['a_complement']); ?></em>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </main>

                            <footer>
                                <button type="button"
                                    onclick="location.href='modifier-adresse.php?id=<?php echo $adresseIndividuelle['id_adresse']; ?>'">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                    Modifier
                                </button>
                                <button type="button"
                                    onclick="if(confirm('Supprimer cette adresse ?')) location.href='supprimer-adresse.php?id=<?php echo $adresseIndividuelle['id_adresse']; ?>'">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path
                                            d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2">
                                        </path>
                                    </svg>
                                    Supprimer
                                </button>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <!-- Section : Mes dernières commandes -->
            <section>
                <h2>Mes dernières commandes</h2>

                <?php if (empty($listeCommandesRecentes)): ?>
                    <!-- Afficher un message si aucune commande -->
                    <p>Aucune commande effectuée</p>
                <?php else: ?>
                    <!-- Boucler sur chaque commande du client -->
                    <?php foreach ($listeCommandesRecentes as $commandeIndividuelle): ?>
                        <article>
                            <header>
                                <div>
                                    <span>Commande</span>
                                    <strong>#<?php echo htmlspecialchars($commandeIndividuelle['id_panier']); ?></strong>
                                </div>
                                <span
                                    data-statut="<?php echo strtolower(str_replace(' ', '-', $commandeIndividuelle['statut'])); ?>">
                                    <?php echo htmlspecialchars($commandeIndividuelle['statut']); ?>
                                </span>
                            </header>

                            <main>
                                <div>
                                    <div>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                            <line x1="16" y1="2" x2="16" y2="6"></line>
                                            <line x1="8" y1="2" x2="8" y2="6"></line>
                                            <line x1="3" y1="10" x2="21" y2="10"></line>
                                        </svg>
                                        <span><?php echo date('d/m/Y', strtotime($commandeIndividuelle['timestamp_commande'])); ?></span>
                                    </div>
                                    <div>
                                        <em><?php echo number_format($commandeIndividuelle['montant_total'], 2, ',', ' '); ?>
                                            €</em>
                                    </div>
                                </div>
                            </main>

                            <footer>
                                <button type="button"
                                    onclick="location.href='suivi-commande.php?id=<?php echo $commandeIndividuelle['id_panier']; ?>'">
                                    Voir les détails
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                        <polyline points="12 5 19 12 12 19"></polyline>
                                    </svg>
                                </button>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <!-- Section : Sécurité -->
            <section>
                <h2>Sécurité</h2>

                <article>
                    <header>
                        <div>
                            <span>Mot de passe</span>
                            <strong>Modifier votre mot de passe</strong>
                        </div>
                        <span data-type="securite">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </span>
                    </header>

                    <main>
                        <!-- Formulaire de changement de mot de passe -->
                        <form method="POST">
                            <div>
                                <label>
                                    <span>Mot de passe actuel</span>
                                    <input type="password" name="current_password" required>
                                </label>
                            </div>

                            <div>
                                <label>
                                    <span>Nouveau mot de passe</span>
                                    <input type="password" name="new_password" required>
                                </label>
                            </div>

                            <div>
                                <label>
                                    <span>Confirmer le mot de passe</span>
                                    <input type="password" name="confirm_password" required>
                                </label>
                            </div>

                            <button type="submit" name="change_password"
                                onclick="return confirm('Confirmer le changement de mot de passe ?');">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                    <polyline points="7 3 7 8 15 8"></polyline>
                                </svg>
                                Changer le mot de passe
                            </button>
                        </form>
                    </main>
                </article>
            </section>

            <!-- Formulaire de déconnexion -->
            <form method="get" class="logout-form">
                <button type="submit" name="action" value="logout">
                    Déconnexion
                </button>
            </form>

        </div>
    </main>

    <?php
    //inclure le pied de page du site
    include __DIR__ . '/../../partials/footer.html';
    ?>

    <script>
        //gestion des onglets
        const boutonsOnglets = document.querySelectorAll('.tab-btn');
        const contenuOnglets = document.querySelectorAll('.tab-content');

        //ajouter un événement sur chaque bouton d'onglet
        boutonsOnglets.forEach(boutonOnglet => {
            boutonOnglet.addEventListener('click', () => {
                //récupérer le nom de l'onglet depuis l'attribut data-tab
                const nomOnglet = boutonOnglet.getAttribute('data-tab');

                //désactiver tous les onglets
                boutonsOnglets.forEach(bouton => bouton.classList.remove('active'));
                contenuOnglets.forEach(contenu => contenu.classList.remove('active'));

                //activer l'onglet sélectionné
                boutonOnglet.classList.add('active');
                document.getElementById(nomOnglet + '-tab').classList.add('active');
            });
        });

        //fonction pour prévisualiser l'image depuis une URL
        function previsualiserImageDepuisURL() {
            //récupérer les éléments du DOM
            const champSaisieURL = document.getElementById('url_image_input');
            const champCacheLienImage = document.getElementById('lien_image');
            const elementImageActuelle = document.getElementById('current-profile-image');
            const urlImageSaisie = champSaisieURL.value.trim();

            //vérifier si une URL a été saisie
            if (!urlImageSaisie) {
                alert('Veuillez saisir une URL d\'image');
                return;
            }

            //vérifier si l'URL est valide
            try {
                new URL(urlImageSaisie);
            } catch (erreur) {
                alert('URL invalide. Veuillez saisir une URL complète (ex: https://exemple.com/image.jpg)');
                return;
            }

            //tester si l'image se charge correctement
            const imageTest = new Image();

            //si l'image se charge avec succès
            imageTest.onload = function () {
                //mettre à jour l'affichage de l'image
                if (elementImageActuelle) {
                    elementImageActuelle.src = urlImageSaisie;
                } else {
                    //créer l'image si elle n'existe pas
                    const conteneurImage = document.querySelector('.profile-image-container');
                    conteneurImage.innerHTML = `<img src="${urlImageSaisie}" alt="Photo de profil" class="profile-image" id="current-profile-image">`;
                }

                //mettre à jour le champ caché
                champCacheLienImage.value = urlImageSaisie;

                alert('Image chargée avec succès ! N\'oubliez pas d\'enregistrer vos modifications.');
            };

            //si l'image ne se charge pas
            imageTest.onerror = function () {
                alert('Impossible de charger l\'image depuis cette URL. Vérifiez que l\'URL est correcte et accessible.');
            };

            imageTest.src = urlImageSaisie;
        }

        //déclaration des variables pour le drag and drop
        const zoneDepotFichier = document.getElementById('drop-zone');
        const champSelectionFichier = document.getElementById('file-input');
        const zoneApercuImage = document.getElementById('preview-zone');
        const zoneStatutUpload = document.getElementById('upload-status');
        const champCacheLienImage = document.getElementById('lien_image');
        const identifiantClientConnecte = <?php echo $identifiantClientConnecte; ?>;

        //clic sur la zone pour ouvrir le sélecteur de fichier
        zoneDepotFichier.addEventListener('click', () => champSelectionFichier.click());

        //empêcher le comportement par défaut du navigateur pour le drag and drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(nomEvenement => {
            zoneDepotFichier.addEventListener(nomEvenement, empecherComportementParDefaut, false);
            document.body.addEventListener(nomEvenement, empecherComportementParDefaut, false);
        });

        //fonction pour empêcher le comportement par défaut
        function empecherComportementParDefaut(evenement) {
            evenement.preventDefault();
            evenement.stopPropagation();
        }

        //effet visuel lors du drag
        ['dragenter', 'dragover'].forEach(nomEvenement => {
            zoneDepotFichier.addEventListener(nomEvenement, () => {
                zoneDepotFichier.classList.add('drag-over');
            });
        });

        ['dragleave', 'drop'].forEach(nomEvenement => {
            zoneDepotFichier.addEventListener(nomEvenement, () => {
                zoneDepotFichier.classList.remove('drag-over');
            });
        });

        //gestion du drop de fichier
        zoneDepotFichier.addEventListener('drop', gererDepotFichier);
        champSelectionFichier.addEventListener('change', gererSelectionFichier);

        //fonction pour gérer le dépôt de fichier
        function gererDepotFichier(evenement) {
            //récupérer les fichiers déposés
            const fichiersDePoses = evenement.dataTransfer.files;
            if (fichiersDePoses.length > 0) {
                envoyerFichierVersServeur(fichiersDePoses[0]);
            }
        }

        //fonction pour gérer la sélection de fichier
        function gererSelectionFichier(evenement) {
            //récupérer les fichiers sélectionnés
            const fichiersSelectionnes = evenement.target.files;
            if (fichiersSelectionnes.length > 0) {
                envoyerFichierVersServeur(fichiersSelectionnes[0]);
            }
        }

        //fonction pour envoyer un fichier vers le serveur
        function envoyerFichierVersServeur(fichierImage) {
            //vérifier que c'est bien une image
            if (!fichierImage.type.startsWith('image/')) {
                afficherMessageStatut('Veuillez sélectionner une image valide (JPEG, PNG, GIF, WebP)', 'error');
                return;
            }

            //vérifier la taille (max 5MB)
            const tailleLimiteMegaOctets = 5 * 1024 * 1024;
            if (fichierImage.size > tailleLimiteMegaOctets) {
                afficherMessageStatut('L\'image est trop volumineuse (max 5 MB)', 'error');
                return;
            }

            //créer un objet FormData pour envoyer le fichier
            const donneesFormulaireUpload = new FormData();
            donneesFormulaireUpload.append('image', fichierImage);
            donneesFormulaireUpload.append('id_client', identifiantClientConnecte);

            //afficher un aperçu de l'image
            const lecteurFichier = new FileReader();
            lecteurFichier.onload = (evenement) => {
                //afficher l'aperçu dans la zone dédiée
                zoneApercuImage.innerHTML = `<img src="${evenement.target.result}" alt="Aperçu">`;

                //mettre à jour l'image de profil actuelle si elle existe
                const elementImageActuelle = document.getElementById('current-profile-image');
                if (elementImageActuelle) {
                    elementImageActuelle.src = evenement.target.result;
                }
            };
            lecteurFichier.readAsDataURL(fichierImage);

            //afficher le message d'upload en cours
            afficherMessageStatut('Upload en cours...', 'success');

            //envoyer le fichier au serveur via fetch
            fetch('upload_image.php', {
                method: 'POST',
                body: donneesFormulaireUpload
            })
                .then(reponse => reponse.json())
                .then(donneesReponse => {
                    if (donneesReponse.success) {
                        //mettre à jour le champ caché avec le chemin local sous /img/clients/[i_alt].[image_format]
                        champCacheLienImage.value = donneesReponse.path;
                        afficherMessageStatut('Image uploadée avec succès ! N\'oubliez pas d\'enregistrer vos modifications.', 'success');
                    } else {
                        afficherMessageStatut('Erreur: ' + donneesReponse.message, 'error');
                    }
                })
                .catch(erreur => {
                    console.error('Erreur:', erreur);
                    afficherMessageStatut('Erreur lors de l\'upload', 'error');
                });
        }

        //fonction pour afficher un message de statut
        function afficherMessageStatut(messageTexte, typeMessage) {
            //afficher le message dans la zone de statut
            zoneStatutUpload.textContent = messageTexte;
            zoneStatutUpload.className = 'upload-status ' + typeMessage;

            //masquer le message après 5 secondes si succès
            if (typeMessage === 'success') {
                setTimeout(() => {
                    zoneStatutUpload.style.display = 'none';
                }, 5000);
            }
        }
    </script>

</body>

</html>