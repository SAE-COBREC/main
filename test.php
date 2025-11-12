<?php
include '../../config.php';

$pdo->exec("SET search_path TO cobrec1");

// Fonction pour afficher une table de manière formatée
function afficherTable($pdo, $table, $titre)
{
    echo "<h2>$titre</h2>\n";

    try {
        $stmt = $pdo->query("SELECT * FROM $table LIMIT 50");
        $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($resultats) > 0) {
            echo "<pre>\n";

            // En-têtes
            $entetes = array_keys($resultats[0]);
            echo implode(" | ", $entetes) . "\n";
            echo str_repeat("-", count($entetes) * 20) . "\n";

            // Données
            foreach ($resultats as $ligne) {
                $valeurs = array_map(function ($v) {
                    return substr($v ?? 'NULL', 0, 30); // Limiter la longueur pour l'affichage
                }, $ligne);
                echo implode(" | ", $valeurs) . "\n";
            }

            echo "</pre>\n";
            echo "<p><strong>Total : " . count($resultats) . " enregistrement(s)</strong></p>\n";
        } else {
            echo "<p>Aucune donnée dans cette table</p>\n";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Erreur lors de la lecture de la table $table : " . $e->getMessage() . "</p>\n";
    }

    echo "<hr>\n";
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affichage des données - Base cobrec1</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }

        h1 {
            color: #333;
            text-align: center;
            background-color: #fff;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #444;
            background-color: #e9ecef;
            padding: 10px;
            border-left: 4px solid #007bff;
        }

        pre {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            overflow-x: auto;
            font-size: 12px;
            line-height: 1.4;
        }

        .statistiques {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>
    <h1>📊 Affichage des données - Base cobrec1</h1>

    <div class="info">
        <strong>Informations :</strong> Cette page affiche les données de toutes les tables du schéma cobrec1.
        Les données sont limitées à 50 enregistrements par table pour des raisons de performance.
    </div>

    <?php
    // Test de connexion et version
    try {
        $stmt = $pdo->query("SELECT version()");
        $version = $stmt->fetch();
        echo "<div class='statistiques'>\n";
        echo "<h3>Informations système</h3>\n";
        echo "<pre>Version PostgreSQL : " . $version['version'] . "</pre>\n";

        // Statistiques générales
        $stmt = $pdo->query("
            SELECT 'Comptes' as table_name, COUNT(*) as count FROM _compte
            UNION ALL SELECT 'Administrateurs', COUNT(*) FROM _administrateur
            UNION ALL SELECT 'Vendeurs', COUNT(*) FROM _vendeur
            UNION ALL SELECT 'Clients', COUNT(*) FROM _client
            UNION ALL SELECT 'Produits', COUNT(*) FROM _produit
            UNION ALL SELECT 'Commandes', COUNT(*) FROM _panier_commande
            UNION ALL SELECT 'Avis', COUNT(*) FROM _avis
        ");
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<h3>Statistiques générales</h3>\n";
        echo "<pre>\n";
        foreach ($stats as $stat) {
            echo str_pad($stat['table_name'], 15) . " : " . $stat['count'] . "\n";
        }
        echo "</pre>\n";
        echo "</div>\n";

    } catch (Exception $e) {
        echo "<p style='color: red;'>Erreur de connexion : " . $e->getMessage() . "</p>\n";
        exit;
    }
    ?>

    <h2>🎯 Tables principales</h2>

    <?php
    // Tables principales
    afficherTable($pdo, '_compte', '📝 Table COMPTE - Comptes utilisateurs');
    afficherTable($pdo, '_administrateur', '👨‍💼 Table ADMINISTRATEUR - Administrateurs');
    afficherTable($pdo, '_vendeur', '🏪 Table VENDEUR - Vendeurs');
    afficherTable($pdo, '_client', '👥 Table CLIENT - Clients');
    afficherTable($pdo, '_adresse', '📍 Table ADRESSE - Adresses');
    ?>

    <h2>📦 Tables produits</h2>

    <?php
    // Tables produits
    afficherTable($pdo, '_produit', '📦 Table PRODUIT - Produits');
    afficherTable($pdo, '_categorie_produit', '📂 Table CATEGORIE_PRODUIT - Catégories');
    afficherTable($pdo, '_couleur', '🎨 Table COULEUR - Couleurs');
    afficherTable($pdo, '_TVA', '💰 Table TVA - Taxes');
    afficherTable($pdo, '_image', '🖼️ Table IMAGE - Images');
    ?>

    <h2>🛒 Tables commandes et paiements</h2>

    <?php
    // Tables commandes
    afficherTable($pdo, '_panier_commande', '🛒 Table PANIER_COMMANDE - Paniers et commandes');
    afficherTable($pdo, '_contient', '📋 Table CONTIENT - Produits dans les paniers');
    afficherTable($pdo, '_facture', '🧾 Table FACTURE - Factures');
    afficherTable($pdo, '_paiement', '💳 Table PAIEMENT - Paiements');
    afficherTable($pdo, '_livraison', '🚚 Table LIVRAISON - Livraisons');
    ?>

    <h2>💬 Tables avis et commentaires</h2>

    <?php
    // Tables avis
    afficherTable($pdo, '_avis', '💬 Table AVIS - Avis produits');
    afficherTable($pdo, '_commentaire', '📝 Table COMMENTAIRE - Commentaires clients');
    afficherTable($pdo, '_reponse', '↩️ Table REPONSE - Réponses aux avis');
    ?>

    <h2>⚠️ Tables signalements</h2>

    <?php
    // Tables signalements
    afficherTable($pdo, '_signalement', '⚠️ Table SIGNALEMENT - Signalements');
    afficherTable($pdo, '_signale_produit', '📦 Table SIGNALE_PRODUIT - Produits signalés');
    afficherTable($pdo, '_signale_compte', '👥 Table SIGNALE_COMPTE - Comptes signalés');
    afficherTable($pdo, '_signale_avis', '💬 Table SIGNALE_AVIS - Avis signalés');
    ?>

    <h2>🎯 Tables promotions et réductions</h2>

    <?php
    // Tables promotions
    afficherTable($pdo, '_reduction', '🎯 Table REDUCTION - Réductions');
    afficherTable($pdo, '_promotion', '🏷️ Table PROMOTION - Promotions');
    afficherTable($pdo, '_en_reduction', '🔗 Table EN_REDUCTION - Produits en réduction');
    afficherTable($pdo, '_en_promotion', '🔗 Table EN_PROMOTION - Produits en promotion');
    ?>

    <h2>🔗 Tables de liaison</h2>

    <?php
    // Tables de liaison
    afficherTable($pdo, '_represente_produit', '🖼️🔗 Table REPRESENTE_PRODUIT - Images produits');
    afficherTable($pdo, '_represente_compte', '👤🔗 Table REPRESENTE_COMPTE - Images comptes');
    afficherTable($pdo, '_fait_partie_de', '📂🔗 Table FAIT_PARTIE_DE - Produits par catégorie');
    afficherTable($pdo, '_est_dote_de', '🎨🔗 Table EST_DOTE_DE - Couleurs produits');
    afficherTable($pdo, '_envoie_signalement', '⚠️🔗 Table ENVOIE_SIGNALEMENT - Envoi signalements');
    afficherTable($pdo, '_definie_pour', '📊🔗 Table DEFINIE_POUR - Seuils alertes');
    afficherTable($pdo, '_seuil_alerte', '📊 Table SEUIL_ALERTE - Seuils d\'alerte');
    ?>

    <div style="text-align: center; margin-top: 30px; padding: 20px; background-color: #fff; border-radius: 5px;">
        <p><strong>Affichage terminé</strong> - Toutes les tables du schéma cobrec1 ont été affichées</p>
        <p>Généré le : <?php echo date('d/m/Y H:i:s'); ?></p>
    </div>

</body>

</html>