<?php
session_start();
include '../../selectBDD.php';
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
echo "The OTP secret is: {$otp->getSecret()}\n";

$otp = $otp->withLabel('Alizon');
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
echo "<img src='{$result->getDataUri()}'>";

try {//enregistrement du secret_A2F dans la BDD
    $sql = '
    UPDATE cobrec1._compte
    SET secret_A2F = :secret
    WHERE id_compte = :idCompte;
    ';
    $stmt = $pdo->prepare($sql);
    $params = [
        'idCompte' => $_SESSION['idCompte'],
        'secret' => /*$otp->getProvisioningUri()*/$otp->getSecret()
    ];
    $stmt->execute($params);
} catch (Exception $e) {}
?>
<form id="a2form">
    <input type="number" name="code"/>
    <button type="submit">Valider</button>
</form>
<p id="output"></p>
<script>
    document.getElementById('a2form').addEventListener('submit', function(event) {
        event.preventDefault();
        const formData = new FormData(event.target);
        const code = formData.get('code');
        document.getElementById('output').innerText = `code: ${code}`;

        const xhttp = new XMLHttpRequest();
        xhttp.open("POST", "./ajax_otp.php", true);
        // xhttp.send("1234");
        xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        // xhttp.onreadystatechange = () => {
        // // Call a function when the state changes.
        // if (xhttp.readyState === XMLHttpRequest.DONE && xhttp.status === 200) {
        //     // Request finished. Do processing here.
        // }
        // };
        xhttp.send("code="+code);


        const xhttp2 = new XMLHttpRequest();
        xhttp2.open("GET", "./ajax.txt", true);
        xhttp2.send();
        xhttp2.onreadystatechange = () => {
        if (xhttp2.readyState === xhttp2.HEADERS_RECEIVED) {
            const contentLength = xhttp2.getResponseHeader("Content-Length");
            if (contentLength == 4) {
                xhttp2.abort();
                alert("Authentification à double facteur activée avec succès.");
                //document.location.href = "/index.php"; 
            }else{
                alert("Echec." + contentLength);
            }
        }
        };
    });


    
    
</script>