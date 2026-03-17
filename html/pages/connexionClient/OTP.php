<?php
//DÉBUT EXTRAIT SOURCE OTP
//nécessités pour la génération de QR code et de l'OTP
try{
    require_once(__DIR__."/../../vendor/autoload.php");
    require_once(__DIR__."/../../vendor/QRcodes/vendor/autoload.php");
}catch(Exception $e){
    try{
        require_once(__DIR__."/../../../vendor/autoload.php");
        require_once(__DIR__."/../../../vendor/QRcodes/vendor/autoload.php");
    }catch(Exception $e2){}
}

$pdo->exec("SET search_path TO cobrec1");
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\ValidationException;

use OTPHP\TOTP;

function retry(){
    // A random secret will be generated from this.
    // You should store the secret with the user for verification.
    $otp = TOTP::generate();


    if (!empty($donneesInformationsClient['c_pseudo'])){
        $otp = $otp->withLabel('Alizon - ' . $donneesInformationsClient['c_pseudo']);
    }else if (!empty($_POST['pseudo'])){
        $otp = $otp->withLabel('Alizon - ' . $_POST['pseudo']);
    }else{
        $otp = $otp->withLabel('Alizon');
    }
    $writer = new PngWriter();
    try{
        // Create QR code
        $qrCode = new QrCode(
            data: $otp->getProvisioningUri(),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Low,
            size: 300,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255)
        );

        $result = $writer->write($qrCode, null, null);

        // Validate the result
        $writer->validateResult($result, $otp->getProvisioningUri());
        return [$otp,$result];
    }catch(Exception $e){
        return retry();
        //si exception alors on réessaie
    }
}
[$otp,$result] = retry();
//FIN EXTRAIT SOURCE OTP
?>