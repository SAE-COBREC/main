<?php
header('Content-Type: application/json');

$result = [];

function state($ok, $msg=''){
    if($ok === true) return ['status'=>'OK','message'=>$msg];
    if($ok === 'degraded') return ['status'=>'Dégradé','message'=>$msg];
    return ['status'=>'Indisponible','message'=>$msg];
}

// Web check: try common HTTP ports on localhost, fallback to PHP lint
$indexFile = realpath(__DIR__ . '/../../index.php');
$webOk = false; $webMsg = '';
$host = '127.0.0.1';
$ports = [80, 5001, 8000, 8080, 3000, 8001];
foreach($ports as $port){
    $fp = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 1);
    if ($fp) {
        $req = "GET / HTTP/1.1\r\nHost: {$host}\r\nConnection: close\r\n\r\n";
        fwrite($fp, $req);
        $line = fgets($fp);
        fclose($fp);
        if ($line !== false && preg_match('#HTTP/\d\.\d\s+(\d{3})#', $line, $m)){
            $code = (int)$m[1];
            $webMsg = "HTTP {$code} on port {$port}";
            if ($code >= 200 && $code < 400) { $webOk = true; break; }
        } else {
            $webMsg = "Port {$port} responded but invalid HTTP";
        }
    }
}

if (!$webOk) {
    // fallback: check php lint to detect basic site issues
    if($indexFile && file_exists($indexFile)){
        $cmd = 'php -l ' . escapeshellarg($indexFile) . ' 2>&1';
        exec($cmd, $out, $rc);
        if($rc === 0){
            // lint ok but HTTP failed -> degraded
            $result['web'] = state('degraded', 'HTTP inaccessible mais PHP ok (' . $webMsg . ')');
        } else {
            $result['web'] = state(false, implode("\n", $out));
        }
    } else {
        $result['web'] = state(false, 'index.php introuvable (' . $webMsg . ')');
    }
} else {
    $result['web'] = state(true, 'Site reachable (' . $webMsg . ')');
}

$dbPort = 5432;
$dbReachable = false;
$fp = @fsockopen($dbHost, $dbPort, $errno, $errstr, 2);
if ($fp) { fclose($fp); $dbReachable = true; }

if ($dbReachable) {
    if (function_exists('pg_connect')){
        $connStr = "host=$dbHost port=$dbPort dbname=saedb user=sae password=kira13 connect_timeout=3";
        $conn = @pg_connect($connStr);
        if ($conn){ pg_close($conn); $result['database'] = state(true,'Connexion OK (authentifiée)'); }
        else { $result['database'] = state('degraded','Port accessible mais authentification échouée'); }
    } else {
        $result['database'] = state(true,'Port TCP accessible (pg ext absente)');
    }
} else {
    $result['database'] = state(false,'Port TCP inaccessible');
}

$transporteur = realpath(__DIR__ . '/../../../Delivraptor/transporteur');
if ($transporteur && file_exists($transporteur)){
    // check if process running via pgrep
    $pg = [];
    exec('pgrep -f transporteur 2>/dev/null', $pg, $rcpg);
    if ($rcpg === 0 && !empty($pg)){
        $msg = 'Présent et en cours d\'exécution (pids: ' . implode(',', $pg) . ')';
        $result['delivraptor'] = state(true, $msg);
    } else {
        $exec_ok = is_executable($transporteur);
        $msg = 'Présent' . ($exec_ok ? ' (non lancé)' : ' (non exécutable)');
        $result['delivraptor'] = state('degraded', $msg);
    }
} else {
    $result['delivraptor'] = state(false,'Binaire introuvable');
}


$historyDir = __DIR__ . '/../../data';
if (!is_dir($historyDir)) {
    @mkdir($historyDir, 0755, true);
}
$historyFile = $historyDir . '/status_history.json';

$entry = ['ts'=>time(), 'iso'=>date(DATE_ATOM), 'result'=>$result];

$history = [];
if (file_exists($historyFile)){
    $content = @file_get_contents($historyFile);
    $history = $content ? json_decode($content, true) : [];
    if (!is_array($history)) $history = [];
}

$history[] = $entry;
if (count($history) > 500) $history = array_slice($history, -500);

@file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode($result);

?>
