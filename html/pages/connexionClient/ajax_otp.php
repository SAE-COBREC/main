<?php
    session_start();
    include '../../selectBDD.php';
    $pdo->exec("SET search_path TO cobrec1");
    require_once(__DIR__."/../../vendor/autoload.php");
    use OTPHP\TOTP;
    use OTPHP\Factory;
    try {//recherche du secret_A2F dans la BDD
        // $sql = '
        // SELECT secret_A2F FROM cobrec1._compte
        // WHERE id_compte = :idCompte;
        // ';
        // $stmt = $pdo->prepare($sql);
        // $params = [
        //     'idCompte' => $_SESSION['idCompte']
        // ];
        // $stmt->execute($params);
        // print_r($_POST);
        // print_r("Secret A2F :\n");
        // $secret = ($stmt->fetchAll(PDO::FETCH_ASSOC))[0]['secret_a2f']; //cherche dans BDD
        if(empty($_SESSION['A2F']['secret'])){
            print_r("empty");
        }else{
            print_r("OK");
            $otp = TOTP::createFromSecret($_SESSION['A2F']['secret']);
            $logFile = "../../pages/connexionClient/ajax.txt";
            $codeOpt = $otp->now();
            file_put_contents("../../pages/connexionClient/log_ajax.txt", 'code OPT :' . $codeOpt . ' code rentré :' . $_POST['code'] . ' secret de BDD :' . $secret . ' secret de OPT généré avec secret de BDD :' . $otp->getSecret());
            if ($codeOpt == $_POST['code']){
                file_put_contents($logFile, "true");
                if (!empty($_SESSION['idCompte'])){
                    try {//enregistrement du secret_A2F dans la BDD
                        $sql = '
                        UPDATE cobrec1._compte
                        SET secret_A2F = :secret
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
    } catch (Exception $e) {}
?>