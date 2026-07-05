<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/SimplePdf.php';
require_once __DIR__ . '/config/sale_columns.php';

$DOC_LABELS = [10 => 'Возврат от покупателя', 20 => 'Приход', 110 => 'Возврат поставщику', 120 => 'Продажа', 127 => 'Списание'];

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
}

$clientFilter = (string)($_GET['client_id'] ?? '');
if ($clientFilter !== '') {
    $ids = array_values(array_filter(array_map('intval', explode(',', $clientFilter)), fn($v) => $v > 0));
    if (count($ids) > 0) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $where .= " AND d.client_id IN ($ph)";
        foreach ($ids as $idv) { $params[] = $idv; $types .= 'i'; }
    }
}

$storeFilter = (string)($_GET['store_id'] ?? '');
if ($storeFilter !== '') {
    $ids = array_values(array_filter(array_map('intval', explode(',', $storeFilter)), fn($v) => $v > 0));
    if (count($ids) > 0) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $where .= " AND d.store_id IN ($ph)";
        foreach ($ids as $idv) { $params[] = $idv; $types .= 'i'; }
    }
}

$all = (int)($_GET['all'] ?? 0);
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
    }
}

$sql = "SELECT d.docum_id, d.number, d.date, d.client_id, d.store_id, d.discount, d.sum, d.sum_plat, d.pos, d.note, d.accept_flag, c.name AS client_name, st.name AS store_name, sotr.doc_name AS sotr_name FROM docum d LEFT JOIN client c ON d.client_id = c.client_id LEFT JOIN store st ON d.store_id = st.store_id LEFT JOIN sotr ON d.sotr_id = sotr.sotr_id WHERE 1=1 $where $orderSql";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$docLabel = $DOC_LABELS[$typeop] ?? 'Документ';
$baseName = $docLabel . ' ' . date('d.m.Y_H.i');

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
                    case 'accept':      $line[] = (int)$row['accept_flag'] > 0 ? '1' : ''; break;
                    case 'number':       $line[] = (int)$row['number'] > 0 ? (int)$row['number'] : ''; break;
                    case 'date':
                        $dt = strtotime((string)$row['date']);
                        $line[] = $dt ? date('d-m-Y H:i', $dt) : '';
                        break;
                    case 'client':       $line[] = $row['client_name']; break;
                    case 'store':        $line[] = $row['store_name']; break;
                    case 'discount':     $line[] = $row['discount']; break;
                    case 'sum':          $line[] = number_format((float)$row['sum'], 2, ',', ''); break;
                    case 'sum_plat':     $line[] = number_format((float)$row['sum_plat'], 2, ',', ''); break;
                    case 'pos':          $line[] = (int)$row['pos'] > 0 ? (int)$row['pos'] : ''; break;
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
                    case 'accept':      echo '<td style="text-align:center">' . ((int)$row['accept_flag'] > 0 ? '1' : '') . '</td>'; break;
                    case 'number':       echo '<td>' . ((int)$row['number'] > 0 ? (int)$row['number'] : '') . '</td>'; break;
                    case 'date':
                        $dt = strtotime((string)$row['date']);
                        echo '<td>' . ($dt ? date('d-m-Y H:i', $dt) : '') . '</td>';
                        break;
                    case 'client':       echo '<td>' . h($row['client_name']) . '</td>'; break;
                    case 'store':        echo '<td>' . h($row['store_name']) . '</td>'; break;
                    case 'discount':     echo '<td>' . h($row['discount']) . '</td>'; break;
                    case 'sum':          echo '<td>' . number_format((float)$row['sum'], 2, ',', '') . '</td>'; break;
                    case 'sum_plat':     echo '<td>' . number_format((float)$row['sum_plat'], 2, ',', '') . '</td>'; break;
                    case 'pos':          echo '<td>' . ((int)$row['pos'] > 0 ? (int)$row['pos'] : '') . '</td>'; break;
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
        $widths = sale_columns_widths_export();
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
                    case 'accept':      $line[] = (int)$row['accept_flag'] > 0 ? '1' : ''; break;
                    case 'number':       $line[] = (int)$row['number'] > 0 ? (int)$row['number'] : ''; break;
                    case 'date':
                        $dt = strtotime((string)$row['date']);
                        $line[] = $dt ? date('d-m-Y H:i', $dt) : '';
                        break;
                    case 'client':       $line[] = $row['client_name']; break;
                    case 'store':        $line[] = $row['store_name']; break;
                    case 'discount':     $line[] = $row['discount']; break;
                    case 'sum':          $line[] = number_format((float)$row['sum'], 2, ',', ''); break;
                    case 'sum_plat':     $line[] = number_format((float)$row['sum_plat'], 2, ',', ''); break;
                    case 'pos':          $line[] = (int)$row['pos'] > 0 ? (int)$row['pos'] : ''; break;
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
