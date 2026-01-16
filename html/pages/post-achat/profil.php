<?php
    session_start();
    $sth = null ;
    $dbh = null ;
    include '../../selectBDD.php';
    $pdo->exec("SET search_path to cobrec1");
    $rechercheNom='';

    // if (empty($_SESSION['idClient'])){
    //     header("Location: ../connexionClient/index.php");
    // }

    if (!empty($_GET['id'])){
        try {//Récupération des infos de la reduc
            $sql = '
            SELECT id_facture, id_panier, id_adresse, nom_destinataire, prenom_destinataire, f_total_ht, f_total_remise, f_total_ttc FROM cobrec1._facture
            WHERE id_panier = :panier;'
            ;
            $stmt = $pdo->prepare($sql);
            $params = [
                'panier' => $_GET['id']
            ];
            $stmt->execute($params);
            $_SESSION["post-achat"]["facture"] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $_SESSION["post-achat"]["facture"] = $_SESSION["post-achat"]["facture"][0];

            if (empty($_SESSION['vendeur_id'])){
                $sql = '
                SELECT id_panier, id_produit, quantite, prix_unitaire, remise_unitaire, frais_de_port, TVA FROM cobrec1._contient
                WHERE id_panier = :panier_commande;'
                ;
                $stmt = $pdo->prepare($sql);
                $params = [
                'panier_commande' => $_SESSION["post-achat"]["facture"]["id_panier"]
                ];
            }else{
                $sql = '
                SELECT id_panier, _contient.id_produit, quantite, prix_unitaire, remise_unitaire, frais_de_port, TVA, id_vendeur FROM cobrec1._contient
                INNER JOIN cobrec1._produit ON _contient.id_produit = _produit.id_produit
                WHERE id_panier = :panier_commande AND id_vendeur= :id_vendeur;'
                ;
                $stmt = $pdo->prepare($sql);
                $params = [
                    'panier_commande' => $_SESSION["post-achat"]["facture"]["id_panier"],
                    'id_vendeur' => $_SESSION['vendeur_id']
                ];
            }
            $stmt->execute($params);
            $_SESSION["post-achat"]["contient"] = $stmt->fetchAll(PDO::FETCH_ASSOC);


            $sql = '
            SELECT id_client, timestamp_commande FROM cobrec1._panier_commande
            WHERE id_panier = :panier_commande;'
            ;
            $stmt = $pdo->prepare($sql);
            $params = [
                'panier_commande' => $_SESSION["post-achat"]["facture"]["id_panier"]
            ];
            $stmt->execute($params);
            $_SESSION["post-achat"]["panier"] = $stmt->fetchAll(PDO::FETCH_ASSOC)[0];

            // if ($_SESSION["post-achat"]["panier"]["id_client"] != $_SESSION['idClient']){
            //     header("Location: ../../index.php");
            // }
        }catch (Exception $e){}
    }


    header("Location: /pages/post-achat/impression.php");
    exit(0);

?>