<?php
    session_start();
    include '../../selectBDD.php';
    $pdo->exec("SET search_path TO cobrec1");
    require_once(__DIR__."/../../vendor/autoload.php");
    use OTPHP\TOTP;
    try {//recherche nb de Promotions appartenant au vendeur
        $sql = '
        SELECT secret_A2F FROM cobrec1._compte
        WHERE id_compte = :idCompte;
        ';
        $stmt = $pdo->prepare($sql);
        $params = [
            'idCompte' => $_SESSION['idCompte']
        ];
        $stmt->execute($params);
        print_r("Secret A2F :\n");
        if(empty(($stmt->fetchAll(PDO::FETCH_ASSOC))[0]['secret_a2f'])){
            print_r("empty");
        }else{
            $secret = ($stmt->fetchAll(PDO::FETCH_ASSOC))[0]['secret_a2f']; //cherche dans BDD
            $_SESSION['A2F']['secret'] = $secret;
            $otp = TOTP::createFromSecret(/*$secret*/ $otp->getSecret());
            $logFile = "ajax.txt";
            if ($secret == $_POST['code']){
                file_put_contents($logFile, "true");
            }else{
                file_put_contents($logFile, "false");
            }
            unset($_POST['code']);
        }
    } catch (Exception $e) {}
?>