/**
 * Стандартные рендеры для вложенных таблиц «Аренда»:
 *   - иконки флагов (rezerv_flag/voz_flag → status)
 *   - время без секунд (hours → чч:мм)
 *   - числа без хвостовых нулей (days/months/sum/sum_zalog/discount/sum_discount)
 * Вызывается после initD2Table(); не требует конфигурации для новых таблиц.
 */
function applyArendaD2Renderers(tbl) {
  if (!tbl || !tbl.columns) return;
  var CHECK_SVG = '<svg class="check-icon" viewBox="0 0 24 24" width="16" height="16"><path fill="#27ae60" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>';
  var TIME_ICON = '<img src="img/time.png" alt="Резерв" width="27" height="27" style="display:block" />';
  var VOZ_ICON = '<img src="img/vozvr.png" alt="Возвращено" width="27" height="27" style="display:block" />';

  tbl.columns.forEach(function (c) {
    if (c.key === 'status' && !c.__arendaRender) {
      c.readonly = true;
      c.__arendaRender = true;
      c.render = function (v, item) {
        if (item.rezerv_flag == 1) return TIME_ICON;
        if (item.voz_flag == 1) return VOZ_ICON;
        return '';
      };
    }
    if (c.key === 'hours' && !c.__arendaRender) {
      c.__arendaRender = true;
      c.render = function (v) {
        var s = String(v == null ? '' : v).trim();
        if (s === '' || s === '00:00' || s === '00:00:00') return '';
        return s.substring(0, 5);
      };
    }
    if ((c.key === 'days' || c.key === 'months') && !c.__arendaRender) {
      c.__arendaRender = true;
      c.render = function (v) {
        var n = parseInt(String(v == null ? '0' : v), 10);
        return (isNaN(n) || n === 0) ? '' : String(n);
      };
    }
    if (['sum', 'sum_zalog', 'discount', 'sum_discount'].indexOf(c.key) >= 0 && !c.__arendaNum) {
      c.__arendaNum = true;
      c.render = function (v) {
        var n = parseFloat(String(v == null ? '0' : v).replace(',', '.'));
        if (isNaN(n) || n === 0) return '';
        var s = n.toFixed(2).replace(/\.?0+$/, '');
        return s;
      };
    }
  });
}
