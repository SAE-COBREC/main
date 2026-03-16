<?php
    session_start();
    require_once(__DIR__."/../../vendor/autoload.php");
    use OTPHP\TOTP;
    use OTPHP\Factory;
    if(empty($_SESSION['OTP']['secret']) && empty($_SESSION['OTPvendeur']['secret'])){
        print_r("empty");
    }else{
        if (!empty($_SESSION['OTP']['secret']) && $_POST['send'] == 0){
            $otp = TOTP::createFromSecret($_SESSION['OTP']['secret']);
            //file_put_contents("../../pages/connexionClient/log_ajax.txt", 'OTP secret :' . $_SESSION['OTP']['secret']);
        }else{
            $otp = TOTP::createFromSecret($_SESSION['OTPvendeur']['secret']);
            //file_put_contents("../../pages/connexionClient/log_ajax.txt", 'idCompte :' . $_SESSION['idCompte'] . ' OTPvendeur secret :' . $_SESSION['OTPvendeur']['secret']);
        }
        $logFile = "../../pages/connexionClient/ajax.txt";
        if ($otp->verify(str_replace(' ', '', $_POST['code']), null, 20)){
            file_put_contents($logFile, "true");
        }else{
            file_put_contents($logFile, "false");
        }
        unset($_POST);
    }
?>