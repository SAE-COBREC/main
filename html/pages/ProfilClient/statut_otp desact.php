<?php
session_start();
include '../../selectBDD.php';
$pdo->exec("SET search_path TO cobrec1");
if (!(empty($_POST['statutOTP']))){
    try {//enregistrement du secret_A2F dans la BDD
        $sql = '
        UPDATE cobrec1._compte
        SET etat_otp = false,
        WHERE id_compte = :idCompte;
        ';
        $stmt = $pdo->prepare($sql);
        $params = [
            'idCompte' => $_SESSION['idCompte']
        ];
        $stmt->execute($params);
        unset($_SESSION['OTP']['statut']);
    } catch (Exception $e) {}
}
?>