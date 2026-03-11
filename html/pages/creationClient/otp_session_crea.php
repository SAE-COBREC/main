<?php
session_start();
if (!(empty($_POST['statutOTP']))){
    $_SESSION['OTP']['statut'] = 'active';
}