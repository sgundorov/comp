<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/config/sale_columns.php';

$DOC_LABELS = [10 => 'Возврат от покупателя', 20 => 'Приход', 110 => 'Возврат поставщику', 120 => 'Продажа', 127 => 'Списание'];

$search  = (string)($_GET['q'] ?? '');
$sf      = (int)($_GET['sf'] ?? 0);
$searchActive = ($search !== '' && $sf === 1);
$searchCols  = (string)($_GET['cols'] ?? '');
$searchCond  = (string)($_GET['cond'] ?? '');
if ($searchCols !== '') $searchCols = explode(',', $searchCols);
else                    $searchCols = [];

$sort = (string)($_GET['sort'] ?? '');
if ($sort !== '') {
    $parts = explode(',', $sort);
    $orderBy = [];
    foreach ($parts as $p) {
        $p = trim($p);
        $col = $p;
        $dir = 'ASC';
        if (strpos($p, ':') !== false) {
            list($col, $dir) = explode(':', $p, 2);
            $col = trim($col);
            $dir = strtoupper(trim($dir)) === 'DESC' ? 'DESC' : 'ASC';
        } elseif (substr($p, 0, 1) === '-') {
            $dir = 'DESC';
            $col = substr($p, 1);
        }
        $col = preg_replace('/[^a-z_]/', '', $col);
        if ($col !== '') $orderBy[] = "$col $dir";
    }
    $orderSql = count($orderBy) > 0 ? ' ORDER BY ' . implode(', ', $orderBy) : ' ORDER BY d.docum_id DESC';
} else {
    $orderSql = ' ORDER BY d.docum_id DESC';
}

$columnsConfig = load_columns_config($conn, 'sale', sale_columns_defaults());
$visibleColumns = array_values(array_filter($columnsConfig, function ($c) { return !empty($c['visible']); }));

$typeop = (int)($_GET['typeop'] ?? 120);
$marks_tbl = 'docum_' . $typeop;

$where = " AND d.typeop = ?";
$params = [$typeop];
$types = 'i';
$filterLabels = [];

if ($searchActive && $search !== '') {
    $likeCond = '%' . $search . '%';
    $ors = [];
    $searchColsDef = [
        'number'       => 'd.number',
        'date'         => 'd.date',
        'client'       => 'c.name',
        'store'        => 'st.name',
        'discount'     => 'd.discount',
        'sum'          => 'd.sum',
        'sum_plat'     => 'd.sum_plat',
        'pos'          => 'd.pos',
        'note'         => 'd.note',
        'sotr'         => 'sotr.doc_name',
        'sotr2'        => 'sotr2.doc_name',
        'zakaz'        => 'd.zakaz_num',
        'date_plat'    => 'd.date_plat',
        'sum_discount' => 'd.sum_discount',
        'sum_balans'   => 'd.sum_balans',
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
        $where .= ' AND (' . implode($cond, $ors) . ')';
    }
    $filterLabels[] = 'Поиск: «' . $search . '»';
}

$clientFilter = (string)($_GET['client_id'] ?? '');
if ($clientFilter !== '') {
    $ids = array_values(array_filter(array_map('intval', explode(',', $clientFilter)), fn($v) => $v > 0));
    if (count($ids) > 0) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $where .= " AND d.client_id IN ($ph)";
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
        $where .= " AND d.store_id IN ($ph)";
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
        $filterLabels[] = 'Участок = ' . implode(', ', $sNames);
    }
}

$all = (int)($_GET['all'] ?? 0);
$onlyPage = (int)($_GET['onlyPage'] ?? 0);
$pageArg  = (int)($_GET['page'] ?? 0);
if ($pageArg > 0 && !$onlyPage) { $onlyPage = 1; $_GET['pageNum'] = $pageArg; }

if ($all) {
    $marks = load_marks_set($conn, $marks_tbl);
    if (count($marks) > 0) {
        $ids = array_keys($marks);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $where .= ' AND d.docum_id IN (' . $placeholders . ')';
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

$sql = "SELECT d.docum_id, d.number, d.date, d.client_id, d.store_id, d.discount, d.sum, d.sum_plat, d.pos, d.note, d.accept_flag, c.name AS client_name, st.name AS store_name, sotr.doc_name AS sotr_name FROM docum d LEFT JOIN client c ON d.client_id = c.client_id LEFT JOIN store st ON d.store_id = st.store_id LEFT JOIN sotr ON d.sotr_id = sotr.sotr_id WHERE 1=1 $where $orderSql $limitSql";

$stmt = $conn->prepare($sql);
if (!$stmt) { die('Ошибка SQL: ' . h($conn->error)); }
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$docLabel = $DOC_LABELS[$typeop] ?? 'Документ';
$pageTitle = $docLabel . ' — Печать ' . date('d.m.Y H:i');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <title><?= h($pageTitle) ?></title>
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
  <div class="print-page-title"><?= h($docLabel) ?><?= $onlyPage ? ' — страница ' . $pageNum : '' ?> <span style="font-size:13px;color:#888;font-weight:normal">(<?= date('d.m.Y H:i') ?>)</span></div>
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
              case 'accept':
                  echo '<td style="text-align:center">' . ($row['accept_flag'] ? '<img src="img/lock.png" alt="Утв." width="16" height="16" />' : '') . '</td>';
                  break;
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
              case 'store':
                  echo '<td>' . h($row['store_name']) . '</td>';
                  break;
              case 'discount':
                  echo '<td>' . h($row['discount']) . '</td>';
                  break;
              case 'sum':
                  echo '<td class="col-sum">' . number_format((float)$row['sum'], 2, ',', ' ') . '</td>';
                  break;
              case 'sum_plat':
                  echo '<td class="col-sum">' . number_format((float)$row['sum_plat'], 2, ',', ' ') . '</td>';
                  break;
              case 'pos':
                  echo '<td>' . ((int)$row['pos'] > 0 ? (int)$row['pos'] : '') . '</td>';
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
