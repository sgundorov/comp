<?php
require_once __DIR__ . '/../config.php';
// Simulate POST request like the form would send
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$_POST = [
    'mode' => 'edit',
    'id' => '1',
    'number' => '1',
    'date' => '2025-01-01',
    'time' => '10:00',
    'client_id' => '1',
    'state' => 'Черновик',
    'store_id' => '1',
    'payment_type' => 'Наличные',
    'discount' => '0',
    'sum_discount' => '0',
    'sum' => '0',
    'sum_nds' => '0',
    'sum_plat' => '0',
    'date_plat' => '',
    'sotr_id' => '1',
    'pos' => '0',
    'note' => '',
];

$isAjax = (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
echo "isAjax: " . ($isAjax ? 'true' : 'false') . PHP_EOL;

$errors = [];
$values = [];
$values['date'] = trim($_POST['date'] ?? '');
$values['client_id'] = (int)($_POST['client_id'] ?? 0);
$values['store_id'] = (int)($_POST['store_id'] ?? 0);
$values['sotr_id'] = (int)($_POST['sotr_id'] ?? 0);

if ($values['date'] === '') $errors[] = 'Дата обязательна';
if ($values['client_id'] <= 0) $errors[] = 'Контрагент обязателен';
if ($values['store_id'] <= 0) $errors[] = 'Участок обязателен';
if ($values['sotr_id'] <= 0) $errors[] = 'Сотрудник обязателен';

echo "Errors: " . count($errors) . PHP_EOL;
foreach ($errors as $e) echo "  - $e" . PHP_EOL;
echo "Result would be: " . json_encode(['ok' => empty($errors)]) . PHP_EOL;
