<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/role_columns.php';
require_once __DIR__ . '/config/role_page.php';

$tp = new TablePage($conn, $rolePageConfig);

$idsParam = trim((string)($_GET['ids'] ?? ''));
$explicitIds = [];
if ($idsParam !== '') {
    $explicitIds = array_values(array_filter(array_map('intval', explode(',', $idsParam)), fn($v) => $v > 0));
}
$onlySelected = ((string)($_GET['all'] ?? '0') === '1');
$skipQuery = false;

if (count($explicitIds) > 0) {
    $place = implode(',', array_fill(0, count($explicitIds), '?'));
    $keyExpr = $tp->keyExpr ?? ($tp->table . '.' . $tp->key);
    $extra = "$keyExpr IN ($place)";
    $tp->appendWhere($extra, $explicitIds, str_repeat('i', count($explicitIds)));
} elseif ($onlySelected) {
    $marks = load_marks_set($conn, $tp->marksTbl);
    $selectedIds = array_keys($marks);
    if (count($selectedIds) === 0) {
        $skipQuery = true;
    } else {
        $place = implode(',', array_fill(0, count($selectedIds), '?'));
        $keyExpr = $tp->keyExpr ?? ($tp->table . '.' . $tp->key);
        $extra = "$keyExpr IN ($place)";
        $tp->appendWhere($extra, $selectedIds, str_repeat('i', count($selectedIds)));
    }
}

$onlyPage = ((string)($_GET['page'] ?? '0') !== '0');
$pageNum = max(1, (int)($_GET['page'] ?? 1));
$pageSize = PAGE_SIZE;
$pageOffset = ($pageNum - 1) * $pageSize;
$pageTotal = 0;
$pageCount = 0;

if ($onlyPage) {
    $countSql = str_placeholder($tp->countSql, $tp->whereSql());
    $cntStmt = @mysqli_prepare($conn, $countSql);
    if ($cntStmt) {
        if ($tp->types !== '') stmt_bind($cntStmt, $tp->types, $tp->params);
        $cntStmt->execute();
        $cntRes = $cntStmt->get_result();
        if ($cntRes && ($crow = $cntRes->fetch_assoc())) {
            $pageTotal = (int)$crow['cnt'];
        }
        $cntStmt->close();
    }
    $pageCount = max(1, (int)ceil($pageTotal / $pageSize));
    if ($pageNum > $pageCount) { $pageNum = $pageCount; $pageOffset = ($pageNum - 1) * $pageSize; }
}

$sql = str_placeholder($tp->selectSql, $tp->whereSql()) . ' ORDER BY ' . $tp->orderBy;
if ($onlyPage) {
    $sql .= " LIMIT $pageSize OFFSET $pageOffset";
}
$rows = [];
if (!$skipQuery) {
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
    $COL_META[$c['name']] = [
        'label' => $c['label'],
        'value' => null,
    ];
}
$COL_META['id']['value']   = function ($r) { return (int)$r['role_id']; };
$COL_META['role']['value'] = function ($r) { return (string)($r['role'] ?? ''); };
$COL_META['note']['value'] = function ($r) { return (string)($r['note'] ?? ''); };
$PRINT_WIDTHS = role_columns_widths_print();

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
  <title>Роли сотрудников — Печать</title>
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
      <div class="print-page-title">Роли сотрудников<?= $onlyPage ? ' &mdash; страница ' . (int)$pageNum : '' ?></div>
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
            <?php foreach ($tp->visibleColumns as $vc):
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
              <?php foreach ($tp->visibleColumns as $vc):
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
