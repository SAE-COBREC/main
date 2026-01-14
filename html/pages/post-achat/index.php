<?php
    session_start();
    $sth = null ;
    $dbh = null ;
    include '../../selectBDD.php';
    $pdo->exec("SET search_path to cobrec1");
    $rechercheNom='';

    if (empty($_SESSION['idClient'])){
        header("Location: ../connexionClient/index.php");
    }

    if (!empty($_GET['facture'])){
        try {//Récupération des infos de la reduc
            $sql = '
            SELECT id_facture, id_panier, id_adresse, nom_destinataire, prenom_destinataire, f_total_ht, f_total_remise, f_total_ttc FROM cobrec1._facture
            WHERE id_facture = :lastFacture;'
            ;
            $stmt = $pdo->prepare($sql);
            $params = [
                'lastFacture' => $_GET['facture']
            ];
            $stmt->execute($params);
            $_SESSION["post-achat"]["facture"] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $_SESSION["post-achat"]["facture"] = $_SESSION["post-achat"]["facture"][0];

            $sql = '
            SELECT id_panier, id_produit, quantite, prix_unitaire, remise_unitaire, frais_de_port, TVA FROM cobrec1._contient
            WHERE id_panier = :panier_commande;'
            ;
            $stmt = $pdo->prepare($sql);
            $params = [
                'panier_commande' => $_SESSION["post-achat"]["facture"]["id_panier"]
            ];
            $stmt->execute($params);
            $_SESSION["post-achat"]["contient"] = $stmt->fetchAll(PDO::FETCH_ASSOC);


            $sql = '
            SELECT id_client FROM cobrec1._panier_commande
            WHERE id_panier = :panier_commande;'
            ;
            $stmt = $pdo->prepare($sql);
            $params = [
                'panier_commande' => $_SESSION["post-achat"]["facture"]["id_panier"]
            ];
            $stmt->execute($params);
            $_SESSION["post-achat"]["client"] = $stmt->fetchAll(PDO::FETCH_ASSOC)[0]["id_client"];

            if ($_SESSION["post-achat"]["client"] != $_SESSION['idClient']){
                header("Location: ../../index.php");
            }
        }catch (Exception $e){}
    }

?>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Récapitulatif d'achat</title>
        <link rel="icon" type="image/png" href="../../img/favicon.svg">
        <link rel="stylesheet" href="/styles/Panier/stylesPanier.css">
        <link rel="stylesheet" href="/styles/post-achat/post-achat.css">
        <link rel="stylesheet" href="/styles/Header/stylesHeader.css">
        <link rel="stylesheet" href="/styles/Footer/stylesFooter.css">
    </head>
    <?php
    include __DIR__ . '/../../partials/header.php';
    ?>
    <body>
        <main>
            <article class="recapCommande">
                <button>Télécharger la facture</button>
            </article>
            <?php
                include __DIR__ . '/table.php';
            ?>
        </main>
    </body>
    <?php
    include __DIR__ . '/../../partials/footer.html';
    include __DIR__ . '/../../partials/toast.html';
    include __DIR__ . '/../../partials/modal.html';
    ?>

</html>