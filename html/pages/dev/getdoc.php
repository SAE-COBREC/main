<?php
header('Content-Type: application/json; charset=utf-8');
$docDir = realpath(__DIR__ . '/../../../Delivraptor/rendu/doc');
$out = ['name'=>'','content'=>''];
if (!$docDir || !is_dir($docDir)){
    http_response_code(500);
    echo json_encode(['error'=>'Doc folder not found']);
    exit;
}

$file = isset($_GET['file']) ? basename($_GET['file']) : '';
if (!$file){ http_response_code(400); echo json_encode(['error'=>'Missing file']); exit; }

$path = $docDir . DIRECTORY_SEPARATOR . $file;
if (!file_exists($path) || !is_file($path)){
    http_response_code(404);
    echo json_encode(['error'=>'File not found']);
    exit;
}

$content = @file_get_contents($path);
if ($content === false){ http_response_code(500); echo json_encode(['error'=>'Unable to read file']); exit; }

echo json_encode(['name'=>$file,'content'=>$content]);

?>
