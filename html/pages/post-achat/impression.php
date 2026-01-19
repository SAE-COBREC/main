<?php
    session_start();
    $sth = null ;
    $dbh = null ;
    include '../../selectBDD.php';
    $pdo->exec("SET search_path to cobrec1");
    $rechercheNom='';
    include '../fonctions.php';
?>

<html>
    <head>
        <link rel="stylesheet" href="/styles/post-achat/impression.css">
    </head>
    <body>
        <img src="/img/svg/logo-text.svg">
        <aside>
            <table>
                <thead><tr><td>Payé</td></tr></thead>
                <tr><td>Référence de paiement : <?php echo $_SESSION["post-achat"]["facture"]["id_facture"]; ?></td></tr>
                <tr><td>Vendu par Alizon</td></tr>
            </table>
            <hr>
            <table>
                <tr>
                    <td>Date de facturation</td>
                    <td><?php echo date('j F Y', strtotime($_SESSION["post-achat"]["panier"]["timestamp_commande"])); ?></td>
                </tr>
                <tr>
                    <td>Total à payer</td>
                    <td><?php 
                    if (empty($_SESSION['vendeur_id'])){
                        echo number_format(calcul_f_total_ttc($pdo, $_SESSION["post-achat"]["facture"]["id_panier"]), 2, ',', ' ') . ' €';
                    }else{
                        echo number_format(calcul_f_total_ttc($pdo, $_SESSION["post-achat"]["facture"]["id_panier"], $_SESSION['vendeur_id']), 2, ',', ' ') . ' €';
                    }
                    ?></td>
                </tr>
                <tr><td>Vendu par Alizon</td></tr>
            </table>
        </aside>
        <article>
            <table>
                <tr><td>ALIZON</td></tr>
                <tr><td>RUE ÉDOUARD BRANLY</td></tr>
                <tr><td>LANNION, 22300</td></tr>
            </table>
        </article>


        <article>
            <br>
            <h2>Détails de la facture</h2>
            <?php
                include __DIR__ . '/table.php';
            ?>
        <article>
    </body>
    <footer>
        <small>LU-810-04</small>
        <small>Alizon, rue Édouard Brenly, 22300 Lannion</small>
        <small>R.C.S. Lannion : B 10 18 18</small>
        <small>SIREN : 487773327 - RCS Nanzere - APE : 4791B - TVA : FR 12487774437</small>
        <small>LU-810-04</small>
    </footer>
</html>
<script>
    print();
    close();
</script>