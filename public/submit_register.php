<?php
// submit_register.php
// Receives POST from registration form and appends a CSV line to ../src/data/data.csv

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Expected fields
$fields = [
    'nom','prenom','pseudo','email','telephone','naissance','rue','codeP','commune','mdp','mdpConfirm'
];

$input = [];
foreach ($fields as $f) {
    $input[$f] = isset($_POST[$f]) ? trim((string)$_POST[$f]) : '';
}

// Basic validation
$required = ['nom','prenom','pseudo','email','telephone','naissance','rue','codeP','commune','mdp','mdpConfirm'];
$missing = [];
foreach ($required as $r) {
    if ($input[$r] === '') $missing[] = $r;
}
if (!empty($missing)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing fields', 'fields' => $missing]);
    exit;
}

if ($input['mdp'] !== $input['mdpConfirm']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Passwords do not match']);
    exit;
}

// Prepare data directory and file
$dataDir = __DIR__ . '/../src/data';
if (!is_dir($dataDir)) {
    if (!mkdir($dataDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Could not create data directory']);
        exit;
    }
}
$file = $dataDir . '/data.csv';

// Hash password
$passwordHash = password_hash($input['mdp'], PASSWORD_DEFAULT);

$row = [
    $input['nom'],
    $input['prenom'],
    $input['pseudo'],
    $input['email'],
    $input['telephone'],
    $input['naissance'],
    $input['rue'],
    $input['codeP'],
    $input['commune'],
    $passwordHash
];

// Append to CSV with lock
$fp = fopen($file, 'a');
if ($fp === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not open data file for writing']);
    exit;
}

if (!flock($fp, LOCK_EX)) {
    fclose($fp);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not lock data file']);
    exit;
}

// If file is empty, write header first
$stat = fstat($fp);
if ($stat['size'] === 0) {
    fputcsv($fp, ['nom','prenom','pseudo','email','telephone','naissance','rue','codeP','commune','mdpHash']);
}

fputcsv($fp, $row);
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

// Respond JSON (works for AJAX). If request comes from a standard form submit
// the front-end JS can handle redirecting as needed.
header('Content-Type: application/json');
echo json_encode(['success' => true]);
exit;
