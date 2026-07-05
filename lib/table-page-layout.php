<?php
if (defined('TABLE_PAGE_LAYOUT_LOADED')) return;
define('TABLE_PAGE_LAYOUT_LOADED', true);

/**
 * Строит query-string для ссылок экспорта/печати с сохранением текущих
 * параметров поиска, сортировки и фильтров.
 */
function build_export_qs(array $preserve, string $search, bool $searchActive, array $searchCols, string $searchCond, string $sortQs, string $countryFilter = ''): string {
    $params = [];
    if ($searchActive && $search !== '') $params['q'] = $search;
    if ($searchActive && count($searchCols) > 0) $params['cols'] = implode(',', $searchCols);
    if ($searchActive) $params['cond'] = $searchCond;
    if ($searchActive) $params['sf'] = '1';
    if ($sortQs !== '') $params['sort'] = $sortQs;
    if ($countryFilter !== '') {
        $field = $preserve[0] ?? 'country_id';
        $params[$field] = $countryFilter;
    }
    return http_build_query($params);
}

/**
 * Строит callback для пагинации (baseQs).
 */
function build_pagination_qs(callable $fn): string {
    return $fn;
}

/**
 * Генерирует HTML элементов выпадающего меню экспорта.
 *
 * @param array $formats  [['fmt'=>'csv','filename'=>'Города.csv','label'=>'Экспорт в CSV'], ...]
 */
function render_export_dropdown_items(array $formats, string $exportQs): string {
    $html = '';
    foreach ($formats as $item) {
        $fullUrl = $item['url'] ?? '';
        $label   = $item['label'] ?? $item['fmt'];
        $filename = $item['filename'] ?? '';
        $format   = $item['format'] ?? strtoupper($item['fmt']);
        $html .= '<a class="dropdown-item" href="#" data-export-url="' . h($fullUrl) . '" data-export-filename="' . h($filename) . '" data-export-format="' . h($format) . '">' . h($label) . '</a>';
    }
    return $html;
}

/**
 * Генерирует HTML элементов выпадающего меню печати.
 */
function render_print_dropdown_items(string $pageUrl, string $exportQs, int $page, bool $hasSelected = false): string {
    $printQs = $exportQs !== '' ? '?' . $exportQs : '';
    $allQs = $exportQs !== '' ? '?all=1&' . $exportQs : '?all=1';
    return
        '<a class="dropdown-item" href="' . h($pageUrl) . '_print.php' . $printQs . '" target="_blank">Все записи</a>' .
        '<a class="dropdown-item" href="' . h($pageUrl) . '_print.php' . $allQs . '" target="_blank">Выбранные</a>' .
        '<a class="dropdown-item" href="' . h($pageUrl) . '_print.php?onlyPage=1&pageNum=' . (int)$page . ($exportQs !== '' ? '&' . $exportQs : '') . '" target="_blank">Текущая страница</a>';
}

/**
 * Генерирует URL для стрелки пагинации.
 */
function build_page_url(string $baseUrl, int $page, string $search, bool $searchActive, array $searchCols, string $searchCond, string $sortQs, string $countryFilter = '', string $countryField = 'country_id'): string {
    $qs = ['page' => $page];
    if ($searchActive) {
        if ($search !== '') $qs['q'] = $search;
        if (count($searchCols) > 0) $qs['cols'] = implode(',', $searchCols);
        $qs['cond'] = $searchCond;
        $qs['sf'] = '1';
    }
    if ($countryFilter !== '') $qs[$countryField] = $countryFilter;
    if ($sortQs !== '') $qs['sort'] = $sortQs;
    return $baseUrl . '?' . http_build_query($qs);
}
