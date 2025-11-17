<?php
session_start();

include '../../selectBDD.php';

$pdo->exec("SET search_path TO cobrec1");

//fonction pour récupérer les infos du client
function recupererInfosClient($pdo, $idClient)
{
    try {
        $sql = "
            SELECT 
                cl.c_nom,
                cl.c_prenom,
                cl.c_pseudo,
                co.email,
                co.num_telephone
            FROM cobrec1._client cl
            INNER JOIN cobrec1._compte co 
                ON cl.id_compte = co.id_compte
            WHERE cl.id_client = ?
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idClient]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        return $client ?: null;
    } catch (Exception $e) {
        return null;
    }
}

//fonction pour récupérer l'id du compte associé
function recupererIdCompte($pdo, $idClient)
{
    try {
        $sql = "SELECT id_compte FROM cobrec1._client WHERE id_client = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idClient]);
        $idCompte = $stmt->fetchColumn();

        return $idCompte !== false ? (int) $idCompte : null;
    } catch (Exception $e) {
        return null;
    }
}

//fonction pour récupérer les adresses du client
function recupererAdressesClient($pdo, $idCompte)
{
    try {
        $sql = "
            SELECT 
                id_adresse,
                a_adresse,
                a_ville,
                a_code_postal,
                a_complement
            FROM cobrec1._adresse
            WHERE id_compte = ?
            ORDER BY id_adresse DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idCompte]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        return [];
    }
}

//fonction pour récupérer les dernières commandes
function recupererDernieresCommandes($pdo, $idClient)
{
    try {
        $sql = "
            SELECT 
                p.id_panier,
                p.timestamp_commande,
                COALESCE(f.f_total_ttc, 0) as montant_total,
                COALESCE(l.etat_livraison, 'En attente') as statut
            FROM cobrec1._panier_commande p
            LEFT JOIN cobrec1._facture f 
                ON p.id_panier = f.id_panier
            LEFT JOIN cobrec1._livraison l 
                ON f.id_facture = l.id_facture
            WHERE p.id_client = ?
              AND p.timestamp_commande IS NOT NULL
            ORDER BY p.timestamp_commande DESC
            LIMIT 5
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idClient]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        return [];
    }
}

//fonction pour récupérer l'image du compte
function recupererImageCompte($pdo, $idCompte)
{
    try {
        $sql = "
            SELECT i.id_image, i.i_lien, i.i_title, i.i_alt
            FROM cobrec1._image i
            INNER JOIN cobrec1._represente_compte rc ON i.id_image = rc.id_image
            WHERE rc.id_compte = ?
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idCompte]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        return null;
    }
}

//fonction pour mettre à jour les infos du client, du compte ET l'image
function mettreAJourInfosClient($pdo, $idClient, $idCompte, $nom, $prenom, $pseudo, $email, $telephone, $lienImage = null, $title = null, $alt = null)
{
    try {
        // Mise à jour des informations du client
        $sqlClient = "
            UPDATE cobrec1._client 
            SET c_nom = ?, c_prenom = ?, c_pseudo = ?
            WHERE id_client = ?
        ";
        $stmtClient = $pdo->prepare($sqlClient);
        $stmtClient->execute([$nom, $prenom, $pseudo, $idClient]);

        //mise à jour des informations du compte
        $sqlCompte = "
            UPDATE cobrec1._compte 
            SET email = ?, num_telephone = ?
            WHERE id_compte = ?
        ";
        $stmtCompte = $pdo->prepare($sqlCompte);
        $stmtCompte->execute([$email, $telephone, $idCompte]);

        //si une image est fournie, la mettre à jour
        if ($lienImage !== null && $lienImage !== '') {
            // Vérifier si une image existe déjà
            $sqlCheck = "SELECT id_image FROM cobrec1._represente_compte WHERE id_compte = ?";
            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->execute([$idCompte]);
            $existingImage = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($existingImage) {
                //mettre à jour l'image existante
                $sqlUpdate = "
                    UPDATE cobrec1._image 
                    SET i_lien = ?, i_title = ?, i_alt = ? 
                    WHERE id_image = ?
                ";
                $stmtUpdate = $pdo->prepare($sqlUpdate);
                $stmtUpdate->execute([$lienImage, $title, $alt, $existingImage['id_image']]);
            } else {
                // Créer une nouvelle image
                $sqlInsertImage = "
                    INSERT INTO cobrec1._image (i_lien, i_title, i_alt) 
                    VALUES (?, ?, ?) RETURNING id_image
                ";
                $stmtInsert = $pdo->prepare($sqlInsertImage);
                $stmtInsert->execute([$lienImage, $title, $alt]);
                $newImageId = $stmtInsert->fetchColumn();

                // Lier l'image au compte
                $sqlLink = "INSERT INTO cobrec1._represente_compte (id_image, id_compte) VALUES (?, ?)";
                $stmtLink = $pdo->prepare($sqlLink);
                $stmtLink->execute([$newImageId, $idCompte]);
            }
        }

        return true;
    } catch (Exception $e) {
        return false;
    }
}

//fonction pour changer le mot de passe
function changerMotDePasse($pdo, $idCompte, $motDePasseActuel, $nouveauMotDePasse, $confirmationMotDePasse)
{
    try {
        //récupérer le mot de passe actuel
        $sqlSelect = "SELECT mdp FROM cobrec1._compte WHERE id_compte = ?";
        $stmt = $pdo->prepare($sqlSelect);
        $stmt->execute([$idCompte]);
        $motDePasseHashe = $stmt->fetchColumn();

        if ($motDePasseHashe === false) {
            return ['success' => false, 'message' => "Compte introuvable."];
        }

        //vérifier le mot de passe actuel
        if ($motDePasseActuel != $motDePasseHashe) {
            return ['success' => false, 'message' => "Mot de passe actuel incorrect."];
        }

        //vérifier la confirmation
        if ($nouveauMotDePasse !== $confirmationMotDePasse) {
            return ['success' => false, 'message' => "Les mots de passe ne correspondent pas."];
        }

        //hasher et mettre à jour
        $nouveauMotDePasseHashe = $nouveauMotDePasse;
        $sqlUpdate = "UPDATE cobrec1._compte SET mdp = ? WHERE id_compte = ?";
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->execute([$nouveauMotDePasseHashe, $idCompte]);

        return ['success' => true, 'message' => "Mot de passe modifié avec succès."];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "Erreur lors du changement de mot de passe : " . $e->getMessage()
        ];
    }
}

//vérifier si le client est connecté
if (!isset($_SESSION['idClient'])) {
    header("Location: /pages/connexionClient/index.php");
    exit;
}

//récupérer l'id du client
$idClient = (int) $_SESSION['idClient'];

//gestion de la déconnexion
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: /index.php');
    exit;
}

$error = null;

//récupérer l'id du compte
$idCompte = recupererIdCompte($pdo, $idClient);

//vérifier que le compte existe
if ($idCompte === null) {
    die("Compte introuvable pour le client : " . htmlspecialchars((string) $idClient));
}

//traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    //mise à jour des infos perso (avec image optionnelle)
    if (isset($_POST['update_info'])) {
        $nom = htmlspecialchars($_POST['nom'] ?? '');
        $prenom = htmlspecialchars($_POST['prenom'] ?? '');
        $pseudo = htmlspecialchars($_POST['pseudo'] ?? '');
        $email = htmlspecialchars($_POST['email'] ?? '');
        $telephone = htmlspecialchars($_POST['telephone'] ?? '');

        // Récupération des données de l'image si fournies
        $lienImage = !empty($_POST['lien_image']) ? htmlspecialchars($_POST['lien_image']) : null;
        $title = "Avatar " . $prenom;
        $alt = "Photo de profil " . $prenom;

        $successUpdate = mettreAJourInfosClient($pdo, $idClient, $idCompte, $nom, $prenom, $pseudo, $email, $telephone, $lienImage, $title, $alt);

        if ($successUpdate) {
            header('Location: index.php?success=info_updated');
            exit;
        } else {
            $error = "Erreur lors de la mise à jour des informations personnelles.";
        }
    }

    //changement du mot de passe
    if (isset($_POST['change_password'])) {
        $motDePasseActuel = $_POST['current_password'] ?? '';
        $nouveauMotDePasse = $_POST['new_password'] ?? '';
        $confirmationMotDePasse = $_POST['confirm_password'] ?? '';

        $resultat = changerMotDePasse($pdo, $idCompte, $motDePasseActuel, $nouveauMotDePasse, $confirmationMotDePasse);

        if ($resultat['success']) {
            header('Location: index.php?success=password_changed');
            exit;
        } else {
            $error = $resultat['message'];
        }
    }
}

//chargement des données pour l'affichage
$client = recupererInfosClient($pdo, $idClient);

if (!$client) {
    die("Client introuvable avec l'ID : " . htmlspecialchars((string) $idClient));
}

$adresses = recupererAdressesClient($pdo, $idCompte);
$commandes = recupererDernieresCommandes($pdo, $idClient);
$imageCompte = recupererImageCompte($pdo, $idCompte);
$sqlImagePresent = "
            SELECT i.i_lien
            FROM cobrec1._image i
            INNER JOIN cobrec1._represente_compte rc ON i.id_image = rc.id_image
            WHERE rc.id_compte = ?
        ";
        $stmt = $pdo->prepare($sqlImagePresent);
        $stmt->execute([$idCompte]);
        
$imagePresent = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

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
    include __DIR__ . '/../../partials/header.html';
    ?>

    <main>
        <div>
            <button onclick="history.back()">
                ← Retour
            </button>

            <h1>Mon Profil</h1>

            <?php if (isset($error)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
                <div class="success-message">
                    <?php
                    if ($_GET['success'] === 'info_updated')
                        echo "Informations mises à jour avec succès.";
                    if ($_GET['success'] === 'password_changed')
                        echo "Mot de passe changé avec succès.";
                    ?>
                </div>
            <?php endif; ?>

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
                        <!-- Affichage de l'image de profil -->
                        <div class="profile-image-container">
                            <?php if ($imageCompte['i_lien'] !== NULL && $imageCompte['i_lien'] !== ''): ?>
                                <img src="<?php echo htmlspecialchars($imageCompte['i_lien']); ?>"
                                    alt="<?php echo htmlspecialchars($imageCompte['i_alt'] ?? 'Photo de profil'); ?>"
                                    title="<?php echo htmlspecialchars($imageCompte['i_title'] ?? ''); ?>"
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
                                value="<?php echo htmlspecialchars($imageCompte['i_lien'] ?? ''); ?>">

                            <!-- Champs existants -->
                            <div>
                                <label>
                                    <span>Nom</span>
                                    <input type="text" name="nom" id="nom"
                                        value="<?php echo htmlspecialchars($client['c_nom'] ?? ''); ?>" required>
                                </label>
                            </div>

                            <div>
                                <label>
                                    <span>Prénom</span>
                                    <input type="text" name="prenom" id="prenom"
                                        value="<?php echo htmlspecialchars($client['c_prenom'] ?? ''); ?>" required>
                                </label>
                            </div>

                            <div>
                                <label>
                                    <span>Pseudo</span>
                                    <input type="text" name="pseudo"
                                        value="<?php echo htmlspecialchars($client['c_pseudo'] ?? ''); ?>" required>
                                </label>
                            </div>

                            <div>
                                <label>
                                    <span>Email</span>
                                    <input type="email" name="email"
                                        value="<?php echo htmlspecialchars($client['email'] ?? ''); ?>" required>
                                </label>
                            </div>

                            <div>
                                <label>
                                    <span>Téléphone</span>
                                    <input type="tel" name="telephone"
                                        value="<?php echo htmlspecialchars($client['num_telephone'] ?? ''); ?>">
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

            <section>
                <div>
                    <h2>Mes adresses</h2>
                    <button type="button" onclick="location.href='ajouter-adresse.php'">
                        + Ajouter une adresse
                    </button>
                </div>

                <?php if (empty($adresses)): ?>
                    <p>Aucune adresse enregistrée</p>
                <?php else: ?>
                    <?php foreach ($adresses as $adresse): ?>
                        <article>
                            <header>
                                <div>
                                    <span>Adresse</span>
                                    <strong>#<?php echo $adresse['id_adresse']; ?></strong>
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
                                        <strong><?php echo htmlspecialchars($adresse['a_adresse']); ?></strong><br>
                                        <?php echo htmlspecialchars($adresse['a_code_postal']); ?>
                                        <?php echo htmlspecialchars($adresse['a_ville']); ?>
                                        <?php if (!empty($adresse['a_complement'])): ?>
                                            <br><em><?php echo htmlspecialchars($adresse['a_complement']); ?></em>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </main>

                            <footer>
                                <button type="button"
                                    onclick="location.href='modifier-adresse.php?id=<?php echo $adresse['id_adresse']; ?>'">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                    Modifier
                                </button>
                                <button type="button"
                                    onclick="if(confirm('Supprimer cette adresse ?')) location.href='supprimer-adresse.php?id=<?php echo $adresse['id_adresse']; ?>'">
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

            <section>
                <h2>Mes dernières commandes</h2>

                <?php if (empty($commandes)): ?>
                    <p>Aucune commande effectuée</p>
                <?php else: ?>
                    <?php foreach ($commandes as $commande): ?>
                        <article>
                            <header>
                                <div>
                                    <span>Commande</span>
                                    <strong>#<?php echo htmlspecialchars($commande['id_panier']); ?></strong>
                                </div>
                                <span data-statut="<?php echo strtolower(str_replace(' ', '-', $commande['statut'])); ?>">
                                    <?php echo htmlspecialchars($commande['statut']); ?>
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
                                        <span><?php echo date('d/m/Y', strtotime($commande['timestamp_commande'])); ?></span>
                                    </div>
                                    <div>
                                        <em><?php echo number_format($commande['montant_total'], 2, ',', ' '); ?> €</em>
                                    </div>
                                </div>
                            </main>

                            <footer>
                                <button type="button"
                                    onclick="location.href='suivi-commande.php?id=<?php echo $commande['id_panier']; ?>'">
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
                        <form method="POST">
                            <div>
                                <label>
                                    <span>Mot de passe actuel</span>
                                    <input type="password" name="current_password" placeholder="••••••••" required>
                                </label>
                            </div>

                            <div>
                                <label>
                                    <span>Nouveau mot de passe</span>
                                    <input type="password" name="new_password" placeholder="••••••••" required>
                                </label>
                            </div>

                            <div>
                                <label>
                                    <span>Confirmer le mot de passe</span>
                                    <input type="password" name="confirm_password" placeholder="••••••••" required>
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

            <form method="get" class="logout-form">
                <button type="submit" name="action" value="logout">
                    Déconnexion
                </button>
            </form>

        </div>
    </main>

    <?php
    include __DIR__ . '/../../partials/footer.html';
    ?>

    <script>
        // Gestion des onglets
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');

        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const tabName = btn.getAttribute('data-tab');

                // Désactiver tous les onglets
                tabBtns.forEach(b => b.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));

                // Activer l'onglet sélectionné
                btn.classList.add('active');
                document.getElementById(tabName + '-tab').classList.add('active');
            });
        });

        // Prévisualisation de l'image depuis URL
        function previewUrlImage() {
            const urlInput = document.getElementById('url_image_input');
            const lienImageInput = document.getElementById('lien_image');
            const currentImage = document.getElementById('current-profile-image');
            const url = urlInput.value.trim();

            if (!url) {
                alert('Veuillez saisir une URL d\'image');
                return;
            }

            // Vérifier si l'URL est valide
            try {
                new URL(url);
            } catch (e) {
                alert('URL invalide. Veuillez saisir une URL complète (ex: https://exemple.com/image.jpg)');
                return;
            }

            // Tester si l'image se charge correctement
            const testImg = new Image();
            testImg.onload = function () {
                // L'image est valide, mettre à jour l'affichage
                if (currentImage) {
                    currentImage.src = url;
                } else {
                    // Créer l'image si elle n'existe pas
                    const container = document.querySelector('.profile-image-container');
                    container.innerHTML = `<img src="${url}" alt="Photo de profil" class="profile-image" id="current-profile-image">`;
                }

                // Mettre à jour le champ caché
                lienImageInput.value = url;

                alert('Image chargée avec succès ! N\'oubliez pas d\'enregistrer vos modifications.');
            };

            testImg.onerror = function () {
                alert('Impossible de charger l\'image depuis cette URL. Vérifiez que l\'URL est correcte et accessible.');
            };

            testImg.src = url;
        }

        // JavaScript pour le drag and drop
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('file-input');
        const previewZone = document.getElementById('preview-zone');
        const uploadStatus = document.getElementById('upload-status');
        const lienImageInput = document.getElementById('lien_image');
        const idClient = <?php echo $idClient; ?>; // ID du client depuis PHP

        // Clic sur la zone pour ouvrir le sélecteur de fichier
        dropZone.addEventListener('click', () => fileInput.click());

        // Empêcher le comportement par défaut du navigateur
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // Effet visuel lors du drag
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => {
                dropZone.classList.add('drag-over');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => {
                dropZone.classList.remove('drag-over');
            });
        });

        // Gestion du drop
        dropZone.addEventListener('drop', handleDrop);
        fileInput.addEventListener('change', handleFileSelect);

        function handleDrop(e) {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                uploadFile(files[0]);
            }
        }

        function handleFileSelect(e) {
            const files = e.target.files;
            if (files.length > 0) {
                uploadFile(files[0]);
            }
        }

        function uploadFile(file) {
            // Vérifier que c'est bien une image
            if (!file.type.startsWith('image/')) {
                showStatus('Veuillez sélectionner une image valide (JPEG, PNG, GIF, WebP)', 'error');
                return;
            }

            // Vérifier la taille (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                showStatus('L\'image est trop volumineuse (max 5 MB)', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('image', file);
            formData.append('id_client', idClient); // Envoyer l'ID client au lieu du prénom

            // Afficher un aperçu
            const reader = new FileReader();
            reader.onload = (e) => {
                previewZone.innerHTML = `<img src="${e.target.result}" alt="Aperçu">`;
                // Mettre à jour l'image de profil actuelle si elle existe
                const currentImage = document.getElementById('current-profile-image');
                if (currentImage) {
                    currentImage.src = e.target.result;
                }
            };
            reader.readAsDataURL(file);

            showStatus('Upload en cours...', 'success');

            // Envoyer le fichier au serveur
            fetch('upload_image.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Mettre à jour le champ caché avec le chemin local
                        lienImageInput.value = data.path;
                        showStatus('Image uploadée avec succès ! N\'oubliez pas d\'enregistrer vos modifications.', 'success');
                    } else {
                        showStatus('Erreur: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    showStatus('Erreur lors de l\'upload', 'error');
                });
        }

        function showStatus(message, type) {
            uploadStatus.textContent = message;
            uploadStatus.className = 'upload-status ' + type;

            if (type === 'success') {
                setTimeout(() => {
                    uploadStatus.style.display = 'none';
                }, 5000);
            }
        }
    </script>


</body>

</html>