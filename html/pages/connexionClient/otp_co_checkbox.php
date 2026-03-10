<?php
session_start();
if ($_POST['etat_OTP'] == 'true'){
    $_SESSION['OTP']['etatCheckbox'] = $_POST['etat_OTP'];
}else{
    unset($_SESSION['OTP']['etatCheckbox']);
}
?>