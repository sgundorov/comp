<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/status_columns.php';

$search  = trim((string)($_GET['q'] ?? ''));
$searchActive = ((string)($_GET['sf'] ?? '0') === '1');
$searchCols = [];
$rawCols = (string)($_GET['cols'] ?? '');
if ($rawCols !== '') {
    $searchCols = array_values(array_filter(array_map('trim', explode(',', $rawCols)), function ($k) {
        return in_array($k, ['id', 'status', 'note'], true);
    }));
}
$searchCond = (string)($_GET['cond'] ?? 'contains');
$allowedCond = ['contains', 'not_contains', 'starts_with', 'ends_with', 'equals', 'not_equals', 'gt', 'lt'];
if (!in_array($searchCond, $allowedCond, true)) $searchCond = 'contains';
if (!$searchActive) { $search = ''; $searchCols = []; }
if ($searchActive && $search !== '' && count($searchCols) === 0) {
    $searchCols = ['status', 'note'];
}

$sortRaw = trim((string)($_GET['sort'] ?? ''));
$sortLevels = [];
if ($sortRaw !== '') {
    foreach (explode(',', $sortRaw) as $lv) {
        $pp = explode(':', $lv);
        $ck = $pp[0] ?? '';
        $dk = strtolower($pp[1] ?? 'asc');
        if (in_array($ck, ['id','status','color','note'], true) && in_array($dk, ['asc','desc'], true)) {
            $sortLevels[] = ['col' => $ck, 'dir' => $dk];
        }
    }
}
if (count($sortLevels) === 0) {
    $sortLevels = [['col' => 'id', 'dir' => 'asc']];
}
$colToOrderSql = [
    'id'     => 'st.status_id',
    'status' => 'st.status',
    'color'  => 'st.color',
    'note'   => 'st.note',
];
$orderParts = [];
foreach ($sortLevels as $sl) {
    $orderParts[] = $colToOrderSql[$sl['col']] . ' ' . strtoupper($sl['dir']);
}
$orderBy = implode(', ', $orderParts);

$COLUMN_DEFAULTS = status_columns_defaults();
$COL_META = [];
foreach ($COLUMN_DEFAULTS as $c) {
    $COL_META[$c['name']] = [
        'label' => $c['label'],
        'value' => null,
    ];
}
$COL_META['id']['value']     = function ($r) { return (int)$r['status_id']; };
$COL_META['status']['value'] = function ($r) { return (string)$r['status']; };
$COL_META['color']['value']  = function ($r) { return $r['color'] ? sprintf('#%06x', (int)$r['color']) : ''; };
$COL_META['note']['value']   = function ($r) { return (string)$r['note']; };
$PRINT_WIDTHS = status_columns_widths_print();

$columnsConfig  = load_columns_config($conn, 'status', $COLUMN_DEFAULTS);
$visibleColumns = array_values(array_filter($columnsConfig, function ($c) { return !empty($c['visible']); }));

$where  = '';
$params = [];
$types  = '';
if ($search !== '' && count($searchCols) > 0) {
    $colToExpr = [
        'id'     => 'st.status_id',
        'status' => 'st.status',
        'note'   => 'st.note',
    ];
    $condToOp = [
        'contains'     => 'LIKE',
        'not_contains' => 'NOT LIKE',
        'starts_with'  => 'LIKE',
        'ends_with'    => 'LIKE',
        'equals'       => '=',
        'not_equals'   => '<>',
        'gt'           => '>',
        'lt'           => '<',
    ];
    $op = $condToOp[$searchCond] ?? 'LIKE';
    $parts = [];
    foreach ($searchCols as $col) {
        if (!isset($colToExpr[$col])) continue;
        $parts[] = $colToExpr[$col] . ' ' . $op . ' ?';
        switch ($searchCond) {
            case 'contains':     $params[] = '%' . $search . '%'; break;
            case 'not_contains': $params[] = '%' . $search . '%'; break;
            case 'starts_with':  $params[] = $search . '%'; break;
            case 'ends_with':    $params[] = '%' . $search; break;
            case 'equals':       $params[] = $search; break;
            case 'not_equals':   $params[] = $search; break;
            case 'gt':           $params[] = $search; break;
            case 'lt':           $params[] = $search; break;
            default:             $params[] = '%' . $search . '%';
        }
        $types .= 's';
    }
    if (count($parts) > 0) {
        $where = 'WHERE (' . implode(' OR ', $parts) . ')';
    }
}

$onlySelected = ((string)($_GET['all'] ?? '0') === '1');
$skipQuery = false;
if ($onlySelected) {
    $marks = load_marks_set($conn, 'status');
    $selectedIds = array_keys($marks);
    if (count($selectedIds) === 0) {
        $skipQuery = true;
    } else {
        $place = implode(',', array_fill(0, count($selectedIds), '?'));
        $extra = "st.status_id IN ($place)";
        $where = $where === '' ? "WHERE $extra" : '(' . substr($where, 6) . ") AND $extra";
        $params = array_merge($params, $selectedIds);
        $types  = $types . str_repeat('i', count($selectedIds));
    }
}

$onlyPage = ((string)($_GET['page'] ?? '0') !== '0');
$pageNum = max(1, (int)($_GET['page'] ?? 1));
$pageSize = PAGE_SIZE;
$pageOffset = ($pageNum - 1) * $pageSize;
$pageTotal = 0;
$pageCount = 0;
if ($onlyPage) {
    $countSql = "SELECT COUNT(*) AS c FROM status st $where";
    $cntStmt = @mysqli_prepare($conn, $countSql);
    if ($cntStmt) {
        if ($types !== '') stmt_bind($cntStmt, $types, $params);
        $cntStmt->execute();
        $cntRes = $cntStmt->get_result();
        if ($cntRes && ($crow = $cntRes->fetch_assoc())) {
            $pageTotal = (int)$crow['c'];
        }
        $cntStmt->close();
    }
    $pageCount = max(1, (int)ceil($pageTotal / $pageSize));
    if ($pageNum > $pageCount) { $pageNum = $pageCount; $pageOffset = ($pageNum - 1) * $pageSize; }
}

$sql = "SELECT st.status_id, st.status, st.color, st.note
        FROM status st
        $where
        ORDER BY $orderBy";
if ($onlyPage) {
    $sql .= " LIMIT $pageSize OFFSET $pageOffset";
}
$rows = [];
if (!$skipQuery) {
    $stmt = @mysqli_prepare($conn, $sql);
    if ($stmt) {
        if ($types !== '') stmt_bind($stmt, $types, $params);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
        $stmt->close();
    }
}

if ($onlyPage) {
    $totalCount = $pageTotal;
    $rangeFrom = $pageTotal > 0 ? $pageOffset + 1 : 0;
    $rangeTo = min($pageOffset + $pageSize, $pageTotal);
} else {
    $totalCount = count($rows);
    $rangeFrom = $totalCount > 0 ? 1 : 0;
    $rangeTo = $totalCount;
}
$now = date('d.m.Y H:i');

function hprint($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
?><!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <title>Состояния заявок — Печать</title>
  <style>
    body {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 13px;
      color: #000;
      margin: 0;
      padding: 16px 20px;
      background: #f0f0f0;
    }
    .print-page {
      background: #fff;
      max-width: 1000px;
      margin: 0 auto 16px;
      padding: 24px 28px;
      box-shadow: 0 2px 8px rgba(0,0,0,.1);
    }
    .print-page-header {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      border-bottom: 2px solid #333;
      padding-bottom: 10px;
      margin-bottom: 14px;
    }
    .print-page-title {
      font-size: 22px;
      font-weight: 700;
    }
    .print-page-meta {
      font-size: 12px;
      color: #555;
      text-align: right;
    }
    .print-toolbar {
      max-width: 1000px;
      margin: 0 auto 16px;
      display: flex;
      gap: 8px;
      align-items: center;
    }
    .print-btn {
      height: 30px;
      padding: 0 14px;
      background: #3a4a5b;
      color: #fff;
      border: 1px solid #2a3a4b;
      border-radius: 2px;
      cursor: pointer;
      font-size: 13px;
    }
    .print-btn:hover { background: #4a5a6b; }
    .print-btn.primary {
      background: #e67e22;
      border-color: #cf6d1a;
    }
    .print-btn.primary:hover { background: #cf6d1a; }
    .print-info {
      margin-left: auto;
      font-size: 12px;
      color: #555;
    }
    table {
      border-collapse: collapse;
      width: 100%;
      font-size: 13px;
    }
    thead th {
      background: #eee;
      border-bottom: 2px solid #333;
      padding: 7px 10px;
      text-align: left;
      font-weight: 700;
    }
    tbody td {
      border-bottom: 1px solid #ccc;
      padding: 6px 10px;
      vertical-align: top;
    }
    tbody tr:nth-child(even) td { background: #f7f7f7; }
    @media print {
      body { background: #fff; padding: 0; }
      .print-page { box-shadow: none; padding: 0; margin: 0; max-width: 100%; }
      .print-toolbar { display: none; }
      thead { display: table-header-group; }
      tr { page-break-inside: avoid; }
    }
  </style>
</head>
<body>
  <div class="print-toolbar">
    <button class="print-btn primary" type="button" onclick="window.print()">Печатать</button>
    <button class="print-btn" type="button" onclick="window.close()">Закрыть</button>
    <?php if ($onlyPage): ?>
      <span class="print-info">Страница <?= (int)$pageNum ?> из <?= (int)$pageCount ?> (записей <?= (int)$rangeFrom ?>&ndash;<?= (int)$rangeTo ?> из <?= (int)$totalCount ?>)</span>
    <?php else: ?>
      <span class="print-info">Записей: <?= (int)$totalCount ?></span>
    <?php endif; ?>
  </div>
  <div class="print-page">
    <div class="print-page-header">
      <div class="print-page-title">Состояния заявок<?= $onlyPage ? ' &mdash; страница ' . (int)$pageNum : '' ?></div>
      <div class="print-page-meta">
        Сформировано: <?= hprint($now) ?><br>
        <?php if ($onlyPage): ?>
          Страница <?= (int)$pageNum ?> из <?= (int)$pageCount ?><br>
          Записей <?= (int)$rangeFrom ?>&ndash;<?= (int)$rangeTo ?> из <?= (int)$totalCount ?>
        <?php else: ?>
          Записей: <?= (int)$totalCount ?>
        <?php endif; ?>
      </div>
    </div>
    <?php if (empty($rows)): ?>
      <p>Нет данных для отображения.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <?php foreach ($visibleColumns as $vc):
              $cn = $vc['name'];
              $w  = $PRINT_WIDTHS[$cn] ?? '';
              $style = $w !== '' ? ' style="width:' . hprint($w) . ';"' : '';
            ?>
              <th<?= $style ?>><?= hprint($COL_META[$cn]['label']) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <?php foreach ($visibleColumns as $vc):
                $cn = $vc['name'];
                $v  = $COL_META[$cn]['value']($r);
              ?>
                <td><?= is_int($v) ? (int)$v : hprint($v) ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>
