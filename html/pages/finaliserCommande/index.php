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
        $qte = (int)$article["quantite"];

        $prix_ht = $donnees["p_prix"];
        if ($donnees["reduction_pourcentage"] > 0){
            $a_deduire = $donnees["p_prix"] * ($donnees["reduction_pourcentage"] / 100);
        }

        $a_deduire = $reduc_unitaire * $qte;

        $f_total_ht += $prix_ht;
        $f_total_remise += $a_deduire;

        $prixTotalFinal +=
            (($donnees["p_prix"] - $reduc_unitaire)
            * (1 + $donnees["montant_tva"] / 100))
            * $qte;
        echo "<br><br><br>prix article : " . $donnees["p_prix"] . "<br>quantite " . $qte . "<br>prix_ht " . $prix_ht . "<br>a deduire " . $a_deduire . "<br>reduc total " . $a_deduire . "<br>prix total" . $prixTotalFinal;
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

function old($name, $default = '') {
    return htmlspecialchars($_POST[$name] ?? $default);
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
                placeholder="1111 2222 3333 4444" value="<?php echo old('numCarte')?>"/>
            <!--maxlenght pour avoir une premiere vérification et minlenght pareil à 19 car il fuat compter les espaces-->

            <div class="dateCode">
                <div>
                    <label>Date d'expriation *</label>
                    <input name="dateExpiration" id="dateExpiration" type="text" required maxlength="5" minlength="5"
                        placeholder="MM/AA" value="<?php echo old('dateExpiration')?>"/>
                </div>
                <div>
                    <label>Code de sécurité *</label>
                    <input name="cvc" id="cvc" type="text" required maxlength="3" minlength="3" placeholder="CVC" value="<?php echo old('dateExpiration')?>"/>
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
                <input type="text" name="numero" placeholder="Ex : 4 " value="<?php echo old('numero')?>"
                pattern="^[1-9]{0,13}( ){0,1}(bis|ter|quater|quinquies|sexies|septies|octies|nonies)?$"/>

                <label>Rue *</label>
                <input type="text" name="rue" placeholder="Ex : Edouard Branly"value="<?php echo old('rue')?>"/>

                <label>Complément (optionnel)</label>
                <input type="text" name="complement" value="<?php echo old('complement')?>"/>

                <label>Ville *</label>
                <input type="text" name="ville" placeholder="Ex : Lannion" value="<?php echo old('ville')?>">

                <label>Code postal *</label>
                <input type="text" name="codePostal" pattern="^((0[1-9])|([1-8][0-9])|(9[0-7])|(2A)|(2B))[0-9]{3}$"
                    maxlength="5" placeholder="Ex : 22970" value="<?php echo old('codePostal')?>">

                <label>Nom du destinataire *</label>
                <input type="text" name="nom_destinataire" placeholder="Ex : Dupont" value="<?php echo old('nom_destinataire')?>">

                <label>Prenom du destinataire *</label>
                <input type="text" name="prenom_destinataire" placeholder="Ex : Jean" value="<?php echo old('prenom_destinataire')?>">
            </div>

            <div class="checkBox">
                <div>
            <label><input type="checkbox" id="cgv" name="cgv" required> J'accepte les <a href="#" onclick="openCGVModal(); return false;">conditions générales de ventes</a></label>
                </div>
            </div>
            <?php if ($boolErreur == true): ?>
            <p><span><?php echo $erreur ?></span></p>
            <?php endif; ?>
            <button>Payer: <?php echo $prixTotalFinal ?>€</button>
        </form>
    </section>
    <div id="cgvModal" class="modal hidden">
    <div class="modal-content">
      <h2>Conditions Générales de Vente</h2>
      <div class="cgv-content">
        <h3>ARTICLE 1 : OBJET ET CHAMP D'APPLICATION</h3>
        <p>Les présentes Conditions Générales de Vente (ci-après « CGV ») régissent les relations commerciales entre les vendeurs tiers (ci-après les « Vendeurs ») et les acheteurs (ci-après les « Clients ») sur la marketplace Alizon (ci-après la « Marketplace »), exploitée par la COBREC, SCOP SARL au capital de 99 999 999 999 999 999 999 999 999 999 999 999 999 999 999 999 999 999 999,69 €, immatriculée au RCS Paris sous le numéro 123 456 789, dont le siège social est situé 7 Rue Édouard Branly, 22300 Lannion, France.</p>
        <p>La passation de toute commande via la Marketplace Alizon emporte acceptation pleine, entière, non équivoque et non viciée des présentes CGV, qui prévalent sur tout autre document émanant du Client.</p>

        <h3>ARTICLE 2 : DÉFINITIONS</h3>
        <p><strong>Opérateur :</strong> la société COBREC, exploitant partie technique et commerciale de la Marketplace.</p>
        <p><strong>Vendeur :</strong> tout professionnel proposant des produits et/ou services via la Marketplace.</p>
        <p><strong>Client / Acheteur :</strong> toute personne physique ou morale passant commande sur la Marketplace.</p>
        <p><strong>Fiche Produit :</strong> page de présentation d'un produit, comprenant notamment ses caractéristiques, son prix, les modalités de livraison et l'identité du Vendeur.</p>

        <h3>ARTICLE 3 : RÔLE DE L'OPÉRATEUR</h3>
        <p>L'Opérateur met à disposition une plateforme technique permettant la mise en relation entre Vendeurs et Clients. Le contrat de vente est conclu directement entre le Vendeur et le Client. L'Opérateur n'est pas partie au contrat de vente et n'assume pas la qualité de Vendeur, sauf indication expresse contraire pour certains produits. Les conditions particulières de chaque Vendeur et la Fiche Produit complètent les présentes CGV pour former le contrat applicable à chaque commande.</p>

        <h3>ARTICLE 4 : PRODUITS ET INFORMATIONS PRÉCONTRACTUELLES</h3>
        <p>Chaque fiche produit mentionne au minimum : les caractéristiques essentielles du produit, le prix toutes taxes comprises (TTC), les frais, modes et délais de livraison, les éventuelles restrictions de livraison et l'identité et les coordonnées du vendeur.</p>
        <p>Le vendeur est seul responsable de l'exactitude et de la mise à jour des informations figurant sur ses produits.</p>

        <h3>ARTICLE 5 : PROCÉDURE DE COMMANDE ET « DOUBLE CLIC »</h3>
        <p><strong>5.1 Étapes de la commande</strong></p>
        <p>La commande sur la Marketplace suit un parcours en plusieurs étapes :</p>
        <ul>
          <li>Sélection des produits : le Client sélectionne un ou plusieurs produits et les ajoute à son panier.</li>
          <li>Accès au panier : le Client consulte le récapitulatif de son panier (prix, quantités, frais de livraison estimés, Vendeurs concernés).</li>
          <li>Identification / création de compte : le Client s'identifie ou crée un compte utilisateur et saisit/valide ses coordonnées de facturation et de livraison.</li>
          <li>Choix du mode de livraison : le Client choisit les modes de livraison proposés par chaque Vendeur.</li>
          <li>Première validation (1er clic) : le Client valide le panier et accède à une page de récapitulatif de commande détaillée.</li>
        </ul>
        <p><strong>5.2 Page de récapitulatif et double clic</strong></p>
        <p>Avant le paiement, une page de synthèse affiche de manière claire : les produits commandés, leur quantité et leur prix unitaire ; le montant total TTC, ventilé par Vendeur si nécessaire ; les frais et modes de livraison ; l'adresse de livraison et de facturation ; le moyen de paiement choisi ; un lien vers les présentes CGV et, le cas échéant, les conditions spécifiques du Vendeur.</p>
        <p>Le Client doit : vérifier l'exactitude de l'ensemble des informations ; Confirmer avoir pris connaissance et accepter les CGV (case à cocher) ; Cliquer sur le bouton de validation conduisant au paiement.</p>
        <p>Cette action constitue le premier clic d'acceptation. Le Client est ensuite redirigé vers l'interface de paiement sécurisé, où il saisit ses données de paiement et confirme le règlement. La confirmation définitive du paiement par un nouveau clic constitue le second clic. Le contrat de vente est considéré comme conclu à l'issue de ce double clic (validation du récapitulatif puis confirmation du paiement), manifestant le consentement ferme et définitif du Client sur le contenu de la commande et l'obligation de paiement.</p>

        <h3>ARTICLE 6 : ACCUSÉ DE RÉCEPTION ET CONFIRMATION DE COMMANDE</h3>
        <p>Après validation du paiement, le Client reçoit un accusé de réception de commande par courrier électronique, récapitulant la commande (produits, prix, frais de livraison, coordonnées, Vendeurs, numéro de commande).</p>
        <p>Le cas échéant, un ou plusieurs emails de confirmation d'expédition émis par chaque Vendeur lorsque les produits sont remis au transporteur.</p>
        <p>L'email d'accusé de réception ne vaut pas toujours acceptation définitive de la commande par le Vendeur, notamment en cas d'erreur manifeste (affichage d'un prix dérisoire, indisponibilité exceptionnelle, fraude présumée). Le Vendeur peut annuler la commande dans ces cas limitatifs, après en avoir informé le Client. Les emails sont conservés à titre de preuve de la transaction. Le Client est invité à les archiver sur un support durable.</p>

        <h3>ARTICLE 7 : PRIX ET PAIEMENT</h3>
        <p>Les prix affichés sur la Marketplace sont exprimés en euros et s'entendent toutes taxes comprises. Les frais de livraison éventuels sont indiqués avant la confirmation définitive de la commande. Le paiement est effectué via des solutions sécurisées proposées par l'Opérateur. Le Client garantit qu'il dispose des autorisations nécessaires pour utiliser le moyen de paiement choisi. Une commission de [X] % du montant de chaque commande est prélevée par l'Opérateur auprès du Vendeur au titre du service de mise en relation.</p>

        <h3>ARTICLE 8 : LIVRAISON</h3>
        <p>Les modalités, délais et coûts de livraison sont indiqués sur chaque Fiche Produit et rappelés lors du parcours de commande. Le Vendeur est seul responsable de l'organisation et de la bonne exécution de la livraison des produits au Client. En cas de retard, perte de colis ou produit endommagé, le Client doit se rapprocher du Vendeur via les outils de messagerie mis à disposition sur la Marketplace.</p>

        <h3>ARTICLE 9 : DROIT DE RÉTRACTATION</h3>
        <p>Lorsque la loi le prévoit, le Client consommateur dispose d'un délai de 14 jours à compter de la réception de son produit pour exercer son droit de rétractation, sans avoir à motiver sa décision ni à supporter d'autres coûts que ceux prévus par la loi. Les modalités d'exercice du droit de rétractation (adresse de retour, formulaire, prise en charge des frais de renvoi, exclusions légales) sont précisées par chaque Vendeur et rappelées au Client lors de la commande.</p>

        <h3>ARTICLE 10 : GARANTIES</h3>
        <p>Les produits vendus via la Marketplace bénéficient des garanties légales applicables (garantie légale de conformité, garantie des vices cachés) et, le cas échéant, de garanties commerciales complémentaires accordées par le Vendeur ou le fabricant. Le Client adresse ses demandes au Vendeur concerné via la messagerie de la Marketplace ou aux coordonnées indiquées sur la Fiche Produit.</p>

        <h3>ARTICLE 11 : RÉCLAMATIONS, LITIGES ET MÉDIATION</h3>
        <p>Pour toute question ou réclamation relative à un produit, à la livraison ou à la facturation, le Client contacte en priorité le Vendeur concerné via la messagerie de la Marketplace. En cas de litige persistant, le Client pourra recourir à un dispositif de médiation de la consommation lorsqu'il y a lieu, selon les modalités précisées dans l'espace d'information légale de la Marketplace.</p>

        <h3>ARTICLE 12 : DONNÉES PERSONNELLES</h3>
        <p>Dans le cadre de la gestion de Alizon et du traitement des commandes, l'Opérateur collecte et traite des données à caractère personnel concernant les Clients et les Vendeurs. Ces traitements sont réalisés conformément à la réglementation applicable et notamment au RGPD. Les finalités, durées de conservation, droits des personnes et modalités d'exercice de ces droits sont détaillés dans les mentions légales accessibles sur la Marketplace.</p>

        <h3>ARTICLE 13 : DROIT APPLICABLE ET JURIDICTION COMPÉTENTE</h3>
        <p>Les présentes CGV sont soumises au droit français. Sous réserve des règles d'ordre public applicables, tout litige relatif à leur interprétation, leur exécution ou leur validité sera soumis aux tribunaux compétents.</p>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-confirm" onclick="closeCGVModal()">Fermer</button>
      </div>
    </div>
  </div>
    </div>
    <script>
    // Fonctions pour CGV
    function openCGVModal() {
        document.getElementById('cgvModal').classList.remove('hidden');
    }
    function closeCGVModal() {
        document.getElementById('cgvModal').classList.add('hidden');
    }

    //récup l'input du numéro de la carte
    const inputCarte = document.getElementById("numCarte");
    inputCarte.addEventListener("input", function() {
        let valeur = this.value;
        valeur = valeur.replace(/\D/g, "");
        valeur = valeur.replace(/(.{4})/g, "$1 ").trim();
        this.value = valeur;
    });

    //récup l'input de la date d'expiration
    const dateInput = document.getElementById("dateExpiration");
    dateInput.addEventListener("input", function() {
        let valeur = this.value;
        valeur = valeur.replace(/\D/g, "");
        if (valeur.length > 2) {
            valeur = valeur.slice(0, 2) + "/" + valeur.slice(2, 4);
        }
        this.value = valeur;
    });

    //récup l'input du CVC
    const cvcInput = document.getElementById("cvc");
    cvcInput.addEventListener("input", function() {
        let valeur = this.value;
        valeur = valeur.replace(/\D/g, "");
        this.value = valeur;
    });

    //a chaque changement de valeur du select
    document.getElementById('adresse').addEventListener('change', function() {
        let champsAdresse = document.getElementById('nouvelleAdresse');
        if (this.value === 'nouvelle') {
            champsAdresse.style.display = 'block';
        } else {
            champsAdresse.style.display = 'none';
        }
    });
    </script>
</body>

</html>