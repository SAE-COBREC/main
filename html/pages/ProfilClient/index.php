<?php
session_start();

include __DIR__ . '../../selectBDD.php';

$pdo->exec("SET search_path TO cobrec1, public");

//fonction pour récupérer les informations personnelles du client
function getInformationsClient($pdo, $clientId)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                cl.c_nom, 
                cl.c_prenom, 
                co.email, 
                co.num_telephone
            FROM cobrec1._client cl
            INNER JOIN cobrec1._compte co ON cl.id_compte = co.id_compte
            WHERE cl.id_client = ?
        ");
        $stmt->execute([$clientId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erreur récupération client : " . $e->getMessage());
        return false;
    }
}

//fonction pour récupérer l'ID compte depuis l'ID client
function getIdCompte($pdo, $clientId)
{
    try {
        $stmt = $pdo->prepare("SELECT id_compte FROM cobrec1._client WHERE id_client = ?");
        $stmt->execute([$clientId]);
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Erreur récupération ID compte : " . $e->getMessage());
        return false;
    }
}

//fonction pour récupérer toutes les adresses d'un compte
function getAdresses($pdo, $idCompte)
{
    try {
        $stmt = $pdo->prepare("
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
        $stmt->execute([$idCompte]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erreur récupération adresses : " . $e->getMessage());
        return [];
    }
}

//fonction pour récupérer les dernières commandes d'un client
function getCommandesRecentes($pdo, $clientId, $limite = 5)
{
    try {
        $stmt = $pdo->prepare("
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
            LIMIT ?
        ");
        $stmt->execute([$clientId, $limite]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erreur récupération commandes : " . $e->getMessage());
        return [];
    }
}

//fonction pour mettre à jour les informations personnelles du client
function updateInformationsClient($pdo, $clientId, $idCompte, $nom, $prenom, $email, $telephone)
{
    try {
        //mise à jour de la table client
        $stmtClient = $pdo->prepare("
            UPDATE cobrec1._client 
            SET c_nom = ?, c_prenom = ? 
            WHERE id_client = ?
        ");
        $stmtClient->execute([$nom, $prenom, $clientId]);

        //mise à jour de la table compte
        $stmtCompte = $pdo->prepare("
            UPDATE cobrec1._compte 
            SET email = ?, num_telephone = ? 
            WHERE id_compte = ?
        ");
        $stmtCompte->execute([$email, $telephone, $idCompte]);

        return true;
    } catch (Exception $e) {
        error_log("Erreur mise à jour client : " . $e->getMessage());
        return false;
    }
}

//fonction pour changer le mot de passe d'un compte
function updateMotDePasse($pdo, $idCompte, $currentPassword, $newPassword, $confirmPassword)
{
    try {
        //récupérer le mot de passe actuel haché
        $stmt = $pdo->prepare("SELECT mdp FROM cobrec1._compte WHERE id_compte = ?");
        $stmt->execute([$idCompte]);
        $hashedPassword = $stmt->fetchColumn();

        //vérifier le mot de passe actuel
        if (!password_verify($currentPassword, $hashedPassword)) {
            return ['success' => false, 'error' => 'Mot de passe actuel incorrect.'];
        }

        //vérifier que les nouveaux mots de passe correspondent
        if ($newPassword !== $confirmPassword) {
            return ['success' => false, 'error' => 'Les mots de passe ne correspondent pas.'];
        }

        //hasher et mettre à jour le nouveau mot de passe
        $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmtUpdate = $pdo->prepare("UPDATE cobrec1._compte SET mdp = ? WHERE id_compte = ?");
        $stmtUpdate->execute([$newHashedPassword, $idCompte]);

        return ['success' => true];
    } catch (Exception $e) {
        error_log("Erreur changement mot de passe : " . $e->getMessage());
        return ['success' => false, 'error' => 'Erreur lors du changement de mot de passe.'];
    }
}

//vérifier si le client est connecté
if (!isset($_SESSION['client_id'])) {
    header("Location: /pages/connexionClient/index.php");
    exit;
}

//récupérer l'ID du client depuis la session
$clientId = $_SESSION['client_id'];

//chargement des données depuis la base de données
$client = getInformationsClient($pdo, $clientId);
if (!$client) {
    die("Client introuvable avec l'ID : " . htmlspecialchars($clientId));
}

$idCompte = getIdCompte($pdo, $clientId);
$adresses = getAdresses($pdo, $idCompte);
$commandes = getCommandesRecentes($pdo, $clientId);

//gérer les soumissions de formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //traitement de la mise à jour des informations personnelles
    if (isset($_POST['update_info'])) {
        $nom = htmlspecialchars($_POST['nom']);
        $prenom = htmlspecialchars($_POST['prenom']);
        $email = htmlspecialchars($_POST['email']);
        $telephone = htmlspecialchars($_POST['telephone']);

        if (updateInformationsClient($pdo, $clientId, $idCompte, $nom, $prenom, $email, $telephone)) {
            header('Location: index.php?success=info_updated');
            exit();
        } else {
            $error = "Erreur lors de la mise à jour des informations.";
        }
    }

    //traitement du changement de mot de passe
    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        $result = updateMotDePasse($pdo, $idCompte, $currentPassword, $newPassword, $confirmPassword);

        if ($result['success']) {
            header('Location: index.php?success=password_changed');
            exit();
        } else {
            $error = $result['error'];
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
    <link rel="stylesheet" href="/styles/profil.css">
</head>
<body>
    <div class="container">
        <h1>Mon Profil</h1>

        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                <?php 
                if ($_GET['success'] === 'info_updated') echo "Informations mises à jour avec succès.";
                if ($_GET['success'] === 'password_changed') echo "Mot de passe changé avec succès.";
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Section informations personnelles -->
        <section class="section-profil">
            <h2>Informations personnelles</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="nom">Nom</label>
                    <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($client['c_nom']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="prenom">Prénom</label>
                    <input type="text" id="prenom" name="prenom" value="<?php echo htmlspecialchars($client['c_prenom']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($client['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="telephone">Téléphone</label>
                    <input type="tel" id="telephone" name="telephone" value="<?php echo htmlspecialchars($client['num_telephone']); ?>" required>
                </div>
                <button type="submit" name="update_info" class="btn-primary">Mettre à jour</button>
            </form>
        </section>

        <!-- Section changement de mot de passe -->
        <section class="section-profil">
            <h2>Changer le mot de passe</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="current_password">Mot de passe actuel</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">Nouveau mot de passe</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmer le mot de passe</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" name="change_password" class="btn-primary">Changer le mot de passe</button>
            </form>
        </section>

        <!-- Section mes adresses -->
        <section class="section-profil">
            <h2>Mes adresses</h2>
            <?php if (count($adresses) > 0): ?>
                <div class="adresses-liste">
                    <?php foreach ($adresses as $adresse): ?>
                        <div class="adresse-card">
                            <p><strong><?php echo htmlspecialchars($adresse['a_adresse']); ?></strong></p>
                            <?php if (!empty($adresse['a_complement'])): ?>
                                <p><?php echo htmlspecialchars($adresse['a_complement']); ?></p>
                            <?php endif; ?>
                            <p><?php echo htmlspecialchars($adresse['a_code_postal']) . ' ' . htmlspecialchars($adresse['a_ville']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="message-vide">Aucune adresse enregistrée</p>
            <?php endif; ?>
        </section>

        <!-- Section mes dernières commandes -->
        <section class="section-profil">
            <h2>Mes dernières commandes</h2>
            <?php if (count($commandes) > 0): ?>
                <table class="commandes-table">
                    <thead>
                        <tr>
                            <th>N° Commande</th>
                            <th>Date</th>
                            <th>Montant</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($commandes as $commande): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($commande['id_panier']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($commande['timestamp_commande'])); ?></td>
                                <td><?php echo number_format($commande['montant_total'], 2, ',', ' '); ?> €</td>
                                <td><?php echo htmlspecialchars($commande['statut']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="message-vide">Aucune commande effectuée</p>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>