<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/ainv_columns.php';
require_once __DIR__ . '/config/ainv_page.php';

$tp = new TablePage($conn, $ainvPageConfig);

$onlyPage = ((string)($_GET['onlyPage'] ?? '0') === '1');
$pageNum = (int)($_GET['pageNum'] ?? 1);
if ($onlyPage) {
    $tp->page = max(1, $pageNum);
}

$rows = $tp->fetchAll($conn);

$colValues = [
    'accept_flag' => function ($r) { return (int)$r['accept_flag'] ? 'Да' : ''; },
    'number'      => fn($r) => (string)$r['number'],
    'date'        => fn($r) => (string)$r['date'],
    'time'        => fn($r) => (string)$r['time'],
    'mesto_id'    => fn($r) => (string)($r['mesto_name'] ?? ''),
    'pos'         => fn($r) => (int)$r['poz'] ? (string)(int)$r['poz'] : '',
    'sum'         => fn($r) => (float)$r['sum'] ? number_format((float)$r['sum'], 2, '.', ' ') : '',
    'first_card'  => fn($r) => (int)$r['first_card'] ? (string)(int)$r['first_card'] : '',
    'last_card'   => fn($r) => (int)$r['last_card'] ? (string)(int)$r['last_card'] : '',
    'firm_id'     => fn($r) => (string)($r['firm_name'] ?? ''),
    'sotr_id'     => fn($r) => (string)($r['sotr_name'] ?? ''),
    'note'        => fn($r) => (string)$r['note'],
];
$printWidths = ainv_columns_widths_print();
$filterLabels = $tp->getFilterDescription();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <title>Инвентаризация — Печать</title>
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
  <h1>Инвентаризация</h1>
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
