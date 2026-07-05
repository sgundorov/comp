<?php
// Shared top menu. Set $activeMenu to the current page filename (e.g. 'country.php')
// BEFORE including this file to highlight the active submenu link.
$activeMenu = isset($activeMenu) ? (string)$activeMenu : '';
$isActive = function(string $href) use ($activeMenu): string {
    $base = strtolower(strtok($href, '?'));
    if (strtolower($activeMenu) !== $base) return '';
    $curTypeop = (int)($_GET['typeop'] ?? 120);
    $wantQuery = strstr($href, '?');
    if ($wantQuery === false) return $curTypeop === 120 ? ' class="active"' : '';
    parse_str(substr($wantQuery, 1), $wantParams);
    $wantTypeop = (int)($wantParams['typeop'] ?? 120);
    return $curTypeop === $wantTypeop ? ' class="active"' : '';
};
?>
<nav class="top-menu">
  <img src="img/app.png" alt="" width="32" height="32" style="margin-right:8px;" />
  <div class="menu-item">
    <span class="menu-link">Файлы</span>
    <div class="submenu">
      <a href="#" onclick="__openFormModal('setup_form.php?mode=edit');return false;"<?= $isActive('setup_form.php') ?>>Параметры настройки</a>
      <a href="repmenu.php"<?= $isActive('repmenu.php') ?>>Настройка документов</a>
    </div>
  </div>
  <div class="menu-item">
    <span class="menu-link">Документы</span>
    <div class="submenu">
      <a href="invo.php"<?= $isActive('invo.php') ?>>Счета</a>
      <a href="invoice.php?kind=offer"<?= $isActive('invoice.php?kind=offer') ?>>Коммерческие предложения</a>
      <a href="plat.php"<?= $isActive('plat.php') ?>>Кассовая книга</a>
    </div>
  </div>
  <div class="menu-item">
    <span class="menu-link">Склад</span>
    <div class="submenu">
      <a href="sale.php?typeop=20"<?= $isActive('sale.php?typeop=20') ?>>Приход</a>
      <a href="sale.php?typeop=110"<?= $isActive('sale.php?typeop=110') ?>>Возврат поставщику</a>
      <a href="sale.php"<?= $isActive('sale.php') ?>>Продажа</a>
      <a href="sale.php?typeop=10"<?= $isActive('sale.php?typeop=10') ?>>Возврат от покупателя</a>
      <a href="sale.php?typeop=100"<?= $isActive('sale.php?typeop=100') ?>>Внутреннее перемещение</a>
      <a href="sale.php?typeop=127"<?= $isActive('sale.php?typeop=127') ?>>Списание</a>
    </div>
  </div>
  <div class="menu-item">
    <span class="menu-link">Справочники</span>
    <div class="submenu">
      <a href="firm.php"<?= $isActive('firm.php') ?>>Фирмы</a>
      <a href="city.php"<?= $isActive('city.php') ?>>Города</a>
      <a href="country.php"<?= $isActive('country.php') ?>>Страны</a>
      <a href="store.php"<?= $isActive('store.php') ?>>Участки</a>
      <a href="zat.php"<?= $isActive('zat.php') ?>>Виды операций с деньгами</a>
      <a href="status.php"<?= $isActive('status.php') ?>>Состояния заявок</a>
      <a href="sotr.php"<?= $isActive('sotr.php') ?>>Сотрудники</a>
      <a href="role.php"<?= $isActive('role.php') ?>>Роли сотрудников</a>
      <a href="object.php"<?= $isActive('object.php') ?>>Объекты доступа</a>
    </div>
  </div>
  <div class="menu-item">
    <span class="menu-link">Контрагенты</span>
    <div class="submenu">
      <a href="client.php"<?= $isActive('client.php') ?>>Контрагенты</a>
      <a href="contact.php"<?= $isActive('contact.php') ?>>Контакты</a>
      <a href="cli_categ.php"<?= $isActive('cli_categ.php') ?>>Категории контрагентов</a>
      <a href="cli_tag.php"<?= $isActive('cli_tag.php') ?>>Виды деятельности</a>
      <a href="contype.php"<?= $isActive('contype.php') ?>>Виды контактов</a>
      <a href="promo.php"<?= $isActive('promo.php') ?>>Виды рекламы</a>
      <?php if ($RegcodeFlag ?? 0): ?>
      <a href="regcod.php"<?= $isActive('regcod.php') ?>>Рег. коды</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="menu-item">
    <span class="menu-link">Товары</span>
    <div class="submenu">
      <a href="tmc.php"<?= $isActive('tmc.php') ?>>Товары</a>
      <a href="categ.php"<?= $isActive('categ.php') ?>>Категории товаров</a>
      <a href="group.php"<?= $isActive('group.php') ?>>Группы, подгруппы</a>
      <a href="izgot.php"<?= $isActive('izgot.php') ?>>Производители</a>
      <a href="unit.php"<?= $isActive('unit.php') ?>>Единицы измерения</a>
    </div>
  </div>
  <div class="menu-item">
    <span class="menu-link">Услуги</span>
    <div class="submenu">
      <a href="service.php"<?= $isActive('service.php') ?>>Услуги</a>
      <a href="groupserv.php"<?= $isActive('groupserv.php') ?>>Группы услуг</a>
      <a href="sgroup.php?kind=service"<?= $isActive('sgroup.php?kind=service') ?>>Подгруппы услуг</a>
    </div>
  </div>
  <div class="menu-spacer"></div>
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
