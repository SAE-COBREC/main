<?php
// Connexion à la BDD
include '../../selectBDD.php';
$pdo->exec("SET search_path TO cobrec1");

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_email') {
    $email = htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8');
    
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM cobrec1._compte WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $count = $stmt->fetchColumn();
        
        echo json_encode(['exists' => $count > 0]);
    } catch (Exception $e) {
        echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['exists' => false, 'error' => 'Invalid request']);
}
?>