<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['file']['error'] ?? -1;
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Upload error: ' . $errCode]);
    exit;
}

$orig = $_FILES['file']['name'];
$ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
$base = preg_replace('/[\\\\\/:*?"<>|]/u', '_', pathinfo($orig, PATHINFO_FILENAME));
$base = preg_replace('/\s+/u', ' ', trim($base));

$uploadDir = __DIR__ . '/sdoc';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$fileName = $base . '.' . $ext;
$dest     = $uploadDir . '/' . $fileName;
$counter  = 1;
while (file_exists($dest)) {
    $fileName = $base . ' (' . $counter . ').' . $ext;
    $dest     = $uploadDir . '/' . $fileName;
    $counter++;
}

if (@move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'path' => 'sdoc/' . $fileName]);
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Failed to move uploaded file']);
}
