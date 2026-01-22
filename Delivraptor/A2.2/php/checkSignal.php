<?php
// Endpoint pour vérifier si un signal a été reçu
header('Content-Type: application/json');

$SIGNAL_FILE = '/tmp/php_signal.flag';
$lastCheck = isset($_GET['lastCheck']) ? (int)$_GET['lastCheck'] : 0;
$bordereau = isset($_GET['bordereau']) ? (int)$_GET['bordereau'] : 0;

$response = ['signal' => false, 'timestamp' => 0, 'status' => null];

// Vérifier le fichier signal
if (file_exists($SIGNAL_FILE)) {
    $timestamp = (int)file_get_contents($SIGNAL_FILE);
    
    if ($timestamp > $lastCheck) {
        $response['signal'] = true;
        $response['timestamp'] = $timestamp;
        
        // Récupérer le nouveau status depuis le transporteur
        if ($bordereau > 0) {
            $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if ($socket !== false && @socket_connect($socket, '127.0.0.1', 9000)) {
                socket_write($socket, "STATUS $bordereau\n");
                $rep = socket_read($socket, 256);
                socket_close($socket);
                
                if (preg_match('/STEP=(\d+)/', $rep, $m)) {
                    $response['status'] = (int)$m[1];
                }
            }
        }
    }
}

echo json_encode($response);
