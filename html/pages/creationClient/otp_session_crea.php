<?php
session_start();
//si OTP activé et code bon (appelé par creationClient)
if (!(empty($_POST['statutOTP']))){
    $_SESSION['OTP']['statut'] = 'active';
}