<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/config/plat_columns.php';

$search  = (string)($_GET['q'] ?? '');
$sf      = (int)($_GET['sf'] ?? 0);
$searchActive = ($search !== '' && $sf === 1);
$searchCols  = (string)($_GET['cols'] ?? '');
$searchCond  = (string)($_GET['cond'] ?? '');
if ($searchCols !== '') $searchCols = explode(',', $searchCols);
else                    $searchCols = [];

$sort = (string)($_GET['sort'] ?? '');
$orderBy = 'p.plat_id DESC';
if ($sort !== '') {
    $allowedCols = ['id', 'datetime', 'client', 'zat', 'sum', 'sum_in', 'sum_out', 'out_flag', 'plat_type', 'doc_id', 'sotr', 'note'];
    $colToSql = [
        'id' => 'p.plat_id', 'datetime' => 'p.datetime', 'client' => 'c.name',
        'zat' => 'z.name', 'sum' => 'p.sum', 'sum_in' => 'p.sum_in', 'sum_out' => 'p.sum_out',
        'out_flag' => 'p.out_flag', 'plat_type' => 'p.plat_type', 'doc_id' => 'p.doc_id',
        'sotr' => 's.doc_name', 'note' => 'p.note',
    ];
    $parts = [];
    foreach (explode(',', $sort) as $lv) {
        $lv = trim($lv);
        $pp = explode(':', $lv);
        $ck = $pp[0] ?? '';
        $dk = strtolower($pp[1] ?? 'asc');
        if (in_array($ck, $allowedCols, true) && in_array($dk, ['asc', 'desc'], true)) {
            $parts[] = ($colToSql[$ck] ?? "p.$ck") . ' ' . strtoupper($dk);
        }
    }
    if (count($parts) > 0) $orderBy = implode(', ', $parts);
}

$columnsConfig = load_columns_config($conn, 'plat', plat_columns_defaults());
$visibleColumns = array_values(array_filter($columnsConfig, function ($c) { return !empty($c['visible']); }));

$where = '';
$params = [];
$types = '';
$filterLabels = [];

$searchColsDef = [
    'client'    => 'c.name',
    'zat'       => 'z.name',
    'plat_type' => 'p.plat_type',
    'doc_id'    => 'p.doc_id',
    'sotr'      => 's.doc_name',
    'note'      => 'p.note',
];

if ($searchActive && $search !== '') {
    $likeCond = '%' . $search . '%';
    $ors = [];
    $searchColsList = (is_array($searchCols) && count($searchCols) > 0) ? $searchCols : array_keys($searchColsDef);
    foreach ($searchColsList as $sc) {
        if (isset($searchColsDef[$sc])) {
            $ors[] = $searchColsDef[$sc] . ' LIKE ?';
            $params[] = $likeCond;
            $types .= 's';
        }
    }
    if (count($ors) > 0) {
        $cond = $searchCond === 'strict' ? ' AND ' : ' OR ';
        $where = ' AND (' . implode($cond, $ors) . ')';
    }
    $condLabels = ['contains' => 'Содержит', 'not_contains' => 'Не содержит', 'starts_with' => 'Начинается с', 'ends_with' => 'Заканчивается на', 'equals' => 'Равно', 'not_equals' => 'Не равно', 'strict' => 'Равно'];
    $filterLabels[] = 'Поиск: «' . $search . '» ' . ($condLabels[$searchCond] ?? 'Содержит');
}

$all = (int)($_GET['all'] ?? 0);
$pageNum = (int)($_GET['page'] ?? 0);
if ($all) {
    $marks = load_marks_set($conn, 'plat');
    if (count($marks) > 0) {
        $ids = array_keys($marks);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $where .= ' AND p.plat_id IN (' . $placeholders . ')';
        foreach ($ids as $id) {
            $params[] = $id;
            $types .= 'i';
        }
        $filterLabels[] = 'Только отмеченные (' . count($ids) . ')';
    }
}

$limitSql = '';
if ($pageNum > 0) {
    $offset = ($pageNum - 1) * PAGE_SIZE;
    $limitSql = " LIMIT $offset, " . PAGE_SIZE;
    $filterLabels[] = 'Страница ' . $pageNum;
}

$clientFilter = (string)($_GET['client_id'] ?? '');
if ($clientFilter !== '') {
    $clientFilterIds = array_values(array_filter(array_map('intval', explode(',', $clientFilter)), fn($v) => $v > 0));
    if (count($clientFilterIds) > 0) {
        $ph = implode(',', array_fill(0, count($clientFilterIds), '?'));
        $where .= " AND p.client_id IN ($ph)";
        foreach ($clientFilterIds as $cid) { $params[] = $cid; $types .= 'i'; }
        $cNames = [];
        $cnStmt = $conn->query("SELECT client_id, name FROM client WHERE client_id IN (" . implode(',', $clientFilterIds) . ")");
        if ($cnStmt) while ($cnRow = $cnStmt->fetch_assoc()) $cNames[] = $cnRow['name'];
        $filterLabels[] = 'Контрагент = ' . implode(', ', $cNames);
    }
}

$zatFilter = (string)($_GET['zat_id'] ?? '');
if ($zatFilter !== '') {
    $zatFilterIds = array_values(array_filter(array_map('intval', explode(',', $zatFilter)), fn($v) => $v > 0));
    if (count($zatFilterIds) > 0) {
        $ph = implode(',', array_fill(0, count($zatFilterIds), '?'));
        $where .= " AND p.zat_id IN ($ph)";
        foreach ($zatFilterIds as $zid) { $params[] = $zid; $types .= 'i'; }
        $zNames = [];
        $znStmt = $conn->query("SELECT zat_id, name FROM zat WHERE zat_id IN (" . implode(',', $zatFilterIds) . ")");
        if ($znStmt) while ($znRow = $znStmt->fetch_assoc()) $zNames[] = $znRow['name'];
        $filterLabels[] = 'Вид операции = ' . implode(', ', $zNames);
    }
}

$sotrFilter = (string)($_GET['sotr_id'] ?? '');
if ($sotrFilter !== '') {
    $sotrFilterIds = array_values(array_filter(array_map('intval', explode(',', $sotrFilter)), fn($v) => $v > 0));
    if (count($sotrFilterIds) > 0) {
        $ph = implode(',', array_fill(0, count($sotrFilterIds), '?'));
        $where .= " AND p.sotr_id IN ($ph)";
        foreach ($sotrFilterIds as $sid) { $params[] = $sid; $types .= 'i'; }
        $sNames = [];
        $snStmt = $conn->query("SELECT sotr_id, doc_name FROM sotr WHERE sotr_id IN (" . implode(',', $sotrFilterIds) . ")");
        if ($snStmt) while ($snRow = $snStmt->fetch_assoc()) $sNames[] = $snRow['doc_name'];
        $filterLabels[] = 'Сотрудник = ' . implode(', ', $sNames);
    }
}

$platTypeFilter = (string)($_GET['plat_type'] ?? '');
if ($platTypeFilter !== '') {
    $platTypeFilterIds = array_values(array_filter(array_map('intval', explode(',', $platTypeFilter)), fn($v) => $v >= 1 && $v <= 4));
    if (count($platTypeFilterIds) > 0) {
        $platTypeIdToValue = [1 => 'Наличные', 2 => 'Безнал.', 3 => 'Карта', 4 => 'Прочее'];
        $ptNames = [];
        foreach ($platTypeFilterIds as $pid) { if (isset($platTypeIdToValue[$pid])) $ptNames[] = $platTypeIdToValue[$pid]; }
        $ph = implode(',', array_fill(0, count($ptNames), '?'));
        $where .= " AND p.plat_type IN ($ph)";
        foreach ($ptNames as $pn) { $params[] = $pn; $types .= 's'; }
        $filterLabels[] = 'Вид платежа = ' . implode(', ', $ptNames);
    }
}

$outTypeFilter = (string)($_GET['out_type'] ?? '');
if ($outTypeFilter !== '') {
    $outTypeFilterIds = array_values(array_filter(array_map('intval', explode(',', $outTypeFilter)), fn($v) => $v >= 1 && $v <= 2));
    if (count($outTypeFilterIds) > 0) {
        $outTypeIdToFlag = [1 => 0, 2 => 1];
        $outTypeFilterFlags = [];
        $otNames = [];
        foreach ($outTypeFilterIds as $oid) {
            if (isset($outTypeIdToFlag[$oid])) $outTypeFilterFlags[] = $outTypeIdToFlag[$oid];
            $otNames[] = $oid === 1 ? 'Приход' : 'Расход';
        }
        $ph = implode(',', array_fill(0, count($outTypeFilterFlags), '?'));
        $where .= " AND p.out_flag IN ($ph)";
        foreach ($outTypeFilterFlags as $of) { $params[] = $of; $types .= 'i'; }
        $filterLabels[] = 'Тип = ' . implode(', ', $otNames);
    }
}

$dfParam = (string)($_GET['date_from'] ?? '');
$dtParam = (string)($_GET['date_to'] ?? '');
if ($dfParam !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dfParam)) {
    $where .= " AND DATE(p.datetime) >= ?";
    $params[] = $dfParam;
    $types .= 's';
    $filterLabels[] = 'Период с ' . date('d-m-Y', strtotime($dfParam));
}
if ($dtParam !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dtParam)) {
    $where .= " AND DATE(p.datetime) <= ?";
    $params[] = $dtParam;
    $types .= 's';
    $endLabel = end($filterLabels);
    if (strpos($endLabel, 'Период с') === 0) {
        $filterLabels[count($filterLabels) - 1] .= ' по ' . date('d-m-Y', strtotime($dtParam));
    } else {
        $filterLabels[] = 'Период по ' . date('d-m-Y', strtotime($dtParam));
    }
}

$sql = "SELECT p.plat_id, p.datetime, p.client_id, p.zat_id, p.sum, p.sum_in, p.sum_out, p.out_flag, p.plat_type, p.doc_id, p.sotr_id, p.note, c.name AS client_name, z.name AS zat_name, s.doc_name AS sotr_name FROM plat p LEFT JOIN client c ON p.client_id = c.client_id LEFT JOIN zat z ON p.zat_id = z.zat_id LEFT JOIN sotr s ON p.sotr_id = s.sotr_id WHERE 1=1 $where ORDER BY $orderBy $limitSql";

$stmt = @$conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $result = @$conn->query($sql);
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

$totalSumIn = 0;
$totalSumOut = 0;
foreach ($rows as $row) {
    $totalSumIn += (float)($row['sum_in'] ?? 0);
    $totalSumOut += (float)($row['sum_out'] ?? 0);
}
$totalSum = $totalSumIn - $totalSumOut;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <title>Кассовая книга — Печать</title>
  <style>
    @media print { .no-print { display: none; } }
    body { font-family: 'Segoe UI', Tahoma, sans-serif; font-size: 13px; color: #222; margin: 20px; }
    .print-toolbar { margin-bottom: 16px; }
    .print-toolbar button { padding: 6px 14px; margin-right: 8px; cursor: pointer; }
    .print-page-title { font-size: 18px; font-weight: 600; margin-bottom: 4px; }
    .print-filters { font-size: 12px; color: #555; margin-bottom: 12px; padding: 6px 10px; background: #f5f5f5; border-radius: 4px; }
    .print-filters span { display: inline-block; margin-right: 12px; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #999; padding: 4px 8px; text-align: left; font-size: 12px; }
    th { background: #e8e8e8; font-weight: 600; }
    .col-sum { text-align: right; white-space: nowrap; }
    .col-id { text-align: center; }
    .print-totals { margin-top: 12px; font-size: 13px; font-weight: 600; }
    .print-totals div { margin-bottom: 2px; }
  </style>
</head>
<body>
  <div class="no-print print-toolbar">
    <button onclick="window.print()">Печать</button>
    <button onclick="window.close()">Закрыть</button>
  </div>
  <div class="print-page-title">Кассовая книга</div>
  <?php if (count($filterLabels) > 0): ?>
  <div class="print-filters">
    <?php foreach ($filterLabels as $fl): ?>
      <span><?= h($fl) ?></span>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <table>
    <thead>
      <tr>
        <?php foreach ($visibleColumns as $vc): ?>
          <th><?= h($vc['label']) ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $row): ?>
      <tr>
        <?php foreach ($visibleColumns as $vc):
          $n = $vc['name'];
          switch ($n) {
              case 'id':
                  echo '<td class="col-id">' . $row['plat_id'] . '</td>';
                  break;
              case 'datetime':
                  $dt = strtotime((string)$row['datetime']);
                  echo '<td>' . ($dt ? date('d-m-Y H:i', $dt) : '') . '</td>';
                  break;
              case 'client':
                  echo '<td>' . h($row['client_name']) . '</td>';
                  break;
              case 'zat':
                  echo '<td>' . h($row['zat_name']) . '</td>';
                  break;
              case 'sum':
                  $f = (float)$row['sum'];
                  echo '<td class="col-sum">' . number_format($f, 2, ',', ' ') . '</td>';
                  break;
              case 'plat_type':
                  echo '<td>' . h($row['plat_type']) . '</td>';
                  break;
              case 'doc_id':
                  echo '<td>' . ((int)$row['doc_id'] > 0 ? '#' . (int)$row['doc_id'] : '') . '</td>';
                  break;
              case 'sotr':
                  echo '<td>' . h($row['sotr_name']) . '</td>';
                  break;
              case 'out_flag':
                  echo '<td>' . ((int)$row['out_flag'] === 1 ? 'Расход' : 'Приход') . '</td>';
                  break;
              case 'note':
                  echo '<td>' . h($row['note']) . '</td>';
                  break;
              default:
                  echo '<td></td>';
          }
        endforeach; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="print-totals">
    <div>Итого приход: <?= number_format($totalSumIn, 2, ',', ' ') ?> руб.</div>
    <div>Итого расход: <?= number_format($totalSumOut, 2, ',', ' ') ?> руб.</div>
    <div>Итого сумма: <?= number_format($totalSum, 2, ',', ' ') ?> руб.</div>
  </div>
</body>
</html>
