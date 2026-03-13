<?php
    session_start();
    include '../../selectBDD.php';
    $pdo->exec("SET search_path TO cobrec1");
    require_once(__DIR__."/../../vendor/autoload.php");
    use OTPHP\TOTP;
    use OTPHP\Factory;
    try {//recherche du secret_OTP dans la BDD
        if(!empty($_SESSION['OTP']['secret'])){
            $otp = TOTP::createFromSecret($_SESSION['OTP']['secret']);
            $logFile = "../../pages/ProfilClient/verif_code.txt";
            if ($otp->verify(str_replace(' ', '', $_POST['code']), null, 20)){
                file_put_contents($logFile, "true");
            }else{
                file_put_contents($logFile, "false");
            }
            unset($_POST['code']);
        }
    } catch (Exception $e) {}
?>