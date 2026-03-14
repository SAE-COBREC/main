<?php
    session_start();
    include '../../selectBDD.php';
    $pdo->exec("SET search_path TO cobrec1");
    require_once(__DIR__."/../../vendor/autoload.php");
    use OTPHP\TOTP;
    use OTPHP\Factory;
    if(empty($_SESSION['OTP']['secret']) && empty($_SESSION['OTPvendeur']['secret'])){
        print_r("empty");
    }else{
        if (!empty($_SESSION['OTP']['secret'])){
            $otp = TOTP::createFromSecret($_SESSION['OTP']['secret']);
        }else{
            $otp = TOTP::createFromSecret($_SESSION['OTPvendeur']['secret']);
        }
        $logFile = "../../pages/connexionClient/ajax.txt";
        //file_put_contents("../../pages/connexionClient/log_ajax.txt", 'code OPT :' . $codeOpt . ' code rentré :' . $_POST['code'] . ' secret de BDD :' . $secret . ' secret de OPT généré avec secret de BDD :' . $otp->getSecret());
        if ($otp->verify(str_replace(' ', '', $_POST['code']), null, 20)){
            file_put_contents($logFile, "true");
            if (!empty($_SESSION['idCompte'])){
                try {//enregistrement du secret_OTP dans la BDD
                    $sql = '
                    UPDATE cobrec1._compte
                    SET secret_OTP = :secret
                    WHERE id_compte = :idCompte;
                    ';
                    $stmt = $pdo->prepare($sql);
                    $params = [
                        'idCompte' => $_SESSION['idCompte'],
                        'secret' => $otp->getSecret()
                    ];
                    $stmt->execute($params);
                } catch (Exception $e) {}
            }
        }else{
            file_put_contents($logFile, "false");
        }
        unset($_POST['code']);
    }
?>