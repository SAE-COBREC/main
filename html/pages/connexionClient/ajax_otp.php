<?php
    session_start();
    require_once(__DIR__."/../../vendor/autoload.php");
    use OTPHP\TOTP;
    // $secret = load_user_secret(); //cherche dans BDD
    $otp = TOTP::createFromSecret(/*$secret*/ $otp->getSecret());
    $logFile = "ajax.txt";
    if ($otp->now() == $_POST['code']){
        file_put_contents($logFile, "true");
    }else{
        file_put_contents($logFile, "false");
    }
    unset($_POST['code']);
?>