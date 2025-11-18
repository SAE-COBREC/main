<?php
//permet de stocker le total du panier avec la méthode AJAX
session_start();
if (isset($_POST['total'])) {
    $_SESSION['totalPanier'] = number_format($_POST['total'], 2, '.');
}
?>