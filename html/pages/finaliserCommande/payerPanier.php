<?php
session_start();

if (isset($_POST['numCarte'], $_POST['dateExpiration'], $_POST['cvc'], $_POST['pays'], $_POST['nom'])) {

    $numCarte = str_replace(' ', '', $_POST['numCarte']);
    $totalNumCarte = 0;
    $alterne = false;

    for ($i = strlen($numCarte) - 1; $i >= 0; $i--) {
        $chiffreActu = intval($numCarte[$i]);

        if ($alterne) {
            $chiffreActu *= 2;
            if ($chiffreActu > 9) {
                $chiffreActu -= 9;
            }
        }

        $totalNumCarte += $chiffreActu;
        $alterne = !$alterne;
    }
    if ($totalNumCarte % 10 == 0){
        echo "carte valide";
    } else {
        echo "carte invalide";
    }


} else {
    echo "ERREUR DURANT LE PAIEMENT VEUILLEZ RÉESSAYER.";
}
?>
