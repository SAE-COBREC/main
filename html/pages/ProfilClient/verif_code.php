<?php
    session_start();
    include '../../selectBDD.php';
    $pdo->exec("SET search_path TO cobrec1");
    require_once(__DIR__."/../../vendor/autoload.php");
    use OTPHP\TOTP;
    use OTPHP\Factory;
    try {//recherche du secret_OTP dans la BDD
        if(!empty($_POST['code'])){
            $stmt = $pdo->prepare("SELECT secret_otp, etat_otp FROM _compte WHERE id_compte = :compte");
            $stmt->execute([':compte' => $_SESSION['idCompte']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $otp = TOTP::createFromSecret($row['secret_otp']);
            $logFile = "../../pages/ProfilClient/verif_code.txt";
            if ($otp->verify(str_replace(' ', '', $_POST['code']), null, 20)){
                $stmt = $pdo->prepare("UPDATE _compte SET etat_otp = false WHERE id_compte = :compte");
                $stmt->execute([':compte' => $_SESSION['idCompte']]);
                file_put_contents($logFile, "true");
            }else{
                file_put_contents($logFile, "false");
            }
            unset($_POST['code']);
            unset($_SESSION['OTP']['statut']);
        }
    } catch (Exception $e) {}
?>