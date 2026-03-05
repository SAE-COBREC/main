<?php
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
?>
<form id="a2form">
<img src='<?php echo $result->getDataUri() ?>' width="250em" height="250em">
<label>
<p>Code secret :</p>
<small><?php echo $otp->getSecret() ?></small>
</label>
<?php

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
    <input type="text" inputmode="numeric" pattern="[0-9]{6}" placeholder="123456" name="code"/>
    <button type="submit">Valider</button>
</form>
<script>
    document.getElementById('a2form').addEventListener('submit', function(event) {
        event.preventDefault();
        const formData = new FormData(event.target);
        const code = formData.get('code');

        const xhttp = new XMLHttpRequest();
        xhttp.open("POST", "../../pages/connexionClient/ajax_otp.php", true);
        xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xhttp.send("code="+code);


        const xhttp2 = new XMLHttpRequest();
        xhttp2.open("GET", "../../pages/connexionClient/ajax.txt", true);
        xhttp2.send();
        xhttp2.onreadystatechange = () => {
        if (xhttp2.readyState === xhttp2.HEADERS_RECEIVED) {
            const contentLength = xhttp2.getResponseHeader("Content-Length");
            if (contentLength == 4) {
                xhttp2.abort();
                alert("Authentification à double facteur activée avec succès.");
                //document.location.href = "/index.php"; 
                document.getElementById('modalA2F').style.display = 'none';

                const xhttp3 = new XMLHttpRequest();
                xhttp3.open("POST", "../../pages/connexionClient/statut_otp.php", true);
                xhttp3.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                xhttp3.send("statutOTP=active");
            }else{
                alert("Echec." + contentLength);
            }
        }
        };
    });


    
    
</script>