<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/SimplePdf.php';
require_once __DIR__ . '/config/plat_columns.php';

$format = (string)($_GET['format'] ?? 'csv');

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
        $dir = 'ASC';
        $col = $p;
        if (substr($p, 0, 1) === '-') { $dir = 'DESC'; $col = substr($p, 1); }
        $col = preg_replace('/[^a-z_]/', '', $col);
        $orderBy[] = "$col $dir";
    }
    $orderSql = ' ORDER BY ' . implode(', ', $orderBy);
} else {
    $orderSql = ' ORDER BY p.plat_id DESC';
}

$COL_META = [
    'id'        => ['label' => 'ID'],
    'datetime'  => ['label' => 'Дата/Время'],
    'client'    => ['label' => 'Контрагент'],
    'zat'       => ['label' => 'Вид операции'],
    'sum'       => ['label' => 'Сумма'],
    'plat_type' => ['label' => 'Вид платежа'],
    'doc_id'    => ['label' => 'Документ №'],
    'sotr'      => ['label' => 'Сотрудник'],
    'note'      => ['label' => 'Примечание'],
];

$columnsConfig = load_columns_config($conn, 'plat', plat_columns_defaults());
$visibleColumns = array_values(array_filter($columnsConfig, function ($c) { return !empty($c['visible']); }));

$where = '';
$params = [];
$types = '';

if ($searchActive && $search !== '') {
    $likeCond = '%' . $search . '%';
    $ors = [];
    $searchColsDef = [
        'client'    => 'c.name',
        'zat'       => 'z.name',
        'plat_type' => 'p.plat_type',
        'doc_id'    => 'p.doc_id',
        'sotr'      => 's.doc_name',
        'note'      => 'p.note',
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
}

$all = (int)($_GET['all'] ?? 0);
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
    }
}

$sql = "SELECT p.plat_id, p.datetime, p.client_id, p.zat_id, p.sum, p.out_flag, p.plat_type, p.doc_id, p.sotr_id, p.note, c.name AS client_name, z.name AS zat_name, s.doc_name AS sotr_name FROM plat p LEFT JOIN client c ON p.client_id = c.client_id LEFT JOIN zat z ON p.zat_id = z.zat_id LEFT JOIN sotr s ON p.sotr_id = s.sotr_id WHERE 1=1 $where $orderSql";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $refs4 = [];
    foreach ($params as $k => $v) { $refs4[$k] = &$params[$k]; }
    $stmt->bind_param($types, ...$refs4);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$baseName = 'Кассовая книга';

switch ($format) {
    case 'csv':
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $baseName . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        $headers = [];
        foreach ($visibleColumns as $vc) {
            $headers[] = $vc['label'];
        }
        fputcsv($out, $headers, ';');
        foreach ($rows as $row) {
            $line = [];
            foreach ($visibleColumns as $vc) {
                $n = $vc['name'];
                switch ($n) {
                    case 'id':        $line[] = $row['plat_id']; break;
                    case 'datetime':
                        $dt = strtotime((string)$row['datetime']);
                        $line[] = $dt ? date('d-m-Y H:i', $dt) : '';
                        break;
                    case 'client':    $line[] = $row['client_name']; break;
                    case 'zat':       $line[] = $row['zat_name']; break;
                    case 'sum':
                        $f = (float)$row['sum'];
                        $sign = (int)$row['out_flag'] === 1 ? '- ' : '';
                        $line[] = $sign . number_format($f, 2, ',', '');
                        break;
                    case 'plat_type': $line[] = $row['plat_type']; break;
                    case 'doc_id':    $line[] = (int)$row['doc_id'] > 0 ? (int)$row['doc_id'] : ''; break;
                    case 'sotr':      $line[] = $row['sotr_name']; break;
                    case 'note':      $line[] = $row['note']; break;
                    default:          $line[] = '';
                }
            }
            fputcsv($out, $line, ';');
        }
        fclose($out);
        break;

    case 'xls':
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $baseName . '.xls"');
        echo '<html><head><meta charset="utf-8"><style>td,th{border:1px solid #ccc;padding:4px 8px;font-size:12px;}</style></head><body>';
        echo '<table><thead><tr>';
        foreach ($visibleColumns as $vc) {
            echo '<th>' . h($vc['label']) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($visibleColumns as $vc) {
                $n = $vc['name'];
                switch ($n) {
                    case 'id':        echo '<td>' . $row['plat_id'] . '</td>'; break;
                    case 'datetime':
                        $dt = strtotime((string)$row['datetime']);
                        echo '<td>' . ($dt ? date('d-m-Y H:i', $dt) : '') . '</td>';
                        break;
                    case 'client':    echo '<td>' . h($row['client_name']) . '</td>'; break;
                    case 'zat':       echo '<td>' . h($row['zat_name']) . '</td>'; break;
                    case 'sum':
                        $f = (float)$row['sum'];
                        $sign = (int)$row['out_flag'] === 1 ? '- ' : '';
                        echo '<td>' . $sign . number_format($f, 2, ',', '') . '</td>';
                        break;
                    case 'plat_type': echo '<td>' . h($row['plat_type']) . '</td>'; break;
                    case 'doc_id':    echo '<td>' . ((int)$row['doc_id'] > 0 ? (int)$row['doc_id'] : '') . '</td>'; break;
                    case 'sotr':      echo '<td>' . h($row['sotr_name']) . '</td>'; break;
                    case 'note':      echo '<td>' . h($row['note']) . '</td>'; break;
                    default:          echo '<td></td>';
                }
            }
            echo '</tr>';
        }
        echo '</tbody></table></body></html>';
        break;

    case 'pdf':
        $pdf = new SimplePdf();
        $pdf->setTitle($baseName);
        $widths = plat_columns_widths_export();
        $headers = [];
        $cols = [];
        foreach ($visibleColumns as $vc) {
            $headers[] = $vc['label'];
            $cols[] = $vc['name'];
        }
        $data = [];
        foreach ($rows as $row) {
            $line = [];
            foreach ($cols as $n) {
                switch ($n) {
                    case 'id':        $line[] = $row['plat_id']; break;
                    case 'datetime':
                        $dt = strtotime((string)$row['datetime']);
                        $line[] = $dt ? date('d-m-Y H:i', $dt) : '';
                        break;
                    case 'client':    $line[] = $row['client_name']; break;
                    case 'zat':       $line[] = $row['zat_name']; break;
                    case 'sum':
                        $f = (float)$row['sum'];
                        $sign = (int)$row['out_flag'] === 1 ? '- ' : '';
                        $line[] = $sign . number_format($f, 2, ',', '');
                        break;
                    case 'plat_type': $line[] = $row['plat_type']; break;
                    case 'doc_id':    $line[] = (int)$row['doc_id'] > 0 ? (int)$row['doc_id'] : ''; break;
                    case 'sotr':      $line[] = $row['sotr_name']; break;
                    case 'note':      $line[] = $row['note']; break;
                    default:          $line[] = '';
                }
            }
            $data[] = $line;
        }
        $pdf->addPage();
        $pdf->renderTable($headers, $data, $widths);
        $pdf->output($baseName . '.pdf');
        break;
}
