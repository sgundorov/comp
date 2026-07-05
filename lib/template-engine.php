<?php
if (defined('TEMPLATE_ENGINE_LOADED')) return;
define('TEMPLATE_ENGINE_LOADED', true);

/**
 * Обрабатывает HTML-файл шаблона, подставляя значения переменных
 * и повторяя секции #D1...#1D для строк дочерних таблиц.
 *
 * Формат шаблона:
 *   #ИмяПеременной#       — подстановка значения
 *   #D1                    — начало строки дочерней таблицы (цикл)
 *   #1D                    — конец строки дочерней таблицы
 *
 * @param string $templatePath путь к HTML-файлу шаблона
 * @param array  $vars         одномерный ассоциативный массив переменных ['Имя' => 'значение']
 * @param array  $details      ассоциативный массив дочерних данных:
 *                             ключ = имя секции (по умолчанию 'D1'),
 *                             значение = массив строк (каждая строка — ассоциативный массив)
 * @return string              итоговый HTML
 */
function render_template(string $templatePath, array $vars = [], array $details = []): string {
    if (!file_exists($templatePath)) {
        return '<p style="color:red;">Шаблон не найден: ' . htmlspecialchars($templatePath) . '</p>';
    }

    $html = file_get_contents($templatePath);

    if (!isset($details['D1'])) {
        $details['D1'] = [];
    }

    $html = process_detail_blocks($html, $details);

    $html = replace_vars($html, $vars);

    return $html;
}

/**
 * Находит все блоки #D1...#1D и заменяет их на повторённые строки данных.
 */
function process_detail_blocks(string $html, array $details): string {
    $result = '';
    $pos = 0;
    $len = strlen($html);

    while ($pos < $len) {
        $blockStart = strpos($html, '#D1', $pos);

        if ($blockStart === false) {
            $result .= substr($html, $pos);
            break;
        }

        $result .= substr($html, $pos, $blockStart - $pos);

        $blockEnd = strpos($html, '#1D', $blockStart + 3);

        if ($blockEnd === false) {
            $result .= substr($html, $blockStart);
            break;
        }

        $blockContent = substr($html, $blockStart + 3, $blockEnd - $blockStart - 3);

        $rows = $details['D1'] ?? [];

        foreach ($rows as $row) {
            $rowHtml = replace_vars($blockContent, $row);
            $result .= $rowHtml;
        }

        $pos = $blockEnd + 3;
    }

    return $result;
}

/**
 * Заменяет все плейсхолдеры #Имя# на соответствующие значения.
 * Если значение не найдено — заменяет на пустую строку.
 */
function replace_vars(string $html, array $vars): string {
    $keys = array_keys($vars);
    if (empty($keys)) return $html;

    $search = [];
    $replace = [];
    foreach ($keys as $k) {
        $search[] = '#' . $k . '#';
        $replace[] = (string)$vars[$k];
    }

    return str_replace($search, $replace, $html);
}
