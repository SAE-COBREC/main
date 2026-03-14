<?php
session_start();
if (!(empty($_POST['statutOTP']))){
    $_SESSION['OTPvendeur']['statut'] = 'active';
}