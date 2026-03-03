<?php
    // require_once(__DIR__.'../../../autoload.php');
    // require_once('./TOTP.php');
    use OTPHP\TOTP;

    $otp = TOTP::generate();
    echo "The OTP secret is: {$otp->getSecret()}\n";

?>