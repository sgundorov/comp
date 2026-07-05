<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/repmenu_columns.php';
require_once __DIR__ . '/config/repmenu_page.php';

$tp = new TablePage($conn, $repmenuPageConfig);

$grFilter = (string)($_GET['gr_id'] ?? '');
if ($grFilter !== '') {
    $grFilterIds = array_values(array_filter(array_map('intval', explode(',', $grFilter)), fn($v) => $v > 0));
    if (count($grFilterIds) > 0) {
        $ph = implode(',', array_fill(0, count($grFilterIds), '?'));
        $tp->appendWhere("m.gr_id IN ($ph)", $grFilterIds, str_repeat('i', count($grFilterIds)));
    }
}

$idsParam = trim((string)($_GET['ids'] ?? ''));
$explicitIds = [];
if ($idsParam !== '') {
    $explicitIds = array_values(array_filter(array_map('intval', explode(',', $idsParam)), fn($v) => $v > 0));
}
$onlySelected = ((string)($_GET['all'] ?? '0') === '1');
$onlyPage = ((string)($_GET['onlyPage'] ?? '0') === '1');
$pageNum = (int)($_GET['pageNum'] ?? $tp->page);
$skipQuery = false;

if (count($explicitIds) > 0) {
    $place = implode(',', array_fill(0, count($explicitIds), '?'));
    $keyExpr = $tp->keyExpr ?? ($tp->table . '.' . $tp->key);
    $extra = "$keyExpr IN ($place)";
    $tp->appendWhere($extra, $explicitIds, str_repeat('i', count($explicitIds)));
} elseif ($onlySelected) {
    $marks = load_marks_set($conn, $tp->marksTbl);
    $selectedIds = array_keys($marks);
    if (count($selectedIds) === 0) {
        $skipQuery = true;
    } else {
        $place = implode(',', array_fill(0, count($selectedIds), '?'));
        $keyExpr = $tp->keyExpr ?? ($tp->table . '.' . $tp->key);
        $extra = "$keyExpr IN ($place)";
        $tp->appendWhere($extra, $selectedIds, str_repeat('i', count($selectedIds)));
    }
} elseif ($onlyPage) {
    $tp->page = max(1, $pageNum);
    $tp->offset = ($tp->page - 1) * PAGE_SIZE;
}

$rows = [];
if (!$skipQuery) {
    $sql = str_placeholder($tp->selectSql, $tp->whereSql())
         . ' ORDER BY ' . $tp->orderBy;
    if ($onlyPage && !$onlySelected && count($explicitIds) === 0) {
        $sql .= ' LIMIT ' . PAGE_SIZE . ' OFFSET ' . $tp->offset;
    }
    $stmt = @mysqli_prepare($conn, $sql);
    if ($stmt) {
        if ($tp->types !== '') stmt_bind($stmt, $tp->types, $tp->params);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
        $stmt->close();
    }
}

$COL_META = [];
foreach ($tp->columns as $c) {
    $COL_META[$c['name']] = [
        'label' => $c['label'],
        'value' => null,
    ];
}
$COL_META['id']['value']    = function ($r) { return (int)$r['number']; };
$COL_META['name']['value']  = function ($r) { return (string)($r['name'] ?? ''); };
$COL_META['fname']['value'] = function ($r) { return (string)($r['fname'] ?? ''); };
$COL_META['quant']['value'] = function ($r) { return (int)($r['quant'] ?? 0); };
$COL_META['note']['value']  = function ($r) { return (string)($r['note'] ?? ''); };

$baseName = 'Настройка документов';

?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title><?= h($baseName) ?></title>
<style>
  body { font-family: Arial, sans-serif; font-size: 14px; padding: 20px; }
  h1 { font-size: 20px; margin-bottom: 16px; }
  table { border-collapse: collapse; width: 100%; }
  th, td { border: 1px solid #888; padding: 4px 8px; text-align: left; }
  th { background: #ddd; font-weight: bold; }
  @media print { body { padding: 0; } }
</style>
</head>
<body>
<h1><?= h($baseName) ?></h1>
<table>
<thead><tr>
<?php foreach ($tp->visibleColumns as $vc): ?>
  <th><?= h($COL_META[$vc['name']]['label']) ?></th>
<?php endforeach; ?>
</tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
<?php foreach ($tp->visibleColumns as $vc): ?>
  <td><?= h((string)$COL_META[$vc['name']]['value']($r)) ?></td>
<?php endforeach; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<script>window.onload = function() { window.print(); }</script>
</body>
</html>
