<?php
    session_start();
    $sth = null ;
    $dbh = null ;
    include '../../../selectBDD.php';
    $pdo->exec("SET search_path to cobrec1");

    if(empty($_SESSION['vendeur_id']) === false){
        $_SESSION["remise"]['etat'] = 'pasSvg';
            if (empty($_GET['modifier']) === false){
                //si US modifier reduc
                //print_r("detect modif\n");
                if ((empty($_SESSION["remise"]['_GET'])) || ($_SESSION["remise"]['_GET']['id_reduction'] != $_GET['modifier'])){
                    //print_r('1');
                    //si premier passage
                    try {//Récupération des infos de la reduc
                        $sql = '
                        SELECT id_reduction, reduction_pourcentage, reduction_debut, reduction_fin FROM cobrec1._reduction
                        WHERE id_reduction = :modifier;'
                        ;
                        $stmt = $pdo->prepare($sql);
                        $params = [
                            'modifier' => $_GET['modifier']
                        ];
                        $stmt->execute($params);
                        $_SESSION["remise"]['_GET'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $_SESSION["remise"]['_GET'] = $_SESSION["remise"]['_GET'][0];

                        $sql = '
                        SELECT id_vendeur FROM cobrec1._produit
                        WHERE id_produit = (SELECT id_produit FROM cobrec1._en_reduction WHERE id_reduction = :modifier);'
                        ;
                        $stmt = $pdo->prepare($sql);
                        $params = [
                            'modifier' => $_GET['modifier']
                        ];
                        $stmt->execute($params);
                        $_SESSION["remise"]['_GET']['id_vendeur'] = $stmt->fetch(PDO::FETCH_ASSOC);
                        $_SESSION["remise"]['_GET']['id_vendeur'] = $_SESSION["remise"]['_GET']['id_vendeur']['id_vendeur'];
                        
                    
                    } catch (Exception $e) {
                        $time = time();
                        $_SESSION["remise"]['_GET'] = null;
                        $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] ="verif titre";
                        $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] =$e;
                        $fp = fopen('file.csv', 'w');
                        foreach ($_SESSION["remise"]['bdd_errors'] as $fields) {
                            fputcsv($fp, $fields, ',', '"', '');
                        }
                        fclose($fp);
                    ?>

                    <script>
                        alert("La valeur de la reduc renseigné dans l'URL n'est pas valide. Vous allez être redirigé vers votre catalogue.");
                        document.location.href = "/pages/backoffice/index.php"; 
                    </script>

                    <?php
                    }
                    if($_SESSION["remise"]['_GET']['id_reduction'] == null){
                        $_SESSION["remise"]['_GET'] = null;
                        ?>

                    <script>
                        alert("La valeur de la reduc renseigné dans l'URL ne correspond à aucun produit. Vous allez être redirigé vers votre catalogue.");
                        document.location.href = "/pages/backoffice/index.php"; 
                    </script>

                    <?php
                    }else if ($_SESSION["remise"]['_GET']['id_vendeur'] != $_SESSION['vendeur_id']){
                        print_r($_SESSION["remise"]['_GET']['id_vendeur']);
                        print_r($_SESSION['vendeur_id']);

                        $time = time();
                        $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] ="reduc ne vous appartient pas";
                        $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] =$_SESSION["remise"]['_GET']['id_vendeur'] . ' != ' . print_r($_SESSION['vendeur_id']);

                        $fp = fopen('file.csv', 'w');
                        foreach ($_SESSION["remise"]['bdd_errors'] as $fields) {
                            fputcsv($fp, $fields, ',', '"', '');
                        }
                        fclose($fp);
                        $_SESSION["remise"]['_GET'] = null;
                        ?>

                    <script>
                        alert("La remise que vous essayez de modifier concerne un produit qui ne vous appartient pas. Vous allez être redirigé vers votre catalogue.");
                        document.location.href = "/pages/backoffice/index.php"; 
                    </script>

                    <?php
                    }else{//tout est bon
                        //peuplement de _post et de remise
                        //print_r("peuplement\n");
                        $_POST = [];
                        $_POST["pourcentage"] =null; //$_SESSION["remise"]['_GET']['reduction_pourcentage'];
                        $_POST["debut"] =null; //$_SESSION["remise"]['_GET']['reduction_debut'];
                        $_POST["fin"] =null; //$_SESSION["remise"]['_GET']['fin'];
                        $_SESSION["remise"]['etat'] = 'svg';

                    }
                }
           
            
            }
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" type="text/css" href="../../../../styles/remise/remise.css" media="screen">
    <title>Ébauche de produit</title>
    <link rel="icon" type="image/png" href="../../../img/favicon.svg">
</head>
<pre>
<?php

print_r($_GET);
// print_r($_SESSION["remise"]['_GET']);
print_r($_POST);
print_r($_SESSION["remise"]);
$_SESSION["remise"]["warn"]= 0; //réinitialisation des warnings
if ($_POST !== []) {

} else {//Initialisation des tablaux pour éviter problèmes de comparaison avec des valeurs nulls
    $_POST["pourcentage"] =null;
    $_POST["debut"] =null;
    $_POST["fin"] =null;

}
?></pre>
<body>
    <?php
    //inclusion du bandeau à gauche de la page
    include __DIR__ . '/../../../partials/aside.html';
    ?>
    <main>
        <h2><?php 
        if (empty($_SESSION["remise"]['_GET']['id_reduction'])){
            //si id_reduction n'est pas connu alors on paramètre les autres var du _GET afind 'éviter des warnings php
            //TODO plus tard
            $_SESSION["remise"]['_GET']['reduction_fin'] = '';
            $_SESSION["remise"]['_GET']['reduction_debut'] = '';
            $_SESSION["remise"]['_GET']['reduction_pourcentage'] = '';
            $_SESSION["remise"]['_GET']['id_reduction'] = '';
        }
        if(empty($_SESSION["remise"]['_GET']) == false){
            echo 'Remise non sauvegardée';
        }else{
            echo 'Modifier la remise sauvegardée';
        }
        ?>
        </h2>
        <form action="index.php<?php 
            if($_SESSION["remise"]["_GET"] != null){
                echo '?modifier=' . $_SESSION["remise"]["_GET"]['id_reduction'];
            }
        ?>" method="post" enctype="multipart/form-data">
            
            <div>
                <section style="<?php 
                    if (($_POST["pourcentage"] === '') && ($_POST["debut"] === '') && ($_POST["fin"] === '') ){
                        //
                    }elseif (($_POST["pourcentage"] !== '') && ($_POST["debut"] !== '') && ($_POST["fin"] !== '') ) {
                        //
                    }elseif ($_POST["btn_maj"] == null){//si la zone de promotion n'est que partiellement remplie
                        $_SESSION["remise"]["warn"]++;
                        echo 'border: 3px solid red';
                    }
                ?>">
                <article>
                        <!-- Liste déroulante -->
                        <label for="produit">Produit</label>
                        <br>
                        <select id="produit" name="produit" required>
                        <option value=''></option>
                            <?php
                                if (empty($produit)){
                                    try {//Permets d'obtenir toutes les catégories de produits listées dans la BDD
                                        $sql = 'SELECT id_produit, p_nom FROM cobrec1._produit WHERE id_vendeur = :vendeur ORDER BY p_nom';
                                        $stmt = $pdo->prepare($sql);
                                        $params = [
                                            'vendeur' => $_SESSION['vendeur_id']
                                        ];
                                        $stmt->execute($params);
                                        $produit = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    } catch (Exception $e) {
                                        print_r($e);
                                        $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $e;
                                    }
                                }

                                foreach ($produit as $value) {
                            ?>
                            <option 
                            value="<?php echo $value['id_produit'] ?>" <?php if ($_POST["produit"] == $value['id_produit']){echo 'selected';} ?>>
                            <?php echo $value['p_nom'] ?>
                            </option>
                            <?php } ?>
                        </select>
                        <br />
                    </article>
                    <article>
                        <label for="pourcentage">Pourcentage de promotion</label>
                        <br>
                        <input type="number" id="pourcentage" name="pourcentage" value="<?php echo $_POST["pourcentage"];?>" step="0.01" min="1" max="99" placeholder="20,00" />

                        <br />
                    </article>

                    <article>
                        <label for="debut">Date de début de promotion</label>
                        <br>
                        <input style="<?php if((($_POST["debut"] >= $_POST["fin"]) && (($_POST["debut"] != '') && ($_POST["fin"] != ''))) && ($_POST["btn_maj"] == null)) {echo 'border: 3px solid red';} ?>" type="datetime-local" id="debut" name="debut" placeholer="20/10/2025"
                            value="<?php echo $_POST["debut"]; ?>" min="2025-01-01T00:00" max="2100-01-01T00:00" />
                            <?php
                                if(($_POST["debut"] >= $_POST["fin"]) && (($_POST["debut"] != '') && ($_POST["fin"] != ''))){
                                    ?>
                                    <br>
                                    <small class="warn"><?php
                                        echo $_POST["debut"] . ' '. $_POST["fin"] . ' ' . 'Le premieur horodatage est postérieur (ou égal) au second';
                                        $_SESSION["remise"]["warn"]++;
                                    ?></small>
                                    <?php
                                }
                        ?>
                        <br />
                    </article>

                    <article>
                        <label for="fin">Date de fin de promotion</label>
                        <br>
                        <input style="<?php if((($_POST["debut"] >= $_POST["fin"]) && (($_POST["debut"] != '') && ($_POST["fin"] != ''))) && ($_POST["btn_maj"] == null)) {echo 'border: 3px solid red';} ?>" type="datetime-local" id="fin" name="fin" placeholer="20/10/2025"
                            value="<?php echo $_POST["fin"]; ?>" min="2025-01-01T00:00" max="2100-01-01T00:00" />
                        <br />
                    </article>
                </section>


            </div>
            <input type="button" value="Annuler" title="Permets d'annuler la création de l'article et de revenir au catalogue."/>
            <?php
            if(empty($_SESSION["remise"]['_GET']['id_reduction']) == false){//si la page est en mode US modification
                ?>
                <input type="submit" name="svgModif" title="Sauvegarde les changements sans changer la visibilité de l'article." value="Sauvegarder les modifications" accesskey="s"/>
                <input class="orange" type="submit" name="enLigne"title="Un article en ligne est visible par les clients." value="Mettre en ligne" />
                <input class="orange" type="submit" name="horsLigne"title="Un article hors ligne n'est plus visible que vous." value="Mettre hors ligne" />
                <script>
                    const btnEnLigne = document.querySelector("input[name='enLigne']");
                    const btnHorsLigne = document.querySelector("input[name='horsLigne']");
                </script>
                <?php
                if ($_SESSION["remise"]['etat'] == 'pasSvg'){
                    //si article hors ligne
                ?>
                
                <script>
                    btnHorsLigne.disabled = true;
                    btnEnLigne.disabled = false;
                    //grisage du bouton hors ligne et dégrisage du bouton en ligne
                </script>
            <?php
                }else{//sinon
                    ?>
                    <script>
                    btnHorsLigne.disabled = false;
                    btnEnLigne.disabled = true;
                    //grisage du bouton en ligne et dégrisage du bouton hors ligne
                    </script>
                <?php
                }
            }else{
                ?>
            <input class="orange" type="submit" name="publier"title="Un article publié est inscrit dans la base de données et est visible par les clients." value="Publier la remise dans le catalogue client" />
            <?php } ?>
            </form>
            <script>
            const btnAnnuler = document.querySelector("input[value='Annuler']");
            // const btnEnLigne = document.querySelector("input[name='enLigne']");
            // const btnHorsLigne = document.querySelector("input[name='horsLigne']");
            btnAnnuler.addEventListener('click', () => {//si clic sur Annuler
                if (confirm("Êtes-vous certain de vouloir annuler ? Ce que vous n'avez pas sauvegardé/publié sera perdu.")) {
                    document.location.href="/pages/backoffice/index.php"; 
                }
            });

            function sauvegarder(){//si clic sur sauvegarder et pas de warnings
                alert("Votre article a bien été sauvegardé.");
                document.location.href = "/pages/backoffice/index.php"; 
            }

            function publier(){//si clic sur publier et pas de warnings
                alert("Votre article a bien été publié.");
                document.location.href = "/pages/backoffice/index.php"; 
            }

            function enLigne(){//si clic sur en ligne et pas de warnings
                if (confirm("Votre article a bien été mis en ligne. Souhaitez-vous continuer à modifier l'article ?.")) {
                    btnHorsLigne.disabled = false;
                    btnEnLigne.disabled = true;
                }else{document.location.href="/pages/backoffice/index.php"; 
                }
            }

            function horsLigne(){//si clic sur hors ligne et pas de warnings
                if (confirm("Votre article a bien été mis hors ligne. Souhaitez-vous continuer à modifier l'article ?.")) {
                    btnHorsLigne.disabled = true;
                    btnEnLigne.disabled = false;
                }else{document.location.href="/pages/backoffice/index.php"; 
                }
            }

            function svgModif(){//si clic sur svgModif et pas de warnings
                if (confirm("Vos modifications ont bien été sauvegardées. Souhaitez-vous continuer à modifier l'article ?.")) {
                }else{document.location.href="/pages/backoffice/index.php"; 
                }
            }
        </script>
            <pre>
                <?php 
                    if (($_SESSION["remise"]["warn"] === 0) && ($_POST["titre"] !== '')){
                        $time = time();
                        // print_r("WARNS : " . $_SESSION["remise"]["warn"]);

                            //Si pas de warning et formulaire soumis via le bouton Sauvegarder ou le bouton Annuler

                            //sert à ne pas avoir de warnings php sur le serv
                            if (empty($_POST["publier"])){
                                $_POST["publier"] = '';
                            }
                            if (empty($_POST["horsLigne"])){
                                $_POST["horsLigne"] = '';
                            }
                            if (empty($_POST["enLigne"])){
                                $_POST["enLigne"] = '';
                            }
                            if (empty($_POST["svgModif"])){
                                $_POST["svgModif"] = '';
                            }
                            if ($_POST["publier"] == "Publier la remise dans le catalogue client"){

                            
                        
                            


                            try {//création de l'objet reduction
                                $sql = '
                                INSERT INTO cobrec1._reduction( reduction_pourcentage, reduction_debut, reduction_fin)
                                VALUES (:pourcentage, :debut, :fin);
                                ';
                                $stmt = $pdo->prepare($sql);
                                $params = [
                                    'pourcentage' => $_POST['pourcentage'],
                                    'debut' => $_POST['debut'],
                                    'fin' => $_POST['fin']
                                ];
                                $stmt->execute($params);
                            } catch (Exception $e) {
                                //$_SESSION["remise"]['bdd_errors'] sert pour consulter les erreurs de la BDD
                                $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] ="création de l'objet reduction dans la base";
                                $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $e;
                            }

                            try {//création de l'affiliation entre reduction et produit
                                $sql = '
                                INSERT INTO cobrec1._en_reduction(id_produit,id_reduction)
                                VALUES (
                                :produit, :id_reduc);
                                ';
                                $stmt = $pdo->prepare($sql);
                                $params = [
                                    'id_reduc' => $pdo->lastInsertId(), 
                                    'produit' => $_POST['produit']
                                ];
                                $stmt->execute($params);
                            } catch (Exception $e) {
                                $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] ="création de l'affiliation entre reduction et produit";
                                $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $e;
                            }
                            
                            if ($_POST["publier"] == "Publier la remise dans le catalogue client"){
                        
                    ?>
                    <script>
                        publier();
                    </script>
    <?php
                    }
                    
                }else if (($_POST["horsLigne"] == "Mettre hors ligne") || ($_POST["enLigne"] == "Mettre en ligne")){
                    //Si pas de warning et formulaire soumis via le bouton Mettre en ligne/hors ligne
                    $time = time();
                    
                    try {//modif de l'objet reduction dans la base
                        $sql = '
                        UPDATE cobrec1._reduction 
                        SET reduction_pourcentage = :pourcentage, 
                        reduction_debut = :debut, 
                        reduction_fin = :fin   
                        WHERE id_reduction = :getId;
                        ';
                        $stmt = $pdo->prepare($sql);
                        $params = [
                            'pourcentage' => $_POST['pourcentage'],
                            'debut' => $_POST['debut'],
                            'fin' => $_POST['fin'],
                            'getId' => $_SESSION["remise"]['_GET']['id_reduction']
                        ];
                        $stmt->execute($params);
                    } catch (Exception $e) {
                        //$_SESSION["remise"]['bdd_errors'] sert pour consulter les erreurs de la BDD
                        $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] ="modif de l'objet reduction dans la base";
                        //print_r($_POST);
                        // foreach ($_POST as $value) {
                        //     $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $key . ' : ' ;
                        //     $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $value ;
                        // }
                        // $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $e;
                    }

                    if ($_SESSION["remise"]['_GET']['id_produit'] != $_POST['produit']){
                        try {//modif de l'affiliation entre catégorie et produit
                            $sql = '
                            UPDATE cobrec1._en_reduction
                            SET id_produit = :produit
                            WHERE id_reduction = :getId;
                            ';
                            $stmt = $pdo->prepare($sql);
                            $params = [
                                'produit' => $_POST['produit'],
                                'getId' => $_SESSION["remise"]['_GET']['id_reduction']
                            ];
                            $stmt->execute($params);
                        } catch (Exception $e) {
                            $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] ="modif de l'affiliation entre reduction et produit";
                            $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $e;
                        }
                    }



                    if ($_POST["horsLigne"] == "Mettre hors ligne"){
                        ?>
                    <script>
                        horsLigne();
                    </script>
                    <?php 
                    

                
                    }else if ($_POST["enLigne"] == "Mettre en ligne"){

                        
                    ?>
                    <script>
                        enLigne();
                    </script>

    <?php
                    }
                }

                if (empty($_SESSION["remise"]['bdd_errors']) !== true){
                    //Sert pour consulter les erreurs de la BDD via un fichier dédié
                    $fp = fopen('file.csv', 'w');
                    foreach ($_SESSION["remise"]['bdd_errors'] as $fields) {
                        fputcsv($fp, $fields, ',', '"', '');
                    }
                    fclose($fp);
                }
                

            }
            if (($_SESSION["remise"]["warn"] === 0) && ($_POST["titre"] !== '') && ($_POST["publier"] == "Publier la remise dans le catalogue client")){
                //nettoyage
                $_POST = [];
                $_SESSION["remise"] = [];
            }
            $sth = null ;
            $dbh = null ;
    ?>
            </pre>
        </main>
    </body>
</html>

<?php
        //}else{//si l'utilisateur est un client
?>
<script>
    //alert("Vous êtes connecté avec un compte client. Vous allez être redirigé vers la page d'accueil des clients.");
    //document.location.href = "/index.php"; 
    
</script>
<?php
        //}
    }else{//si l'utilisateur n'est pas connecté

?>

<script>
    alert("Vous n'êtes pas connecté. Vous allez être redirigé vers la page de connexion.");
    document.location.href = "/pages/backoffice/connexionVendeur/index.php";
</script>
<?php
    }

?>