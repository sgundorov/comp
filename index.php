<?php
require_once __DIR__ . '/config.php';

$pageMap = [
    'clients'  => 'client.php',
    'goods'    => 'tmc.php',
    'invoices' => 'invoice.php',
    'sales'    => 'plat.php',
];

$startup = $appSettings['startup_page'] ?? 'clients';
$target  = $pageMap[$startup] ?? 'client.php';

header('Location: ' . $target);
exit;
