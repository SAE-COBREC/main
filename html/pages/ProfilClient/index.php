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

//----------------------------------------------------------------
// CHARGEMENT OPTIMISÉ DU PROFIL CLIENT (1 seule requête au lieu de 6)
//----------------------------------------------------------------

//récupérer toutes les données du profil en une seule requête optimisée avec cache
$profilComplet = recupererProfilCompletClientAvecCache($connexionBaseDeDonnees, $identifiantClientConnecte);

//vérifier que le profil existe
if ($profilComplet === null) {
    die("Impossible de charger le profil client.");
}

//extraire les données pour compatibilité avec le reste du code
$identifiantCompteClient = $profilComplet['id_compte'];
$donneesInformationsClient = $profilComplet;
$listeAdressesClient = $profilComplet['adresses'];
$listeCommandesRecentes = $profilComplet['commandes_recentes'];
$donneesImageProfilCompte = $profilComplet['image_profil'];

//----------------------------------------------------------------
// FIN DU CHARGEMENT OPTIMISÉ
//----------------------------------------------------------------

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
            invaliderCacheProfilClient($identifiantClientConnecte); // ← INVALIDER LE CACHE
            $url = 'index.php?success=info_updated';
            echo '<!doctype html><html><head><meta http-equiv="refresh" content="0;url=' . $url . '">';
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
            invaliderCacheProfilClient($identifiantClientConnecte); // ← INVALIDER LE CACHE
            $url = 'index.php?success=password_changed';
            echo '<!doctype html><html><head><meta http-equiv="refresh" content="0;url=' . $url . '">';
            exit;
        } else {
            $messageErreur = $resultatModificationMotDePasse['message'];
        }
    }

    //mise à jour d'une adresse
    if (isset($_POST['update_address'])) {
        $idAdresse = (int) $_POST['id_adresse'];
        $adresseSaisie = htmlspecialchars($_POST['adresse'] ?? '');
        $villeSaisie = htmlspecialchars($_POST['ville'] ?? '');
        $codePostalSaisi = htmlspecialchars($_POST['code_postal'] ?? '');
        $complementSaisi = htmlspecialchars($_POST['complement'] ?? '');

        $resultatMiseAJourAdresse = mettreAJourAdresse(
            $connexionBaseDeDonnees,
            $idAdresse,
            $identifiantCompteClient,
            $adresseSaisie,
            $villeSaisie,
            $codePostalSaisi,
            $complementSaisi
        );

        if ($resultatMiseAJourAdresse['success']) {
            invaliderCacheProfilClient($identifiantClientConnecte); // ← INVALIDER LE CACHE
            $url = 'index.php?success=address_updated';
            echo '<!doctype html><html><head><meta http-equiv="refresh" content="0;url=' . $url . '">';
            exit;
        } else {
            $messageErreur = $resultatMiseAJourAdresse['message'];
        }
    }

    //suppression d'une adresse
    if (isset($_POST['delete_address'])) {
        $idAdresse = (int) $_POST['id_adresse'];

        //vérifier d'abord le nombre d'adresses avant de supprimer
        $nombreAdresses = count($listeAdressesClient);

        if ($nombreAdresses <= 1) {
            $messageErreur = "Vous ne pouvez pas supprimer votre dernière adresse. Vous devez avoir au moins une adresse enregistrée.";
        } else {
            $resultatSuppressionAdresse = supprimerAdresse($connexionBaseDeDonnees, $idAdresse, $identifiantCompteClient);

            if ($resultatSuppressionAdresse['success']) {
                invaliderCacheProfilClient($identifiantClientConnecte); // ← INVALIDER LE CACHE
                $url = 'index.php?success=address_deleted';
                echo '<!doctype html><html><head><meta http-equiv="refresh" content="0;url=' . $url . '">';
                exit;
            } else {
                $messageErreur = $resultatSuppressionAdresse['message'];
            }
        }
    }

    //AJOUT ADRESSE - traitement POST pour l'ajout d'une nouvelle adresse
    if (isset($_POST['add_address'])) {
        $adresseSaisie = htmlspecialchars($_POST['adresse'] ?? '');
        $villeSaisie = htmlspecialchars($_POST['ville'] ?? '');
        $codePostalSaisi = htmlspecialchars($_POST['code_postal'] ?? '');
        $complementSaisi = htmlspecialchars($_POST['complement'] ?? '');

        $resultatAjoutAdresse = ajouterNouvelleAdresse(
            $connexionBaseDeDonnees,
            $identifiantCompteClient,
            $adresseSaisie,
            $villeSaisie,
            $codePostalSaisi,
            $complementSaisi
        );

        if ($resultatAjoutAdresse['success']) {
            invaliderCacheProfilClient($identifiantClientConnecte); // ← INVALIDER LE CACHE
            $url = 'index.php?success=address_added';
            echo '<!doctype html><html><head><meta http-equiv="refresh" content="0;url=' . $url . '">';
            exit;
        } else {
            $messageErreur = $resultatAjoutAdresse['message'];
        }
    }
}

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
                    if ($_GET['success'] === 'address_updated')
                        echo "Adresse mise à jour avec succès.";
                    if ($_GET['success'] === 'address_deleted')
                        echo "Adresse supprimée avec succès.";
                    // AJOUT ADRESSE - message de succès
                    if ($_GET['success'] === 'address_added')
                        echo "Adresse ajoutée avec succès.";
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
                            <?php if ($donneesImageProfilCompte !== null && $donneesImageProfilCompte['i_lien'] !== NULL && $donneesImageProfilCompte['i_lien'] !== ''): ?>
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
                    <!-- AJOUT ADRESSE - bouton modifié pour ouvrir le modal -->
                    <button type="button" onclick="ouvrirModalAjoutAdresse()">
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
                                    onclick="ouvrirModalModificationAdresse(<?php echo $adresseIndividuelle['id_adresse']; ?>, '<?php echo htmlspecialchars($adresseIndividuelle['a_adresse'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($adresseIndividuelle['a_ville'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($adresseIndividuelle['a_code_postal'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($adresseIndividuelle['a_complement'], ENT_QUOTES); ?>')">
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
            <section style="display : none;">
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
                                    <span>N° Commande</span>
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
                                    <input type="password" name="current_password" required>
                                </label>
                            </div>
                            <div>
                                <label>
                                    <span>Nouveau mot de passe</span>
                                    <input type="password" name="new_password"
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
                    <span>Adresse</span>
                    <input type="text" name="adresse" id="modification_adresse" required>
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

                <label>
                    <span>Complément (optionnel)</span>
                    <input type="text" name="complement" id="modification_complement">
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
                    <span>Adresse</span>
                    <input type="text" name="adresse" id="ajout_adresse" required>
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

                <label>
                    <span>Complément (optionnel)</span>
                    <input type="text" name="complement" id="ajout_complement">
                </label>

                <button type="submit" name="add_address">Ajouter</button>
                <button type="button" onclick="fermerModalAjoutAdresse()">Annuler</button>
            </form>
        </div>
    </div>

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

        //fonction pour ouvrir le modal de modification d'adresse
        function ouvrirModalModificationAdresse(id, adresse, ville, codePostal, complement) {
            document.getElementById('modification_id_adresse').value = id;
            document.getElementById('modification_adresse').value = adresse;
            document.getElementById('modification_ville').value = ville;
            document.getElementById('modification_code_postal').value = codePostal;
            document.getElementById('modification_complement').value = complement;
            document.getElementById('modalModificationAdresse').style.display = 'block';
        }

        //fonction pour fermer le modal de modification d'adresse
        function fermerModalModificationAdresse() {
            document.getElementById('modalModificationAdresse').style.display = 'none';
        }

        // AJOUT ADRESSE - fonction pour ouvrir le modal d'ajout d'adresse
        function ouvrirModalAjoutAdresse() {
            //réinitialiser les champs du formulaire
            document.getElementById('ajout_adresse').value = '';
            document.getElementById('ajout_ville').value = '';
            document.getElementById('ajout_code_postal').value = '';
            document.getElementById('ajout_complement').value = '';
            document.getElementById('modalAjoutAdresse').style.display = 'block';
        }

        // AJOUT ADRESSE - fonction pour fermer le modal d'ajout d'adresse
        function fermerModalAjoutAdresse() {
            document.getElementById('modalAjoutAdresse').style.display = 'none';
        }

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