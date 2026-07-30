<?php
$admin = new mysqli('localhost', 'root', '1439');
if ($admin->connect_error) {
    die('Admin connection failed: ' . $admin->connect_error);
}

$admin->query('DROP DATABASE IF EXISTS comp_test');
$admin->query('CREATE DATABASE comp_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$admin->select_db('comp_test');

$srcDb = 'comp';
$tables = $admin->query("SHOW TABLES FROM `$srcDb`");
while ($row = $tables->fetch_row()) {
    $table = $row[0];
    $admin->query("CREATE TABLE `comp_test`.`$table` LIKE `$srcDb`.`$table`");
}

$admin->query("INSERT INTO country (country_id, country, note) VALUES (1, 'Россия', ''), (2, 'США', '')");
$admin->query("INSERT INTO city (city_id, city, note, country_id) VALUES (1, 'Москва', '', 1), (2, 'Санкт-Петербург', '', 1), (3, 'New York', '', 2)");
$admin->query("INSERT INTO app_settings (`key`, `value`) VALUES ('page_size', '20'), ('page_width', '1100')");
$admin->query("INSERT INTO unit (unit_id, unit, note) VALUES (1, 'шт.', ''), (2, 'кг', '')");
$admin->query("INSERT INTO sotr (sotr_id, login, passw, doc_name) VALUES (1, 'admin', '" . md5('admin') . "', 'Администратор')");

$localConfig = [
    'DB_HOST' => 'localhost',
    'DB_USER' => 'root',
    'DB_PASS' => '1439',
    'DB_NAME' => 'comp_test',
];
file_put_contents(__DIR__ . '/../config.local.php', '<?php return ' . var_export($localConfig, true) . ';');
echo "Test DB ready.\n";

$_GET = [];
$_POST = [];
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

@ini_set('session.use_cookies', '0');
@ini_set('session.cache_limiter', '');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "Before include...\n";
ob_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../city.php';
$output = ob_get_clean();

echo 'Output length: ' . strlen($output) . "\n";

// Search for city names in raw binary
$patterns = ['Москва', 'Санкт-Петербург', 'New York', 'Россия', 'США', '<td'];
foreach ($patterns as $p) {
    $found = mb_strpos($output, $p) !== false;
    echo "  $p: " . ($found ? 'YES' : 'NO');
    if ($found) {
        $pos = mb_strpos($output, $p);
        echo " (pos=$pos)";
    }
    echo "\n";
}

// Also check hex encoding of Москва
echo "\nHex check:\n";
echo 'Output contains М (U+041C): ' . (strpos($output, "\xD0\x9C") !== false ? 'YES' : 'NO') . "\n";
echo 'Output contains о (U+043E): ' . (strpos($output, "\xD0\xBE") !== false ? 'YES' : 'NO') . "\n";

// Look for table rows
preg_match_all('/<tr[^>]*>.*?<\/tr>/s', $output, $matches);
echo "\nTable rows found: " . count($matches[0]) . "\n";
foreach ($matches[0] as $i => $row) {
    if ($i > 10) { echo "... truncating\n"; break; }
    echo "  Row $i: " . mb_substr(strip_tags($row), 0, 80) . "\n";
}

$admin->query('DROP DATABASE IF EXISTS comp_test');
$admin->close();
echo "\nDone.\n";
