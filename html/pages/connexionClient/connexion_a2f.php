<?php 
//DÉBUT EXTRAIT SOURCE OTP
session_start(); 
require_once(__DIR__."/../../vendor/autoload.php");
use OTPHP\TOTP;
use OTPHP\Factory;
?>
<?php if (!empty($_SESSION['A2F'])){//si l'utilisateur est arrivé via la redirection de connexion ?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion A2F - Alizon</title>
    <link rel="icon" type="image/png" href="../../img/favicon.svg">
    <link rel="stylesheet" href="../../styles/Connexion_Creation/styleCoCrea.css">
    <link rel="stylesheet" href="../../styles/Connexion_Creation/clientCoCrea.css">
</head>
<body>
    <main>
        <div class="card">
            <div class="logo">
                <img src="../../img/svg/logo-text.svg" alt="Logo Alizon"
                    onclick="window.location.href='../../index.php'">
            </div>
            <form action="connexion_a2f.php" method="post" enctype="multipart/form-data">
                <h1>Connexion à double facteurs</h1>
                <input type="text" inputmode="numeric" pattern="[0-9]{3} [0-9]{3}" min="7" max="7" placeholder="123 456" name="code" />
                <div class="connex-btn">
                    <button type="submit">
                        Valider
                    </button>
                </div>
                <div class="error2">
                    <strong>Erreur</strong> : Code A2F incorrect
                </div>
                <script>
                    document.querySelector(".error2").style.display = 'none';
                </script>
            </form>
        </div>
    </main>
</body>
</html>
<script>
    //sert à automatiquement avoir un espace tous les 3 chiffres
    document.querySelector("input").addEventListener("input", function() {
        let valeur = this.value;
        valeur = valeur.replace(/\D/g, "");
        valeur = valeur.replace(/(.{3})/g, "$1 ").trim();
        this.value = valeur;
    });
</script>
    <?php
    $otp = TOTP::createFromSecret($_SESSION['A2F']['secret_otp']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // print_r($otp->now());
        if ($otp->verify(str_replace(' ', '', $_POST['code']), null, 20)){
            //établit la connexion
            $_SESSION['idClient'] = $_SESSION['A2F']['idClient'];
            $_SESSION['idCompte'] = $_SESSION['A2F']['idCompte'];
            $_SESSION['OTP']['secret'] = $_SESSION['A2F']['secret_otp'];
            unset($_SESSION['A2F']);
            $_SESSION['OTP']['statut'] = 'active';
            $url = '../../index.php';
            echo '<!doctype html><html lang="fr"><head><meta http-equiv="refresh" content="0;url='.$url.'">';
        }else{?>
            <script>
                document.querySelector(".error2").style.display = 'block';
            </script><?php
        }
    }
    
    ?>
<?php
}else{//sinon redirection
    $url = '../../index.php';
    echo '<!doctype html><html lang="fr"><head><meta http-equiv="refresh" content="0;url='.$url.'">';
}

//FIN EXTRAIT SOURCE OTP
?>