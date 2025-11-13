<?php
session_start();

include __DIR__ . '/../../../../config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['client_id'])) {
    header('Location: connexion.php');
    exit();
}

$clientId = $_SESSION['client_id'];

// Récupérer les informations du client
$stmtClient = $pdo->prepare("
    SELECT c_nom, c_prenom, c_email, c_telephone 
    FROM Client 
    WHERE c_id = ?
");
$stmtClient->execute([$clientId]);
$client = $stmtClient->fetch(PDO::FETCH_ASSOC);

// Récupérer les adresses du client
$stmtAdresses = $pdo->prepare("
    SELECT a_id, a_nom, a_rue, a_code_postal, a_ville, a_pays, a_principale 
    FROM Adresse 
    WHERE c_id = ? 
    ORDER BY a_principale DESC, a_id DESC
");
$stmtAdresses->execute([$clientId]);
$adresses = $stmtAdresses->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les dernières commandes
$stmtCommandes = $pdo->prepare("
    SELECT 
        co_id, 
        co_numero_suivi, 
        co_date, 
        co_statut, 
        co_montant_total 
    FROM Commande 
    WHERE c_id = ? 
    ORDER BY co_date DESC 
    LIMIT 5
");
$stmtCommandes->execute([$clientId]);
$commandes = $stmtCommandes->fetchAll(PDO::FETCH_ASSOC);

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Mise à jour des informations personnelles
    if (isset($_POST['update_info'])) {
        $nom = htmlspecialchars($_POST['nom']);
        $prenom = htmlspecialchars($_POST['prenom']);
        $email = htmlspecialchars($_POST['email']);
        $telephone = htmlspecialchars($_POST['telephone']);
        
        $stmtUpdate = $pdo->prepare("
            UPDATE Client 
            SET c_nom = ?, c_prenom = ?, c_email = ?, c_telephone = ? 
            WHERE c_id = ?
        ");
        $stmtUpdate->execute([$nom, $prenom, $email, $telephone, $clientId]);
        
        header('Location: profil.php?success=info_updated');
        exit();
    }
    
    // Changement de mot de passe
    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Vérifier l'ancien mot de passe
        $stmtCheckPwd = $pdo->prepare("SELECT c_mdp FROM Client WHERE c_id = ?");
        $stmtCheckPwd->execute([$clientId]);
        $hashedPassword = $stmtCheckPwd->fetchColumn();
        
        if (password_verify($currentPassword, $hashedPassword)) {
            if ($newPassword === $confirmPassword) {
                $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmtUpdatePwd = $pdo->prepare("UPDATE Client SET c_mdp = ? WHERE c_id = ?");
                $stmtUpdatePwd->execute([$newHashedPassword, $clientId]);
                
                header('Location: profil.php?success=password_changed');
                exit();
            }
        }
    }
    
    // Mise à jour des préférences
    if (isset($_POST['update_preferences'])) {
        $newsletter = isset($_POST['newsletter']) ? 1 : 0;
        $promotions = isset($_POST['promotions']) ? 1 : 0;
        $recommandations = isset($_POST['recommandations']) ? 1 : 0;
        
        $stmtPreferences = $pdo->prepare("
            UPDATE Client 
            SET c_newsletter = ?, c_promotions = ?, c_recommandations = ? 
            WHERE c_id = ?
        ");
        $stmtPreferences->execute([$newsletter, $promotions, $recommandations, $clientId]);
        
        header('Location: profil.php?success=preferences_updated');
        exit();
    }
}

// Récupérer les préférences du client
$stmtPrefs = $pdo->prepare("
    SELECT c_newsletter, c_promotions, c_recommandations 
    FROM Client 
    WHERE c_id = ?
");
$stmtPrefs->execute([$clientId]);
$preferences = $stmtPrefs->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - Alizon</title>
    <link rel="stylesheet" href="/styles/Pages/ProfilClient" />
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    
    <header>
        <!-- Votre header existant -->
    </header>

    <main>
        <div>
            <button onclick="history.back()">
                ← Retour
            </button>
            
            <h1>Mon Profil</h1>

            <!-- Section informations personnelles -->
            <section>
                <h2>Informations personnelles</h2>
                
                <form method="POST">
                    <div>
                        <label>
                            <span>Nom</span>
                            <input type="text" name="nom" value="<?= htmlspecialchars($client['c_nom']) ?>" required>
                        </label>
                    </div>
                    
                    <div>
                        <label>
                            <span>Prénom</span>
                            <input type="text" name="prenom" value="<?= htmlspecialchars($client['c_prenom']) ?>" required>
                        </label>
                    </div>
                    
                    <div>
                        <label>
                            <span>Email</span>
                            <input type="email" name="email" value="<?= htmlspecialchars($client['c_email']) ?>" required>
                        </label>
                    </div>
                    
                    <div>
                        <label>
                            <span>Téléphone</span>
                            <input type="tel" name="telephone" value="<?= htmlspecialchars($client['c_telephone'] ?? '') ?>">
                        </label>
                    </div>
                    
                    <button type="submit" name="update_info">
                        Enregistrer les modifications
                    </button>
                </form>
            </section>

            <!-- Section adresses -->
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
                            <div>
                                <?php if ($adresse['a_principale']): ?>
                                    <div>
                                        <span>Adresse principale</span>
                                    </div>
                                <?php endif; ?>
                                <h3><?= htmlspecialchars($adresse['a_nom']) ?></h3>
                                <p>
                                    <?= htmlspecialchars($adresse['a_rue']) ?><br>
                                    <?= htmlspecialchars($adresse['a_code_postal']) ?> <?= htmlspecialchars($adresse['a_ville']) ?><br>
                                    <?= htmlspecialchars($adresse['a_pays']) ?>
                                </p>
                            </div>
                            <div>
                                <button type="button" onclick="location.href='modifier-adresse.php?id=<?= $adresse['a_id'] ?>'">
                                    Modifier
                                </button>
                                <button type="button" onclick="if(confirm('Supprimer cette adresse ?')) location.href='supprimer-adresse.php?id=<?= $adresse['a_id'] ?>'">
                                    Supprimer
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <!-- Section commandes récentes -->
            <section>
                <h2>Mes dernières commandes</h2>
                
                <?php if (empty($commandes)): ?>
                    <p>Aucune commande effectuée</p>
                <?php else: ?>
                    <?php foreach ($commandes as $commande): ?>
                        <article>
                            <div>
                                <span>Commande #<?= htmlspecialchars($commande['co_numero_suivi']) ?></span>
                            </div>
                            <div>
                                <div>
                                    <span>Date : <?= date('d/m/Y', strtotime($commande['co_date'])) ?></span>
                                    <span>Statut : <?= htmlspecialchars($commande['co_statut']) ?></span>
                                </div>
                                <span><?= number_format($commande['co_montant_total'], 2, ',', ' ') ?>€</span>
                            </div>
                            <button type="button" onclick="location.href='suivi-commande.php?id=<?= $commande['co_id'] ?>'">
                                Voir les détails
                            </button>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <!-- Section sécurité -->
            <section>
                <h2>Sécurité</h2>
                
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
                        Changer le mot de passe
                    </button>
                </form>
            </section>

            <!-- Section préférences -->
            <section>
                <h2>Préférences</h2>
                
                <form method="POST">
                    <label>
                        <input type="checkbox" name="newsletter" <?= $preferences['c_newsletter'] ? 'checked' : '' ?>>
                        <span>Recevoir les newsletters</span>
                    </label>
                    
                    <label>
                        <input type="checkbox" name="promotions" <?= $preferences['c_promotions'] ? 'checked' : '' ?>>
                        <span>Recevoir les offres promotionnelles</span>
                    </label>
                    
                    <label>
                        <input type="checkbox" name="recommandations" <?= $preferences['c_recommandations'] ? 'checked' : '' ?>>
                        <span>Recevoir les recommandations personnalisées</span>
                    </label>
                    
                    <button type="submit" name="update_preferences">
                        Enregistrer les préférences
                    </button>
                </form>
            </section>
        </div>
    </main>

    <footer>
        <!-- Votre footer existant -->
    </footer>

</body>
</html>
