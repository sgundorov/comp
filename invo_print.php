<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/invo_columns.php';
require_once __DIR__ . '/config/invo_page.php';

$tp = new TablePage($conn, $invoPageConfig);
$tp->appendWhere("i.doctype_id = ?", [10], 'i');

$onlyPage = ((string)($_GET['onlyPage'] ?? '0') === '1');
$pageNum = (int)($_GET['pageNum'] ?? 1);
if ($onlyPage) {
    $tp->page = max(1, $pageNum);
}

$rows = $tp->fetchAll($conn);

$colValues = [
    'number'       => fn($r) => (string)$r['number'],
    'date'         => fn($r) => (string)$r['date'],
    'client'       => fn($r) => (string)($r['client_name'] ?? ''),
    'state'        => function ($r) { $s = ['1'=>'Черновик','2'=>'Выставлен','3'=>'Оплачен','4'=>'Отменен']; return $s[(string)$r['state']] ?? $r['state']; },
    'store'        => fn($r) => (string)($r['store_name'] ?? ''),
    'discount'     => fn($r) => (int)$r['discount'] ? (string)(int)$r['discount'] . '%' : '',
    'sum'          => fn($r) => (float)$r['sum'] ? number_format((float)$r['sum'], 2, '.', ' ') : '',
    'sotr'         => fn($r) => (string)($r['sotr_name'] ?? ''),
    'pos'          => fn($r) => (int)$r['pos'] ? (string)(int)$r['pos'] : '',
    'sum_plat'     => fn($r) => (float)$r['sum_plat'] ? number_format((float)$r['sum_plat'], 2, '.', ' ') : '',
    'sum_nds'      => fn($r) => (float)$r['sum_nds'] ? number_format((float)$r['sum_nds'], 2, '.', ' ') : '',
    'date_plat'    => fn($r) => (string)($r['date_plat'] ?? ''),
    'sum_discount' => fn($r) => (float)$r['sum_discount'] ? number_format((float)$r['sum_discount'], 2, '.', ' ') : '',
    'time'         => fn($r) => (string)($r['time'] ?? ''),
    'note'         => fn($r) => (string)$r['note'],
];
$printWidths = invo_columns_widths_print();
$filterLabels = $tp->getFilterDescription();
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
  <?php if (count($filterLabels) > 0): ?>
    <div style="font-size:12px;color:#555;margin:-8px 0 14px;line-height:1.5">
      <?php foreach ($filterLabels as $fl): ?>
        <div><?= h($fl) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <table>
    <thead>
      <tr>
        <?php foreach ($tp->visibleColumns as $vc): ?>
          <th style="<?= !empty($printWidths[$vc['name']]) ? 'width:' . h($printWidths[$vc['name']]) : '' ?>"><?= h($vc['label'] ?? $vc['name']) ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <?php foreach ($tp->visibleColumns as $vc):
            $cn = $vc['name'];
            $fn = $colValues[$cn] ?? null;
            $v = $fn ? $fn($r) : ((string)($r[$cn] ?? ''));
          ?>
            <td><?= h($v) ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="print-footer">Всего записей: <?= count($rows) ?></div>
  <script>window.onload = function() { window.print(); }</script>
</body>
</html>