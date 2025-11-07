<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" type="text/css" href="/html/styles/creerArticle/creerArticle.css" media="screen">
    <title>Ajouter un produit</title>
</head>
<?php
session_start();
// session_unset();
// session_destroy();
$warn = 0;
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
    print_r($_POST["debut"]);
    print_r($_POST["fin"]);


    if ($_FILES["photo"]["name"][0] !== ''){
        foreach ($_FILES["photo"]["name"] as $key => $value) {
            //if (!in_array($value,$_SESSION["_FILES"]['name'])){
                $_SESSION["_FILES"]["name"][] = $_FILES["photo"]["name"][$key];
                $_SESSION["_FILES"]["tmp_name"][] = $_FILES["photo"]["tmp_name"][$key];
            //}
        }
    }
    //print_r($_SESSION);
    } else {
        $_FILES["photo"]["name"] = [];
        $_SESSION["_FILES"]['name'] = [];
    }

?>
<body>
    <?php
    include __DIR__ . '/../../../partials/aside.html';
    ?>
    <main>
        <h2>⏴ Produit non enregistré</h2>
        <form action="creerArticle.php" method="post" enctype="multipart/form-data">
            <!-- Bouton de soumission -->
            <input type="button" value="Annuler" />
            <input type="submit" value="Sauvegarder" />
            <input type="submit" value="Publier" />
            <!-- Annuler-->
            <!-- Sauvegarder-->
            <div>
                <section>
                    <h3>Ajouter produit</h3>
                    <article>
                        <!-- Texte avec label -->
                        <label for="titre">Titre</label>
                        <br>
                        <input style="<?php if ($_POST["titre"] === 'Déjà pris') {echo 'border: 3px solid red';} ?>" type="text" id="titre" name="titre" value="<?php echo $_POST["titre"]; ?>"
                            maxlength="100"
                            pattern="[&0-9a-zA-ZàâäéèêëîïôöùûüÿçæœÀÂÄÇÉÈÊËÎÏÔÖÙÛÜŸÆŒ]+"
                            required />
                        <?php
                        if($_POST["titre"] === 'Déjà pris'){
                            ?>
                            <br>
                            <small class="warn"><?php
                                echo 'Le titre de votre article est déjà pris. Veuillez choisir un autre titre';
                                $warn++;
                            ?></small>
                            <?php
                        }
                        ?>
                        <br />
                    </article>

                    <article>
                        <!-- Texte multiligne -->
                        <label for="description">Description</label>
                        <br>
                        <textarea id="description" name="description" rows="5" cols="60"
                            required><?php echo $_POST["description"]; ?></textarea>
                        <br />
                    </article>
                </section>
                <section>
                    <article>
                        <!-- Téléversement de photo -->
                        <h3>Photo(s)</h3>
                        <label for="photo[]">Sélectionner un fichier</label>
                        <br>
                         <input style="<?php if ($_SESSION["_FILES"]["name"][0] === '') {echo 'border: 3px solid red';} ?>" type="file" multiple id="photo[]" name="photo[]" accept="image/*" value="<?php
                        // for (i=0;i<3; i++){
                        //     echo $_SESSION["_FILES"]["photo"]["tmp_name"][i] . '/' . $_SESSION["_FILES"]["photo"]["full_path"][i];
                        // }
                        echo $_SESSION["_FILES"]["photo"]["tmp_name"][0] . '/' . $_SESSION["_FILES"]["photo"]["full_path"][0];
                        ?>" />
                        <?php
                        $i = count($_SESSION["_FILES"]["name"]);
                        $i = 3;
                        if ($_SESSION["_FILES"]["name"][0] === '') {
                            ?>
                            <br>
                            <small class="warn"><?php
                            echo 'Veuillez téléverser au moins une photographie.';
                            $warn++;
                            ?></small>
                            <?php
                        }
                        for ($i = 1; $i < 4; $i++) {
                            ?><br>
                            <small>
                                <?php echo $_SESSION["_FILES"]["name"][count($_SESSION["_FILES"]["name"]) - $i]; ?>
                            </small>
                            <?php
                        }

                        ?>
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
                    }else{
                        $warn++;
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
                            $warn++;
                        ?></small>
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
                        <input style="<?php if(($_POST["debut"] >= $_POST["fin"]) && (($_POST["debut"] != '') && ($_POST["fin"] != ''))) {echo 'border: 3px solid red';} ?>" type="datetime-local" id="debut" name="debut" placeholer="20/10/2025"
                            value="<?php echo $_POST["debut"]; ?>" min="2025-01-01T00:00" max="2100-01-01T00:00" />
                            <?php
                                if(($_POST["debut"] >= $_POST["fin"]) && (($_POST["debut"] != '') && ($_POST["fin"] != ''))){
                                    ?>
                                    <br>
                                    <small class="warn"><?php
                                        echo $_POST["debut"] . ' '. $_POST["fin"] . ' ' . 'Le premieur horodatage est postérieur (ou égal) au second';
                                        $warn++;
                                    ?></small>
                                    <?php
                                }
                        ?>
                        <br />
                    </article>

                    <article>
                        <label for="fin">Date de fin de réduction</label>
                        <br>
                        <input style="<?php if(($_POST["debut"] >= $_POST["fin"]) && (($_POST["debut"] != '') && ($_POST["fin"] != ''))) {echo 'border: 3px solid red';} ?>" type="datetime-local" id="fin" name="fin" placeholer="20/10/2025"
                            value="<?php echo $_POST["fin"]; ?>" min="2025-01-01T00:00" max="2100-01-01T00:00" />
                        <br />
                    </article>
                </section>


            </div>
        </form>
    </main>
</body>

</html>