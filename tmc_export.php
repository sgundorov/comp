<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/tmc_columns.php';
require_once __DIR__ . '/config/tmc_page.php';
require_once __DIR__ . '/lib/table-template.php';

$format = (string)($_GET['format'] ?? 'csv');
$all    = (string)($_GET['all'] ?? '');
$page   = (int)($_GET['page'] ?? 0);
$idsRaw = (string)($_GET['ids'] ?? '');
$ids    = $idsRaw !== '' ? array_filter(array_map('intval', explode(',', $idsRaw)), fn($v) => $v > 0) : [];
$singleId = (int)($_GET['id'] ?? 0);

$tp = new TablePage($conn, $tmcPageConfig);

$where  = $tp->where;
$params = $tp->params;
$types  = $tp->types;

$filterDefs = [
    'categ_id'   => ['col_expr' => 'p.categ_id'],
    'group_id'   => ['col_expr' => 'p.group_id'],
    'sgroup_id'  => ['col_expr' => 'p.sgroup_id'],
    'country_id' => ['col_expr' => 'p.country_id'],
];
foreach ($filterDefs as $key => $fd) {
    $raw = (string)($_GET[$key] ?? '');
    if ($raw !== '') {
        $filterIds = array_values(array_filter(array_map('intval', explode(',', $raw)), fn($v) => $v > 0));
        if (count($filterIds) > 0) {
            $place = implode(',', array_fill(0, count($filterIds), '?'));
            $tp->appendWhere($fd['col_expr'] . " IN ($place)", $filterIds, str_repeat('i', count($filterIds)));
        }
    }
}

if (count($ids) > 0) {
    $place = implode(',', array_fill(0, count($ids), '?'));
    $tp->appendWhere("p.product_id IN ($place)", $ids, str_repeat('i', count($ids)));
} elseif ($all === '1') {
    $marks = load_marks_set($conn, 'product');
    $markIds = array_keys($marks);
    if (count($markIds) > 0) {
        $place = implode(',', array_fill(0, count($markIds), '?'));
        $tp->appendWhere("p.product_id IN ($place)", $markIds, str_repeat('i', count($markIds)));
    }
} elseif ($singleId > 0) {
    $tp->appendWhere("p.product_id = ?", [$singleId], 'i');
}

$rows = $tp->getRows($conn);

$COL_META = $tp->colMeta;

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tmc.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    $headers = [];
    foreach ($tp->visibleColumns as $vc) {
        if (!empty($vc['export'])) $headers[] = $vc['label'];
    }
    fputcsv($out, $headers, ';');
    foreach ($rows as $r) {
        $row = [];
        foreach ($tp->visibleColumns as $vc) {
            if (empty($vc['export'])) continue;
            $cn = $vc['name'];
            $cm = $COL_META[$cn] ?? null;
            $val = '';
            if ($cm && isset($cm['value'])) {
                $val = (string)$cm['value']($r);
            } else {
                switch ($cn) {
                    case 'id':        $val = (string)(int)$r['product_id']; break;
                    case 'name':      $val = (string)$r['name']; break;
                    case 'article':   $val = (string)$r['article']; break;
                    case 'categ':     $val = (string)($r['categ_name'] ?? ''); break;
                    case 'group':     $val = (string)($r['group_name'] ?? ''); break;
                    case 'sgroup':    $val = (string)($r['sgroup_name'] ?? ''); break;
                    case 'country':   $val = (string)($r['country_name'] ?? ''); break;
                    case 'quant':     $val = (string)(float)($r['quant'] ?? 0); break;
                    case 'price_in':  $val = (string)(float)($r['price_in'] ?? 0); break;
                    case 'price_out': $val = (string)(float)($r['price_out'] ?? 0); break;
                    case 'note':      $val = (string)$r['note']; break;
                }
            }
            $row[] = $val;
        }
        fputcsv($out, $row, ';');
    }
    fclose($out);
} else {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="tmc.xls"');
    echo "<html><meta charset=\"utf-8\"><table><tr>";
    foreach ($tp->visibleColumns as $vc) {
        if (!empty($vc['export'])) echo '<th>' . h($vc['label']) . '</th>';
    }
    echo "</tr>";
    foreach ($rows as $r) {
        echo "<tr>";
        foreach ($tp->visibleColumns as $vc) {
            if (empty($vc['export'])) continue;
            $cn = $vc['name'];
            $cm = $COL_META[$cn] ?? null;
            $val = '';
            if ($cm && isset($cm['value'])) {
                $val = (string)$cm['value']($r);
            } else {
                switch ($cn) {
                    case 'id':        $val = (string)(int)$r['product_id']; break;
                    case 'name':      $val = (string)$r['name']; break;
                    case 'article':   $val = (string)$r['article']; break;
                    case 'categ':     $val = (string)($r['categ_name'] ?? ''); break;
                    case 'group':     $val = (string)($r['group_name'] ?? ''); break;
                    case 'sgroup':    $val = (string)($r['sgroup_name'] ?? ''); break;
                    case 'country':   $val = (string)($r['country_name'] ?? ''); break;
                    case 'quant':     $val = (string)(float)($r['quant'] ?? 0); break;
                    case 'price_in':  $val = (string)(float)($r['price_in'] ?? 0); break;
                    case 'price_out': $val = (string)(float)($r['price_out'] ?? 0); break;
                    case 'note':      $val = (string)$r['note']; break;
                }
            }
            echo '<td>' . h($val) . '</td>';
        }
        echo "</tr>";
    }
    echo "</table></html>";
}
