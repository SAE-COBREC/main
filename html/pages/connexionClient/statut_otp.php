<?php
session_start();
//DÉBUT EXTRAIT SOURCE OTP
include '../../selectBDD.php';
$pdo->exec("SET search_path TO cobrec1");
if (!(empty($_POST['statutOTP']))){
    if ($_POST['send'] == 0){
        $_SESSION['OTP']['statut'] = $_POST['statutOTP'];
        $secret = $_SESSION['OTP']['secret'];
    }else{
        $_SESSION['OTPvendeur']['statut'] = $_POST['statutOTP'];
        $secret = $_SESSION['OTPvendeur']['secret'];
    }
    try {//enregistrement du secret_A2F dans la BDD
        $sql = '
        UPDATE cobrec1._compte
        SET etat_otp = true,
        secret_otp = :secret
        WHERE id_compte = :idCompte;
        ';
        $stmt = $pdo->prepare($sql);
        $params = [
            'idCompte' => $_SESSION['idCompte'],
            'secret' => base64_encode($secret) // chiffrement de la clé
        ];
        $stmt->execute($params);
    } catch (Exception $e) {}
}
//FIN EXTRAIT SOURCE OTP
?>