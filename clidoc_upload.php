<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_FILES['file'])) {
    echo json_encode(['ok' => false, 'error' => 'Файл не передан']);
    exit;
}

$clientId = (int)($_POST['client_id'] ?? 0);
$number   = (int)($_POST['number'] ?? 0);

if ($clientId <= 0 || $number <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Не указан client_id или number']);
    exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'error' => 'Ошибка загрузки: код ' . $file['error']]);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext === '') $ext = 'bin';

$uploadDir = __DIR__ . '/uploads/client';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$destName = 'Cli_' . $clientId . '_' . $number . '.' . $ext;
$destPath = $uploadDir . '/' . $destName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['ok' => false, 'error' => 'Ошибка сохранения файла']);
    exit;
}

exec('icacls ' . escapeshellarg($destPath) . ' /grant "NT AUTHORITY\\IUSR:(RX)" "BUILTIN\\IIS_IUSRS:(RX)" 2>&1');

echo json_encode(['ok' => true, 'filename' => 'uploads/client/' . $destName]);
