<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/regcod_columns.php';
require_once __DIR__ . '/config/regcod_page.php';

$regcodPageConfig['columns'] = regcod_columns_defaults();
$regcodPageConfig['default_sort'] = ['col' => 'regcod', 'dir' => 'desc'];
$regcodPageConfig['key_expr'] = 'r.regcod_id';

$tp = new TablePage($conn, $regcodPageConfig);

$clientId = (int)($_GET['client_id'] ?? 0);
if ($clientId > 0) {
    $tp->appendWhere("r.client_id = ?", [$clientId], 'i');
}

$rows = $tp->fetchAll($conn);

$clientName = '';
if ($clientId > 0) {
    $stmt = @$conn->prepare("SELECT name FROM client WHERE client_id = ?");
    if ($stmt) { $stmt->bind_param('i', $clientId); $stmt->execute(); $res = $stmt->get_result(); if ($res && ($c = $res->fetch_assoc())) $clientName = (string)$c['name']; $stmt->close(); }
}

$title = 'Регистрационные коды' . ($clientName ? ': ' . $clientName : '');
$now = date('d.m.Y H:i');
$filterLabels = $tp->getFilterDescription();
?><!DOCTYPE html>
<html lang="ru">
<head><meta charset="UTF-8"><title><?= h($title) ?></title>
<style>
body { font-family: Arial, sans-serif; font-size: 13px; margin: 20px; }
h1 { font-size: 18px; margin-bottom: 8px; }
table { border-collapse: collapse; width: 100%; }
th, td { border: 1px solid #999; padding: 4px 8px; text-align: left; }
th { background: #eee; }
.print-toolbar { margin-bottom: 12px; }
@media print { .print-toolbar { display: none; } body { margin: 0; } }
</style>
</head>
<body>
<div class="print-toolbar">
  <button onclick="window.print()">Печатать</button>
  <button onclick="window.close()">Закрыть</button>
  <span style="margin-left:12px;font-size:12px;color:#555">Записей: <?= count($rows) ?></span>
</div>
<h1><?= h($title) ?></h1>
<?php if (count($filterLabels) > 0): ?>
  <div style="font-size:12px;color:#555;margin:-4px 0 12px;line-height:1.5">
    <?php foreach ($filterLabels as $fl): ?><div><?= h($fl) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>
<?php if (empty($rows)): ?><p>Нет записей.</p>
<?php else: ?>
<table><thead><tr>
<?php foreach ($tp->visibleColumns as $vc): ?><th><?= h($vc['label'] ?? $vc['name']) ?></th><?php endforeach; ?>
</tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr>
<?php foreach ($tp->visibleColumns as $vc):
    $cn = $vc['name'];
    $v = match($cn) {
        'days' => (int)($r[$cn] ?? 0) === 0 ? '' : $r[$cn],
        default => (string)($r[$cn] ?? ''),
    };
?><td><?= h($v) ?></td><?php endforeach; ?>
</tr><?php endforeach; ?>
</tbody></table>
<?php endif; ?>
<div style="margin-top:12px;font-size:11px;color:#888">Сформировано: <?= h($now) ?></div>
</body></html>