<?php
    session_start();
    $sth = null ;
    $dbh = null ;
    include '../../../selectBDD.php';
    $pdo->exec("SET search_path to cobrec1");

    if (!empty($_SESSION["remise"]['_GET']['id_reduction'])){
        try {//suppr de l'objet reduction dans la base
            $sql = '
            DELETE FROM cobrec1._reduction 
            WHERE id_reduction = :getId;
            ';
            $stmt = $pdo->prepare($sql);
            $params = [
                'getId' => $_SESSION["remise"]['_GET']['id_reduction']
            ];
            $stmt->execute($params);
        } catch (Exception $e) {}
        $_SESSION['remise'] = [];
    }
    header("Location: ../index.php");
    exit(0);

?>