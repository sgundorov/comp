<?php
require_once __DIR__ . '/tests/Db/TestDb.php';

$action = $_GET['action'] ?? 'setup';
header('Content-Type: text/plain; charset=utf-8');

try {
    if ($action === 'setup') {
        TestDb::setup();
        echo "OK: test database created, config.local.php written.\n";
        echo "DB: comp_test\n";
        echo "Login: admin / admin\n";
    } elseif ($action === 'teardown') {
        TestDb::teardown();
        echo "OK: test database dropped, config.local.php removed.\n";
    } else {
        echo "Usage: ?action=setup | ?action=teardown\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
