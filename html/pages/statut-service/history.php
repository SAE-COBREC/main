<?php
header('Content-Type: application/json');
$historyFile = __DIR__ . '/../../data/status_history.json';
$out = [];
if (file_exists($historyFile)){
    $content = @file_get_contents($historyFile);
    $out = $content ? json_decode($content, true) : [];
    if (!is_array($out)) $out = [];
}
echo json_encode($out);
?>
