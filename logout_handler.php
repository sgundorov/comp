<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['sotr_id'])) {
    $sid = (int)$_SESSION['sotr_id'];
    @$conn->query("UPDATE sotr SET user_status = 0 WHERE sotr_id = $sid");
    unset($_SESSION['sotr_id'], $_SESSION['last_activity']);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true]);
