<?php
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
            <td>Montant HT</td>
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
            <td><?php echo $value["quantite"]; ?></td>
            <td><?php echo number_format($value["prix_unitaire"] , 2, ',', ' ') . ' €'; ?></td>
            <td><?php  
                    echo number_format(($value["quantite"] * $value["prix_unitaire"]) , 2, ',', ' ') . ' €'; 
                    $totalHT += $value["quantite"] * $value["prix_unitaire"];
            ?></td>
            <td><?php if ($value["remise_unitaire"] != 0) echo number_format($value["remise_unitaire"] , 2, ',', ' ') . ' %'; ?></td>
            <td><?php  echo number_format($value["tva"] , 2, ',', ' ') . ' %'; ?></td>
            <td><?php  
                echo number_format(round(((($value["quantite"] * $value["prix_unitaire"]) - (($value['remise_unitaire'] / 100) * $value["prix_unitaire"]  * $value["quantite"])) * (1 + $value["tva"]/100)),2) , 2, ',', ' ') . ' €'; 
                $netCom += round(($value["quantite"] * $value["prix_unitaire"]) - (($value['remise_unitaire'] / 100) * $value["prix_unitaire"]  * $value["quantite"]),2);
                $totalTTC += round((($value["quantite"] * $value["prix_unitaire"]) - (($value['remise_unitaire'] / 100) * $value["prix_unitaire"]  * $value["quantite"])) * (1 + $value["tva"]/100), 2);
            ?></td>
        </tr>
        <?php
            }
        ?>
        </tbody>
    </table>
    <br>
    <table>
        <tr>
            <td colspan="7">Montant brut HT</td>
            <td><?php echo number_format($totalHT , 2, ',', ' ') . ' €' ?></td>
        </tr>
        <tr>
            <td colspan="7">Net commercial  </td>
            <td><?php echo number_format($netCom , 2, ',', ' ') . ' €' ?></td>
        </tr>
        <tr>
            <td colspan="7">Net à payer TTC</td>
            <td><strong><?php echo number_format($totalTTC, 2, ',', ' ') . ' €' ?></strong></td>
        </tr>
</table>