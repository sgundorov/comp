<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/table-helper.php';

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
$values = [
    'name'       => '',
    'address'    => '',
    'addres_jur' => '',
    'phone'      => '',
    'email'      => '',
    'country_id' => 0,
    'city_id'    => 0,
    'requisites' => '',
    'director'   => '',
    'ogrn'       => '',
    'nds'        => '',
    'info'       => '',
    'note'       => '',
];
$origName = '';
$origCityName = '';
$origCountryName = '';

function load_city_list(mysqli $conn): array {
    $out = [];
    $rs = $conn->query("SELECT city_id, city, country_id FROM city ORDER BY city");
    if ($rs) while ($r = $rs->fetch_assoc()) {
        $out[] = ['id' => (int)$r['city_id'], 'name' => (string)$r['city'], 'country_id' => (int)($r['country_id'] ?? 0)];
    }
    return $out;
}

function load_country_list(mysqli $conn): array {
    $out = [];
    $rs = $conn->query("SELECT country_id, country FROM country ORDER BY country");
    if ($rs) while ($r = $rs->fetch_assoc()) {
        $out[] = ['id' => (int)$r['country_id'], 'name' => (string)$r['country']];
    }
    return $out;
}

if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = $conn->prepare("SELECT f.name, f.address, f.addres_jur, f.phone, f.email,
                                   f.country_id, f.city_id, f.requisites, f.director,
                                   f.ogrn, f.nds, f.info, f.note,
                                   c.city AS city_name,
                                   co.country AS country_name
                            FROM firm f
                            LEFT JOIN city c ON c.city_id = f.city_id
                            LEFT JOIN country co ON co.country_id = f.country_id
                            WHERE f.firm_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) {
        $errors[] = 'Запись не найдена.';
    } else {
        $origName = (string)$r['name'];
        $origCityName = (string)$r['city_name'];
        $origCountryName = (string)$r['country_name'];
        $values['name'] = ($mode === 'copy') ? '' : (string)$r['name'];
        $values['address'] = (string)$r['address'];
        $values['addres_jur'] = (string)$r['addres_jur'];
        $values['phone'] = (string)$r['phone'];
        $values['email'] = (string)$r['email'];
        $values['country_id'] = (int)$r['country_id'];
        $values['city_id'] = (int)$r['city_id'];
        $values['requisites'] = (string)$r['requisites'];
        $values['director'] = (string)$r['director'];
        $values['ogrn'] = (string)$r['ogrn'];
        $values['nds'] = (string)$r['nds'];
        $values['info'] = (string)$r['info'];
        $values['note'] = (string)$r['note'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['name'] = (string)($_POST['name'] ?? '');
    $values['address'] = (string)($_POST['address'] ?? '');
    $values['addres_jur'] = (string)($_POST['addres_jur'] ?? '');
    $values['phone'] = (string)($_POST['phone'] ?? '');
    $values['email'] = (string)($_POST['email'] ?? '');
    $values['country_id'] = (int)($_POST['country_id'] ?? 0);
    $values['city_id'] = (int)($_POST['city_id'] ?? 0);
    $values['requisites'] = (string)($_POST['requisites'] ?? '');
    $values['director'] = (string)($_POST['director'] ?? '');
    $values['ogrn'] = (string)($_POST['ogrn'] ?? '');
    $values['nds'] = (string)($_POST['nds'] ?? '');
    $values['info'] = (string)($_POST['info'] ?? '');
    $values['note'] = (string)($_POST['note'] ?? '');

    if ($mode !== 'delete' && trim($values['name']) === '') {
        $errors[] = 'Поле «Наименование» обязательно для заполнения.';
        $focusField = 'name';
    }

    if (empty($errors)) {
        if ($mode === 'delete') {
            $stmt = $conn->prepare("DELETE FROM firm WHERE firm_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $conn->query("DELETE FROM marks WHERE tbl = 'firm' AND row_id = " . (int)$id);
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode]);
                exit;
            }
            header('Location: firm.php');
            exit;
        }
        if ($mode === 'new' || $mode === 'copy') {
            $stmt = $conn->prepare("INSERT INTO firm (name, address, addres_jur, phone, email, country_id, city_id, requisites, director, ogrn, nds, info, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            bind_auto($stmt, [$values['name'], $values['address'], $values['addres_jur'], $values['phone'],
                $values['email'], $values['country_id'], $values['city_id'], $values['requisites'],
                $values['director'], $values['ogrn'], $values['nds'], $values['info'], $values['note']]);
            if (!$stmt->execute()) {
                $errors[] = 'Ошибка БД: ' . $stmt->error;
            }
            $newId = (int)$conn->insert_id;
            $stmt->close();
            if (empty($errors) && $isAjax) {
                $sort = get_current_sort(['col' => 'id', 'dir' => 'asc']);
                $pageOfNew = computePageOfNew($conn, 'firm', 'firm_id', $sort['col'], $sort['dir'], $newId, $newId, '');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $newId, 'name' => $values['name'], 'page' => $pageOfNew]);
                exit;
            }
            if (empty($errors)) {
                header('Location: firm.php');
                exit;
            }
        }
        if ($mode === 'edit') {
            $stmt = $conn->prepare("UPDATE firm SET name = ?, address = ?, addres_jur = ?, phone = ?, email = ?, country_id = ?, city_id = ?, requisites = ?, director = ?, ogrn = ?, nds = ?, info = ?, note = ? WHERE firm_id = ?");
            bind_auto($stmt, [$values['name'], $values['address'], $values['addres_jur'], $values['phone'],
                $values['email'], $values['country_id'], $values['city_id'], $values['requisites'],
                $values['director'], $values['ogrn'], $values['nds'], $values['info'], $values['note'],
                $id]);
            if (!$stmt->execute()) {
                $errors[] = 'Ошибка БД: ' . $stmt->error;
            }
            $stmt->close();
            if (empty($errors) && $isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $id, 'name' => $values['name']]);
                exit;
            }
            if (empty($errors)) {
                header('Location: firm.php');
                exit;
            }
        }
    }
}

$cities = load_city_list($conn);
$countries = load_country_list($conn);

$cityCountryMap = [];
foreach ($cities as $c) {
    $cityCountryMap[$c['id']] = ['country_id' => $c['country_id']];
}
foreach ($countries as $c) {
    $cityCountryMap[$c['id']] ??= ['country_id' => 0];
    // build reverse lookup for city country names
}
$countryById = [];
foreach ($countries as $c) $countryById[$c['id']] = $c['name'];

$currentCityName = '';
foreach ($cities as $c) {
    if ($c['id'] === (int)$values['city_id']) { $currentCityName = $c['name']; break; }
}
if ($currentCityName === '' && $origCityName !== '' && (int)$values['city_id'] > 0) {
    $currentCityName = $origCityName;
}
$currentCountryName = '';
foreach ($countries as $c) {
    if ($c['id'] === (int)$values['country_id']) { $currentCountryName = $c['name']; break; }
}
if ($currentCountryName === '' && $origCountryName !== '' && (int)$values['country_id'] > 0) {
    $currentCountryName = $origCountryName;
}

$titles = [
    'new'    => 'Фирма (новая)',
    'edit'   => 'Фирма: ' . $origName,
    'copy'   => 'Фирма: ' . $origName . ' (копия)',
    'delete' => 'Фирма: ' . $origName . ' (удаление)',
];
$pageTitle = $titles[$mode] ?? 'Экспорт в Excel';

$isReadonly = ($mode === 'delete');

ob_start();
?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><img src="img/firm.png" alt="" /> <?= h($pageTitle) ?></h2>
<form class="form<?= $mode === 'delete' ? ' form--delete' : '' ?>" method="post" action="firm_form.php" autocomplete="off" data-form-modal>
<?= render_input('hidden', 'mode', $mode) ?>
<?= render_input('hidden', 'id', $id) ?>

<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>

<?php if (3 > 5): /* top actions вЂ” currently always hidden */ ?>
<?= render_form_actions(
    $mode === 'delete'
        ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','formnovalidate'=>true,'tabindex'=>'-1'])]
        : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true,'tabindex'=>'-1']),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'firm.php', ['class'=>'btn-secondary','tabindex'=>'-1'])]
) ?>
<?php endif; ?>

<?= render_field('Наименование', render_input('text', 'name', $values['name'], [
        'id' => 'firm-name-input',
        'required' => !$isReadonly,
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    true,
    ['readonly' => $isReadonly]
) ?>

<?= render_field('Адрес',
    render_input('text', 'address', $values['address'], [
        'id' => 'firm-address',
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    false,
    ['wide' => true, 'readonly' => $isReadonly]
) ?>

<?= render_field('Юридический адрес',
    render_input('text', 'addres_jur', $values['addres_jur'], [
        'id' => 'firm-addres-jur',
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    false,
    ['wide' => true, 'readonly' => $isReadonly]
) ?>

<div class="field-row">
<?= render_field('Телефон',
    render_input('text', 'phone', $values['phone'], [
        'id' => 'firm-phone',
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    false,
    ['readonly' => $isReadonly]
) ?>
<?= render_field('Email',
    render_input('text', 'email', $values['email'], [
        'id' => 'firm-email',
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    false,
    ['readonly' => $isReadonly]
) ?>
</div>

<div class="field-row">
<?= render_field('Город',
    render_lookup('city', 'city_id', $values['city_id'], $currentCityName,
        h(json_encode($cities, JSON_UNESCAPED_UNICODE)),
        'city_form.php?mode=new', $isReadonly, ['id' => 'firm-city-id']),
    true,
    ['readonly' => $isReadonly]
) ?>
<?= render_field('Страна',
    render_lookup('country', 'country_id', $values['country_id'], $currentCountryName,
        h(json_encode($countries, JSON_UNESCAPED_UNICODE)),
        'country_form.php?mode=new', $isReadonly, ['id' => 'firm-country-id']),
    false,
    ['readonly' => $isReadonly]
) ?>
</div>

<?= render_field('Реквизиты',
    render_input('text', 'requisites', $values['requisites'], [
        'id' => 'firm-requisites',
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    false,
    ['wide' => true, 'readonly' => $isReadonly]
) ?>

<?= render_field('Директор',
    render_input('text', 'director', $values['director'], [
        'id' => 'firm-director',
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    false,
    ['readonly' => $isReadonly]
) ?>

<div class="field-row">
<?= render_field('ОГРН',
    render_input('text', 'ogrn', $values['ogrn'], [
        'id' => 'firm-ogrn',
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    false,
    ['readonly' => $isReadonly]
) ?>
<?= render_field('НДС (%)',
    render_input('text', 'nds', $values['nds'], [
        'id' => 'firm-nds',
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    false,
    ['readonly' => $isReadonly]
) ?>
</div>

<?= render_field('Дополнительно',
    render_input('text', 'info', $values['info'], [
        'id' => 'firm-info',
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    false,
    ['wide' => true, 'readonly' => $isReadonly]
) ?>

<?= render_field('Примечание', render_input('text', 'note', $values['note'], [
        'id' => 'firm-note-input',
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    false,
    ['wide' => true, 'readonly' => $isReadonly]
) ?>

<?= render_form_note() ?>

<?= render_form_actions(
    $mode === 'delete'
        ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'firm.php', ['class'=>'btn-secondary'])]
        : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'firm.php', ['class'=>'btn-secondary'])]
) ?>
</form>
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= h($pageTitle) ?></title>
  <link rel="stylesheet" href="app.css" />
  <style>
    .lookup-wrap { position: relative; max-width: 430px; }
  </style>
</head>
<body>
  <div class="page page--form">
    <?= $formHtml ?>
  </div>
  <script src="assets/lookup.js"></script>
  <script>
    var __cityCountryMap = <?= json_encode($cityCountryMap, JSON_NUMERIC_CHECK) ?>;
    var __countryById = <?= json_encode($countryById, JSON_UNESCAPED_UNICODE) ?>;

    (function () {
      var cityRoot = document.querySelector('[data-lookup="city"]');
      var countryRoot = document.querySelector('[data-lookup="country"]');

      if (!cityRoot || !countryRoot) return;

      var cityList = JSON.parse(cityRoot.getAttribute('data-countries') || '[]');
      var countryList = JSON.parse(countryRoot.getAttribute('data-countries') || '[]');
      var cityReadonly = cityRoot.querySelector('.lookup-input') && cityRoot.querySelector('.lookup-input').hasAttribute('readonly');
      var countryReadonly = countryRoot.querySelector('.lookup-input') && countryRoot.querySelector('.lookup-input').hasAttribute('readonly');

      function autoFillCountry(cityId) {
        cityId = parseInt(cityId, 10);
        var entry = __cityCountryMap[cityId];
        if (entry && entry.country_id > 0) {
          countryLookup.inputId.value = entry.country_id;
          countryLookup.input.value = __countryById[entry.country_id] || '';
        } else {
          countryLookup.inputId.value = 0;
          countryLookup.input.value = '';
        }
      }

      var countryLookup = bindLookup({ root: countryRoot, data: countryList, readonly: countryReadonly });
      var cityLookup = bindLookup({
        root: cityRoot,
        data: cityList,
        readonly: cityReadonly,
        onSelect: autoFillCountry
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !cityLookup.pop.classList.contains('open')) {
          e.preventDefault();
          if (window.parent && window.parent !== window) {
            try { window.parent.postMessage({ type: 'form-cancel' }, '*'); } catch (err) {}
          } else {
            window.location.href = 'firm.php';
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
        'select:not([tabindex="-1"]):not([disabled])'
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
</body>
</html>
