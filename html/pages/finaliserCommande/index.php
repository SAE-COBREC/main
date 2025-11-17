<?php
    session_start();
    $id_client = $_SESSION['idClient'];
    include __DIR__ . '/../../selectBDD.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Paiement - Alizon</title>
  <link rel="icon" type="image/png" href="../../../img/favicon.svg">
  <link rel="stylesheet" href="../../styles/Register/....css">
</head>

<body>
    <div class="englobePaiement">
        <a href="/pages/panier/index.php" id="retourPanier">Panier</a>
        <section class="infoPaiement">
            <h2><img src="/img/svg/logo.svg"/>Alizon</h2>

            <label>Numéro de carte</label>
            <input name="num_carte" id="num_carte" type="text" required maxlength="12" minlength="12" placeholder="1111 2222 3333 4444"/>

            <div class="dateCode">
                <label>Date d'expriation</label>
                <input name="dateExpiration" id="dateExpiration" type="text" required maxlength="5" minlength="5" placeholder="MM/AA"/>

                <label>Code de sécurité</label>
                <input name="cvc" id="cvc" type="text" required maxlength="3" minlength="3" placeholder="CVC"/>
            </div>
            <h3>Adresse de facturation</h3>

            <label>Nom*</label>
            <input name="num_carte" id="num_carte" type="text" required placeholder="ex: Dupont"/>

            <label>Pays*</label>
            <input name="num_carte" id="num_carte" type="text" required placeholder="ex: Dupont"/>

            <input name="saveDonneePaiement" id="saveDonneePaiement" type="checkbox" required/>
            <label>J'accepte de sauvegarder mes informations de paiement pour de futurs achats</label>

            <input name="conditions" id="conditions" type="checkbox" required/>
            <label>J'accepte les conditions de service et que mon mode de paiement soit utilisé pour cette transaction.</label>

            <button>Payer: <?php echo $_SESSION['totalPanier']?></button>
</body>

</html>