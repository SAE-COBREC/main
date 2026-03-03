<?php
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

//stockage secret en BDD.

// // Note: use your own way to load the user secret.
// // The function "load_user_secret" is simply a placeholder.
// $secret = load_user_secret();
// $otp = TOTP::createFromSecret($secret);
// echo "The current OTP is: {$otp->now()}\n";