<?php
    session_start();
    $sth = null ;
    $dbh = null ;
    include '../../../selectBDD.php';
    $pdo->exec("SET search_path to cobrec1");

    if (!empty($_SESSION["promotion"]['_GET']['id_promotion'])){
        try {//suppr de l'objet promotion dans la base
            $sql = '
            DELETE FROM cobrec1._promotion 
            WHERE id_promotion = :getId;
            ';
            $stmt = $pdo->prepare($sql);
            $params = [
                'getId' => $_SESSION["promotion"]['_GET']['id_promotion']
            ];
            $stmt->execute($params);
        } catch (Exception $e) {}
        $_SESSION['promotion'] = [];
        $_SESSION['reduction'] = [];
    }
    header("Location: ../index.php");
    exit(0);

?>