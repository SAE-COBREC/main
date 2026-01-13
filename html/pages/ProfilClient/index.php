<?php
//démarrer la session pour récupérer les informations du client
session_start();

//inclure le fichier de configuration pour la connexion à la base de données
include '../../selectBDD.php';

//inclure les fonctions utilitaires
include '../fonctions.php';

//récupérer la connexion PDO depuis le fichier de configuration
$connexionBaseDeDonnees = $pdo;

//définir le schéma de la base de données à utiliser
$connexionBaseDeDonnees->exec("SET search_path TO cobrec1");

//vérifier si le client est connecté via la session
if (!isset($_SESSION['idClient'])) {
    $url = '/pages/connexionClient/index.php';
    echo '<!doctype html><html><head><meta http-equiv="refresh" content="0;url=' . $url . '">';
    exit;
}

//récupérer l'identifiant du client depuis la session
$identifiantClientConnecte = (int) $_SESSION['idClient'];

//gestion de la déconnexion
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    $url = '/index.php';
    echo '<!doctype html><html><head><meta http-equiv="refresh" content="0;url=' . $url . '">';
    exit;
}

//initialiser la variable pour les messages d'erreur
$messageErreur = null;
$messageSucces = null;

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
        $civilteSaisie = htmlspecialchars($_POST['civilite'] ?? '');
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
            $civilteSaisie,
            $cheminLienImageSaisi,
            $titreImageGenere,
            $texteAlternatifImageGenere
        );

        //rediriger avec un message de succès ou afficher une erreur
        if ($resultatModificationProfil['success']) {
            $messageSucces = "Vos informations ont été mises à jour avec succès";
            $donneesInformationsClient = recupererInformationsCompletesClient($connexionBaseDeDonnees, $identifiantClientConnecte);
        } else {
            $messageErreur = $resultatModificationProfil['message'];
        }
    }

    //changement du mot de passe
    if (isset($_POST['change_password'])) {
        //récupérer les mots de passe saisis
        $motDePasseActuelSaisi = $_POST['actuel_password'] ?? '';
        $nouveauMotDePasseSaisi = $_POST['nouveau_password'] ?? '';
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
            $messageSucces = "Votre mot de passe a été modifié avec succès";
        } else {
            $messageErreur = $resultatModificationMotDePasse['message'];
        }
    }

    //mise à jour d'une adresse
    if (isset($_POST['update_address'])) {
        $idAdresse = (int) $_POST['id_adresse'];
        $numSaisi = (int) ($_POST['num'] ?? 1);
        $adresseSaisie = htmlspecialchars($_POST['adresse'] ?? '');
        $villeSaisie = htmlspecialchars($_POST['ville'] ?? '');
        $codePostalSaisi = htmlspecialchars($_POST['code_postal'] ?? '');
        $complementSaisi = htmlspecialchars($_POST['complement'] ?? '');

        $resultatMiseAJourAdresse = mettreAJourAdresse(
            $connexionBaseDeDonnees,
            $idAdresse,
            $identifiantCompteClient,
            $numSaisi,
            $adresseSaisie,
            $villeSaisie,
            $codePostalSaisi,
            $complementSaisi
        );

        if ($resultatMiseAJourAdresse['success']) {
    $messageSucces = "Votre adresse a été modifiée avec succès";
    $listeAdressesClient = recupererToutesAdressesClient($connexionBaseDeDonnees, $identifiantCompteClient);
} else {
    $messageErreur = $resultatMiseAJourAdresse['message'];
}

    }

    //suppression d'une adresse
    if (isset($_POST['delete_address'])) {
        $idAdresse = (int) $_POST['id_adresse'];

        //vérifier d'abord le nombre d'adresses avant de supprimer
        $adressesActuelles = recupererToutesAdressesClient($connexionBaseDeDonnees, $identifiantCompteClient);
        $nombreAdresses = count($adressesActuelles);

        if ($nombreAdresses <= 1) {
            $messageErreur = "Vous ne pouvez pas supprimer votre dernière adresse. Vous devez avoir au moins une adresse enregistrée.";
        } else {
            $resultatSuppressionAdresse = supprimerAdresse($connexionBaseDeDonnees, $idAdresse, $identifiantCompteClient);

            if ($resultatSuppressionAdresse['success']) {
                $url = 'index.php?success=address_deleted';
                echo '<!doctype html><html><head><meta http-equiv="refresh" content="0;url=' . $url . '">';
                exit;
            } else {
                $messageErreur = $resultatSuppressionAdresse['message'];
            }
        }
    }

    //traitement POST pour l'ajout d'une nouvelle adresse
    if (isset($_POST['add_address'])) {
        $numSaisi = (int) ($_POST['num'] ?? 1);
        $adresseSaisie = htmlspecialchars($_POST['adresse'] ?? '');
        $villeSaisie = htmlspecialchars($_POST['ville'] ?? '');
        $codePostalSaisi = htmlspecialchars($_POST['code_postal'] ?? '');
        $complementSaisi = htmlspecialchars($_POST['complement'] ?? '');

        $resultatAjoutAdresse = ajouterNouvelleAdresse(
            $connexionBaseDeDonnees,
            $identifiantCompteClient,
            $numSaisi,
            $adresseSaisie,
            $villeSaisie,
            $codePostalSaisi,
            $complementSaisi
        );

        if ($resultatAjoutAdresse['success']) {
            $messageSucces = "Votre nouvelle adresse a été ajoutée avec succès";
            //recharger les adresses
            $listeAdressesClient = recupererToutesAdressesClient($connexionBaseDeDonnees, $identifiantCompteClient);
        } else {
            $messageErreur = $resultatAjoutAdresse['message'];
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
    <link rel="icon" type="image/png" href="../../img/favicon.svg">
    <link rel="stylesheet" href="/styles/Header/stylesHeader.css">
    <link rel="stylesheet" href="/styles/Footer/stylesFooter.css">
</head>

<body>

    <?php
    //inclure l'en-tête du site
    include __DIR__ . '/../../partials/header.php';
    ?>

    <main>
        <div>
            <button class="back-button" onclick="window.location.href='/index.php';">
                ← Retour
            </button>

            <h1>Mon Profil</h1>

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
                        <div class="profile-image-container">
                            <?php if ($donneesImageProfilCompte !== null && $donneesImageProfilCompte['i_lien'] !== NULL && $donneesImageProfilCompte['i_lien'] !== ''): ?>
                            <img src="<?php echo htmlspecialchars($donneesImageProfilCompte['i_lien']); ?>"
                                alt="<?php echo htmlspecialchars($donneesImageProfilCompte['i_alt'] ?? 'Photo de profil'); ?>"
                                title="<?php echo htmlspecialchars($donneesImageProfilCompte['i_title'] ?? ''); ?>"
                                class="profile-image" id="current-profile-image">
                            <?php else: ?>
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

                            <input type="hidden" name="lien_image" id="lien_image"
                                value="<?php echo htmlspecialchars($donneesImageProfilCompte['i_lien'] ?? ''); ?>">

                            <div>
                                <label>
                                    <span>Nom</span>
                                    <input type="text" name="nom" id="nom"
                                        value="<?php echo htmlspecialchars($donneesInformationsClient['nom'] ?? ''); ?>"
                                        required>
                                </label>
                            </div>

                            <div>
                                <label>
                                    <span>Prénom</span>
                                    <input type="text" name="prenom" id="prenom"
                                        value="<?php echo htmlspecialchars($donneesInformationsClient['prenom'] ?? ''); ?>"
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
                                    <span>Civilité</span>
                                    <select name="civilite" required>
                                        <option value="">-- Sélectionnez --</option>
                                        <option value="M."
                                            <?php echo ($donneesInformationsClient['civilite'] ?? '') === 'M.' ? 'selected' : ''; ?>>
                                            M.</option>
                                        <option value="Mme"
                                            <?php echo ($donneesInformationsClient['civilite'] ?? '') === 'Mme' ? 'selected' : ''; ?>>
                                            Mme</option>
                                        <option value="Inconnu"
                                            <?php echo ($donneesInformationsClient['civilite'] ?? '') === 'Inconnu' ? 'selected' : ''; ?>>
                                            Inconnu</option>
                                    </select>
                                </label>
                            </div>


                            <div>
                                <label>
                                    <span>Téléphone</span>
                                    <input type="tel" name="telephone" inputmode="numeric"
                                        pattern="(0|\\+33|0033)[1-9][0-9]{8}" maxlength="10"
                                        placeholder="ex: 0615482649" required
                                        title="Le numéro de télephone doit contenir 10 chiffres"
                                        oninput="this.value=this.value.replace(/\D/g,'').slice(0,10)"
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
                    <button type="button" onclick="ouvrirModalAjoutAdresse()">
                        + Ajouter une adresse
                    </button>
                </div>

                <?php if (empty($listeAdressesClient)): ?>
                <p>Aucune adresse enregistrée</p>
                <?php else: ?>
                <?php foreach ($listeAdressesClient as $adresseIndividuelle): ?>
                <article>
                    <main>
                        <div>
                            <p>
                                <strong><?php echo htmlspecialchars($adresseIndividuelle['a_numero']) ; ?><?php echo ' ' ;?><?php echo htmlspecialchars($adresseIndividuelle['a_adresse']); ?></strong><br>
                                <?php echo htmlspecialchars($adresseIndividuelle['a_code_postal']); ?>
                                <?php echo htmlspecialchars($adresseIndividuelle['a_ville']); ?>
                                <?php if (!empty($adresseIndividuelle['a_complement'])): ?>
                                <br><em><?php echo htmlspecialchars($adresseIndividuelle['a_complement']); ?></em>
                                <?php endif; ?>
                            </p>
                        </div>
                    </main>

                    <footer>
                        <button type="button" onclick="ouvrirModalModificationAdresse(
    <?php echo $adresseIndividuelle['id_adresse']; ?>, 
    '<?php echo htmlspecialchars($adresseIndividuelle['a_numero'] ?? '', ENT_QUOTES); ?>', 
    '<?php echo htmlspecialchars($adresseIndividuelle['a_adresse'], ENT_QUOTES); ?>', 
    '<?php echo htmlspecialchars($adresseIndividuelle['a_pays'] ?? 'France', ENT_QUOTES); ?>', 
    '<?php echo htmlspecialchars($adresseIndividuelle['a_ville'], ENT_QUOTES); ?>', 
    '<?php echo htmlspecialchars($adresseIndividuelle['a_code_postal'], ENT_QUOTES); ?>', 
    '<?php echo htmlspecialchars($adresseIndividuelle['a_complement'], ENT_QUOTES); ?>'
)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                            Modifier
                        </button>

                        <form method="post" style="display:inline;"
                            onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette adresse ?');">
                            <input type="hidden" name="id_adresse"
                                value="<?php echo $adresseIndividuelle['id_adresse']; ?>">
                            <button type="submit" name="delete_address">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path
                                        d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2">
                                    </path>
                                </svg>
                                Supprimer
                            </button>
                        </form>
                    </footer>
                </article>
                <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <!-- Section : Mes dernières commandes -->
            <section>
                <h2>Mes dernières commandes</h2>

                <?php if (empty($listeCommandesRecentes)): ?>
                <p>Aucune commande effectuée</p>
                <?php else: ?>
                <?php foreach ($listeCommandesRecentes as $commandeIndividuelle): ?>
                <article>
                    <header>
                        <div>
                            <span>Commande</span>
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

            <!-- Section Sécurité -->
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
                                    <input type="password" name="actuel_password" required>
                                </label>
                            </div>
                            <div>
                                <label>
                                    <span>Nouveau mot de passe</span>
                                    <input type="password" name="nouveau_password"
                                        pattern="^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[A-Za-z0-9.!@#$%^&*]).{8,16}$"
                                        title="Le mot de passe doit contenir entre 8 et 16 caractères, au moins une majuscule, une minuscule et un caractère parmi : . ! @ # $ % ^ & *"
                                        required>
                                </label>
                            </div>
                            <div>
                                <label>
                                    <span>Confirmer le mot de passe</span>
                                    <input type="password" name="confirm_password"
                                        pattern="^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[A-Za-z0-9.!@#$%^&*]).{8,16}$"
                                        title="Le mot de passe doit contenir entre 8 et 16 caractères, au moins une majuscule, une minuscule et un caractère parmi : . ! @ # $ % ^ & *"
                                        required>
                                </label>
                            </div>
                            <button type="submit" name="change_password"
                                onclick="return confirm('Confirmer le changement de mot de passe ?')">
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

    <!-- modif une adresse -->
    <div id="modalModificationAdresse">
        <div>
            <h2>Modifier l'adresse</h2>
            <form method="post" id="formulaireModificationAdresse">
                <input type="hidden" name="id_adresse" id="modification_id_adresse">

                <label>
                    <span>Numéro</span>
                    <input type="text" name="num" id="modification_num" inputmode="numeric" pattern="[0-9]+"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
                </label>


                <label>
                    <span>Adresse</span>
                    <input type="text" name="adresse" id="modification_adresse" required>
                </label>

                <label>
                    <span>Complément (optionnel)</span>
                    <input type="text" name="complement" id="modification_complement">
                </label>

                <label>
                    <span>Pays</span>
                    <input type="text" name="pays" id="modification_pays" required>
                </label>

                <label>
                    <span>Ville</span>
                    <input type="text" name="ville" id="modification_ville" required>
                </label>

                <label>
                    <span>Code postal</span>
                    <input type="text" name="code_postal" id="modification_code_postal" inputmode="numeric"
                        pattern="^((0[1-9])|([1-8][0-9])|(9[0-7])|(2A)|(2B)) *([0-9]{3})?$" maxlength="5"
                        placeholder="ex: 22300" required>
                </label>



                <button type="submit" name="update_address">Mettre à jour</button>
                <button type="button" onclick="fermerModalModificationAdresse()">Annuler</button>
            </form>
        </div>
    </div>

    <!-- ajouter une adresse -->
    <div id="modalAjoutAdresse">
        <div>
            <h2>Ajouter une adresse</h2>
            <form method="post" id="formulaireAjoutAdresse">

                <label>
                    <span>Numéro</span>
                    <input type="text" name="num" id="ajout_num" inputmode="numeric" pattern="[0-9]+"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
                </label>

                <label>
                    <span>Adresse</span>
                    <input type="text" name="adresse" id="ajout_adresse" required>
                </label>

                <label>
                    <span>Complément (optionnel)</span>
                    <input type="text" name="complement" id="ajout_complement">
                </label>

                <label>
                    <span>Pays</span>
                    <input type="text" name="pays" id="ajout_pays" required>
                </label>

                <label>
                    <span>Ville</span>
                    <input type="text" name="ville" id="ajout_ville" required>
                </label>

                <label>
                    <span>Code postal</span>
                    <input type="text" name="code_postal" id="ajout_code_postal" inputmode="numeric"
                        pattern="^((0[1-9])|([1-8][0-9])|(9[0-7])|(2A)|(2B)) *([0-9]{3})?$" maxlength="5"
                        placeholder="ex: 22300" required>
                </label>



                <button type="submit" name="add_address">Ajouter</button>
                <button type="button" onclick="fermerModalAjoutAdresse()">Annuler</button>
            </form>
        </div>
    </div>

    <?php
    //inclure le pied de page du site
    include __DIR__ . '/../../partials/footer.html';
    include __DIR__ . '/../../partials/toast.html';
    ?>

    <script>
    //attend que la page HTML soit complètement chargée avant d'exécuter le code
    document.addEventListener('DOMContentLoaded', () => {
        //récupère le champ caché qui stockera l'URL de l'image
        const champCacheLienImage = document.getElementById('lien_image');
        //récupère l'ID du client connecté depuis PHP
        const identifiantClientConnecte = <?php echo $identifiantClientConnecte; ?>;


        //configure le système de drag & drop pour l'upload d'image
        configurerUploadImage(champCacheLienImage, identifiantClientConnecte);


        //affiche les notifications de succès ou d'erreur si elles existent
        <?php if (isset($messageSucces) && $messageSucces !== null): ?>
        //affiche une notification de succès
        notify(<?= json_encode($messageSucces) ?>, 'success');
        <?php endif; ?>


        <?php if (isset($messageErreur) && $messageErreur !== null): ?>
        //affiche une notification d'erreur
        notify(<?= json_encode($messageErreur) ?>, 'error');
        <?php endif; ?>
    });


    //fonction pour ouvrir le modal de modification d'adresse
    function ouvrirModalModificationAdresse(id, num, adresse, pays, ville, codePostal, complement) {
        //remplit le champ du numéro de rue
        document.getElementById('modification_num').value = num;
        //remplit le champ caché de l'ID de l'adresse
        document.getElementById('modification_id_adresse').value = id;
        //remplit le champ du pays
        document.getElementById('modification_pays').value = pays;
        //remplit le champ de l'adresse
        document.getElementById('modification_adresse').value = adresse;
        //remplit le champ de la ville
        document.getElementById('modification_ville').value = ville;
        //remplit le champ du code postal
        document.getElementById('modification_code_postal').value = codePostal;
        //remplit le champ du complément d'adresse
        document.getElementById('modification_complement').value = complement;
        //affiche le modal de modification
        document.getElementById('modalModificationAdresse').style.display = 'block';
    }


    //fonction pour fermer le modal de modification d'adresse
    function fermerModalModificationAdresse() {
        //cache le modal de modification
        document.getElementById('modalModificationAdresse').style.display = 'none';
    }


    //fonction pour ouvrir le modal d'ajout d'adresse
    function ouvrirModalAjoutAdresse() {
        //réinitialise le champ du numéro de rue
        document.getElementById('ajout_num').value = '';
        //réinitialise le champ de l'adresse
        document.getElementById('ajout_adresse').value = '';
        //réinitialise le champ du pays
        document.getElementById('ajout_pays').value = '';
        //réinitialise le champ de la ville
        document.getElementById('ajout_ville').value = '';
        //réinitialise le champ du code postal
        document.getElementById('ajout_code_postal').value = '';
        //réinitialise le champ du complément d'adresse
        document.getElementById('ajout_complement').value = '';
        //affiche le modal d'ajout
        document.getElementById('modalAjoutAdresse').style.display = 'block';
    }


    //fonction pour fermer le modal d'ajout d'adresse
    function fermerModalAjoutAdresse() {
        //cache le modal d'ajout
        document.getElementById('modalAjoutAdresse').style.display = 'none';
    }


    //fonction pour prévisualiser une image depuis une URL saisie
    function previsualiserImageDepuisURL() {
        //récupère le champ de saisie de l'URL
        const champSaisieURL = document.getElementById('url_image_input');
        //récupère l'URL saisie et supprime les espaces
        const urlImageSaisie = champSaisieURL.value.trim();


        //vérifie que l'utilisateur a bien saisi une URL
        if (!urlImageSaisie) {
            //affiche un message d'erreur si le champ est vide
            alert('Veuillez saisir une URL d\'image');
            return;
        }


        //vérifie que l'URL saisie est valide
        try {
            //tente de créer un objet URL pour valider le format
            new URL(urlImageSaisie);
        } catch {
            //affiche un message d'erreur si l'URL est invalide
            alert('URL invalide. Veuillez saisir une URL complète (ex: https://exemple.com/image.jpg)');
            return;
        }


        //crée un nouvel objet Image pour tester le chargement
        const imageTest = new Image();
        //fonction exécutée si l'image se charge correctement
        imageTest.onload = function() {
            //récupère l'élément de l'image de profil actuelle
            const elementImageActuelle = document.getElementById('current-profile-image');
            //vérifie si l'élément existe déjà
            if (elementImageActuelle) {
                //met à jour la source de l'image existante
                elementImageActuelle.src = urlImageSaisie;
            } else {
                //crée une nouvelle image si elle n'existe pas
                document.querySelector('.profile-image-container').innerHTML =
                    `<img src="${urlImageSaisie}" alt="Photo de profil" class="profile-image" id="current-profile-image">`;
            }
            //récupère le champ caché qui stocke l'URL de l'image
            const champCacheLienImage = document.getElementById('lien_image');
            //sauvegarde l'URL dans le champ caché pour l'enregistrement
            champCacheLienImage.value = urlImageSaisie;
            //informe l'utilisateur que l'image est chargée
            alert('Image chargée avec succès ! N\'oubliez pas d\'enregistrer vos modifications.');
        };
        //fonction exécutée si l'image ne peut pas être chargée
        imageTest.onerror = function() {
            //affiche un message d'erreur si le chargement échoue
            alert(
                'Impossible de charger l\'image depuis cette URL. Vérifiez que l\'URL est correcte et accessible.');
        };
        //démarre le chargement de l'image de test
        imageTest.src = urlImageSaisie;
    }


    //fonction principale pour configurer le système d'upload d'image
    function configurerUploadImage(champCacheLienImage, identifiantClientConnecte) {
        //récupère la zone de dépôt (drag & drop)
        const zoneDepotFichier = document.getElementById('drop-zone');
        //récupère le champ de sélection de fichier caché
        const champSelectionFichier = document.getElementById('file-input');
        //récupère la zone d'aperçu de l'image
        const zoneApercuImage = document.getElementById('preview-zone');
        //récupère la zone d'affichage du statut d'upload
        const zoneStatutUpload = document.getElementById('upload-status');


        //vérifie que tous les éléments nécessaires existent
        if (!zoneDepotFichier || !champSelectionFichier || !zoneApercuImage || !zoneStatutUpload) {
            //arrête la fonction si un élément manque
            return;
        }


        //ouvre la fenêtre de sélection de fichier quand on clique sur la zone
        zoneDepotFichier.addEventListener('click', () => champSelectionFichier.click());


        //fonction pour empêcher le comportement par défaut du navigateur
        function empecherComportementParDefaut(evenement) {
            //empêche l'action par défaut (ouvrir l'image dans le navigateur)
            evenement.preventDefault();
            //arrête la propagation de l'événement
            evenement.stopPropagation();
        }


        //liste des événements de drag & drop à gérer
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(nomEvenement => {
            //empêche le comportement par défaut sur la zone de dépôt
            zoneDepotFichier.addEventListener(nomEvenement, empecherComportementParDefaut);
            //empêche le comportement par défaut sur toute la page
            document.body.addEventListener(nomEvenement, empecherComportementParDefaut);
        });


        //ajoute un effet visuel quand on survole la zone avec un fichier
        ['dragenter', 'dragover'].forEach(nomEvenement => {
            //ajoute la classe CSS pour l'effet de survol
            zoneDepotFichier.addEventListener(nomEvenement, () => zoneDepotFichier.classList.add('drag-over'));
        });


        //retire l'effet visuel quand on quitte la zone ou dépose le fichier
        ['dragleave', 'drop'].forEach(nomEvenement => {
            //retire la classe CSS de l'effet de survol
            zoneDepotFichier.addEventListener(nomEvenement, () => zoneDepotFichier.classList.remove(
                'drag-over'));
        });


        //gère le dépôt de fichier par drag & drop
        zoneDepotFichier.addEventListener('drop', (evenement) => gererDepotFichier(evenement, champCacheLienImage,
            identifiantClientConnecte, zoneApercuImage, zoneStatutUpload));
        //gère la sélection de fichier par clic
        champSelectionFichier.addEventListener('change', (evenement) => gererSelectionFichier(evenement,
            champCacheLienImage, identifiantClientConnecte, zoneApercuImage, zoneStatutUpload));
    }


    //fonction appelée quand un fichier est déposé dans la zone
    function gererDepotFichier(evenement, champCacheLienImage, identifiantClientConnecte, zoneApercuImage,
        zoneStatutUpload) {
        //récupère les fichiers déposés
        const fichiersDePoses = evenement.dataTransfer.files;
        //vérifie qu'au moins un fichier a été déposé
        if (fichiersDePoses.length > 0) {
            //envoie le premier fichier au serveur
            envoyerFichierVersServeur(fichiersDePoses[0], champCacheLienImage, identifiantClientConnecte,
                zoneApercuImage, zoneStatutUpload);
        }
    }


    //fonction appelée quand un fichier est sélectionné par clic
    function gererSelectionFichier(evenement, champCacheLienImage, identifiantClientConnecte, zoneApercuImage,
        zoneStatutUpload) {
        //récupère les fichiers sélectionnés
        const fichiersSelectionnes = evenement.target.files;
        //vérifie qu'au moins un fichier a été sélectionné
        if (fichiersSelectionnes.length > 0) {
            //envoie le premier fichier au serveur
            envoyerFichierVersServeur(fichiersSelectionnes[0], champCacheLienImage, identifiantClientConnecte,
                zoneApercuImage, zoneStatutUpload);
        }
    }


    //fonction pour envoyer le fichier image au serveur
    function envoyerFichierVersServeur(fichierImage, champCacheLienImage, identifiantClientConnecte, zoneApercuImage,
        zoneStatutUpload) {
        //vérifie que le fichier est bien une image
        if (!fichierImage.type.startsWith('image/')) {
            //affiche un message d'erreur si ce n'est pas une image
            afficherMessageStatut('Veuillez sélectionner une image valide (JPEG, PNG, GIF, WebP)', 'error',
                zoneStatutUpload);
            return;
        }


        //vérifie que la taille du fichier ne dépasse pas 5 MB
        if (fichierImage.size > 5 * 1024 * 1024) {
            //affiche un message d'erreur si le fichier est trop volumineux
            afficherMessageStatut('L\'image est trop volumineuse (max 5 MB)', 'error', zoneStatutUpload);
            return;
        }


        //crée un objet FormData pour envoyer le fichier
        const donneesFormulaireUpload = new FormData();
        //ajoute le fichier image aux données
        donneesFormulaireUpload.append('image', fichierImage);
        //ajoute l'ID du client aux données
        donneesFormulaireUpload.append('id_client', identifiantClientConnecte);


        //crée un lecteur de fichier pour afficher l'aperçu
        const lecteurFichier = new FileReader();
        //fonction exécutée quand le fichier est lu
        lecteurFichier.onload = (evenement) => {
            //affiche l'aperçu de l'image dans la zone prévue
            zoneApercuImage.innerHTML = `<img src="${evenement.target.result}" alt="Aperçu">`;
            //récupère l'élément de l'image de profil actuelle
            const elementImageActuelle = document.getElementById('current-profile-image');
            //met à jour l'image de profil si elle existe
            if (elementImageActuelle) elementImageActuelle.src = evenement.target.result;
        };
        //lit le fichier comme une URL de données
        lecteurFichier.readAsDataURL(fichierImage);


        //affiche un message d'upload en cours
        afficherMessageStatut('Upload en cours...', 'success', zoneStatutUpload);


        //envoie la requête AJAX au serveur
        fetch('upload_image.php', {
                //méthode POST pour envoyer les données
                method: 'POST',
                //données du formulaire avec le fichier
                body: donneesFormulaireUpload
            })
            //attend la réponse et la convertit en JSON
            .then(reponse => reponse.json())
            //traite la réponse du serveur
            .then(donneesReponse => {
                //vérifie si l'upload a réussi
                if (donneesReponse.success) {
                    //sauvegarde le chemin de l'image dans le champ caché
                    champCacheLienImage.value = donneesReponse.path;
                    //affiche un message de succès
                    afficherMessageStatut(
                        'Image uploadée avec succès ! N\'oubliez pas d\'enregistrer vos modifications.',
                        'success', zoneStatutUpload);
                } else {
                    //affiche le message d'erreur retourné par le serveur
                    afficherMessageStatut('Erreur: ' + donneesReponse.message, 'error', zoneStatutUpload);
                }
            })
            //capture les erreurs de la requête
            .catch(erreur => {
                //affiche l'erreur dans la console du navigateur
                console.error('Erreur:', erreur);
                //affiche un message d'erreur à l'utilisateur
                afficherMessageStatut('Erreur lors de l\'upload', 'error', zoneStatutUpload);
            });
    }


    //fonction pour afficher un message de statut d'upload
    function afficherMessageStatut(messageTexte, typeMessage, zoneStatutUpload) {
        //affiche le texte du message
        zoneStatutUpload.textContent = messageTexte;
        //applique la classe CSS correspondant au type de message
        zoneStatutUpload.className = 'upload-status ' + typeMessage;
        //rend la zone visible
        zoneStatutUpload.style.display = 'block';
        //si c'est un message de succès, le cache après 5 secondes
        if (typeMessage === 'success') {
            //cache automatiquement le message après 5 secondes
            setTimeout(() => zoneStatutUpload.style.display = 'none', 5000);
        }
    }
    </script>
    <script src="/js/notifications.js"></script>
</body>

</html>