<?php
session_start();
include '../../selectBDD.php';
$boolErreur = false;

if (isset($_SESSION['idClient'])) {
    $id_client = $_SESSION['idClient'];
    $pdo->exec("SET search_path TO cobrec1");

    $requetePanier = "
            SELECT c_nom
            FROM _client
            WHERE id_client = :id_client";

    $stmt = $pdo->prepare($requetePanier);

    $stmt->execute([
        ':id_client' => $id_client
    ]);


    $nom = $stmt->fetch();

    //recalcul du total directement depuis la base de donnees  pour éviter les problème de modification de variable en javascipt
    $reqTotal = "
            SELECT SUM(p_prix * quantite * (1 + montant_tva / 100)) 
            FROM _contient 
            JOIN _produit ON _contient.id_produit = _produit.id_produit 
            JOIN _tva ON _produit.id_tva = _tva.id_tva
            WHERE id_panier = :id_panier
        ";
    $stmt = $pdo->prepare($reqTotal);
    $stmt->execute([':id_panier' => $_SESSION['panierEnCours']]);
    $totalCalcul = $stmt->fetchColumn();

    //formater le prix pour l'afficher
    $totalPanier = number_format($totalCalcul, 2, '.', '');
} else {
    $url = '/pages/connexionClient/index.php';
    echo '<!doctype html><html><head><meta http-equiv="refresh" content="0;url=' . $url . '">';
}


//cette condition sert à pour le paiement à vérifier si les informations sont valide ou non
if (isset($_POST['numCarte'], $_POST['dateExpiration'], $_POST['cvc'], $_POST['pays'], $_POST['nom'])) { //on vérifie qu'on a bien toutes les infrmations du paiement
    /*
    Algorythme pour vérifier si une carte est valide :
    1.On part du numéro complet à 16 chiffres.
    2.On double un chiffre sur deux en partant de la droite.
    3.Si le double fait plus que 9, on enlève 9.
    4.On additionne tous les chiffres.
    5.le total doit être un multiple de 10.*/
    $numCarte = str_replace(' ', '', $_POST['numCarte']);
    $totalNumCarte = 0; //initialise le total pour l'étape 4
    $alterne = false; //ppour alternrer un chiffre sur 2 pour étape 2
    $erreur = "";
    $boolErreur = false;
    for ($i = strlen($numCarte) - 1; $i >= 0; $i--) {
        $chiffreActu = intval($numCarte[$i]);

        if ($alterne) {
            $chiffreActu *= 2;
            if ($chiffreActu > 9) {
                $chiffreActu -= 9;
            }
        }
        $totalNumCarte += $chiffreActu;
        $alterne = !$alterne;//on alternre un chiffre sur 2 pour étape 2
    }
    if ($totalNumCarte % 10 !== 0) {
        $erreur = "carte invalide";
        $boolErreur = true;
    } else {
        $dateTodayComplet = date("Y-m-d H:i:s");
        $erreur = "";
    }


    /*DATE D'EXPIRATION*/
    $dateExpiration = $_POST['dateExpiration']; //sotck la date de la carte


    // Récupération des valeurs mois/année
    list($mm, $yy) = explode("/", $dateExpiration);
    $mm = intval($mm);
    $yy = intval($yy);

    // Vérification du mois
    if ($mm < 1 || $mm > 12) {
        $erreur = $erreur .  " Mois d'expiration incorrect";
        $boolErreur = true;
    } else {
        $yy = 2000 + $yy;
        $expiration = $yy . "-" . str_pad($mm, 2, "0", STR_PAD_LEFT);
        $dateTodayMA = date("Y-m");
        if ($expiration < $dateTodayMA) {
            $erreur = $erreur . " Date d'expiration incorrecte";
            $boolErreur = true;
        }
    }


    if ($boolErreur == false) { //si pas d'erreur on valide tout
        $panierEnCours = $_SESSION['panierEnCours']; //récup du panier en cours
        $requetePanierTimeStamp = "UPDATE _panier_commande 
                                            SET timestamp_commande = :dateTodayComplet    
                                            WHERE id_panier = :id_panier"; //la requête pour update le time stamp
        $stmt = $pdo->prepare($requetePanierTimeStamp);
        $stmt->execute([
            ':dateTodayComplet' => $dateTodayComplet,
            ':id_panier' => $panierEnCours
        ]); //execute la requête pour update le time stamp


        //requête qui servira plus tard pour la facture etle suivi de commande
        $requeteFacture = "INSERT INTO _facture (id_panier, f_total_ht, f_total_remise, f_total_ht_remise, f_total_ttc) VALUES
                                                        (:id_panier, 0, 0, 0, :f_total_ttc)";
        $stmt = $pdo->prepare($requeteFacture);
        $stmt->execute([
            ':id_panier' => $panierEnCours,
            ':f_total_ttc' => $totalPanier
        ]);

        /*RESTE À FAIRE JE DOIS PRENDRE LE PRIX TOTAL HORS TAXE, LE PRIX TOTAL TTC LES REMIS ECT POUR INSERER DANS LA BDD CORRECTEMENT ET LA REMISE AUSSI*/

        $url = '/index.php';
        echo '<!doctype html><html><head><meta http-equiv="refresh" content="0;url=' . $url . '">';

    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement - Alizon</title>
    <link rel="icon" type="image/png" href="../../../img/favicon.svg">
    <link rel="stylesheet" href="/styles/finaliserCommande/styleFinaliserCommande.css">
</head>

<body>
    <h1><a href="/pages/panier/index.php" id="retourPanier">◀ Panier</a></h1>
    <section class="infoPaiement">
        <h2><a href="/index.php"><img src="/img/svg/logo.svg" /> Alizon</a></h2>
        <form id="payerPanier" method="POST" action="/pages/finaliserCommande/index.php">
            <label>Numéro de carte *</label>
            <input name="numCarte" id="numCarte" type="text" required maxlength="19" minlength="19"
                placeholder="1111 2222 3333 4444" />
            <!--maxlenght pour avoir une premiere vérification et minlenght pareil à 19 car il fuat compter les espaces-->

            <div class="dateCode">
                <div>
                    <label>Date d'expriation *</label>
                    <input name="dateExpiration" id="dateExpiration" type="text" required maxlength="5" minlength="5"
                        placeholder="MM/AA" />
                </div>
                <div>
                    <label>Code de sécurité *</label>
                    <input name="cvc" id="cvc" type="text" required maxlength="3" minlength="3" placeholder="CVC" />
                </div>
            </div>
            <h2>Adresse de facturation</h2>

            <label>Nom *</label>
            <input name="nom" id="nom" type="text" required maxlength="100" placeholder="ex: Dupont"
                value="<?php echo $nom['c_nom'] ?>" />

            <label>Pays *</label>
            <input name="pays" id="pays" type="text" required maxlength="100" placeholder="ex: France" />

            <div class="checkBox">
                <div>
                    <input name="conditions" id="conditions" type="checkbox" required />
                    <label>J'accepte les conditions générales de vente</label>
                </div>
            </div>
            <?php if ($boolErreur == true): ?>
                <p><span><?php echo $erreur ?></span></p>
            <?php endif; ?>
            <button>Payer: <?php echo $totalPanier ?>€</button>
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
    </script>
</body>

</html>