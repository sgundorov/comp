<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { echo json_encode(['ok' => false, 'error' => 'method']); exit; }

$isAjax = ((string)($_GET['ajax'] ?? '') === '1')
       || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest');
if (!$isAjax) { echo json_encode(['ok' => false, 'error' => 'ajax required']); exit; }

$name = (string)($_POST['name'] ?? '');
$width = isset($_POST['width']) && $_POST['width'] !== '' ? (int)$_POST['width'] : null;
if ($name === '') { echo json_encode(['ok' => false, 'error' => 'bad name']); exit; }

$ok = save_column_width($conn, 'client', $name, $width);
echo json_encode(['ok' => (bool)$ok]);
