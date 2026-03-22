<?php
session_start();
include '../../selectBDD.php';
$pdo->exec("SET search_path TO cobrec1");
if (!(empty($_POST['statutOTP']))){
    try {//désactivation de l'A2F dans la bdd
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
        if ($_POST['send'] == 0){
            unset($_SESSION['OTP']['statut']);
        }else{
            unset($_SESSION['OTPvendeur']['statut']);
        }
    } catch (Exception $e) {}
}
?>