<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/repmenu_columns.php';
require_once __DIR__ . '/config/repmenu_page.php';

$tp = new TablePage($conn, $repmenuPageConfig);

$tp->applyFilterWithLabel($conn, 'gr_id', 'm.gr_id', 'Группа', 'repgroup', 'gr_id', 'name');

$pageNum = max(1, (int)($_GET['page'] ?? $_GET['pageNum'] ?? 0));
$onlyPage = ((string)($_GET['onlyPage'] ?? '0') === '1') || ((string)($_GET['page'] ?? '0') !== '0');
if ($onlyPage) {
    $tp->page = $pageNum;
}

if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
[$rows, $pagination] = $tp->fetchPage($conn);

$colValues = [
    'id'    => fn($r) => (int)$r['number'],
    'name'  => fn($r) => (string)($r['name'] ?? ''),
    'fname' => fn($r) => (string)($r['fname'] ?? ''),
    'note'  => fn($r) => (string)($r['note'] ?? ''),
];
$filterLabels = $tp->getFilterDescription();
$title = 'Настройка документов';
?><!DOCTYPE html>
<html lang="ru">
<head><meta charset="UTF-8"><title><?= h($title) ?></title>
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
<h1><?= h($title) ?></h1>
<?php if (count($filterLabels) > 0): ?>
  <div style="font-size:12px;color:#555;margin:-8px 0 14px;line-height:1.5">
    <?php foreach ($filterLabels as $fl): ?><div><?= h($fl) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>
<table>
<thead><tr><?php foreach ($tp->visibleColumns as $vc): ?><th><?= h($vc['label'] ?? $vc['name']) ?></th><?php endforeach; ?></tr></thead>
<tbody><?php foreach ($rows as $r): ?><tr><?php foreach ($tp->visibleColumns as $vc):
  $cn = $vc['name'];
  $fn = $colValues[$cn] ?? null;
  $v = $fn ? $fn($r) : ((string)($r[$cn] ?? ''));
?><td><?= h($v) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
</table>
<script>window.onload = function() { window.print(); }</script>
</body></html>