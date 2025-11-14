<?php
//démarrer la session utilisateur
//session_start();

//inclure le fichier de connexion à la base de données
include '../../selectBDD.php';

//définir le schéma PostgreSQL utilisé pour les requêtes
$pdo->exec("SET search_path TO cobrec1, public");

//vérifier si le client est connecté
// if (!isset($_SESSION['client_id'])) {
//     header("Location: /pages/connexionClient/index.php");
//     exit;
// }

//récupérer l'id du client depuis la session
//$clientId = $_SESSION['client_id'];
$clientId = 1;

//requête SQL pour obtenir les informations du client (nom, prénom, email, téléphone)
$stmtClient = $pdo->prepare("
    SELECT 
        cl.c_nom, 
        cl.c_prenom, 
        co.email, 
        co.num_telephone 
    FROM cobrec1._client cl
    INNER JOIN cobrec1._compte co ON cl.id_compte = co.id_compte
    WHERE cl.id_client = ?
");
$stmtClient->execute([$clientId]);
$client = $stmtClient->fetch(PDO::FETCH_ASSOC);

//gestion de la déconnexion
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // destruction de la session
    session_unset();
    session_destroy();

    //même redirection que ton bouton "retours"
    header('Location: /pages/connexionClient/index.php');
    exit;
}

//vérifier que le client existe
if (!$client) {
    die("Client introuvable avec l'ID : " . htmlspecialchars($clientId));
}

//requête SQL pour récupérer l'id du compte associé au client
$stmtIdCompte = $pdo->prepare("SELECT id_compte FROM cobrec1._client WHERE id_client = ?");
$stmtIdCompte->execute([$clientId]);
$idCompte = $stmtIdCompte->fetchColumn();

//requête SQL pour récupérer toutes les adresses du client
$stmtAdresses = $pdo->prepare("
    SELECT 
        id_adresse, 
        a_adresse, 
        a_ville, 
        a_code_postal, 
        a_complement 
    FROM cobrec1._adresse 
    WHERE id_compte = ? 
    ORDER BY id_adresse DESC
");
$stmtAdresses->execute([$idCompte]);
$adresses = $stmtAdresses->fetchAll(PDO::FETCH_ASSOC);

//requête SQL pour récupérer les 5 dernières commandes du client
$stmtCommandes = $pdo->prepare("
    SELECT 
        p.id_panier, 
        p.timestamp_commande,
        COALESCE(f.f_total_ttc, 0) as montant_total,
        COALESCE(l.etat_livraison, 'En attente') as statut
    FROM cobrec1._panier_commande p
    LEFT JOIN cobrec1._facture f ON p.id_panier = f.id_panier
    LEFT JOIN cobrec1._livraison l ON f.id_facture = l.id_facture
    WHERE p.id_client = ? AND p.timestamp_commande IS NOT NULL
    ORDER BY p.timestamp_commande DESC 
    LIMIT 5
");
$stmtCommandes->execute([$clientId]);
$commandes = $stmtCommandes->fetchAll(PDO::FETCH_ASSOC);

//traitement du formulaire si une requête POST est envoyée
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //mise à jour des informations personnelles si le formulaire est soumis
    if (isset($_POST['update_info'])) {
        //récupérer et sécuriser les données du formulaire
        $nom = htmlspecialchars($_POST['nom']);
        $prenom = htmlspecialchars($_POST['prenom']);
        $email = htmlspecialchars($_POST['email']);
        $telephone = htmlspecialchars($_POST['telephone']);

        try {
            //requête SQL pour mettre à jour les données dans _client
            $stmtUpdateClient = $pdo->prepare("
                UPDATE cobrec1._client 
                SET c_nom = ?, c_prenom = ?
                WHERE id_client = ?
            ");
            $stmtUpdateClient->execute([$nom, $prenom, $clientId]);

            //requête SQL pour mettre à jour l'email et le téléphone dans _compte
            $stmtUpdateCompte = $pdo->prepare("
                UPDATE cobrec1._compte 
                SET email = ?, num_telephone = ?
                WHERE id_compte = ?
            ");
            $stmtUpdateCompte->execute([$email, $telephone, $idCompte]);

            //redirection après la mise à jour
            header('Location: index.php?success=info_updated');
            exit();
        } catch (Exception $e) {
            $error = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    }

    //changement du mot de passe si le formulaire est soumis
    if (isset($_POST['change_password'])) {
        //récupérer les valeurs des champs mot de passe
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        try {
            //vérifier le mot de passe actuel avec celui stocké dans la BDD
            $stmtCheckPwd = $pdo->prepare("SELECT mdp FROM cobrec1._compte WHERE id_compte = ?");
            $stmtCheckPwd->execute([$idCompte]);
            $hashedPassword = $stmtCheckPwd->fetchColumn();

            //test du mot de passe actuel
            if (password_verify($currentPassword, $hashedPassword)) {
                //vérifier que les nouveaux mots de passe correspondent
                if ($newPassword === $confirmPassword) {
                    //hasher le nouveau mot de passe et l'enregistrer dans la BDD
                    $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmtUpdatePwd = $pdo->prepare("UPDATE cobrec1._compte SET mdp = ? WHERE id_compte = ?");
                    $stmtUpdatePwd->execute([$newHashedPassword, $idCompte]);

                    //redirection après la modification du mot de passe
                    header('Location: index.php?success=password_changed');
                    exit();
                } else {
                    $error = "Les mots de passe ne correspondent pas.";
                }
            } else {
                $error = "Mot de passe actuel incorrect.";
            }
        } catch (Exception $e) {
            $error = "Erreur lors du changement de mot de passe : " . $e->getMessage();
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
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
</head>

<body>

    <?php
    include __DIR__ . '../partials/header.html';
    ?>

    <main>
        <div>
            <button onclick="history.back()">
                ← Retour
            </button>

            <h1>Mon Profil</h1>

            <?php if (isset($error)): ?>
                <div style="color: red; padding: 10px; background: #fee; margin-bottom: 20px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
                <div style="color: green; padding: 10px; background: #efe; margin-bottom: 20px;">
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
                        <form method="POST">
                            <div>
                                <label>
                                    <span>Nom</span>
                                    <input type="text" name="nom"
                                        value="<?php echo htmlspecialchars($client['c_nom'] ?? ''); ?>" required>
                                </label>
                            </div>

                            <div>
                                <label>
                                    <span>Prénom</span>
                                    <input type="text" name="prenom"
                                        value="<?php echo htmlspecialchars($client['c_prenom'] ?? ''); ?>" required>
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

                            <button type="submit" name="change_password">
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

            <form method="get" style="margin-top: 1rem; text-align: center;">
                <button type="submit" name="action" value="logout">
                    Déconnexion
                </button>
            </form>


        </div>
    </main>

    <?php
    include __DIR__ . '../partials/footer.html';
    ?>

</body>

</html>