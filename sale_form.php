<?php
require_once __DIR__ . '/config.php';
ensure_marks_table($conn);
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/table-helper.php';
require_once __DIR__ . '/lib/EmbeddedTable.php';
require_once __DIR__ . '/config/docum2_columns.php';

$isAjax = (
    (string)($_GET['ajax'] ?? '') === '1' ||
    (string)($_POST['ajax'] ?? '') === '1' ||
    (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

$mode = (string)($_GET['mode'] ?? $_POST['mode'] ?? 'edit');
$id   = (int)($_GET['id']   ?? $_POST['id']   ?? 0);
$typeop = (int)($_GET['typeop'] ?? $_POST['typeop'] ?? 120);
if (!in_array($typeop, [10, 20, 100, 110, 120, 127], true)) $typeop = 120;

$prihodFlag = 0;
$pfRow = $conn->query("SELECT COALESCE(prihod_flag,0) AS pf FROM typeop WHERE typeop_id = $typeop")->fetch_assoc();
if ($pfRow) $prihodFlag = (int)$pfRow['pf'];
if (!in_array($mode, ['new', 'edit', 'copy', 'delete'], true)) $mode = 'edit';

$errors = [];
$focusField = '';
$values = [
    'number'       => '',
    'date'         => '',
    'time'         => '',
    'client_id'    => 0,
    'store_id'     => 0,
    'store2_id'    => 0,
    'discount'     => '0.000',
    'sum_discount' => '0.00',
    'sum'          => '0.00',
    'sum_plat'     => '0.00',
    'sum_balans'   => '0.00',
    'pos'          => 0,
    'date_plat'    => '',
    'zakaz_num'    => '',
    'sotr_id'      => 0,
    'sotr2_id'     => 0,
    'note'         => '',
    'accept_flag'  => 0,
];

if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = $conn->prepare("SELECT number, date, time, client_id, store_id, store2_id, discount, sum_discount, sum, sum_plat, sum_balans, pos, date_plat, zakaz_num, sotr_id, sotr2_id, note, accept_flag FROM docum WHERE docum_id = ? AND typeop = ?");
    $stmt->bind_param('ii', $id, $typeop);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) {
        $errors[] = 'Запись не найдена.';
    } else {
        $values['number']       = (int)$r['number'] > 0 ? (string)(int)$r['number'] : '';
        $values['date']         = (string)$r['date'];
        $values['time']         = (string)$r['time'];
        $values['client_id']    = (int)$r['client_id'];
        $values['store_id']     = (int)$r['store_id'];
        $values['store2_id']    = (int)$r['store2_id'];
        $values['discount']     = (string)$r['discount'];
        $values['sum_discount'] = (string)$r['sum_discount'];
        $values['sum']          = (string)$r['sum'];
        $values['sum_plat']     = (string)$r['sum_plat'];
        $values['sum_balans']   = (string)$r['sum_balans'];
        $values['pos']          = (int)$r['pos'];
        $values['date_plat']    = (string)$r['date_plat'];
        $values['zakaz_num']    = (string)$r['zakaz_num'];
        $values['sotr_id']      = (int)$r['sotr_id'];
        $values['sotr2_id']     = (int)$r['sotr2_id'];
        $values['note']         = (string)$r['note'];
        $values['accept_flag']  = (int)$r['accept_flag'];
        $posRecalc = $conn->query("SELECT COUNT(*) AS cnt FROM docum2 WHERE docum_id = $id");
        if ($posRecalc) {
            $posRow = $posRecalc->fetch_assoc();
            $recalcPos = (int)$posRow['cnt'];
            if ($recalcPos !== $values['pos']) {
                $conn->query("UPDATE docum SET pos = $recalcPos WHERE docum_id = $id");
                $values['pos'] = $recalcPos;
            }
        }
    }
}

// ---- docum2 items ----
$docum2Data = [];
$docum2ColDefaults = docum2_columns_defaults();
$docum2ColConfig = [];
$docum2ColWidths = [];
$docum2DefaultWidths = ['product_name' => 'auto', 'quant' => '80px', 'price' => '90px', 'discount' => '80px', 'sum' => '100px', 'note' => '250px'];
$d2w = function($name) use ($docum2ColWidths, $docum2DefaultWidths) {
    return isset($docum2ColWidths[$name]) ? $docum2ColWidths[$name] . 'px' : ($docum2DefaultWidths[$name] ?? 'auto');
};
function fmt_qty($v) {
    $n = (float)str_replace(',', '.', $v);
    if ($n == 0) return '';
    $s = number_format($n, 3, '.', '');
    $s = rtrim(rtrim($s, '0'), '.');
    return $n == (int)$n ? (string)(int)$n : str_replace('.', ',', $s);
}
function fmt_price($v) {
    $n = (float)str_replace(',', '.', $v);
    if ($n == 0) return '';
    return number_format($n, 2, ',', '');
}
if ($id > 0) {
    $itemsRes = $conn->query("SELECT docum2_id AS id, product_id, code, product_name, quant, price, discount, sum, sum_discount, note FROM docum2 WHERE docum_id = $id ORDER BY docum2_id");
    if ($itemsRes) while ($ir = $itemsRes->fetch_assoc()) {
        $item = [
            'id' => (int)$ir['id'],
            'product_id' => (int)$ir['product_id'],
            'code' => (string)$ir['code'],
            'product_name' => (string)$ir['product_name'],
            'quant' => fmt_qty($ir['quant']),
            'price' => fmt_price($ir['price']),
            'discount' => fmt_price($ir['discount']),
            'sum' => fmt_price($ir['sum']),
            'sum_discount' => fmt_price($ir['sum_discount']),
            'note' => (string)$ir['note'],
        ];
        $docum2Data[] = $item;
    }
    $docum2ColWidths = load_columns_widths($conn, 'docum2');
    $d2w = function($name) use ($docum2ColWidths, $docum2DefaultWidths) {
        return isset($docum2ColWidths[$name]) ? $docum2ColWidths[$name] . 'px' : ($docum2DefaultWidths[$name] ?? 'auto');
    };
    $docum2ColConfig = load_columns_config($conn, 'docum2', $docum2ColDefaults);
}

// --- plat records for payment tab ---
$platList = [];
$zatIdForPlat = 0;
$zr = $conn->query("SELECT zat_id FROM zat WHERE name = 'Оплата продажи' LIMIT 1");
if ($zr && ($zrow = $zr->fetch_assoc())) $zatIdForPlat = (int)$zrow['zat_id'];
if (!$zatIdForPlat) {
    $zr2 = $conn->query("SELECT zat_id FROM zat WHERE name = 'Оплата' LIMIT 1");
    if ($zr2 && ($zrow2 = $zr2->fetch_assoc())) $zatIdForPlat = (int)$zrow2['zat_id'];
}
if ($id > 0 && $mode !== 'new') {
    $stmtP = $conn->prepare("SELECT p.plat_id, p.datetime, p.sum_in, p.sum_out, p.sum, p.out_flag, p.plat_type, p.doc_id, p.note, c.name AS client_name, z.name AS zat_name FROM plat p LEFT JOIN client c ON c.client_id = p.client_id LEFT JOIN zat z ON z.zat_id = p.zat_id WHERE p.doc_id = ? AND p.doc_type = ? ORDER BY p.plat_id DESC");
    if ($stmtP) {
        bind_auto($stmtP, [$id, $typeop]);
        $stmtP->execute();
        $platList = $stmtP->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtP->close();
    }
}
$docum2ColInitialJs = json_encode(array_map(function($c) {
    return ['name' => $c['name'], 'label' => $c['label'], 'visible' => !empty($c['visible'])];
}, $docum2ColConfig), JSON_UNESCAPED_UNICODE);
$docum2ColDefaultsJs = json_encode(array_map(function($c) {
    return ['name' => $c['name'], 'label' => $c['label'], 'visible' => true];
}, $docum2ColDefaults), JSON_UNESCAPED_UNICODE);
$productList = [];
$prs = $conn->query("SELECT product_id, product_name, article AS code, price_out AS price FROM product WHERE hide_flag = 0 ORDER BY product_name");
if ($prs) while ($pr = $prs->fetch_assoc()) {
    $productList[] = ['id' => (int)$pr['product_id'], 'name' => (string)$pr['product_name'], 'code' => (string)$pr['code'], 'price' => (string)$pr['price']];
}

$d2Columns = [
    ['key' => 'product_name', 'label' => 'Товар', 'type' => 'lookup', 'dbField' => 'product_id'],
    ['key' => 'quant',        'label' => 'Кол-во', 'align' => 'right'],
    ['key' => 'price',        'label' => 'Цена', 'align' => 'right'],
];
if (!in_array($typeop, [127, 100], true)) {
    $d2Columns[] = ['key' => 'discount', 'label' => 'Скидка', 'align' => 'right'];
}
$d2Columns[] = ['key' => 'sum', 'label' => 'Сумма', 'align' => 'right'];
$d2Columns[] = ['key' => 'note', 'label' => 'Примечание'];

$d2ColWidths = ['product_name' => 'auto', 'quant' => '80px', 'price' => '90px', 'discount' => '80px', 'sum' => '100px', 'note' => '250px'];
foreach ($docum2ColWidths as $name => $w) {
    if (isset($d2ColWidths[$name])) $d2ColWidths[$name] = $w . 'px';
}

$d2Table = new EmbeddedTable([
    'prefix'           => 'd2',
    'columns'          => $d2Columns,
    'colWidths'        => $d2ColWidths,
    'saveUrl'          => 'docum2_field_save.php',
    'parentField'      => 'docum_id',
    'childFormUrl'     => 'docum2_form.php',
    'childFormName'    => 'docum2',
    'hasExport'        => true,
    'hasPrint'         => true,
    'hasSearch'        => true,
    'lookupData' => [
        'product_name' => $productList,
    ],
    'totalsCallback' => 'applyDocum2Totals',
]);

$d2RenderData = array_map(function($item) {
    $data = [
        'id' => $item['id'],
        'product_id' => $item['product_id'],
        'product_name' => $item['product_name'],
        'quant' => $item['quant'],
        'price' => $item['price'],
        'sum' => $item['sum'],
        'note' => $item['note'],
    ];
    if (!in_array($GLOBALS['typeop'], [127, 100], true)) {
        $data['discount'] = $item['discount'];
    }
    return $data;
}, $docum2Data);

$platColumns = [
    ['key' => 'datetime',    'label' => 'Дата/Время', 'readonly' => true],
    ['key' => 'client_name', 'label' => 'Контрагент', 'readonly' => true],
    ['key' => 'zat_name',    'label' => 'Вид операции', 'readonly' => true],
    ['key' => 'sum',         'label' => 'Сумма', 'align' => 'right'],
    ['key' => 'plat_type',   'label' => 'Вид платежа'],
    ['key' => 'note',        'label' => 'Примечание'],
];

$platColWidths = ['datetime' => '140px', 'client_name' => 'auto', 'zat_name' => '150px', 'sum' => '100px', 'plat_type' => '100px', 'note' => 'auto'];

$platTable = new EmbeddedTable([
    'prefix'           => 'plat',
    'columns'          => $platColumns,
    'colWidths'        => $platColWidths,
    'saveUrl'          => 'plat_field_save.php',
    'parentField'      => 'doc_id',
    'childFormUrl'     => 'plat_form.php?doc_type=' . $typeop . '&client_id=' . $values['client_id'] . '&sotr_id=' . $CurSotrID . '&zat_id=' . $zatIdForPlat . '&return_url=' . urlencode('sale_form.php?mode=edit&id=' . (int)$id . '&typeop=' . $typeop),
    'childFormName'    => 'plat',
    'hasExport'        => false,
    'hasPrint'         => false,
    'hasSearch'        => true,
    'totalsCallback' => 'applyDocum2Totals',
]);

$platListData = array_map(function($p) {
    $dt = strtotime((string)$p['datetime']);
    return [
        'id' => (int)$p['plat_id'],
        'datetime' => $dt ? date('d.m.Y H:i', $dt) : '-',
        'client_name' => (string)($p['client_name'] ?? '-'),
        'zat_name' => (string)($p['zat_name'] ?? '-'),
        'sum' => (float)$p['sum'],
        'plat_type' => (string)$p['plat_type'],
        'out_flag' => (int)$p['out_flag'],
        'note' => (string)$p['note'],
    ];
}, $platList);

if ($mode === 'new' || $mode === 'copy') {
    $nr = $conn->query("SELECT COALESCE(MAX(number), 0) + 1 AS next_num FROM docum WHERE typeop = $typeop");
    if ($nr && ($nrow = $nr->fetch_assoc())) $values['number'] = (int)$nrow['next_num'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['number']       = (int)($_POST['number'] ?? 0);
    $values['date']         = trim((string)($_POST['date'] ?? ''));
    $values['time']         = trim((string)($_POST['time'] ?? ''));
    $values['client_id']    = (int)($_POST['client_id'] ?? 0);
    $values['store_id']     = (int)($_POST['store_id'] ?? 0);
    $values['store2_id']    = (int)($_POST['store2_id'] ?? 0);
    $values['discount']     = str_replace(' ', '', str_replace(',', '.', trim((string)($_POST['discount'] ?? '0'))));
    $values['sum_discount'] = str_replace(' ', '', str_replace(',', '.', trim((string)($_POST['sum_discount'] ?? '0'))));
    $values['sum']          = str_replace(' ', '', str_replace(',', '.', trim((string)($_POST['sum'] ?? '0'))));
    $values['sum_plat']     = str_replace(' ', '', str_replace(',', '.', trim((string)($_POST['sum_plat'] ?? '0'))));
    $values['sum_balans']   = str_replace(' ', '', str_replace(',', '.', trim((string)($_POST['sum_balans'] ?? '0'))));
    $values['date_plat']    = trim((string)($_POST['date_plat'] ?? ''));
    $values['zakaz_num']    = trim((string)($_POST['zakaz_num'] ?? ''));
    $values['sotr_id']      = (int)($_POST['sotr_id'] ?? 0);
    $values['sotr2_id']     = (int)($_POST['sotr2_id'] ?? 0);
    $values['pos']          = (int)($_POST['pos'] ?? 0);
    $values['note']         = trim((string)($_POST['note'] ?? ''));

    if ($values['date'] === '') {
        $errors[] = 'Поле «Дата» обязательно для заполнения.';
        $focusField = 'sale-date';
    }
    if ($values['client_id'] <= 0 && !in_array($typeop, [100, 127])) {
        $errors[] = 'Поле «Контрагент» обязательно для заполнения.';
        $focusField = $focusField ?: 'client-id';
    }
    if ($values['store_id'] <= 0) {
        $errors[] = 'Поле «Участок» обязательно для заполнения.';
        $focusField = $focusField ?: 'store-id';
    }
    if ($typeop === 100 && $values['store2_id'] <= 0) {
        $errors[] = 'Поле «Куда» обязательно для заполнения.';
        $focusField = $focusField ?: 'store2-id';
    }

    if (empty($errors)) {
        $numVal = $values['number'] > 0 ? $values['number'] : 0;
        $disc = (float)$values['discount'];
        $sumDisc = (float)$values['sum_discount'];
        $sumVal = (float)$values['sum'];
        $sumPlat = (float)$values['sum_plat'];
        $sumBalans = (float)$values['sum_balans'];

        if ($mode === 'delete') {
            $stmt = $conn->prepare("DELETE FROM docum WHERE docum_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $conn->query("DELETE FROM marks WHERE tbl = 'sale' AND row_id = " . (int)$id);
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $id, 'name' => '#' . $id]);
                exit;
            } else {
                header('Location: sale.php?typeop=' . $typeop);
                exit;
            }
        }

        $maxRetries = 10;
        $saved = false;

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            if ($mode === 'new' || $mode === 'copy') {
                $stmt = $conn->prepare("INSERT INTO docum (typeop, number, date, time, client_id, store_id, store2_id, discount, sum_discount, sum, sum_plat, sum_balans, pos, date_plat, zakaz_num, sotr_id, sotr2_id, note, accept_flag, voz_flag) VALUES ($typeop, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)");
                bind_auto($stmt, [$numVal, $values['date'], $values['time'], $values['client_id'], $values['store_id'], $values['store2_id'], $disc, $sumDisc, $sumVal, $sumPlat, $sumBalans, $values['pos'], $values['date_plat'], $values['zakaz_num'], $values['sotr_id'], $values['sotr2_id'], $values['note']]);
            } else {
                $stmt = $conn->prepare("UPDATE docum SET number = ?, date = ?, time = ?, client_id = ?, store_id = ?, store2_id = ?, discount = ?, sum_discount = ?, sum = ?, sum_plat = ?, sum_balans = ?, pos = ?, date_plat = ?, zakaz_num = ?, sotr_id = ?, sotr2_id = ?, note = ? WHERE docum_id = ?");
                bind_auto($stmt, [$numVal, $values['date'], $values['time'], $values['client_id'], $values['store_id'], $values['store2_id'], $disc, $sumDisc, $sumVal, $sumPlat, $sumBalans, $values['pos'], $values['date_plat'], $values['zakaz_num'], $values['sotr_id'], $values['sotr2_id'], $values['note'], $id]);
            }

            if ($stmt->execute()) {
                $saved = true;
                break;
            }

            if ($stmt->errno === 1062) {
                $stmt->close();
                $nr = $conn->query("SELECT COALESCE(MAX(number),0)+1 AS next_num FROM docum WHERE typeop = $typeop");
                if ($nr && ($nrow = $nr->fetch_assoc())) {
                    $values['number'] = $numVal = (int)$nrow['next_num'];
                }
                continue;
            }

            $errors[] = 'Ошибка БД: ' . $stmt->error;
            $stmt->close();
            goto render;
        }

        if (!$saved) {
            $errors[] = 'Не удалось сохранить: превышено количество попыток.';
            $stmt->close();
            goto render;
        }

        $newId = ($mode === 'new' || $mode === 'copy') ? $conn->insert_id : $id;
        $stmt->close();

        if (!empty($_POST['auto_save'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true]);
            exit;
        }
        if ($isAjax) {
            $pageOfNew = 0;
            if (defined('PAGE_SIZE') && PAGE_SIZE > 0 && ($mode === 'new' || $mode === 'copy') && $newId > 0) {
                $pageOfNew = computePageOfNew($conn, 'docum', 'docum_id', 'number', 'desc', $numVal, $newId, "typeop = $typeop");
            }
            $resp = ['ok' => true, 'mode' => $mode, 'id' => $newId, 'name' => '#' . $newId, 'page' => $pageOfNew];
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($resp);
            exit;
        }
        header('Location: sale.php?typeop=' . $typeop);
        exit;
    }
}

render:
$clientList = [];
$crs = $conn->query("SELECT client_id, name FROM client ORDER BY name");
if ($crs) while ($cr = $crs->fetch_assoc()) $clientList[] = ['id' => (int)$cr['client_id'], 'name' => (string)$cr['name']];

$storeList = [];
$srs = $conn->query("SELECT store_id, name FROM store ORDER BY name");
if ($srs) while ($sr = $srs->fetch_assoc()) $storeList[] = ['id' => (int)$sr['store_id'], 'name' => (string)$sr['name']];

$sotrList = [];
$sotrs = $conn->query("SELECT sotr_id, doc_name AS name FROM sotr ORDER BY doc_name");
if ($sotrs) while ($sr = $sotrs->fetch_assoc()) $sotrList[] = ['id' => (int)$sr['sotr_id'], 'name' => (string)$sr['name']];

$currentClientName = '';
foreach ($clientList as $c) { if ($c['id'] === $values['client_id']) { $currentClientName = $c['name']; break; } }
$currentStoreName = '';
foreach ($storeList as $st) { if ($st['id'] === $values['store_id']) { $currentStoreName = $st['name']; break; } }
$currentSotrName = '';
foreach ($sotrList as $so) { if ($so['id'] === $values['sotr_id']) { $currentSotrName = $so['name']; break; } }
$currentSotr2Name = '';
foreach ($sotrList as $so) { if ($so['id'] === $values['sotr2_id']) { $currentSotr2Name = $so['name']; break; } }
$currentStore2Name = '';
foreach ($storeList as $st) { if ($st['id'] === $values['store2_id']) { $currentStore2Name = $st['name']; break; } }

$DOC_LABELS = [10 => 'Возврат от покупателя', 20 => 'Приход', 100 => 'Внутреннее перемещение', 110 => 'Возврат поставщику', 120 => 'Продажа', 127 => 'Списание'];
$DOC_ICONS  = [10 => 'sale.png', 20 => 'prihod.png', 100 => 'move.png', 110 => 'prihod.png', 120 => 'sale.png', 127 => 'spisan.png'];
$docLabel = $DOC_LABELS[$typeop];
$pageIcon = 'img/' . $DOC_ICONS[$typeop];
$titleNum = $values['number'] > 0 ? '№ ' . $values['number'] : '(новый)';
if ($mode === 'edit') {
    $pageTitle = $docLabel . ' ' . $titleNum . ' (' . $currentClientName . ')';
} elseif ($mode === 'delete') {
    $pageTitle = $docLabel . ' ' . $titleNum . ' (удаление)';
} elseif ($mode === 'new') {
    $pageTitle = $docLabel . ': новый';
} elseif ($mode === 'copy') {
    $pageTitle = $docLabel . ' ' . $titleNum . ' (копия)';
} else {
    $pageTitle = $docLabel;
}
$isReadonly = ($mode === 'delete');
$isApproved = !$isReadonly && $values['accept_flag'] === 1;
$ro = $isReadonly || $isApproved;

function dv($v) { return fmt_price($v); }

ob_start();
?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><img src="<?= h($pageIcon) ?>" alt="" /> <?= h($pageTitle) ?></h2>
<form class="form<?= $mode === 'delete' ? ' form--delete' : '' ?>" method="post" action="sale_form.php" autocomplete="off" data-form-modal>
<?= render_input('hidden', 'mode', $mode) ?>
<?= render_input('hidden', 'id', $id) ?>
<?= render_input('hidden', 'typeop', $typeop) ?>
<?= render_input('hidden', 'cli_name', '', ['id' => 'cli-name']) ?>
<?= render_input('hidden', 'store_name', '', ['id' => 'store-name']) ?>
<?= render_input('hidden', 'store2_name', '', ['id' => 'store2-name']) ?>
<?= render_input('hidden', 'sotr_name', '', ['id' => 'sotr-name']) ?>
<?= render_input('hidden', 'sotr2_name', '', ['id' => 'sotr2-name']) ?>

<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>

<div class="tab-container">
  <div class="tab-headers">
    <div class="tab-header active" data-tab-index="0">Параметры</div>
    <div class="tab-header" data-tab-index="1">Товары</div>
    <?php if (!in_array($typeop, [100, 127])): ?>
    <div class="tab-header" data-tab-index="2">Оплата</div>
    <?php endif; ?>
  </div>

  <div class="tab-pane active" data-tab-index="0">
    <table class="form-table">
      <tr>
        <td class="form-label">Документ №</td>
        <td class="form-label">Дата<span class="required">*</span></td>
        <td class="form-label">&nbsp;</td>
      </tr>
      <tr>
        <td><?= render_input('number', 'number', $values['number'] > 0 ? $values['number'] : '', [
                'id' => 'sale-number',
                'readonly' => $ro,
                'tabindex' => $ro ? '-1' : null,
                'style' => 'max-width:120px',
            ]) ?></td>
        <td><div style="display:flex;gap:10px"><?= render_input('date', 'date', $values['date'] ?: date('Y-m-d'), [
                'id' => 'sale-date',
                'required' => !$ro,
                'readonly' => $ro,
                'tabindex' => $ro ? '-1' : null,
                'style' => 'max-width:128px',
            ]) ?><?= render_input('time', 'time', $values['time'] ?: date('H:i'), [
                'id' => 'sale-time',
                'readonly' => $ro,
                'tabindex' => $ro ? '-1' : null,
                'style' => 'max-width:120px',
            ]) ?></div></td>
        <td>&nbsp;</td>
      </tr>
      <?php if ($typeop === 100): ?>
      <tr>
        <td class="form-label">Откуда<span class="required">*</span></td>
        <td class="form-label">Куда<span class="required">*</span></td>
        <td class="form-label">&nbsp;</td>
      </tr>
      <tr>
        <td><?= render_lookup('store', 'store_id', $values['store_id'], $currentStoreName,
                h(json_encode($storeList, JSON_UNESCAPED_UNICODE)),
                'store_form.php?mode=new', $ro, ['id' => 'store-id', 'data-name-input' => 'store-name']) ?></td>
        <td><?= render_lookup('store', 'store2_id', $values['store2_id'], $currentStore2Name,
                h(json_encode($storeList, JSON_UNESCAPED_UNICODE)),
                'store_form.php?mode=new', $ro, ['id' => 'store2-id', 'data-name-input' => 'store2-name']) ?></td>
        <td>&nbsp;</td>
      </tr>
      <?php elseif ($typeop !== 127): ?>
      <tr>
        <td class="form-label">Контрагент<span class="required">*</span></td>
        <td class="form-label">Участок<span class="required">*</span></td>
        <td class="form-label">&nbsp;</td>
      </tr>
      <tr>
        <td><?= render_lookup('client', 'client_id', $values['client_id'], $currentClientName,
                h(json_encode($clientList, JSON_UNESCAPED_UNICODE)),
                'client_form.php?mode=new', $ro, ['id' => 'client-id', 'data-name-input' => 'cli-name']) ?></td>
        <td><?= render_lookup('store', 'store_id', $values['store_id'], $currentStoreName,
                h(json_encode($storeList, JSON_UNESCAPED_UNICODE)),
                'store_form.php?mode=new', $ro, ['id' => 'store-id', 'data-name-input' => 'store-name']) ?></td>
        <td>&nbsp;</td>
      </tr>
      <?php else: ?>
      <tr>
        <td class="form-label">Участок<span class="required">*</span></td>
        <td class="form-label">&nbsp;</td>
        <td class="form-label">&nbsp;</td>
      </tr>
      <tr>
        <td><?= render_lookup('store', 'store_id', $values['store_id'], $currentStoreName,
                h(json_encode($storeList, JSON_UNESCAPED_UNICODE)),
                'store_form.php?mode=new', $ro, ['id' => 'store-id', 'data-name-input' => 'store-name']) ?></td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
      </tr>
      <?php endif; ?>
      <?php if (!in_array($typeop, [100, 127])): ?>
      <tr>
        <td class="form-label">Скидка %</td>
        <td class="form-label">Сумма скидки</td>
        <td class="form-label">&nbsp;</td>
      </tr>
      <tr>
        <td><div style="display:flex;gap:10px;align-items:center"><?= render_input('text', 'discount', dv($values['discount']), [
                'id' => 'sale-discount',
                'readonly' => $ro,
                'tabindex' => $ro ? '-1' : null,
                'style' => 'max-width:100px;flex:0 0 auto',
            ]) ?><?php if (!$ro): ?><?= render_btn_icon('img/make.png', ['id'=>'apply-discount-btn','type'=>'button','title'=>'Установить скидку','class'=>'icon-btn']) ?><?php endif; ?></div></td>
        <td><?= render_input('text', 'sum_discount', dv($values['sum_discount']), [
                'id' => 'sale-sum-discount',
                'readonly' => true,
                'tabindex' => '-1',
                'style' => 'max-width:120px',
            ]) ?></td>
        <td>&nbsp;</td>
      </tr>
      <?php endif; ?>
      <?php if ($typeop !== 100): ?>
      <tr>
        <td class="form-label">Итоговая сумма</td>
        <td class="form-label">Позиций</td>
        <td class="form-label">&nbsp;</td>
      </tr>
      <tr>
        <td><?= render_input('text', 'sum', dv($values['sum']), [
                'id' => 'sale-sum',
                'readonly' => true,
                'tabindex' => '-1',
                'style' => 'max-width:120px;font-weight:bold',
            ]) ?></td>
        <td><?= render_input('number', 'pos', $values['pos'] > 0 ? (string)$values['pos'] : '', [
                'id' => 'sale-pos',
                'readonly' => true,
                'tabindex' => '-1',
                'style' => 'max-width:80px',
            ]) ?></td>
        <td>&nbsp;</td>
      </tr>
      <?php else: ?>
      <tr>
        <td class="form-label">Итоговая сумма</td>
        <td class="form-label">Позиций</td>
        <td class="form-label">&nbsp;</td>
      </tr>
      <tr>
        <td><?= render_input('text', 'sum', dv($values['sum']), [
                'id' => 'sale-sum',
                'readonly' => true,
                'tabindex' => '-1',
                'style' => 'max-width:120px;font-weight:bold',
            ]) ?></td>
        <td><?= render_input('number', 'pos', $values['pos'] > 0 ? (string)$values['pos'] : '', [
                'id' => 'sale-pos',
                'readonly' => true,
                'tabindex' => '-1',
                'style' => 'max-width:80px',
            ]) ?></td>
        <td>&nbsp;</td>
      </tr>
      <tr>
        <td class="form-label">По документу №</td>
        <td class="form-label">&nbsp;</td>
        <td class="form-label">&nbsp;</td>
      </tr>
      <tr>
        <td><?= render_input('text', 'zakaz_num', $values['zakaz_num'] === '0' ? '' : $values['zakaz_num'], [
                'id' => 'sale-zakaz-num',
                'readonly' => $ro,
                'tabindex' => $ro ? '-1' : null,
                'style' => 'max-width:120px',
            ]) ?></td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
      </tr>
      <?php endif; ?>
      <?php if (!in_array($typeop, [100, 127])): ?>
      <tr>
        <td class="form-label">Оплачено</td>
        <td class="form-label">Дата оплаты</td>
        <td class="form-label">&nbsp;</td>
      </tr>
      <tr>
        <td><?= render_input('text', 'sum_plat', dv($values['sum_plat']), [
                'id' => 'sale-sum-plat',
                'readonly' => true,
                'tabindex' => '-1',
                'style' => 'max-width:120px;font-weight:bold' . ($prihodFlag ? (((float)$values['sum'] > -(float)$values['sum_plat']) ? ';color:#e57373' : '') : (((float)$values['sum_plat'] < (float)$values['sum']) ? ';color:#e57373' : '')),
            ]) ?></td>
        <td><?= render_input('date', 'date_plat', $values['date_plat'], [
                'id' => 'sale-date-plat',
                'readonly' => $ro,
                'tabindex' => $ro ? '-1' : null,
                'style' => 'max-width:128px',
            ]) ?></td>
        <td>&nbsp;</td>
      </tr>
      <?php endif; ?>
      <?php if (!in_array($typeop, [100, 127])): ?>
      <tr>
        <td class="form-label">По документу №</td>
        <td class="form-label">&nbsp;</td>
        <td class="form-label">&nbsp;</td>
      </tr>
      <tr>
        <td><?= render_input('text', 'zakaz_num', $values['zakaz_num'] === '0' ? '' : $values['zakaz_num'], [
                'id' => 'sale-zakaz-num',
                'readonly' => $ro,
                'tabindex' => $ro ? '-1' : null,
                'style' => 'max-width:120px',
            ]) ?></td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
      </tr>
      <?php endif; ?>
      <tr>
        <td class="form-label">Сотрудник</td>
        <td class="form-label">Принял</td>
        <td class="form-label">&nbsp;</td>
      </tr>
      <tr>
        <td><?= render_lookup('sotr', 'sotr_id', $values['sotr_id'], $currentSotrName,
                h(json_encode($sotrList, JSON_UNESCAPED_UNICODE)),
                'sotr_form.php?mode=new', $ro, ['id' => 'sotr-id', 'data-name-input' => 'sotr-name']) ?></td>
        <td><?= render_lookup('sotr', 'sotr2_id', $values['sotr2_id'], $currentSotr2Name,
                h(json_encode($sotrList, JSON_UNESCAPED_UNICODE)),
                'sotr_form.php?mode=new', $ro, ['id' => 'sotr2-id', 'data-name-input' => 'sotr2-name']) ?></td>
        <td>&nbsp;</td>
      </tr>
      <tr>
        <td class="form-label" colspan="3">Примечание</td>
      </tr>
      <tr>
        <td colspan="3"><?= render_input('text', 'note', $values['note'], [
                'id' => 'sale-note',
                'class' => 'full',
                'readonly' => $isReadonly,
                'tabindex' => $isReadonly ? '-1' : null,
            ]) ?></td>
      </tr>
    </table>
  </div>

  <div class="tab-pane" data-tab-index="1">
<?php if ($id > 0 && ($mode !== 'new')): ?>
    <?= $d2Table->render($d2RenderData) ?>
<?php elseif ($mode === 'new'): ?>
    <div style="padding:40px 20px;text-align:center;color:var(--muted);font-size:14px">Сохраните продажу, чтобы добавить товары</div>
<?php endif; ?>
  </div>

  <div class="tab-pane" data-tab-index="2">
<?php if ($id > 0 && $mode !== 'new'): ?>
    <?= $platTable->render($platListData) ?>
<?php else: ?>
    <div style="padding:40px 20px;text-align:center;color:var(--muted);font-size:14px">Сохраните продажу, чтобы добавить платежи</div>
<?php endif; ?>
  </div>
</div>

<?= render_form_note() ?>

<?= render_form_actions(
    $mode === 'delete'
        ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'sale.php?typeop=' . $typeop, ['class'=>'btn-secondary'])]
        : [render_btn_icon_text('img/accept.png', 'Применить', ['type'=>'submit','name'=>'action','value'=>'apply','formnovalidate'=>true,'class'=>'btn-secondary']),
           render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'sale.php?typeop=' . $typeop, ['class'=>'btn-secondary'])]
) ?>
</form>
<style>
    .lookup-wrap { position: relative; max-width: 430px; }
    .form-modal { max-width: 990px; }
    .page--form { max-width: 990px; }
    .form { max-width: 990px; }
    .tab-container { margin-bottom: 16px; width: 100%; }
    .tab-headers { display: flex; border-bottom: 2px solid var(--accent); margin-bottom: 12px; }
    .tab-header { padding: 8px 20px; cursor: pointer; font-size: 14px; font-weight: bold; color: var(--muted); border: 1px solid transparent; border-bottom: none; border-radius: 4px 4px 0 0; user-select: none; }
    .tab-header.active { color: #fff; background: var(--accent); border-color: var(--accent); }
    .tab-header:hover:not(.active) { color: #fff; background: var(--btn-hover); }
    .tab-pane { display: none; width: 100%; }
    .tab-pane.active { display: block; width: 100%; }
    .table-wrap { width: 100%; box-sizing: border-box; }
    .form-table { width: 100%; border-collapse: collapse; }
    .form-table td { vertical-align: top; padding-bottom: 6px; padding-right: 28px; }
    .form-table td:last-child { padding-right: 0; }
    .form-table td.form-label { font-size: 12px; color: var(--muted); padding-bottom: 2px; }
    input.full, textarea.full { width: 100%; box-sizing: border-box; }
</style>
<?php
$formHtml = ob_get_clean();

if ($isAjax) {
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
  <script>
  // Standalone page init (not modal). Modal init is in sale.php.
  (function() {
    if (window.__openFormModal) return;
    <?php $d2Table->renderScripts(); ?>
    initD2Table();
  })();
  </script>
  <script>
  (function() {
    if (window.__openFormModal) return;
    <?php $platTable->renderScripts(); ?>
    initPlatTable();
  })();
  </script>
</body>
</html>
