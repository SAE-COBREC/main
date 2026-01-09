<?php
    session_start();
    $sth = null ;
    $dbh = null ;
    $_SESSION["remise"]["warn"]= 0; //réinitialisation des warnings
    include '../../../selectBDD.php';
    $pdo->exec("SET search_path to cobrec1");

    if(empty($_SESSION['vendeur_id']) === false){
        $_SESSION["remise"]['etat'] = 'pasSvg';
            if (empty($_GET['modifier']) === false){
                //si US modifier reduc
                //print_r("detect modif\n");
                if ((empty($_SESSION["remise"]['_GET'])) || ($_SESSION["remise"]['_GET']['produit'] != $_GET['modifier'])){
                    //print_r('1');
                    //si premier passage
                     $_SESSION["remise"]["warn"]++;
                    try {//Récupération des infos de la reduc
                        $sql = '
                        SELECT id_reduction, id_produit, reduction_pourcentage, reduction_debut, reduction_fin FROM cobrec1._reduction
                        WHERE id_produit = :modifier;'
                        ;
                        $stmt = $pdo->prepare($sql);
                        $params = [
                            'modifier' => $_GET['modifier']
                        ];
                        $stmt->execute($params);
                        $_SESSION["remise"]['_GET'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        if (empty($_SESSION["remise"]['_GET'][0])){
                            $_SESSION["remise"]['_GET'][0] = [];
                            $_SESSION["remise"]['_GET'][0]['produit'] = '';
                            $_SESSION["remise"]['_GET']['produit'] = '';
                            $_SESSION["remise"]['_GET']['id_produit'] = '';
                            $_SESSION["remise"]['_GET']['id_vendeur'] = [];
                            $_SESSION["remise"]['_GET']['id_vendeur']['id_vendeur'] = '';
                        }
                        $_SESSION["remise"]['_GET'] = $_SESSION["remise"]['_GET'][0];
                        $_SESSION["remise"]['_GET']['produit'] = $_SESSION["remise"]['_GET']['id_produit'];
                        if (!empty($_SESSION["remise"]['_GET']['id_produit'])){
                            unset($_SESSION["remise"]['_GET']['id_produit']);
                        }

                        // $sql = '
                        // SELECT id_produit FROM cobrec1._en_reduction WHERE id_reduction = :modifier;'
                        // ;
                        // $stmt = $pdo->prepare($sql);
                        // $params = [
                        //     'modifier' => $_GET['modifier']
                        // ];
                        // $stmt->execute($params);
                        // $_SESSION["remise"]['_GET']['produit'] = $stmt->fetch(PDO::FETCH_ASSOC);
                        // $_SESSION["remise"]['_GET']['produit'] = $_SESSION["remise"]['_GET']['produit']['id_produit'];

                        $sql = '
                        SELECT id_vendeur FROM cobrec1._produit
                        WHERE id_produit = :produit;'
                        ;
                        $stmt = $pdo->prepare($sql);
                        $params = [
                            'produit' => $_SESSION["remise"]['_GET']['produit']
                        ];
                        $stmt->execute($params);
                        $_SESSION["remise"]['_GET']['id_vendeur'] = $stmt->fetch(PDO::FETCH_ASSOC);
                        if (!empty($_SESSION["remise"]['_GET']['id_vendeur']['id_vendeur'])){
                            $_SESSION["remise"]['_GET']['id_vendeur'] = $_SESSION["remise"]['_GET']['id_vendeur']['id_vendeur'];
                        }
                        
                    
                    } catch (Exception $e) {
                        $time = time();
                        $_SESSION["remise"]['_GET'] = null;
                        $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] ="verif titre" . " " . $_SESSION["remise"]['_GET']['produit'];
                        $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] =$e;
                    ?>

                    <script>
                        alert("La valeur de la remise renseigné dans l'URL n'est pas valide. Vous allez être redirigé vers votre catalogue.");
                        document.location.href = "/pages/backoffice/index.php"; 
                    </script>

                    <?php
                    }

                    try {//Récupération des infos de la reduc
                        $sql = '
                        SELECT id_produit, id_vendeur FROM cobrec1._produit
                        WHERE id_produit = :modifier;'
                        ;
                        $stmt = $pdo->prepare($sql);
                        $params = [
                            'modifier' => $_GET['modifier']
                        ];
                        $stmt->execute($params);
                        $_SESSION["remise"]['_GET']['produit'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $_SESSION["remise"]['_GET']['id_vendeur']  = $_SESSION["remise"]['_GET']['produit'][0]['id_vendeur'];
                        $_SESSION["remise"]['_GET']['produit'] = $_SESSION["remise"]['_GET']['produit'][0]['id_produit'];
                        //print_r($_SESSION["remise"]['_GET']);
                        
                    
                    } catch (Exception $e) {}
                    
                    if($_SESSION["remise"]['_GET']['produit'] == null){
                        $_SESSION["remise"]['_GET'] = null;

                        ?>

                    <script>
                        alert("La valeur de la remise renseigné dans l'URL ne correspond à aucune remise et à aucun produit. Vous allez être redirigé vers votre catalogue.");
                        document.location.href = "/pages/backoffice/index.php"; 
                    </script>

                    <?php
                    }else if ($_SESSION["remise"]['_GET']['id_vendeur'] != $_SESSION['vendeur_id']){
                        // print_r($_SESSION["remise"]['_GET']['id_vendeur']);
                        // print_r($_SESSION['vendeur_id']);

                        $time = time();
                        $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] ="reduc ne vous appartient pas";
                        $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] =$_SESSION["remise"]['_GET']['id_vendeur'] . ' != ' . $_SESSION['vendeur_id'];

                        // $fp = fopen('file.csv', 'w');
                        // foreach ($_SESSION["remise"]['bdd_errors'] as $fields) {
                        //     fputcsv($fp, $fields, ',', '"', '');
                        // }
                        // fclose($fp);
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
                        if (empty($_SESSION["remise"]['_GET']['produit'])){
                            $_POST["produit"] = $_SESSION["remise"]['_GET']['produit'];
                            $_POST["pourcentage"] = $_SESSION["remise"]['_GET']['reduction_pourcentage'];
                            $_POST["debut"] = $_SESSION["remise"]['_GET']['reduction_debut'];
                            $_POST["fin"] = $_SESSION["remise"]['_GET']['reduction_fin'];
                        }
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
    <link rel="stylesheet" type="text/css" href="../../../../styles/creerArticle/creerArticle.css" media="screen">
    <title>Ébauche de produit</title>
    <link rel="icon" type="image/png" href="../../../img/favicon.svg">
</head>
<pre>
<?php

//print_r($_GET);
// print_r($_SESSION["remise"]['_GET']);
print_r($_POST);
print_r($_SESSION["remise"]);

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
            //si id_reduction n'est pas connu alors on paramètre les autres var du _GET afin d'éviter des warnings php
            //TODO plus tard
            $_SESSION["remise"]['_GET']['reduction_fin'] = '';
            $_SESSION["remise"]['_GET']['reduction_debut'] = '';
            $_SESSION["remise"]['_GET']['reduction_pourcentage'] = '';
            $_SESSION["remise"]['_GET']['id_reduction'] = '';
        }
        if(empty($_GET['modifier']) || empty($_SESSION["remise"]['_GET']['id_reduction'])){
            //si on a un ?modifier= dans l'URL ou que la remise n'existe pas
            echo 'Remise non sauvegardée';
        }else{
            echo 'Modifier la remise sauvegardée';
        }
        ?>
        </h2>
        <form action="index.php<?php 
            if(!empty($_SESSION["remise"]["_GET"]['produit'])){
                echo '?modifier=' . $_SESSION["remise"]["_GET"]['produit'];
            }
        ?>" method="post" enctype="multipart/form-data">
            
            <div>
                <section>
                <article>
                        <!-- Liste déroulante -->
                        <label for="produit">Produit</label>
                        <br>
                        <select id="produit" name="produit" required>
                        <option value=''></option>
                            <?php
                                if (empty($produit)){
                                    try {//Permets d'obtenir toutes les catégories de produits listées dans la BDD
                                        $sql = 'SELECT id_produit, p_nom, p_stock, p_statut FROM cobrec1._produit WHERE id_vendeur = :vendeur ORDER BY p_nom';
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

                                    // try {//
                                    //     $sql = 'SELECT id_produit FROM cobrec1._reduction WHERE id_produit = :produit';
                                    //     $stmt = $pdo->prepare($sql);
                                    //     $params = [
                                    //         'produit' => $_SESSION["remise"]['_GET']['produit']
                                    //     ];
                                    //     $stmt->execute($params);
                                    //     $warn2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    //     print_r($warn2);
                                    // } catch (Exception $e) {
                                    //     print_r($e);
                                    //     $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $e;
                                    // }
                                }

                                foreach ($produit as $value) {
                            ?>
                            <option 
                            value="<?php echo $value['id_produit']?>" <?php if ($_POST["produit"] == $value['id_produit']){echo 'selected';} ?>>
                            <?php echo $value['p_nom']  . ' (' . $value['p_statut'] . ' ; ' . $value['p_stock'] . ' en stock)' ?>
                            </option>
                            <?php } ?>
                        </select>
                        <br />
                    </article>
                    <article>
                        <label for="pourcentage">Pourcentage de remise</label>
                        <br>
                        <input type="number" id="pourcentage" name="pourcentage" value="<?php echo $_POST["pourcentage"];?>" step="0.01" min="1" max="99" placeholder="20,00" required/>

                        <br />
                    </article>

                    <article>
                        <label for="debut">Date de début de remise</label>
                        <br>
                        <input style="<?php if(/*(time() + 15 * 60 >= strtotime($_POST["debut"])) ||*/ ((($_POST["debut"] >= $_POST["fin"]) && (($_POST["debut"] != '') && ($_POST["fin"] != ''))) && ($_POST["btn_maj"] == null))) {echo 'border: 3px solid red';} ?>" type="datetime-local" id="debut" name="debut" placeholer="20/10/2025"
                            value="<?php echo $_POST["debut"]; ?>" min="2025-01-01T00:00" max="2100-01-01T00:00" required/>
                            <?php
                                if(($_POST["debut"] >= $_POST["fin"]) && (($_POST["debut"] != '') && ($_POST["fin"] != ''))){
                                    ?>
                                    <br>
                                    <small class="warn"><?php
                                        echo 'Le premieur horodatage est postérieur (ou égal) au second';
                                        $_SESSION["remise"]["warn"]++;
                                    ?></small>
                                    <?php
                                }
                                //if(time() + 15 * 60 >= strtotime($_POST["debut"])){
                                    ?>
                                    <!-- <br>
                                    <small class="warn"> --><?php
                                        // echo 'Le premieur horodatage est bloqué dans le passé';
                                        // $_SESSION["remise"]["warn"]++;
                                    ?><!-- </small> -->
                                    <?php
                                //}
                        ?>
                        <br />
                    </article>

                    <article>
                        <label for="fin">Date de fin de remise</label>
                        <br>
                        <input style="<?php if((($_POST["debut"] >= $_POST["fin"]) && (($_POST["debut"] != '') && ($_POST["fin"] != ''))) && ($_POST["btn_maj"] == null)) {echo 'border: 3px solid red';} ?>" type="datetime-local" id="fin" name="fin" placeholer="20/10/2025"
                            value="<?php echo $_POST["fin"]; ?>" min="2025-01-01T00:00" max="2100-01-01T00:00" required/>
                        <br />
                    </article>
                </section>


            </div>
            <input type="button" value="Annuler" title="Permets d'annuler la création de l'article et de revenir au catalogue."/>
            <?php
            if(empty($_SESSION["remise"]['_GET']['produit']) == false){//si la page est en mode US modification
                ?>
                <input type="submit" class="orange" name="svgModif" title="Sauvegarde les changements sans changer la visibilité de l'article." value="Sauvegarder les modifications" accesskey="s"/>
                <input type="button" name="suppr" title="Supprimer la remise." value="Supprimer la remise" accesskey="d"/>
                <script>
                        const btnSupprimer = document.querySelector("input[value='Supprimer la remise']");
                        btnSupprimer.addEventListener('click', () => {//si clic sur Supprimer
                            if (confirm("Êtes-vous certain de vouloir supprimer cette remise ?")) {
                                document.location.href="/pages/backoffice/supprRemise/index.php"; 
                            }
                        });
                    </script>
                <?php
                if (empty($_SESSION["remise"]['_GET']['id_reduction'])){
                    ?>
                    <script>
                        btnSupprimer.disabled = true; //grisage du bouton
                    </script>
                    <?php
                }else{
                    ?>
                    <script>
                        btnSupprimer.disabled = false; //dégrisage du bouton
                    </script>
                    <?php
                }
            }else{
                ?>
            <input class="orange" type="submit" name="publier" title="Un article publié est inscrit dans la base de données et est visible par les clients." value="Publier la remise dans le catalogue client" />
            <?php } ?>
            </form>
            <script>
            const btnAnnuler = document.querySelector("input[value='Annuler']");
            btnAnnuler.addEventListener('click', () => {//si clic sur Annuler
                if (confirm("Êtes-vous certain de vouloir annuler ? Ce que vous n'avez pas sauvegardé/publié sera perdu.")) {
                    document.location.href="/pages/backoffice/index.php"; 
                }
            });

            function sauvegarder(){//si clic sur sauvegarder et pas de warnings
                alert("Votre remise a bien été sauvegardé.");
                document.location.href = "/pages/backoffice/index.php"; 
            }

            function publier(){//si clic sur publier et pas de warnings
                alert("Votre remise a bien été appliquée.");
                document.location.href = "/pages/backoffice/index.php"; 
            }

            function avertirEcrasement(){//si clic sur publier, pas de warnings et écrasement produit
                if (confirm("Votre remise a écrasée la remise précédente appliquée sur ce produit. Souhaitez-vous contineur à modifier la remise ?")) {
                }else{document.location.href="/pages/backoffice/index.php"; 
                }
            }

            function svgModif(){//si clic sur svgModif et pas de warnings
                if (confirm("Vos modifications ont bien été sauvegardées. Souhaitez-vous continuer à modifier la remise ?.")) {
                }else{document.location.href="/pages/backoffice/index.php"; 
                }
            }
        </script>
            <pre>
                <?php 
                    if (empty($_POST["produit"])){
                        $_POST["produit"] = '';
                    }
                    if (($_SESSION["remise"]["warn"] === 0) && ($_POST["produit"] !== '')){
                        $_SESSION["remise"]["warn"]++;
                        $time = time();
                        // print_r("WARNS : " . $_SESSION["remise"]["warn"]);

                            //Si pas de warning et formulaire soumis via le bouton Sauvegarder ou le bouton Annuler

                            //sert à ne pas avoir de warnings php sur le serv
                            if (empty($_POST["publier"])){
                                $_POST["publier"] = '';
                            }
                            if (empty($_POST["svgModif"])){
                                $_POST["svgModif"] = '';
                            }

                            
                        
                            
                        
                        if ($_POST["publier"] == "Publier la remise dans le catalogue client"  || (empty($_SESSION["remise"]['_GET']['id_reduction']) && ($_POST["svgModif"] == "Sauvegarder les modifications"))){
                            //si la remise n'existe pas (même si l'URL nous fait croire que l'on en modifie une, ce qui en fait n'est pas le cas)
                            try {//création de l'objet reduction
                                $sql = '
                                INSERT INTO cobrec1._reduction(id_produit, reduction_pourcentage, reduction_debut, reduction_fin)
                                VALUES (:produit, :pourcentage, :debut, :fin);
                                ';
                                $stmt = $pdo->prepare($sql);
                                $params = [
                                    'pourcentage' => $_POST['pourcentage'],
                                    'debut' => $_POST['debut'],
                                    'fin' => $_POST['fin'],
                                    'produit' => $_POST['produit']
                                ];
                                $stmt->execute($params);
                                $_SESSION["remise"]['_GET']['id_reduction'] = $pdo->lastInsertId();
                                ?>
                                <script>
                                    btnSupprimer.disabled = false; //dégrisage du bouton
                                    const titre = document.querySelector("h2");
                                    titre.textContent = 'Modifier la remise sauvegardée';
                                </script>
                                <?php
                                //print_r('INSERT ' . $_POST['produit']);
                            } catch (Exception $e) {
                                //$_SESSION["remise"]['bdd_errors'] sert pour consulter les erreurs de la BDD
                                $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] ="création de l'objet reduction dans la base";
                                $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $e;
                            }

                            // try {//création de l'affiliation entre reduction et produit
                            //     $sql = '
                            //     INSERT INTO cobrec1._en_reduction(id_produit,id_reduction)
                            //     VALUES (
                            //     :produit, :id_reduc);
                            //     ';
                            //     $stmt = $pdo->prepare($sql);
                            //     $params = [
                            //         'id_reduc' => $pdo->lastInsertId(), 
                            //         'produit' => $_POST['produit']
                            //     ];
                            //     $stmt->execute($params);
                            // } catch (Exception $e) {
                            //     $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] ="création de l'affiliation entre reduction et produit";
                            //     $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $e;
                            // }
                            
                            if ($_POST["publier"] == "Publier la remise dans le catalogue client"){
                        
                    ?>
                    <script>
                        publier();
                    </script>
    <?php
                            }
                    
                }else if ($_POST["svgModif"] == "Sauvegarder les modifications"){
                    $_SESSION["remise"]["warn"]++;
                    //Si pas de warning et formulaire soumis via le bouton Mettre en ligne/hors ligne
                    
                    try {//modif de l'objet reduction dans la base
                        $sql = '
                        UPDATE cobrec1._reduction 
                        SET reduction_pourcentage = :pourcentage, 
                        id_produit = :produit,
                        reduction_debut = :debut, 
                        reduction_fin = :fin   
                        WHERE id_reduction = :getId;
                        ';
                        $stmt = $pdo->prepare($sql);
                        $params = [
                            'pourcentage' => $_POST['pourcentage'],
                            'debut' => $_POST['debut'],
                            'fin' => $_POST['fin'],
                            'getId' => $_SESSION["remise"]['_GET']['id_reduction'],
                            'produit' => $_POST['produit']
                        ];
                        //print_r('UPDATE' . $_POST['produit']);
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

                    // if ($_SESSION["remise"]['_GET']['id_produit'] != $_POST['produit']){
                    //     try {//modif de l'affiliation entre catégorie et produit
                    //         $sql = '
                    //         UPDATE cobrec1._en_reduction
                    //         SET id_produit = :produit
                    //         WHERE id_reduction = :getId;
                    //         ';
                    //         $stmt = $pdo->prepare($sql);
                    //         $params = [
                    //             'produit' => $_POST['produit'],
                    //             'getId' => $_SESSION["remise"]['_GET']['id_reduction']
                    //         ];
                    //         $stmt->execute($params);
                    //     } catch (Exception $e) {
                    //         $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] ="modif de l'affiliation entre reduction et produit";
                    //         $_SESSION["remise"]['bdd_errors'][date("d-m-Y H:i:s",$time)][] = $e;
                    //     }
                    // }
                }

                if (empty($_SESSION["remise"]['bdd_errors']) !== true){
                    //Sert pour consulter les erreurs de la BDD via un fichier dédié
                    // $fp = fopen('file.csv', 'w');
                    // foreach ($_SESSION["remise"]['bdd_errors'] as $fields) {
                    //     fputcsv($fp, $fields, ',', '"', '');
                    // }
                    // fclose($fp);
                }
                

            }
            if (($_SESSION["remise"]["warn"] === 0) && ($_POST["produit"] !== '') && ($_POST["publier"] == "Publier la remise dans le catalogue client")){
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