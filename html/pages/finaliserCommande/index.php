<?php
session_start();
include '../../selectBDD.php';
include '../fonctions.php';
$boolErreur = false;

if (isset($_SESSION['idClient'])) {
    $id_client = $_SESSION['idClient'];
    $pdo->exec("SET search_path TO cobrec1");

    $requetePanier = "
            SELECT nom, prenom, _compte.id_compte
            FROM _client
            JOIN _compte ON _client.id_compte = _compte.id_compte
            WHERE id_client = :id_client";

    $stmt = $pdo->prepare($requetePanier);

    $stmt->execute([
        ':id_client' => $id_client
    ]);

    $nomPrenom = $stmt->fetch();

    $requeteAdresse = "SELECT a_adresse, a_ville, a_code_postal, a_pays, id_adresse, a_numero
            FROM _client
            JOIN _compte ON _client.id_compte = _compte.id_compte
            JOIN _adresse ON _compte.id_compte = _adresse.id_compte
            WHERE id_client = :id_client";
    $stmt = $pdo->prepare($requeteAdresse);

    $stmt->execute([
        ':id_client' => $id_client
    ]);
    $adresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    //on init les prix qui seront rentré dans la facture de la BDD
    $prixTotalFinal = 0;
    $f_total_ht = 0; //le prix total HT
    $f_total_remise = 0; //le prix des remises

    //on récup tous les id des produit dans le panier
    $reqArticlePanier = "
            SELECT _contient.id_produit, quantite
            FROM _contient 
            JOIN _produit ON _contient.id_produit = _produit.id_produit
            WHERE id_panier = :id_panier
            AND p_statut = 'En ligne';
        ";
    $stmt = $pdo->prepare($reqArticlePanier);
    $stmt->execute([':id_panier' => $_SESSION['panierEnCours']]);
    $articlesDansPanier = $stmt->fetchAll(PDO::FETCH_ASSOC);

    //pour chaque article dans le panier  
    foreach($articlesDansPanier as $article){
        $donnees = recupInfoPourFactureArticle($pdo, $article["id_produit"]);
        $f_total_ht = $f_total_ht + $donnees["p_prix"];
        $reduc = $donnees["p_prix"] * ($donnees["reduction_pourcentage"] / 100);
        $f_total_remise = $f_total_remise + $reduc;
        $prixTotalFinal = $prixTotalFinal + ((($donnees["p_prix"] - $reduc) * (1 + $donnees["montant_tva"] / 100)) * $article["quantite"]);
        //echo $prixTotalFinal . "_";
    }
    $prixTotalFinal = round($prixTotalFinal, 2);

} else {
    $url = '/pages/connexionClient/index.php';
    echo '<!doctype html><html><head><meta http-equiv="refresh" content="0;url=' . $url . '">';
}


//cette condition sert à pour le paiement à vérifier si les informations sont valide ou non
if (isset($_POST['numCarte'], $_POST['dateExpiration'], $_POST['cvc'], $_POST['adresse'], $_POST['nom'])) { //on vérifie qu'on a bien toutes les infrmations du paiement
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
        $erreur = $erreur .  " Date d'expiration invalide";
        $boolErreur = true;
    } else {
        $yy = 2000 + $yy;
        $expiration = $yy . "-" . str_pad($mm, 2, "0", STR_PAD_LEFT);
        $dateTodayMA = date("Y-m");
        if ($expiration < $dateTodayMA) {
            $erreur = $erreur . " Date d'expiration invalide";
            $boolErreur = true;
        }
    }

    if ($_POST['adresse'] == "invalide"){ //invalide c'est quand l'utilisateur n'a pas changé d'adresse donc l'option par défaut dans le select
        $erreur = $erreur . " Adresse invalide"; 
        $boolErreur = true; //c'est donc une erreur on passe le booleen a vrai pour ne pas validé le paiement
    } else if ($_POST['adresse'] == "nouvelle"){ //si l'utilisateur choisi une nouvelle adresse
        if (empty($_POST['numero']) || empty($_POST['rue']) || empty($_POST['ville']) || empty($_POST['codePostal'] ||
         empty($_POST['nom_destinataire']) || empty($_POST['prenom_destinataire']))){ 
            //si les champs obligatoire sont vide
            $erreur = $erreur . " Information d'adresse manquante";
            $boolErreur = true; //c'est donc une erreur on passe le booleen a vrai pour ne pas validé le paiement
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


        //⚠️On insère dans la facture MAIS A TERMINER⚠️
        /*RESTE À FAIRE JE DOIS PRENDRE LE PRIX TOTAL HORS TAXE, LE PRIX TOTAL TTC LES REMIS ECT POUR INSERER DANS LA BDD CORRECTEMENT ET LA REMISE AUSSI⚠️⚠️⚠️⚠️⚠️⚠️*/

        if ($_POST['adresse'] == "nouvelle"){ 
            $resultatAdresse = ajouterNouvelleAdresse($pdo, $nomPrenom["id_compte"], $_POST['numero'], $_POST['rue'], $_POST['ville'], $_POST['codePostal'], $_POST['complement']);
            insererFacture($pdo, $panierEnCours, $_POST['nom_destinataire'], $_POST['prenom_destinataire'], 
            $f_total_ht ?? 0, $f_total_remise ?? 0, $prixTotalFinal, $resultatAdresse['id_adresse']);
            $idFacture = $pdo->lastInsertId();
        } else {
            insererFacture($pdo, $panierEnCours, $nomPrenom["nom"], $nomPrenom["prenom"],
            $f_total_ht ?? 0, $f_total_remise ?? 0, $prixTotalFinal, $_POST['adresse']);
            $idFacture = $pdo->lastInsertId();
        }

        try {
            //récupérer les articles du panier validé
            $sqlStock = "SELECT id_produit, quantite FROM _contient WHERE id_panier = :id_panier";
            $stmtStock = $pdo->prepare($sqlStock);
            $stmtStock->execute([':id_panier' => $panierEnCours]);
            $articlesAStock = $stmtStock->fetchAll(PDO::FETCH_ASSOC);

            //préparer la requête de déduction
            $sqlUpdateStock = "UPDATE _produit SET p_stock = p_stock - :quantite WHERE id_produit = :id_produit";
            $stmtUpdateStock = $pdo->prepare($sqlUpdateStock);

            $sqlUpdateNbVentes = "UPDATE _produit SET p_nb_ventes = p_nb_ventes + :quantite WHERE id_produit = :id_produit";
            $stmtUpdateNbVentes = $pdo->prepare($sqlUpdateNbVentes);

            //déduire chaque article
            foreach($articlesAStock as $artStock){
                $stmtUpdateStock->execute([
                    ':quantite' => $artStock['quantite'],
                    ':id_produit' => $artStock['id_produit']
                ]);

                //mettre à jour le nombre de ventes
                $stmtUpdateNbVentes->execute([
                    ':quantite' => $artStock['quantite'],
                    ':id_produit' => $artStock['id_produit']
                ]);
            }
        } catch (Exception $e) {
            error_log("Erreur lors de la mise à jour du stock : " . $e->getMessage());
        }

        //on créer un session pour le panier qui vient d'être commandé car le panierEnCours on doit le supprimé pour éviter
        //les problèmes ave la page panier qui ne reset pas le panier  en cours et donc on aura encore le panier en cours dans notre 
        //panier alors qu'on vient de payer. Il servira pour la page de suivi de commande vu qu'on est redirigé sur celle-ci après le paiement
        $_SESSION["id_commande"] = $_SESSION["panierEnCours"];
        unset($_SESSION['bordereau']); 
        //une fois que tout le panier a été traité on créer un nouveau pour eviter les erreurs
        $sqlCreatePanier = "
            INSERT INTO _panier_commande (id_client, timestamp_commande)
            VALUES (:id_client, NULL)
            RETURNING id_panier
        ";
        $stmtCreate = $pdo->prepare($sqlCreatePanier);
        $stmtCreate->execute([":id_client" => $id_client]);
        $idPanier = (int) $stmtCreate->fetchColumn();

        $_SESSION["panierEnCours"] = $idPanier; // on stock l'id du panier créer dans le panier en cours

      
        $url = '../suiviCommande/index.php';
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
            <label>Nom du titulaire *</label>
            <input name="nom" id="nom" type="text" required maxlength="100" placeholder="ex: Dupont"
                value="<?php echo $nomPrenom['nom'] ?>" />
            <h2>Adresse de facturation</h2>
            <select name="adresse" id="adresse">
                <option value="invalide">Choisir l'adresse de livraison</option>
                <?php foreach($adresses as $adresse) : ?>
                <option value="<?php echo $adresse["id_adresse"] ?>">
                    <?php echo $adresse["a_numero"] . " " . $adresse["a_adresse"] . " (" . $adresse["a_code_postal"] . ") " . $adresse["a_ville"];?>
                </option>
                <?php endforeach; ?>
                <option value="nouvelle">Nouvelle adresse</option>
            </select>
            <div id="nouvelleAdresse" style="display: none;">
                <label>Numéro *</label>
                <input type="number" name="numero" min="0" max="9999999" placeholder="Ex : 4">

                <label>Rue *</label>
                <input type="text" name="rue" placeholder="Ex : Edouard Branly">

                <label>Complément (optionnel)</label>
                <input type="text" name="complement">

                <label>Ville *</label>
                <input type="text" name="ville" placeholder="Ex : Lannion">

                <label>Code postal *</label>
                <input type="text" name="codePostal" pattern="^((0[1-9])|([1-8][0-9])|(9[0-7])|(2A)|(2B))[0-9]{3}$"
                    maxlength="5" placeholder="Ex : 22970">

                <label>Nom du destinataire *</label>
                <input type="text" name="nom_destinataire" placeholder="Ex : Dupont">

                <label>Prenom du destinataire *</label>
                <input type="text" name="prenom_destinataire" placeholder="Ex : Jean">
            </div>

            <div class="checkBox">
                <div>
                    <input name="conditions" id="conditions" type="checkbox" required />
                    <label>J'accepte les conditions générales de vente</label>
                </div>
            </div>
            <?php if ($boolErreur == true): ?>
            <p><span><?php echo $erreur ?></span></p>
            <?php endif; ?>
            <button>Payer: <?php echo $prixTotalFinal ?>€</button>
        </form>
    </section>
    <script>
    //récup l'input du numéro de la carte
    const inputCarte = document.getElementById("numCarte");

    //pour chaque entré dans l'input
    inputCarte.addEventListener("input", function() {
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
    dateInput.addEventListener("input", function() {
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
    cvcInput.addEventListener("input", function() {
        let valeur = this.value; //stock la valeur de l'input
        //on remplace tout sauf les chiffres
        valeur = valeur.replace(/\D/g, "");


        this.value = valeur;
    });

    //a chaque changement de valeur du select
    document.getElementById('adresse').addEventListener('change', function() {
        let champsAdresse = document.getElementById('nouvelleAdresse');

        if (this.value === 'nouvelle') { //regarde si la valeur est nouvelle
            champsAdresse.style.display = 'block'; //si oui faire apparaitre les block
        } else { //sinon les rendre invisible
            champsAdresse.style.display = 'none';
        }
    });
    </script>
</body>

</html>