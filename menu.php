<?php
// Shared top menu. Set $activeMenu to the current page filename (e.g. 'country.php')
// BEFORE including this file to highlight the active submenu link.
$activeMenu = isset($activeMenu) ? (string)$activeMenu : '';
// Page-to-object mapping for access control (dostup_flag)
$menuAccessMap = [
    'role.php'      => 'Role',
    'sotr.php'      => 'Sotr',
    'object.php'    => 'Object',
    'store.php'     => 'Store',
    'client.php'    => 'Client',
    'contact.php'   => 'Contact',
    'country.php'   => 'Country',
    'city.php'      => 'City',
    'invoice.php'   => 'Invoice',
    'plat.php'      => 'Plat',
    'tmc.php'       => 'Product',
    'service.php'   => 'Product',
    'group.php'     => 'Group',
    'categ.php'     => 'Group',
    'groupserv.php' => 'Group',
    'sgroup.php'    => 'Group',
    'izgot.php'     => 'Izgot',
    'unit.php'      => 'Unit',
    'repmenu.php'   => 'RepMenu',
    'zat.php'       => 'Zat',
    'sale.php'      => 'Docum',
    'ainv.php'      => 'Docum',
    'setup_form.php'=> 'Setup',
];
$menuDisableCache = [];
$menuAttr = function(string $href) use ($activeMenu, $menuAccessMap, &$menuDisableCache): string {
    $base = strtolower(strtok($href, '?'));
    // active check
    $activeBase = strtolower(strtok($activeMenu, '?'));
    $isActive = ($activeBase === $base);
    if ($isActive && strstr($href, '?')) {
        $curParams = [];
        $curQuery = strstr($_SERVER['REQUEST_URI'] ?? '', '?');
        if ($curQuery !== false) parse_str($curQuery, $curParams);
        $wantParams = [];
        parse_str(substr(strstr($href, '?'), 1), $wantParams);
        foreach ($wantParams as $k => $v) {
            if (!isset($curParams[$k]) || $curParams[$k] !== $v) { $isActive = false; break; }
        }
    }
    // disabled check
    $disabled = false;
    if (!isset($menuDisableCache[$base])) {
        $obj = $menuAccessMap[$base] ?? null;
        if ($obj && !empty($GLOBALS['CurRoleID'])) {
            $flags = get_access_flags($GLOBALS['conn'], $obj);
            $menuDisableCache[$base] = !empty($flags['dostup_flag']);
        } else {
            $menuDisableCache[$base] = false;
        }
    }
    $disabled = $menuDisableCache[$base];
    // build class attribute
    $classes = [];
    if ($isActive) $classes[] = 'active';
    if ($disabled) $classes[] = 'menu-disabled';
    return $classes ? ' class="' . implode(' ', $classes) . '"' : '';
};
?>
<nav class="top-menu">
  <img src="img/app.png" alt="" width="32" height="32" style="margin-right:8px;" />
  <div class="menu-item">
    <span class="menu-link">Справочники</span>
    <div class="submenu">
      <a href="firm.php"<?= $menuAttr('firm.php') ?>>Фирмы</a>
      <a href="city.php"<?= $menuAttr('city.php') ?>>Города</a>
      <a href="country.php"<?= $menuAttr('country.php') ?>>Страны</a>
      <a href="store.php"<?= $menuAttr('store.php') ?>>Участки</a>
      <a href="zat.php"<?= $menuAttr('zat.php') ?>>Виды операций с деньгами</a>
      <a href="status.php"<?= $menuAttr('status.php') ?>>Состояния заявок</a>
      <a href="sotr.php"<?= $menuAttr('sotr.php') ?>>Сотрудники</a>
    </div>
  </div>
  <div class="menu-item">
    <span class="menu-link">Документы</span>
    <div class="submenu">
      <a href="invoice.php"<?= $menuAttr('invoice.php') ?>>Счета</a>
      <a href="invoice.php?kind=offer"<?= $menuAttr('invoice.php?kind=offer') ?>>Коммерческие предложения</a>
      <a href="plat.php"<?= $menuAttr('plat.php') ?>>Кассовая книга</a>
      <a href="ainv.php"<?= $menuAttr('ainv.php') ?>>Инвентаризация</a>
    </div>
  </div>
  <div class="menu-item">
    <span class="menu-link">Склад</span>
    <div class="submenu">
      <a href="sale.php?typeop=20"<?= $menuAttr('sale.php?typeop=20') ?>>Приход</a>
      <a href="sale.php?typeop=110"<?= $menuAttr('sale.php?typeop=110') ?>>Возврат поставщику</a>
      <a href="sale.php"<?= $menuAttr('sale.php') ?>>Продажа</a>
      <a href="sale.php?typeop=40"<?= $menuAttr('sale.php?typeop=40') ?>>Возврат от покупателя</a>
      <a href="sale.php?typeop=100"<?= $menuAttr('sale.php?typeop=100') ?>>Внутреннее перемещение</a>
      <a href="sale.php?typeop=127"<?= $menuAttr('sale.php?typeop=127') ?>>Списание</a>
    </div>
  </div>

  <div class="menu-item">
    <span class="menu-link">Контрагенты</span>
    <div class="submenu">
      <a href="client.php"<?= $menuAttr('client.php') ?>>Контрагенты</a>
      <a href="contact.php"<?= $menuAttr('contact.php') ?>>Контакты</a>
      <a href="cli_categ.php"<?= $menuAttr('cli_categ.php') ?>>Категории контрагентов</a>
      <a href="cli_tag.php"<?= $menuAttr('cli_tag.php') ?>>Виды деятельности</a>
      <a href="contype.php"<?= $menuAttr('contype.php') ?>>Виды контактов</a>
      <a href="promo.php"<?= $menuAttr('promo.php') ?>>Виды рекламы</a>
      <?php if ($RegcodeFlag ?? 0): ?>
      <a href="regcod.php"<?= $menuAttr('regcod.php') ?>>Рег. коды</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="menu-item">
    <span class="menu-link">Номенклатура</span>
    <div class="submenu">
      <a href="tmc.php"<?= $menuAttr('tmc.php') ?>>Товары и Услуги</a>
      <a href="categ.php"<?= $menuAttr('categ.php') ?>>Категории</a>
      <a href="group.php"<?= $menuAttr('group.php') ?>>Группы, подгруппы</a>
      <a href="izgot.php"<?= $menuAttr('izgot.php') ?>>Производители</a>
      <a href="unit.php"<?= $menuAttr('unit.php') ?>>Единицы измерения</a>
    </div>
  </div>
  <div class="menu-item">
    <span class="menu-link">Администрирование</span>
    <div class="submenu">
      <a href="#" onclick="__openFormModal('setup_form.php?mode=edit');return false;"<?= $menuAttr('setup_form.php') ?>>Параметры настройки</a>
      <a href="repmenu.php"<?= $menuAttr('repmenu.php') ?>>Настройка документов</a>
      <a href="role.php"<?= $menuAttr('role.php') ?>>Роли сотрудников</a>
      <a href="object.php"<?= $menuAttr('object.php') ?>>Объекты доступа</a>
      <a href="dostup.php"<?= $menuAttr('dostup.php') ?>>Права доступа</a>
    </div>
  </div>
<?php if (!empty($CurSotrID)): ?>
  <?php
  $stmt = $conn->prepare("SELECT login FROM sotr WHERE sotr_id = ?");
  $stmt->bind_param('i', $CurSotrID);
  $stmt->execute();
  $u = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $login = $u ? $u['login'] : '#'.$CurSotrID;
  ?>
  <div class="menu-item menu-user">
    <span class="menu-link user-login"><?= h($login) ?></span>
    <div class="submenu submenu-right">
      <a href="#" id="logout-btn">Выход</a>
    </div>
  </div>
<?php endif; ?>
</nav>
