<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/tmc_columns.php';
require_once __DIR__ . '/config/tmc_page.php';
require_once __DIR__ . '/lib/table-template.php';

$TBL = 'product';
$all    = (string)($_GET['all'] ?? '');
$page   = (int)($_GET['page'] ?? 0);
$idsRaw = (string)($_GET['ids'] ?? '');
$ids    = $idsRaw !== '' ? array_filter(array_map('intval', explode(',', $idsRaw)), fn($v) => $v > 0) : [];

$tp = new TablePage($conn, $tmcPageConfig);

$filterLabels = [];

$filterDefs = [
    'categ_id'   => ['col_expr' => 'p.categ_id', 'label' => 'Категория', 'table' => 'categ', 'idCol' => 'categ_id', 'nameCol' => 'categ'],
    'group_id'   => ['col_expr' => 'p.group_id', 'label' => 'Группа', 'table' => '`group`', 'idCol' => 'group_id', 'nameCol' => 'name'],
    'sgroup_id'  => ['col_expr' => 'p.sgroup_id', 'label' => 'Подгруппа', 'table' => 'sgroup', 'idCol' => 'sgroup_id', 'nameCol' => 'name'],
    'country_id' => ['col_expr' => 'p.country_id', 'label' => 'Страна', 'table' => 'country', 'idCol' => 'country_id', 'nameCol' => 'country'],
];
foreach ($filterDefs as $key => $fd) {
    $raw = (string)($_GET[$key] ?? '');
    if ($raw !== '') {
        $filterIds = array_values(array_filter(array_map('intval', explode(',', $raw)), fn($v) => $v > 0));
        if (count($filterIds) > 0) {
            $place = implode(',', array_fill(0, count($filterIds), '?'));
            $tp->appendWhere($fd['col_expr'] . " IN ($place)", $filterIds, str_repeat('i', count($filterIds)));
            $ph = implode(',', array_fill(0, count($filterIds), '?'));
            $nStmt = $conn->prepare("SELECT {$fd['idCol']}, {$fd['nameCol']} FROM {$fd['table']} WHERE {$fd['idCol']} IN ($ph)");
            $names = [];
            if ($nStmt) {
                $nStmt->bind_param(str_repeat('i', count($filterIds)), ...$filterIds);
                $nStmt->execute();
                $nr = $nStmt->get_result();
                if ($nr) while ($row = $nr->fetch_assoc()) $names[] = $row[$fd['nameCol']];
                $nStmt->close();
            }
            $filterLabels[] = $fd['label'] . ' = ' . implode(', ', $names);
        }
    }
}

$search = (string)($_GET['q'] ?? '');
$sf = (int)($_GET['sf'] ?? 0);
if ($search !== '' && $sf === 1) {
    $filterLabels[] = 'Поиск: «' . $search . '»';
}

if ($page > 0) {
    $tp->page = $page;
    $tp->offset = ($page - 1) * PAGE_SIZE;
}

if (count($ids) > 0) {
    $place = implode(',', array_fill(0, count($ids), '?'));
    $tp->appendWhere("p.product_id IN ($place)", $ids, str_repeat('i', count($ids)));
} elseif ($all === '1') {
    $marks = load_marks_set($conn, $TBL);
    $markIds = array_keys($marks);
    if (count($markIds) > 0) {
        $place = implode(',', array_fill(0, count($markIds), '?'));
        $tp->appendWhere("p.product_id IN ($place)", $markIds, str_repeat('i', count($markIds)));
        $filterLabels[] = 'Только отмеченные (' . count($markIds) . ')';
    }
}

$rows = $tp->getRows($conn);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8" />
<title>Печать — Товары</title>
<style>
body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
table { border-collapse: collapse; width: 100%; }
th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; }
th { background: #f0f0f0; font-weight: bold; }
h1 { font-size: 18px; margin-bottom: 4px; }
.filter-sub { font-size: 12px; color: #555; margin-bottom: 12px; padding: 4px 10px; background: #f5f5f5; border-radius: 4px; }
.filter-sub span { margin-right: 12px; }
.actions { margin-bottom: 12px; }
.actions button { padding: 6px 16px; font-size: 13px; cursor: pointer; }
@media print { .actions { display: none; } }
</style>
</head>
<body>
<div class="actions">
  <button onclick="window.print()">Печатать</button>
  <button onclick="window.close()">Закрыть</button>
</div>
<h1>Товары</h1>
<?php if (count($filterLabels) > 0): ?>
<div class="filter-sub"><?php foreach ($filterLabels as $fl): ?><span><?= h($fl) ?></span><?php endforeach; ?></div>
<?php endif; ?>
<table>
<thead>
<tr>
  <th>ID</th>
  <th>Название</th>
  <th>Артикул</th>
  <th>Категория</th>
  <th>Группа</th>
  <th>Подгруппа</th>
  <th>Страна</th>
  <th>Количество</th>
  <th>Закуп. цена</th>
  <th>Розн. цена</th>
  <th>Примечание</th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
  <td><?= (int)$r['product_id'] ?></td>
  <td><?= h((string)$r['name']) ?></td>
  <td><?= h((string)$r['article']) ?></td>
  <td><?= h((string)($r['categ_name'] ?? '')) ?></td>
  <td><?= h((string)($r['group_name'] ?? '')) ?></td>
  <td><?= h((string)($r['sgroup_name'] ?? '')) ?></td>
  <td><?= h((string)($r['country_name'] ?? '')) ?></td>
  <td style="text-align:right;"><?= number_format((float)($r['quant'] ?? 0), 3, '.', ' ') ?></td>
  <td style="text-align:right;"><?= number_format((float)($r['price_in'] ?? 0), 2, '.', ' ') ?></td>
  <td style="text-align:right;"><?= number_format((float)($r['price_out'] ?? 0), 2, '.', ' ') ?></td>
  <td><?= h((string)$r['note']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</body>
</html>
