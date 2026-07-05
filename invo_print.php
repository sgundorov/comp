<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/invo_columns.php';
require_once __DIR__ . '/config/invo_page.php';

$tp = new TablePage($conn, $invoPageConfig);
$tp->appendWhere("i.doctype_id = ?", [10], 'i');

$idsParam = trim((string)($_GET['ids'] ?? ''));
$explicitIds = [];
if ($idsParam !== '') {
    $explicitIds = array_values(array_filter(array_map('intval', explode(',', $idsParam)), fn($v) => $v > 0));
}
$onlySelected = ((string)($_GET['all'] ?? '0') === '1');
$onlyPage = ((string)($_GET['onlyPage'] ?? '0') === '1');
$pageNum = (int)($_GET['pageNum'] ?? 1);
$skipQuery = false;

if (count($explicitIds) > 0) {
    $place = implode(',', array_fill(0, count($explicitIds), '?'));
    $keyExpr = $tp->keyExpr ?? ($tp->table . '.' . $tp->key);
    $tp->appendWhere("$keyExpr IN ($place)", $explicitIds, str_repeat('i', count($explicitIds)));
} elseif ($onlySelected) {
    $marks = load_marks_set($conn, $tp->marksTbl);
    $selectedIds = array_keys($marks);
    if (count($selectedIds) === 0) { $skipQuery = true; }
    else {
        $place = implode(',', array_fill(0, count($selectedIds), '?'));
        $keyExpr = $tp->keyExpr ?? ($tp->table . '.' . $tp->key);
        $tp->appendWhere("$keyExpr IN ($place)", $selectedIds, str_repeat('i', count($selectedIds)));
    }
} elseif ($onlyPage) {
    $tp->page = max(1, $pageNum);
}

$rows = [];
if (!$skipQuery) {
    $sql = str_placeholder($tp->selectSql, $tp->whereSql()) . ' ORDER BY ' . $tp->orderBy;
    if ($onlyPage) {
        $tp->perPage = 30;
        $tp->buildPage();
        $sql .= ' LIMIT ' . (int)$tp->perPage . ' OFFSET ' . (int)$tp->offset;
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
    $COL_META[$c['name']] = ['label' => $c['label'], 'value' => null];
}
$COL_META['number']['value']   = function ($r) { return (string)$r['number']; };
$COL_META['date']['value']     = function ($r) { return (string)$r['date']; };
$COL_META['client']['value']   = function ($r) { return (string)($r['client_name'] ?? ''); };
$COL_META['state']['value']    = function ($r) { $s = ['1'=>'Черновик','2'=>'Выставлен','3'=>'Оплачен','4'=>'Отменен']; return $s[(string)$r['state']] ?? $r['state']; };
$COL_META['store']['value']    = function ($r) { return (string)($r['store_name'] ?? ''); };
$COL_META['discount']['value'] = function ($r) { return (int)$r['discount'] ? (string)(int)$r['discount'] . '%' : ''; };
$COL_META['sum']['value']      = function ($r) { return (float)$r['sum'] ? number_format((float)$r['sum'], 2, '.', ' ') : ''; };
$COL_META['sotr']['value']     = function ($r) { return (string)($r['sotr_name'] ?? ''); };
$COL_META['pos']['value']      = function ($r) { return (int)$r['pos'] ? (string)(int)$r['pos'] : ''; };
$COL_META['sum_plat']['value'] = function ($r) { return (float)$r['sum_plat'] ? number_format((float)$r['sum_plat'], 2, '.', ' ') : ''; };
$COL_META['sum_nds']['value']  = function ($r) { return (float)$r['sum_nds'] ? number_format((float)$r['sum_nds'], 2, '.', ' ') : ''; };
$COL_META['date_plat']['value']= function ($r) { return (string)($r['date_plat'] ?? ''); };
$COL_META['sum_discount']['value'] = function ($r) { return (float)$r['sum_discount'] ? number_format((float)$r['sum_discount'], 2, '.', ' ') : ''; };
$COL_META['time']['value']     = function ($r) { return (string)($r['time'] ?? ''); };
$COL_META['note']['value']     = function ($r) { return (string)$r['note']; };

$printWidths = invo_columns_widths_print();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <title>Счета — Печать</title>
  <style>
    body { font-family: Arial, sans-serif; font-size: 14px; margin: 20px; }
    h1 { font-size: 18px; margin-bottom: 10px; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #333; padding: 4px 8px; text-align: left; }
    th { background: #eee; font-weight: bold; }
    .print-footer { margin-top: 20px; font-size: 12px; color: #666; }
    @media print { body { margin: 0; } }
  </style>
</head>
<body>
  <h1>Счета</h1>
  <table>
    <thead>
      <tr>
        <?php foreach ($tp->visibleColumns as $vc): ?>
          <th style="<?= !empty($printWidths[$vc['name']]) ? 'width:' . h($printWidths[$vc['name']]) : '' ?>"><?= h($COL_META[$vc['name']]['label']) ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
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
  <div class="print-footer">Всего записей: <?= count($rows) ?></div>
  <script>window.onload = function() { window.print(); }</script>
</body>
</html>
