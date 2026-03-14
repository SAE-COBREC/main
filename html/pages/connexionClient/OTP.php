<?php
include '../../selectBDD.php';
include '../../../selectBDD.php';
$pdo->exec("SET search_path TO cobrec1");
require_once(__DIR__."/../../vendor/autoload.php");
require_once(__DIR__."/../../vendor/QRcodes/vendor/autoload.php");
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
$grCodeUri = $otp->getQrCodeUri(
    'https://api.qrserver.com/v1/create-qr-code/?data=[DATA]&size=300x300&ecc=M',
    '[DATA]'
);
//echo "<img src='{$grCodeUri}'>";
$writer = new PngWriter();

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
?>