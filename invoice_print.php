<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/invoice_columns.php';
require_once __DIR__ . '/config/invoice_page.php';

$tp = new TablePage($conn, $invoicePageConfig);
$tp->appendWhere("i.doctype_id = ?", [10], 'i');

$tp->applyFilterWithLabel($conn, 'client_id', 'i.client_id', 'Контрагент', 'client', 'client_id', 'name');
$tp->applyFilterWithLabel($conn, 'store_id',  'i.store_id',  'Склад',      'store',  'store_id',  'name');
$tp->applyFilterWithLabel($conn, 'sotr_id',   'i.sotr_id',   'Сотрудник',  'sotr',   'sotr_id',   'name');

$stateFilter = (string)($_GET['state'] ?? '');
if ($stateFilter !== '') {
    $stateMap = [1 => 'Черновик', 2 => 'Выставлен', 3 => 'Оплачен', 4 => 'Отменен'];
    $stateIds = array_values(array_filter(array_map('intval', explode(',', $stateFilter)), fn($v) => isset($stateMap[$v])));
    if (count($stateIds) > 0) {
        $stateNames = array_map(fn($id) => $stateMap[$id], $stateIds);
        $ph = implode(',', array_fill(0, count($stateNames), '?'));
        $tp->appendWhere("i.state IN ($ph)", $stateNames, str_repeat('s', count($stateNames)));
        $labels = [];
        foreach ($stateIds as $sid) $labels[] = $stateMap[$sid] ?? $sid;
        $tp->filters[] = ['kind' => 'col_filter', 'text' => 'Статус = ' . implode(', ', $labels), 'clear' => null];
    }
}

$rows = $tp->fetchAll($conn);

$filterLabels = $tp->getFilterDescription();
$now = date('d.m.Y H:i');
?><!DOCTYPE html>
<html lang="ru">
<head><meta charset="UTF-8"><title>Счета — Печать</title>
<style>
@media print { .no-print { display: none; } }
body { font-family: 'Segoe UI', Tahoma, sans-serif; font-size: 13px; color: #222; margin: 20px; }
.print-toolbar { margin-bottom: 16px; }
.print-toolbar button { padding: 6px 14px; margin-right: 8px; cursor: pointer; }
.print-page-title { font-size: 18px; font-weight: 600; margin-bottom: 4px; }
.filter-sub { font-size: 12px; color: #555; margin-bottom: 12px; padding: 4px 10px; background: #f5f5f5; border-radius: 4px; }
.filter-sub span { margin-right: 12px; }
table { border-collapse: collapse; width: 100%; }
th, td { border: 1px solid #999; padding: 4px 8px; text-align: left; font-size: 12px; }
th { background: #e8e8e8; font-weight: 600; }
.col-sum { text-align: right; white-space: nowrap; }
</style>
</head>
<body>
<div class="no-print print-toolbar">
  <button onclick="window.print()">Печать</button>
  <button onclick="window.close()">Закрыть</button>
  <span style="margin-left:12px;font-size:12px;color:#555">Записей: <?= count($rows) ?></span>
</div>
<div class="print-page-title">Счета <span style="font-size:13px;color:#888;font-weight:normal">(<?= h($now) ?>)</span></div>
<?php if (count($filterLabels) > 0): ?>
<div class="filter-sub"><?php foreach ($filterLabels as $fl): ?><span><?= h($fl) ?></span><?php endforeach; ?></div>
<?php endif; ?>
<table>
<thead><tr>
<?php foreach ($tp->visibleColumns as $vc): ?><th><?= h($vc['label']) ?></th><?php endforeach; ?>
</tr></thead>
<tbody>
<?php foreach ($rows as $row): ?>
<tr>
<?php foreach ($tp->visibleColumns as $vc):
    $n = $vc['name'];
    $v = match($n) {
        'number' => (int)($row['number'] ?? 0) > 0 ? (int)$row['number'] : '',
        'date' => (($dt = strtotime((string)($row['date'] ?? ''))) ? date('d-m-Y H:i', $dt) : ''),
        'client' => (string)($row['client_name'] ?? ''),
        'state' => (function($r) { $s = ['1'=>'Черновик','2'=>'Выставлен','3'=>'Оплачен','4'=>'Отменен']; return $s[(string)($r['state'] ?? '')] ?? (string)($r['state'] ?? ''); })($row),
        'store' => (string)($row['store_name'] ?? ''),
        'sum' => number_format((float)($row['sum'] ?? 0), 2, ',', ' '),
        'sotr' => (string)($row['sotr_name'] ?? ''),
        'note' => (string)($row['note'] ?? ''),
        default => (string)($row[$n] ?? ''),
    };
?><td class="<?= $n === 'sum' ? 'col-sum' : '' ?>"><?= h($v) ?></td>
<?php endforeach; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</body></html>