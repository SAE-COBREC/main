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
            <form>
                <label>Numéro de carte</label>
                <input name="numCarte" id="numCarte" type="text" required maxlength="19" minlength="19" placeholder="1111 2222 3333 4444"/>
                <!--maxlenght pour avoir une premiere vérification et minlenght pareil à 19 car il fuat compter les espaces-->

                <div class="dateCode">
                    <label>Date d'expriation</label>
                    <input name="dateExpiration" id="dateExpiration" type="text" required maxlength="5" minlength="5" placeholder="MM/AA"/>

                    <label>Code de sécurité</label>
                    <input name="cvc" id="cvc" type="text" required maxlength="3" minlength="3" placeholder="CVC"/>
                </div>
                <h3>Adresse de facturation</h3>

                <label>Nom</label>
                <input name="nom" id="nom" type="text" required maxlength="100" placeholder="ex: Dupont"/>

                <label>Pays</label>
                <input name="pays" id="pays" type="text" required maxlength="100" placeholder="ex: France"/>

                <input name="saveDonneePaiement" id="saveDonneePaiement" type="checkbox" required/>
                <label>J'accepte de sauvegarder mes informations de paiement pour de futurs achats</label>

                <input name="conditions" id="conditions" type="checkbox" required/>
                <label>J'accepte les conditions de service et que mon mode de paiement soit utilisé pour cette transaction.</label>

                <button>Payer: <?php echo $_SESSION['totalPanier']?></button>
            <form>
    </section>
    <script>
        //récup l'input du numéro de la carte
        const inputCarte = document.getElementById("numCarte");

        //pour chaque entré dans l'input
        inputCarte.addEventListener("input", function () {
            let valeur = this.value; //stock la valeur de l'input

            //on remplace tout sauf les chiffres
            valeur = valeur.replace(/\D/g, "");


            //(.{4}) chaque groupe de 4 caractères. le g pour dire que c'est dans toutes la chaine de caractere
            //$1 remplace chaque groupe de 4 chiffres par le même groupe + un espace
            //trim suprrime le derniere espace
            valeur = valeur.replace(/(.{4})/g, "$1 ").trim();

            //met la valeur changé (après tous les regex)
            this.value = valeur;
        });        

        //récup l'input de la date d'expiration
        const dateInput = document.getElementById("dateExpiration");
        
        //pour chaque entré dans l'input
        dateInput.addEventListener("input", function () {
            let valeur = this.value; //stock la valeur de l'input

            //on remplace tout sauf les chiffres
            valeur = valeur.replace(/\D/g, "");

            // Ajouter le "/" après les 2 chiffres
            if (valeur.length > 2) {
                valeur = valeur.slice(0, 2) + "/" + valeur.slice(2, 4);
            }

            this.value = valeur;
        });


        //récup l'input du CVC
        const cvcInput = document.getElementById("cvc");

        //pour chaque entré dans l'input
        cvcInput.addEventListener("input", function () {
            let valeur = this.value; //stock la valeur de l'input
            //on remplace tout sauf les chiffres
            valeur = valeur.replace(/\D/g, "");


            this.value = valeur;
        });
        
        //récup l'input du pays
        const cvcInput = document.getElementById("pays");

        //pour chaque entré dans l'input
        paysInput.addEventListener("input", function () {
            let valeur = this.value; //stock la valeur de l'input
            valeur = valeur.replace(/[]/g, "");


            this.value = valeur;
        });
    </script>
</body>
</html>
