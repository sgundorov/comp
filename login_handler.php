<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['ok' => false, 'error' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['error'] = 'Invalid method';
    echo json_encode($response);
    exit;
}

$login = trim((string)($_POST['login'] ?? ''));
$password = trim((string)($_POST['password'] ?? ''));

if ($login === '' || $password === '') {
    $response['error'] = 'Введите логин и пароль';
    echo json_encode($response);
    exit;
}

$stmt = $conn->prepare("SELECT sotr_id, passw FROM sotr WHERE login = ?");
$stmt->bind_param('s', $login);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$r || $r['passw'] !== $password) {
    $response['error'] = 'Неверный логин или пароль';
    echo json_encode($response);
    exit;
}

$sotrId = (int)$r['sotr_id'];
$_SESSION['sotr_id'] = $sotrId;
$_SESSION['last_activity'] = time();

$conn->query("UPDATE sotr SET user_status = 1 WHERE sotr_id = $sotrId");

$response['ok'] = true;
$response['sotr_id'] = $sotrId;
echo json_encode($response);
