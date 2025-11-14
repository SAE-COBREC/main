<?php
    $sth = null ;
    $dbh = null ;
    include '../../../../../config.php';
    $pdo->exec("SET search_path to cobrec1");
    session_start();
    const NB_IMGS_MAX = 3;
    const EMPLACEMENT_DEF_IMGS = 'html/img/Photo';
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" type="text/css" href="../../../../styles/creerArticle/creerArticle.css" media="screen">
    <title>Ébauche de produit</title>
</head>
<pre>
<?php

// foreach ($_SESSION["id_image"] as $key => $value) {
//     print_r("\n");
//     print_r($value);
//     print_r("\n");
// }
$_SESSION["creerArticle"]["warn"]= 0;
//print_r($_SESSION["creerArticle"]["warn"]);
$warnPromo = false;
if ($_POST !== []) {
    // if (count($_FILES["photo"]["name"]) === 0) {
    //     print_r("Aucune photo ");
    // }// else{
    //     print_r(count($_FILES["photo"]["name"]) . ' photos.');
    // }
    // if (($_POST["pourcentage"] === '') && ($_POST["debut"] === '') && ($_POST["fin"] === '')) {
    //     print_r("réduc vide");
    // } elseif (($_POST["pourcentage"] !== '') && ($_POST["debut"] !== '') && ($_POST["fin"] !== '')) {
    //     print_r("réduc pleine");
    // } else {
    //     print_r("entre deux");
    // }
    // print_r($_POST);
    // print_r($_FILES);
    // print_r($_POST["debut"]);
    // print_r($_POST["fin"]);

    //print_r("\n");

    function decalage($aDecaler){
        //unlink('temp_/' . $_SESSION["creerArticle"]["_FILES"]["name"][$aDecaler-1]);
        if ($_SESSION["creerArticle"]["_FILES"]["name"][$aDecaler] === null){
            //L'image $aDecaler n'existe pas. Décalage interrompu. Suppression de l'image $aDecaler-1.
            unset($_SESSION["creerArticle"]["_FILES"]["name"][$aDecaler-1]);
            unset($_SESSION["creerArticle"]["_FILES"]["tmp_name"][$aDecaler-1]);
        }else{
            //L'image $aDecaler existe. Décalage. L'image $aDecaler-1 originale est écrasée.
            $_SESSION["creerArticle"]["_FILES"]["name"][$aDecaler-1] = $_SESSION["creerArticle"]["_FILES"]["name"][$aDecaler];
            $_SESSION["creerArticle"]["_FILES"]["tmp_name"][$aDecaler-1] = $_SESSION["creerArticle"]["_FILES"]["tmp_name"][$aDecaler];
        }
    }

    function supprDeTemp($aSuppr){
        unset($_SESSION["creerArticle"]["tmp_file"]["tmp_name"][array_search($_SESSION["creerArticle"]["_FILES"]["name"][$aSuppr], $_SESSION["creerArticle"]["tmp_file"]["name"])]);
        unset($_SESSION["creerArticle"]["tmp_file"]["name"][array_search($_SESSION["creerArticle"]["_FILES"]["name"][$aSuppr], $_SESSION["creerArticle"]["tmp_file"]["name"])]);
    }

    function supprDeDef(){
        //unlink('temp_/' . $_SESSION["creerArticle"]["_FILES"]["name"][NB_IMGS_MAX-1]);
        unset($_SESSION["creerArticle"]["_FILES"]["name"][NB_IMGS_MAX-1]);
        unset($_SESSION["creerArticle"]["_FILES"]["tmp_name"][NB_IMGS_MAX-1]);
    }
    if ($_POST["btn_moins0"] == '-'){
        //suppression de l'image 0 dans $_SESSION["creerArticle"]["tmp_file"]
        supprDeTemp(0);
        //écrasement de l'image 0 originale par l'image 1
        decalage(1);
        //écrasement de l'image 1 originale par l'image 2
        decalage(2);
        //L'image 2 est supprimée pour éviter toute duplication résultante du décalage.
        supprDeDef();
    }elseif ($_POST["btn_moins1"] === '-'){
        //suppression de l'image 1 dans $_SESSION["creerArticle"]["tmp_file"]
        supprDeTemp(1);
        //écrasement de l'image 1 originale par l'image 2
        decalage(2);
        //L'image 2 est supprimée pour éviter toute duplication résultante du décalage.
        supprDeDef();
    }elseif ($_POST["btn_moins2"] === '-'){
        //suppression de l'image 2 dans $_SESSION["creerArticle"]["tmp_file"]
        supprDeTemp(2);
        //L'image 2 est supprimée pour éviter toute duplication résultante du décalage.
        supprDeDef();
    }

    if ($_FILES["photo"]["name"][0] !== ''){//Si au moins un fichier a été déposé



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
            $_SESSION["creerArticle"]["_FILES"]["name"][(count($_SESSION["creerArticle"]["tmp_file"]["name"])-1-$i) % NB_IMGS_MAX] = 
                $value;
            $_SESSION["creerArticle"]["_FILES"]["tmp_name"][(count($_SESSION["creerArticle"]["tmp_file"]["tmp_name"])-1-$i) % NB_IMGS_MAX] = 
                $_SESSION["creerArticle"]["tmp_file"]["tmp_name"][$key];
            $i++;
            $i %= NB_IMGS_MAX;
        }
            
        
        foreach ($_SESSION["creerArticle"]["_FILES"]["name"] as $key => $value) {
            if ($value === null){//permet d'avoir un affichage qui ne parait pas bizarre
                unset($_SESSION["creerArticle"]["_FILES"]["name"][$key]);
                unset($_SESSION["creerArticle"]["_FILES"]["tmp_name"][$key]);
            }
        }
    }
    
    
    // print_r($_SESSION["creerArticle"]);
    // print_r($_FILES);

} else {//Initialisation des tablaux pour éviter problèmes de comparaison avec des valeurs nulls
    $_FILES["photo"]["name"] = [];
    $_SESSION["creerArticle"]["_FILES"]['name'] = [];
    $_SESSION["creerArticle"]["tmp_file"]["name"] = [];
    $_SESSION["creerArticle"]["tmp_file"]["tmp_name"] = [];
    $_SESSION["creerArticle"]["_FILES"]['tmp_name'] = [];
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

}

?></pre>
<body>
    <?php
    //inclusion du bandeau à gauche de la page
    include __DIR__ . '/../../../partials/aside.html';
    ?>
    <main>
        <h2>Produit non enregistré</h2>
        <form action="index.php" method="post" enctype="multipart/form-data">
            <!-- Boutons de soumission principaux -->
            <input type="button" value="Annuler" title="Permets d'annuler la création de l'article et de revenir au catalogue."/>
            <input type="submit" name="sauvegarder" title="Un article sauvegardé est inscrit dans la base de données mais n'est visible que par vous." value="Sauvegarder" />
            <input type="submit" name="publier"title="Un article publié est inscrit dans la base de données et est visible par les clients." value="Publier" />
            <div>
                <section>
                    <h3>Ajouter un produit</h3>
                    <article>
                        <!-- Texte avec label -->
                         <!-- <pre> -->
                        <?php
                        $titre_pour_bdd = str_replace("'","''",$_POST['titre']);
                        $titre_pour_bdd = str_replace('"','""',$titre_pour_bdd);
                            try {
                                $sql = 'SELECT p_nom FROM cobrec1._produit where p_nom = ' . "'" . $titre_pour_bdd ."'";
                                $stmt = $pdo->query($sql);
                                $titre = $stmt->fetch(PDO::FETCH_ASSOC);
                                //print_r('Resultat : ' . $result);
                            } catch (Exception $e) {
                                //print_r($e);
                            }
                            //print_r("Tite =: " . $titre);
                        ?>
<!-- </pre> -->
                        <label for="titre">Titre</label>
                        <br>
                        <input style="<?php 
                            
                            if (($titre != '') && ($_POST["btn_maj"] == null)) {echo 'border: 3px solid red';} ?>" type="text" id="titre" name="titre" value="<?php echo $_POST["titre"]; 
                        
                        ?>"
                            maxlength="100"
                            pattern="[\[\]\(\)\'\x22&0-9a-zA-ZàâäéèêëîïôöùûüÿçæœÀÂÄÇÉÈÊËÎÏÔÖÙÛÜŸÆŒ+=°: .;,!? ]+"
                            required />
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
                        <small>Seuls les caractères alphanumériques d'usage en français, les signes de ponctuation et les caractères suivants +=°[]() sont autorisés.</small>
                        <br /> 
                    </article>

                    <article>
                        <!-- Texte multiligne -->
                        <label for="description">Description</label>
                        <br>
                        <textarea id="description" name="description" rows="5" cols="60"
                        pattern="[\[\]\(\)&0-9a-zA-ZàâäéèêëîïôöùûüÿçæœÀÂÄÇÉÈÊËÎÏÔÖÙÛÜŸÆŒ+=°: .;,!? ]+"
                        required><?php echo $_POST["description"]; ?></textarea>
                        <br>
                        <small>Seuls les caractères alphanumériques d'usage en français, les signes de ponctuation et les caractères suivants +=°[]() sont autorisés.</small>
                        <br /> 
                    </article>
                </section>
                <section>
                    <article>
                        <!-- Téléversement de photo -->
                        <h3>Photo(s)</h3>
                        <label for="photo[]">Sélectionner un fichier</label>
                        <br>
                         <input style="<?php if ((($_SESSION["creerArticle"]["_FILES"]["name"] === [])) && ($_POST["btn_maj"] == null)) {echo 'border: 3px solid red';} ?>" type="file" multiple id="photo[]" name="photo[]" accept="image/*" value="<?php
                        echo $_SESSION["creerArticle"]["_FILES"]["photo"]["tmp_name"][0] . '/' . $_SESSION["creerArticle"]["_FILES"]["photo"]["full_path"][0];
                        ?>" />
                        <?php
                        
                        $i = count($_SESSION["creerArticle"]["_FILES"]["name"]);
                        $i = NB_IMGS_MAX;
                        if (($_SESSION["creerArticle"]["_FILES"]["name"] === [])) {
                            ?>
                            <br>

                            <small class="warn"><?php
                            echo 'Veuillez téléverser au moins une photographie.';
                            $_SESSION["creerArticle"]["warn"]++;
                            ?></small>
                            <?php
                        }
                        foreach ($_SESSION["creerArticle"]["_FILES"]["name"] as $key => $value) {
                            ?><br>
                            <small>
                                <!-- affichage -->
                                <img src="temp_/<?php echo $value ?>" height="25">
                                <?php echo $value; ?>
                                <input type="submit" name="btn_moins<?php echo $key?>" title="Permets de supprimer l'image qui est en face." value="-" />
                            </small>
                            <?php
                        }

                        ?>
                        <br>
                        <input type="submit" name="btn_maj" value="Voir les images ayant été déposées" />
                        <br />
                    </article>
                </section>

                <section>
                    <article>
                        <label for="stock">Stock</label>
                        <br>
                        <input type="number" id="stock" name="stock" value="<?php echo $_POST["stock"]; ?>" placeholder="0" required
                            min="0" value="0">
                        <br />
                    </article>

                    <article>
                        <label for="prix">Prix</label>
                        <br>
                        <input type="number" id="prix" name="prix" value="<?php echo $_POST["prix"]; ?>" step="0.01"
                            min="1" placeholder="0.00 €" required>
                        <br />
                    </article>

                    <article>
                        <label for="poids">Poids (en kg)</label>
                        <br>
                        <input type="number" id="poids" name="poids" value="<?php echo $_POST["poids"]; ?>" step="0.01"
                            min="0.01" placeholder="0.01" required><small> kg</small>
                        <br />
                    </article>

                    <article>
                        <label for="volume">Volume (en m<sup>3</sup>)</label>
                        <br>
                        <input type="number" id="volume" name="volume" value="<?php echo $_POST["volume"]; ?>" step="0.01"
                            min="0.01" placeholder="0.01" required><small> m<sup>3</sup></small>
                        <br />
                    </article>

                    <article>
                        <!-- Liste déroulante -->
                        <label for="tva">TVA</label>
                        <br>
                        <select id="tva" name="tva" >
                            <?php
                                try {
                                    $sql = 'SELECT * FROM cobrec1._tva';
                                    $stmt = $pdo->query($sql);
                                    $tva = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                } catch (Exception $e) {
                                    print_r($e);
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
                        <label for="categorie">Catégorie</label>
                        <br>
                        <select id="categorie" name="categorie" required>
                        <option value=''></option>
                            <?php
                                try {
                                    $sql = 'SELECT nom_categorie FROM cobrec1._categorie_produit';
                                    $stmt = $pdo->query($sql);
                                    $categorie = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    //print_r($categorie);
                                } catch (Exception $e) {
                                    //print_r($e);
                                }

                                foreach ($categorie as $value) {
                            ?>
                            <option 
                            value="<?php echo $value['nom_categorie'] ?>" <?php if ($_POST["categorie"] === $value['nom_categorie']){echo 'selected';} ?>>
                            <?php echo $value['nom_categorie'] ?>
                            </option>
                            <?php } ?>
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

                <section style="<?php 
                    if (($_POST["pourcentage"] === '') && ($_POST["debut"] === '') && ($_POST["fin"] === '') ){
                        //
                    }elseif (($_POST["pourcentage"] !== '') && ($_POST["debut"] !== '') && ($_POST["fin"] !== '') ) {
                        //
                    }elseif ($_POST["btn_maj"] == null){//si la zone de réduction n'est que partiellement remplie
                        $_SESSION["creerArticle"]["warn"]++;
                        $warnPromo = true;
                        echo 'border: 3px solid red';
                    }
                ?>">
                    <article>
                        <?php 
                            if ($warnPromo){
                        ?>
                        <small class="warn"><?php
                            echo 'Veuillez remplir tous les champs du bloc réduction ou n\'en remplir aucun.';
                            $_SESSION["creerArticle"]["warn"]++;
                        ?></small>
                        <br>
                        <?php
                            }
                        ?>
                        <label for="pourcentage">Pourcentage de réduction</label>
                        <br>
                        <input type="number" id="pourcentage" name="pourcentage" value="<?php echo $_POST["pourcentage"];?>" step="0.01" min="1" max="99" placeholder="20,00 %" />

                        <br />
                    </article>

                    <article>
                        <label for="debut">Date de début de réduction</label>
                        <br>
                        <input style="<?php if((($_POST["debut"] >= $_POST["fin"]) && (($_POST["debut"] != '') && ($_POST["fin"] != ''))) && ($_POST["btn_maj"] == null)) {echo 'border: 3px solid red';} ?>" type="datetime-local" id="debut" name="debut" placeholer="20/10/2025"
                            value="<?php echo $_POST["debut"]; ?>" min="2025-01-01T00:00" max="2100-01-01T00:00" />
                            <?php
                                if(($_POST["debut"] >= $_POST["fin"]) && (($_POST["debut"] != '') && ($_POST["fin"] != ''))){
                                    ?>
                                    <br>
                                    <small class="warn"><?php
                                        echo $_POST["debut"] . ' '. $_POST["fin"] . ' ' . 'Le premieur horodatage est postérieur (ou égal) au second';
                                        $_SESSION["creerArticle"]["warn"]++;
                                    ?></small>
                                    <?php
                                }
                        ?>
                        <br />
                    </article>

                    <article>
                        <label for="fin">Date de fin de réduction</label>
                        <br>
                        <input style="<?php if((($_POST["debut"] >= $_POST["fin"]) && (($_POST["debut"] != '') && ($_POST["fin"] != ''))) && ($_POST["btn_maj"] == null)) {echo 'border: 3px solid red';} ?>" type="datetime-local" id="fin" name="fin" placeholer="20/10/2025"
                            value="<?php echo $_POST["fin"]; ?>" min="2025-01-01T00:00" max="2100-01-01T00:00" />
                        <br />
                    </article>
                </section>


            </div>
            </form>
            <script>
            const btnPrincipaux = document.querySelectorAll("input");
            btnPrincipaux[0].addEventListener('click', () => {//si clicl sur Annuler
                // const element = document.getElementById(".small_moins0");
                // element.innerHTML = "";
                if (confirm("Êtes-vous certain de voulair annuler ? Ce que vous n'avez pas sauvegardé/publié sera perdu.")) {
                    document.location.href="http://localhost:8888/pages/backoffice/index.php"; 
                }
            });

            function sauvegarder(){//si clic sur sauvegarder et pas de warnings
                confirm("Votre article a bien été sauvegardé.");
                document.location.href = "http://localhost:8888/pages/backoffice/index.php"; 
            }

            function publier(){//si clic sur publier et pas de warnings
                confirm("Votre article a bien été publié.");
                document.location.href = "http://localhost:8888/pages/backoffice/index.php"; 
            }
        </script>
            <pre>
                <?php 
                    if (($_SESSION["creerArticle"]["warn"] === 0) && ($_POST["titre"] !== '') && (($_POST["sauvegarder"] == "Sauvegarder") || ($_POST["publier"] == "Publier"))){
                        print_r("WARNS : " . $_SESSION["creerArticle"]["warn"]);
                        print_r($_SESSION["creerArticle"]["_FILES"]["name"]);
                        
                        $svg_titre = $_POST['titre'];
                        $svg_desc = $_POST['description'];
                        $_POST['titre'] = str_replace("'","''",$_POST['titre']);
                        $_POST['titre'] = str_replace('"','""',$_POST['titre']);
                        $_POST['description'] = str_replace("'","''",$_POST['description']);
                        $_POST['description'] = str_replace('"','""',$_POST['description']);
                        $id_vendeur  = 1; //à changer quand Léo aura fini de faire ce qu'il faut pour gérer l'id
                        $taille  = 'M';  //à ne pas incorporer
                        try {//création de l'objet produit dans la base
                            $sql = '
                            INSERT INTO cobrec1._produit(id_TVA,id_vendeur,p_nom,p_description,p_poids,p_volume,p_prix,p_stock,p_taille,date_arrivee_stock_recent)
                            VALUES (
                            ' . "'" . $_POST['tva'] ."'" .', 
                            ' . $id_vendeur .', 
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
                            print_r("création de l'objet produit dans la base");
                            print_r($e);
                        }

                        try {//création de l'affiliation entre catégorie et produit
                            $sql = '
                            INSERT INTO cobrec1._fait_partie_de(id_produit,id_categorie)
                            VALUES (
                            (SELECT id_produit FROM cobrec1._produit WHERE p_nom = ' . "'" . $_POST['titre'] . "'". '), 
                            (SELECT id_categorie FROM cobrec1._categorie_produit WHERE nom_categorie = ' . "'" . $_POST['categorie'] . "'". ')
                            );
                            ';
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute();
                        } catch (Exception $e) {
                            print_r("création de l'affiliation entre catégorie et produit");
                            print_r($e);
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

                        foreach ($_SESSION["creerArticle"]["_FILES"]["name"] as $key => $value) {
                            try {//création des images
                                $sql = '
                                INSERT INTO cobrec1._image(i_lien, i_title, i_alt)
                                VALUES (
                                ' . "'" . 'html/img/Photo/' . $_POST['titre'] . '/' . $value ."'" .', '
                                . "'" . $value . "'". ','
                                . "'" . 'Photo du produit ' . $_POST["titre"] . "'". '
                                );
                                ';
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute();
                            } catch (Exception $e) {
                                print_r("création des images");
                                print_r($e);
                            }


                            try {//création de l'affiliation entre images et produit
                                $sql = '
                                INSERT INTO cobrec1._represente_produit(id_produit,id_image)
                                VALUES (
                                (SELECT id_produit FROM cobrec1._produit WHERE p_nom = '. "'" . $_POST['titre'] . "'".'), 
                                (SELECT id_image FROM cobrec1._image WHERE i_title = ' . "'" . $value ."'" .')
                                );
                                ';
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute();
                            } catch (Exception $e) {
                                print_r("création de l'affiliation entre images et produit");
                                print_r($_POST["titre"]);
                                print_r($e);
                            }

                            try {//maj du lien et du titre de l'image
                                $sql = '
                                SELECT id_image FROM cobrec1._image 
                                WHERE i_title = ' . "'" . $value ."'" .'
                                ;
                                ';
                                $stmt = $pdo->query($sql);
                                $id_image = $stmt->fetch(PDO::FETCH_ASSOC);
                                $id_image = $id_image['id_image'];
                                // $value = $id_image . '_' . $value;
                                // $_SESSION["creerArticle"]["_FILES"]["temp_name"][$key] = 'html/img/Photo/' . $value;

                                // $sql = '
                                // UPDATE cobrec1._image 
                                // SET i_lien = ' . "'" . $_SESSION["creerArticle"]["_FILES"]["temp_name"][$key] . "' ". ',
                                // i_title = ' . "'" . $value . "'" . '
                                // WHERE id_image = ' . $id_image . ';'
                                // ;
                                // $stmt = $pdo->prepare($sql);
                                // $stmt->execute();
                            } catch (Exception $e) {
                                print_r("maj du lien et du titre de l'image");
                                print_r($e);
                            }



                            
                            mkdir('../../../img/Photo/' . $_POST['titre']);
                            rename('temp_/' . $value, '../../../img/Photo/' . $_POST['titre'] . '/' . $value);
                        }
                        

                        //ne pas faire réduction, hors must du Sprint 1
                        
                        
                        
                        $svg_titre = $_POST['titre'];
                        $svg_desc = $_POST['description'];

                        $fichiers = glob('/temp_*');
                        foreach ($fichiers as $key => $value) {
                            unlink('temp_/' . $value);
                        }
                        if ($_POST["sauvegarder"] == "Sauvegarder"){
                            //Vive les if du php
                            
                
                ?>
                <script>
                    sauvegarder();
                </script>
                <?php }else if ($_POST["publier"] == "Publier"){
                    
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
                        print_r("mise en ligne du produit");
                        print_r($e);
                    }
                }
            }
            if (($_SESSION["creerArticle"]["warn"] === 0) && ($_POST["titre"] !== '') && (($_POST["sauvegarder"] == "Sauvegarder") || ($_POST["publier"] == "Publier"))){
                $_POST = [];
                $_SESSION["creerArticle"] = [];
                $_FILES = [];
            }else{
                for ($i = 0; (($i < NB_IMGS_MAX) && ($i < count($_SESSION["creerArticle"]["_FILES"]["name"]))) ; $i++){
                    //déplace les fichiers dans un dossier pour que l'utilisateur puisse voir les imgs
                    move_uploaded_file($_SESSION["creerArticle"]["_FILES"]["tmp_name"][$i], 
                    'temp_/' . $_SESSION["creerArticle"]["_FILES"]["name"][$i]);
                }
            }
            $sth = null ;
            $dbh = null ;
    ?>
            </pre>
        </main>
    </body>
</html>
