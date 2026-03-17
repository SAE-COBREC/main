<?php
session_start();
//si OTP activé et code bon (appelé par creationVendeur)
if (!(empty($_POST['statutOTP']))){
    $_SESSION['OTPvendeur']['statut'] = 'active';
}