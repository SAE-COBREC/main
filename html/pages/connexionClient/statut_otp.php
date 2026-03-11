<?php
session_start();
include '../../selectBDD.php';
$pdo->exec("SET search_path TO cobrec1");
// file_put_contents("../../pages/connexionClient/log_ajax.txt", 'statut OPT pre-if :' . $_POST['statutOTP']);
if (!(empty($_POST['statutOTP']))){
    // file_put_contents("../../pages/connexionClient/log_ajax.txt", 'statut OPT post-if :' . $_POST['statutOTP']);
    $_SESSION['OTP']['statut'] = $_POST['statutOTP'];
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
            'secret' => $_SESSION['OTP']['secret']
        ];
        $stmt->execute($params);
} catch (Exception $e) {
    //file_put_contents("../../pages/connexionClient/log_ajax.txt", $e);
}
}
?>