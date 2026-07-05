<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/repgroup_columns.php';
require_once __DIR__ . '/config/repgroup_page.php';

$tp = new TablePage($conn, $repgroupPageConfig);
$onlySelected = ((string)($_GET['all'] ?? '0') === '1');
$skipQuery = false;

if ($onlySelected) {
    $marks = load_marks_set($conn, $tp->marksTbl);
    $selectedIds = array_keys($marks);
    if (count($selectedIds) === 0) { $skipQuery = true; }
    else {
        $place = implode(',', array_fill(0, count($selectedIds), '?'));
        $tp->appendWhere("{$tp->key} IN ($place)", $selectedIds, str_repeat('i', count($selectedIds)));
    }
}

$rows = [];
if (!$skipQuery) {
    $sql = str_placeholder($tp->selectSql, $tp->whereSql()) . ' ORDER BY ' . $tp->orderBy;
    $stmt = @mysqli_prepare($conn, $sql);
    if ($stmt) { if ($tp->types !== '') stmt_bind($stmt, $tp->types, $tp->params); $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r; $stmt->close(); }
}

$COL_META = [];
foreach ($tp->columns as $c) $COL_META[$c['name']] = ['label' => $c['label'], 'value' => null];
$COL_META['id']['value']   = function ($r) { return (int)$r['gr_id']; };
$COL_META['name']['value'] = function ($r) { return (string)($r['name'] ?? ''); };
$COL_META['note']['value'] = function ($r) { return (string)($r['note'] ?? ''); };
?>
<!DOCTYPE html>
<html lang="ru">
<head><meta charset="UTF-8"><title>Группы шаблонов документов</title>
<style>body{font-family:Arial;font-size:14px;padding:20px}h1{font-size:20px;margin-bottom:16px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #888;padding:4px 8px;text-align:left}th{background:#ddd;font-weight:bold}@media print{body{padding:0}}</style>
</head>
<body>
<h1>Группы шаблонов документов</h1>
<table><thead><tr><?php foreach ($tp->visibleColumns as $vc): ?><th><?= h($COL_META[$vc['name']]['label']) ?></th><?php endforeach; ?></tr></thead>
<tbody><?php foreach ($rows as $r): ?><tr><?php foreach ($tp->visibleColumns as $vc): ?><td><?= h((string)$COL_META[$vc['name']]['value']($r)) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table>
<script>window.onload=function(){window.print()}</script>
</body></html>
