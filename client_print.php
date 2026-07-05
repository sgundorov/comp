<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/client_columns.php';

$search  = trim((string)($_GET['q'] ?? ''));
$searchActive = ((string)($_GET['sf'] ?? '0') === '1');
$searchCols = [];
$clientSearchCols = ['id', 'name', 'last_name', 'first_name', 'title', 'cli_categ_id', 'phone', 'cphone', 'email', 'site', 'city_id', 'country_id', 'postindex', 'address_jur', 'address', 'pasport', 'pasp_vydan', 'promo_id', 'inn', 'kpp', 'ogrn', 'jur_name', 'director', 'glavbuh', 'bank', 'bik', 'schet', 'kschet', 'okonh', 'okpo', 'dop1', 'tags', 'note'];
$rawCols = (string)($_GET['cols'] ?? '');
if ($rawCols !== '') {
    $searchCols = array_values(array_filter(array_map('trim', explode(',', $rawCols)), function ($k) {
        global $clientSearchCols;
        return in_array($k, $clientSearchCols, true);
    }));
}
$searchCond = (string)($_GET['cond'] ?? 'contains');
$allowedCond = ['contains', 'not_contains', 'starts_with', 'ends_with', 'equals', 'not_equals', 'gt', 'lt'];
if (!in_array($searchCond, $allowedCond, true)) $searchCond = 'contains';
if (!$searchActive) { $search = ''; $searchCols = []; }
if ($searchActive && $search !== '' && count($searchCols) === 0) {
    $searchCols = $clientSearchCols;
}

$sortRaw = trim((string)($_GET['sort'] ?? ''));
$sortLevels = [];
$clientSortCols = ['id', 'name', 'last_name', 'first_name', 'title', 'cli_categ_id', 'supplier_flag', 'problem_flag', 'juridical_flag', 'hide_flag', 'phone', 'cphone', 'email', 'site', 'city_id', 'country_id', 'postindex', 'address_jur', 'address', 'pasport', 'pasp_date', 'pasp_vydan', 'birthday', 'promo_id', 'inn', 'kpp', 'ogrn', 'jur_name', 'director', 'glavbuh', 'bank', 'bik', 'schet', 'kschet', 'okonh', 'okpo', 'disc_goods', 'sum_nach', 'sum_plat', 'sum_balans', 'bdate', 'dop1', 'tags', 'note'];
if ($sortRaw !== '') {
    foreach (explode(',', $sortRaw) as $lv) {
        $pp = explode(':', $lv);
        $ck = $pp[0] ?? '';
        $dk = strtolower($pp[1] ?? 'asc');
        if (in_array($ck, $clientSortCols, true) && in_array($dk, ['asc', 'desc'], true)) {
            $sortLevels[] = ['col' => $ck, 'dir' => $dk];
        }
    }
}
if (count($sortLevels) === 0) {
    $sortLevels = [['col' => 'id', 'dir' => 'desc']];
}
$colToOrderSql = [
    'id'             => 'c.client_id',
    'name'           => 'c.last_name',
    'last_name'      => 'c.last_name',
    'first_name'     => 'c.first_name',
    'title'          => 'c.title',
    'cli_categ_id'   => 'cat.categ',
    'supplier_flag'  => 'c.supplier_flag',
    'problem_flag'   => 'c.problem_flag',
    'juridical_flag' => 'c.juridical_flag',
    'hide_flag'      => 'c.hide_flag',
    'phone'          => 'c.phone',
    'cphone'         => 'c.cphone',
    'email'          => 'c.email',
    'site'           => 'c.site',
    'city_id'        => 'ci.city',
    'country_id'     => 'co.country',
    'postindex'      => 'c.postindex',
    'address_jur'    => 'c.address_jur',
    'address'        => 'c.address',
    'pasport'        => 'c.pasport',
    'pasp_date'      => 'c.pasp_date',
    'pasp_vydan'     => 'c.pasp_vydan',
    'birthday'       => 'c.birthday',
    'promo_id'       => 'pr.promo',
    'inn'            => 'c.inn',
    'kpp'            => 'c.kpp',
    'ogrn'           => 'c.ogrn',
    'jur_name'       => 'c.jur_name',
    'director'       => 'c.director',
    'glavbuh'        => 'c.glavbuh',
    'bank'           => 'c.bank',
    'bik'            => 'c.bik',
    'schet'          => 'c.schet',
    'kschet'         => 'c.kschet',
    'okonh'          => 'c.okonh',
    'okpo'           => 'c.okpo',
    'disc_goods'     => 'c.disc_goods',
    'sum_nach'       => 'c.sum_nach',
    'sum_plat'       => 'c.sum_plat',
    'sum_balans'     => 'c.sum_balans',
    'bdate'          => 'c.bdate',
    'dop1'           => 'c.dop1',
    'tags'           => 'tags_concat',
    'note'           => 'c.note',
];
$orderParts = [];
foreach ($sortLevels as $sl) {
    $orderParts[] = ($colToOrderSql[$sl['col']] ?? 'c.client_id') . ' ' . strtoupper($sl['dir']);
}
$orderBy = implode(', ', $orderParts);

$COLUMN_DEFAULTS = client_columns_defaults();
$COL_META = [];
foreach ($COLUMN_DEFAULTS as $c) {
    $COL_META[$c['name']] = [
        'label' => $c['label'],
        'value' => null,
    ];
}
$COL_META['id']['value']             = function ($r) { return (int)$r['client_id']; };
$COL_META['name']['value']           = function ($r) { return (string)($r['name'] ?? ''); };
$COL_META['last_name']['value']      = function ($r) { return (string)($r['last_name'] ?? ''); };
$COL_META['first_name']['value']     = function ($r) { return (string)($r['first_name'] ?? ''); };
$COL_META['title']['value']          = function ($r) { return (string)($r['title'] ?? ''); };
$COL_META['cli_categ_id']['value']   = function ($r) { return (string)($r['cli_categ_name'] ?? ''); };
$COL_META['supplier_flag']['value']  = function ($r) { return (string)($r['supplier_flag'] ?? '0'); };
$COL_META['problem_flag']['value']   = function ($r) { return (string)($r['problem_flag'] ?? '0'); };
$COL_META['juridical_flag']['value'] = function ($r) { return (string)($r['juridical_flag'] ?? '0'); };
$COL_META['hide_flag']['value']      = function ($r) { return (string)($r['hide_flag'] ?? '0'); };
$COL_META['phone']['value']          = function ($r) { return (string)($r['phone'] ?? ''); };
$COL_META['cphone']['value']         = function ($r) { return (string)($r['cphone'] ?? ''); };
$COL_META['email']['value']          = function ($r) { return (string)($r['email'] ?? ''); };
$COL_META['site']['value']           = function ($r) { return (string)($r['site'] ?? ''); };
$COL_META['city_id']['value']        = function ($r) { return (string)($r['city_name'] ?? ''); };
$COL_META['country_id']['value']     = function ($r) { return (string)($r['country_name'] ?? ''); };
$COL_META['postindex']['value']      = function ($r) { return (string)($r['postindex'] ?? ''); };
$COL_META['address_jur']['value']    = function ($r) { return (string)($r['address_jur'] ?? ''); };
$COL_META['address']['value']        = function ($r) { return (string)($r['address'] ?? ''); };
$COL_META['pasport']['value']        = function ($r) { return (string)($r['pasport'] ?? ''); };
$COL_META['pasp_date']['value']      = function ($r) { $v = (string)($r['pasp_date'] ?? ''); return $v !== '' ? date('d.m.Y', strtotime($v)) : ''; };
$COL_META['pasp_vydan']['value']     = function ($r) { return (string)($r['pasp_vydan'] ?? ''); };
$COL_META['birthday']['value']       = function ($r) { $v = (string)($r['birthday'] ?? ''); return $v !== '' ? date('d.m.Y', strtotime($v)) : ''; };
$COL_META['promo_id']['value']       = function ($r) { return (string)($r['promo_name'] ?? ''); };
$COL_META['inn']['value']            = function ($r) { return (string)($r['inn'] ?? ''); };
$COL_META['kpp']['value']            = function ($r) { return (string)($r['kpp'] ?? ''); };
$COL_META['ogrn']['value']           = function ($r) { return (string)($r['ogrn'] ?? ''); };
$COL_META['jur_name']['value']       = function ($r) { return (string)($r['jur_name'] ?? ''); };
$COL_META['director']['value']       = function ($r) { return (string)($r['director'] ?? ''); };
$COL_META['glavbuh']['value']        = function ($r) { return (string)($r['glavbuh'] ?? ''); };
$COL_META['bank']['value']           = function ($r) { return (string)($r['bank'] ?? ''); };
$COL_META['bik']['value']            = function ($r) { return (string)($r['bik'] ?? ''); };
$COL_META['schet']['value']          = function ($r) { return (string)($r['schet'] ?? ''); };
$COL_META['kschet']['value']         = function ($r) { return (string)($r['kschet'] ?? ''); };
$COL_META['okonh']['value']          = function ($r) { return (string)($r['okonh'] ?? ''); };
$COL_META['okpo']['value']           = function ($r) { return (string)($r['okpo'] ?? ''); };
$COL_META['disc_goods']['value']     = function ($r) { return (string)($r['disc_goods'] ?? ''); };
$COL_META['sum_nach']['value']       = function ($r) { return (string)($r['sum_nach'] ?? '0'); };
$COL_META['sum_plat']['value']       = function ($r) { return (string)($r['sum_plat'] ?? '0'); };
$COL_META['sum_balans']['value']     = function ($r) { return (string)($r['sum_balans'] ?? '0'); };
$COL_META['bdate']['value']          = function ($r) { $v = (string)($r['bdate'] ?? ''); return $v !== '' ? date('d.m.Y', strtotime($v)) : ''; };
$COL_META['dop1']['value']           = function ($r) { return (string)($r['dop1'] ?? ''); };
$COL_META['tags']['value']           = function ($r) { return (string)($r['tags_concat'] ?? ''); };
$COL_META['note']['value']           = function ($r) { return (string)($r['note'] ?? ''); };
$PRINT_WIDTHS = [
    'id' => '35px', 'name' => '120px', 'last_name' => '100px', 'first_name' => '80px',
    'title' => '80px', 'cli_categ_id' => '80px', 'phone' => '90px', 'cphone' => '90px',
    'email' => '100px', 'site' => '80px', 'city_id' => '80px',
    'postindex' => '50px', 'address' => '100px', 'inn' => '80px', 'kpp' => '60px',
    'jur_name' => '100px', 'director' => '100px', 'schet' => '80px', 'kschet' => '80px',
    'bank' => '80px', 'bik' => '50px', 'dop1' => '60px', 'tags' => '80px',
];

$columnsConfig  = load_columns_config($conn, 'client', $COLUMN_DEFAULTS);
$visibleColumns = array_values(array_filter($columnsConfig, function ($c) { return !empty($c['visible']) && !in_array($c['name'], ['country_id', 'note'], true); }));

$where  = '';
$params = [];
$types  = '';
$filterLabels = [];
if ($search !== '' && count($searchCols) > 0) {
    $colToExpr = [
        'id'            => 'c.client_id',
        'name'          => "TRIM(CONCAT_WS(' ', c.last_name, c.first_name))",
        'last_name'     => 'c.last_name',
        'first_name'    => 'c.first_name',
        'title'         => 'c.title',
        'cli_categ_id'  => 'cat.categ',
        'phone'         => 'c.phone',
        'cphone'        => 'c.cphone',
        'email'         => 'c.email',
        'site'          => 'c.site',
        'city_id'       => 'ci.city',
        'country_id'    => 'co.country',
        'postindex'     => 'c.postindex',
        'address_jur'   => 'c.address_jur',
        'address'       => 'c.address',
        'pasport'       => 'c.pasport',
        'pasp_vydan'    => 'c.pasp_vydan',
        'promo_id'      => 'pr.promo',
        'inn'           => 'c.inn',
        'kpp'           => 'c.kpp',
        'ogrn'          => 'c.ogrn',
        'jur_name'      => 'c.jur_name',
        'director'      => 'c.director',
        'glavbuh'       => 'c.glavbuh',
        'bank'          => 'c.bank',
        'bik'           => 'c.bik',
        'schet'         => 'c.schet',
        'kschet'        => 'c.kschet',
        'okonh'         => 'c.okonh',
        'okpo'          => 'c.okpo',
        'dop1'          => 'c.dop1',
        'tags'          => 'tags_concat',
        'note'          => 'c.note',
    ];
    $condToOp = [
        'contains'     => 'LIKE',
        'not_contains' => 'NOT LIKE',
        'starts_with'  => 'LIKE',
        'ends_with'    => 'LIKE',
        'equals'       => '=',
        'not_equals'   => '<>',
        'gt'           => '>',
        'lt'           => '<',
    ];
    $op = $condToOp[$searchCond] ?? 'LIKE';
    $parts = [];
    foreach ($searchCols as $col) {
        if (!isset($colToExpr[$col])) continue;
        $parts[] = $colToExpr[$col] . ' ' . $op . ' ?';
        switch ($searchCond) {
            case 'contains':     $params[] = '%' . $search . '%'; break;
            case 'not_contains': $params[] = '%' . $search . '%'; break;
            case 'starts_with':  $params[] = $search . '%'; break;
            case 'ends_with':    $params[] = '%' . $search; break;
            case 'equals':       $params[] = $search; break;
            case 'not_equals':   $params[] = $search; break;
            case 'gt':           $params[] = $search; break;
            case 'lt':           $params[] = $search; break;
            default:             $params[] = '%' . $search . '%';
        }
        $types .= 's';
    }
    if (count($parts) > 0) {
        $where = 'WHERE (' . implode(' OR ', $parts) . ')';
    }
    $filterLabels[] = 'Поиск: «' . $search . '»';
}

$cliCategFilter = (string)($_GET['cli_categ_id'] ?? '');
if ($cliCategFilter !== '') {
    $ids = array_values(array_filter(array_map('intval', explode(',', $cliCategFilter)), fn($v) => $v > 0));
    if (count($ids) > 0) {
        $place = implode(',', array_fill(0, count($ids), '?'));
        $extra = "c.cli_categ_id IN ($place)";
        $where = $where === '' ? "WHERE $extra" : 'WHERE (' . substr($where, 6) . ") AND $extra";
        $params = array_merge($params, $ids);
        $types  = $types . str_repeat('i', count($ids));
        $nStmt = @$conn->prepare("SELECT cli_categ_id, categ FROM cli_categ WHERE cli_categ_id IN ($place)");
        $names = [];
        if ($nStmt) { $nStmt->bind_param(str_repeat('i', count($ids)), ...$ids); $nStmt->execute(); $nr = $nStmt->get_result(); if ($nr) while ($row = $nr->fetch_assoc()) $names[] = $row['categ']; $nStmt->close(); }
        $filterLabels[] = 'Категория = ' . implode(', ', $names);
    }
}

$cityFilter = (string)($_GET['city_id'] ?? '');
if ($cityFilter !== '') {
    $ids = array_values(array_filter(array_map('intval', explode(',', $cityFilter)), fn($v) => $v > 0));
    if (count($ids) > 0) {
        $place = implode(',', array_fill(0, count($ids), '?'));
        $extra = "c.city_id IN ($place)";
        $where = $where === '' ? "WHERE $extra" : 'WHERE (' . substr($where, 6) . ") AND $extra";
        $params = array_merge($params, $ids);
        $types  = $types . str_repeat('i', count($ids));
        $nStmt = @$conn->prepare("SELECT city_id, city FROM city WHERE city_id IN ($place)");
        $names = [];
        if ($nStmt) { $nStmt->bind_param(str_repeat('i', count($ids)), ...$ids); $nStmt->execute(); $nr = $nStmt->get_result(); if ($nr) while ($row = $nr->fetch_assoc()) $names[] = $row['city']; $nStmt->close(); }
        $filterLabels[] = 'Город = ' . implode(', ', $names);
    }
}

$countryFilter = (string)($_GET['country_id'] ?? '');
if ($countryFilter !== '') {
    $ids = array_values(array_filter(array_map('intval', explode(',', $countryFilter)), fn($v) => $v > 0));
    if (count($ids) > 0) {
        $place = implode(',', array_fill(0, count($ids), '?'));
        $extra = "c.country_id IN ($place)";
        $where = $where === '' ? "WHERE $extra" : 'WHERE (' . substr($where, 6) . ") AND $extra";
        $params = array_merge($params, $ids);
        $types  = $types . str_repeat('i', count($ids));
        $nStmt = @$conn->prepare("SELECT country_id, country FROM country WHERE country_id IN ($place)");
        $names = [];
        if ($nStmt) { $nStmt->bind_param(str_repeat('i', count($ids)), ...$ids); $nStmt->execute(); $nr = $nStmt->get_result(); if ($nr) while ($row = $nr->fetch_assoc()) $names[] = $row['country']; $nStmt->close(); }
        $filterLabels[] = 'Страна = ' . implode(', ', $names);
    }
}

$tagFilter = (string)($_GET['tag_id'] ?? '');
if ($tagFilter !== '') {
    $tagIds = array_values(array_filter(array_map('intval', explode(',', $tagFilter)), fn($v) => $v > 0));
    if (count($tagIds) > 0) {
        $place = implode(',', array_fill(0, count($tagIds), '?'));
        $extra = "c.client_id IN (SELECT client_id FROM client_tag WHERE tag_id IN ($place))";
        $where = $where === '' ? "WHERE $extra" : 'WHERE (' . substr($where, 6) . ") AND $extra";
        $params = array_merge($params, $tagIds);
        $types  = $types . str_repeat('i', count($tagIds));
        $nStmt = @$conn->prepare("SELECT tag_id, tag FROM tag WHERE tag_id IN ($place)");
        $names = [];
        if ($nStmt) { $nStmt->bind_param(str_repeat('i', count($tagIds)), ...$tagIds); $nStmt->execute(); $nr = $nStmt->get_result(); if ($nr) while ($row = $nr->fetch_assoc()) $names[] = $row['tag']; $nStmt->close(); }
        $filterLabels[] = 'Тег = ' . implode(', ', $names);
    }
}

$onlySelected = ((string)($_GET['all'] ?? '0') === '1');
$skipQuery = false;
if ($onlySelected) {
    $marks = load_marks_set($conn, 'client');
    $selectedIds = array_keys($marks);
    if (count($selectedIds) === 0) {
        $skipQuery = true;
    } else {
        $place = implode(',', array_fill(0, count($selectedIds), '?'));
        $extra = "c.client_id IN ($place)";
        $where = $where === '' ? "WHERE $extra" : 'WHERE (' . substr($where, 6) . ") AND $extra";
        $params = array_merge($params, $selectedIds);
        $types  = $types . str_repeat('i', count($selectedIds));
        $filterLabels[] = 'Только отмеченные (' . count($selectedIds) . ')';
    }
}

$onlyPage = ((string)($_GET['page'] ?? '0') !== '0');
$pageNum = max(1, (int)($_GET['page'] ?? 1));
$pageSize = PAGE_SIZE;
$pageOffset = ($pageNum - 1) * $pageSize;
$pageTotal = 0;
$pageCount = 0;
if ($onlyPage) {
    $countSql = "SELECT COUNT(*) AS c FROM client c
        LEFT JOIN cli_categ cat ON cat.cli_categ_id = c.cli_categ_id
        LEFT JOIN city ci ON ci.city_id = c.city_id
        LEFT JOIN country co ON co.country_id = c.country_id
        LEFT JOIN promo pr ON pr.promo_id = c.promo_id
        $where";
    $cntStmt = @mysqli_prepare($conn, $countSql);
    if ($cntStmt) {
        if ($types !== '') stmt_bind($cntStmt, $types, $params);
        $cntStmt->execute();
        $cntRes = $cntStmt->get_result();
        if ($cntRes && ($crow = $cntRes->fetch_assoc())) {
            $pageTotal = (int)$crow['c'];
        }
        $cntStmt->close();
    }
    $pageCount = max(1, (int)ceil($pageTotal / $pageSize));
    if ($pageNum > $pageCount) { $pageNum = $pageCount; $pageOffset = ($pageNum - 1) * $pageSize; }
}

$sql = "SELECT c.client_id,
    TRIM(CONCAT_WS(' ', c.last_name, c.first_name)) AS name,
    c.last_name, c.first_name, c.title,
    c.cli_categ_id, cat.categ AS cli_categ_name,
    c.supplier_flag, c.problem_flag, c.juridical_flag, c.hide_flag,
    c.phone, c.cphone, c.email, c.site,
    c.city_id, ci.city AS city_name,
    c.country_id, co.country AS country_name,
    c.postindex, c.address_jur, c.address,
    c.pasport, c.pasp_date, c.pasp_vydan, c.birthday,
    c.promo_id, pr.promo AS promo_name,
    c.inn, c.kpp, c.ogrn, c.jur_name, c.director, c.glavbuh,
    c.bank, c.bik, c.schet, c.kschet, c.okonh, c.okpo,
    c.disc_goods, c.sum_nach, c.sum_plat, c.sum_balans,
    c.bdate, c.dop1, c.note,
    (SELECT GROUP_CONCAT(tg.tag SEPARATOR ', ') FROM client_tag ctg JOIN tag tg ON tg.tag_id = ctg.tag_id WHERE ctg.client_id = c.client_id) AS tags_concat
    FROM client c
    LEFT JOIN cli_categ cat ON cat.cli_categ_id = c.cli_categ_id
    LEFT JOIN city ci ON ci.city_id = c.city_id
    LEFT JOIN country co ON co.country_id = c.country_id
    LEFT JOIN promo pr ON pr.promo_id = c.promo_id
    $where
    ORDER BY $orderBy";
if ($onlyPage) {
    $sql .= " LIMIT $pageSize OFFSET $pageOffset";
}
$rows = [];
$queryError = '';
if (!$skipQuery) {
    $stmt = @mysqli_prepare($conn, $sql);
    if ($stmt) {
        if ($types !== '') stmt_bind($stmt, $types, $params);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
        if (!$res) $queryError = mysqli_error($conn);
        $stmt->close();
    } else {
        $queryError = mysqli_error($conn);
    }
}

if ($onlyPage) {
    $totalCount = $pageTotal;
    $rangeFrom = $pageTotal > 0 ? $pageOffset + 1 : 0;
    $rangeTo = min($pageOffset + $pageSize, $pageTotal);
} else {
    $totalCount = count($rows);
    $rangeFrom = $totalCount > 0 ? 1 : 0;
    $rangeTo = $totalCount;
}
$now = date('d.m.Y H:i');

function hprint($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
if ($totalCount === 0 && ($queryError !== '' || $onlySelected)): ?>
<div style="max-width:1000px;margin:0 auto 16px;padding:16px 20px;background:#ffe0e0;border:1px solid #c00;font-size:13px;font-family:Arial,sans-serif">
  <b>Диагностика</b><br>
  <b>all=1:</b> <?= $onlySelected ? 'Экспорт в Excel' : 'Экспорт в Excel' ?><br>
  <b>skipQuery:</b> <?= $skipQuery ? 'Экспорт в Excel' : 'Экспорт в Excel' ?><br>
  <?php if ($onlySelected): $marksDbg = load_marks_set($conn, 'client'); ?>
  <b>marks found:</b> <?= count($marksDbg) ?><br>
  <b>marks IDs:</b> <?= implode(', ', array_keys($marksDbg)) ?><br>
  <?php endif; if ($queryError !== ''): ?>
  <b>MySQL error:</b> <?= hprint($queryError) ?><br>
  <?php endif; ?>
  <b>SQL:</b><br>
  <pre style="background:#fff;border:1px solid #ccc;padding:8px;font-size:11px;max-height:200px;overflow:auto"><?= hprint($sql ?? '') ?></pre>
  <b>types:</b> <?= $types ?><br>
  <b>params:</b> <?= implode(', ', array_map('strval', $params)) ?>
</div>
<?php endif; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <title>Контрагенты — Печать</title>
  <style>
    body {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 13px;
      color: #000;
      margin: 0;
      padding: 16px 20px;
      background: #f0f0f0;
    }
    .print-page {
      background: #fff;
      max-width: 1000px;
      margin: 0 auto 16px;
      padding: 24px 28px;
      box-shadow: 0 2px 8px rgba(0,0,0,.1);
    }
    .print-page-header {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      border-bottom: 2px solid #333;
      padding-bottom: 10px;
      margin-bottom: 14px;
    }
    .print-page-title {
      font-size: 22px;
      font-weight: 700;
    }
    .filter-sub { font-size: 12px; color: #555; margin-bottom: 12px; padding: 4px 10px; background: #f5f5f5; border-radius: 4px; }
    .filter-sub span { margin-right: 12px; }
    .print-page-meta {
      font-size: 12px;
      color: #555;
      text-align: right;
    }
    .print-toolbar {
      max-width: 1000px;
      margin: 0 auto 16px;
      display: flex;
      gap: 8px;
      align-items: center;
    }
    .print-btn {
      height: 30px;
      padding: 0 14px;
      background: #3a4a5b;
      color: #fff;
      border: 1px solid #2a3a4b;
      border-radius: 2px;
      cursor: pointer;
      font-size: 13px;
    }
    .print-btn:hover { background: #4a5a6b; }
    .print-btn.primary {
      background: #e67e22;
      border-color: #cf6d1a;
    }
    .print-btn.primary:hover { background: #cf6d1a; }
    .print-info {
      margin-left: auto;
      font-size: 12px;
      color: #555;
    }
    table {
      border-collapse: collapse;
      width: 100%;
      font-size: 12px;
    }
    thead th {
      background: #eee;
      border-bottom: 2px solid #333;
      padding: 4px 6px;
      text-align: left;
      font-weight: 700;
      white-space: nowrap;
      font-size: 11px;
    }
    tbody td {
      border-bottom: 1px solid #ccc;
      padding: 3px 6px;
      vertical-align: top;
      font-size: 11px;
    }
    tbody tr:nth-child(even) td { background: #f7f7f7; }
    @media print {
      body { background: #fff; padding: 0; }
      .print-page { box-shadow: none; padding: 0; margin: 0; max-width: 100%; }
      .print-toolbar { display: none; }
      thead { display: table-header-group; }
      tr { page-break-inside: avoid; }
    }
  </style>
</head>
<body>
  <div class="print-toolbar">
    <button class="print-btn primary" type="button" onclick="window.print()">Печатать</button>
    <button class="print-btn" type="button" onclick="window.close()">Закрыть</button>
    <?php if ($onlyPage): ?>
      <span class="print-info">Страница <?= (int)$pageNum ?> из <?= (int)$pageCount ?> (записей <?= (int)$rangeFrom ?>&ndash;<?= (int)$rangeTo ?> из <?= (int)$totalCount ?>)</span>
    <?php else: ?>
      <span class="print-info">Записей: <?= (int)$totalCount ?></span>
    <?php endif; ?>
  </div>
  <div class="print-page">
    <div class="print-page-header">
      <div class="print-page-title">Контрагенты<?= $onlyPage ? 'Экспорт в Excel' . (int)$pageNum : '' ?></div>
      <?php if (count($filterLabels) > 0): ?>
      <div class="filter-sub"><?php foreach ($filterLabels as $fl): ?><span><?= hprint($fl) ?></span><?php endforeach; ?></div>
      <?php endif; ?>
      <div class="print-page-meta">
        Сформировано: <?= hprint($now) ?><br>
        <?php if ($onlyPage): ?>
          Страница <?= (int)$pageNum ?> из <?= (int)$pageCount ?><br>
          Записей <?= (int)$rangeFrom ?>&ndash;<?= (int)$rangeTo ?> из <?= (int)$totalCount ?>
        <?php else: ?>
          Записей: <?= (int)$totalCount ?>
        <?php endif; ?>
      </div>
    </div>
    <?php if (empty($rows)): ?>
      <p>Нет данных для отображения.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <?php foreach ($visibleColumns as $vc):
              $cn = $vc['name'];
              $w  = $PRINT_WIDTHS[$cn] ?? '';
              $style = $w !== '' ? ' style="width:' . hprint($w) . ';"' : '';
            ?>
              <th<?= $style ?>><?= hprint($COL_META[$cn]['label']) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <?php foreach ($visibleColumns as $vc):
                $cn = $vc['name'];
                $v  = $COL_META[$cn]['value']($r);
              ?>
                <td><?= is_int($v) ? (int)$v : hprint($v) ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>
