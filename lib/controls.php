<?php
if (defined('CONTROLS_LOADED')) return;
define('CONTROLS_LOADED', true);

require_once __DIR__ . '/../config.php';

// -------- BUTTONS --------

function _render_btn_attrs(array $attrs): string {
    $html = '';
    foreach ($attrs as $k => $v) {
        if ($v === true) $html .= ' ' . $k;
        elseif ($v !== false && $v !== null) $html .= ' ' . $k . '="' . h($v) . '"';
    }
    return $html;
}

function _render_btn(string $tag, string $content, string $extraClass, array $attrs): string {
    $cls = $extraClass;
    if (!empty($attrs['class'])) $cls .= ' ' . $attrs['class'];
    unset($attrs['class']);
    $a = _render_btn_attrs($attrs);
    return '<' . $tag . ' class="' . trim($cls) . '"' . $a . '>' . $content . '</' . $tag . '>';
}

function render_btn_text(string $text, array $attrs = []): string {
    return _render_btn('button', h($text), 'btn', $attrs);
}

function render_btn_icon(string $icon, array $attrs = []): string {
    $img = '<img src="' . h($icon) . '" alt="" />';
    return _render_btn('button', $img, 'icon-btn', $attrs);
}

function render_btn_icon_text(string $icon, string $text, array $attrs = []): string {
    $content = '<img src="' . h($icon) . '" alt="" />' . h($text);
    return _render_btn('button', $content, '', $attrs);
}

function render_btn_text_icon(string $text, string $icon, array $attrs = []): string {
    $content = h($text) . '<img src="' . h($icon) . '" alt="" />';
    return _render_btn('button', $content, '', $attrs);
}

function render_btn_primary(string $icon, string $text, array $attrs = []): string {
    $cls = 'btn-primary';
    if (!empty($attrs['class'])) $cls .= ' ' . $attrs['class'];
    unset($attrs['class']);
    return render_btn_icon_text($icon, $text, $attrs + ['class' => $cls]);
}

function render_btn_danger(string $icon, string $text, array $attrs = []): string {
    $cls = 'btn-danger';
    if (!empty($attrs['class'])) $cls .= ' ' . $attrs['class'];
    unset($attrs['class']);
    return render_btn_icon_text($icon, $text, $attrs + ['class' => $cls]);
}

function render_btn_secondary_icon_text(string $icon, string $text, array $attrs = []): string {
    $cls = 'btn-secondary';
    if (!empty($attrs['class'])) $cls .= ' ' . $attrs['class'];
    unset($attrs['class']);
    return render_btn_icon_text($icon, $text, $attrs + ['class' => $cls]);
}

function render_btn_link(string $text, string $href, array $attrs = []): string {
    $attrs['href'] = $href;
    return _render_btn('a', h($text), 'btn', $attrs);
}

function render_btn_link_icon_text(string $icon, string $text, string $href, array $attrs = []): string {
    $cls = 'btn';
    if (!empty($attrs['class'])) $cls .= ' ' . $attrs['class'];
    unset($attrs['class']);
    $attrs['href'] = $href;
    $content = '<img src="' . h($icon) . '" alt="" />' . h($text);
    return _render_btn('a', $content, $cls, $attrs);
}

// -------- INPUTS --------

function render_input(string $type, string $name, $value = '', array $attrs = []): string {
    $attrs['type'] = $type;
    $attrs['name'] = $name;
    if ($value !== '' && $value !== null) $attrs['value'] = $value;
    if (empty($attrs['class']) && $type !== 'hidden' && $type !== 'checkbox' && $type !== 'radio') {
        $attrs['class'] = 'field-input';
    }
    if ($type === 'checkbox' || $type === 'radio') {
        $attrs['class'] = $attrs['class'] ?? '';
    }
    return '<input' . _render_btn_attrs($attrs) . ' />';
}

function render_textarea(string $name, $value = '', array $attrs = []): string {
    $attrs['name'] = $name;
    if (empty($attrs['class'])) $attrs['class'] = 'field-input';
    $a = _render_btn_attrs($attrs);
    return '<textarea' . $a . '>' . h($value) . '</textarea>';
}

function render_select(string $name, array $options, $selected = '', array $attrs = []): string {
    $attrs['name'] = $name;
    if (empty($attrs['class'])) $attrs['class'] = 'field-input';
    $a = _render_btn_attrs($attrs);
    $html = '<select' . $a . '>';
    foreach ($options as $opt) {
        if (is_array($opt)) {
            $val = $opt['value'] ?? '';
            $label = $opt['label'] ?? $val;
        } else {
            $val = $opt;
            $label = $opt;
        }
        $sel = (string)$val === (string)$selected ? ' selected' : '';
        $html .= '<option value="' . h($val) . '"' . $sel . '>' . h($label) . '</option>';
    }
    $html .= '</select>';
    return $html;
}

function render_checkbox(string $name, $value = '1', bool $checked = false, array $attrs = []): string {
    $attrs['type'] = 'checkbox';
    $attrs['name'] = $name;
    $attrs['value'] = $value;
    if ($checked) $attrs['checked'] = true;
    return '<input' . _render_btn_attrs($attrs) . ' />';
}

function render_radio(string $name, string $value, bool $checked = false, array $attrs = []): string {
    $attrs['type'] = 'radio';
    $attrs['name'] = $name;
    $attrs['value'] = $value;
    if ($checked) $attrs['checked'] = true;
    return '<input' . _render_btn_attrs($attrs) . ' />';
}

// -------- LOOKUP (select with search from DB table) --------

function render_lookup(string $tableName, string $hiddenName, $hiddenValue, string $labelValue,
                       string $listJson, string $addUrl, bool $readonly = false, array $attrs = []): string {
    $id = $attrs['id'] ?? $hiddenName;
    unset($attrs['id']);
    $extraHtml = '';
    foreach ($attrs as $k => $v) {
        if ($v === null || $v === false) continue;
        $extraHtml .= ' ' . h($k) . '="' . h($v) . '"';
    }
    $labelId = $id . '-label';
    $html = '<div class="lookup lookup-wrap" data-lookup="' . h($tableName) . '"'
          . ' data-countries=\'' . $listJson . '\'' . $extraHtml . ($readonly ? ' data-readonly="1"' : '') . '>';
    $html .= '<input class="lookup-input" id="' . h($labelId) . '" name="' . h($tableName) . '_name" type="text"'
           . ' value="' . h($labelValue) . '" autocomplete="off" tabindex="-1" readonly />';
    $html .= '<input type="hidden" id="' . h($id) . '" name="' . h($hiddenName) . '" value="' . h($hiddenValue) . '" data-lookup-id />';
    $html .= '<div class="lookup-tools"' . ($readonly ? ' style="display:none"' : '') . '>';
    $html .= '<button class="lookup-tool clear" type="button" title="Очистить" data-lookup-clear>×</button>';
    $html .= '<button class="lookup-tool" type="button" title="Открыть поиск" data-lookup-open>▾</button>';
    $html .= '<a class="lookup-tool add" href="' . h($addUrl) . '" target="_blank" data-lookup-add="' . h($tableName) . '" title="Добавить">+</a>';
    $html .= '</div>';
    $html .= '<div class="lookup-pop" data-lookup-pop>';
    $html .= '<input class="lookup-pop-search" type="text" data-lookup-search placeholder="Поиск…" />';
    $html .= '<div class="lookup-pop-list" data-lookup-list></div>';
    $html .= '</div></div>';
    return $html;
}

// -------- FIELD WRAPPER --------

function render_field(string $label, string $controlHtml, bool $required = false, array $opts = []): string {
    $wide = !empty($opts['wide']);
    $readonly = !empty($opts['readonly']);
    $labelFor = $opts['for'] ?? '';
    $cls = 'field';
    if ($wide) $cls .= ' field--wide';
    else $cls .= ' field--narrow';
    if ($readonly) $cls .= ' field--readonly';
    $html = '<div class="' . $cls . '">';
    $html .= '<label class="field-label"' . ($labelFor ? ' for="' . h($labelFor) . '"' : '') . '>'
           . h($label)
           . ($required ? '<span class="required">*</span>' : '')
           . '</label>';
    $html .= $controlHtml;
    $html .= '</div>';
    return $html;
}

// -------- FORM HELPERS --------

function render_form_actions(array $buttons, string $position = 'bottom'): string {
    $html = '<div class="form-actions form-actions--' . h($position) . '">';
    foreach ($buttons as $btn) {
        $html .= $btn;
    }
    $html .= '</div>';
    return $html;
}

function render_form_note(): string {
    return '<div class="form-note">* — Обязательное поле</div>';
}

// -------- COLOR PICKER --------

$GLOBALS['_color_picker_modal_rendered'] = false;

function render_color_picker_modal(): string {
    if (!empty($GLOBALS['_color_picker_modal_rendered'])) return '';
    $GLOBALS['_color_picker_modal_rendered'] = true;
    return '<div class="color-picker-modal" id="color-picker-modal">
  <div class="color-picker-modal-backdrop"></div>
  <div class="color-picker-modal-content">
    <div class="color-picker-modal-header">
      <span>Выберите цвет</span>
      <button type="button" class="color-picker-modal-close" id="color-picker-modal-close">&times;</button>
    </div>
    <div class="color-picker-grid" id="color-picker-grid"></div>
    <div class="color-picker-footer">
      <button type="button" class="color-picker-custom-btn" id="color-picker-custom">Другой цвет\u2026</button>
      <button type="button" class="color-picker-clear-btn" id="color-picker-clear">Сбросить</button>
    </div>
    <input type="color" id="color-picker-native" style="display:none" />
  </div>
</div>';
}

function render_color_picker(string $name, string $value = '', bool $readonly = false, array $attrs = []): string {
    $id = $attrs['id'] ?? $name;
    $bg = $value ? h($value) : '#f0f0f0';
    $html = '<div class="color-picker-wrap">';
    $html .= '<input type="hidden" name="' . h($name) . '" id="' . h($id) . '" value="' . h($value) . '" data-color-picker />';
    $html .= '<div class="color-rect' . ($value ? '' : ' color-rect--empty') . '" data-color-rect style="background-color:' . $bg . '"'
           . ($readonly ? '' : '') . '></div>';
    $html .= '<button type="button" class="color-picker-btn" data-color-btn title="Выбрать цвет"' . ($readonly ? ' disabled' : '') . '>'
           . '<svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M17.66 7.93L12 2.27 6.34 7.93c-3.12 3.12-3.12 8.19 0 11.31C7.9 20.8 9.95 21.58 12 21.58c2.05 0 4.1-.78 5.66-2.34 3.12-3.12 3.12-8.19 0-11.31zM12 19.59c-1.6 0-3.11-.62-4.24-1.76C6.62 16.69 6 15.19 6 13.59s.62-3.11 1.76-4.24L12 5.1v14.49z"/></svg>'
           . '</button>';
    $html .= '</div>';
    return $html;
}
