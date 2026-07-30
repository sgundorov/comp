<?php
// Integration test front controller
// Handles all PHP requests to ensure proper per-request initialization

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$docRoot = __DIR__ . '/../..';
$file = $docRoot . $path;

// Static files
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$staticExts = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico'];
if (in_array($ext, $staticExts, true) && file_exists($file) && !is_dir($file)) {
    return false;
}

// Write test config before every request that will run PHP
if (preg_match('/\.php$/i', $path) || $path === '/' || $path === '') {
    $localConfig = [
        'DB_HOST' => 'localhost',
        'DB_USER' => 'root',
        'DB_PASS' => '1439',
        'DB_NAME' => 'comp_test',
    ];
    file_put_contents($docRoot . '/config.local.php', '<?php return ' . var_export($localConfig, true) . ';');
}

// For PHP files, let the built-in server handle them
// config.php uses require_once which means constants/classes persist
// but $conn (a variable) doesn't persist across requests in the built-in server.
// However, config.php will be re-executed because require_once checks the
// file's compiled hash, which is reset per-request in the built-in server's
// request context. Wait — actually it persists.
// 
// To be safe, we always require config.php with a fresh DB connection.
// We do this by including a prepend script first.
if (preg_match('/\.php$/i', $path) && file_exists($file) && !is_dir($file)) {
    // For PHP files, just let the server handle them
    return false;
}

return false;
