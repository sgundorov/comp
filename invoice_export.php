<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/SimplePdf.php';
require_once __DIR__ . '/config/invoice_columns.php';

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
    $orderSql = ' ORDER BY i.invoice_id DESC';
}

$COL_META = [
    'number'       => ['label' => 'Счет №'],
    'date'         => ['label' => 'Дата'],
    'client'       => ['label' => 'Контрагент'],
    'state'        => ['label' => 'Состояние'],
    'store'        => ['label' => 'Участок'],
    'sum'          => ['label' => 'Сумма'],
    'sotr'         => ['label' => 'Сотрудник'],
    'note'         => ['label' => 'Примечание'],
];

$columnsConfig = load_columns_config($conn, 'invoice', invoice_columns_defaults());
$visibleColumns = array_values(array_filter($columnsConfig, function ($c) { return !empty($c['visible']); }));

$where = '';
$params = [];
$types = '';

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
}

$clientFilter = (string)($_GET['client_id'] ?? '');
if ($clientFilter !== '') {
    $ids = array_values(array_filter(array_map('intval', explode(',', $clientFilter)), fn($v) => $v > 0));
    if (count($ids) > 0) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $where .= " AND i.client_id IN ($ph)";
        foreach ($ids as $idv) { $params[] = $idv; $types .= 'i'; }
    }
}

$storeFilter = (string)($_GET['store_id'] ?? '');
if ($storeFilter !== '') {
    $ids = array_values(array_filter(array_map('intval', explode(',', $storeFilter)), fn($v) => $v > 0));
    if (count($ids) > 0) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $where .= " AND i.store_id IN ($ph)";
        foreach ($ids as $idv) { $params[] = $idv; $types .= 'i'; }
    }
}

$sotrFilter = (string)($_GET['sotr_id'] ?? '');
if ($sotrFilter !== '') {
    $ids = array_values(array_filter(array_map('intval', explode(',', $sotrFilter)), fn($v) => $v > 0));
    if (count($ids) > 0) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $where .= " AND i.sotr_id IN ($ph)";
        foreach ($ids as $idv) { $params[] = $idv; $types .= 'i'; }
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
    }
}

$all = (int)($_GET['all'] ?? 0);
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
    }
}

$sql = "SELECT i.invoice_id, i.number, i.date, i.time, i.client_id, i.state, i.store_id, i.zakaz_num, i.zakaz_id, i.zakaz_type, i.payment_type, i.discount, i.sum_discount, i.sum, i.sum_nds, i.sum_plat, i.date_plat, i.sotr_id, i.pos, i.note, c.name AS client_name, st.name AS store_name, s.doc_name AS sotr_name FROM invoice i LEFT JOIN client c ON i.client_id = c.client_id LEFT JOIN store st ON i.store_id = st.store_id LEFT JOIN sotr s ON i.sotr_id = s.sotr_id WHERE i.doctype_id = 10 $where $orderSql";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$baseName = 'Счета';

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
                    case 'number':       $line[] = (int)$row['number'] > 0 ? (int)$row['number'] : ''; break;
                    case 'date':
                        $dt = strtotime((string)$row['date']);
                        $line[] = $dt ? date('d-m-Y H:i', $dt) : '';
                        break;
                    case 'client':       $line[] = $row['client_name']; break;
                    case 'state':        $line[] = $row['state']; break;
                    case 'store':        $line[] = $row['store_name']; break;
                    case 'sum':          $line[] = number_format((float)$row['sum'], 2, ',', ''); break;
                    case 'sotr':         $line[] = $row['sotr_name']; break;
                    case 'note':         $line[] = $row['note']; break;
                    default:             $line[] = '';
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
                    case 'number':       echo '<td>' . ((int)$row['number'] > 0 ? (int)$row['number'] : '') . '</td>'; break;
                    case 'date':
                        $dt = strtotime((string)$row['date']);
                        echo '<td>' . ($dt ? date('d-m-Y H:i', $dt) : '') . '</td>';
                        break;
                    case 'client':       echo '<td>' . h($row['client_name']) . '</td>'; break;
                    case 'state':        echo '<td>' . h($row['state']) . '</td>'; break;
                    case 'store':        echo '<td>' . h($row['store_name']) . '</td>'; break;
                    case 'sum':          echo '<td>' . number_format((float)$row['sum'], 2, ',', '') . '</td>'; break;
                    case 'sotr':         echo '<td>' . h($row['sotr_name']) . '</td>'; break;
                    case 'note':         echo '<td>' . h($row['note']) . '</td>'; break;
                    default:             echo '<td></td>';
                }
            }
            echo '</tr>';
        }
        echo '</tbody></table></body></html>';
        break;

    case 'pdf':
        $pdf = new SimplePdf();
        $pdf->setTitle($baseName);
        $widths = invoice_columns_widths_export();
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
                    case 'number':       $line[] = (int)$row['number'] > 0 ? (int)$row['number'] : ''; break;
                    case 'date':
                        $dt = strtotime((string)$row['date']);
                        $line[] = $dt ? date('d-m-Y H:i', $dt) : '';
                        break;
                    case 'client':       $line[] = $row['client_name']; break;
                    case 'state':        $line[] = $row['state']; break;
                    case 'store':        $line[] = $row['store_name']; break;
                    case 'sum':          $line[] = number_format((float)$row['sum'], 2, ',', ''); break;
                    case 'sotr':         $line[] = $row['sotr_name']; break;
                    case 'note':         $line[] = $row['note']; break;
                    default:             $line[] = '';
                }
            }
            $data[] = $line;
        }
        $pdf->addPage();
        $pdf->renderTable($headers, $data, $widths);
        $pdf->output($baseName . '.pdf');
        break;
}
