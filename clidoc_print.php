<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/table-helper.php';

$clientId = (int)($_GET['client_id'] ?? 0);
$ids = [];
if (!empty($_GET['ids'])) {
    $ids = array_values(array_filter(array_map('intval', explode(',', $_GET['ids'])), fn($v) => $v > 0));
}

$rows = [];
if ($clientId > 0) {
    $sql = "SELECT id, number, name, filename, note FROM clidoc WHERE client_id = " . (int)$clientId;
    if (!empty($ids)) $sql .= " AND id IN (" . implode(',', $ids) . ")";
    $sql .= " ORDER BY number ASC";
    $rs = $conn->query($sql);
    if ($rs) while ($r = $rs->fetch_assoc()) $rows[] = $r;
}

$clientName = '';
if ($clientId > 0) {
    $stmt = @$conn->prepare("SELECT name FROM client WHERE client_id = ?");
    if ($stmt) { $stmt->bind_param('i', $clientId); $stmt->execute(); $res = $stmt->get_result(); if ($res && ($c = $res->fetch_assoc())) $clientName = (string)$c['name']; $stmt->close(); }
}

$columns = [
    ['name' => 'number',   'label' => '№'],
    ['name' => 'name',     'label' => 'Документ'],
    ['name' => 'filename', 'label' => 'Файл'],
    ['name' => 'note',     'label' => 'Примечание'],
];

$title = 'Документы контрагента' . ($clientName ? ': ' . $clientName : '');
$now = date('d.m.Y H:i');
?><!DOCTYPE html>
<html lang="ru">
<head><meta charset="UTF-8"><title><?= h($title) ?></title>
<style>
@page { size: portrait; }
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
<?php if (empty($rows)): ?><p>Нет записей.</p>
<?php else: ?>
<table><thead><tr>
<?php foreach ($columns as $c): ?><th><?= h($c['label']) ?></th><?php endforeach; ?>
</tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr>
<?php foreach ($columns as $c):
    $cn = $c['name'];
    $v = (string)($r[$cn] ?? '');
?><td><?= h($v) ?></td><?php endforeach; ?>
</tr><?php endforeach; ?>
</tbody></table>
<?php endif; ?>
<div style="margin-top:12px;font-size:11px;color:#888">Сформировано: <?= h($now) ?></div>
</body></html>
