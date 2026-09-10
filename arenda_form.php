<?php
require_once __DIR__ . '/config.php';
ensure_marks_table($conn);
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/table-helper.php';
require_once __DIR__ . '/lib/EmbeddedTable.php';
require_once __DIR__ . '/lib/arenda2_totals.php';
require_once __DIR__ . '/lib/arenda2_voz.php';

$accessFlags = get_access_flags($conn, 'Docum');

$isAjax = (
    (string)($_GET['ajax'] ?? '') === '1' ||
    (string)($_POST['ajax'] ?? '') === '1' ||
    (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

const ARENDATYPEOP = 90;

$arShowHoursFlag   = (int)($appSettings['ShowHoursFlag'] ?? 0) === 1;
$arShowDaysFlag    = (int)($appSettings['ShowDaysFlag'] ?? 0) === 1;
$arShowMonthsFlag  = (int)($appSettings['ShowMonthsFlag'] ?? 0) === 1;

function arenda_hide_zero_time($time) {
    $t = trim((string)$time);
    if ($t === '' || $t === '00:00' || $t === '00:00:00') return true;
    return false;
}

$mode = (string)($_GET['mode'] ?? $_POST['mode'] ?? 'edit');
$id   = (int)($_GET['id']   ?? $_POST['id']   ?? 0);
if (!in_array($mode, ['new', 'edit', 'copy', 'delete'], true)) $mode = 'edit';

$errors = [];
$focusField = '';
$values = [
    'number'      => '',
    'date'        => '',
    'time'        => '',
    'date_beg'    => '',
    'time_beg'    => '',
    'time_shift'  => '',
    'date_voz'    => '',
    'time_voz'    => '',
    'firm_id'     => 0,
    'client_id'   => 0,
    'rezerv_flag' => 0,
    'voz_flag'    => 0,
    'discount'    => '0.000',
    'sum_discount'=> '0.00',
    'sum'         => '0.00',
    'sum_zalog'   => '0.00',
    'sum_plat'    => '0.00',
    'pos'         => 0,
    'plat_type'   => 'Наличные',
    'sotr_id'     => 0,
    'store_id'    => 0,
    'note'        => '',
];

/* Инициализация новой записи аренды */
if ($mode === 'new') {
    $values['date']       = date('Y-m-d');
    $values['time']       = date('H:i:s');
    $values['time_shift'] = (string)($appSettings['TimeShift'] ?? '00:00');
    if (!preg_match('/^\d{1,2}:\d{2}/', $values['time_shift'])) $values['time_shift'] = '00:00';
    $shiftMin = ((int)substr($values['time_shift'], 0, 2)) * 60 + (int)substr($values['time_shift'], 3, 2);
    $values['date_beg'] = $values['date'];
    $values['time_beg'] = date('H:i:s', strtotime($values['time']) + $shiftMin * 60);
    $values['date_voz'] = '';
    $values['time_voz'] = '';
    $values['firm_id']  = (int)($appSettings['firm_id'] ?? 0);
    $values['sotr_id']  = ($CurSotrID > 0) ? $CurSotrID : 0;
    if (!empty($appSettings['current_store_id']) && (int)$appSettings['current_store_id'] > 0) {
        $values['store_id'] = (int)$appSettings['current_store_id'];
    }
    $getClientId = (int)($_GET['client_id'] ?? 0);
    if ($getClientId > 0) $values['client_id'] = $getClientId;
}

$origRezervFlag = 0;
$origVozFlag = 0;

if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = $conn->prepare("SELECT number, date, time, date_beg, time_beg, time_shift, date_voz, time_voz,
        firm_id, client_id, rezerv_flag, voz_flag, discount, sum_discount, sum, sum_zalog, sum_plat, pos, plat_type,
        sotr_id, store_id, note FROM docum WHERE docum_id = ? AND typeop = " . ARENDATYPEOP);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) {
        $errors[] = 'Запись не найдена.';
    } else {
        foreach ($values as $k => $v) { $values[$k] = isset($r[$k]) ? (string)$r[$k] : $v; }
        $values['number']      = (int)$r['number'] > 0 ? (string)(int)$r['number'] : '';
        $values['rezerv_flag'] = (int)$r['rezerv_flag'];
        $values['voz_flag']    = (int)$r['voz_flag'];
        $values['pos']         = (int)$r['pos'];
        $origRezervFlag        = (int)$r['rezerv_flag'];
        $origVozFlag           = (int)$r['voz_flag'];
        // пересчёт позиций
        $pr = $conn->query("SELECT COUNT(*) AS cnt FROM docum2 WHERE docum_id = $id");
        if ($pr && ($prow = $pr->fetch_assoc())) {
            $cnt = (int)$prow['cnt'];
            if ($cnt !== (int)$values['pos']) {
                $conn->query("UPDATE docum SET pos = $cnt WHERE docum_id = $id");
                $values['pos'] = $cnt;
            }
        }
    }
}
if ($mode === 'delete' && (int)$values['voz_flag'] === 1) {
    $errors[] = 'Нельзя удалить возвращённый документ.';
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'html' => '<div class="flash flash--error">Нельзя удалить возвращённый документ.</div>', 'mode' => $mode]);
        exit;
    }
    header('Location: arenda.php');
    exit;
}

if ($mode === 'new' || $mode === 'copy') {
    $nr = $conn->query("SELECT COALESCE(MAX(number), 0) + 1 AS next_num FROM docum WHERE typeop = " . ARENDATYPEOP);
    if ($nr && ($nrow = $nr->fetch_assoc())) $values['number'] = (int)$nrow['next_num'];
}
if ($mode === 'copy') {
    $values['rezerv_flag'] = 0;
    $values['voz_flag'] = 0;
    $values['date_voz'] = '';
    $values['time_voz'] = '';
}

// ---- товары документа (docum2) ----
function fmt_qty($v) { return fmt_num($v, 3); }
function fmt_price($v) { return fmt_num($v, 2); }

$docum2Data = [];
if ($id > 0 && $mode !== 'new') {
    $itemsRes = $conn->query("SELECT d.docum2_id AS id, d.product_id, d.code, CASE WHEN d.product_name != '' THEN d.product_name ELSE p.product_name END AS product_name, d.quant, d.price, d.discount, d.sum, d.sum_discount, d.note, d.hours, d.days, d.months, d.sum_zalog, d.rezerv_flag, d.voz_flag, COALESCE(p.noquant_flag,0) AS noquant_flag
        FROM docum2 d LEFT JOIN product p ON p.product_id = d.product_id WHERE d.docum_id = $id AND d.typeop = " . ARENDATYPEOP . " ORDER BY d.docum2_id");
    if ($itemsRes) while ($ir = $itemsRes->fetch_assoc()) {
        $docum2Data[] = [
            'id'           => (int)$ir['id'],
            'product_id'   => (int)$ir['product_id'],
            'code'         => (string)$ir['code'],
            'product_name' => (string)$ir['product_name'],
            'quant'        => fmt_qty($ir['quant']),
            'price'        => fmt_price($ir['price']),
            'discount'     => fmt_price($ir['discount']),
            'sum'          => fmt_price($ir['sum']),
            'sum_discount' => fmt_price($ir['sum_discount']),
            'note'         => (string)$ir['note'],
            'hours'        => trim((string)$ir['hours']) === '' ? '' : substr((string)$ir['hours'], 0, 5),
            'days'         => (int)$ir['days'] > 0 ? (int)$ir['days'] : '',
            'months'       => (int)$ir['months'] > 0 ? (int)$ir['months'] : '',
            'sum_zalog'    => (float)$ir['sum_zalog'] == 0 ? '' : fmt_price($ir['sum_zalog']),
            'rezerv_flag'  => (int)$ir['rezerv_flag'],
            'voz_flag'     => (int)$ir['voz_flag'],
            'noquant_flag' => (int)$ir['noquant_flag'],
        ];
    }
}

$productList = [];
$hideFilter = (empty($appSettings['show_hidden']) || $appSettings['show_hidden'] !== '1') ? 'hide_flag = 0' : '1';
$prs = $conn->query("SELECT product_id, product_name, article AS code, price_out AS price FROM product WHERE " . $hideFilter . " ORDER BY product_name");
if ($prs) while ($pr = $prs->fetch_assoc()) {
    $productList[] = ['id' => (int)$pr['product_id'], 'name' => (string)$pr['product_name'], 'code' => (string)$pr['code'], 'price' => (string)$pr['price']];
}

$d2Columns = [
    ['key' => 'status',       'label' => '',          'readonly' => true],
    ['key' => 'product_name', 'label' => 'Товар',    'type' => 'lookup', 'dbField' => 'product_id'],
    ['key' => 'quant',        'label' => 'Кол-во',   'align' => 'right', 'lockedField' => 'noquant_flag'],
    ['key' => 'hours',        'label' => 'Часов',    'align' => 'right', 'readonly' => !$arShowHoursFlag],
    ['key' => 'days',         'label' => 'Дней',     'align' => 'right', 'readonly' => !$arShowDaysFlag],
    ['key' => 'months',       'label' => 'Месяцев',  'align' => 'right', 'readonly' => !$arShowMonthsFlag],
    ['key' => 'sum',          'label' => 'Сумма',    'align' => 'right'],
    ['key' => 'sum_zalog',    'label' => 'Залог',    'align' => 'right'],
    ['key' => 'discount',     'label' => 'Скидка %', 'align' => 'right'],
    ['key' => 'note',         'label' => 'Примечание'],
];
$d2ColWidths = ['status' => '32px', 'product_name' => '220px', 'quant' => '50px',
    'hours' => '45px', 'days' => '45px', 'months' => '50px', 'sum' => '65px',
    'sum_zalog' => '60px', 'discount' => '50px', 'note' => '500px'];

$d2Table = new EmbeddedTable([
    'prefix'           => 'd2',
    'columns'          => $d2Columns,
    'colWidths'        => $d2ColWidths,
    'saveUrl'          => 'docum2_field_save.php',
    'parentField'      => 'docum_id',
    'childFormUrl'     => 'arenda2_form.php',
    'childFormName'    => 'docum2',
    'columnResizeUrl'  => 'docum2_column_width_save.php',
    'columnResizeTbl'  => 'docum2',
    'hasExport'        => true,
    'hasPrint'         => true,
    'hasSearch'        => true,
    'lookupData'       => ['product_name' => $productList],
    'totalsCallback'   => 'applyDocum2Totals',
    'accessFlags'      => $accessFlags,
]);

$d2RenderData = array_map(function($item) {
    return [
        'id' => $item['id'],
        'product_id' => $item['product_id'],
        'product_name' => $item['product_name'],
        'code' => $item['code'],
        'quant' => $item['quant'],
        'price' => $item['price'],
        'discount' => $item['discount'],
        'sum' => $item['sum'],
        'sum_discount' => $item['sum_discount'] ?? '0.00',
        'note' => $item['note'],
        'hours' => $item['hours'],
        'days' => $item['days'],
        'months' => $item['months'],
        'sum_zalog' => $item['sum_zalog'],
            'rezerv_flag' => (int)$item['rezerv_flag'],
            'voz_flag' => (int)$item['voz_flag'],
        'noquant_flag' => (int)$item['noquant_flag'],
    ];
}, $docum2Data);

// ---- платёжки (doc_type = 90) ----
$platList = [];
$zatIdForPlat = 0;
$zr = $conn->query("SELECT zat_id FROM zat WHERE name = 'За аренду' LIMIT 1");
if ($zr && ($zrow = $zr->fetch_assoc())) $zatIdForPlat = (int)$zrow['zat_id'];
if (!$zatIdForPlat) {
    $zr2 = $conn->query("SELECT zat_id FROM zat WHERE name = 'Оплата' LIMIT 1");
    if ($zr2 && ($zrow2 = $zr2->fetch_assoc())) $zatIdForPlat = (int)$zrow2['zat_id'];
}
if ($id > 0 && $mode !== 'new') {
    $stmtP = $conn->prepare("SELECT p.plat_id, p.datetime, p.sum_in, p.sum_out, p.sum, p.out_flag, p.plat_type, p.doc_id, p.note, c.name AS client_name, z.name AS zat_name, s.last_name AS sotr_name, s.doc_name AS sotr_doc_name FROM plat p LEFT JOIN client c ON c.client_id = p.client_id LEFT JOIN zat z ON z.zat_id = p.zat_id LEFT JOIN sotr s ON s.sotr_id = p.sotr_id WHERE p.doc_id = ? AND p.doc_type = " . ARENDATYPEOP . " ORDER BY p.plat_id DESC");
    if ($stmtP) {
        bind_auto($stmtP, [$id]);
        $stmtP->execute();
        $platList = $stmtP->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtP->close();
    }
}

$platColumns = [
    ['key' => 'datetime',    'label' => 'Дата/Время', 'readonly' => true],
    ['key' => 'client_name', 'label' => 'Контрагент', 'readonly' => true],
    ['key' => 'zat_name',    'label' => 'Вид операции', 'readonly' => true],
    ['key' => 'sum',         'label' => 'Сумма', 'align' => 'right'],
    ['key' => 'plat_type',   'label' => 'Вид платежа'],
    ['key' => 'sotr_name',   'label' => 'Сотрудник', 'readonly' => true],
    ['key' => 'note',        'label' => 'Примечание', 'align' => 'left'],
];

$platColWidths = ['datetime' => '140px', 'client_name' => 'auto', 'zat_name' => '140px', 'sum' => '100px', 'plat_type' => '110px', 'sotr_name' => '140px', 'note' => '400px'];

$platRemain = max(0, (float)$values['sum'] - (float)$values['sum_plat']);

$platTable = new EmbeddedTable([
    'prefix'           => 'plat',
    'columns'          => $platColumns,
    'colWidths'        => $platColWidths,
    'saveUrl'          => 'plat_field_save.php',
    'parentField'      => 'doc_id',
    'childFormUrl'     => 'plat_form.php?doc_type=' . ARENDATYPEOP . '&client_id=' . (int)$values['client_id'] . '&sotr_id=' . $CurSotrID . '&zat_id=' . $zatIdForPlat . '&sum_in=' . urlencode(number_format($platRemain, 2, '.', '')) . '&number=' . urlencode($values['number']) . '&return_url=' . urlencode('arenda_form.php?mode=edit&id=' . (int)$id),
    'childFormName'    => 'plat',
    'columnResizeUrl'  => 'plat_column_width_save.php',
    'columnResizeTbl'  => 'plat',
    'hasExport'        => false,
    'hasPrint'         => false,
    'hasSearch'        => true,
    'totalsCallback'   => 'applyDocum2Totals',
    'toolbarExtraAfterRefresh' => '<span style="display:inline-flex;gap:6px;align-items:center;margin-left:8px">'
        . '<button type="button" class="btn-secondary" id="quickplat-oplata" title="Оплата аренды">Оплата</button>'
        . '<button type="button" class="btn-secondary" id="quickplat-zalog" title="Оплата залога">Залог</button>'
        . '<button type="button" class="btn-secondary" id="quickplat-vozvrat" title="Возврат залога">Возврат</button>'
        . '</span>',
    'accessFlags'      => $accessFlags,
]);

$platListData = array_map(function($p) {
    $dt = strtotime((string)$p['datetime']);
    return [
        'id'          => (int)$p['plat_id'],
        'datetime'    => $dt ? date('d.m.Y H:i', $dt) : '-',
        'client_name' => (string)($p['client_name'] ?? '-'),
        'zat_name'    => (string)($p['zat_name'] ?? '-'),
        'sum'         => (float)$p['sum'],
        'plat_type'   => (string)$p['plat_type'],
        'out_flag'    => (int)$p['out_flag'],
        'sotr_name'   => (string)(($p['sotr_doc_name'] ?? '') !== '' ? $p['sotr_doc_name'] : ($p['sotr_name'] ?? '-')),
        'note'        => (string)$p['note'],
    ];
}, $platList);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['number']       = (int)($_POST['number'] ?? 0);
    $values['date']         = trim((string)($_POST['date'] ?? ''));
    $values['time']         = trim((string)($_POST['time'] ?? ''));
    $values['date_beg']     = trim((string)($_POST['date_beg'] ?? ''));
    $values['time_beg']     = trim((string)($_POST['time_beg'] ?? ''));
    $values['time_shift']   = trim((string)($_POST['time_shift'] ?? '00:00'));
    $values['date_voz']     = trim((string)($_POST['date_voz'] ?? ''));
    $values['time_voz']     = trim((string)($_POST['time_voz'] ?? ''));
    $values['firm_id']      = (int)($_POST['firm_id'] ?? 0);
    $values['client_id']    = (int)($_POST['client_id'] ?? 0);
    $values['rezerv_flag']  = (int)(!empty($_POST['rezerv_flag']));
    $values['voz_flag']     = (int)(!empty($_POST['voz_flag']));
    $values['discount']     = str_replace(',', '.', trim((string)($_POST['discount'] ?? '0')));
    $values['sum_discount'] = str_replace(',', '.', trim((string)($_POST['sum_discount'] ?? '0')));
    $values['sum']          = str_replace(',', '.', trim((string)($_POST['sum'] ?? '0')));
    $values['sum_plat']     = str_replace(',', '.', trim((string)($_POST['sum_plat'] ?? '0')));
    $values['pos']          = (int)($_POST['pos'] ?? 0);
    $values['plat_type']    = trim((string)($_POST['plat_type'] ?? 'Наличные'));
    $values['sotr_id']      = (int)($_POST['sotr_id'] ?? 0);
    $values['store_id']     = (int)($_POST['store_id'] ?? 0);
    $values['note']         = trim((string)($_POST['note'] ?? ''));

    if (empty($_POST['auto_save'])) {
        if ($values['date'] === '') {
            $errors[] = 'Поле «Дата» обязательно для заполнения.';
            $focusField = 'arenda-date';
        }
        if ($values['client_id'] <= 0) {
            $errors[] = 'Поле «Клиент» обязательно для заполнения.';
            $focusField = $focusField ?: 'client-id';
        }
        if ($values['store_id'] <= 0) {
            $errors[] = 'Поле «Участок» обязательно для заполнения.';
            $focusField = $focusField ?: 'store-id';
        }
    }
    if ($values['voz_flag'] === 1 && $values['rezerv_flag'] === 1) {
        // Возвращено имеет приоритет: резерв снимается
        $values['rezerv_flag'] = 0;
    }

    if (empty($errors)) {
        $numVal = $values['number'] > 0 ? $values['number'] : 0;

        if ($mode === 'delete') {
            $stmt = $conn->prepare("DELETE FROM docum WHERE docum_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $conn->query("DELETE FROM docum2 WHERE docum_id = $id AND typeop = " . ARENDATYPEOP);
            $conn->query("DELETE FROM marks WHERE tbl = 'arenda' AND row_id = " . (int)$id);
            if ($isAjax) {
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $id]);
                exit;
            }
            header('Location: arenda.php');
            exit;
        }

        $maxRetries = 10;
        $saved = false;
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            if ($mode === 'new' || $mode === 'copy') {
                $stmt = $conn->prepare("INSERT INTO docum (typeop, number, date, time, date_beg, time_beg, time_shift, date_voz, time_voz, firm_id, client_id, rezerv_flag, voz_flag, discount, sum_discount, sum, sum_zalog, sum_plat, pos, plat_type, sotr_id, store_id, note)
                    VALUES (" . ARENDATYPEOP . ", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                bind_auto($stmt, [$numVal, $values['date'], $values['time'], $values['date_beg'], $values['time_beg'], $values['time_shift'],
                    $values['date_voz'], $values['time_voz'], $values['firm_id'], $values['client_id'], $values['rezerv_flag'], $values['voz_flag'],
                    (float)$values['discount'], (float)$values['sum_discount'], (float)$values['sum'], (float)$values['sum_zalog'], (float)$values['sum_plat'], $values['pos'],
                    $values['plat_type'], $values['sotr_id'], $values['store_id'], $values['note']]);
            } else {
                $stmt = $conn->prepare("UPDATE docum SET number = ?, date = ?, time = ?, date_beg = ?, time_beg = ?, time_shift = ?, date_voz = ?, time_voz = ?,
                    firm_id = ?, client_id = ?, rezerv_flag = ?, voz_flag = ?, discount = ?, sum_discount = ?, sum = ?, sum_zalog = ?, sum_plat = ?, pos = ?, plat_type = ?, sotr_id = ?, store_id = ?, note = ?
                    WHERE docum_id = ?");
                bind_auto($stmt, [$numVal, $values['date'], $values['time'], $values['date_beg'], $values['time_beg'], $values['time_shift'],
                    $values['date_voz'], $values['time_voz'], $values['firm_id'], $values['client_id'], $values['rezerv_flag'], $values['voz_flag'],
                    (float)$values['discount'], (float)$values['sum_discount'], (float)$values['sum'], (float)$values['sum_zalog'], (float)$values['sum_plat'], $values['pos'],
                    $values['plat_type'], $values['sotr_id'], $values['store_id'], $values['note'], $id]);
            }

            if ($stmt->execute()) { $saved = true; break; }
            if ($stmt->errno === 1062) {
                $stmt->close();
                $nr = $conn->query("SELECT COALESCE(MAX(number),0)+1 AS next_num FROM docum WHERE typeop = " . ARENDATYPEOP);
                if ($nr && ($nrow = $nr->fetch_assoc())) $values['number'] = $numVal = (int)$nrow['next_num'];
                continue;
            }
            $errors[] = 'Ошибка БД: ' . $stmt->error;
            $stmt->close();
            goto render;
        }
        $stmt->close();

        if (!$saved) {
            $errors[] = 'Не удалось сохранить: превышено количество попыток.';
            goto render;
        }

        $newId = ($mode === 'new' || $mode === 'copy') ? $conn->insert_id : $id;

        /* При переключении docum.rezerv_flag / docum.voz_flag (в момент сохранения
         * формы ДОКУМЕНТА) пробрасываем флаг на ВСЕ товары; при voz_flag — пересчёт стоимости.
         * Приведение docum.voz_flag к фактическому состоянию товаров здесь НЕ выполняется —
         * дерривация срабатывает только сразу после изменения docum2
         * (arenda2_form.php / docum2_field_save.php), а не при сохранении самого документа. */
        if ($mode === 'edit' && $id > 0) {
            $rezChanged = ((int)$values['rezerv_flag'] !== (int)$origRezervFlag);
            $vozChanged = ((int)$values['voz_flag'] !== (int)$origVozFlag);
            if ($rezChanged || $vozChanged) {
                arenda2_items_set_flags($conn, $id, (int)$values['rezerv_flag'], (int)$values['voz_flag']);
            }
            if ($vozChanged) {
                arenda2_items_recalc_sums($conn, $id, (int)$values['voz_flag'], $appSettings);
            }
        }

        if (!empty($_POST['auto_save'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'id' => $newId]);
            exit;
        }
        if ($isAjax) {
            $pageOfNew = 0;
            if (($mode === 'new' || $mode === 'copy') && $newId > 0) {
                $pageOfNew = computePageOfNew($conn, 'docum', 'docum_id', 'number', 'desc', $numVal, $newId, "typeop = " . ARENDATYPEOP);
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $newId, 'name' => '#' . $newId, 'page' => $pageOfNew]);
            exit;
        }
        header('Location: arenda.php');
        exit;
    }
}

render:

$clientList = [];
$hideSql = (empty($appSettings['show_hidden']) || $appSettings['show_hidden'] !== '1') ? ' WHERE hide_flag = 0' : '';
$crs = $conn->query("SELECT client_id, name FROM client" . $hideSql . " ORDER BY name");
if ($crs) while ($cr = $crs->fetch_assoc()) $clientList[] = ['id' => (int)$cr['client_id'], 'name' => (string)$cr['name']];

$storeList = [];
$srs = $conn->query("SELECT store_id, name FROM store ORDER BY name");
if ($srs) while ($sr = $srs->fetch_assoc()) $storeList[] = ['id' => (int)$sr['store_id'], 'name' => (string)$sr['name']];

$sotrList = [];
$sotrs = $conn->query("SELECT sotr_id, last_name AS name FROM sotr ORDER BY last_name");
if ($sotrs) while ($sr = $sotrs->fetch_assoc()) $sotrList[] = ['id' => (int)$sr['sotr_id'], 'name' => (string)$sr['name']];

$currentClientName = '';
foreach ($clientList as $c) { if ($c['id'] === (int)$values['client_id']) { $currentClientName = $c['name']; break; } }
$currentStoreName = '';
foreach ($storeList as $st) { if ($st['id'] === (int)$values['store_id']) { $currentStoreName = $st['name']; break; } }
$currentSotrName = '';
foreach ($sotrList as $so) { if ($so['id'] === (int)$values['sotr_id']) { $currentSotrName = $so['name']; break; } }

$titleNum = ($values['number'] > 0) ? '№ ' . $values['number'] : '(новый)';
if ($mode === 'edit')       $pageTitle = 'Аренда ' . $titleNum;
elseif ($mode === 'delete') $pageTitle = 'Аренда ' . $titleNum . ' (удаление)';
elseif ($mode === 'new')    $pageTitle = 'Аренда (новая)';
else                        $pageTitle = 'Аренда ' . $titleNum . ' (копия)';

$isReadonly = ($mode === 'delete');
$ro = $isReadonly;
$d2Table->readonly = $ro;
$platTable->readonly = $ro;

function dv($v) { return fmt_price($v); }

ob_start();
?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><img src="img/price.png" alt="" /> <?= h($pageTitle) ?></h2>
<form class="form<?= $mode === 'delete' ? ' form--delete' : '' ?>" method="post" action="arenda_form.php" autocomplete="off" data-form-modal>
<?= render_input('hidden', 'mode', $mode) ?>
<?= render_input('hidden', 'id', $id) ?>
<input type="hidden" name="auto_save_ready" value="1" />
<?= render_input('hidden', 'cli_name', '', ['id' => 'cli-name']) ?>
<?= render_input('hidden', 'store_name', '', ['id' => 'store-name']) ?>
<?= render_input('hidden', 'sotr_name', '', ['id' => 'sotr-name']) ?>

<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>

<div class="tab-container">
  <div class="tab-headers">
    <div class="tab-header active" data-tab-index="0">Параметры</div>
    <div class="tab-header" data-tab-index="1">Товары</div>
    <div class="tab-header" data-tab-index="2">Оплата</div>
  </div>

  <div class="tab-pane active" data-tab-index="0">
    <table class="form-table">
      <tr>
        <td class="form-label">Номер</td>
        <td class="form-label">Фирма</td>
        <td class="form-label">Создано</td>
      </tr>
      <tr>
        <td><?= render_input('number', 'number', $values['number'] > 0 ? $values['number'] : '', [
                'id' => 'arenda-number', 'readonly' => true, 'tabindex' => '-1', 'style' => 'max-width:110px',
            ]) ?></td>
        <td><?= render_input('number', 'firm_id', (int)$values['firm_id'] > 0 ? (int)$values['firm_id'] : '', [
                'id' => 'arenda-firm', 'readonly' => $ro, 'tabindex' => $ro ? '-1' : null, 'style' => 'max-width:80px',
            ]) ?></td>
        <td><div style="display:flex;gap:10px"><?= render_input('date', 'date', $values['date'] ?: date('Y-m-d'), [
                'id' => 'arenda-date', 'required' => !$ro, 'readonly' => true, 'tabindex' => '-1', 'style' => 'max-width:128px',
            ]) ?><?= render_input('time', 'time', $values['time'] ?: date('H:i'), [
                'id' => 'arenda-time', 'readonly' => true, 'tabindex' => '-1', 'style' => 'max-width:110px',
            ]) ?></div></td>
      </tr>
      <tr>
        <td class="form-label">Начало</td>
        <td class="form-label">&nbsp;</td>
        <td>&nbsp;</td>
      </tr>
      <tr>
        <td><div style="display:flex;gap:10px;align-items:center"><?= render_input('date', 'date_beg', $values['date_beg'], [
                'id' => 'arenda-date-beg', 'readonly' => $ro, 'tabindex' => $ro ? '-1' : null, 'style' => 'max-width:150px',
            ]) ?><?= render_input('time', 'time_beg', arenda_hide_zero_time($values['time_beg']) ? '' : substr((string)$values['time_beg'], 0, 5), [
                'id' => 'arenda-time-beg', 'readonly' => $ro, 'tabindex' => $ro ? '-1' : null, 'style' => 'max-width:92px',
            ]) ?><?php if (!$ro): ?><?= render_btn_icon('img/make.png', ['id'=>'apply-beg-btn','type'=>'button','title'=>'Установить Начало у всех товаров','class'=>'icon-btn','style'=>'width:24px;height:24px;padding:0']) ?><?php endif; ?></div></td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
      </tr>
      <tr>
        <td class="form-label">Возврат</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
      </tr>
      <tr>
        <td><div style="display:flex;gap:10px;align-items:center"><?= render_input('date', 'date_voz', $values['date_voz'], [
                'id' => 'arenda-date-voz', 'readonly' => $ro, 'tabindex' => $ro ? '-1' : null, 'style' => 'max-width:150px',
            ]) ?><?= render_input('time', 'time_voz', arenda_hide_zero_time($values['time_voz']) ? '' : substr((string)$values['time_voz'], 0, 5), [
                'id' => 'arenda-time-voz', 'readonly' => $ro, 'tabindex' => $ro ? '-1' : null, 'style' => 'max-width:92px',
            ]) ?><?php if (!$ro): ?><?= render_btn_icon('img/make.png', ['id'=>'apply-voz-btn','type'=>'button','title'=>'Установить Возврат у всех товаров','class'=>'icon-btn','style'=>'width:24px;height:24px;padding:0']) ?><?php endif; ?></div></td>
        <td><label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="voz_flag" id="arenda-doc-voz-flag" value="1"<?= $values['voz_flag'] ? ' checked' : '' ?><?= $ro ? ' disabled' : '' ?> /> Возвращено</label></td>
        <td><label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="rezerv_flag" id="arenda-doc-rezerv-flag" value="1"<?= $values['rezerv_flag'] ? ' checked' : '' ?><?= $ro ? ' disabled' : '' ?> /> Резервирование</label></td>
      </tr>
      <tr>
        <td class="form-label">Контрагент</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
      </tr>
      <tr>
        <td><?= render_lookup('client', 'client_id', (int)$values['client_id'], $currentClientName,
                h(json_encode($clientList, JSON_UNESCAPED_UNICODE)),
                'client_form.php?mode=new', $ro, ['id' => 'client-id', 'data-name-input' => 'cli-name']) ?></td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
      </tr>
      <tr>
        <td class="form-label">Скидка %</td>
        <td class="form-label">Сумма скидки</td>
        <td class="form-label">Позиций</td>
      </tr>
      <tr>
        <td><div style="display:flex;gap:10px;align-items:center"><?= render_input('text', 'discount', dv($values['discount']), [
                'id' => 'arenda-discount', 'readonly' => $ro, 'tabindex' => $ro ? '-1' : null, 'style' => 'max-width:100px;flex:0 0 auto',
            ]) ?><?php if (!$ro): ?><?= render_btn_icon('img/make.png', ['id'=>'apply-discount-btn','type'=>'button','title'=>'Установить Скидку у всех товаров','class'=>'icon-btn','style'=>'width:24px;height:24px;padding:0']) ?><?php endif; ?></div></td>
        <td><?= render_input('text', 'sum_discount', dv($values['sum_discount']), [
                'id' => 'arenda-sum-discount', 'readonly' => true, 'tabindex' => '-1', 'style' => 'max-width:120px',
            ]) ?></td>
        <td><?= render_input('number', 'pos', $values['pos'] > 0 ? (string)$values['pos'] : '', [
                'id' => 'arenda-pos', 'readonly' => true, 'tabindex' => '-1', 'style' => 'max-width:80px',
            ]) ?></td>
      </tr>
      <tr>
        <td class="form-label">Итоговая сумма</td>
        <td class="form-label">Оплачено</td>
        <td class="form-label">Сумма залога</td>
      </tr>
      <tr>
        <td><?= render_input('text', 'sum', dv($values['sum']), [
                'id' => 'arenda-sum', 'readonly' => true, 'tabindex' => '-1', 'style' => 'max-width:120px;font-weight:bold',
            ]) ?></td>
        <td><?= render_input('text', 'sum_plat', dv($values['sum_plat']), [
                'id' => 'arenda-sum-plat', 'readonly' => true, 'tabindex' => '-1', 'style' => 'max-width:120px;font-weight:bold',
            ]) ?></td>
        <td><?= render_input('text', 'sum_zalog', dv($values['sum_zalog']), [
                'id' => 'arenda-sum-zalog', 'readonly' => true, 'tabindex' => '-1', 'style' => 'max-width:120px;font-weight:bold',
            ]) ?></td>
      </tr>
      <tr>
        <td class="form-label">Сотрудник</td>
        <td class="form-label">Участок</td>
        <td class="form-label">Вид платежа</td>
      </tr>
      <tr>
        <td><?= render_lookup('sotr', 'sotr_id', (int)$values['sotr_id'], $currentSotrName,
                h(json_encode($sotrList, JSON_UNESCAPED_UNICODE)),
                '', $ro, ['id' => 'sotr-id', 'data-name-input' => 'sotr-name']) ?></td>
        <td><?= render_lookup('store', 'store_id', (int)$values['store_id'], $currentStoreName,
                h(json_encode($storeList, JSON_UNESCAPED_UNICODE)),
                '', $ro, ['id' => 'store-id', 'data-name-input' => 'store-name']) ?></td>
        <td><?php
            $ptOptions = ['Наличные', 'Безнал.', 'Карта', 'Прочее'];
            $ptHtml = '<select name="plat_type" id="arenda-plat-type" class="field-input"' . ($ro ? ' disabled' : '') . '>';
            foreach ($ptOptions as $opt) {
                $ptHtml .= '<option value="' . h($opt) . '"' . ($values['plat_type'] === $opt ? ' selected' : '') . '>' . h($opt) . '</option>';
            }
            $ptHtml .= '</select>';
            echo $ptHtml;
        ?></td>
      </tr>
      <tr>
        <td class="form-label" colspan="3">Примечание</td>
      </tr>
      <tr>
        <td colspan="3"><?= render_input('text', 'note', $values['note'], [
                'id' => 'arenda-note', 'class' => 'full', 'readonly' => $isReadonly,
                'tabindex' => $isReadonly ? '-1' : null,
            ]) ?></td>
      </tr>
    </table>
    <?= render_form_note() ?>
  </div>

  <div class="tab-pane" data-tab-index="1">
<?php if ($id > 0 && $mode !== 'new'): ?>
    <?= $d2Table->render($d2RenderData) ?>
<?php else: ?>
    <div style="padding:40px 20px;text-align:center;color:var(--muted);font-size:14px">Сохраните документ аренды, чтобы добавить товары</div>
<?php endif; ?>
  </div>

  <div class="tab-pane" data-tab-index="2">
<?php if ($id > 0 && $mode !== 'new'): ?>
    <?= $platTable->render($platListData) ?>
<?php else: ?>
    <div style="padding:40px 20px;text-align:center;color:var(--muted);font-size:14px">Сохраните документ аренды, чтобы добавить платежи</div>
<?php endif; ?>
  </div>
</div>

<?= render_form_actions(
    $mode === 'delete'
        ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'arenda.php', ['class'=>'btn-secondary'])]
        : [render_btn_icon_text('img/accept.png', 'Применить', ['type'=>'submit','name'=>'action','value'=>'apply','formnovalidate'=>true,'class'=>'btn-secondary']),
           render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'arenda.php', ['class'=>'btn-secondary'])]
) ?>
</form>
<script src="assets/inline-edit.js"></script>
<script>if(typeof FormModalCore!=='undefined'&&FormModalCore.initTabAutoSave)FormModalCore.initTabAutoSave([1,2]);</script>
<style>
    .form-note { margin: 1.5px 0; }
    .lookup-wrap { position: relative; max-width: 430px; }
    .form-table { width: 100%; border-collapse: collapse; }
    .form-table td { vertical-align: top; padding-bottom: 6px; padding-right: 8px; }
    .form-table td:last-child { padding-right: 0; }
    .form-table td.form-label { font-size: 12px; color: var(--muted); padding-bottom: 2px; }
    .form-modal { max-width: 990px; }
    .page--form { max-width: 990px; }
    .form { max-width: 990px; }
    .form-table { width: 100%; border-collapse: collapse; }
    /* Единая высота строки значений — отступы одинаковы у всех полей,
       в т.ч. с кнопками и лукапами */
    .form-table { width: 100%; border-collapse: collapse; }
    .form-table td { vertical-align: top; padding-bottom: 6px; padding-right: 8px; }
    .form-table td:last-child { padding-right: 0; }
    .form-table td.form-label { font-size: 12px; color: var(--muted); padding-bottom: 2px; }
    input.full { width: 100%; box-sizing: border-box; }
    .table-wrap { width: 100%; box-sizing: border-box; }
    .docum2-table td { cursor: default; }
    .docum2-table .cell-editable { cursor: text; }
    .docum2-table td[data-field="status"] { position: relative; text-align: center; }
    .docum2-table td[data-field="status"] img {
        display: block;
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
    }
    .tab-container { margin-bottom: 16px; width: 100%; }
    .table-wrap { width: 100%; box-sizing: border-box; }
</style>
<script>
<?php $d2Table->renderScripts(); ?>
<?php $platTable->renderScripts(); ?>
if (typeof window.__openFormModal === 'undefined') {
  try { initD2Table(); } catch(e) {}
  if (window.__d2Table) { applyArendaD2Renderers(window.__d2Table); window.__d2Table.render(); }
  try { initPlatTable(); } catch(e) {}
}
/* Быстрое создание платёжных записей (Оплата / Залог / Возврат) —
   создаёт запись в plat без открытия формы ввода. Глобальная функция, чтобы
   её можно было перепривязать при восстановлении формы в модалке (arenda.php). */
window.bindArendaQuickPlat = function () {
  var kinds = [
    ['quickplat-oplata', 'oplata'],
    ['quickplat-zalog', 'zalog'],
    ['quickplat-vozvrat', 'vozvrat']
  ];
  kinds.forEach(function (pair) {
    var btn = document.getElementById(pair[0]);
    if (!btn || btn.dataset.bound) return;
    btn.dataset.bound = '1';
    btn.addEventListener('click', function () {
      var idEl = document.querySelector('input[name="id"]');
      var id = idEl ? parseInt(idEl.value, 10) : 0;
      if (!id) { alert('Сначала сохраните документ'); return; }
      var fd = new FormData();
      fd.set('docum_id', String(id));
      fd.set('kind', pair[1]);
      fetch('arenda_quickplat.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!(data || {}).ok) { alert((data && data.error) || 'Ошибка'); return; }
          if (typeof window.applyDocum2Totals === 'function') window.applyDocum2Totals(data);
          var tbl = window.__platTable;
          if (tbl) tbl.refresh({ focusId: data.id });
        })
        .catch(function (err) { alert('Ошибка: ' + ((err && err.message) || err)); });
    });
  });
};
bindArendaQuickPlat();

/* Мгновенный проброс docum.voz_flag / docum.rezerv_flag на все товары при
   переключении чекбоксов «Возвращено»/«Резервирование» в форме документа.
   Делает AJAX _set_doc_flags (arenda_field_save.php), который сразу сохраняет
   флаги/периоды/суммы в БД, затем обновляет d2-таблицу и итоги формы — без
   нажатия «Записать» и без выхода из формы. */
window.bindArendaDocFlags = function () {
  var vz = document.getElementById('arenda-doc-voz-flag');
  var rz = document.getElementById('arenda-doc-rezerv-flag');
  if (vz && !vz.__docFlagBound) { vz.__docFlagBound = true; vz.addEventListener('change', function () { arendaSendDocFlags(); }); }
  if (rz && !rz.__docFlagBound) { rz.__docFlagBound = true; rz.addEventListener('change', function () { arendaSendDocFlags(); }); }
};
function arendaSendDocFlags() {
  var idEl = document.querySelector('input[name="id"]');
  var id = idEl ? parseInt(idEl.value, 10) : 0;
  if (!id) { alert('Сначала сохраните документ'); return; }
  var vzEl = document.getElementById('arenda-doc-voz-flag');
  var rzEl = document.getElementById('arenda-doc-rezerv-flag');
  var fd = new FormData();
  fd.set('field', '_set_doc_flags');
  fd.set('docum_id', String(id));
  fd.set('voz_flag', vzEl && vzEl.checked ? '1' : '0');
  fd.set('rezerv_flag', rzEl && rzEl.checked ? '1' : '0');
  fetch('arenda_field_save.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!(data || {}).ok) { alert((data && data.error) || 'Ошибка'); return; }
      /* Синхронизировать чекбоксы с фактическим состоянием (например voz_flag
         сброшен, если не все товары возвращены). */
      if (data.doc_voz_flag !== undefined && vzEl) vzEl.checked = data.doc_voz_flag === 1;
      if (data.doc_rezerv_flag !== undefined && rzEl) rzEl.checked = data.doc_rezerv_flag === 1;
      /* Обновить итоги формы. */
      if (typeof window.applyDocum2Totals === 'function') window.applyDocum2Totals(data);
      /* Обновить d2-таблицу, чтобы увидеть статусы товаров. */
      if (window.__d2Table) window.__d2Table.refresh({ focusId: window.__d2Table.selectedId });
    })
    .catch(function (err) { alert('Ошибка: ' + ((err && err.message) || err)); });
}
bindArendaDocFlags();
</script>
<?php
$formHtml = ob_get_clean();

if ($isAjax) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => empty($errors), 'html' => $formHtml, 'mode' => $mode, 'focusField' => $focusField]);
    exit;
}
?><!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <title><?= h($pageTitle) ?></title>
  <link rel="stylesheet" href="app.css" />
</head>
<body>
  <div class="page page--form">
    <?= $formHtml ?>
  </div>
  <script src="assets/lookup.js"></script>
  <script src="assets/embedded-subtable.js"></script>
<script src="assets/arenda-d2-renderers.js"></script>
  <script>
  if (typeof window.__openFormModal === 'undefined') {
    initD2Table();
    if (window.__d2Table) { applyArendaD2Renderers(window.__d2Table); window.__d2Table.render(); }
    initPlatTable();
  }
  </script>
</body>
</html>
