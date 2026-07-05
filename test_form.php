<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/table-helper.php';

$mode = 'edit';
$id   = (int)($_GET['id'] ?? 1);

echo "<h2>Тест формы Контрагент (id=$id)</h2>";

echo "<h3>1. Проверка \$conn:</h3>";
if ($conn) {
    echo "OK: \$conn создан<br>";
    $test = $conn->query("SELECT 1 AS val");
    if ($test) {
        echo "OK: запрос выполняется<br>";
    } else {
        echo "ОШИБКА: " . $conn->error . "<br>";
    }
} else {
    echo "ОШИБКА: \$conn = null<br>";
}

echo "<h3>2. ensure_client_tag_table:</h3>";
try {
    ensure_client_tag_table($conn);
    echo "OK<br>";
} catch (Throwable $e) {
    echo "ОШИБКА: " . $e->getMessage() . "<br>";
}

echo "<h3>3. Запрос записи:</h3>";
try {
    $stmt = $conn->prepare("SELECT c.last_name, c.first_name, c.title,
        c.cli_categ_id, c.supplier_flag, c.problem_flag, c.juridical_flag, c.hide_flag,
        c.phone, c.cphone, c.email, c.site,
        c.city_id, c.country_id, c.postindex, c.address_jur, c.address,
        c.pasport, c.pasp_date, c.pasp_vydan, c.birthday,
        c.promo_id, c.inn, c.kpp, c.ogrn, c.jur_name, c.director, c.glavbuh,
        c.bank, c.bik, c.schet, c.kschet, c.okonh, c.okpo,
        c.disc_goods, c.dop1, c.note,
        (SELECT GROUP_CONCAT(ctg.tag_id SEPARATOR ',') FROM client_tag ctg WHERE ctg.client_id = c.client_id) AS tag_ids
        FROM client c WHERE c.client_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($r) {
        echo "OK: запись найдена: " . ($r['last_name'] ?? '') . " " . ($r['first_name'] ?? '') . "<br>";
    } else {
        echo "Запись не найдена<br>";
    }
} catch (Throwable $e) {
    echo "ОШИБКА: " . $e->getMessage() . "<br>";
}

echo "<h3>4. client_tag запрос:</h3>";
try {
    $cts = $conn->prepare("SELECT tag_id FROM client_tag WHERE client_id = ?");
    $cts->bind_param('i', $id);
    $cts->execute();
    $ctr = $cts->get_result();
    $tags = [];
    while ($ct = $ctr->fetch_assoc()) $tags[] = (int)$ct['tag_id'];
    $cts->close();
    echo "OK: тегов найдено: " . count($tags) . "<br>";
} catch (Throwable $e) {
    echo "ОШИБКА: " . $e->getMessage() . "<br>";
}

echo "<h3>5. JSON-ответ (как в AJAX):</h3>";
try {
    $testData = ['ok' => true, 'html' => '<div>тест</div>', 'mode' => 'edit', 'focusField' => ''];
    $json = json_encode($testData);
    if ($json === false) {
        echo "ОШИБКА json_encode: " . json_last_error_msg() . "<br>";
    } else {
        echo "OK: JSON корректен<br>";
    }
} catch (Throwable $e) {
    echo "ОШИБКА: " . $e->getMessage() . "<br>";
}
