<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/config/invoice_columns.php';

$search  = (string)($_GET['q'] ?? '');
$sf      = (int)($_GET['sf'] ?? 0);
$searchActive = ($search !== '' && $sf === 1);
$searchCols  = (string)($_GET['cols'] ?? '');
$searchCond  = (string)($_GET['cond'] ?? '');
if ($searchCols !== '') $searchCols = explode(',', $searchCols);
else                    $searchCols = [];

$sort = (string)($_GET['sort'] ?? '');
$orderBy = 'i.invoice_id DESC';
if ($sort !== '') {
    $allowedCols = ['number', 'date', 'client', 'state', 'store', 'sum', 'sotr', 'note'];
    $colToSql = [
        'number' => 'i.number', 'date' => 'i.date', 'client' => 'c.name',
        'state' => 'i.state', 'store' => 'st.name', 'sum' => 'i.sum',
        'sotr' => 's.doc_name', 'note' => 'i.note',
    ];
    $parts = [];
    foreach (explode(',', $sort) as $lv) {
        $lv = trim($lv);
        $pp = explode(':', $lv);
        $ck = $pp[0] ?? '';
        $dk = strtolower($pp[1] ?? 'asc');
        if (in_array($ck, $allowedCols, true) && in_array($dk, ['asc', 'desc'], true)) {
            $parts[] = ($colToSql[$ck] ?? "i.$ck") . ' ' . strtoupper($dk);
        }
    }
    if (count($parts) > 0) $orderBy = implode(', ', $parts);
}

$columnsConfig = load_columns_config($conn, 'invoice', invoice_columns_defaults());
$visibleColumns = array_values(array_filter($columnsConfig, function ($c) { return !empty($c['visible']); }));

$where = '';
$params = [];
$types = '';
$filterLabels = [];

if ($searchActive && $search !== '') {
    $likeCond = '%' . $search . '%';
    $ors = [];
    $searchColsDef = [
        'number'       => 'i.number',
        'date'         => 'i.date',
        'client'       => 'c.name',
        'state'        => 'i.state',
        'store'        => 'st.name',
        'sum'          => 'i.sum',
        'sotr'         => 's.doc_name',
        'note'         => 'i.note',
    ];
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
    $filterLabels[] = 'Поиск: «' . $search . '»';
}

$clientFilter = (string)($_GET['client_id'] ?? '');
if ($clientFilter !== '') {
    $ids = array_values(array_filter(array_map('intval', explode(',', $clientFilter)), fn($v) => $v > 0));
    if (count($ids) > 0) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $where .= " AND i.client_id IN ($ph)";
        foreach ($ids as $idv) { $params[] = $idv; $types .= 'i'; }
        $cnr = $conn->prepare("SELECT client_id, name FROM client WHERE client_id IN ($ph)");
        $cNames = [];
        if ($cnr) {
            $cnr->bind_param(str_repeat('i', count($ids)), ...$ids);
            $cnr->execute();
            $cnRes = $cnr->get_result();
            if ($cnRes) while ($cr = $cnRes->fetch_assoc()) $cNames[] = $cr['name'];
            $cnr->close();
        }
        $filterLabels[] = 'Контрагент = ' . implode(', ', $cNames);
    }
}

$storeFilter = (string)($_GET['store_id'] ?? '');
if ($storeFilter !== '') {
    $ids = array_values(array_filter(array_map('intval', explode(',', $storeFilter)), fn($v) => $v > 0));
    if (count($ids) > 0) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $where .= " AND i.store_id IN ($ph)";
        foreach ($ids as $idv) { $params[] = $idv; $types .= 'i'; }
        $snr = $conn->prepare("SELECT store_id, name FROM store WHERE store_id IN ($ph)");
        $sNames = [];
        if ($snr) {
            $snr->bind_param(str_repeat('i', count($ids)), ...$ids);
            $snr->execute();
            $snRes = $snr->get_result();
            if ($snRes) while ($sr = $snRes->fetch_assoc()) $sNames[] = $sr['name'];
            $snr->close();
        }
        $filterLabels[] = 'Склад = ' . implode(', ', $sNames);
    }
}

$sotrFilter = (string)($_GET['sotr_id'] ?? '');
if ($sotrFilter !== '') {
    $ids = array_values(array_filter(array_map('intval', explode(',', $sotrFilter)), fn($v) => $v > 0));
    if (count($ids) > 0) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $where .= " AND i.sotr_id IN ($ph)";
        foreach ($ids as $idv) { $params[] = $idv; $types .= 'i'; }
        $sonr = $conn->prepare("SELECT sotr_id, doc_name FROM sotr WHERE sotr_id IN ($ph)");
        $soNames = [];
        if ($sonr) {
            $sonr->bind_param(str_repeat('i', count($ids)), ...$ids);
            $sonr->execute();
            $soRes = $sonr->get_result();
            if ($soRes) while ($sor = $soRes->fetch_assoc()) $soNames[] = $sor['doc_name'];
            $sonr->close();
        }
        $filterLabels[] = 'Сотрудник = ' . implode(', ', $soNames);
    }
}

$stateFilter = (string)($_GET['state'] ?? '');
if ($stateFilter !== '') {
    $stateIds = array_values(array_filter(array_map('intval', explode(',', $stateFilter)), fn($v) => $v >= 1 && $v <= 4));
    $stateMap = [1 => 'Черновик', 2 => 'Выставлен', 3 => 'Оплачен', 4 => 'Отменен'];
    $names = [];
    foreach ($stateIds as $sid) { if (isset($stateMap[$sid])) $names[] = $stateMap[$sid]; }
    if (count($names) > 0) {
        $ph = implode(',', array_fill(0, count($names), '?'));
        $where .= " AND i.state IN ($ph)";
        foreach ($names as $nv) { $params[] = $nv; $types .= 's'; }
        $filterLabels[] = 'Статус = ' . implode(', ', $names);
    }
}

$all = (int)($_GET['all'] ?? 0);
$onlyPage = (int)($_GET['onlyPage'] ?? 0);

if ($all) {
    $marks = load_marks_set($conn, 'invoice');
    if (count($marks) > 0) {
        $ids = array_keys($marks);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $where .= ' AND i.invoice_id IN (' . $placeholders . ')';
        foreach ($ids as $idv) {
            $params[] = $idv;
            $types .= 'i';
        }
        $filterLabels[] = 'Только отмеченные (' . count($ids) . ')';
    }
}

$pageNum = 1;
if ($onlyPage) {
    $pageNum = (int)($_GET['pageNum'] ?? 1);
    $offset = ($pageNum - 1) * PAGE_SIZE;
    $limitSql = " LIMIT $offset, " . PAGE_SIZE;
} else {
    $limitSql = '';
}

$sql = "SELECT i.invoice_id, i.number, i.date, i.client_id, i.state, i.store_id, i.sum, i.sotr_id, i.note, c.name AS client_name, st.name AS store_name, s.doc_name AS sotr_name FROM invoice i LEFT JOIN client c ON i.client_id = c.client_id LEFT JOIN store st ON i.store_id = st.store_id LEFT JOIN sotr s ON i.sotr_id = s.sotr_id WHERE i.doctype_id = 10 $where ORDER BY $orderBy $limitSql";

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
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <title>Счета — Печать</title>
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
  </div>
  <div class="print-page-title">Счета<?= $onlyPage ? ' &mdash; страница ' . (int)$pageNum : '' ?></div>
  <?php if (count($filterLabels) > 0): ?>
  <div class="filter-sub"><?php foreach ($filterLabels as $fl): ?><span><?= h($fl) ?></span><?php endforeach; ?></div>
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
              case 'number':
                  echo '<td>' . ((int)$row['number'] > 0 ? (int)$row['number'] : '') . '</td>';
                  break;
              case 'date':
                  $dt = strtotime((string)$row['date']);
                  echo '<td>' . ($dt ? date('d-m-Y H:i', $dt) : '') . '</td>';
                  break;
              case 'client':
                  echo '<td>' . h($row['client_name']) . '</td>';
                  break;
              case 'state':
                  echo '<td>' . h($row['state']) . '</td>';
                  break;
              case 'store':
                  echo '<td>' . h($row['store_name']) . '</td>';
                  break;
              case 'sum':
                  echo '<td class="col-sum">' . number_format((float)$row['sum'], 2, ',', ' ') . '</td>';
                  break;
              case 'sotr':
                  echo '<td>' . h($row['sotr_name']) . '</td>';
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
</body>
</html>
