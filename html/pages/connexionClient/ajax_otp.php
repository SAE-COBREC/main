<?php
    session_start();
    require_once(__DIR__."/../../vendor/autoload.php");
    use OTPHP\TOTP;
    use OTPHP\Factory;
    //DÉBUT EXTRAIT SOURCE OTP
    //appelé lors de l'activation de l'OTP (création de compte avec OTP ou activation dans le profil)
    if(!(empty($_SESSION['OTP']['secret']) && empty($_SESSION['OTPvendeur']['secret']))){
        if (!empty($_SESSION['OTP']['secret']) && $_POST['send'] == 0){
            //si appel provient du côté client
            $otp = TOTP::createFromSecret($_SESSION['OTP']['secret']); //non déchiffrée car code ne provenant pas de la bdd (donc non chiffré)
        }else{
            //si appel provient de côté vendeur
            $otp = TOTP::createFromSecret($_SESSION['OTPvendeur']['secret']); //non déchiffrée car code ne provenant pas de la bdd (donc non chiffré)
        }
        $logFile = "../../pages/connexionClient/ajax.txt";
        if ($otp->verify(str_replace(' ', '', $_POST['code']), null, 20)){
            //code bon
            file_put_contents($logFile, "true");
        }else{
            //code faux
            file_put_contents($logFile, "false");
        }
        unset($_POST);
    }
?>