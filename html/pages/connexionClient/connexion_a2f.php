<?php session_start(); ?>
<?php if (!empty($_SESSION['A2F'])){ ?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion A2F - Alizon</title>
    <link rel="icon" type="image/png" href="../../img/favicon.svg">
    <link rel="stylesheet" href="../../styles/Connexion_Creation/styleCoCrea.css">
</head>
<body>
    <main>
        <div class="card">
            <div class="logo">
                <img src="../../img/svg/logo-text.svg" alt="Logo Alizon"
                    onclick="window.location.href='../../index.php'">
            </div>

            <h1>Connexion à double facteurs</h1>
            <form id="otporm">
                <input type="text" inputmode="numeric" pattern="[0-9]{6}" placeholder="123456" name="code" />
                <div class="connex-btn">
                    <button type="submit" onclick="finishRegistration()">
                        Valider
                    </button>
                </div>
            </form>
    <?php
    $otp = TOTP::createFromSecret($_SESSION['OTP']['secret']);
    if ($otp->verify($_POST['code'], null, 20)){
        $_SESSION['idClient'] = $_SESSION['A2F']['idClient'];
        $_SESSION['idCompte'] = $_SESSION['A2F']['idCompte'];
        unset($_SESSION['A2F']);
    }else{
        ?>
        <div class='error'>
            <strong>Erreur :</strong>
            Code OTP incorrect
        <?php
    }
    ?>
            </div>
        </main>
    </body>
</html>
<?php
}else{
    print_r("redirection");
    //$url = '../../index.php';
    //echo '<!doctype html><html lang="fr"><head><meta http-equiv="refresh" content="0;url='.$url.'">';
}


?>
</pre>