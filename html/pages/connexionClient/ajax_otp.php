<?php
    session_start();
    include '../../selectBDD.php';
    $pdo->exec("SET search_path TO cobrec1");
    require_once(__DIR__."/../../vendor/autoload.php");
    use OTPHP\TOTP;
    use OTPHP\Factory;
    try {//recherche du secret_A2F dans la BDD
        $sql = '
        SELECT secret_A2F FROM cobrec1._compte
        WHERE id_compte = :idCompte;
        ';
        $stmt = $pdo->prepare($sql);
        $params = [
            'idCompte' => $_SESSION['idCompte']
        ];
        $stmt->execute($params);
        print_r($_POST);
        print_r("Secret A2F :\n");
        $secret = ($stmt->fetchAll(PDO::FETCH_ASSOC))[0]['secret_a2f']; //cherche dans BDD
        if(empty($secret)){
            print_r("empty");
        }else{
            print_r("OK");
            $otp = TOTP::createFromSecret($secret);
            //$otp = Factory::loadFromProvisioningUri($secret);
            $logFile = "ajax.txt";
            //file_put_contents("log_ajax.txt", 'code OPT :' . $otp->now() . ' code rentré :' . $_POST['code'] . ' secret de BDD :' . $secret . ' secret de OPT généré avec secret de BDD :' . $otp->getSecret());
            if ($otp->now() == $_POST['code']){
                file_put_contents($logFile, "true");
            }else{
                file_put_contents($logFile, "false");
            }
            unset($_POST['code']);
        }
    } catch (Exception $e) {}
?>