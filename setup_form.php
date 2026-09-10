<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/table-helper.php';
require_once __DIR__ . '/lib/table-template.php';

$isAjax = (
    (string)($_GET['ajax'] ?? '') === '1' ||
    (string)($_POST['ajax'] ?? '') === '1' ||
    (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

$errors = [];
$focusField = '';
$saved = (string)($_GET['saved'] ?? '');

$values = [
    'startup_page' => 'clients',
    'page_size'    => '20',
    'page_width'   => '1100',
    'db_host'      => DB_HOST,
    'db_user'      => DB_USER,
    'db_name'      => DB_NAME,
    'db_pass'      => DB_PASS,
    'nds_rate'     => '22',
    'no_nds'       => '0',
    'regcode_flag' => '0',
    'show_hidden'  => '0',
    'neg_ost_flag'     => '0',
    'firm_id'          => '0',
    'current_store_id' => '0',
    'ShowHoursFlag'         => '0',
    'ShowDaysFlag'          => '0',
    'ShowMonthsFlag'        => '0',
    'ManualTariffFlag'      => '0',
    'FixedFlag'             => '0',
    'KeepDaysOnEarlyReturn' => '0',
    'NoRefundOnEarlyReturn' => '0',
    'AddLateDayCharge'      => '0',
    'TimeShift' => '00:00',
    'TimeLate'  => '00:00',
    'SezonFlag' => '0',
];

$rentalFlagKeys = [
    'ShowHoursFlag',
    'ShowDaysFlag',
    'ShowMonthsFlag',
    'ManualTariffFlag',
    'FixedFlag',
    'KeepDaysOnEarlyReturn',
    'NoRefundOnEarlyReturn',
    'AddLateDayCharge',
    'SezonFlag',
];

$appSettings = load_app_settings($conn);
$values['startup_page'] = $appSettings['startup_page'] ?? 'clients';
$values['page_size']    = (string)((int)($appSettings['page_size'] ?? 20));
$values['page_width']   = (string)((int)($appSettings['page_width'] ?? 1100));
$values['nds_rate']     = (string)((int)($appSettings['nds_rate'] ?? 22));
$values['no_nds']       = (string)((int)($appSettings['no_nds'] ?? 0));
$values['regcode_flag'] = (string)((int)($appSettings['regcode_flag'] ?? 0));
$values['show_hidden']  = (string)((int)($appSettings['show_hidden'] ?? 0));
$values['neg_ost_flag'] = (string)((int)($appSettings['neg_ost_flag'] ?? 0));
$values['firm_id']          = (string)((int)($appSettings['firm_id'] ?? 0));
$values['current_store_id'] = (string)((int)($appSettings['current_store_id'] ?? 0));
foreach ($rentalFlagKeys as $flagKey) {
    $values[$flagKey] = (string)((int)($appSettings[$flagKey] ?? 0));
}
foreach (['TimeShift', 'TimeLate'] as $timeKey) {
    $t = trim((string)($appSettings[$timeKey] ?? '00:00'));
    $values[$timeKey] = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) ? $t : '00:00';
}

$firmOptions = [];
$firmResult = @$conn->query("SELECT firm_id, name FROM firm ORDER BY firm_id LIMIT 100");
if ($firmResult) {
    while ($fr = $firmResult->fetch_assoc()) {
        $firmOptions[] = ['value' => $fr['firm_id'], 'label' => $fr['name']];
    }
}
if (empty($firmOptions)) {
    $firmOptions[] = ['value' => '0', 'label' => '— нет записей —'];
}
if ($values['firm_id'] === '0' && !empty($firmOptions) && $firmOptions[0]['value'] !== '0') {
    $values['firm_id'] = (string)$firmOptions[0]['value'];
}

$storeOptions = [];
$storeResult = @$conn->query("SELECT store_id, name FROM store ORDER BY name LIMIT 500");
if ($storeResult) {
    while ($sr = $storeResult->fetch_assoc()) {
        $storeOptions[] = ['value' => $sr['store_id'], 'label' => $sr['name']];
    }
}
if (empty($storeOptions)) {
    $storeOptions[] = ['value' => '0', 'label' => '— нет записей —'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['startup_page'] = (string)($_POST['startup_page'] ?? 'clients');
    $values['page_size']    = (string)(int)($_POST['page_size'] ?? 20);
    $values['page_width']   = (string)(int)($_POST['page_width'] ?? 1100);
    $values['db_host']      = trim((string)($_POST['db_host'] ?? ''));
    $values['db_user']      = trim((string)($_POST['db_user'] ?? ''));
    $values['db_name']      = trim((string)($_POST['db_name'] ?? ''));
    $values['db_pass']      = (string)($_POST['db_pass'] ?? '');
    $values['nds_rate']     = (string)(int)($_POST['nds_rate'] ?? 22);
    $values['no_nds']       = (string)(int)(!empty($_POST['no_nds']) ? 1 : 0);
    $values['regcode_flag'] = (string)(int)(!empty($_POST['regcode_flag']) ? 1 : 0);
    $values['show_hidden']  = (string)(int)(!empty($_POST['show_hidden']) ? 1 : 0);
    $values['neg_ost_flag'] = (string)(int)(!empty($_POST['neg_ost_flag']) ? 1 : 0);
    $values['firm_id']          = (string)(int)($_POST['firm_id'] ?? 0);
    $values['current_store_id'] = (string)(int)($_POST['current_store_id'] ?? 0);
    foreach ($rentalFlagKeys as $flagKey) {
        $values[$flagKey] = (string)(int)(!empty($_POST[$flagKey]) ? 1 : 0);
    }
    foreach (['TimeShift', 'TimeLate'] as $timeKey) {
        $values[$timeKey] = trim((string)($_POST[$timeKey] ?? '00:00'));
    }

    $pageSize  = (int)$values['page_size'];
    $pageWidth = (int)$values['page_width'];
    $ndsRate   = (int)$values['nds_rate'];

    if ($pageSize < 5) {
        $errors[] = 'Количество строк должно быть не менее 5.';
        $focusField = 'page_size';
    }
    if ($pageWidth < 800) {
        $errors[] = 'Ширина страницы должна быть не менее 800px.';
        $focusField = 'page_width';
    }
    if ($ndsRate < 0 || $ndsRate > 100) {
        $errors[] = 'Ставка НДС должна быть от 0 до 100.';
        $focusField = 'nds_rate';
    }
    foreach (['TimeShift' => 'Время задержки начала проката', 'TimeLate' => 'Допустимое время опоздания'] as $timeKey => $timeLabel) {
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $values[$timeKey])) {
            $errors[] = 'Поле «' . $timeLabel . '» должно содержать время в формате чч:мм.';
            $focusField = $timeKey;
        }
    }
    if ($values['firm_id'] === '0') {
        $errors[] = 'Выберите фирму.';
        $focusField = 'firm_id';
    }
    if ($values['db_host'] === '') {
        $errors[] = 'Поле «Хост БД» обязательно для заполнения.';
        $focusField = 'db_host';
    }
    if ($values['db_user'] === '') {
        $errors[] = 'Поле «Пользователь БД» обязательно для заполнения.';
        $focusField = 'db_user';
    }
    if ($values['db_name'] === '') {
        $errors[] = 'Поле «Имя БД» обязательно для заполнения.';
        $focusField = 'db_name';
    }

    if (empty($errors)) {
        $testConn = @mysqli_connect($values['db_host'], $values['db_user'], $values['db_pass'], $values['db_name']);
        if (!$testConn) {
            $errors[] = 'Не удалось подключиться к БД с указанными параметрами: ' . mysqli_connect_error();
            $focusField = 'db_host';
        } else {
            mysqli_close($testConn);
            save_app_setting($conn, 'startup_page', $values['startup_page']);
            save_app_setting($conn, 'page_size', $values['page_size']);
            save_app_setting($conn, 'page_width', $values['page_width']);
            save_app_setting($conn, 'nds_rate', $values['nds_rate']);
            save_app_setting($conn, 'no_nds', $values['no_nds']);
            save_app_setting($conn, 'regcode_flag', $values['regcode_flag']);
            save_app_setting($conn, 'show_hidden', $values['show_hidden']);
            save_app_setting($conn, 'neg_ost_flag', $values['neg_ost_flag']);
            save_app_setting($conn, 'firm_id', $values['firm_id']);
            save_app_setting($conn, 'current_store_id', $values['current_store_id']);
            foreach ($rentalFlagKeys as $flagKey) {
                save_app_setting($conn, $flagKey, $values[$flagKey]);
            }
            save_app_setting($conn, 'TimeShift', $values['TimeShift']);
            save_app_setting($conn, 'TimeLate', $values['TimeLate']);

            $localConfig = "<?php\nreturn [\n";
            $localConfig .= "    'DB_HOST' => " . var_export($values['db_host'], true) . ",\n";
            $localConfig .= "    'DB_USER' => " . var_export($values['db_user'], true) . ",\n";
            $localConfig .= "    'DB_PASS' => " . var_export($values['db_pass'], true) . ",\n";
            $localConfig .= "    'DB_NAME' => " . var_export($values['db_name'], true) . ",\n";
            $localConfig .= "];\n";

            $written = @file_put_contents(__DIR__ . '/config.local.php', $localConfig);
            if ($written === false) {
                $errInfo = error_get_last();
                $extra = $errInfo ? ': ' . ($errInfo['message'] ?? 'unknown') : '';
                $errors[] = 'Не удалось записать файл config.local.php' . $extra;
            } else {
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['ok' => true]);
                    exit;
                }
                header('Location: setup_form.php?saved=1');
                exit;
            }
        }
    }
}

$startupOptions = [
    ['value' => 'clients',  'label' => 'Контрагенты'],
    ['value' => 'goods',    'label' => 'Товары'],
    ['value' => 'invoices', 'label' => 'Счета'],
    ['value' => 'sales',    'label' => 'Продажи'],
];

ob_start();
?><style>
  .setup-table { width:100%; border-collapse:collapse; }
  .setup-table td { vertical-align:bottom; padding:10px 8px 10px 0; }
  .setup-table td:last-child { padding-right:0; }
  .setup-table .field { margin-bottom:0; }
  .fieldset { border: 1px solid #6b7785; border-radius: 4px; padding: 12px 11px 4px; margin: 0 0 10px 0; }
  .fieldset-legend { font-weight: 700; color: #ffe9a8; padding: 0 6px; }
  .checkbox-label { display: flex; align-items: center; gap: 6px; cursor: pointer; }
  .checkbox-label input[type="checkbox"] { width: 16px; height: 16px; margin: 0; }
  .fieldset-db { margin-top:6px; }
  .form-actions--bottom { margin-top: 2px; margin-bottom: 4px; }
  .form-modal { max-width: 800px !important; }
  .form { max-width: none; }
</style>
<h2 class="page-title"><img src="img/setup.png" alt="" /> Параметры настройки</h2>
<form class="form" method="post" action="setup_form.php" autocomplete="off" data-form-modal>
<?php if (!empty($errors)): ?>
  <div class="flash flash--error">
    <?php foreach ($errors as $e): ?>
      <div><?= h($e) ?></div>
    <?php endforeach; ?>
  </div>
<?php elseif ($saved === '1'): ?>
  <div class="flash flash--success">Настройки сохранены</div>
<?php endif; ?>

<?php $rentalTabActive = in_array($focusField, ['TimeShift', 'TimeLate'], true); ?>
<div class="tab-container">
  <div class="tab-headers">
    <div class="tab-header<?= $rentalTabActive ? '' : ' active' ?>" data-tab-index="0">Общие</div>
    <div class="tab-header<?= $rentalTabActive ? ' active' : '' ?>" data-tab-index="1">Аренда</div>
  </div>

  <div class="tab-pane<?= $rentalTabActive ? '' : ' active' ?>" data-tab-index="0">
<fieldset class="fieldset">
  <legend class="fieldset-legend">Основные</legend>
  <table class="setup-table">
    <tr>
      <td><?= render_field('Показывать при включении', render_select('startup_page', $startupOptions, $values['startup_page'], ['id' => 'startup_page']), false, ['for' => 'startup_page']) ?></td>
      <td><?= render_field('Фирма', render_select('firm_id', $firmOptions, $values['firm_id'], ['id' => 'firm_id']), true, ['for' => 'firm_id']) ?></td>
      <td><?= render_field('Текущий участок', render_select('current_store_id', $storeOptions, $values['current_store_id'], ['id' => 'current_store_id']), false, ['for' => 'current_store_id']) ?></td>
    </tr>
    <tr>
      <td><?= render_field('Ставка НДС', render_input('number', 'nds_rate', $values['nds_rate'], ['id' => 'nds_rate', 'min' => '0', 'max' => '100', 'step' => '1', 'style' => 'width:100px']), false, ['for' => 'nds_rate']) ?></td>
      <td colspan="2"><?= render_field('', '<label class="checkbox-label"><input type="checkbox" name="no_nds" value="1"' . ($values['no_nds'] === '1' ? ' checked' : '') . ' /> Цены без НДС</label>', false, ['for' => 'no_nds']) ?></td>
    </tr>
    <tr>
      <td><?= render_field('Количество строк на странице', render_input('number', 'page_size', $values['page_size'], ['id' => 'page_size', 'min' => '5', 'step' => '1', 'style' => 'width:100px']), false, ['for' => 'page_size']) ?></td>
      <td colspan="2"><?= render_field('Макс. ширина страницы (px)', render_input('number', 'page_width', $values['page_width'], ['id' => 'page_width', 'min' => '800', 'step' => '10', 'style' => 'width:120px']), false, ['for' => 'page_width']) ?></td>
    </tr>
    <tr>
      <td><?= render_field('', '<label class="checkbox-label"><input type="checkbox" name="regcode_flag" value="1"' . ($values['regcode_flag'] === '1' ? ' checked' : '') . ' /> Регистрационные коды</label>', false, ['for' => 'regcode_flag']) ?></td>
      <td><?= render_field('', '<label class="checkbox-label"><input type="checkbox" name="show_hidden" value="1"' . ($values['show_hidden'] === '1' ? ' checked' : '') . ' /> Показывать все записи</label>', false, ['for' => 'show_hidden']) ?></td>
      <td><?= render_field('', '<label class="checkbox-label"><input type="checkbox" name="neg_ost_flag" value="1"' . ($values['neg_ost_flag'] === '1' ? ' checked' : '') . ' /> Запрет отрицательных остатков</label>', false, ['for' => 'neg_ost_flag']) ?></td>
    </tr>
  </table>
</fieldset>

<fieldset class="fieldset fieldset-db">
  <legend class="fieldset-legend">Подключение к БД</legend>
  <div style="display:flex;gap:24px;flex-wrap:wrap">
    <div><?= render_field('Хост', render_input('text', 'db_host', $values['db_host'], ['id' => 'db_host', 'style' => 'width:220px']), true, ['for' => 'db_host']) ?></div>
    <div><?= render_field('Имя БД', render_input('text', 'db_name', $values['db_name'], ['id' => 'db_name', 'style' => 'width:220px']), true, ['for' => 'db_name']) ?></div>
  </div>
  <div style="display:flex;gap:24px;flex-wrap:wrap">
    <div><?= render_field('Пользователь', render_input('text', 'db_user', $values['db_user'], ['id' => 'db_user', 'style' => 'width:220px']), true, ['for' => 'db_user']) ?></div>
    <div><?= render_field('Пароль', render_input('password', 'db_pass', $values['db_pass'], ['id' => 'db_pass', 'style' => 'width:220px']), false, ['for' => 'db_pass']) ?></div>
  </div>
</fieldset>
  </div>

  <div class="tab-pane<?= $rentalTabActive ? ' active' : '' ?>" data-tab-index="1">
<fieldset class="fieldset">
  <legend class="fieldset-legend">Аренда</legend>
  <table class="setup-table">
    <tr>
      <td><?= render_field('', '<label class="checkbox-label"><input type="checkbox" name="ShowHoursFlag" value="1"' . ($values['ShowHoursFlag'] === '1' ? ' checked' : '') . ' /> Показывать поле Часов</label>', false, ['for' => 'show-hours-flag']) ?></td>
      <td><?= render_field('', '<label class="checkbox-label"><input type="checkbox" name="ShowDaysFlag" value="1"' . ($values['ShowDaysFlag'] === '1' ? ' checked' : '') . ' /> Показывать поле Дней</label>', false, ['for' => 'show-days-flag']) ?></td>
      <td><?= render_field('', '<label class="checkbox-label"><input type="checkbox" name="ShowMonthsFlag" value="1"' . ($values['ShowMonthsFlag'] === '1' ? ' checked' : '') . ' /> Показывать поле Месяцев</label>', false, ['for' => 'show-months-flag']) ?></td>
    </tr>
    <tr>
      <td><?= render_field('', '<label class="checkbox-label"><input type="checkbox" name="ManualTariffFlag" value="1"' . ($values['ManualTariffFlag'] === '1' ? ' checked' : '') . ' /> Тарифы вводятся вручную</label>', false, ['for' => 'manual-tariff-flag']) ?></td>
      <td><?= render_field('', '<label class="checkbox-label"><input type="checkbox" name="FixedFlag" value="1"' . ($values['FixedFlag'] === '1' ? ' checked' : '') . ' /> Фиксированные тарифы за период времени</label>', false, ['for' => 'fixed-flag']) ?></td>
      <td><?= render_field('', '<label class="checkbox-label"><input type="checkbox" name="AddLateDayCharge" value="1"' . ($values['AddLateDayCharge'] === '1' ? ' checked' : '') . ' /> Добавлять стоимость суток за опоздание</label>', false, ['for' => 'add-late-day-charge']) ?></td>
    </tr>
    <tr>
      <td><?= render_field('', '<label class="checkbox-label"><input type="checkbox" name="KeepDaysOnEarlyReturn" value="1"' . ($values['KeepDaysOnEarlyReturn'] === '1' ? ' checked' : '') . ' /> Не изменять «Дней» при досрочном возврате</label>', false, ['for' => 'keep-days-on-early-return']) ?></td>
      <td><?= render_field('', '<label class="checkbox-label"><input type="checkbox" name="NoRefundOnEarlyReturn" value="1"' . ($values['NoRefundOnEarlyReturn'] === '1' ? ' checked' : '') . ' /> Не возвращать деньги при досрочном возврате</label>', false, ['for' => 'no-refund-on-early-return']) ?></td>
      <td><?= render_field('', '<label class="checkbox-label"><input type="checkbox" name="SezonFlag" value="1"' . ($values['SezonFlag'] === '1' ? ' checked' : '') . ' /> Использовать сезоны тарифных планов</label>', false, ['for' => 'sezon-flag']) ?></td>
    </tr>
    <tr>
      <td><?= render_field('Время задержки начала проката', render_input('text', 'TimeShift', $values['TimeShift'], ['id' => 'time-shift', 'maxlength' => '5', 'placeholder' => 'чч:мм', 'style' => 'width:100px']), false, ['for' => 'time-shift']) ?></td>
      <td><?= render_field('Допустимое время опоздания', render_input('text', 'TimeLate', $values['TimeLate'], ['id' => 'time-late', 'maxlength' => '5', 'placeholder' => 'чч:мм', 'style' => 'width:100px']), false, ['for' => 'time-late']) ?></td>
      <td></td>
    </tr>
  </table>
</fieldset>
  </div>
</div>

<script>
(function () {
  var container = document.querySelector('.tab-container');
  if (!container) return;
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
})();
</script>

<div class="form-actions form-actions--bottom">
  <button type="submit" class="btn btn-primary"><img src="img/ok.png" alt="" /> Сохранить</button>
  <button type="button" class="btn btn-secondary" data-form-close><img src="img/cancel.png" alt="" /> Отменить</button>
</div>
</form>
<?php
$formHtml = ob_get_clean();

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => empty($errors), 'html' => $formHtml]);
    }
    exit;
}

$pageTitle = 'Параметры настройки';
render_head_start($pageTitle);
?>
<?php render_head_end(); ?>
  <div class="page page--form">
    <?php $activeMenu = 'setup_form.php'; include 'menu.php'; ?>
    <?= $formHtml ?>
  </div>
<?php render_page_footer(); ?>
