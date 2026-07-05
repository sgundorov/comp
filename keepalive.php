<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$ok = false;
if (isset($_SESSION['sotr_id']) && (int)$_SESSION['sotr_id'] > 0) {
    $timeout = 60 * 60;
    if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > $timeout) {
        unset($_SESSION['sotr_id'], $_SESSION['last_activity']);
    } else {
        $ok = true;
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => $ok]);
