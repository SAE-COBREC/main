<?php
    session_start();
    $totalHT = 0;
    $netCom = 0;
    $totalTTC = 0;
?>
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
            <td><?php echo $value["remise_unitaire"] . ' %'; ?></td>
            <td><?php  echo $value["tva"] . ' %'; ?></td>
            <td><?php  
                echo ((($value["quantite"] * $value["prix_unitaire"]) - (($value['remise_unitaire'] / 100) * $value["prix_unitaire"]  * $value["quantite"])) * $value["tva"]) . ' €'; 
                $netCom += ($value["quantite"] * $value["prix_unitaire"]) - (($value['remise_unitaire'] / 100) * $value["prix_unitaire"]  * $value["quantite"]);
                $totalTTC += (($value["quantite"] * $value["prix_unitaire"]) - (($value['remise_unitaire'] / 100) * $value["prix_unitaire"]  * $value["quantite"])) * $value["tva"];
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