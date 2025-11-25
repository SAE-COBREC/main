<?php
    session_start();
    $sth = null ;
    $dbh = null ;
    include '../../../selectBDD.php';
    $pdo->exec("SET search_path to cobrec1");
    const NB_IMGS_MAX = 3;
    const EMPLACEMENT_DES_IMGS = '/img/photo/';

    if(empty($_SESSION['vendeur_id']) === false){
            if (empty($_GET['modifier']) === false){
                //si US modifier produit
                //print_r("detect modif\n");
                if ((empty($_SESSION["creerArticle"]['_GET'])) || ($_SESSION["creerArticle"]['_GET']['id_produit'] != $_GET['modifier'])){
                    //print_r('1');
                    //si premier passage
                    try {//Récupération des infos du produit
                        $sql = '
                        SELECT * FROM cobrec1._produit 
                        WHERE id_produit = ' . $_GET['modifier'] . ';'
                        ;
                        $stmt = $pdo->query($sql);
                        $_SESSION["creerArticle"]['_GET'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $_SESSION["creerArticle"]['_GET'] = $_SESSION["creerArticle"]['_GET'][0];
                        
                        $sql = '
                        SELECT id_categorie FROM cobrec1._fait_partie_de 
                        WHERE id_produit = ' . $_GET['modifier'] . ';'
                        ;
                        $stmt = $pdo->query($sql);
                        $_SESSION["creerArticle"]['_GET']['id_categorie'] = $stmt->fetch(PDO::FETCH_ASSOC);
                        $_SESSION["creerArticle"]['_GET']['id_categorie'] = $_SESSION["creerArticle"]['_GET']['id_categorie']['id_categorie'];
                        
                        $sql = '
                        SELECT id_image FROM cobrec1._represente_produit
                        WHERE id_produit = ' . $_GET['modifier'] . ';'
                        ;
                        $stmt = $pdo->query($sql);
                        $_SESSION["creerArticle"]['_GET']['imgs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($_SESSION["creerArticle"]['_GET']['imgs'] as $key => $value) {
                            $sql = '
                            SELECT * FROM cobrec1._image 
                            WHERE id_image = ' . $value['id_image'] . ';'
                            ;
                            $stmt = $pdo->query($sql);
                            $_SESSION["creerArticle"]['_GET']['imgs'][$key] = $stmt->fetch(PDO::FETCH_ASSOC);
                        }
                        
                    
                    } catch (Exception $e) {
                        $_SESSION["creerArticle"]['_GET'] = null;
                    ?>

                    <script>
                        alert("La valeur du produit renseigné dans l'URL n'est pas valide. Vous allez être redirigé vers votre catalogue.");
                        document.location.href = "/pages/backoffice/index.php"; 
                    </script>

                    <?php
                    }
                    if($_SESSION["creerArticle"]['_GET']['id_produit'] == null){
                        $_SESSION["creerArticle"]['_GET'] = null;
                        ?>

                    <script>
                        alert("La valeur du produit renseigné dans l'URL ne correspond à aucun produit. Vous allez être redirigé vers votre catalogue.");
                        document.location.href = "/pages/backoffice/index.php"; 
                    </script>

                    <?php
                    }else if ($_SESSION["creerArticle"]['_GET']['id_vendeur'] != $_SESSION['vendeur_id']){
                        $_SESSION["creerArticle"]['_GET'] = null;
                        ?>

                    <script>
                        alert("Le produit que vous essayez de modifier ne vous appartient pas. Vous allez être redirigé vers votre catalogue.");
                        document.location.href = "/pages/backoffice/index.php"; 
                    </script>

                    <?php
                    }else{//tout est bon
                        //peuplement de _post et de creerArticle
                        //print_r("peuplement\n");
                        $_FILES["photo"]["name"] = [];
                        $_SESSION["creerArticle"]["tmp_file"]["name"] = [];
                        $_SESSION["creerArticle"]["tmp_file"]["tmp_name"] = [];
                        $_SESSION["creerArticle"]["_FILES"]['photo']['name'] = [];
                        foreach ($_SESSION["creerArticle"]['_GET']['imgs'] as $key => $value) {
                            $_SESSION["creerArticle"]["_FILES"]['photo']['name'][$key] = $value['i_title'];
                            $_SESSION["creerArticle"]["_FILES"]['photo']['tmp_name'][$key] = str_replace('/img/photo', '../../../img/photo',$value['i_lien']);
                            // $_SESSION["creerArticle"]["_FILES"]['photo']['name'][$key] = str_replace("'","''",$_SESSION["creerArticle"]["_FILES"]['photo']['name'][$key]);
                            // $_SESSION["creerArticle"]["_FILES"]['photo']['tmp_name'][$key] = str_replace("'","''",$_SESSION["creerArticle"]["_FILES"]['photo']['tmp_name'][$key]);
                            $_SESSION["creerArticle"]["_FILES"]['photo']['name'][$key] = str_replace(' ','_',$_SESSION["creerArticle"]["_FILES"]['photo']['name'][$key]);
                            $_SESSION["creerArticle"]["_FILES"]['photo']['tmp_name'][$key] = str_replace(' ','_',$_SESSION["creerArticle"]["_FILES"]['photo']['tmp_name'][$key]);
                            if (file_exists($_SESSION["creerArticle"]["_FILES"]['photo']['tmp_name'][$key])){
                                copy($_SESSION["creerArticle"]["_FILES"]['photo']['tmp_name'][$key], 
                                'temp_/' . $_SESSION["creerArticle"]["_FILES"]['photo']['name'][$key]);
                            }
                        }
                        $_POST = [];
                        $_POST["titre"] = str_replace("''","'", $_SESSION["creerArticle"]['_GET']['p_nom']);
                        $_POST["description"] = str_replace("''","'", $_SESSION["creerArticle"]['_GET']['p_description']);
                        $_POST["photo"] ='';
                        $_POST["pourcentage"] =null;
                        $_POST["debut"] =null;
                        $_POST["fin"] =null;
                        $_POST["tva"] = $_SESSION["creerArticle"]['_GET']['id_tva'];
                        $_POST["categorie"] = $_SESSION["creerArticle"]['_GET']['id_categorie'];
                        $_POST["origine"] = $_SESSION["creerArticle"]['_GET']['p_origine'];
                        // $_POST["couleur"] =null;
                        $_POST["poids"] = $_SESSION["creerArticle"]['_GET']['p_poids'];
                        $_POST["volume"] = $_SESSION["creerArticle"]['_GET']['p_volume'];
                        $_POST["stock"] = $_SESSION["creerArticle"]['_GET']['p_stock'];
                        $_POST["prix"] = $_SESSION["creerArticle"]['_GET']['p_prix'];

                    }
                }
           
            
            }

            function RemplacerCaracteresProblematiquesDansChampsTextes($contenuChamp){
                $contenuChamp = str_replace("'", "''",$contenuChamp);
                $contenuChamp = str_replace('"', '""',$contenuChamp);
                return $contenuChamp;
            }
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" type="text/css" href="../../../../styles/creerArticle/creerArticle.css" media="screen">
    <title>Ébauche de produit</title>
    <link rel="icon" type="image/png" href="../../../img/favicon.svg">
</head>
<pre>
<?php

// print_r($_GET);
// print_r($_SESSION["creerArticle"]['_GET']);
// print_r($_POST);
// print_r($_SESSION["creerArticle"]);
// print_r($_FILES);
$_SESSION["creerArticle"]["warn"]= 0; //réinitialisation des warnings
$warnPromo = false;
if ($_POST !== []) {//Si le formulaire a été submit au moins une fois

    function decalage($aDecaler){
        //unlink('temp_/' . $_SESSION["creerArticle"]["_FILES"]["photo"]["name"][$aDecaler-1]);
        if (empty($_SESSION["creerArticle"]["_FILES"]["photo"]["name"][$aDecaler])){
            //L'image $aDecaler n'existe pas. Décalage interrompu. Suppression de l'image $aDecaler-1.
            unset($_SESSION["creerArticle"]["_FILES"]["photo"]["name"][$aDecaler-1]);
            unset($_SESSION["creerArticle"]["_FILES"]["photo"]["tmp_name"][$aDecaler-1]);
        }else{
            //L'image $aDecaler existe. Décalage. L'image $aDecaler-1 originale est écrasée.
            $_SESSION["creerArticle"]["_FILES"]["photo"]["name"][$aDecaler-1] = $_SESSION["creerArticle"]["_FILES"]["photo"]["name"][$aDecaler];
            $_SESSION["creerArticle"]["_FILES"]["photo"]["tmp_name"][$aDecaler-1] = $_SESSION["creerArticle"]["_FILES"]["photo"]["tmp_name"][$aDecaler];
        }
    }

    function supprDeTemp($aSuppr){
        unset($_SESSION["creerArticle"]["tmp_file"]["tmp_name"][array_search($_SESSION["creerArticle"]["_FILES"]["photo"]["name"][$aSuppr], $_SESSION["creerArticle"]["tmp_file"]["name"])]);
        unset($_SESSION["creerArticle"]["tmp_file"]["name"][array_search($_SESSION["creerArticle"]["_FILES"]["photo"]["name"][$aSuppr], $_SESSION["creerArticle"]["tmp_file"]["name"])]);
    }

    function supprDeDef(){
        //unlink('temp_/' . $_SESSION["creerArticle"]["_FILES"]["photo"]["name"][NB_IMGS_MAX-1]);
        unset($_SESSION["creerArticle"]["_FILES"]["photo"]["name"][NB_IMGS_MAX-1]);
        unset($_SESSION["creerArticle"]["_FILES"]["photo"]["tmp_name"][NB_IMGS_MAX-1]);
    }
    if (empty($_POST["btn_moins0"]) == false){
        //suppression de l'image 0 dans $_SESSION["creerArticle"]["tmp_file"]
        supprDeTemp(0);
        //écrasement de l'image 0 originale par l'image 1
        decalage(1);
        //écrasement de l'image 1 originale par l'image 2
        decalage(2);
        //L'image 2 est supprimée pour éviter toute duplication résultante du décalage.
        supprDeDef();
    }elseif (empty($_POST["btn_moins1"]) == false){
        //suppression de l'image 1 dans $_SESSION["creerArticle"]["tmp_file"]
        supprDeTemp(1);
        //écrasement de l'image 1 originale par l'image 2
        decalage(2);
        //L'image 2 est supprimée pour éviter toute duplication résultante du décalage.
        supprDeDef();
    }elseif (empty($_POST["btn_moins2"]) == false){
        //suppression de l'image 2 dans $_SESSION["creerArticle"]["tmp_file"]
        supprDeTemp(2);
        //L'image 2 est supprimée pour éviter toute duplication résultante du décalage.
        supprDeDef();
    }

    // if (empty($_FILES["photo"]["name"][0])){
    //     $_FILES["photo"]["name"][0] = '';
    // }
    if (empty($_FILES["photo"]["name"][0]) == false){//Si au moins un fichier a été déposé

        foreach ($_FILES["photo"]["name"] as $key => $value) {//chgt noms pour éviter pbs lors du déplacement de l'img
            $_FILES["photo"]["name"][$key] = str_replace(' ', '_',$_FILES["photo"]["name"][$key]);
            $_FILES["photo"]["name"][$key] = str_replace("'", "_",$_FILES["photo"]["name"][$key]);
            $_FILES["photo"]["name"][$key] = str_replace('"', '_',$_FILES["photo"]["name"][$key]);
            if ($_FILES["photo"]["size"][$key] > 5 * 1024 * 1024){//si fichier trop volumineux
                $_SESSION['creerArticle']['imageTropVolumineuse'] = true;
                unset($_FILES["photo"]["name"][$key]);
                unset($_FILES["photo"]["tmp_name"][$key]);
            }
            if (str_starts_with($_FILES["photo"]["type"][$key], 'image/') == false){
                unset($_FILES["photo"]["name"][$key]);
                unset($_FILES["photo"]["tmp_name"][$key]);
                $_SESSION['creerArticle']['pasImage'] = true;
            }
        }

        foreach ($_SESSION["creerArticle"]["tmp_file"]["name"] as $key => $value) {//suppression de tous les éléménts de $_SESSION["creerArticle"]["tmp_file"]
            //sert à éviter de se retrouver avec des images censées avoir été oubliées
            unset($_SESSION["creerArticle"]["tmp_file"]["name"][$key]);
            unset($_SESSION["creerArticle"]["tmp_file"]["tmp_name"][$key]);
        }

        foreach ($_SESSION["creerArticle"]["_FILES"]["photo"]["name"] as $key => $value) {//repeuplement de $_SESSION["creerArticle"]["tmp_file"] avec $_SESSION["creerArticle"]["_FILES"]
            //sert à préserver les images auparavant enregistrées 
            $_SESSION["creerArticle"]["tmp_file"]["name"][$key] = 
                $_SESSION["creerArticle"]["_FILES"]["photo"]["name"][$key];
            $_SESSION["creerArticle"]["tmp_file"]["tmp_name"][$key] = 
                $_SESSION["creerArticle"]["_FILES"]["photo"]["tmp_name"][$key];
        }

        for ($i=0; (($i<NB_IMGS_MAX) && ($i < count($_FILES["photo"]["name"]))) ; ) {//transfert des 3 dernières images de _FILES
            $_SESSION["creerArticle"]["tmp_file"]["name"][] = 
                $_FILES["photo"]["name"][count($_FILES["photo"]["name"])-1-$i];
            $_SESSION["creerArticle"]["tmp_file"]["tmp_name"][] = 
                $_FILES["photo"]["tmp_name"][count($_FILES["photo"]["name"])-1-$i];
            $i++;
        }
        $i=0;
        foreach ($_SESSION["creerArticle"]["tmp_file"]["name"] as $key => $value) {//transfert des 3 dernières images de $_SESSION["creerArticle"]["tmp_file"]
            //permet d'éviter problèmes causés par suppression d'images
            $_SESSION["creerArticle"]["_FILES"]["photo"]["name"][(count($_SESSION["creerArticle"]["tmp_file"]["name"])-1-$i) % NB_IMGS_MAX] = 
                $value;
            $_SESSION["creerArticle"]["_FILES"]["photo"]["tmp_name"][(count($_SESSION["creerArticle"]["tmp_file"]["tmp_name"])-1-$i) % NB_IMGS_MAX] = 
                $_SESSION["creerArticle"]["tmp_file"]["tmp_name"][$key];
            $i++;
            $i %= NB_IMGS_MAX;
        }
            
        
        foreach ($_SESSION["creerArticle"]["_FILES"]["photo"]["name"] as $key => $value) {
            if ($value === null){//permet d'avoir un affichage qui ne parait pas bizarre
                unset($_SESSION["creerArticle"]["_FILES"]["photo"]["name"][$key]);
                unset($_SESSION["creerArticle"]["_FILES"]["photo"]["tmp_name"][$key]);
            }
        }
    }

} else {//Initialisation des tablaux pour éviter problèmes de comparaison avec des valeurs nulls
    $_FILES["photo"]["name"] = [];
    $_SESSION["creerArticle"]["_FILES"]['photo']['name'] = [];
    $_SESSION["creerArticle"]["tmp_file"]["name"] = [];
    $_SESSION["creerArticle"]["tmp_file"]["tmp_name"] = [];
    $_SESSION["creerArticle"]["_FILES"]['photo']['tmp_name'] = [];
    $_SESSION['creerArticle']['imageTropVolumineuse'] = false;
    $_SESSION['creerArticle']['pasImage'] = false;
    $_POST["titre"] = '';
    $_POST["description"] ='';
    $_POST["photo"] ='';
    $_POST["pourcentage"] =null;
    $_POST["debut"] =null;
    $_POST["fin"] =null;
    $_POST["tva"] =null;
    // $_POST["couleur"] =null;
    $_POST["poids"] =null;
    $_POST["volume"] =null;
    $_POST["origine"] =null;
    $_POST["categorie"] =null;
    $_POST["btn_maj"] =null;
    $_POST["stock"] =null;
    $_POST["prix"] =null;

}
?></pre>
<body>
    <?php
    //inclusion du bandeau à gauche de la page
    include __DIR__ . '/../../../partials/aside.html';
    ?>
    <main>
        <h2><?php 
        if (empty($_SESSION["creerArticle"]['_GET']['id_produit'])){
            //si id_produit n'est pas connu alors on paramètre les autres var du _GET afind 'éviter des warnings php
            $_SESSION["creerArticle"]['_GET']['p_statut'] = '';
            $_SESSION["creerArticle"]['_GET']['p_nom'] = '';
            $_SESSION["creerArticle"]['_GET']['id_produit'] = '';
        }
        if(($_SESSION["creerArticle"]['_GET'] == null) || ($_SESSION["creerArticle"]['_GET']['p_statut'] == 'Ébauche')){
            echo 'Ébauche de produit';
        }else if(($_SESSION["creerArticle"]['_GET']['p_statut'] == 'Hors ligne')){
            echo 'Produit hors ligne';
        }else{
            echo 'Produit en ligne';
        }
        ?>
        </h2>
        <form action="index.php<?php 
            if($_SESSION["creerArticle"]["_GET"] != null){
                echo '?modifier=' . $_SESSION["creerArticle"]["_GET"]['id_produit'];
            }
        ?>" method="post" enctype="multipart/form-data">
            <!-- Boutons de soumission principaux -->
            <input type="button" value="Annuler" title="Permets d'annuler la création de l'article et de revenir au catalogue."/>
            <?php
            if(empty($_SESSION["creerArticle"]['_GET']['id_produit']) == false){//si la page est en mode US modification
                ?>
                <input type="submit" name="svgModif" title="Sauvegarde les changements sans changer la visibilité de l'article." value="Sauvegarder les modifications" />
                <input class="orange" type="submit" name="enLigne"title="Un article en ligne est visible par les clients." value="Mettre en ligne" />
                <input class="orange" type="submit" name="horsLigne"title="Un article hors ligne n'est plus visible que vous." value="Mettre hors ligne" />
                <script>
                    const btnPrincipauxModif = document.querySelectorAll("input");
                </script>
                <?php
                if (($_SESSION["creerArticle"]['_GET']['p_statut'] == 'Hors ligne') || ($_SESSION["creerArticle"]['_GET']['p_statut'] == 'Ébauche')){
                    //si article hors ligne
                ?>
                
                <script>
                    btnPrincipauxModif[2].disabled = false;
                    btnPrincipauxModif[3].disabled = true;
                    //grisage du bouton hors ligne et dégrisage du bouton en ligne
                </script>
            <?php
                }else{//sinon
                    ?>
                    <script>
                    btnPrincipauxModif[3].disabled = false;
                    btnPrincipauxModif[2].disabled = true;
                    //grisage du bouton en ligne et dégrisage du bouton hors ligne
                    </script>
                <?php
                }
            }else{
                ?>
            <input type="submit" name="sauvegarder" title="Un article sauvegardé est inscrit dans la base de données mais n'est visible que par vous." value="Sauvegarder l'ébauche" />
            <input class="orange" type="submit" name="publier"title="Un article publié est inscrit dans la base de données et est visible par les clients." value="Publier le produit dans le catalogue client" />
            <?php } ?>
            <div>
                <section>
                    <h3><?php 
                        if($_SESSION["creerArticle"]['_GET'] == null){
                            echo 'Ajouter un produit';
                        }else{
                            echo 'Modifier un produit';
                        }
                        ?></h3>
                    <article>
                        <!-- Texte avec label -->
                         <!-- <pre> -->
                        <?php
                        //fonction RemplacerCaracteresProblematiquesDansChampsTextes non utilisé car cas spécial
                        $titre_pour_bdd = str_replace("'","''",$_POST['titre']);
                        $titre_pour_bdd = str_replace('"','""',$titre_pour_bdd);

                        if(($_SESSION["creerArticle"]['_GET'] != null) && (($_SESSION["creerArticle"]['_GET']['p_nom'] == $_POST['titre']) || ($_SESSION["creerArticle"]['_GET']['p_nom'] == $titre_pour_bdd))){
                            $titre = '';
                        }else{
                            try {//cherche dans la BDD pour voir si le nom n'est pas déjà pris
                                $sql = 'SELECT p_nom FROM cobrec1._produit where p_nom = ' . "'" . $titre_pour_bdd ."'";
                                $stmt = $pdo->query($sql);
                                $titre = $stmt->fetch(PDO::FETCH_ASSOC);
                            } catch (Exception $e) {
                                print_r($e);
                            }
                        }
                        ?>
<!-- </pre> -->
                        <label for="titre">Titre</label>
                        <br>
                        <input 
                            minlength="4"
                            maxlength="100"
                            pattern="[\[\]\(\)'&0-9a-zA-ZàâäéèêëîïôöùûüÿçæœÀÂÄÇÉÈÊËÎÏÔÖÙÛÜŸÆŒ+\-=°: .;,!? ]+"
                            required
                        style="<?php 
                            
                            if (($titre != '') && (empty($_POST["btn_maj"]))) {echo 'border: 3px solid red';} ?>" type="text" id="titre" name="titre" value="<?php echo $_POST["titre"]; 
                        
                        ?>" />
                            <?php
                        if($titre != ''){
                            ?>
                            <br>
                            <small class="warn"><?php
                                echo 'Le titre de votre article est déjà pris. Veuillez choisir un autre titre';
                                $_SESSION["creerArticle"]["warn"]++;
                            ?></small>
                        <?php } ?>
                        <br>
                        <small>Seuls les caractères alphanumériques d'usage en français, les signes de ponctuation et les caractères suivants +-=°[]()' sont autorisés.</small>
                        <br /> 
                    </article>

                    <article>
                        <!-- Texte multiligne -->
                        <label for="description">Description</label>
                        <br>
                        <textarea id="description" name="description" rows="5" cols="60"
                        minlength="4"
                        maxlength="9999"
                        pattern="[\[\]\(\)'&0-9a-zA-ZàâäéèêëîïôöùûüÿçæœÀÂÄÇÉÈÊËÎÏÔÖÙÛÜŸÆŒ+\-=°: .;,!? ]+"
                        required><?php echo $_POST["description"]; ?></textarea>
                        <br>
                        <small>Seuls les caractères alphanumériques d'usage en français, les signes de ponctuation et les caractères suivants +-=°[]()' sont autorisés.</small>
                        <br /> 
                    </article>
                    <article>
                        <!-- Liste déroulante -->
                        <label for="categorie">Catégorie</label>
                        <br>
                        <select id="categorie" name="categorie" required>
                        <option value=''></option>
                            <?php
                                try {//Permets d'obtenir toutes les catégories de produits listées dans la BDD
                                    $sql = 'SELECT id_categorie, nom_categorie FROM cobrec1._categorie_produit';
                                    $stmt = $pdo->query($sql);
                                    $categorie = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    //print_r($categorie);
                                } catch (Exception $e) {
                                    //print_r($e);
                                }

                                foreach ($categorie as $value) {
                            ?>
                            <option 
                            value="<?php echo $value['id_categorie'] ?>" <?php if ($_POST["categorie"] == $value['id_categorie']){echo 'selected';} ?>>
                            <?php echo $value['nom_categorie'] ?>
                            </option>
                            <?php } ?>
                        </select>
                        <br />
                    </article>
                </section>
                <section>
                    <article>
                        <!-- Téléversement de photo -->
                        <h3>Photo(s)</h3>
                        <label for="photo[]">Sélectionner un fichier</label>
                        <br>
                         <input style="<?php 
                        if (empty($_POST["btn_maj"])){
                            $_POST["btn_maj"] = '';
                        }
                        if ((($_SESSION["creerArticle"]["_FILES"]["photo"]["name"] === [])) && ($_POST["btn_maj"] == null)) {echo 'border: 3px solid red';} ?>" type="file" multiple id="photo[]" name="photo[]" accept="image/*" value="<?php
                        if (empty($_SESSION["creerArticle"]["_FILES"]["photo"]["tmp_name"][0])){
                            //si vide alors
                            $_SESSION["creerArticle"]["_FILES"]["photo"]["tmp_name"][0] = '';
                        }
                        if (empty($_SESSION["creerArticle"]["_FILES"]["photo"]["full_path"][0])){
                            //si vide alors
                            $_SESSION["creerArticle"]["_FILES"]["photo"]["full_path"][0] = '';
                        }
                        echo $_SESSION["creerArticle"]["_FILES"]["photo"]["tmp_name"][0] . '/' . $_SESSION["creerArticle"]["_FILES"]["photo"]["full_path"][0];
                        ?>" />
                        <?php
                        
                        if (($_SESSION["creerArticle"]["_FILES"]["photo"]["name"] === [])) {
                            ?>
                            <br>

                            <small class="warn"><?php
                            echo 'Veuillez téléverser au moins une photographie.';
                            $_SESSION["creerArticle"]["warn"]++;
                            ?></small>
                            <?php
                        }
                        if (empty($_SESSION['creerArticle']['imageTropVolumineuse'])){
                            $_SESSION['creerArticle']['imageTropVolumineuse'] = false;
                        }
                        if($_SESSION['creerArticle']['imageTropVolumineuse']){
                            ?>
                            <br>

                            <small class="warn"><?php
                            echo 'Au moins une de vos images fait plus de 5MB. Les images dépassant la taille maximale ne seront pas téléversés.';
                            $_SESSION["creerArticle"]["warn"]++;
                            $_SESSION['creerArticle']['imageTropVolumineuse'] = false;
                            ?></small>
                            <?php
                        }
                        
                        if (empty($_SESSION['creerArticle']['pasImage'])){
                            $_SESSION['creerArticle']['pasImage'] = false;
                        }
                        if($_SESSION['creerArticle']['pasImage']){
                            ?>
                            <br>

                            <small class="warn"><?php
                            echo "Au moins un de vos fichiers n'est pas une image." . " Les fichiers n'étant pas des images" . ' ne seront pas téléversés.';
                            $_SESSION["creerArticle"]["warn"]++;
                            $_SESSION['creerArticle']['pasImage'] = false;
                            ?></small>
                            <?php
                        }
                        
                        ?>
                        <br>
                        <small><?php 
                            if(empty($_SESSION["creerArticle"]["_FILES"]["photo"]["name"])){
                                //si aucune photo alors indiquer 0
                                echo 0;
                            }else{
                                echo count($_SESSION["creerArticle"]["_FILES"]["photo"]["name"]);
                            }
                        ?>
                            /<?php echo NB_IMGS_MAX;?></small>
                        
                        <?php
                        
                        foreach ($_SESSION["creerArticle"]["_FILES"]["photo"]["name"] as $key => $value) {
                            ?><br>
                            <small>
                                <img src="<?php 
                                if ((str_starts_with($_SESSION["creerArticle"]["_FILES"]["photo"]["tmp_name"][$key],'https://')) || (str_starts_with($_SESSION["creerArticle"]["_FILES"]["photo"]["tmp_name"][$key],'http://'))){
                                    //sert à bien afficher les images qui ne sont pas en local
                                    echo $_SESSION["creerArticle"]["_FILES"]["photo"]["tmp_name"][$key];
                                }else{
                                    echo 'temp_/' . $value;
                                }
                                ?>" height="25">
                                <?php 
                                echo $value; ?>
                                <input type="submit" name="btn_moins<?php echo $key ?>" title="Permets de supprimer l'image qui est en face." value="-" />
                            </small>
                            <?php } ?>
                        <br>
                        <input type="submit" name="btn_maj" title="Cliquez ici pour voir les images que vous avez déposées !" value="Voir les images ayant été déposées" />
                        <br />
                    </article>
                </section>

                <section>
                    <article>
                        <label for="stock">Stock</label>
                        <br>
                        <input type="number" id="stock" name="stock" value="<?php echo $_POST["stock"]; ?>" placeholder="0" required
                            min="0" max="999999999" value="0">
                        <br />
                    </article>

                    <article>
                        <label for="prix">Prix</label>
                        <br>
                        <input type="number" id="prix" name="prix" value="<?php echo $_POST["prix"]; ?>" step="0.01"
                            min="0.01" max="999999999.99" placeholder="0.00 €" required>
                        <br />
                    </article>

                    <article>
                        <label for="poids">Poids (en kg)</label>
                        <br>
                        <input type="number" id="poids" name="poids" value="<?php echo $_POST["poids"]; ?>" step="0.01"
                            min="0.01" max="999999999.99" placeholder="0.01" required><small> kg</small>
                        <br />
                    </article>

                    <article>
                        <label for="volume">Volume (en m<sup>3</sup>)</label>
                        <br>
                        <input type="number" id="volume" name="volume" value="<?php echo $_POST["volume"]; ?>" step="0.01"
                            min="0.01" max="999999999.99" placeholder="0.01" required><small> m<sup>3</sup></small>
                        <br />
                    </article>

                    <article>
                        <!-- Liste déroulante -->
                        <label for="tva">TVA</label>
                        <br>
                        <select id="tva" name="tva" >
                            <?php
                                try {//Permets d'obtenir toutes les TVA listées dans la BDD
                                    $sql = 'SELECT * FROM cobrec1._tva';
                                    $stmt = $pdo->query($sql);
                                    $tva = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                } catch (Exception $e) {
                                    //print_r($e);
                                }

                                foreach ($tva as $value) {
                            ?>
                            <option value="<?php echo $value['id_tva']; ?>" <?php if ($_POST["tva"] == $value['id_tva']){echo 'selected';} ?>>
                                <?php echo $value['montant_tva'] . ' (' . $value['libelle_tva'] . ')'?>
                            </option>
                            <?php } ?>
                        </select>
                        <br />
                    </article>

                    <article>
                        <!-- Liste déroulante -->
                        <label for="origine">Origine</label>
                        <br>
                        <select id="origine" name="origine" required>
                        <option value=''></option>
                        <option 
                        value="Bretagne" <?php if ($_POST["origine"] == 'Bretagne'){echo 'selected';} ?>>
                        Bretagne
                        </option>
                        <option 
                        value="France" <?php if ($_POST["origine"] == 'France'){echo 'selected';} ?>>
                        France
                        </option>
                        <option 
                        value="UE" <?php if ($_POST["origine"] == 'UE'){echo 'selected';} ?>>
                        Union européenne
                        </option>
                        <option 
                        value="Hors UE" <?php if ($_POST["origine"] == 'Hors UE'){echo 'selected';} ?>>
                        Hors Union européenne
                        </option>
                        </select>
                        <br />
                    </article>

                    

                    <!-- <article>
                        
                        <label for="couleur">Couleur</label>
                        <br>
                        <select id="couleur" name="couleur" >
                        <option value=''></option>
                            <?php
                                // try {
                                //     $sql = 'SELECT * FROM cobrec1._couleur';
                                //     $stmt = $pdo->query($sql);
                                //     $tva = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                // } catch (Exception $e) {
                                //     print_r($e);
                                // }

                                // foreach ($tva as $value) {
                            ?>
                            <option value="<?php 
                            //echo $value['code_hexa']; ?>
                            " <?php 
                            //if ($_POST["couleur"] == $value['code_hexa']){echo 'selected';} ?>>
                                <?php //if($value['type_couleur'] != ''){echo $value['nom'] . ' (' . $value['type_couleur'] . ')';}else{echo $value['nom'];}?>
                            </option>
                            <?php //} ?>
                        </select>
                        <br />
                    </article> -->

                </section>

                <!-- <section style="<?php 
                    // if (($_POST["pourcentage"] === '') && ($_POST["debut"] === '') && ($_POST["fin"] === '') ){
                    //     //
                    // }elseif (($_POST["pourcentage"] !== '') && ($_POST["debut"] !== '') && ($_POST["fin"] !== '') ) {
                    //     //
                    // }elseif ($_POST["btn_maj"] == null){//si la zone de promotion n'est que partiellement remplie
                    //     $_SESSION["creerArticle"]["warn"]++;
                    //     $warnPromo = true;
                    //     echo 'border: 3px solid red';
                    // }
                ?>"> -->
                    <article>
                        <!-- <p><strong>(Zone facultative)</strong></p>
                        <?php 
                            // if ($warnPromo){
                        ?>
                        <small class="warn"><?php
                            // echo 'Veuillez remplir tous les champs du bloc promotion ou n\'en remplir aucun.';
                            // $_SESSION["creerArticle"]["warn"]++;
                        ?></small>
                        <br>
                        <?php
                            //}
                        ?>
                        <label for="pourcentage">Pourcentage de promotion</label>
                        <br>
                        <input type="number" id="pourcentage" name="pourcentage" value="<?php //echo $_POST["pourcentage"];?>" step="0.01" min="1" max="99" placeholder="20,00 %" />

                        <br /> -->
                    </article>

                    <article>
                        <!-- <label for="debut">Date de début de promotion</label>
                        <br>
                        <input style="<?php //if((($_POST["debut"] >= $_POST["fin"]) && (($_POST["debut"] != '') && ($_POST["fin"] != ''))) && ($_POST["btn_maj"] == null)) {echo 'border: 3px solid red';} ?>" type="datetime-local" id="debut" name="debut" placeholer="20/10/2025"
                            value="<?php //echo $_POST["debut"]; ?>" min="2025-01-01T00:00" max="2100-01-01T00:00" />
                            <?php
                                // if(($_POST["debut"] >= $_POST["fin"]) && (($_POST["debut"] != '') && ($_POST["fin"] != ''))){
                                    ?>
                                    <br>
                                    <small class="warn"><?php
                                        // echo $_POST["debut"] . ' '. $_POST["fin"] . ' ' . 'Le premieur horodatage est postérieur (ou égal) au second';
                                        // $_SESSION["creerArticle"]["warn"]++;
                                    ?></small>
                                    <?php
                                //}
                        ?>
                        <br /> -->
                    </article>

                    <article>
                        <!-- <label for="fin">Date de fin de promotion</label>
                        <br>
                        <input style="<?php //if((($_POST["debut"] >= $_POST["fin"]) && (($_POST["debut"] != '') && ($_POST["fin"] != ''))) && ($_POST["btn_maj"] == null)) {echo 'border: 3px solid red';} ?>" type="datetime-local" id="fin" name="fin" placeholer="20/10/2025"
                            value="<?php //echo $_POST["fin"]; ?>" min="2025-01-01T00:00" max="2100-01-01T00:00" />
                        <br /> -->
                    </article>
                </section>


            </div>
            </form>
            <script>
            const btnPrincipaux = document.querySelectorAll("input");
            btnPrincipaux[0].addEventListener('click', () => {//si clic sur Annuler
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
                    btnPrincipauxModif[3].disabled = false;
                    btnPrincipauxModif[2].disabled = true;
                }else{document.location.href="/pages/backoffice/index.php"; 
                }
            }

            function horsLigne(){//si clic sur hors ligne et pas de warnings
                if (confirm("Votre article a bien été mis hors ligne. Souhaitez-vous continuer à modifier l'article ?.")) {
                    btnPrincipauxModif[2].disabled = false;
                    btnPrincipauxModif[3].disabled = true;
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
                    if (($_SESSION["creerArticle"]["warn"] === 0) && ($_POST["titre"] !== '')){
                        $time = time();
                        // print_r("WARNS : " . $_SESSION["creerArticle"]["warn"]);
                            // print_r($_SESSION["creerArticle"]["_FILES"]["photo"]["name"]);

                            //Si pas de warning et formulaire soumis via le bouton Sauvegarder ou le bouton Annuler
                            
                            $svg_titre = $_POST['titre'];
                            $svg_desc = $_POST['description'];
                            //fonction là pour éviter des problèmes à l'insertions dans la BDD
                            $_POST['titre'] = RemplacerCaracteresProblematiquesDansChampsTextes($_POST['titre']);
                            $_POST['description'] = RemplacerCaracteresProblematiquesDansChampsTextes($_POST['description']);
                            $taille  = 'M';  //à ne pas incorporer

                            //sert à ne pas avoir de warnings php sur le serv
                            if (empty($_POST["sauvegarder"])){
                                $_POST["sauvegarder"] = '';
                            }
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
                            if (($_POST["sauvegarder"] == "Sauvegarder l'ébauche") || ($_POST["publier"] == "Publier le produit dans le catalogue client")){

                            
                        
                            


                            try {//création de l'objet produit dans la base
                                $sql = '
                                INSERT INTO cobrec1._produit(id_TVA,id_vendeur,p_origine,p_nom,p_description,p_poids,p_volume,p_prix,p_stock,p_taille,date_arrivee_stock_recent)
                                VALUES (
                                ' . "'" . $_POST['tva'] ."'" .', 
                                ' . $_SESSION['vendeur_id'] .', 
                                ' . "'" . $_POST['origine'] ."'" .', 
                                ' . "'" . $_POST['titre'] ."'" .', 
                                ' . "'" . $_POST["description"] ."'" .', 
                                ' . "'" . $_POST["poids"] ."'" .', 
                                ' . "'" . $_POST["volume"] ."'" .',
                                ' . $_POST['prix'] .',
                                ' . $_POST['stock'] .',
                                ' . "'" . $taille ."'" .', 
                                CURRENT_TIMESTAMP
                                );
                                ';
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute();
                            } catch (Exception $e) {
                                //$_SESSION['bdd_errors'] sert pour consulter les erreurs de la BDD
                                $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] ="création de l'objet produit dans la base";
                                $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $e;
                            }

                            try {//création de l'affiliation entre catégorie et produit
                                $sql = '
                                INSERT INTO cobrec1._fait_partie_de(id_produit,id_categorie)
                                VALUES (
                                (SELECT id_produit FROM cobrec1._produit WHERE p_nom = ' . "'" . $_POST['titre'] . "'". '), 
                                ' . "'" . $_POST['tva'] ."'" .'
                                );
                                ';
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute();
                            } catch (Exception $e) {
                                $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] ="création de l'affiliation entre catégorie et produit";
                                $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $e;
                            }

                            // try {//création de l'affiliation entre couleur et produit
                            //     $sql = '
                            //     INSERT INTO cobrec1._est_dote_de(id_produit,code_hexa)
                            //     VALUES (
                            //     (SELECT id_produit FROM cobrec1._produit WHERE p_nom = '. "'" . $_POST['titre'] . "'".'),' 
                            //     . "'" . $_POST['couleur'] . "'". '
                            //     );
                            //     ';
                            //     $stmt = $pdo->prepare($sql);
                            //     $stmt->execute();
                            // } catch (Exception $e) {
                            //     print_r($e);
                            // }
                            try{//SELEC de id_produit
                                $sql = '
                                    SELECT id_produit FROM cobrec1._produit WHERE p_nom = '. "'" . $_POST['titre'] . "'".'
                                    ;
                                    ';
                                    $stmt = $pdo->query($sql);
                                    $id_produit = $stmt->fetch(PDO::FETCH_ASSOC);
                                    $id_produit = $id_produit['id_produit'];
                            }catch(Exception $e){
                                $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] ="SELEC de id_produit";
                                $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $e;
                            }
                            
                            
                            foreach ($_SESSION["creerArticle"]["_FILES"]["photo"]["name"] as $key => $value) {
                                
                                move_uploaded_file($_SESSION["creerArticle"]["_FILES"]["photo"]["tmp_name"][$key], 
                                    'temp_/' . $value);

                                try {//création des images
                                    $sql = '
                                    INSERT INTO cobrec1._image(i_lien, i_title, i_alt)
                                    VALUES (
                                    ' . "'" . EMPLACEMENT_DES_IMGS . $id_produit . '_' . $value . "'" .', '
                                    . "'" . $id_produit . '_' . $value . "'". ','
                                    . "'" . 'photo du produit ' . $_POST["titre"] . "'". '
                                    );
                                    ';
                                    $stmt = $pdo->prepare($sql);
                                    $stmt->execute();
                                } catch (Exception $e) {
                                    $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] ="création des images";
                                    $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $e;
                                }


                                try {//création de l'affiliation entre images et produit
                                    $sql = '
                                    INSERT INTO cobrec1._represente_produit(id_produit,id_image)
                                    VALUES (
                                    ' . $id_produit .', 
                                    (SELECT id_image FROM cobrec1._image WHERE i_lien = ' . "'" . EMPLACEMENT_DES_IMGS . $id_produit . '_' . $value ."'" .')
                                    );
                                    ';
                                    $stmt = $pdo->prepare($sql);
                                    $stmt->execute();
                                } catch (Exception $e) {
                                    $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] ="création de l'affiliation entre images et produit";
                                    $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $e;
                                    //print_r($_POST["titre"]);
                                }
                                
                                //déplacement du fichier vers le dossier img/photo
                                if (file_exists('temp_/' . $value)){
                                     rename('temp_/' . $value, '../../../img/photo/'. $value);
                                }
                                //renommage du fichier
                                if (file_exists('../../../img/photo/'. $value)){
                                    rename('../../../img/photo/'. $value, '../../../img/photo/'. $id_produit . '_' . $value);
                                }
                            }

                            //ne pas faire promotion, hors must du Sprint 1


                            //Supprime les fichiers de temp_
                            $fichiers = glob('temp_/*');
                            foreach ($fichiers as $value) {
                                unlink($value);
                            }
                            
                            if ($_POST["sauvegarder"] == "Sauvegarder l'ébauche"){
                                //Vive les if du php
                                
                    ?>
                    <script>
                        sauvegarder();
                    </script>
                    <?php }else if ($_POST["publier"] == "Publier le produit dans le catalogue client"){
                        
                    ?>
                    <script>
                        publier();
                    </script>
    <?php
                        try {//mise en ligne du produit
                            $sql = '
                            UPDATE cobrec1._produit 
                            SET p_statut = ' . "'" . 'En ligne' . "' ". '
                            WHERE p_nom = '. "'" . $_POST['titre'] . "'"
                            ;
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute();
                        } catch (Exception $e) {
                            $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] ="mise en ligne du produit";
                            $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $e;
                        }
                    }
                    
                }else if (($_POST["horsLigne"] == "Mettre hors ligne") || ($_POST["enLigne"] == "Mettre en ligne") || ($_POST["svgModif"] == "Sauvegarder les modifications")){
                    //Si pas de warning et formulaire soumis via le bouton Mettre en ligne/hors ligne
                    $time = time();
                    
                    try {//modif de l'objet produit dans la base
                        $sql = '
                        UPDATE cobrec1._produit
                        SET id_tva =' . $_POST['tva'] . ', 
                        p_origine ='. "'" . $_POST['origine'] . "'" . ', 
                        p_nom ='. "'" . $_POST['titre'] . "'" . ', 
                        p_description ='. "'" . $_POST['description'] . "'" . ', 
                        p_poids =' . $_POST['poids'] . ', 
                        p_volume =' . $_POST['volume'] . ', 
                        p_prix =' . $_POST['prix'] . ', 
                        p_stock =' . $_POST['stock'] . ' 
                        WHERE id_produit =' . $_SESSION["creerArticle"]['_GET']['id_produit'] .';
                        ';
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute();

                        $_SESSION["creerArticle"]['_GET']['p_nom'] = $_POST['titre'];
                    } catch (Exception $e) {
                        //$_SESSION['bdd_errors'] sert pour consulter les erreurs de la BDD
                        $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] ="modif de l'objet produit dans la base";
                        //print_r($_POST);
                        foreach ($_POST as $value) {
                            $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $key . ' : ' ;
                            $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $value ;
                        }
                        $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $e;
                    }

                    if ($_SESSION["creerArticle"]['_GET']['id_categorie'] != $_POST['categorie']){
                        try {//modif de l'affiliation entre catégorie et produit
                            $sql = '
                            UPDATE cobrec1._fait_partie_de 
                            SET id_categorie = ' . $_POST['categorie'] . ' 
                            WHERE id_produit =' . $_SESSION["creerArticle"]['_GET']['id_produit'] .';
                            ';
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute();
                        } catch (Exception $e) {
                            $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] ="modif de l'affiliation entre catégorie et produit";
                            $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $e;
                        }
                    }

                    try{//suppression du lien images-produit
                        $sql = '
                        DELETE FROM cobrec1._represente_produit
                        WHERE id_produit = ' . $_SESSION["creerArticle"]['_GET']['id_produit'] . '
                        ';
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute();
                    } catch (Exception $e) {
                        $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] ="suppression du lien images-produit";
                        $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $e;
                    }


                    foreach ($_SESSION["creerArticle"]['_GET']['imgs'] as $key => $value) {
                        if (file_exists('../../../img/Photo/' . $value['i_title'])){
                            unlink('../../../img/Photo/' . $value['i_title']);
                        }
                        try {//suppression des images
                            $sql = '
                            DELETE FROM cobrec1._image
                            WHERE i_lien = ' . "'" .$value['i_lien'] . "'" . ';
                            ';
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute();
                        } catch (Exception $e) {
                            $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] ="suppression des images";
                            $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $e;
                        }
                        unset($_SESSION["creerArticle"]['_GET']['imgs'][$key]);
                    }

                    

                    foreach ($_SESSION["creerArticle"]["_FILES"]["photo"]["name"] as $key => $value) {
                        move_uploaded_file($_SESSION["creerArticle"]["_FILES"]["photo"]["tmp_name"][$key], 
                                'temp_/' . $value);

                        $value = str_replace($_SESSION["creerArticle"]['_GET']['id_produit'] . '_', '', $value);
                        try {//création des images
                            $sql = '
                            INSERT INTO cobrec1._image(i_lien, i_title, i_alt)
                            VALUES (
                            ' . "'" . EMPLACEMENT_DES_IMGS . $_SESSION["creerArticle"]['_GET']['id_produit'] . '_' . $value . "'" .', '
                            . "'" . $_SESSION["creerArticle"]['_GET']['id_produit'] . '_' . $value . "'". ','
                            . "'" . 'photo du produit ' . $_POST["titre"] . "'". '
                            );
                            ';
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute();
                            $_SESSION["creerArticle"]['_GET']['imgs'][$key]['i_lien'] = EMPLACEMENT_DES_IMGS . $_SESSION["creerArticle"]['_GET']['id_produit'] . '_' . $value;
                            $_SESSION["creerArticle"]['_GET']['imgs'][$key]['i_title'] = $_SESSION["creerArticle"]['_GET']['id_produit'] . '_' . $value;
                            $_SESSION["creerArticle"]['_GET']['imgs'][$key]['i_alt'] = 'photo du produit ' . $_POST["titre"];
                        } catch (Exception $e) {
                            $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] ="création des images";
                            $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $e;
                        }


                        try {//création de l'affiliation entre images et produit
                            $sql = '
                            INSERT INTO cobrec1._represente_produit(id_produit,id_image)
                            VALUES (
                            ' . $_SESSION["creerArticle"]['_GET']['id_produit'] .', 
                            (SELECT id_image FROM cobrec1._image WHERE i_lien = ' . "'" . EMPLACEMENT_DES_IMGS . $_SESSION["creerArticle"]['_GET']['id_produit'] . '_' . $value ."'" .')
                            );
                            ';
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute();
                        } catch (Exception $e) {
                            $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] ="création de l'affiliation entre images et produit";
                            $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $e;
                            //print_r($_POST["titre"]);
                        }

                        if (file_exists('temp_/' . $value)){
                            copy('temp_/' . $value, '../../../img/photo/'. $value);
                             //renommage du fichier
                            rename('../../../img/photo/'. $value, '../../../img/photo/'. $_SESSION["creerArticle"]['_GET']['id_produit'] . '_' . $value);
                        }
                        
                       
                    }

                    if ($_POST["horsLigne"] == "Mettre hors ligne"){
                        ?>
                    <script>
                        horsLigne();
                    </script>
                    <?php 
                    
                        try {//mise hors ligne du produit
                            $sql = '
                            UPDATE cobrec1._produit 
                            SET p_statut = ' . "'" . 'Hors ligne' . "' ". '
                            WHERE p_nom = '. "'" . $_POST['titre'] . "'"
                            ;
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute();
                            $_SESSION["creerArticle"]['_GET']['p_statut'] = 'Hors ligne';
                        } catch (Exception $e) {
                            $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] ="mise hors ligne du produit";
                            $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $e;
                        }
                
                    }else if ($_POST["enLigne"] == "Mettre en ligne"){

                        
                    ?>
                    <script>
                        enLigne();
                    </script>

    <?php
                        try {//mise en ligne du produit
                            $sql = '
                            UPDATE cobrec1._produit 
                            SET p_statut = ' . "'" . 'En ligne' . "' ". '
                            WHERE p_nom = '. "'" . $_POST['titre'] . "'"
                            ;
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute();
                            $_SESSION["creerArticle"]['_GET']['p_statut'] = 'En ligne';
                        } catch (Exception $e) {
                            $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] ="mise en ligne du produit";
                            $_SESSION['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $e;
                        }
                    }
                }

                // if (empty($_SESSION['bdd_errors']) !== true){
                //     //Sert pour consulter les erreurs de la BDD via un fichier dédié
                //     $fp = fopen('file.csv', 'w');
                //     foreach ($_SESSION['bdd_errors'] as $fields) {
                //         fputcsv($fp, $fields, ',', '"', '');
                //     }
                //     fclose($fp);
                // }
                

                //Sert pour des tests
                $svg_titre = $_POST['titre'];
                $svg_desc = $_POST['description'];

            }
            if (($_SESSION["creerArticle"]["warn"] === 0) && ($_POST["titre"] !== '') && (($_POST["sauvegarder"] == "Sauvegarder l'ébauche") || ($_POST["publier"] == "Publier le produit dans le catalogue client"))){
                //nettoyage
                $_POST = [];
                $_SESSION["creerArticle"] = [];
                $_FILES = [];
            }else{
                
                for ($i = 0; (($i < NB_IMGS_MAX) && ($i < count($_SESSION["creerArticle"]["_FILES"]["photo"]["name"]))) ; $i++){
                    //déplace les fichiers dans un dossier pour que l'utilisateur puisse voir les imgs
                    move_uploaded_file($_SESSION["creerArticle"]["_FILES"]["photo"]["tmp_name"][$i], 
                    'temp_/' . $_SESSION["creerArticle"]["_FILES"]["photo"]["name"][$i]);
                }
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
