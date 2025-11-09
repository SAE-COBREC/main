<?php
    // include('../../../../config.php');
    session_start();
    $sth = null ;
    $dbh = null ;
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" type="text/css" href="/html/styles/creerArticle/creerArticle.css" media="screen">
    <title>Ajouter un produit</title>
</head>
<pre>
<?php
// print_r($_POST);
// try{
//     $dbh = new PDO (
//         'postgres : host = servbdd ; dbname = pg_test',
//         $user , $password 
//     );
// } catch (PDOException $e) {
//     print "Erreur ! : " . $e->getMessage() . "<br/>";
//     die();
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

    print_r(["creerArticle"]["_FILES"]["name"]);
    if ($_POST["btn_moins0"] == '-'){
        print_r("if 1\n");
        if ($_SESSION["creerArticle"]["_FILES"]["name"][1] === null){
            unset($_SESSION["creerArticle"]["_FILES"]["name"][0]);
        }else{
            $_SESSION["creerArticle"]["_FILES"]["name"][0] = $_SESSION["creerArticle"]["_FILES"]["name"][1];
        }
        if ($_SESSION["creerArticle"]["_FILES"]["name"][2] === null){
            unset($_SESSION["creerArticle"]["_FILES"]["name"][1]);
        }else{
            $_SESSION["creerArticle"]["_FILES"]["name"][1] = $_SESSION["creerArticle"]["_FILES"]["name"][2];
        }
        unset($_SESSION["creerArticle"]["_FILES"]["name"][2]);
    }elseif ($_POST["btn_moins1"] === '-'){
        print_r("if 2\n");
        if ($_SESSION["creerArticle"]["_FILES"]["name"][2] === null){
            unset($_SESSION["creerArticle"]["_FILES"]["name"][1]);
        }else{
            $_SESSION["creerArticle"]["_FILES"]["name"][1] = $_SESSION["creerArticle"]["_FILES"]["name"][2];
        }
        unset($_SESSION["creerArticle"]["_FILES"]["name"][2]);
    }elseif ($_POST["btn_moins2"] === '-'){
        print_r("if 3\n");
        unset($_SESSION["creerArticle"]["_FILES"]["name"][2]);
    }
    
    if ($_FILES["photo"]["name"][0] !== ''){
        // if (count($_SESSION["creerArticle"]["_FILES"]['name']) >3){
        //     for ($i = 3; ($i < count($_SESSION["creerArticle"]["_FILES"]["name"])); $i++) {
        //         echo $_SESSION["creerArticle"]["_FILES"]["name"][count($_SESSION["creerArticle"]["_FILES"]["name"]) - $i];
        //     }
        // }



        for ($i=0; (($i<3) && ($i < count($_FILES["photo"]["name"]))) ; ) {
            //if (!in_array($value,$_SESSION["creerArticle"]["_FILES"]['name'])){
                // $_SESSION["creerArticle"]["_FILES"]["name"][(count($_SESSION["creerArticle"]["_FILES"]["name"])-1 -$i)] = 
                //     $_FILES["photo"]["name"][count($_FILES["photo"]["name"])-1-$i];
                // $_SESSION["creerArticle"]["_FILES"]["tmp_name"][count($_SESSION["creerArticle"]["_FILES"]["name"]) %3] = 
                //     $_FILES["photo"]["tmp_name"][count($_FILES["photo"]["tmp_name"])-1-$i];
                $_SESSION["creerArticle"]["tmp_file"]["name"][] = 
                    $_FILES["photo"]["name"][count($_FILES["photo"]["name"])-1-$i];
                $_SESSION["creerArticle"]["tmp_file"]["tmp_name"][] = 
                    $_FILES["photo"]["tmp_name"][count($_FILES["photo"]["name"])-1-$i];
                $i++;
            //}
        }
        for ($i=0; (($i<3) && ($i < count($_SESSION["creerArticle"]["tmp_file"]["name"]))) ; $i++) {
            $_SESSION["creerArticle"]["_FILES"]["name"][(count($_SESSION["creerArticle"]["tmp_file"]["name"])-1-$i) % 3] = 
                $_SESSION["creerArticle"]["tmp_file"]["name"][count($_SESSION["creerArticle"]["tmp_file"]["name"])-1-$i];
            $_SESSION["creerArticle"]["_FILES"]["tmp_name"][(count($_SESSION["creerArticle"]["tmp_file"]["name"])-1-$i) % 3] = 
                $_SESSION["creerArticle"]["tmp_file"]["tmp_name"][count($_SESSION["creerArticle"]["tmp_file"]["name"])-1-$i];
        }
    }
    
print_r($_SESSION["creerArticle"]["_FILES"]);
print_r($_SESSION["creerArticle"]["tmp_file"]["name"]);
} else {
    $_FILES["photo"]["name"] = [];
    $_SESSION["creerArticle"]["_FILES"]['name'] = [];
    $_SESSION["creerArticle"]["tmp_file"]["name"] = [];
    $_SESSION["creerArticle"]["tmp_file"]["tmp_name"] = [];
    $_SESSION["creerArticle"]["_FILES"]['tmp_name'] = [];
}

?></pre>
<body>
    <?php
    include __DIR__ . '/../../../partials/aside.html';
    ?>
    <main>
        <h2>Produit non enregistré</h2>
        <form action="index.php" method="post" enctype="multipart/form-data">
            <!-- Boutons de soumission principaux -->
            <input type="button" value="Annuler" />
            <input type="submit" name="sauvegarder" value="Sauvegarder" />
            <input type="submit" name="publier" value="Publier" />
            <div>
                <section>
                    <h3>Ajouter produit</h3>
                    <article>
                        <!-- Texte avec label -->
                        <label for="titre">Titre</label>
                        <input style="<?php if (($_POST["titre"] === 'Déjà pris') && ($_POST["btn_maj"] == null)) {echo 'border: 3px solid red';} ?>" type="text" id="titre" name="titre" value="<?php echo $_POST["titre"]; ?>"
                            maxlength="100"
                            pattern="[\[\]\(\)&0-9a-zA-ZàâäéèêëîïôöùûüÿçæœÀÂÄÇÉÈÊËÎÏÔÖÙÛÜŸÆŒ+=°: .;,!? ]+"
                            required />
                            <?php
                        if($_POST["titre"] === 'Déjà pris'){
                            ?>
                            <br>
                            <small class="warn"><?php
                                echo 'Le titre de votre article est déjà pris. Veuillez choisir un autre titre';
                                echo $dbh->query('SELECT p_nom FROM cobrec1._produit where p_nom = ' . $_POST['titre']);
                                $_SESSION["creerArticle"]["warn"]++;
                            ?></small>
                            <?php
                        // }elseif (preg_match("[&0-9a-zA-ZàâäéèêëîïôöùûüÿçæœÀÂÄÇÉÈÊËÎÏÔÖÙÛÜŸÆŒ-_:\ °]+",$_POST["titre"])) {
                        //     # code...
                        }
                        ?>
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
                        // for (i=0;i<3; i++){
                        //     echo $_SESSION["creerArticle"]["_FILES"]["photo"]["tmp_name"][i] . '/' . $_SESSION["creerArticle"]["_FILES"]["photo"]["full_path"][i];
                        // }
                        echo $_SESSION["creerArticle"]["_FILES"]["photo"]["tmp_name"][0] . '/' . $_SESSION["creerArticle"]["_FILES"]["photo"]["full_path"][0];
                        ?>" />
                        <?php
                        $i = count($_SESSION["creerArticle"]["_FILES"]["name"]);
                        $i = 3;
                        if (($_SESSION["creerArticle"]["_FILES"]["name"] === [])) {
                            ?>
                            <br>
                            <small class="warn"><?php
                            echo 'Veuillez téléverser au moins une photographie.';
                            $_SESSION["creerArticle"]["warn"]++;
                            ?></small>
                            <?php
                        }
                        //for ($i = 1; ($i < 4) && ($i < 1+count($_SESSION["creerArticle"]["_FILES"]["name"])); $i++) {
                        for ($i = 0; (($i < 3) && ($i < count($_SESSION["creerArticle"]["_FILES"]["name"]))) ; $i++) {
                            ?><br>
                            <small>
                                <?php echo $_SESSION["creerArticle"]["_FILES"]["name"][count($_SESSION["creerArticle"]["_FILES"]["name"]) -1-$i]; ?>
                                <input type="submit" name="btn_moins<?php echo count($_SESSION["creerArticle"]["_FILES"]["name"]) -1-$i?>" value="-" />
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
                        <input type="number" id="stock" name="stock" value="<?php echo $_POST["stock"]; ?>" required
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
                        <!-- Liste déroulante -->
                        <label for="categorie">Catégorie</label>
                        <br>
                        <select id="categorie" value="<?php echo $_POST["categorie"]; ?>" name="categorie">
                            <option value="france">France</option>
                            <option value="england">Angleterre</option>
                            <option value="italie">Italie</option>
                            <option value="japon">Japon</option>
                        </select>
                        <br />
                    </article>

                </section>

                <section style="<?php 
                    if (($_POST["pourcentage"] === '') && ($_POST["debut"] === '') && ($_POST["fin"] === '') ){
                        //
                    }elseif (($_POST["pourcentage"] !== '') && ($_POST["debut"] !== '') && ($_POST["fin"] !== '') ) {
                        //
                    }elseif ($_POST["btn_maj"] == null){
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
                        <label for="pourcentage">Pourcentage réduction</label>
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
            btnPrincipaux[0].addEventListener('click', () => {
                // const element = document.getElementById(".small_moins0");
                // element.innerHTML = "";
                if (confirm("Êtes-vous certain de voulair annuler ? Ce que vous n'avez pas sauvegardé sera perdu.")) {
                    console.log("You pressed OK!");
                    document.location.href="http://localhost:8888/html/pages/backoffice/index.php"; 
                } else {
                    console.log("You pressed Cancel!");
                } 
            });

            function sauvegarder(){
                confirm("Votre article a bien été sauvegardé.");
                document.location.href = "http://localhost:8888/html/pages/backoffice/index.php"; 
            }

            function publier(){
                confirm("Votre article a bien été publié.");
                document.location.href = "http://localhost:8888/html/pages/backoffice/index.php"; 
            }
        </script>
            <pre>
                <?php 
                    print_r($_SESSION["creerArticle"]["warn"]); 
                    if (($_SESSION["creerArticle"]["warn"]=== 0) && ($_POST !== []) && (($_POST["sauvegarder"] == "Sauvegarder")) || ($_POST["publier"] == "Publier")){
                        print_r($_SESSION["creerArticle"]["_FILES"]["name"]);
                        print_r("OK");
                        $_SESSION["creerArticle"]['fin'] = "fin";
                        for ($i = 0; (($i < 3) && ($i < count($_SESSION["creerArticle"]["_FILES"]["name"]))) ; $i++){
                            print_r($i . "\n");
                            //print_r($_SESSION["creerArticle"]["_FILES"]["tmp_name"][$i]);
                            move_uploaded_file($_SESSION["creerArticle"]["_FILES"]["tmp_name"][$i], 
                            'temp_banque_images/' . $_SESSION["creerArticle"]["_FILES"]["name"][$i]);
                        }
                        print_r("  FIN");

                        // $stmt = $dbh -> prepare (
                        //     " INSERT INTO REGISTRY ( name , value )
                        //     VALUES ('$name','$value')"
                        // );
                        // $stmt -> execute ();
                        
                        
                        // $_POST = [];
                        // $_SESSION["creerArticle"] = [];
                        // $_FILES = [];
                        
                        if ($_POST["sauvegarder"] == "Sauvegarder"){
                            
                
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
                }
            }
            $sth = null ;
            $dbh = null ;
    ?>
            </pre>
        </main>
    </body>
</html>