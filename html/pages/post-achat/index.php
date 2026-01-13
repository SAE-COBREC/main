<?php
    session_start();
    $sth = null ;
    $dbh = null ;
    include '../../selectBDD.php';
    $pdo->exec("SET search_path to cobrec1");


    try {//Récupération des infos de la reduc
        $sql = '
        SELECT id_facture, id_panier, id_adresse, nom_destinataire, prenom_destinataire, f_total_ht, f_total_remise, f_total_ttc FROM cobrec1._facture
        WHERE id_facture = :lastFacture;'
        ;
        $stmt = $pdo->prepare($sql);
        $params = [
            'lastFacture' => $_SESSION['finaliserCommande']
        ];
        $stmt->execute($params);
        $_SESSION["post-achat"]["facture"] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $_SESSION["post-achat"]["facture"] = $_SESSION["post-achat"]["facture"][0];

        $sql = '
        SELECT id_produit, quantite, prix_unitaire, remise_unitaire, frais_de_port, TVA FROM cobrec1._contient
        WHERE id_panier = :panier_commande;'
        ;
        $stmt = $pdo->prepare($sql);
        $params = [
            'panier_commande' => $_SESSION["post-achat"]["facture"]["id_panier"]
        ];
        $stmt->execute($params);
        $_SESSION["post-achat"]["contient"] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $_SESSION["post-achat"]["contient"] = $_SESSION["post-achat"]["contient"];
    }catch (Exception $e){}
    $totalHT = 0;
    $netCom = 0;
    $totalTTC = 0;

?>
<pre>
<?php
    print_r($_SESSION["post-achat"]);
?>
</pre>
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
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>

            <article class="recapCommande">
                <button>Télécharger la facture</button>
            </article>
            <br>
            <br>
            <table>
                <thead>
                    <tr>
                        <td>Référence</td>
                        <td>Article</td>
                        <td>Quantité</td>
                        <td>Prix unitaire</td>
                        <td>Montant</td>
                        <td>Remise</td>
                        <td>TVA</td>
                        <td>Montant TTC</td>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        foreach ($_SESSION["post-achat"]["contient"] as $key => $value) {
                    ?>
                    <tr>
                        <td><?php  echo $value["id_produit"]; ?></td>
                        <td><?php  
                            $sql = '
                                SELECT p_nom FROM cobrec1._produit
                                WHERE id_produit = :produit;'
                                ;
                                $stmt = $pdo->prepare($sql);
                                $params = [
                                    'produit' => $value["id_produit"]
                                ];
                                $stmt->execute($params);
                                echo $stmt->fetchAll(PDO::FETCH_ASSOC)[0]["p_nom"];
                        
                        ?></td>
                        <td><?php  echo $value["quantite"]; ?></td>
                        <td><?php  echo $value["prix_unitaire"] . ' €'; ?></td>
                        <td><?php  
                                echo ($value["quantite"] * $value["prix_unitaire"]) . ' €'; 
                                $totalHT += $value["quantite"] * $value["prix_unitaire"];
                        ?></td>
                        <td><?php echo round((($value["remise_unitaire"] / $value["prix_unitaire"]) * 100), 2) . ' %'; ?></td>
                        <td><?php  echo $value["tva"]; ?></td>
                        <td><?php  
                            echo ((($value["quantite"] * $value["prix_unitaire"]) - $value["remise_unitaire"] * $value["quantite"]) * $value["tva"]) . ' €'; 
                            $netCom += ($value["quantite"] * $value["prix_unitaire"]) - $value["remise_unitaire"] * $value["quantite"];
                            $totalTTC += (($value["quantite"] * $value["prix_unitaire"]) - $value["remise_unitaire"] * $value["quantite"]) * $value["tva"];
                        ?></td>
                    </tr>
                    <?php
                        }
                    ?>
                    <tr><td colspan="5"></td></tr>
                    <tr>
                        <td colspan="4">Montant brut HT</td>
                        <td><?php echo $totalHT . ' €' ?></td>
                    </tr>
                    <tr>
                        <td colspan="5">= Net commercial</td>
                        <td><?php echo $netCom . ' €' ?></td>
                    </tr>
                    <tr>
                        <td colspan="7">Net à payer TTC</td>
                        <td><?php echo $totalTTC . ' €' ?></td>
                    </tr>
                </tbody>
            </table>
        </main>
    </body>
    <?php
    include __DIR__ . '/../../partials/footer.html';
    include __DIR__ . '/../../partials/toast.html';
    include __DIR__ . '/../../partials/modal.html';
    ?>

</html>