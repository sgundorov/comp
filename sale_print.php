<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/sale_columns.php';
require_once __DIR__ . '/config/sale_page.php';

$DOC_LABELS = [10 => 'Возврат от покупателя', 20 => 'Приход', 110 => 'Возврат поставщику', 120 => 'Продажа', 127 => 'Списание'];

$typeop = (int)($_GET['typeop'] ?? 120);
if (!in_array($typeop, [10, 20, 100, 110, 120, 127], true)) $typeop = 120;

$salePageConfig['marks_session'] = 'sale_select_' . $typeop;
$salePageConfig['marks_tbl'] = 'docum_' . $typeop;

$tp = new TablePage($conn, $salePageConfig);
$tp->appendWhere("d.typeop = ?", [$typeop], 'i');

$tp->applyFilterWithLabel($conn, 'client_id', 'd.client_id', 'Контрагент', 'client', 'client_id', 'name');
$tp->applyFilterWithLabel($conn, 'store_id',  'd.store_id',  'Склад',      'store',  'store_id',  'name');

$rows = $tp->fetchAll($conn);

$docLabel = $DOC_LABELS[$typeop] ?? 'Документ';
$filterLabels = $tp->getFilterDescription();
$now = date('d.m.Y H:i');
?><!DOCTYPE html>
<html lang="ru">
<head><meta charset="UTF-8"><title><?= h($docLabel) ?> — Печать</title>
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
.col-id { text-align: center; }
</style>
</head>
<body>
<div class="no-print print-toolbar">
  <button onclick="window.print()">Печать</button>
  <button onclick="window.close()">Закрыть</button>
  <span style="margin-left:12px;font-size:12px;color:#555">Записей: <?= count($rows) ?></span>
</div>
<div class="print-page-title"><?= h($docLabel) ?> <span style="font-size:13px;color:#888;font-weight:normal">(<?= h($now) ?>)</span></div>
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
        'accept' => (int)($row['accept_flag'] ?? 0) > 0 ? '✓' : '',
        'number' => (int)($row['number'] ?? 0) > 0 ? (int)$row['number'] : '',
        'date' => (($dt = strtotime((string)($row['date'] ?? ''))) ? date('d-m-Y H:i', $dt) : ''),
        'client' => (string)($row['client_name'] ?? ''),
        'store' => (string)($row['store_name'] ?? ''),
        'discount' => (string)($row['discount'] ?? ''),
        'sum' => number_format((float)($row['sum'] ?? 0), 2, ',', ' '),
        'sum_plat' => number_format((float)($row['sum_plat'] ?? 0), 2, ',', ' '),
        'pos' => (int)($row['pos'] ?? 0) > 0 ? (int)$row['pos'] : '',
        'note' => (string)($row['note'] ?? ''),
        default => (string)($row[$n] ?? ''),
    };
?><td class="<?= in_array($n, ['sum','sum_plat']) ? 'col-sum' : '' ?>"><?= h($v) ?></td>
<?php endforeach; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</body></html>