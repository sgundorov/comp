<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    set_error_handler(function ($s, $m, $f, $l) { throw new ErrorException($m, 0, $s, $f, $l); });

$isAjax = (
    (string)($_POST['ajax'] ?? '') === '1' ||
    strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
);
if (!$isAjax) { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'AJAX only']); exit; }

$id    = (int)($_POST['id'] ?? 0);
$field = (string)($_POST['field'] ?? '');

if ($id <= 0) { echo json_encode(['ok' => false, 'error' => 'Некорректный id']); exit; }

$chk = $conn->prepare("SELECT client_id FROM client WHERE client_id = ?");
$chk->bind_param('i', $id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) { $chk->close(); echo json_encode(['ok' => false, 'error' => 'Запись не найдена']); exit; }
$chk->close();

$ALLOWED = [
    'last_name'     => ['col' => 'last_name',     'type' => 's', 'max' => 100],
    'first_name'    => ['col' => 'first_name',    'type' => 's', 'max' => 200],
    'title'         => ['col' => 'title',         'type' => 's', 'max' => 100],
    'cli_categ_id'  => ['col' => 'cli_categ_id',  'type' => 'i', 'max' => 0],
    'supplier_flag' => ['col' => 'supplier_flag', 'type' => 's', 'max' => 1],
    'problem_flag'  => ['col' => 'problem_flag',  'type' => 's', 'max' => 1],
    'juridical_flag'=> ['col' => 'juridical_flag','type' => 's', 'max' => 1],
    'hide_flag'     => ['col' => 'hide_flag',     'type' => 's', 'max' => 1],
    'phone'         => ['col' => 'phone',         'type' => 's', 'max' => 50],
    'cphone'        => ['col' => 'cphone',        'type' => 's', 'max' => 50],
    'email'         => ['col' => 'email',         'type' => 's', 'max' => 100],
    'site'          => ['col' => 'site',          'type' => 's', 'max' => 200],
    'city_id'       => ['col' => 'city_id',       'type' => 'i', 'max' => 0],
    'country_id'    => ['col' => 'country_id',    'type' => 'i', 'max' => 0],
    'postindex'     => ['col' => 'postindex',     'type' => 's', 'max' => 20],
    'address_jur'   => ['col' => 'address_jur',   'type' => 's', 'max' => 500],
    'address'       => ['col' => 'address',       'type' => 's', 'max' => 500],
    'pasport'       => ['col' => 'pasport',       'type' => 's', 'max' => 50],
    'pasp_date'     => ['col' => 'pasp_date',     'type' => 's', 'max' => 20],
    'pasp_vydan'    => ['col' => 'pasp_vydan',    'type' => 's', 'max' => 500],
    'birthday'      => ['col' => 'birthday',      'type' => 's', 'max' => 20],
    'promo_id'      => ['col' => 'promo_id',      'type' => 'i', 'max' => 0],
    'inn'           => ['col' => 'inn',           'type' => 's', 'max' => 20],
    'kpp'           => ['col' => 'kpp',           'type' => 's', 'max' => 20],
    'ogrn'          => ['col' => 'ogrn',          'type' => 's', 'max' => 20],
    'jur_name'      => ['col' => 'jur_name',      'type' => 's', 'max' => 300],
    'director'      => ['col' => 'director',      'type' => 's', 'max' => 100],
    'glavbuh'       => ['col' => 'glavbuh',       'type' => 's', 'max' => 100],
    'bank'          => ['col' => 'bank',          'type' => 's', 'max' => 200],
    'bik'           => ['col' => 'bik',           'type' => 's', 'max' => 20],
    'schet'         => ['col' => 'schet',         'type' => 's', 'max' => 50],
    'kschet'        => ['col' => 'kschet',        'type' => 's', 'max' => 50],
    'okonh'         => ['col' => 'okonh',         'type' => 's', 'max' => 50],
    'okpo'          => ['col' => 'okpo',          'type' => 's', 'max' => 50],
    'disc_goods'    => ['col' => 'disc_goods',    'type' => 's', 'max' => 10],
    'dop1'          => ['col' => 'dop1',          'type' => 's', 'max' => 500],
    'note'          => ['col' => 'note',          'type' => 's', 'max' => 5000],
    'tag_ids'       => ['col' => 'tag_ids',       'type' => 'tags', 'max' => 0],
];

if (!isset($ALLOWED[$field])) {
    echo json_encode(['ok' => false, 'error' => 'Поле недоступно для редактирования']);
    exit;
}

$cfg = $ALLOWED[$field];
$rawValue = $_POST['value'] ?? '';
$isCheckbox = in_array($field, ['supplier_flag', 'problem_flag', 'juridical_flag', 'hide_flag']);

if ($isCheckbox) {
    $value = $rawValue === '1' ? '1' : '0';
    $displayValue = $value === '1'
        ? '<svg class="check-icon" viewBox="0 0 24 24" width="16" height="16"><path fill="#27ae60" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>'
        : '';
    $stmt = $conn->prepare("UPDATE client SET {$cfg['col']} = ? WHERE client_id = ?");
    bind_auto($stmt, [$value, $id]);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['ok' => true, 'field' => $field, 'value' => (string)$value, 'displayValue' => $displayValue]);
} elseif ($field === 'tag_ids') {
    $tagIds = array_values(array_filter(array_map('intval', explode(',', $rawValue)), fn($v) => $v > 0));
    $stmt = $conn->prepare("DELETE FROM client_tag WHERE client_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    foreach ($tagIds as $tid) {
        $stmt = $conn->prepare("INSERT IGNORE INTO client_tag (client_id, tag_id) VALUES (?, ?)");
        bind_auto($stmt, [$id, $tid]);
        $stmt->execute();
        $stmt->close();
    }
    $names = [];
    if (count($tagIds) > 0) {
        $ph = implode(',', array_fill(0, count($tagIds), '?'));
        $nr = $conn->prepare("SELECT tag_id, tag AS name FROM tag WHERE tag_id IN ($ph)");
        if ($nr) {
            stmt_bind($nr, str_repeat('i', count($tagIds)), $tagIds);
            $nr->execute();
            $res = $nr->get_result();
            if ($res) while ($r = $res->fetch_assoc()) $names[] = (string)$r['name'];
            $nr->close();
        }
    }
    $displayValue = implode(', ', $names);
    echo json_encode(['ok' => true, 'field' => $field, 'value' => implode(',', $tagIds), 'displayValue' => $displayValue]);
} elseif (in_array($field, ['cli_categ_id', 'city_id', 'country_id', 'promo_id'])) {
    $value = (int)$rawValue;
    $valueForZero = $value > 0 ? $value : null;
    if ($valueForZero === null) {
        $stmt = $conn->prepare("UPDATE client SET {$cfg['col']} = NULL WHERE client_id = ?");
        $stmt->bind_param('i', $id);
    } else {
        $stmt = $conn->prepare("UPDATE client SET {$cfg['col']} = ? WHERE client_id = ?");
        bind_auto($stmt, [$valueForZero, $id]);
    }
    $stmt->execute();
    $stmt->close();

    $displayValue = (string)$value;
    if ($field === 'cli_categ_id' && $value > 0) {
        $nr = $conn->prepare("SELECT categ FROM cli_categ WHERE cli_categ_id = ?");
        $nr->bind_param('i', $value);
        $nr->execute();
        $nrd = $nr->get_result()->fetch_assoc();
        $displayValue = $nrd ? (string)$nrd['categ'] : '';
        $nr->close();
    } elseif ($field === 'city_id' && $value > 0) {
        $nr = $conn->prepare("SELECT city FROM city WHERE city_id = ?");
        $nr->bind_param('i', $value);
        $nr->execute();
        $nrd = $nr->get_result()->fetch_assoc();
        $displayValue = $nrd ? (string)$nrd['city'] : '';
        $nr->close();
    } elseif ($field === 'country_id' && $value > 0) {
        $nr = $conn->prepare("SELECT country FROM country WHERE country_id = ?");
        $nr->bind_param('i', $value);
        $nr->execute();
        $nrd = $nr->get_result()->fetch_assoc();
        $displayValue = $nrd ? (string)$nrd['country'] : '';
        $nr->close();
    } elseif ($field === 'promo_id' && $value > 0) {
        $nr = $conn->prepare("SELECT promo FROM promo WHERE promo_id = ?");
        $nr->bind_param('i', $value);
        $nr->execute();
        $nrd = $nr->get_result()->fetch_assoc();
        $displayValue = $nrd ? (string)$nrd['promo'] : '';
        $nr->close();
    }
    echo json_encode(['ok' => true, 'field' => $field, 'value' => (string)$value, 'displayValue' => $displayValue]);
} elseif ($field === 'disc_goods') {
    $raw = trim((string)$rawValue);
    $value = ($raw === '') ? '0' : $raw;
    $stmt = $conn->prepare("UPDATE client SET {$cfg['col']} = ? WHERE client_id = ?");
    bind_auto($stmt, [$value, $id]);
    $stmt->execute();
    $stmt->close();
    $displayValue = $value !== '0' ? $value . '%' : '';
    echo json_encode(['ok' => true, 'field' => $field, 'value' => $value, 'displayValue' => $displayValue]);
} elseif (in_array($field, ['last_name', 'first_name'])) {
    $value = trim((string)$rawValue);
    if ($cfg['max'] > 0 && mb_strlen($value) > $cfg['max']) $value = mb_substr($value, 0, $cfg['max']);
    $stmt = $conn->prepare("UPDATE client SET {$cfg['col']} = ? WHERE client_id = ?");
    bind_auto($stmt, [$value, $id]);
    $stmt->execute();
    $stmt->close();
    $stmt2 = $conn->prepare("SELECT last_name, first_name, jur_name FROM client WHERE client_id = ?");
    $stmt2->bind_param('i', $id);
    $stmt2->execute();
    $r2 = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();
    $ln = $r2 ? $r2['last_name'] : '';
    $fn = $r2 ? $r2['first_name'] : '';
    $jn = $r2 ? $r2['jur_name'] : '';
    $newName = $jn ?: trim($ln . ' ' . $fn);
    $stmt3 = $conn->prepare("UPDATE client SET name = ? WHERE client_id = ?");
    bind_auto($stmt3, [$newName, $id]);
    $stmt3->execute();
    $stmt3->close();
    echo json_encode(['ok' => true, 'field' => $field, 'value' => (string)$value, 'name' => $newName]);
} elseif ($field === 'jur_name') {
    $value = trim((string)$rawValue);
    if ($cfg['max'] > 0 && mb_strlen($value) > $cfg['max']) $value = mb_substr($value, 0, $cfg['max']);
    $stmt = $conn->prepare("UPDATE client SET jur_name = ? WHERE client_id = ?");
    bind_auto($stmt, [$value, $id]);
    $stmt->execute();
    $stmt->close();
    $jurFlag = $value !== '' ? '1' : '0';
    $stmt2 = $conn->prepare("UPDATE client SET juridical_flag = ? WHERE client_id = ?");
    bind_auto($stmt2, [$jurFlag, $id]);
    $stmt2->execute();
    $stmt2->close();
    $stmt3 = $conn->prepare("SELECT last_name, first_name FROM client WHERE client_id = ?");
    $stmt3->bind_param('i', $id);
    $stmt3->execute();
    $r3 = $stmt3->get_result()->fetch_assoc();
    $stmt3->close();
    $ln = $r3 ? $r3['last_name'] : '';
    $fn = $r3 ? $r3['first_name'] : '';
    $newName = $value ?: trim($ln . ' ' . $fn);
    $stmt4 = $conn->prepare("UPDATE client SET name = ? WHERE client_id = ?");
    bind_auto($stmt4, [$newName, $id]);
    $stmt4->execute();
    $stmt4->close();
    echo json_encode(['ok' => true, 'field' => $field, 'value' => (string)$value, 'name' => $newName]);
} elseif (in_array($field, ['pasp_date', 'birthday'])) {
    $value = trim((string)$rawValue);
    if ($value !== '') {
        $dt = strtotime($value);
        if ($dt === false) {
            echo json_encode(['ok' => false, 'error' => 'Некорректный формат даты']);
            exit;
        }
        $value = date('Y-m-d', $dt);
    }
    $stmt = $conn->prepare("UPDATE client SET {$cfg['col']} = ? WHERE client_id = ?");
    if ($value === '') {
        bind_auto($stmt, [$value, $id]);
    } else {
        bind_auto($stmt, [$value, $id]);
    }
    $stmt->execute();
    $stmt->close();
    $displayValue = $value !== '' ? date('d.m.Y', strtotime($value)) : '';
    echo json_encode(['ok' => true, 'field' => $field, 'value' => (string)$value, 'displayValue' => $displayValue]);
} else {
    $value = trim((string)$rawValue);
    if ($cfg['max'] > 0 && mb_strlen($value) > $cfg['max']) $value = mb_substr($value, 0, $cfg['max']);
    $stmt = $conn->prepare("UPDATE client SET {$cfg['col']} = ? WHERE client_id = ?");
    bind_auto($stmt, [$value, $id]);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['ok' => true, 'field' => $field, 'value' => (string)$value]);
}
} catch (Throwable $e) {
    try { @error_log('client_field_save error: ' . $e->getMessage()); } catch (Throwable $_) {}
    http_response_code(500);
    $errBody = ['ok' => false, 'error' => $e->getMessage()];
    echo json_encode($errBody);
}
