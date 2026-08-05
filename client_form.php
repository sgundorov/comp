<?php
ob_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/table-helper.php';
require_once __DIR__ . '/lib/TablePage.php';

$isAjax = (
    (string)($_GET['ajax'] ?? '') === '1' ||
    (string)($_POST['ajax'] ?? '') === '1' ||
    (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

$mode = (string)($_GET['mode'] ?? $_POST['mode'] ?? 'edit');
$id   = (int)($_GET['id']   ?? $_POST['id']   ?? 0);
if (!in_array($mode, ['new', 'edit', 'copy', 'delete'], true)) {
    $mode = 'edit';
}

$errors = [];
$focusField = '';
$appSettings = load_app_settings($conn);
$accessFlags = [];
$RegcodeFlag = (int)($appSettings['regcode_flag'] ?? 0);
$values = [
    'last_name'      => '',
    'first_name'     => '',
    'name'           => '',
    'title'          => '',
    'cli_categ_id'   => 0,
    'supplier_flag'  => '0',
    'problem_flag'   => '0',
    'juridical_flag' => '0',
    'hide_flag'      => '0',
    'phone'          => '',
    'cphone'         => '',
    'email'          => '',
    'site'           => '',
    'city_id'        => 0,
    'country_id'     => 0,
    'postindex'      => '',
    'address_jur'    => '',
    'address'        => '',
    'pasport'        => '',
    'pasp_date'      => '',
    'pasp_vydan'     => '',
    'birthday'       => '',
    'promo_id'       => 0,
    'inn'            => '',
    'kpp'            => '',
    'ogrn'           => '',
    'jur_name'       => '',
    'director'       => '',
    'glavbuh'        => '',
    'bank'           => '',
    'bik'            => '',
    'schet'          => '',
    'kschet'         => '',
    'okonh'          => '',
    'okpo'           => '',
    'disc_goods'     => '',
    'dop1'           => '',
    'note'           => '',
];

try {
    $accessFlags = get_access_flags($conn, 'Client');
    ensure_client_tag_table($conn);

if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = $conn->prepare("SELECT c.last_name, c.first_name, c.name, c.title,
        c.cli_categ_id, c.supplier_flag, c.problem_flag, c.juridical_flag, c.hide_flag,
        c.phone, c.cphone, c.email, c.site,
        c.city_id, c.country_id, c.postindex, c.address_jur, c.address,
        c.pasport, c.pasp_date, c.pasp_vydan, c.birthday,
        c.promo_id, c.inn, c.kpp, c.ogrn, c.jur_name, c.director, c.glavbuh,
        c.bank, c.bik, c.schet, c.kschet, c.okonh, c.okpo,
        c.disc_goods, c.dop1, c.note,
        (SELECT GROUP_CONCAT(ctg.tag_id SEPARATOR ',') FROM client_tag ctg WHERE ctg.client_id = c.client_id) AS tag_ids
        FROM client c WHERE c.client_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) {
        $errors[] = 'Запись не найдена.';
    } else {
        $origName = trim((string)$r['last_name'] . ' ' . (string)$r['first_name']);
        $values['last_name'] = ($mode === 'copy') ? '' : (string)$r['last_name'];
        $values['first_name'] = ($mode === 'copy') ? '' : (string)$r['first_name'];
        $values['name'] = ($mode === 'copy') ? '' : (string)$r['name'];
        $values['title'] = (string)$r['title'];
        $values['cli_categ_id'] = (int)$r['cli_categ_id'];
        $values['supplier_flag'] = (string)$r['supplier_flag'];
        $values['problem_flag'] = (string)$r['problem_flag'];
        $values['juridical_flag'] = (string)$r['juridical_flag'];
        $values['hide_flag'] = (string)$r['hide_flag'];
        $values['phone'] = (string)$r['phone'];
        $values['cphone'] = (string)$r['cphone'];
        $values['email'] = (string)$r['email'];
        $values['site'] = (string)$r['site'];
        $values['city_id'] = (int)$r['city_id'];
        $values['country_id'] = (int)$r['country_id'];
        $values['postindex'] = (string)$r['postindex'];
        $values['address_jur'] = (string)$r['address_jur'];
        $values['address'] = (string)$r['address'];
        $values['pasport'] = (string)$r['pasport'];
        $values['pasp_date'] = (string)$r['pasp_date'];
        $values['pasp_vydan'] = (string)$r['pasp_vydan'];
        $values['birthday'] = (string)$r['birthday'];
        $values['promo_id'] = (int)$r['promo_id'];
        $values['inn'] = (string)$r['inn'];
        $values['kpp'] = (string)$r['kpp'];
        $values['ogrn'] = (string)$r['ogrn'];
        $values['jur_name'] = (string)$r['jur_name'];
        $values['director'] = (string)$r['director'];
        $values['glavbuh'] = (string)$r['glavbuh'];
        $values['bank'] = (string)$r['bank'];
        $values['bik'] = (string)$r['bik'];
        $values['schet'] = (string)$r['schet'];
        $values['kschet'] = (string)$r['kschet'];
        $values['okonh'] = (string)$r['okonh'];
        $values['okpo'] = (string)$r['okpo'];
        $values['disc_goods'] = (string)$r['disc_goods'];
        $values['dop1'] = (string)$r['dop1'];
        $values['note'] = (string)$r['note'];
        $tagIdsStr = (string)$r['tag_ids'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['last_name'] = (string)($_POST['last_name'] ?? '');
    $values['first_name'] = (string)($_POST['first_name'] ?? '');
    $values['title'] = (string)($_POST['title'] ?? '');
    $values['cli_categ_id'] = (int)($_POST['cli_categ_id'] ?? 0);
    $values['supplier_flag'] = (string)($_POST['supplier_flag'] ?? '0');
    $values['problem_flag'] = (string)($_POST['problem_flag'] ?? '0');
    $values['hide_flag'] = (string)($_POST['hide_flag'] ?? '0');
    $values['phone'] = (string)($_POST['phone'] ?? '');
    $values['cphone'] = (string)($_POST['cphone'] ?? '');
    $values['email'] = (string)($_POST['email'] ?? '');
    $values['site'] = (string)($_POST['site'] ?? '');
    $values['city_id'] = (int)($_POST['city_id'] ?? 0);
    $values['country_id'] = (int)($_POST['country_id'] ?? 0);
    $values['postindex'] = (string)($_POST['postindex'] ?? '');
    $values['address_jur'] = (string)($_POST['address_jur'] ?? '');
    $values['address'] = (string)($_POST['address'] ?? '');
    $values['pasport'] = (string)($_POST['pasport'] ?? '');
    $values['pasp_date'] = (string)($_POST['pasp_date'] ?? '');
    $values['pasp_vydan'] = (string)($_POST['pasp_vydan'] ?? '');
    $values['birthday'] = (string)($_POST['birthday'] ?? '');
    $values['promo_id'] = (int)($_POST['promo_id'] ?? 0);
    $values['inn'] = (string)($_POST['inn'] ?? '');
    $values['kpp'] = (string)($_POST['kpp'] ?? '');
    $values['ogrn'] = (string)($_POST['ogrn'] ?? '');
    $values['jur_name'] = (string)($_POST['jur_name'] ?? '');
    $values['juridical_flag'] = $values['jur_name'] !== '' ? '1' : '0';
    $values['director'] = (string)($_POST['director'] ?? '');
    $values['glavbuh'] = (string)($_POST['glavbuh'] ?? '');
    $values['bank'] = (string)($_POST['bank'] ?? '');
    $values['bik'] = (string)($_POST['bik'] ?? '');
    $values['schet'] = (string)($_POST['schet'] ?? '');
    $values['kschet'] = (string)($_POST['kschet'] ?? '');
    $values['okonh'] = (string)($_POST['okonh'] ?? '');
    $values['okpo'] = (string)($_POST['okpo'] ?? '');
    $discGoods = (string)($_POST['disc_goods'] ?? '');
$values['disc_goods'] = ($discGoods === '') ? '0' : $discGoods;
    $values['dop1'] = (string)($_POST['dop1'] ?? '');
    $values['note'] = (string)($_POST['note'] ?? '');
    $tagIdsStr = (string)($_POST['tag_ids'] ?? '');

    if ($values['jur_name'] !== '') {
        $values['name'] = trim($values['jur_name']);
    } else {
        $values['name'] = trim(($values['last_name'] ?? '') . ' ' . ($values['first_name'] ?? ''));
    }
    if ($mode !== 'delete' && $values['name'] === '') {
        $errors[] = 'Введите название организации или ИНН';
        $focusField = 'jur_name';
    }

    if (empty($errors)) {
        if ($mode === 'delete') {
            $stmt = $conn->prepare("DELETE FROM client WHERE client_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $conn->query("DELETE FROM marks WHERE tbl = 'client' AND row_id = " . (int)$id);
            $conn->query("DELETE FROM client_tag WHERE client_id = " . (int)$id);
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $id]);
                exit;
            }
            header('Location: client.php');
            exit;
        }
        if ($mode === 'new' || $mode === 'copy') {
            $stmt = $conn->prepare("INSERT INTO client (last_name, first_name, title, name, cli_categ_id, supplier_flag, problem_flag, juridical_flag, hide_flag, phone, cphone, email, site, city_id, country_id, postindex, address_jur, address, pasport, pasp_date, pasp_vydan, birthday, promo_id, inn, kpp, ogrn, jur_name, director, glavbuh, bank, bik, schet, kschet, okonh, okpo, disc_goods, dop1, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            bind_auto($stmt, [$values['last_name'], $values['first_name'], $values['title'], $values['name'],
                $values['cli_categ_id'], $values['supplier_flag'], $values['problem_flag'], $values['juridical_flag'], $values['hide_flag'],
                $values['phone'], $values['cphone'], $values['email'], $values['site'], $values['city_id'], $values['country_id'], $values['postindex'],
                $values['address_jur'], $values['address'], $values['pasport'], $values['pasp_date'], $values['pasp_vydan'], $values['birthday'],
                $values['promo_id'], $values['inn'], $values['kpp'], $values['ogrn'], $values['jur_name'], $values['director'], $values['glavbuh'],
                $values['bank'], $values['bik'], $values['schet'], $values['kschet'], $values['okonh'], $values['okpo'],
                $values['disc_goods'], $values['dop1'], $values['note']]);
            if (!$stmt->execute()) {
                $errors[] = 'Ошибка БД: ' . $stmt->error;
            }
            $newId = (int)$conn->insert_id;
            $stmt->close();
            $pageOfNew = computePageOfNew($conn, 'client', 'client_id', 'id', 'desc', $newId, $newId, '');
        }
        if ($mode === 'edit') {
            $stmt = $conn->prepare("UPDATE client SET last_name = ?, first_name = ?, title = ?, name = ?,
                cli_categ_id = ?, supplier_flag = ?, problem_flag = ?, juridical_flag = ?, hide_flag = ?,
                phone = ?, cphone = ?, email = ?, site = ?, city_id = ?, country_id = ?, postindex = ?,
                address_jur = ?, address = ?, pasport = ?, pasp_date = ?, pasp_vydan = ?, birthday = ?,
                promo_id = ?, inn = ?, kpp = ?, ogrn = ?, jur_name = ?, director = ?, glavbuh = ?,
                bank = ?, bik = ?, schet = ?, kschet = ?, okonh = ?, okpo = ?,
                disc_goods = ?, dop1 = ?, note = ? WHERE client_id = ?");
            bind_auto($stmt, [$values['last_name'], $values['first_name'], $values['title'], $values['name'],
                $values['cli_categ_id'], $values['supplier_flag'], $values['problem_flag'], $values['juridical_flag'], $values['hide_flag'],
                $values['phone'], $values['cphone'], $values['email'], $values['site'], $values['city_id'], $values['country_id'], $values['postindex'],
                $values['address_jur'], $values['address'], $values['pasport'], $values['pasp_date'], $values['pasp_vydan'], $values['birthday'],
                $values['promo_id'], $values['inn'], $values['kpp'], $values['ogrn'], $values['jur_name'], $values['director'], $values['glavbuh'],
                $values['bank'], $values['bik'], $values['schet'], $values['kschet'], $values['okonh'], $values['okpo'],
                $values['disc_goods'], $values['dop1'], $values['note'], $id]);
            if (!$stmt->execute()) {
                $errors[] = 'Ошибка БД: ' . $stmt->error;
            }
            $stmt->close();
        }

        // Save tags (many-to-many)
        $savedId = ($mode === 'new' || $mode === 'copy') ? $newId : $id;
        if ($savedId > 0) {
            $tagIds = array_values(array_filter(array_map('intval', explode(',', $tagIdsStr)), fn($v) => $v > 0));
            $stmt = $conn->prepare("DELETE FROM client_tag WHERE client_id = ?");
            $stmt->bind_param('i', $savedId);
            $stmt->execute();
            $stmt->close();
            foreach ($tagIds as $tid) {
                $stmt = $conn->prepare("INSERT IGNORE INTO client_tag (client_id, tag_id) VALUES (?, ?)");
                bind_auto($stmt, [$savedId, $tid]);
                $stmt->execute();
                $stmt->close();
            }
        }

        if (empty($errors) && $isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            $resp = ['ok' => true, 'mode' => $mode, 'id' => ($mode === 'new' || $mode === 'copy') ? $newId : $id];
            if ($mode === 'new' || $mode === 'copy') $resp['page'] = $pageOfNew;
            echo json_encode($resp);
            exit;
        }
        if (empty($errors)) {
            header('Location: client.php');
            exit;
        }
    }
}

// Load lookup data
$cliCategList = [];
$crs = $conn->query("SELECT cli_categ_id, categ FROM cli_categ ORDER BY categ");
if ($crs) while ($cr = $crs->fetch_assoc()) {
    $cliCategList[] = ['id' => (int)$cr['cli_categ_id'], 'name' => (string)$cr['categ']];
}

$cityList = [];
$crs = $conn->query("SELECT city_id, city, country_id FROM city ORDER BY city");
if ($crs) while ($cr = $crs->fetch_assoc()) {
    $cityList[] = ['id' => (int)$cr['city_id'], 'name' => (string)$cr['city'], 'country_id' => (int)$cr['country_id']];
}

$countryList = [];
$cityCountryNames = [];
$crs = $conn->query("SELECT country_id, country FROM country ORDER BY country");
if ($crs) while ($cr = $crs->fetch_assoc()) {
    $countryList[] = ['id' => (int)$cr['country_id'], 'name' => (string)$cr['country']];
    $cityCountryNames[(int)$cr['country_id']] = (string)$cr['country'];
}

$promoList = [];
$crs = $conn->query("SELECT promo_id, promo FROM promo ORDER BY promo");
if ($crs) while ($cr = $crs->fetch_assoc()) {
    $promoList[] = ['id' => (int)$cr['promo_id'], 'name' => (string)$cr['promo']];
}

$tagList = [];
$crs = $conn->query("SELECT tg.tag_id, tg.tag FROM tag tg WHERE tg.client_flag = '1' ORDER BY tg.tag");
if ($crs) while ($cr = $crs->fetch_assoc()) {
    $tagList[] = ['id' => (int)$cr['tag_id'], 'name' => (string)$cr['tag']];
}

$currentCliCategName = '';
foreach ($cliCategList as $c) {
    if ($c['id'] === (int)$values['cli_categ_id']) { $currentCliCategName = $c['name']; break; }
}

$currentCityName = '';
foreach ($cityList as $c) {
    if ($c['id'] === (int)$values['city_id']) { $currentCityName = $c['name']; break; }
}

$currentCountryName = '';
foreach ($countryList as $c) {
    if ($c['id'] === (int)$values['country_id']) { $currentCountryName = $c['name']; break; }
}

$currentCityCountryId = 0;
foreach ($cityList as $c) {
    if ($c['id'] === (int)$values['city_id']) { $currentCityCountryId = $c['country_id']; break; }
}

$currentPromoName = '';
foreach ($promoList as $c) {
    if ($c['id'] === (int)$values['promo_id']) { $currentPromoName = $c['name']; break; }
}

$currentTagIds = [];
if ($id > 0 && ($mode === 'edit' || $mode === 'delete')) {
    $cts = $conn->prepare("SELECT tag_id FROM client_tag WHERE client_id = ?");
    $cts->bind_param('i', $id);
    $cts->execute();
    $ctr = $cts->get_result();
    while ($ct = $ctr->fetch_assoc()) $currentTagIds[] = (int)$ct['tag_id'];
    $cts->close();
}

$regcodList = [];
if ($RegcodeFlag && $id > 0 && ($mode === 'edit' || $mode === 'delete')) {
    $rcs = $conn->prepare("SELECT r.regcod_id AS id, r.regcod, r.product, r.quant, r.date, r.city, r.signat, r.days, r.block_flag, r.note FROM regcod r WHERE r.client_id = ? ORDER BY r.regcod_id DESC");
    $rcs->bind_param('i', $id);
    $rcs->execute();
    $rcr = $rcs->get_result();
    while ($rc = $rcr->fetch_assoc()) {
        $regcodList[] = [
            'id'         => (int)$rc['id'],
            'regcod'     => (string)$rc['regcod'],
            'product'    => (string)$rc['product'],
            'quant'      => (int)$rc['quant'],
            'date'       => (string)$rc['date'],
            'city'       => (string)$rc['city'],
            'signat'     => (string)$rc['signat'],
            'days'       => (int)$rc['days'],
            'block_flag' => (int)$rc['block_flag'],
            'note'       => (string)$rc['note'],
        ];
    }
    $rcs->close();
}

$embedReadonly = ($mode === 'delete');

$embedConfig = [
    'prefix'       => 'rc',
    'colWidths'    => ['product' => '200px', 'regcod' => '120px', 'quant' => '60px', 'date' => '90px', 'signat' => '120px', 'days' => '50px', 'note' => '150px'],
    'saveUrl'      => 'regcod_field_save.php',
    'parentField'  => 'client_id',
    'childFormUrl' => 'regcod_form.php',
    'childFormName'=> 'regcod',
    'hasExport'    => true,
    'hasPrint'     => true,
    'exportUrl'    => 'regcod_export.php',
    'printUrl'     => 'regcod_print.php',
    'parentParam'  => 'client_id=',
    'hasSearch'    => false,
    'lookupData'   => [],
    'columnResizeUrl' => 'regcod_column_width_save.php',
    'columnResizeTbl' => 'regcod',
    'readonly' => $embedReadonly,
];
$embeddedTable = $RegcodeFlag && $id > 0 && ($mode === 'edit' || $mode === 'delete')
    ? new TablePage($conn, [
        'table'       => 'regcod',
        'key'         => 'id',
        'search_cols' => [],
        'column_visibility_tbl' => '',
        'columns'     => [
            ['name' => 'product', 'label' => 'Товар'],
            ['name' => 'regcod',  'label' => 'Рег. код'],
            ['name' => 'quant',   'label' => 'Кол-во'],
            ['name' => 'date',    'label' => 'Дата'],
            ['name' => 'signat',  'label' => 'Сигнатура'],
            ['name' => 'days',    'label' => 'Дней',    'hideZero' => true],
            ['name' => 'note',    'label' => 'Примечание'],
        ],
        'defaultColumnWidths' => ['product' => '200px', 'regcod' => '120px', 'quant' => '60px', 'date' => '90px', 'signat' => '120px', 'days' => '50px', 'note' => '150px'],
        'lookupData' => [],
    ])
    : null;

ensure_clidoc_table($conn);

$clidocList = [];
if ($id > 0 && ($mode === 'edit' || $mode === 'delete')) {
    $cds = $conn->prepare("SELECT c.id, c.number, c.name, c.filename, c.note FROM clidoc c WHERE c.client_id = ? ORDER BY c.number ASC");
    $cds->bind_param('i', $id);
    $cds->execute();
    $cdr = $cds->get_result();
    while ($cd = $cdr->fetch_assoc()) {
        $clidocList[] = [
            'id'       => (int)$cd['id'],
            'number'   => (int)$cd['number'],
            'name'     => (string)$cd['name'],
            'filename' => (string)$cd['filename'],
            'note'     => (string)$cd['note'],
        ];
    }
    $cds->close();
}

$cdEmbedConfig = [
    'prefix'       => 'cd',
    'colWidths'    => ['number' => '60px', 'name' => '250px', 'filename' => '150px', 'note' => '200px'],
    'saveUrl'      => 'clidoc_field_save.php',
    'parentField'  => 'client_id',
    'childFormUrl' => 'clidoc_form.php',
    'childFormName'=> 'clidoc',
    'hasExport'    => true,
    'hasPrint'     => true,
    'exportUrl'    => 'clidoc_export.php',
    'printUrl'     => 'clidoc_print.php',
    'parentParam'  => 'client_id=',
    'hasSearch'    => false,
    'lookupData'   => [],
    'columnResizeUrl' => 'clidoc_column_width_save.php',
    'columnResizeTbl' => 'clidoc',
    'readonly' => $embedReadonly,
];
$cdEmbeddedTable = $id > 0 && ($mode === 'edit' || $mode === 'delete')
    ? new TablePage($conn, [
        'table'       => 'clidoc',
        'key'         => 'id',
        'search_cols' => [],
        'column_visibility_tbl' => '',
        'columns'     => [
            ['name' => 'number',   'label' => '№'],
            ['name' => 'name',     'label' => 'Документ'],
            ['name' => 'filename', 'label' => 'Файл'],
            ['name' => 'note',     'label' => 'Примечание'],
        ],
        'defaultColumnWidths' => ['number' => '60px', 'name' => '250px', 'filename' => '150px', 'note' => '200px'],
        'lookupData' => [],
    ])
    : null;

$productList = [];
$prs = $conn->query("SELECT product_id, product_name FROM product WHERE hide_flag = 0 ORDER BY product_name");
if ($prs) while ($pr = $prs->fetch_assoc()) $productList[] = ['id' => (int)$pr['product_id'], 'name' => (string)$pr['product_name']];

$titles = [
    'new'    => 'Контрагент (новый)',
    'edit'   => 'Контрагент: ' . ($values['jur_name'] ?: trim($values['last_name'] . ' ' . $values['first_name'])),
    'copy'   => 'Контрагент (копия)',
    'delete' => 'Контрагент (удаление): ' . ($values['jur_name'] ?: trim($values['last_name'] . ' ' . $values['first_name'])),
];
$pageTitle = $titles[$mode] ?? 'Контрагент';

$isReadonly = ($mode === 'delete');

ob_start();
?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><img src="img/customer.png" alt="" /> <?= h($pageTitle) ?></h2>
<form class="form<?= $mode === 'delete' ? ' form--delete' : '' ?>" method="post" action="client_form.php" autocomplete="off" data-form-modal>
<?= render_input('hidden', 'mode', $mode) ?>
<?php if ($id > 0): ?><?= render_input('hidden', 'id', $id) ?><?php endif; ?>
<input type="hidden" name="tag_ids" id="tag-ids-hidden" value="<?= h(implode(',', $currentTagIds)) ?>" />

<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>

<div class="tab-container">
  <div class="tab-headers">
    <div class="tab-header active" data-tab-index="0">Общие</div>
    <div class="tab-header" data-tab-index="1">Адрес</div>
    <div class="tab-header" data-tab-index="2">Реквизиты</div>
    <?php if ($RegcodeFlag): ?>
    <div class="tab-header" data-tab-index="3">Рег. коды</div>
    <?php endif; ?>
    <div class="tab-header" data-tab-index="4">Документы</div>
  </div>

  <?php
  $tabIndex = 0;
  // tab-pane 0: Общие
  ?>
  <div class="tab-pane active" data-tab-index="0">
    <table class="form-table">
      <tr><td class="form-label">Организация</td></tr>
      <tr><td colspan="3"><?= render_input('text', 'jur_name', $values['jur_name'], ['id' => 'jur-name', 'class' => 'full', 'readonly' => $isReadonly]) ?></td></tr>
      <tr>
        <td class="form-label">Фамилия</td>
        <td class="form-label" colspan="2">Имя и отчество</td>
      </tr>
      <tr>
        <td><?= render_input('text', 'last_name', $values['last_name'], ['id' => 'last-name', 'class' => 'full', 'readonly' => $isReadonly]) ?></td>
        <td colspan="2"><?= render_input('text', 'first_name', $values['first_name'], ['id' => 'first-name', 'class' => 'full', 'readonly' => $isReadonly]) ?></td>
      </tr>
      <tr>
        <td class="form-label">Категория</td>
        <td class="form-label">Телефон</td>
        <td class="form-label">Сотовый телефон</td>
      </tr>
      <tr>
        <td><?= render_lookup('cli_categ', 'cli_categ_id', $values['cli_categ_id'], $currentCliCategName, h(json_encode($cliCategList, JSON_UNESCAPED_UNICODE)), 'cli_categ_form.php?mode=new', $isReadonly, ['id' => 'cli-categ-id', 'data-name-input' => 'cli-categ-name']) ?></td>
        <td><?= render_input('text', 'phone', $values['phone'], ['id' => 'phone', 'class' => 'full', 'readonly' => $isReadonly]) ?></td>
        <td><?= render_input('text', 'cphone', $values['cphone'], ['id' => 'cphone', 'class' => 'full', 'readonly' => $isReadonly]) ?></td>
      </tr>
      <tr>
        <td class="form-label">E-mail</td>
        <td class="form-label">Виды деятельности</td>
        <td>&nbsp;</td>
      </tr>
      <tr>
        <td><?= render_input('text', 'email', $values['email'], ['id' => 'email', 'class' => 'full', 'readonly' => $isReadonly]) ?></td>
        <td colspan="2"><div id="tag-picker-container" class="search-cond-control" data-values="<?= h(implode(',', $currentTagIds)) ?>" data-items='<?= h(json_encode($tagList, JSON_UNESCAPED_UNICODE)) ?>'></div></td>
      </tr>
      <tr><td class="form-label">Примечание</td></tr>
      <tr><td colspan="3"><?= render_textarea('note', $values['note'], ['id' => 'note', 'class' => 'full', 'readonly' => $isReadonly, 'rows' => '5', 'style' => 'resize:vertical']) ?></td></tr>
    </table>
    <?= render_form_note() ?>
  </div>

  <div class="tab-pane" data-tab-index="1">
    <table class="form-table">
      <tr><td class="form-label">Город</td><td class="form-label">Страна</td><td class="form-label">Индекс</td></tr>
      <tr>
        <td><?= render_lookup('city', 'city_id', $values['city_id'], $currentCityName, h(json_encode($cityList, JSON_UNESCAPED_UNICODE)), 'city_form.php?mode=new', $isReadonly, ['id' => 'city-id', 'data-name-input' => 'city-name', 'data-country-input' => 'country-id', 'data-city-country-id' => $currentCityCountryId]) ?></td>
        <td><?= render_lookup('country', 'country_id', $values['country_id'], $currentCountryName, h(json_encode($countryList, JSON_UNESCAPED_UNICODE)), 'country_form.php?mode=new', $isReadonly, ['id' => 'country-id', 'data-name-input' => 'country-name']) ?></td>
        <td><?= render_input('text', 'postindex', $values['postindex'], ['id' => 'postindex', 'class' => 'full', 'readonly' => $isReadonly]) ?></td>
      </tr>
      <tr><td class="form-label">Юридический адрес</td></tr>
      <tr><td colspan="3"><?= render_input('text', 'address_jur', $values['address_jur'], ['id' => 'address-jur', 'class' => 'full', 'readonly' => $isReadonly]) ?></td></tr>
      <tr><td class="form-label">Фактический адрес</td></tr>
      <tr><td colspan="3"><?= render_input('text', 'address', $values['address'], ['id' => 'address', 'class' => 'full', 'readonly' => $isReadonly]) ?></td></tr>
      <tr><td class="form-label">Сайт</td></tr>
      <tr><td colspan="3"><?= render_input('text', 'site', $values['site'], ['id' => 'site', 'class' => 'full', 'readonly' => $isReadonly]) ?></td></tr>
    </table>
  </div>

  <div class="tab-pane" data-tab-index="2">
    <table class="form-table">
      <tr><td class="form-label">ИНН</td><td class="form-label">КПП</td><td class="form-label">ОГРН</td></tr>
      <tr>
        <td><?= render_input('text', 'inn', $values['inn'], ['id' => 'inn', 'class' => 'full', 'readonly' => $isReadonly]) ?></td>
        <td><?= render_input('text', 'kpp', $values['kpp'], ['id' => 'kpp', 'class' => 'full', 'readonly' => $isReadonly]) ?></td>
        <td><?= render_input('text', 'ogrn', $values['ogrn'], ['id' => 'ogrn', 'class' => 'full', 'readonly' => $isReadonly]) ?></td>
      </tr>
      <tr><td class="form-label" colspan="2">Банк</td><td class="form-label">БИК</td></tr>
      <tr>
        <td colspan="2"><?= render_input('text', 'bank', $values['bank'], ['id' => 'bank', 'class' => 'full', 'readonly' => $isReadonly]) ?></td>
        <td><?= render_input('text', 'bik', $values['bik'], ['id' => 'bik', 'class' => 'full', 'readonly' => $isReadonly]) ?></td>
      </tr>
      <tr><td class="form-label">Расчетный счет</td><td class="form-label">Корсчет</td><td>&nbsp;</td></tr>
      <tr>
        <td><?= render_input('text', 'schet', $values['schet'], ['id' => 'schet', 'class' => 'full', 'readonly' => $isReadonly]) ?></td>
        <td><?= render_input('text', 'kschet', $values['kschet'], ['id' => 'kschet', 'class' => 'full', 'readonly' => $isReadonly]) ?></td>
        <td>&nbsp;</td>
      </tr>
      <tr><td class="form-label">Директор</td><td class="form-label">Главный бухгалтер</td><td>&nbsp;</td></tr>
      <tr>
        <td><?= render_input('text', 'director', $values['director'], ['id' => 'director', 'class' => 'full', 'readonly' => $isReadonly]) ?></td>
        <td><?= render_input('text', 'glavbuh', $values['glavbuh'], ['id' => 'glavbuh', 'class' => 'full', 'readonly' => $isReadonly]) ?></td>
        <td>&nbsp;</td>
      </tr>
      <tr><td class="form-label">Реклама</td></tr>
      <tr>
        <td><?= render_lookup('promo', 'promo_id', $values['promo_id'], $currentPromoName, h(json_encode($promoList, JSON_UNESCAPED_UNICODE)), 'promo_form.php?mode=new', $isReadonly, ['id' => 'promo-id', 'data-name-input' => 'promo-name']) ?></td>
      </tr>
    </table>
  </div>

  <?php if ($RegcodeFlag): ?>
  <div class="tab-pane" data-tab-index="3">
    <?php if ($id > 0 && ($mode === 'edit' || $mode === 'delete')): ?>
    <?= $embeddedTable->renderEmbedded($regcodList, $embedConfig) ?>
    <?php else: ?>
    <div style="padding:40px 20px;text-align:center;color:var(--muted);font-size:14px">Сохраните контрагента, чтобы добавить регистрационные коды</div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <div class="tab-pane" data-tab-index="4">
    <div style="display:flex;gap:12px;">
      <div style="flex:2;min-width:0;">
        <?php if ($id > 0 && ($mode === 'edit' || $mode === 'delete')): ?>
        <?= $cdEmbeddedTable->renderEmbedded($clidocList, $cdEmbedConfig) ?>
        <?php else: ?>
        <div style="padding:40px 20px;text-align:center;color:var(--muted);font-size:14px">Сохраните контрагента, чтобы добавить документы</div>
        <?php endif; ?>
      </div>
      <div style="flex:1;min-width:0;" id="cd-preview-panel">
        <div style="padding:20px;text-align:center;color:var(--muted);font-size:13px;border:1px dashed var(--line);border-radius:4px;">Выберите строку для просмотра</div>
      </div>
    </div>
  </div>
</div>

<style>
#regcod-modal .lookup-pop { z-index: 2300; }
</style>

<?= render_form_actions(
    $mode === 'delete'
        ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'client.php', ['class'=>'btn-secondary'])]
        : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'client.php', ['class'=>'btn-secondary'])]
) ?>

<script>
(function() {
  var container = document.getElementById('tag-picker-container');
  if (!container) return;
  var items = [];
  try { items = JSON.parse(container.getAttribute('data-items') || '[]'); } catch(e) {}
  var selectedIds = (container.getAttribute('data-values') || '').split(',').map(Number).filter(function(n) { return n > 0; });
  var hidden = document.getElementById('tag-ids-hidden');

  function renderTags() {
    container.innerHTML = '';
    if (selectedIds.length === 0) {
      container.innerHTML = '<span class="search-cond-empty">Выберите вид деятельности</span>';
    } else {
      selectedIds.forEach(function(id) {
        var item = items.find(function(i) { return i.id === id; });
        if (!item) return;
        var chip = document.createElement('span');
        chip.className = 'search-cond-chip';
        chip.textContent = item.name;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'search-cond-chip-remove';
        btn.textContent = '\u00d7';
        btn.addEventListener('click', function() {
          selectedIds = selectedIds.filter(function(n) { return n !== id; });
          if (hidden) hidden.value = selectedIds.join(',');
          renderTags();
        });
        chip.appendChild(btn);
        container.appendChild(chip);
      });
    }
    if (hidden) hidden.value = selectedIds.join(',');
  }

  renderTags();

  container.addEventListener('click', function(e) {
    if (e.target.closest('.search-cond-chip')) return;
    var pop = document.createElement('div');
    pop.className = 'search-cond-pop';
    pop.style.cssText = 'position:absolute;left:0;top:100%;z-index:1000;background:#2f3e4e;border:1px solid #6b7785;border-radius:4px;padding:8px;min-width:200px;max-height:250px;overflow-y:auto;';
    items.forEach(function(item) {
      var label = document.createElement('label');
      label.style.cssText = 'display:flex;align-items:center;gap:6px;padding:4px 0;cursor:pointer;color:#e0e0e0;';
      var cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.checked = selectedIds.indexOf(item.id) >= 0;
      cb.value = item.id;
      label.appendChild(cb);
      label.appendChild(document.createTextNode(' ' + item.name));
      pop.appendChild(label);
    });
    var applyBtn = document.createElement('button');
    applyBtn.type = 'button';
    applyBtn.className = 'btn btn-primary';
    applyBtn.textContent = '';
    applyBtn.style.cssText = 'margin-top:8px;width:100%;';
    applyBtn.addEventListener('click', function() {
      selectedIds = [];
      pop.querySelectorAll('input[type="checkbox"]:checked').forEach(function(cb) {
        selectedIds.push(parseInt(cb.value, 10));
      });
      if (hidden) hidden.value = selectedIds.join(',');
      renderTags();
      pop.remove();
    });
    pop.appendChild(applyBtn);
    container.appendChild(pop);

    function closePop(ev) {
      if (!container.contains(ev.target)) { pop.remove(); document.removeEventListener('click', closePop); }
    }
    document.addEventListener('click', closePop);
  });
})();
</script>

</form>

<style>
.tab-container { margin-bottom: 16px; }
.tab-headers { display: flex; border-bottom: 2px solid var(--accent); margin-bottom: 12px; }
.tab-header {
  padding: 8px 20px; cursor: pointer; font-size: 14px; font-weight: bold;
  color: var(--muted); border: 1px solid transparent; border-bottom: none;
  border-radius: 4px 4px 0 0; user-select: none;
}
.tab-header.active { color: #fff; background: var(--accent); border-color: var(--accent); }
.tab-header:hover:not(.active) { color: #fff; background: var(--btn-hover); }
.tab-pane { display: none; }
.tab-pane.active { display: block; }
.form-table { width: 100%; border-collapse: collapse; }
.form-table td { vertical-align: top; padding-bottom: 6px; padding-right: 8px; }
.form-table td:last-child { padding-right: 0; }
.form-table td.form-label { font-size: 12px; color: var(--muted); padding-bottom: 2px; }
input.full, textarea.full { width: 100%; box-sizing: border-box; }
#tag-picker-container.search-cond-control { min-height: 0; padding: 2px 24px 2px 4px; font-size: 12px; }
#tag-picker-container.search-cond-control .search-cond-empty { padding: 2px 4px; }

</style>
<script src="assets/lookup.js"></script>
<script>
(function () {
  var lastFocused = null;

  function initTabs(container) {
    var headers = container.querySelectorAll('.tab-header');
    var panes = container.querySelectorAll('.tab-pane');
    headers.forEach(function (hdr) {
      hdr.addEventListener('click', function () {
        var idx = parseInt(hdr.dataset.tabIndex, 10);
        headers.forEach(function (h) { h.classList.remove('active'); });
        panes.forEach(function (p) { p.classList.remove('active'); });
        hdr.classList.add('active');
        var pane = container.querySelector('.tab-pane[data-tab-index="' + idx + '"]');
        if (pane) pane.classList.add('active');
      });
    });
  }

  var tabContainer = document.querySelector('.tab-container');
  if (tabContainer) initTabs(tabContainer);

  function updateNameDisplay() {
    var jur = (document.getElementById('jur-name') || {}).value || '';
    var last = (document.getElementById('last-name') || {}).value || '';
    var first = (document.getElementById('first-name') || {}).value || '';
    var display = document.getElementById('name-display');
    if (display) display.value = jur || (last + ' ' + first).trim();
    var flag = document.getElementById('juridical-flag');
    if (flag) flag.checked = jur !== '';
  }

  document.getElementById('jur-name')?.addEventListener('input', updateNameDisplay);
  document.getElementById('last-name')?.addEventListener('input', updateNameDisplay);
  document.getElementById('first-name')?.addEventListener('input', updateNameDisplay);
  updateNameDisplay();

  var cityRoot = document.querySelector('[data-lookup="city"]');
  var countryRoot = document.querySelector('[data-lookup="country"]');
  var cityList = cityRoot ? JSON.parse(cityRoot.getAttribute('data-countries') || '[]') : [];
  var countryList = countryRoot ? JSON.parse(countryRoot.getAttribute('data-countries') || '[]') : [];

  var cityReadonly = cityRoot && cityRoot.querySelector('.lookup-input') && cityRoot.querySelector('.lookup-input').hasAttribute('readonly');
  var countryReadonly = countryRoot && countryRoot.querySelector('.lookup-input') && countryRoot.querySelector('.lookup-input').hasAttribute('readonly');

  function onCitySelect(id, name) {
    var city = cityList.find(function (c) { return String(c.id) === String(id); });
    if (city && city.country_id) {
      var country = countryList.find(function (c) { return String(c.id) === String(city.country_id); });
      if (country) {
        var countryInput = document.querySelector('[data-lookup="country"] .lookup-input');
        var countryHidden = document.querySelector('[data-lookup="country"] [data-lookup-id]');
        if (countryInput) countryInput.value = country.name;
        if (countryHidden) countryHidden.value = country.id;
      }
    }
  }

  if (cityRoot && !cityReadonly) {
    bindLookup({ root: cityRoot, data: cityList, readonly: cityReadonly, onSelect: onCitySelect });
  }
  if (countryRoot && !countryReadonly) {
    bindLookup({ root: countryRoot, data: countryList, readonly: countryReadonly });
  }

  var cliCategRoot = document.querySelector('[data-lookup="cli_categ"]');
  var cliCategList = cliCategRoot ? JSON.parse(cliCategRoot.getAttribute('data-countries') || '[]') : [];
  if (cliCategRoot && !cliCategRoot.querySelector('.lookup-input')?.readOnly) {
    bindLookup({ root: cliCategRoot, data: cliCategList, readonly: false });
  }

  var promoRoot = document.querySelector('[data-lookup="promo"]');
  var promoList = promoRoot ? JSON.parse(promoRoot.getAttribute('data-countries') || '[]') : [];
  if (promoRoot && !promoRoot.querySelector('.lookup-input')?.readOnly) {
    bindLookup({ root: promoRoot, data: promoList, readonly: false });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      var anyOpen = document.querySelectorAll('.lookup-pop.open');
      if (anyOpen.length > 0) return;
      var anyTagPop = document.querySelectorAll('.search-cond-pop.open');
      if (anyTagPop.length > 0) return;
      e.preventDefault();
      if (window.parent && window.parent !== window) {
        try { window.parent.postMessage({ type: 'form-cancel' }, '*'); } catch (err) {}
      } else {
        window.location.href = 'client.php';
      }
    }
  });
})();

(function () {
  var form = document.querySelector('form[data-form-modal]');
  if (!form) return;
  var focusable = Array.from(form.querySelectorAll(
    'input:not([type="hidden"]):not([tabindex="-1"]):not([readonly]),' +
    'button:not([tabindex="-1"]):not([disabled]),' +
    'a[href]:not([tabindex="-1"]),' +
    'textarea:not([tabindex="-1"]):not([readonly]),' +
    'select:not([tabindex="-1"]):not([readonly])'
  )).filter(function (el) { return el.offsetParent !== null; });
  if (focusable.length < 2) return;
  form.addEventListener('keydown', function (e) {
    if (e.key !== 'Tab') return;
    var idx = focusable.indexOf(document.activeElement);
    if (idx === -1) return;
    e.preventDefault();
    if (e.shiftKey) {
      focusable[(idx - 1 + focusable.length) % focusable.length].focus();
    } else {
      focusable[(idx + 1) % focusable.length].focus();
    }
  });
})();
</script>
<?php if ($embeddedTable): ?>
<script>
<?php $embeddedTable->renderEmbeddedScripts($embedConfig); ?>
initRcTable();
window.__columnDefaultWidths = <?= json_encode($embedConfig['colWidths'], JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php endif; ?>
<?php if ($cdEmbeddedTable): ?>
<script>
<?php $cdEmbeddedTable->renderEmbeddedScripts($cdEmbedConfig); ?>
initCdTable();
(function(){
  var tbl = window['__cdTable'];
  if (!tbl) return;
  var col = tbl.columns.find(function(c){return c.key==='filename';});
  if (col) col.render = function(val,item){
    if (!val) return '';
    var parts = val.split('/');
    return '<a href="' + val + '" target="_blank">' + parts[parts.length-1] + '</a>';
  };
  tbl.onRowSelect = function(id){
    var item = null;
    for (var i=0;i<tbl.data.length;i++){if(tbl.data[i].id==id){item=tbl.data[i];break;}}
    var panel=document.getElementById('cd-preview-panel');
    if(!panel)return;
    if(!item||!item.filename){panel.innerHTML='<div style="padding:20px;text-align:center;color:var(--muted);font-size:13px;border:1px dashed var(--line);border-radius:4px;">Нет файла для просмотра</div>';return;}
    var fn=item.filename,ext=fn.split('.').pop().toLowerCase();
    if(['jpg','jpeg','png','gif','webp','svg','bmp'].indexOf(ext)>=0){panel.innerHTML='<div style="padding:4px;text-align:center;"><a href="'+fn+'" target="_blank"><img src="'+fn+'" style="max-width:100%;max-height:70vh;border-radius:4px;" /></a></div>';}
    else{panel.innerHTML='<div style="padding:20px;text-align:center;color:var(--muted);font-size:13px;">Предпросмотр недоступен</div>';}
  };
  tbl.render();
  if (tbl.selectedId) tbl.onRowSelect(tbl.selectedId);
})();
</script>
<?php endif; ?>
<?php
$formHtml = ob_get_clean();
} catch (Throwable $e) {
    while (ob_get_level() > 0) ob_end_clean();
    $formHtml = '<div class="flash flash--error">' . h($e->getMessage()) . '</div>';
    $errors[] = $e->getMessage();
}

if ($isAjax) {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => empty($errors), 'html' => $formHtml, 'mode' => $mode, 'focusField' => $focusField]);
    exit;
}
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= h($pageTitle) ?></title>
  <link rel="stylesheet" href="app.css" />
</head>
<body>
  <div class="page page--form">
    <?= $formHtml ?>
  </div>
</body>
</html>