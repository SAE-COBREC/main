<?php
session_start();
require_once(__DIR__."/../../vendor/autoload.php");
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
echo "<img src='{$grCodeUri}'>";

try {//recherche nb de Promotions appartenant au vendeur
    $sql = '
    SELECT  FROM cobrec1._promotion
    INNER JOIN cobrec1._produit ON 
    _promotion.id_produit = _produit.id_produit
    WHERE id_vendeur = :idVendeur;
    ';
    $stmt = $pdo->prepare($sql);
    $params = [
        'idCompte' => $_SESSION['idCompte']
    ];
    $stmt->execute($params);
    if (count($stmt->fetchAll(PDO::FETCH_ASSOC)) >= 2){?>
    <script>
        alert("Vous avez créé plus de deux promotions. Vous ne pouvez donc plus créer de promotion. Vous pouvez toutefois toujours modifier ou supprimer des promotions.");
        document.location.href = "/pages/backoffice/index.php"; 
    </script>
    <?php
    exit(0);
}
} catch (Exception $e) {}

//stockage secret en BDD.

// // Note: use your own way to load the user secret.
// // The function "load_user_secret" is simply a placeholder.


?>
<script>
const xhttp = new XMLHttpRequest();
xhttp.open("POST", "./ajax_otp.php", true);
// xhttp.send("1234");
xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
xhttp.onreadystatechange = () => {
  // Call a function when the state changes.
  if (xhttp.readyState === XMLHttpRequest.DONE && xhttp.status === 200) {
    // Request finished. Do processing here.
  }
};
xhttp.send("code=1234");

xhttp.open("GET", "./ajax.txt", true);
xhttp.send();
xhttp.onreadystatechange = () => {
  if (xhttp.readyState === xhttp.HEADERS_RECEIVED) {
    const contentLength = xhttp.getResponseHeader("Content-Length");
    if (contentLength === 4) {
        xhttp.abort();
        alert("Authentification à double facteur activée avec succès.");
        //document.location.href = "/index.php"; 
    }else{
        alert("Echec.");
    }
  }
};
</script>