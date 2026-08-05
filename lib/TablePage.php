<?php
if (!defined('TABLEPAGE_LOADED')) {
    define('TABLEPAGE_LOADED', true);

    require_once __DIR__ . '/TableComponent.php';

    class TablePage extends TableComponent {
        public $table;
        public $key;
        public $searchColExprs = [];
        public $defaultSearchCols = [];
        public $defaultSort = ['col' => 'id', 'dir' => 'asc'];
        public $marksSessionKey;
        public $marksTbl;
        public $selectSql;
        public $countSql;
        public $idSelectSql;
        public $allowedConds = ['contains', 'not_contains', 'starts_with', 'ends_with', 'equals', 'not_equals', 'gt', 'lt'];
        public $colFilters = [];
        public $countryFilterField = null;
        public $countryFilterExpr = null;
        public $keyExpr = null;
        public $selectExtra = '';

        public $page = 1;
        public $search = '';
        public $searchActive = false;
        public $searchCols = [];
        public $searchCond = 'contains';
        public $sortLevels = [];
        public $sortIsDefault = true;
        public $sortQs = '';
        public $orderBy = '';
        public $offset = 0;
        public $showOnly = false;
        public $marks = [];
        public $marksCount = 0;
        public $marksFilterIds = [];
        public $marksFilterActive = false;
        public $countryFilter = '';
        public $countryIds = [];
        public $countryNames = [];
        public $filters = [];

        public $where = '';
        public $params = [];
        public $types = '';
        public $total = 0;
        public $pages = 1;
        public $debugCountSql = '';
        public $debugSelectSql = '';
        public $debugSelectError = '';

        public $baseUrl = 'city.php';

        public $rows = [];
        public $formPrefix = '';

        public function __construct(mysqli $conn, array $config) {
            parent::__construct($config);

            $this->table           = $config['table'];
            $this->key             = $config['key'];
            $this->searchColExprs  = $config['search_cols'] ?? [];
            $this->defaultSearchCols = array_keys($this->searchColExprs);
            $this->defaultSort     = $config['default_sort'] ?? ['col' => 'id', 'dir' => 'asc'];
            $this->marksSessionKey = $config['marks_session'] ?? ($this->table . '_select');
            $this->marksTbl        = $config['marks_tbl'] ?? $this->table;
            $this->columnVisibilityTbl = $config['column_visibility_tbl'] ?? $this->marksTbl;
            $this->selectSql       = $config['select_sql'] ?? null;
            $this->countSql        = $config['count_sql'] ?? null;
            $this->idSelectSql     = $config['id_select_sql'] ?? null;
            $this->colFilters      = $config['col_filters'] ?? [];
            $this->countryFilterField = $config['country_filter_field'] ?? null;
            $this->countryFilterExpr  = $config['country_filter_expr']  ?? null;
            $this->keyExpr             = $config['key_expr']             ?? null;
            $this->baseUrl         = $config['base_url'] ?? ($this->table . '.php');
            $this->selectExtra     = $config['select_extra'] ?? '';

            $this->parseRequest();
            $this->parseSort();
            $this->loadColumnsConfig($conn);
            $this->loadColumnWidths($conn);
            $this->loadMarks($conn);
            $this->loadSession();
            if ($this->showOnly && $this->marksCount === 0) {
                $this->showOnly = false;
                $_SESSION[$this->marksSessionKey]['show_only'] = false;
            }
            $this->parseMarksRequest();
            $this->parseCountryFilter();
            $this->buildWhere();
            $this->buildOrderBy();
            $this->buildFilters();
        }

        protected function parseRequest() {
            $this->page         = max(1, (int)($_GET['page'] ?? 1));
            $this->search       = trim((string)($_GET['q'] ?? ''));
            $this->searchActive = ((string)($_GET['sf'] ?? '0') === '1');
            $this->searchCond   = (string)($_GET['cond'] ?? 'contains');
            if (!in_array($this->searchCond, $this->allowedConds, true)) $this->searchCond = 'contains';

            $this->searchCols = [];
            $rawCols = (string)($_GET['cols'] ?? '');
            if ($rawCols !== '') {
                $this->searchCols = array_values(array_filter(
                    array_map('trim', explode(',', $rawCols)),
                    function ($k) { return isset($this->searchColExprs[$k]); }
                ));
            }
            if (!$this->searchActive) {
                $this->search = '';
                $this->searchCols = [];
            }
            if ($this->searchActive && $this->search !== '' && count($this->searchCols) === 0) {
                $this->searchCols = $this->defaultSearchCols;
            }
            $this->offset = ($this->page - 1) * PAGE_SIZE;
        }

        protected function parseSort() {
            $this->sortLevels = [];
            $sortRaw = trim((string)($_GET['sort'] ?? ''));
            if ($sortRaw !== '') {
                foreach (explode(',', $sortRaw) as $lv) {
                    $pp = explode(':', $lv);
                    $ck = $pp[0] ?? '';
                    $dk = strtolower($pp[1] ?? 'asc');
                    if (isset($this->colMeta[$ck]) && in_array($dk, ['asc','desc'], true)) {
                        $this->sortLevels[] = ['col' => $ck, 'dir' => $dk];
                    }
                }
            }
            if (count($this->sortLevels) === 0) {
                $this->sortLevels = [['col' => $this->defaultSort['col'], 'dir' => $this->defaultSort['dir']]];
            }
            $this->sortIsDefault = (count($this->sortLevels) === 1
                && $this->sortLevels[0]['col'] === $this->defaultSort['col']
                && $this->sortLevels[0]['dir'] === $this->defaultSort['dir']);
            $this->sortQs = $this->sortIsDefault
                ? ''
                : implode(',', array_map(function ($l) { return $l['col'] . ':' . $l['dir']; }, $this->sortLevels));
        }

        protected function buildOrderBy() {
            $colToExpr = [];
            foreach ($this->colMeta as $n => $m) {
                if (!empty($m['sort_expr'])) $colToExpr[$n] = $m['sort_expr'];
            }
            $parts = [];
            foreach ($this->sortLevels as $sl) {
                if (!isset($colToExpr[$sl['col']])) continue;
                $parts[] = $colToExpr[$sl['col']] . ' ' . strtoupper($sl['dir']);
            }
            $this->orderBy = count($parts) > 0 ? implode(', ', $parts) : $this->defaultSort['col'] . ' ' . strtoupper($this->defaultSort['dir']);
        }

        protected function loadMarks(mysqli $conn) {
            $this->marks      = load_marks_set($conn, $this->marksTbl);
            $this->marksCount = count_marks($conn, $this->marksTbl);
        }

        protected function loadSession() {
            if (!isset($_SESSION[$this->marksSessionKey]) || !is_array($_SESSION[$this->marksSessionKey])) {
                $_SESSION[$this->marksSessionKey] = ['show_only' => false];
            }
            $this->showOnly = (bool)($_SESSION[$this->marksSessionKey]['show_only'] ?? false);
        }

        protected function parseMarksRequest() {
            $this->marksFilterIds = [];
            $this->marksFilterActive = false;

            $idsParam = trim((string)($_GET['ids'] ?? ''));
            if ($idsParam !== '') {
                $ids = array_values(array_filter(array_map('intval', explode(',', $idsParam)), fn($v) => $v > 0));
                if (count($ids) > 0) {
                    $this->marksFilterIds = $ids;
                    $this->marksFilterActive = true;
                }
                return;
            }

            $onlySelected = ((string)($_GET['all'] ?? '0') === '1');
            if ($onlySelected) {
                $ids = array_keys($this->marks);
                if (count($ids) > 0) {
                    $this->marksFilterIds = $ids;
                    $this->marksFilterActive = true;
                }
            }
        }

        protected function parseCountryFilter() {
            $this->countryFilter = $this->countryFilterField ? (string)($_GET[$this->countryFilterField] ?? '') : '';
            $this->countryIds = [];
            if ($this->countryFilter !== '' && $this->countryFilterField) {
                $this->countryIds = array_values(array_filter(
                    array_map('intval', explode(',', $this->countryFilter)),
                    fn($v) => $v > 0
                ));
            }
        }

        public function buildWhere() {
            $this->where = '';
            $this->params = [];
            $this->types  = '';

            if ($this->search !== '' && count($this->searchCols) > 0) {
                $condToOp = [
                    'contains'     => 'LIKE',
                    'not_contains' => 'NOT LIKE',
                    'starts_with'  => 'LIKE',
                    'ends_with'    => 'LIKE',
                    'equals'       => '=',
                    'not_equals'   => '<>',
                    'gt'           => '>',
                    'lt'           => '<',
                ];
                $op = $condToOp[$this->searchCond] ?? 'LIKE';
                $parts = [];
                foreach ($this->searchCols as $col) {
                    if (!isset($this->searchColExprs[$col])) continue;
                    $parts[] = $this->searchColExprs[$col] . ' ' . $op . ' ?';
                    switch ($this->searchCond) {
                        case 'contains':     $this->params[] = '%' . $this->search . '%'; break;
                        case 'not_contains': $this->params[] = '%' . $this->search . '%'; break;
                        case 'starts_with':  $this->params[] = $this->search . '%'; break;
                        case 'ends_with':    $this->params[] = '%' . $this->search; break;
                        case 'equals':       $this->params[] = $this->search; break;
                        case 'not_equals':   $this->params[] = $this->search; break;
                        case 'gt':           $this->params[] = $this->search; break;
                        case 'lt':           $this->params[] = $this->search; break;
                        default:             $this->params[] = '%' . $this->search . '%';
                    }
                    $this->types .= 's';
                }
                if (count($parts) > 0) {
                    $this->where = '(' . implode(' OR ', $parts) . ')';
                }
            }

            if (count($this->countryIds) > 0) {
                $place = implode(',', array_fill(0, count($this->countryIds), '?'));
                $colExpr = $this->countryFilterExpr ?? ($this->table . '.' . $this->key);
                $extra = $colExpr . ' IN (' . $place . ')';
                $this->appendWhere($extra, $this->countryIds, str_repeat('i', count($this->countryIds)));
            }

            if ($this->showOnly) {
                $colExpr = $this->keyExpr ?? $this->key;
                $extra = $colExpr . " IN (SELECT row_id FROM marks WHERE tbl = '" . $this->marksTbl . "')";
                $this->appendWhereRaw($extra);
            }

            if ($this->marksFilterActive) {
                $colExpr = $this->keyExpr ?? $this->key;
                $place = implode(',', array_fill(0, count($this->marksFilterIds), '?'));
                $extra = "$colExpr IN ($place)";
                $this->appendWhere($extra, $this->marksFilterIds, str_repeat('i', count($this->marksFilterIds)));
            }
        }

        public function appendWhere($extra, array $addParams, string $addTypes) {
            $this->params = array_merge($this->params, $addParams);
            $this->types  = $this->types . $addTypes;
            $this->where  = $this->where === '' ? $extra : $this->where . ' AND ' . $extra;
        }

        public function appendWhereRaw($extra) {
            $this->where = $this->where === '' ? $extra : $this->where . ' AND ' . $extra;
        }

        public function applyFilterWithLabel(mysqli $conn, string $getParam, string $colExpr, string $label, string $lookupTable, string $lookupIdCol, string $lookupNameCol): void {
            $raw = (string)($_GET[$getParam] ?? '');
            if ($raw === '') return;
            $ids = array_values(array_filter(array_map('intval', explode(',', $raw)), fn($v) => $v > 0));
            if (count($ids) === 0) return;
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $this->appendWhere("$colExpr IN ($ph)", $ids, str_repeat('i', count($ids)));
            $ph2 = implode(',', array_fill(0, count($ids), '?'));
            $stmt = @mysqli_prepare($conn, "SELECT $lookupIdCol, $lookupNameCol FROM $lookupTable WHERE $lookupIdCol IN ($ph2)");
            $names = [];
            if ($stmt) {
                $refs = [];
                foreach ($ids as $k => $v) { $refs[$k] = &$ids[$k]; }
                $stmt->bind_param(str_repeat('i', count($ids)), ...$refs);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) while ($r = $res->fetch_assoc()) $names[] = (string)$r[$lookupNameCol];
                $stmt->close();
            }
            $this->filters[] = ['kind' => 'col_filter', 'text' => "$label = " . (count($names) > 0 ? implode(', ', $names) : implode(',', $ids)), 'clear' => $getParam];
        }

        public function whereSql() {
            return $this->where === '' ? '' : 'WHERE ' . $this->where;
        }

        public function buildFilters() {
            $colFilterChips = [];
            foreach ($this->filters as $f) {
                if (($f['kind'] ?? '') === 'col_filter') $colFilterChips[] = $f;
            }
            $this->filters = $colFilterChips;
            if ($this->searchActive && $this->search !== '') {
                $condLabels = [
                    'contains' => 'содержит', 'not_contains' => 'не содержит',
                    'starts_with' => 'начинается с', 'ends_with' => 'заканчивается на',
                    'equals' => 'равно', 'not_equals' => 'не равно',
                    'gt' => 'больше', 'lt' => 'меньше',
                ];
                $condLabel = $condLabels[$this->searchCond] ?? 'содержит';
                $colLabels = [];
                foreach ($this->searchCols as $sc) {
                    $m = $this->colMeta[$sc] ?? null;
                    $colLabels[] = $m ? $m['label'] : $sc;
                }
                $colStr = implode(', ', $colLabels);
                $this->filters[] = ['kind' => 'search', 'text' => "$colStr $condLabel «{$this->search}»", 'clear' => ['q','cols','cond','sf','page']];
            }
            if ($this->showOnly) {
                $this->filters[] = ['kind' => 'show_only', 'text' => 'Показаны только выбранные', 'clear' => null];
            }
            if ($this->marksFilterActive) {
                $this->filters[] = ['kind' => 'marks', 'text' => 'Только отмеченные (' . count($this->marksFilterIds) . ')', 'clear' => null];
            }
            if (count($this->countryIds) > 0) {
                $names = [];
                foreach ($this->countryIds as $cid) {
                    $names[] = $this->countryNames[$cid] ?? ('#' . $cid);
                }
                $this->filters[] = ['kind' => 'country', 'text' => 'Страна = ' . implode(', ', $names), 'clear' => $this->countryFilterField];
            }
        }

        public function getFilterDescription(): array {
            return array_map(fn($f) => $f['text'], $this->filters);
        }

        public function fetchAll(mysqli $conn): array {
            if ($this->marksFilterActive && count($this->marksFilterIds) === 0) {
                return [];
            }
            $sql = str_placeholder($this->selectSql, $this->whereSql()) . ' ORDER BY ' . $this->orderBy;
            $rows = [];
            $stmt = @mysqli_prepare($conn, $sql);
            if ($stmt) {
                if ($this->types !== '') stmt_bind($stmt, $this->types, $this->params);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
                $stmt->close();
            }
            return $rows;
        }

        /**
         * Вычисляет номер страницы, на которой окажется запись с заданным первичным ключом,
         * с учётом текущей сортировки и активных фильтров/поиска.
         */
        public function computePageForNew(mysqli $conn, $newPk): int {
            $pageSize = PAGE_SIZE;
            $base = $this->idSelectSql ?: $this->selectSql;
            $sql = str_placeholder($base, $this->whereSql()) . ' ORDER BY ' . $this->orderBy;
            $stmt = @mysqli_prepare($conn, $sql);
            if (!$stmt) return 1;
            if ($this->types !== '') stmt_bind($stmt, $this->types, $this->params);
            $stmt->execute();
            $res = $stmt->get_result();
            $idx = 0;
            $found = false;
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $keyVal = $r['id'] ?? $r[$this->key] ?? null;
                    if ($keyVal !== null && (string)$keyVal === (string)$newPk) {
                        $found = true;
                        break;
                    }
                    $idx++;
                }
            }
            $stmt->close();
            if (!$found) return 1;
            return (int)ceil(($idx + 1) / $pageSize);
        }

        public function fetchPage(mysqli $conn): array {
            $onlyPage = ((string)($_GET['page'] ?? '0') !== '0');
            $pageNum = max(1, (int)($_GET['page'] ?? 1));
            $pageSize = PAGE_SIZE;
            $focusRaw = (string)($_GET['focus'] ?? '0');
            if ($focusRaw !== '' && $focusRaw !== '0' && $focusRaw !== 'first' && $focusRaw !== 'last' && is_numeric($focusRaw)) {
                $focusId = (int)$focusRaw;
                $computed = $this->computePageForNew($conn, $focusId);
                if ($computed > 0) {
                    $pageNum = $computed;
                    $onlyPage = true;
                }
            }
            $pageOffset = ($pageNum - 1) * $pageSize;
            $pageTotal = 0;
            $pageCount = 0;

            if ($onlyPage) {
                $countSql = str_placeholder($this->countSql, $this->whereSql());
                $cntStmt = @mysqli_prepare($conn, $countSql);
                if ($cntStmt) {
                    if ($this->types !== '') stmt_bind($cntStmt, $this->types, $this->params);
                    $cntStmt->execute();
                    $cntRes = $cntStmt->get_result();
                    if ($cntRes && ($crow = $cntRes->fetch_assoc())) {
                        $pageTotal = (int)$crow['cnt'];
                    }
                    $cntStmt->close();
                }
                $pageCount = max(1, (int)ceil($pageTotal / $pageSize));
                if ($pageNum > $pageCount) { $pageNum = $pageCount; $pageOffset = ($pageNum - 1) * $pageSize; }
            }

            if ($this->marksFilterActive && count($this->marksFilterIds) === 0) {
                return [[], ['onlyPage' => $onlyPage, 'pageNum' => $pageNum, 'pageSize' => $pageSize, 'pageTotal' => 0, 'pageCount' => 0, 'totalCount' => 0, 'rangeFrom' => 0, 'rangeTo' => 0]];
            }

            $sql = str_placeholder($this->selectSql, $this->whereSql()) . ' ORDER BY ' . $this->orderBy;
            if ($onlyPage) {
                $sql .= " LIMIT $pageSize OFFSET $pageOffset";
            }
            $rows = [];
            $stmt = @mysqli_prepare($conn, $sql);
            if ($stmt) {
                if ($this->types !== '') stmt_bind($stmt, $this->types, $this->params);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
                $stmt->close();
            }

            if ($onlyPage) {
                $totalCount = $pageTotal;
                $rangeFrom = $pageTotal > 0 ? $pageOffset + 1 : 0;
                $rangeTo = min($pageOffset + $pageSize, $pageTotal);
            } else {
                $totalCount = count($rows);
                $rangeFrom = $totalCount > 0 ? 1 : 0;
                $rangeTo = $totalCount;
            }
            return [$rows, [
                'onlyPage' => $onlyPage,
                'pageNum' => $pageNum,
                'pageSize' => $pageSize,
                'pageTotal' => $pageTotal,
                'pageCount' => $pageCount,
                'totalCount' => $totalCount,
                'rangeFrom' => $rangeFrom,
                'rangeTo' => $rangeTo,
            ]];
        }

        public function renderPrintPage(array $rows, array $pagination, array $options = []): void {
            $title = $options['title'] ?? '';
            $colValues = $options['colValues'] ?? [];
            $printWidths = $options['printWidths'] ?? [];
            $now = date('d.m.Y H:i');
            $filterLabels = $this->getFilterDescription();

            $onlyPage = ($pagination['pageTotal'] ?? 0) > 0;
            $pageNum = $pagination['pageNum'] ?? 1;
            $pageCount = $pagination['pageCount'] ?? 1;
            $totalCount = $pagination['totalCount'] ?? count($rows);
            $rangeFrom = $pagination['rangeFrom'] ?? (count($rows) > 0 ? 1 : 0);
            $rangeTo = $pagination['rangeTo'] ?? count($rows);

            ?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title><?= h($title) ?></title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: Arial, Helvetica, sans-serif; background:#f0f0f0; padding:16px; }
    .print-page {
        background:#fff; max-width:1000px; margin:0 auto 16px;
        padding:24px 28px; box-shadow:0 2px 8px rgba(0,0,0,.1);
    }
    .print-page-header {
        display:flex; align-items:flex-end; justify-content:space-between;
        border-bottom:2px solid #333; padding-bottom:10px; margin-bottom:14px;
    }
    .print-page-title { font-size:22px; font-weight:700; }
    .print-page-meta { font-size:12px; color:#555; text-align:right; }
    .print-toolbar {
        max-width:1000px; margin:0 auto 16px;
        display:flex; gap:8px; align-items:center;
    }
    .print-btn {
        height:30px; padding:0 14px; background:#3a4a5b; color:#fff;
        border:1px solid #2a3a4b; border-radius:2px; cursor:pointer; font-size:13px;
    }
    .print-btn:hover { background:#4a5a6b; }
    .print-btn.primary { background:#e67e22; border-color:#cf6d1a; }
    .print-btn.primary:hover { background:#cf6d1a; }
    .print-info { margin-left:auto; font-size:12px; color:#555; }
    table { border-collapse:collapse; width:100%; font-size:13px; }
    thead th { background:#ddd; font-weight:700; padding:4px 8px; border:1px solid #bbb; text-align:left; }
    tbody td { padding:4px 8px; border:1px solid #ccc; }
    tbody tr:nth-child(even) { background:#fafafa; }
    @media print {
        .print-page { box-shadow:none; padding:0; margin:0; max-width:100%; }
        .print-toolbar { display:none; }
        body { background:#fff; padding:0; }
    }
</style>
</head>
<body>
<div class="print-toolbar">
    <button class="print-btn primary" type="button" onclick="window.print()">Печатать</button>
    <button class="print-btn" type="button" onclick="window.close()">Закрыть</button>
    <?php if ($onlyPage): ?>
        <span class="print-info">Страница <?= (int)$pageNum ?> из <?= (int)$pageCount ?> (записей <?= (int)$rangeFrom ?>&ndash;<?= (int)$rangeTo ?> из <?= (int)$totalCount ?>)</span>
    <?php else: ?>
        <span class="print-info">Записей: <?= (int)$totalCount ?></span>
    <?php endif; ?>
</div>
<div class="print-page">
    <div class="print-page-header">
        <div class="print-page-title"><?= h($title) ?><?= $onlyPage ? ' &mdash; страница ' . (int)$pageNum : '' ?></div>
        <div class="print-page-meta">
            Сформировано: <?= h($now) ?><br>
            <?php if ($onlyPage): ?>
                Страница <?= (int)$pageNum ?> из <?= (int)$pageCount ?><br>
                Записей <?= (int)$rangeFrom ?>&ndash;<?= (int)$rangeTo ?> из <?= (int)$totalCount ?>
            <?php else: ?>
                Записей: <?= (int)$totalCount ?>
            <?php endif; ?>
        </div>
    </div>
    <?php if (count($filterLabels) > 0): ?>
        <div style="font-size:12px;color:#555;margin:-8px 0 14px;line-height:1.5">
            <?php foreach ($filterLabels as $fl): ?>
                <div><?= h($fl) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if (empty($rows)): ?>
        <p>Нет данных для отображения.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <?php foreach ($this->visibleColumns as $vc):
                        $cn = $vc['name'];
                        $w = $printWidths[$cn] ?? '';
                        $style = $w !== '' ? ' style="width:' . h($w) . ';"' : '';
                    ?>
                        <th<?= $style ?>><?= h($vc['label'] ?? $cn) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <?php foreach ($this->visibleColumns as $vc):
                            $cn = $vc['name'];
                            $fn = $colValues[$cn] ?? null;
                            $v = $fn ? $fn($r) : ((string)($r[$cn] ?? ''));
                        ?>
                            <td><?= is_int($v) ? (int)$v : h($v) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
<?php
        }

        public function renderExport(string $format, array $rows, array $options = []): void {
            $baseName = $options['baseName'] ?? $this->table;
            $colValues = $options['colValues'] ?? [];
            $filterLabels = $this->getFilterDescription();

            $customName = trim((string)($_GET['filename'] ?? ''));
            if ($customName !== '') {
                $customName = preg_replace('/[\x00-\x1F\x7F\/\\\\<>:"|?*]+/u', '_', $customName);
                $customName = trim($customName, ". \t\n\r\0\x0B");
                $customName = mb_substr($customName, 0, 120, 'UTF-8');
                if ($customName !== '') {
                    $customName = preg_replace('/\.(csv|xls)$/i', '', $customName);
                    if ($customName !== '') $baseName = $customName;
                }
            }

            if ($format === 'csv') {
                $this->exportCsv($rows, $baseName, $colValues, $filterLabels);
            } elseif ($format === 'xls') {
                $this->exportXls($rows, $baseName, $colValues, $filterLabels);
            }
        }

        protected function exportCsv(array $rows, string $baseName, array $colValues, array $filterLabels): void {
            $filename = $baseName . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');

            $headers = [];
            foreach ($this->visibleColumns as $vc) $headers[] = $vc['label'] ?? $vc['name'];

            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($filterLabels as $fl) {
                fwrite($out, '# ' . $fl . "\r\n");
            }
            fputcsv($out, $headers, ';', '"', '\\');
            foreach ($rows as $r) {
                $cells = [];
                foreach ($this->visibleColumns as $vc) {
                    $cn = $vc['name'];
                    $fn = $colValues[$cn] ?? null;
                    $v = $fn ? $fn($r) : ((string)($r[$cn] ?? ''));
                    $cells[] = $v;
                }
                fputcsv($out, $cells, ';', '"', '\\');
            }
            fclose($out);
        }

        protected function exportXls(array $rows, string $baseName, array $colValues, array $filterLabels): void {
            $filename = $baseName . '.xls';
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');

            echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
            echo '<head><meta charset="UTF-8"><title>' . htmlspecialchars($baseName, ENT_QUOTES, 'UTF-8') . '</title>';
            echo '<style>table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 14px; } th, td { border: 1px solid #888; padding: 4px 8px; } th { background: #ddd; font-weight: bold; }</style>';
            echo '</head><body>';
            if (count($filterLabels) > 0) {
                echo '<p style="font-size:12px;color:#555;margin:0 0 10px">' . implode('; ', array_map(fn($fl) => htmlspecialchars($fl, ENT_QUOTES, 'UTF-8'), $filterLabels)) . '</p>';
            }
            echo '<table>';
            echo '<thead><tr>';
            foreach ($this->visibleColumns as $vc) {
                echo '<th>' . htmlspecialchars($vc['label'] ?? $vc['name'], ENT_QUOTES, 'UTF-8') . '</th>';
            }
            echo '</tr></thead>';
            echo '<tbody>';
            foreach ($rows as $r) {
                echo '<tr>';
                foreach ($this->visibleColumns as $vc) {
                    $cn = $vc['name'];
                    $fn = $colValues[$cn] ?? null;
                    $v = $fn ? $fn($r) : ((string)($r[$cn] ?? ''));
                    echo '<td>' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table></body></html>';
        }

        public function loadCountryNames(mysqli $conn, string $table, string $col, string $idCol) {
            if (count($this->countryIds) === 0) return;
            $place = implode(',', array_fill(0, count($this->countryIds), '?'));
            $sql = "SELECT $idCol AS id, $col AS name FROM $table WHERE $idCol IN ($place) ORDER BY $col";
            $stmt = @mysqli_prepare($conn, $sql);
            if ($stmt) {
                try {
                    stmt_bind($stmt, str_repeat('i', count($this->countryIds)), $this->countryIds);
                    mysqli_stmt_execute($stmt);
                    $res = mysqli_stmt_get_result($stmt);
                    if ($res) while ($r = $res->fetch_assoc()) {
                        $this->countryNames[(int)$r['id']] = (string)$r['name'];
                    }
                } catch (Throwable $e) {
                    error_log('TablePage::loadCountryNames: ' . $e->getMessage());
                }
                $stmt->close();
            }
        }

        public function getTotalCount(mysqli $conn) {
            $focusRaw = (string)($_GET['focus'] ?? '0');
            if ($focusRaw !== '' && $focusRaw !== '0' && $focusRaw !== 'first' && $focusRaw !== 'last' && is_numeric($focusRaw)) {
                $computed = $this->computePageForNew($conn, (int)$focusRaw);
                if ($computed > 0) {
                    $this->page = $computed;
                    $this->offset = ($this->page - 1) * PAGE_SIZE;
                }
            }
            $sql = $this->countSql ?? "SELECT COUNT(*) AS cnt FROM " . $this->table;
            $sql = str_placeholder($sql, $this->whereSql());
            $this->debugCountSql = $sql;
            $total = 0;
            $stmt = @mysqli_prepare($conn, $sql);
            if ($stmt) {
                try {
                    if ($this->types !== '') stmt_bind($stmt, $this->types, $this->params);
                    mysqli_stmt_execute($stmt);
                    $totalRes = mysqli_stmt_get_result($stmt);
                    $totalRow = $totalRes ? $totalRes->fetch_assoc() : ['cnt' => 0];
                    $total    = (int)($totalRow['cnt'] ?? 0);
                } catch (Throwable $e) {
                    error_log('TablePage COUNT error: ' . $e->getMessage() . ' SQL: ' . $sql);
                    $total = 0;
                }
                $stmt->close();
            } else {
                error_log('TablePage COUNT prepare failed: ' . mysqli_error($conn) . ' SQL: ' . $sql);
            }
            $this->total = $total;
            $this->pages = max(1, (int)ceil($total / PAGE_SIZE));
            if ($this->page > $this->pages) {
                $this->page    = $this->pages;
                $this->offset   = ($this->page - 1) * PAGE_SIZE;
            }
            return $total;
        }

        public function getRows(mysqli $conn) {
            $sql = $this->selectSql ?? "SELECT * FROM " . $this->table;
            $sql = str_placeholder($sql, $this->whereSql())
                 . " ORDER BY " . $this->orderBy
                 . " LIMIT ? OFFSET ?";
            $this->debugSelectSql = $sql;
            $rows = [];
            $stmt = @mysqli_prepare($conn, $sql);
            if ($stmt) {
                try {
                    if ($this->types !== '') {
                        $bindTypes  = $this->types . 'ii';
                        $bindParams = array_merge($this->params, [PAGE_SIZE, $this->offset]);
                    } else {
                        $bindTypes  = 'ii';
                        $bindParams = [PAGE_SIZE, $this->offset];
                    }
                    stmt_bind($stmt, $bindTypes, $bindParams);
                    $execOk = @mysqli_stmt_execute($stmt);
                    if (!$execOk) {
                        $this->debugSelectError = 'execute failed: ' . mysqli_stmt_error($stmt);
                        error_log('TablePage SELECT execute failed: ' . mysqli_stmt_error($stmt) . ' SQL: ' . $sql);
                    } else {
                        $result = @mysqli_stmt_get_result($stmt);
                        if (!$result) {
                            $this->debugSelectError = 'get_result failed: ' . mysqli_stmt_error($stmt);
                        } else {
                            $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
                        }
                    }
                } catch (Throwable $e) {
                    $this->debugSelectError = $e->getMessage();
                    error_log('TablePage SELECT error: ' . $e->getMessage() . ' SQL: ' . $sql);
                    $rows = [];
                }
                $stmt->close();
            } else {
                $this->debugSelectError = 'prepare failed: ' . mysqli_error($conn);
                error_log('TablePage SELECT prepare failed: ' . mysqli_error($conn) . ' SQL: ' . $sql);
            }
            $this->rows = $rows;
            return $rows;
        }

        public function getFilteredIds(mysqli $conn) {
            $sql = $this->idSelectSql ?? ("SELECT " . $this->table . "." . $this->key . " AS id FROM " . $this->table);
            $sql = str_placeholder($sql, $this->whereSql());
            $ids = [];
            if ($this->types === '') {
                $res = @$conn->query($sql);
                if ($res) while ($r = $res->fetch_assoc()) $ids[] = (int)$r['id'];
            } else {
                $stmt = @mysqli_prepare($conn, $sql);
                if ($stmt) {
                    stmt_bind($stmt, $this->types, $this->params);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res) while ($r = $res->fetch_assoc()) $ids[] = (int)$r['id'];
                    $stmt->close();
                }
            }
            return $ids;
        }

        public function clearQs(array $drop) {
            $qs = $_GET;
            foreach ($drop as $k) unset($qs[$k], $qs['page']);
            $s = http_build_query($qs);
            $sep = strpos($this->baseUrl, '?') !== false ? '&' : '?';
            return $s !== '' ? $this->baseUrl . $sep . $s : $this->baseUrl;
        }

        public function buildClearQs(array $extraDrop = []) {
            return function ($drop) use ($extraDrop) {
                $drop = is_array($drop) ? $drop : [$drop];
                return $this->clearQs(array_merge($drop, $extraDrop));
            };
        }

        public static function condLabel(string $cond): string {
            $labels = [
                'contains'     => 'Содержит',
                'not_contains' => 'Не содержит',
                'starts_with'  => 'Начинается с',
                'ends_with'    => 'Заканчивается на',
                'equals'       => 'Равно',
                'not_equals'   => 'Не равно',
                'gt'           => 'Больше',
                'lt'           => 'Меньше',
            ];
            return $labels[$cond] ?? $cond;
        }

        public function buildExportQs(array $extraParams = []) {
            $params = [
                'q'    => $this->searchActive && $this->search !== '' ? $this->search : null,
                'cols' => $this->searchActive && count($this->searchCols) > 0 ? implode(',', $this->searchCols) : null,
                'cond' => $this->searchActive ? $this->searchCond : null,
                'sf'   => $this->searchActive ? '1' : null,
                'sort' => $this->sortQs !== '' ? $this->sortQs : null,
            ];
            foreach ($this->getColumnFilterParams() as $p) {
                if (!empty($_GET[$p])) $params[$p] = $_GET[$p];
            }
            foreach ($extraParams as $k => $v) {
                $params[$k] = ($v !== null && $v !== '') ? $v : null;
            }
            return http_build_query(array_filter($params, function ($v) { return $v !== null && $v !== ''; }));
        }

        public function normalizeColFilterConfig($cfg): array {
            if (isset($cfg['table']) || isset($cfg['options'])) return $cfg;
            if (is_array($cfg)) {
                $a = array_values($cfg);
                return [
                    'col'   => $a[0] ?? null,
                    'table' => $a[1] ?? null,
                    'id'    => $a[2] ?? null,
                    'label' => $a[3] ?? null,
                    'param' => $a[4] ?? null,
                ];
            }
            return [];
        }

        public function colFilterOptions(mysqli $conn, string $col) {
            $cfg = $this->colFilters[$col] ?? null;
            if (!$cfg) return [];
            $cfg = $this->normalizeColFilterConfig($cfg);
            if (isset($cfg['options'])) return $cfg['options'];
            $table = $cfg['table'] ?? null;
            $idCol = $cfg['id'] ?? null;
            $labelExpr = $cfg['label'] ?? null;
            if (!$table || !$idCol || !$labelExpr) return [];
            $sql = "SELECT $idCol AS id, $labelExpr AS name FROM $table ORDER BY $labelExpr";
            $out = [];
            $res = @$conn->query($sql);
            if ($res) while ($r = $res->fetch_assoc()) {
                $out[] = ['id' => (int)$r['id'], 'name' => (string)$r['name']];
            }
            return $out;
        }

        public function getColumnFilterParams(): array {
            $params = [];
            foreach ($this->colFilters as $col => $cfg) {
                $cfg = $this->normalizeColFilterConfig($cfg);
                $p = $cfg['param'] ?? ($this->colMeta[$col]['param'] ?? ($col . '_id'));
                $params[] = $p;
            }
            return $params;
        }

        public function processColumnFilters(mysqli $conn): void {
            foreach ($this->colFilters as $col => $cfg) {
                $cfg = $this->normalizeColFilterConfig($cfg);
                if (empty($cfg)) continue;
                if (!empty($cfg['subquery'])) continue;
                $param = $cfg['param'] ?? ($this->colMeta[$col]['param'] ?? ($col . '_id'));
                $raw = (string)($_GET[$param] ?? '');
                if ($raw === '') continue;
                $colExpr = $cfg['col'] ?? '';
                if ($colExpr === '') {
                    $cm = $this->colMeta[$col] ?? null;
                    $colExpr = ($cm['sort_expr'] ?? '') ?: $param;
                }
                if (isset($cfg['options'])) {
                    $useId = ($cfg['value_key'] ?? 'name') === 'id';
                    $parts = array_values(array_filter(explode(',', $raw), fn($s) => $s !== ''));
                    $rawIds = array_map('intval', $parts);
                    $validIds = [];
                    foreach ($cfg['options'] as $opt) $validIds[] = (int)$opt['id'];
                    $ids = array_values(array_intersect($rawIds, $validIds));
                    if (count($ids) === 0) continue;
                    $idToName = [];
                    foreach ($cfg['options'] as $opt) $idToName[(int)$opt['id']] = (string)$opt['name'];
                    $names = array_map(fn($id) => $idToName[$id] ?? '#' . $id, $ids);
                    if ($useId) {
                        $ph = implode(',', array_fill(0, count($ids), '?'));
                        $this->appendWhere("$colExpr IN ($ph)", $ids, str_repeat('i', count($ids)));
                    } else {
                        $ph = implode(',', array_fill(0, count($names), '?'));
                        $this->appendWhere("$colExpr IN ($ph)", $names, str_repeat('s', count($names)));
                    }
                    $label = $this->colMeta[$col]['label'] ?? $cfg['label'] ?? $col;
                    $this->filters[] = ['kind' => 'col_filter', 'text' => "$label = " . implode(', ', $names), 'clear' => $param];
                } elseif (isset($cfg['table'])) {
                    $ids = array_values(array_filter(array_map('intval', explode(',', $raw)), fn($v) => $v > 0));
                    if (count($ids) === 0) continue;
                    $ph = implode(',', array_fill(0, count($ids), '?'));
                    $this->appendWhere("$colExpr IN ($ph)", $ids, str_repeat('i', count($ids)));
                    $lookupTable = $cfg['table'];
                    $lookupIdCol = $cfg['id'] ?? 'id';
                    $lookupNameCol = $cfg['label'] ?? 'name';
                    $names = [];
                    $ph2 = implode(',', array_fill(0, count($ids), '?'));
                    $stmt = @$conn->prepare("SELECT $lookupIdCol, $lookupNameCol AS name FROM $lookupTable WHERE $lookupIdCol IN ($ph2)");
                    if ($stmt) {
                        $refs2 = [];
                        foreach ($ids as $k => $v) { $refs2[$k] = &$ids[$k]; }
                        $stmt->bind_param(str_repeat('i', count($ids)), ...$refs2);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        if ($res) while ($r = $res->fetch_assoc()) $names[] = (string)$r['name'];
                        $stmt->close();
                    }
                    $label = $this->colMeta[$col]['label'] ?? $cfg['label'] ?? $col;
                    $this->filters[] = ['kind' => 'col_filter', 'text' => "$label = " . (count($names) > 0 ? implode(', ', $names) : implode(',', $ids)), 'clear' => $param];
                }
            }
        }

        // --- Rendering ---

        public function loadColumnWidths(mysqli $conn): void {
            $this->columnWidths = load_columns_widths($conn, $this->table);
        }

        public function renderHead(string $title): void {
            ?><!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= h($title) ?></title>
  <link rel="stylesheet" href="app.css" />
<?php
        }

        public function renderHeadEnd(): void {
            $pw = isset($GLOBALS['pageWidth']) ? (int)$GLOBALS['pageWidth'] : 1100;
            ?><link rel="stylesheet" href="/comp/assets/table.css" />
    <?php if ($pw !== 1100): ?>
    <style>.page { max-width: <?= $pw ?>px; }</style>
    <?php endif; ?>
</head>
<body>
<?php
            global $CurSotrID;
            if (empty($CurSotrID)) {
                $login = (string)($_POST['login'] ?? '');
                ?>
<style>
#login-overlay{position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;flex-direction:column}
#login-overlay .login-box{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:32px 40px;box-shadow:0 8px 32px rgba(0,0,0,.4);min-width:320px}
#login-overlay .login-box h2{margin:-32px -40px 20px;padding:10px 12px;background:#1f2c3a;color:#ffe9a8;font-size:22px;font-weight:700;line-height:1.1;border-bottom:2px solid #2a3a4b;border-radius:8px 8px 0 0;user-select:none}
#login-overlay .login-box .field{margin-bottom:14px}
#login-overlay .login-box .field label{display:block;font-size:12px;color:var(--muted);margin-bottom:2px}
#login-overlay .login-box .field input{width:100%;box-sizing:border-box;height:24px;padding:0 6px;border:1px solid var(--border);border-radius:1px;font-size:14px;font-family:inherit;color:var(--input-text);background:var(--input-bg)}
#login-overlay .login-box .field input:focus{border-color:var(--accent);outline:none;box-shadow:0 0 0 2px rgba(230,126,34,.2)}
#login-overlay .login-box .actions{display:flex;gap:18px;justify-content:center;margin-top:20px}
#login-overlay .login-box .btn-primary{background:var(--accent);border-color:var(--accent);color:#fff;padding:8px 24px}
#login-overlay .login-box .btn-primary:hover{background:var(--accent-hover)}
#login-overlay .login-error{color:var(--danger);font-size:13px;text-align:center;margin-top:10px;display:none}
</style>
<div id="login-overlay">
  <div class="login-box">
    <h2>Регистрация сотрудника</h2>
    <div class="field"><label>Логин</label><input type="text" id="login-login" value="<?= h($login) ?>" autocomplete="username" /></div>
    <div class="field"><label>Пароль</label><input type="password" id="login-password" autocomplete="current-password" /></div>
    <div class="login-error" id="login-error"></div>
    <div class="actions">
      <button class="btn btn-primary" id="login-submit"><img src="img/accept.png" alt="" /> Вход</button>
    </div>
  </div>
</div>
<script>
(function(){
  var overlay = document.getElementById('login-overlay');
  var loginInput = document.getElementById('login-login');
  var passInput = document.getElementById('login-password');
  var errEl = document.getElementById('login-error');
  function showError(msg) { errEl.textContent = msg; errEl.style.display = 'block'; }
  function hideError() { errEl.style.display = 'none'; }
  function doLogin() {
    var l = loginInput.value.trim();
    var p = passInput.value;
    if (!l || !p) { showError('Введите логин и пароль'); return; }
    hideError();
    fetch('login_handler.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin', body:'login='+encodeURIComponent(l)+'&password='+encodeURIComponent(p) })
      .then(function(r){return r.json()})
      .then(function(d){
        if (d && d.ok) { location.reload(); }
        else { showError(d && d.error ? d.error : 'Ошибка входа'); }
      })
      .catch(function(){ showError('Ошибка соединения'); });
  }
  document.getElementById('login-submit').addEventListener('click', doLogin);
  loginInput.addEventListener('keydown', function(e){ if (e.key === 'Enter') doLogin(); });
  passInput.addEventListener('keydown', function(e){ if (e.key === 'Enter') doLogin(); });
  loginInput.focus();
})();
</script>
<?php
            }
            $this->renderExportModal();
            $this->renderFormModal();
        }

        public function renderExportModal(): void {
            ?><div class="export-modal-backdrop" id="exportModal" role="dialog" aria-labelledby="exportModalTitle" aria-modal="true">
    <div class="export-modal">
      <div class="export-modal-title" id="exportModalTitle">Сохранение файла</div>
      <div class="export-modal-row export-modal-row-input">
        <label class="export-modal-label" for="exportModalFilename">Имя файла:</label>
        <input class="export-modal-input" type="text" id="exportModalFilename" />
        <span class="export-modal-ext" id="exportModalExt"></span>
      </div>
      <div class="export-modal-row">
        <div class="export-modal-label">Формат:</div>
        <div class="export-modal-value" id="exportModalFormat"></div>
      </div>
      <div class="export-modal-row">
        <div class="export-modal-label">Записей:</div>
        <div class="export-modal-value" id="exportModalCount"></div>
      </div>
      <div class="export-modal-actions">
        <button type="button" class="export-cancel">Отмена</button>
        <button type="button" class="export-apply">Скачать</button>
      </div>
    </div>
  </div><?php
        }

        public function renderFormModal(): void {
            ?><div class="form-modal-backdrop" id="formModal" role="dialog" aria-labelledby="formModalTitle" aria-modal="true">
    <div class="form-modal">
      <button type="button" class="form-modal-close" data-form-close title="Закрыть" aria-label="Закрыть">&times;</button>
      <div class="form-modal-body" id="formModalBody"></div>
    </div>
  </div><?php
        }

        public function renderTitle(string $icon, string $title): void {
            ?><h1 class="page-title"><img src="img/<?= h($icon) ?>" alt="" /> <?= h($title) ?></h1><?php
        }

        protected function renderExportDropdownItems(array $formats, string $exportQs): string {
            $html = '';
            foreach ($formats as $item) {
                $fullUrl = $item['url'] ?? ($this->table . '_export.php?format=' . $item['fmt'] . ($exportQs !== '' ? '&' . $exportQs : ''));
                $label   = $item['label'] ?? $item['fmt'];
                $filename = $item['filename'] ?? '';
                $format   = $item['format'] ?? strtoupper($item['fmt']);
                $html .= '<a class="dropdown-item" href="#" data-export-url="' . h($fullUrl) . '" data-export-filename="' . h($filename) . '" data-export-format="' . h($format) . '">' . h($label) . '</a>';
            }
            return $html;
        }

        protected function renderPrintDropdownItems(string $exportQs): string {
            $printUrl = $this->table . '_print.php';
            $printQs = $exportQs !== '' ? '?' . $exportQs : '';
            $allQs = $exportQs !== '' ? '?all=1&' . $exportQs : '?all=1';
            return
                '<a class="dropdown-item" href="' . h($printUrl) . $printQs . '" target="_blank">Все записи</a>' .
                '<a class="dropdown-item" href="' . h($printUrl) . $allQs . '" target="_blank">Выбранные</a>' .
                '<a class="dropdown-item" href="' . h($printUrl) . '?page=' . (int)$this->page . ($exportQs !== '' ? '&' . $exportQs : '') . '" target="_blank">Текущая страница</a>';
        }

        public function renderToolbar(array $options = []): void {
            $formPrefix = $options['formPrefix'] ?? $this->formPrefix;
            $extraLeftHtml = $options['extraLeftHtml'] ?? '';
            $afterPrintHtml = $options['afterPrintHtml'] ?? '';

            $extraExportParams = $options['extraExportParams'] ?? [];
            if (!array_key_exists('extraExportParams', $options)) {
                if ($this->countryFilterField && $this->countryFilter !== '') {
                    $extraExportParams[$this->countryFilterField] = $this->countryFilter;
                }
            }
            $exportQs = $this->buildExportQs($extraExportParams);

            $exportFormats = $options['exportFormats'] ?? [
                ['fmt' => 'csv', 'filename' => $this->table . '.csv', 'format' => 'CSV', 'label' => 'Экспорт в CSV'],
                ['fmt' => 'xls', 'filename' => $this->table . '.xls', 'format' => 'XLS (Excel)', 'label' => 'Экспорт в Excel'],
            ];
            $exportDropdownHtml = $this->renderExportDropdownItems($exportFormats, $exportQs);
            $printDropdownHtml = $this->renderPrintDropdownItems($exportQs);

            $clQs = $this->buildClearQs();
            $scriptName = $this->baseUrl;
            $search = $this->search;
            $searchActive = $this->searchActive;
            $searchCols = $this->searchCols;
            $searchCond = $this->searchCond;
            $marksCount = $this->marksCount;

            ?><div class="toolbar" data-total="<?= (int)$this->total ?>" data-show-only="<?= $this->showOnly ? '1' : '0' ?>" data-marks-count="<?= (int)$marksCount ?>" data-search="<?= h($search) ?>" data-page="<?= (int)$this->page ?>" data-pages="<?= (int)$this->pages ?>" data-focus="<?= h((string)($_GET['focus'] ?? '0')) ?>">
    <div class="toolbar-left">
      <button class="icon-btn" title="Добавить" data-form-open="<?= h($formPrefix) ?>.php?mode=new"><img src="img/add.png" alt="" /></button>
      <button class="icon-btn" id="rowOpenBtn" title="Изменить" type="button" disabled><img src="img/edit.png" alt="" /></button>
      <button class="icon-btn" id="rowDeleteBtn" title="Удалить" type="button" disabled><img src="img/delete.png" alt="" /></button>
      <button class="icon-btn" id="rowCopyBtn" title="Копировать" type="button" disabled><img src="img/copy.png" alt="" /></button>
      <button class="icon-btn" title="Обновить" onclick="location.reload()"><img src="img/refresh.png" alt="" /></button>
      <?= $extraLeftHtml ?>
      <div class="dropdown">
        <button class="icon-btn" type="button" title="Экспорт"><img src="img/export.png" alt="" /></button>
        <div class="dropdown-menu"><?= $exportDropdownHtml ?></div>
      </div>
      <div class="dropdown">
        <button class="icon-btn" type="button" title="Печать"><img src="img/print.png" alt="" /></button>
        <div class="dropdown-menu"><?= $printDropdownHtml ?></div>
      </div>
      <?= $afterPrintHtml ?>
      <div class="dropdown selected-actions<?= $marksCount > 0 ? ' visible' : '' ?>" id="selectedActions">
        <button class="menu-btn" type="button" title="Действия с выбранными">
          <span id="selectedCount">Выбрано <?= (int)$marksCount ?></span>
          <img src="img/look.png" alt="" />
        </button>
        <div class="dropdown-menu">
          <a class="dropdown-item" href="#" onclick="clearSelection();return false;">Очистить выбор</a>
          <a class="dropdown-item" href="#" onclick="invertSelection();return false;">Инвертировать выбор</a>
          <a class="dropdown-item" href="#" onclick="toggleShowOnly();return false;">Показать выбранные</a>
          <a class="dropdown-item" href="#" onclick="exportSelected();return false;">Экспорт</a>
          <a class="dropdown-item" href="#" onclick="printSelected();return false;">Печать</a>
        </div>
      </div>
    </div>
    <div class="toolbar-right">
      <form id="searchForm" method="get" action="<?= h($scriptName) ?>" style="display:flex;gap:4px;align-items:center;">
        <input class="quick-search" type="text" name="q" placeholder="Быстрый поиск" value="<?= h($search) ?>" />
        <input type="hidden" name="cols" value="<?= h(implode(',', $searchCols)) ?>" />
        <input type="hidden" name="cond" value="<?= h($searchCond) ?>" />
        <input type="hidden" name="sf" value="<?= $searchActive ? '1' : '0' ?>" />
        <?php if ($searchActive): ?>
        <a class="icon-btn clear-filter-btn" title="Очистить фильтр" href="<?= h($clQs(['q','cols','cond','sf','page'])) ?>">✕</a>
        <?php endif; ?>
        <button class="icon-btn search-toggle-btn<?= $searchActive ? ' active' : '' ?>" type="button" id="searchToggleBtn" title="Искать" aria-pressed="<?= $searchActive ? 'true' : 'false' ?>">
          <img src="img/find.png" alt="" />
        </button>
        <button class="icon-btn search-mini-btn" type="button" id="searchCondBtn" title="Условия поиска" aria-haspopup="true" aria-expanded="false">
          <img src="img/look.png" alt="" />
        </button>
      </form>
      <button class="icon-btn" id="sortBtn" title="Сортировка" type="button"><img src="img/sort.png" alt="" /></button>
      <button class="icon-btn" id="columnsBtn" title="Настройка столбцов таблицы" type="button"><img src="img/setup.png" alt="" /></button>
    </div>
  </div><?php
        }

        public function renderFilterBanner(string $icon = 'img/filter.png'): void {
            $this->buildFilters();
            if (count($this->filters) === 0) return;
            $clQs = $this->buildClearQs();
            ?><div class="mode-banner" id="filterBanner">
    <img src="<?= h($icon) ?>" alt="" />
    <?php foreach ($this->filters as $fi => $f): ?>
      <?php if ($fi > 0): ?><span class="filter-sep">и</span><?php endif; ?>
      <span class="filter-chip">
        <span class="filter-chip-text">(<?= h($f['text']) ?>)</span>
        <?php if ($f['clear'] === null): ?>
          <button class="filter-chip-close" type="button" title="Снять фильтр" onclick="toggleShowOnly()">✕</button>
        <?php else: ?>
          <a class="filter-chip-close" href="<?= h($clQs($f['clear'])) ?>" title="Снять фильтр">✕</a>
        <?php endif; ?>
      </span>
    <?php endforeach; ?>
  </div><?php
        }

        public function renderTable(callable $cellValue, array $options = []): void {
            $defaultWidths = $options['defaultWidths'] ?? $this->defaultColumnWidths;
            $thAttrsCallback = $options['thAttrsCallback'] ?? null;
            $thHtmlCallback = $options['thHtmlCallback'] ?? null;
            $tdExtraAttrs = $options['tdExtraAttrs'] ?? null;
            $trExtraAttrs = $options['trExtraAttrs'] ?? null;
            $noDataMessage = $options['noDataMessage'] ?? null;
            $rows = $options['rows'] ?? $this->rows;
            $hasCheckbox = $options['hasCheckbox'] ?? true;
            $checkboxThHtml = $options['checkboxThHtml'] ?? null;
            $checkboxCallback = $options['checkboxCallback'] ?? null;

            // colgroup
            ?><colgroup>
    <col class="col-check" style="width: 32px;" />
    <?php foreach ($this->visibleColumns as $vc):
        $cn = $vc['name'];
        $savedW = $this->columnWidths[$cn] ?? null;
        $dflt = $defaultWidths[$cn] ?? '150px';
        $w = $savedW !== null ? $savedW . 'px' : (is_numeric($dflt) ? $dflt . 'px' : $dflt);
    ?>
      <col class="col-<?= h($cn) ?>" style="width: <?= $w ?>;" />
    <?php endforeach; ?>
  </colgroup><?php

            // thead
            $allRowsMarked = false;
            if ($hasCheckbox && count($rows) > 0) {
                $markedCnt = 0;
                foreach ($rows as $r) { if (isset($this->marks[(int)$r[$this->key]])) $markedCnt++; }
                $allRowsMarked = $markedCnt === count($rows);
            }
            $checkAllDisabled = count($rows) === 0;
            ?><thead><tr>
    <?php if ($checkboxThHtml !== null): ?>
      <?= $checkboxThHtml ?>
    <?php elseif ($hasCheckbox): ?>
      <th class="col-check"><input type="checkbox" id="checkAll"<?= $allRowsMarked ? ' checked' : '' ?><?= $checkAllDisabled ? ' disabled' : '' ?> /></th>
    <?php endif; ?>
    <?php foreach ($this->visibleColumns as $i => $vc):
        $cn  = $vc['name'];
        $cl  = $vc['label'];
        $cm  = $this->colMeta[$cn] ?? null;
        $sortIdx = -1;
        foreach ($this->sortLevels as $si => $sl) if ($sl['col'] === $cn) $sortIdx = $si;
        $sortDir = $sortIdx >= 0 ? $this->sortLevels[$sortIdx]['dir'] : '';
        $hasSortExpr = !empty($vc['sort_expr']);
        $thAttrs = 'class="col-' . h($cn) . '" data-col="' . h($cn) . '"' . ($hasSortExpr ? ' data-sort-col="' . h($cn) . '"' : '') . ' data-col-idx="' . (int)$i . '" data-sort-dir="' . h($sortDir) . '"';
        if (!empty($cm['param'])) $thAttrs .= ' data-param="' . h($cm['param']) . '"';
        if (!empty($vc['no_resize'])) $thAttrs .= ' data-no-resize="1"';
        // auto-add data-param and data-values for column filters
        $cfCfg = $this->colFilters[$cn] ?? null;
        if ($cfCfg) {
            $cfCfg = $this->normalizeColFilterConfig($cfCfg);
            $cfParam = $cfCfg['param'] ?? ($cm['param'] ?? ($cn . '_id'));
            if (empty($cm['param'])) $thAttrs .= ' data-param="' . h($cfParam) . '"';
            $raw = (string)($_GET[$cfParam] ?? '');
            if ($raw !== '') {
                $validIds = [];
                if (isset($cfCfg['options']) && ($cfCfg['value_key'] ?? 'name') === 'id') {
                    foreach ($cfCfg['options'] as $opt) $validIds[] = (int)$opt['id'];
                }
                $ids = array_values(array_filter(array_map('intval', explode(',', $raw)), fn($v) => count($validIds) > 0 ? in_array($v, $validIds) : $v > 0));
                if (count($ids) > 0) $thAttrs .= ' data-values="' . implode(',', $ids) . '"';
            }
        }
        if ($thAttrsCallback) $thAttrs .= $thAttrsCallback($cn, $cm, $i);
    ?>
      <th <?= $thAttrs ?>>
        <span class="col-filter-label"><?= h($cl) ?></span>
        <?php if ($thHtmlCallback): ?><?= $thHtmlCallback($cn, $cm) ?><?php endif; ?>
        <?php if ($sortIdx >= 0): ?>
          <span class="sort-indicator"><?= ($sortIdx + 1) ?> <?= $sortDir === 'asc' ? '▲' : '▼' ?></span>
        <?php endif; ?>
      </th>
    <?php endforeach; ?>
  </tr></thead><?php

            // tbody
            $search = $this->search;
            $key = $this->key;
            $marks = $this->marks;
            ?><tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="<?= ($hasCheckbox ? 1 : 0) + count($this->visibleColumns) ?>" style="text-align:center; padding:20px; color:var(--muted);"><?= $noDataMessage ?? ($search !== '' ? 'По запросу &laquo;' . h($search) . '&raquo; ничего не найдено.' : 'Нет данных.') ?></td></tr>
    <?php else: ?>
      <?php foreach ($rows as $r):
          $rid = (int)$r[$key];
      ?>
      <tr data-row-id="<?= $rid ?>"<?= $trExtraAttrs ? ' ' . $trExtraAttrs($r) : '' ?>>
        <?php if ($checkboxCallback): ?>
          <td class="col-check"><?= $checkboxCallback($rid) ?></td>
        <?php elseif ($hasCheckbox): ?>
          <td class="col-check"><input type="checkbox" class="row-check" value="<?= $rid ?>" data-id="<?= $rid ?>"<?= isset($marks[$rid]) ? ' checked' : '' ?> /></td>
        <?php endif; ?>
        <?php foreach ($this->visibleColumns as $i => $vc):
            $cn = $vc['name'];
            [$rawValue, $displayValue] = $cellValue($r, $cn, $vc);
            if ($search !== '' && in_array($cn, $this->searchCols) && strpos($displayValue, '<span class="hl"') === false) {
                $displayValue = preg_replace('/' . preg_quote($search, '/') . '/iu', '<span class="hl">$0</span>', $displayValue);
            }
            $readonly = !empty($vc['readonly']) || $cn === 'id';
            $editable = !$readonly;
            $tdAttrs = 'class="col-' . h($cn) . ($editable ? ' cell-editable' : '') . '"';
            if ($editable) $tdAttrs .= ' data-field="' . h($cn) . '"';
            $tdAttrs .= ' data-value="' . h((string)$rawValue) . '"';
            if ($tdExtraAttrs) $tdAttrs .= $tdExtraAttrs($cn, $vc, $r, $i);
        ?>
          <td <?= $tdAttrs ?>>
            <span class="cell-value"><?= $displayValue ?></span>
          </td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody><?php
        }

        public function renderPagination(array $extraParams = []): void {
            if ($this->pages <= 1) return;
            $prev = max(1, $this->page - 1);
            $next = min($this->pages, $this->page + 1);
            $cfParams = [];
            foreach ($this->getColumnFilterParams() as $p) {
                if (!empty($_GET[$p])) $cfParams[$p] = $_GET[$p];
            }
            $baseQs = function ($p) use ($cfParams, $extraParams) {
                $qs = ['page' => (int)$p];
                foreach ($extraParams as $k => $v) {
                    if ($v !== '' && $v !== null) $qs[$k] = $v;
                }
                if ($this->searchActive) {
                    if ($this->search !== '') $qs['q'] = $this->search;
                    if (count($this->searchCols) > 0) $qs['cols'] = implode(',', $this->searchCols);
                    $qs['cond'] = $this->searchCond;
                    $qs['sf'] = '1';
                }
                if ($this->countryFilterField && $this->countryFilter !== '') {
                    $qs[$this->countryFilterField] = $this->countryFilter;
                }
                if ($this->sortQs !== '') $qs['sort'] = $this->sortQs;
                foreach ($cfParams as $k => $v) $qs[$k] = $v;
                $sep = strpos($this->baseUrl, '?') !== false ? '&' : '?';
                return $this->baseUrl . $sep . http_build_query($qs);
            };
            ?><div class="pagination">
      <a class="page-btn" href="<?= h($baseQs(1)) ?>"<?= $this->page <= 1 ? ' aria-disabled="true" style="pointer-events:none;opacity:.5;"' : '' ?>>«</a>
      <?php if ($prev !== $this->page): ?>
      <a class="page-btn" href="<?= h($baseQs($prev)) ?>"><?= $prev ?></a>
      <?php endif; ?>
      <a class="page-btn active" href="<?= h($baseQs($this->page)) ?>"><?= $this->page ?></a>
      <?php if ($next !== $this->page): ?>
      <a class="page-btn" href="<?= h($baseQs($next)) ?>"><?= $next ?></a>
      <?php endif; ?>
      <a class="page-btn" href="<?= h($baseQs($this->pages)) ?>"<?= $this->page >= $this->pages ? ' aria-disabled="true" style="pointer-events:none;opacity:.5;"' : '' ?>>»</a>
      <span class="page-info"><?= $this->page ?> из <?= $this->pages ?></span>
    </div><?php
        }

        public function renderPageStart(): void {
            ?><div class="page"><?php
        }

        public function renderPageEnd(): void {
            ?></div><?php
        }

        public function renderEmbedded(array $data, array $options = []): string {
            $p = $options['prefix'] ?? 'et';
            $readonly = $options['readonly'] ?? false;
            $hasSearch = $options['hasSearch'] ?? true;
            $hasExport = $options['hasExport'] ?? false;
            $hasPrint = $options['hasPrint'] ?? false;
            $hasImport = $options['hasImport'] ?? false;
            $colWidths = $options['colWidths'] ?? $this->defaultColumnWidths;
            $visibleCols = $this->visibleColumns ?: $this->columns;

            ob_start();
            ?>
    <div class="toolbar" style="margin-top:0;padding-top:0;margin-bottom:8px" id="<?= h($p) ?>-toolbar">
      <div class="toolbar-left">
        <?php if (!$readonly): ?>
        <button type="button" class="icon-btn" title="Добавить" id="<?= h($p) ?>-add-btn"><img src="img/add.png" alt="" /></button>
        <button type="button" class="icon-btn" title="Изменить" id="<?= h($p) ?>-edit-btn" disabled><img src="img/edit.png" alt="" /></button>
        <button type="button" class="icon-btn" title="Удалить" id="<?= h($p) ?>-del-btn" disabled><img src="img/delete.png" alt="" /></button>
        <button type="button" class="icon-btn" title="Копировать" id="<?= h($p) ?>-copy-btn" disabled><img src="img/copy.png" alt="" /></button>
        <?php endif; ?>
        <button type="button" class="icon-btn" title="Обновить" id="<?= h($p) ?>-refresh-btn"><img src="img/refresh.png" alt="" /></button>
        <?php if ($hasImport && !$readonly): ?>
        <button type="button" class="icon-btn" title="Импорт отмеченных" id="<?= h($p) ?>-import-btn"><img src="img/import.png" alt="" /></button>
        <?php endif; ?>
        <?php if ($hasExport): ?>
        <div class="dropdown">
          <button type="button" class="icon-btn" title="Экспорт" id="<?= h($p) ?>-export-btn"><img src="img/export.png" alt="" /></button>
          <div class="dropdown-menu">
            <a class="dropdown-item" href="#" id="<?= h($p) ?>-export-all">Экспорт в CSV</a>
            <a class="dropdown-item" href="#" id="<?= h($p) ?>-export-all-xls">Экспорт в Excel</a>
          </div>
        </div>
        <?php endif; ?>
        <?php if ($hasPrint): ?>
        <div class="dropdown">
          <button type="button" class="icon-btn" title="Печать" id="<?= h($p) ?>-print-btn"><img src="img/print.png" alt="" /></button>
          <div class="dropdown-menu">
            <a class="dropdown-item" href="#" id="<?= h($p) ?>-print-all">Все записи</a>
            <a class="dropdown-item" href="#" id="<?= h($p) ?>-print-page">Текущая страница</a>
          </div>
        </div>
        <?php endif; ?>
        <?php if (!$readonly): ?>
        <div class="dropdown selected-actions" id="<?= h($p) ?>-sel-wrap">
          <button type="button" class="menu-btn" id="<?= h($p) ?>-sel-btn">Выбрано <b><span id="<?= h($p) ?>-sel-count">0</span></b> <span class="btn-caret">&#9660;</span></button>
          <div class="dropdown-menu" style="min-width:180px">
            <a class="dropdown-item" href="#" id="<?= h($p) ?>-sel-clear">Очистить выбор</a>
            <a class="dropdown-item" href="#" id="<?= h($p) ?>-sel-invert">Инвертировать выбор</a>
            <a class="dropdown-item" href="#" id="<?= h($p) ?>-sel-show">Показать выбранные</a>
            <a class="dropdown-item" href="#" id="<?= h($p) ?>-sel-delete">Удалить отмеченные</a>
            <a class="dropdown-item" href="#" id="<?= h($p) ?>-sel-export">Экспорт выбранных</a>
            <a class="dropdown-item" href="#" id="<?= h($p) ?>-sel-print">Печать выбранных</a>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <?php if ($hasSearch): ?>
      <div class="toolbar-right">
        <div id="<?= h($p) ?>-search-form" style="display:flex;gap:4px;align-items:center;">
          <input class="quick-search" type="text" id="<?= h($p) ?>-search-input" name="q" placeholder="Быстрый поиск" />
          <a class="icon-btn clear-filter-btn" title="Сбросить поиск" id="<?= h($p) ?>-clear-search" style="display:none;text-decoration:none">✕</a>
          <button type="button" class="icon-btn" title="Поиск" id="<?= h($p) ?>-search-btn"><img src="img/find.png" alt="" /></button>
          <button type="button" class="icon-btn" title="Условия поиска" id="<?= h($p) ?>-search-cond-btn"><img src="img/look.png" alt="" /></button>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <div class="mode-banner" id="<?= h($p) ?>-filter-banner" style="display:none">
      <img src="img/filter.png" alt="" />
      <span id="<?= h($p) ?>-filter-chips"></span>
    </div>
    <div class="table-wrap" style="overflow-x:auto">
    <table class="data-table docum2-table" style="table-layout:fixed;width:100%" id="<?= h($p) ?>-table"
           data-items='<?= h(json_encode($data, JSON_UNESCAPED_UNICODE)) ?>'
           <?php if (!empty($options['columnResizeUrl'])): ?>data-col-resize-url="<?= h($options['columnResizeUrl']) ?>"<?php endif; ?>
           <?php if (!empty($options['columnResizeTbl'])): ?>data-col-resize-tbl="<?= h($options['columnResizeTbl']) ?>"<?php endif; ?>>
      <?php $this->renderColgroup(true, $visibleCols) ?>
      <thead>
        <tr>
          <th class="col-check"><input type="checkbox" id="<?= h($p) ?>-check-all" /></th>
          <?php foreach ($visibleCols as $col):
              $cn = $col['name'] ?? $col['key'] ?? '';
              if ($cn === '') continue;
          ?>
            <th class="col-<?= h($cn) ?>"><?= h($col['label'] ?? $cn) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
    </div>
    <div id="<?= h($p) ?>-pagination" style="margin-top:8px;text-align:left"></div>
            <?php
            return ob_get_clean();
        }

        public function renderEmbeddedScripts(array $options = []): void {
            $p = $options['prefix'] ?? 'et';
            $readonly = $options['readonly'] ?? false;
            $hasExport = $options['hasExport'] ?? false;
            $hasPrint = $options['hasPrint'] ?? false;
            $visibleCols = $this->visibleColumns ?: $this->columns;

            $columnsJs = array_map(function ($c) {
                $out = $c;
                $out['key'] = $c['name'] ?? $c['key'] ?? '';
                unset($out['name']);
                return $out;
            }, $visibleCols);
            $columnsJson = json_encode($columnsJs, JSON_UNESCAPED_UNICODE);
            $searchCols = array_values(array_map(function ($c) {
                return $c['name'] ?? $c['key'] ?? '';
            }, $visibleCols));
            $searchColsJson = json_encode($searchCols, JSON_UNESCAPED_UNICODE);
            $lookupDataMapJson = json_encode($this->lookupData, JSON_UNESCAPED_UNICODE);
            $saveUrl = $options['saveUrl'] ?? '';
            $parentField = $options['parentField'] ?? '';
            $childFormUrl = $options['childFormUrl'] ?? '';
            $childFormName = $options['childFormName'] ?? '';
            $pageSize = $options['pageSize'] ?? 15;
            $totalsCallback = $options['totalsCallback'] ?? '';
            $totalsCallbackName = $totalsCallback ?: 'apply' . ucfirst($p) . 'Totals';
            $exportUrl = $options['exportUrl'] ?? '';
            $printUrl = $options['printUrl'] ?? '';
            $parentParam = $options['parentParam'] ?? ($parentField . '=');
            ?>
        <?php if (!$totalsCallback): ?>
        window.<?= $totalsCallbackName ?> = function (data) {
          if (!data || typeof data !== 'object') return;
          Object.keys(data).forEach(function (key) {
            var el = document.querySelector('[data-total-field="' + key + '"]');
            if (el) el.textContent = data[key];
          });
        };
        <?php endif; ?>
        window.init<?= ucfirst($p) ?>Table = function () {
          var table = document.getElementById('<?= $p ?>-table');
          if (!table) return;
          var groupIdEl = document.querySelector('input[name="id"]');
          var groupId = groupIdEl ? parseInt(groupIdEl.value, 10) : 0;
          if (!groupId) return;
          if (window['__<?= $p ?>Table'] && window['__<?= $p ?>Table'].parentId === groupId && window['__<?= $p ?>Table'].tableEl === table) return;
          window['__<?= $p ?>Table'] = null;
          var data = [];
          try { data = JSON.parse(table.dataset.items || '[]'); } catch(e) {}
          var formBody = document.querySelector('.form-modal-body') || document.querySelector('[data-form-modal]') || document.body;

          window['__<?= $p ?>Table'] = EmbeddedSubTable.create({
            tableEl: table,
            formBody: formBody,
            saveUrl: '<?= $saveUrl ?>',
            prefix: '<?= $p ?>',
            parentId: groupId,
            parentField: '<?= $parentField ?>',
            data: data,
            columns: <?= $columnsJson ?>,
            searchCols: <?= $searchColsJson ?>,
            pageSize: <?= $pageSize ?>,
            selWrapEl: '#<?= $p ?>-sel-wrap',
            selCountEl: '#<?= $p ?>-sel-count',
            checkAllEl: '#<?= $p ?>-check-all',
            paginationEl: '#<?= $p ?>-pagination',
            btnAddEl: '#<?= $p ?>-add-btn',
            btnEditEl: '#<?= $p ?>-edit-btn',
            btnCopyEl: '#<?= $p ?>-copy-btn',
            btnDeleteEl: '#<?= $p ?>-del-btn',
            btnRefreshEl: '#<?= $p ?>-refresh-btn',
            childFormUrl: '<?= $childFormUrl ?>',
            childFormName: '<?= $childFormName ?>',
            totalsCallback: <?= json_encode($totalsCallbackName, JSON_UNESCAPED_UNICODE) ?>,
            readonly: <?= json_encode($readonly) ?>,
            onRowDoubleClick: <?= $readonly ? 'null' : 'function (id) { window[\'__' . $p . 'Table\'].openChildForm(\'edit\', id); }' ?>,
            filterBannerEl: '#<?= $p ?>-filter-banner',
            filterChipsEl: '#<?= $p ?>-filter-chips',
            clearSearchBtnEl: '#<?= $p ?>-clear-search'
          });

          <?php if (!$readonly): ?>
          if (typeof InlineEdit !== 'undefined') {
            var ieFields = {};
            <?php foreach ($visibleCols as $col):
                $cn = $col['name'] ?? $col['key'] ?? '';
                $colType = !empty($col['param']) ? 'lookup' : ($col['type'] ?? 'text');
                $dbField = $col['param'] ?? $cn;
            ?>
            ieFields['<?= $cn ?>'] = { dbField: '<?= $dbField ?>', type: '<?= $colType ?>', label: <?= json_encode($col['label'] ?? $cn, JSON_UNESCAPED_UNICODE) ?> };
            <?php endforeach; ?>
            InlineEdit.init({
              tbody: table.querySelector('tbody'),
              saveUrl: '<?= $saveUrl ?>',
              fields: ieFields,
              getLookupData: function (field) {
                var map = <?= $lookupDataMapJson ?>;
                return map[field] || [];
              },
              validate: function (field, value) {
                var fieldCfg = ieFields[field];
                if (fieldCfg && fieldCfg.type === 'lookup' && (!value || value === '0' || value === 0)) return 'Выберите значение из списка';
                return null;
              },
              onSaveSuccess: function (data, field) {
                if (data && data.item && data.item.id) {
                  var item = data.item;
                  for (var i = 0; i < window['__<?= $p ?>Table'].data.length; i++) {
                    if (window['__<?= $p ?>Table'].data[i].id == item.id) { window['__<?= $p ?>Table'].data[i] = item; break; }
                  }
                  window['__<?= $p ?>Table'].selectedId = item.id;
                  window['__<?= $p ?>Table'].render();
                }
                if (typeof window.<?= $totalsCallbackName ?> === 'function') window.<?= $totalsCallbackName ?>(data);
                <?= $options['onSaveSuccessExtra'] ?? '' ?>
              }
            });
          }
          <?php endif; ?>

          var addBtn = formBody.querySelector('#<?= $p ?>-add-btn');
          if (addBtn) addBtn.addEventListener('click', function () {
            window['__<?= $p ?>Table'].openChildForm('new');
          });
          var editBtn = formBody.querySelector('#<?= $p ?>-edit-btn');
          if (editBtn) editBtn.addEventListener('click', function () {
            if (window['__<?= $p ?>Table'].selectedId) window['__<?= $p ?>Table'].openChildForm('edit', window['__<?= $p ?>Table'].selectedId);
          });
          var delBtn = formBody.querySelector('#<?= $p ?>-del-btn');
          if (delBtn) delBtn.addEventListener('click', function () {
            if (window['__<?= $p ?>Table'].selectedId) window['__<?= $p ?>Table'].openChildForm('delete', window['__<?= $p ?>Table'].selectedId);
          });
          var copyBtn = formBody.querySelector('#<?= $p ?>-copy-btn');
          if (copyBtn) copyBtn.addEventListener('click', function () {
            if (window['__<?= $p ?>Table'].selectedId) window['__<?= $p ?>Table'].openChildForm('copy', window['__<?= $p ?>Table'].selectedId);
          });
          var refreshBtn = formBody.querySelector('#<?= $p ?>-refresh-btn');
          if (refreshBtn) refreshBtn.addEventListener('click', function () {
            window['__<?= $p ?>Table'].refresh();
          });

          var searchInput = formBody.querySelector('#<?= $p ?>-search-input');
          var searchBtn = formBody.querySelector('#<?= $p ?>-search-btn');
          var searchCondBtn = formBody.querySelector('#<?= $p ?>-search-cond-btn');
          if (searchInput && searchBtn) {
            searchBtn.addEventListener('click', function () {
              var q = searchInput.value;
              window['__<?= $p ?>Table'].searchText = q;
              window['__<?= $p ?>Table'].searchActive = q.length > 0;
              window['__<?= $p ?>Table'].currentPage = 1;
              window['__<?= $p ?>Table'].render();
            });
            searchInput.addEventListener('keydown', function (e) {
              if (e.key === 'Enter') { e.preventDefault(); searchBtn.click(); }
            });
          }
          if (searchCondBtn) {
            searchCondBtn.addEventListener('click', function (e) {
              e.stopPropagation();
              SearchPanel.open({
                target: searchCondBtn,
                columns: <?= json_encode(array_map(function ($c) { return ['key' => $c['name'] ?? $c['key'] ?? '', 'label' => $c['label'] ?? '']; }, $visibleCols), JSON_UNESCAPED_UNICODE) ?>,
                currentCond: window['__<?= $p ?>Table'] ? window['__<?= $p ?>Table'].searchCond : 'contains',
                currentCols: window['__<?= $p ?>Table'] ? window['__<?= $p ?>Table'].searchCols : null,
                onApply: function (state) {
                  if (!window['__<?= $p ?>Table']) return;
                  window['__<?= $p ?>Table'].searchCols = Array.from(state.cols);
                  window['__<?= $p ?>Table'].searchCond = state.cond;
                  window['__<?= $p ?>Table'].searchActive = true;
                  window['__<?= $p ?>Table'].currentPage = 1;
                  window['__<?= $p ?>Table'].render();
                }
              });
            });
          }

          var selClear = formBody.querySelector('#<?= $p ?>-sel-clear');
          if (selClear) selClear.addEventListener('click', function (e) {
            e.preventDefault();
            window['__<?= $p ?>Table'].checkedIds = new Set();
            window['__<?= $p ?>Table'].render();
          });
          var selInvert = formBody.querySelector('#<?= $p ?>-sel-invert');
          if (selInvert) selInvert.addEventListener('click', function (e) {
            e.preventDefault();
            var rows = window['__<?= $p ?>Table'].tbody.querySelectorAll('.d2-row-check');
            rows.forEach(function (cb) {
              var id = parseInt(cb.dataset.id, 10);
              if (window['__<?= $p ?>Table'].checkedIds.has(id)) { window['__<?= $p ?>Table'].checkedIds.delete(id); } else { window['__<?= $p ?>Table'].checkedIds.add(id); }
              cb.checked = window['__<?= $p ?>Table'].checkedIds.has(id);
            });
            window['__<?= $p ?>Table']._updateCheckedUI();
          });
          var selDelete = formBody.querySelector('#<?= $p ?>-sel-delete');
          if (selDelete) selDelete.addEventListener('click', function (e) {
            e.preventDefault();
            window['__<?= $p ?>Table'].batchDeleteChecked();
          });
          var selShow = formBody.querySelector('#<?= $p ?>-sel-show');
          if (selShow) selShow.addEventListener('click', function (e) {
            e.preventDefault();
            window['__<?= $p ?>Table'].showOnlyChecked = !window['__<?= $p ?>Table'].showOnlyChecked;
            window['__<?= $p ?>Table'].currentPage = 1;
            window['__<?= $p ?>Table'].render();
          });

          function sgQs(tbl) {
            if (!tbl || !tbl.searchActive || !tbl.searchText) return '';
            var q = 'q=' + encodeURIComponent(tbl.searchText) + '&sf=1';
            if (tbl.searchCols && tbl.searchCols.length > 0) q += '&cols=' + encodeURIComponent(tbl.searchCols.join(','));
            q += '&cond=' + encodeURIComponent(tbl.searchCond || 'contains');
            return q;
          }

          <?php if ($hasExport && $exportUrl): ?>
          var sgGroupId = groupId;
          var sgBaseUrl = '<?= $exportUrl ?>?<?= $parentParam ?>' + sgGroupId;
          var sgExportAll = formBody.querySelector('#<?= $p ?>-export-all');
          if (sgExportAll) sgExportAll.addEventListener('click', function (e) {
            e.preventDefault();
            var tbl = window['__<?= $p ?>Table'];
            var ids = tbl && tbl.showOnlyChecked ? Array.from(tbl.checkedIds) : [];
            var url = sgBaseUrl + '&format=csv' + (ids.length > 0 ? '&ids=' + ids.join(',') : '');
            var q = sgQs(tbl); if (q) url += '&' + q;
            window.open(url, '_blank');
          });
          var sgExportAllXls = formBody.querySelector('#<?= $p ?>-export-all-xls');
          if (sgExportAllXls) sgExportAllXls.addEventListener('click', function (e) {
            e.preventDefault();
            var tbl = window['__<?= $p ?>Table'];
            var ids = tbl && tbl.showOnlyChecked ? Array.from(tbl.checkedIds) : [];
            var url = sgBaseUrl + '&format=xls' + (ids.length > 0 ? '&ids=' + ids.join(',') : '');
            var q = sgQs(tbl); if (q) url += '&' + q;
            window.open(url, '_blank');
          });
          var selExport = formBody.querySelector('#<?= $p ?>-sel-export');
          if (selExport) selExport.addEventListener('click', function (e) {
            e.preventDefault();
            var ids = Array.from(window['__<?= $p ?>Table'].checkedIds);
            if (ids.length === 0) return;
            var url = sgBaseUrl + '&format=csv&ids=' + ids.join(',');
            var q = sgQs(window['__<?= $p ?>Table']); if (q) url += '&' + q;
            window.open(url, '_blank');
          });
          <?php endif; ?>

          <?php if ($hasPrint && $printUrl): ?>
          var sgPrintUrl = '<?= $printUrl ?>?<?= $parentParam ?>' + groupId;
          var sgPrintAll = formBody.querySelector('#<?= $p ?>-print-all');
          if (sgPrintAll) sgPrintAll.addEventListener('click', function (e) {
            e.preventDefault();
            var tbl = window['__<?= $p ?>Table'];
            var ids = tbl && tbl.showOnlyChecked ? Array.from(tbl.checkedIds) : [];
            var url = sgPrintUrl + (ids.length > 0 ? '&ids=' + ids.join(',') : '');
            var q = sgQs(tbl); if (q) url += '&' + q;
            window.open(url, '_blank');
          });
          var sgPrintPage = formBody.querySelector('#<?= $p ?>-print-page');
          if (sgPrintPage) sgPrintPage.addEventListener('click', function (e) {
            e.preventDefault();
            var tbl = window['__<?= $p ?>Table'];
            var ids = tbl && tbl.showOnlyChecked ? Array.from(tbl.checkedIds) : [];
            var url = sgPrintUrl + '&page=' + (tbl ? tbl.currentPage : 1) + (ids.length > 0 ? '&ids=' + ids.join(',') : '');
            var q = sgQs(tbl); if (q) url += '&' + q;
            window.open(url, '_blank');
          });
          var selPrint = formBody.querySelector('#<?= $p ?>-sel-print');
          if (selPrint) selPrint.addEventListener('click', function (e) {
            e.preventDefault();
            var ids = Array.from(window['__<?= $p ?>Table'].checkedIds);
            if (ids.length === 0) return;
            var url = sgPrintUrl + '&ids=' + ids.join(',');
            var q = sgQs(window['__<?= $p ?>Table']); if (q) url += '&' + q;
            window.open(url, '_blank');
          });
          <?php endif; ?>

          var tabs = formBody.querySelectorAll('.tab-header');
          var panes = formBody.querySelectorAll('.tab-pane');
          tabs.forEach(function (h) {
            h.addEventListener('click', function () {
              var idx = parseInt(h.dataset.tabIndex, 10);
              tabs.forEach(function (t) { t.classList.remove('active'); });
              panes.forEach(function (p) { p.classList.remove('active'); });
              h.classList.add('active');
              var pane = formBody.querySelector('.tab-pane[data-tab-index="' + idx + '"]');
              if (pane) pane.classList.add('active');
              if (pane && pane.querySelector('#<?= $p ?>-table') && window['__<?= $p ?>Table']) {
                var tbl = pane.querySelector('#<?= $p ?>-table');
                if (tbl) tbl.focus();
              }
            });
          });
        }
            <?php
        }

        public function renderScripts(array $options = []): void {
            $formPrefix = $options['formPrefix'] ?? $this->formPrefix;
            $formModalConfig = $options['formModalConfig'] ?? [];
            $lookupData = $options['lookupData'] ?? $this->lookupData;
            $searchPanelConfig = $options['searchPanelConfig'] ?? [];
            $colFilters = $options['colFilters'] ?? [];
            $extraCode = $options['extraCode'] ?? '';
            $extraScripts = $options['extraScripts'] ?? [];
            $currentSort = $options['currentSort'] ?? null;
            $skipInlineEdit = $options['skipInlineEdit'] ?? false;
            $searchColumnsOverride = $options['searchColumns'] ?? null;
            $sortColumnsOverride = $options['sortColumns'] ?? null;
            $defaultColumnsOverride = $options['defaultColumns'] ?? null;
            $extraRowSelectBtns = $options['extraRowSelectBtns'] ?? [];

            // script includes
            $core = [
                'assets/lookup.js',
                'assets/inline-edit.js',
                'assets/row-select.js',
                'assets/keyboard.js',
                'assets/table-keyboard.js',
                'assets/selection-toolbar.js',
                'assets/search-panel.js',
                'assets/sort-panel.js',
                'assets/column-resize.js',
                'assets/form-modal-core.js',
                'assets/columns-panel.js',
                'assets/export-modal.js',
                'assets/column-filter.js',
            ];
            $allScripts = array_values(array_unique(array_merge($core, $extraScripts)));
            foreach ($allScripts as $src) {
                $fullPath = __DIR__ . '/../' . $src;
                $ts = file_exists($fullPath) ? filemtime($fullPath) : 0;
                ?><script src="<?= h($src) ?>?v=<?= $ts ?>"></script>
<?php
            }

            if ($searchColumnsOverride !== null) {
                $searchColumns = $searchColumnsOverride;
            } else {
                $searchColumns = array_values(array_map(function ($k) {
                    $m = $this->colMeta[$k] ?? null;
                    return ['key' => $k, 'label' => $m ? $m['label'] : $k];
                }, array_keys($this->searchColExprs)));
            }

            if ($sortColumnsOverride !== null) {
                $sortColumns = $sortColumnsOverride;
            } else {
                $sortColumns = array_values(array_map(function ($vc) {
                    return ['key' => $vc['name'], 'label' => $vc['label']];
                }, $this->visibleColumns));
            }

            $preserveParams = ['sort'];
            if ($this->countryFilterField) $preserveParams[] = $this->countryFilterField;
            $preserveParamsJson = json_encode($preserveParams);

            $searchColumnsJson  = json_encode($searchColumns, JSON_UNESCAPED_UNICODE);
            $sortColumnsJson    = json_encode($sortColumns, JSON_UNESCAPED_UNICODE);

            $colsForInit = $this->columnsConfig ?: $this->visibleColumns;
            $initialColumnsJson = json_encode(array_map(function ($c) {
                return ['name' => $c['name'], 'label' => $c['label'], 'visible' => !empty($c['visible'])];
            }, $colsForInit), JSON_UNESCAPED_UNICODE);
            if ($defaultColumnsOverride !== null) {
                $defaultColumnsJson = json_encode($defaultColumnsOverride, JSON_UNESCAPED_UNICODE);
            } else {
                $defaultColumnsJson = json_encode(array_map(function ($c) {
                    return ['name' => $c['name'], 'label' => $c['label'], 'visible' => true];
                }, $this->columns), JSON_UNESCAPED_UNICODE);
            }

            // inline fields for InlineEdit
            $inlineFields = $this->buildInlineFields();
            $inlineFieldsJson = json_encode($inlineFields, JSON_UNESCAPED_UNICODE);
            $valCases = $this->buildInlineValCases();

            $pageUrl = $this->baseUrl;
            $exportUrl = $this->table . '_export.php';
            $printUrl = $this->table . '_print.php';
            $fieldSaveUrl = $this->table . '_field_save.php';
            $columnsSaveUrl = $this->table . '_columns_save.php';
            $columnResizeUrl = $this->table . '_column_width_save.php';
            $tableKey = $this->table;

            $marksTbl = $this->marksTbl;
            ?><script>
    function closeAllPanels() {
      document.querySelectorAll('.search-cond-panel.open, .search-cond-pop.open, .columns-panel.open, .col-filter-panel.open').forEach(function (p) { p.classList.remove('open'); if (p.style) p.style.display = ''; });
    }
    window.__focusAfterSave = function (params, form, data) {
      var mode = (form.querySelector('input[name="mode"]') || {}).value || '';
      if (mode === 'new' || mode === 'copy') {
        if (data.id) { params.set('focus', String(data.id)); if (data.page) params.set('page', String(data.page)); else params.delete('page'); }
        else { params.delete('focus'); }
      } else if (mode === 'edit') {
        var eid = parseInt((form.querySelector('input[name="id"]') || {}).value || '0', 10) || 0;
        if (eid > 0) params.set('focus', String(eid)); else params.delete('focus');
      } else if (mode === 'delete') {
        var did = parseInt((form.querySelector('input[name="id"]') || {}).value || '0', 10) || 0;
        var rows = Array.from(document.querySelectorAll('tr[data-row-id]'));
        var idx = rows.findIndex(function (tr) { return parseInt(tr.dataset.rowId, 10) === did; });
        var tb = (document.querySelector('.toolbar') || {}).dataset || {};
        var cp = parseInt(tb.page || '1', 10), tp = parseInt(tb.pages || '1', 10);
        if (idx >= 0) {
          if (idx + 1 < rows.length) { params.set('focus', String(parseInt(rows[idx + 1].dataset.rowId, 10))); }
          else if (cp < tp) { params.set('page', String(cp + 1)); params.set('focus', 'first'); }
          else if (idx - 1 >= 0) { params.set('focus', String(parseInt(rows[idx - 1].dataset.rowId, 10))); }
          else if (cp > 1) { params.set('page', String(cp - 1)); params.delete('focus'); }
          else { params.delete('focus'); }
        } else { params.delete('focus'); }
      } else { params.delete('focus'); }
    };
    document.querySelectorAll('.submenu a').forEach(function (a) {
      a.addEventListener('click', function () {
        var item = a.closest('.menu-item');
        if (item) item.dispatchEvent(new MouseEvent('mouseleave', { bubbles: true }));
      });
    });

    var _tb = document.querySelector('.toolbar');
    SelectionToolbar.initTableSelection('<?= h($pageUrl) ?>', _tb ? _tb.getAttribute('data-search') || '' : '');

      SelectionToolbar.init({
        pageUrl: '<?= h($pageUrl) ?>',
        search: _tb ? _tb.getAttribute('data-search') || '' : '',
        getExportUrl: function () {
          if (document.querySelectorAll('.row-check:checked').length === 0) return null;
          return '<?= h($exportUrl) ?>?format=csv&all=1';
        },
        getPrintUrl: function () {
          if (document.querySelectorAll('.row-check:checked').length === 0) return null;
          return '<?= h($printUrl) ?>?all=1';
        },
<?php if ($marksTbl): ?>
        getInvertUrl: function () {
          var p = new URLSearchParams(location.search);
          p.delete('ids');
          return '<?= h($pageUrl) ?>?action=invertSelection&' + p.toString();
        }
<?php endif; ?>
      });
<?php
$searchPanelExtra = '';
$searchPanelKeys = ['popupCheckboxes', 'emptyClass', 'labels', 'onApply', 'onToggle', 'onSubmit'];
foreach ($searchPanelKeys as $k) {
    if (isset($searchPanelConfig[$k])) {
        $v = $searchPanelConfig[$k];
        if (is_string($v) && substr($v, 0, 8) === 'function') {
            $searchPanelExtra .= ',' . "\n        " . json_encode($k, JSON_UNESCAPED_UNICODE) . ': ' . $v;
        } else {
            $searchPanelExtra .= ',' . "\n        " . json_encode($k, JSON_UNESCAPED_UNICODE) . ': ' . json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
}
?>
      SearchPanel.init({
        form: document.getElementById('searchForm'),
        condBtn: document.getElementById('searchCondBtn'),
        toggleBtn: document.getElementById('searchToggleBtn'),
        columns: <?= $searchColumnsJson ?>,
        closeAllPanels: closeAllPanels,
        pageUrl: '<?= h($pageUrl) ?>',
        preserveParams: <?= $preserveParamsJson ?><?= $searchPanelExtra ?>
      });

      SortPanel.init({
        btn: document.getElementById('sortBtn'),
        columns: <?= $sortColumnsJson ?>,
        pageUrl: '<?= h($pageUrl) ?>',
        mode: 'modal',
        directions: [
          { key: 'asc',  label: 'По возрастанию' },
          { key: 'desc', label: 'По убыванию' },
        ]<?= $currentSort !== null ? ',' . "\n        " . 'currentSort: ' . json_encode($currentSort, JSON_UNESCAPED_UNICODE) : '' ?>
      });

    ExportModal.init();
    </script>
<?php if ($marksTbl): ?>
    <script>
    window.clearSelection = function () {
      fetch(window.__marksUrl('clearSelection'), { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (d.ok) location.reload(); });
    };
    window.invertSelection = function () {
      fetch(window.__marksUrl('invertSelection'), { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (d.ok) location.reload(); });
    };
    window.toggleShowOnly = function () {
      fetch(window.__marksUrl('toggleShowOnly'), { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (d.ok) location.reload(); });
    };
    </script>
<?php endif; ?>
<?php
$cfInit = is_array($colFilters) ? $colFilters : [];
if ($colFilters === true) {
    foreach ($this->colFilters as $col => $cfg) {
        $cfg = $this->normalizeColFilterConfig($cfg);
        $cfParam = $cfg['param'] ?? ($this->colMeta[$col]['param'] ?? ($col . '_id'));
        $init = ['thSelector' => '.col-' . $col, 'pageUrl' => $this->baseUrl];
        if ($cfParam !== $col . '_id') $init['param'] = $cfParam;
        if (isset($cfg['options'])) $init['options'] = $cfg['options'];
        $cfInit[] = $init;
    }
}
if (!empty($cfInit)): ?>
    <script>
<?php foreach ($cfInit as $cf): ?>
    ColumnFilter.init(<?= json_encode($cf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
<?php endforeach; ?>
    </script>
<?php endif; ?>
    <?php
            // render form modal script
            $fmFormPrefix = $formPrefix;
            $fmBaseUrl = $pageUrl;
            $fmLookupTables = $formModalConfig['lookup_tables'] ?? [];
            $fmLookupData = $formModalConfig['lookup_data'] ?? [];
            $fmCleanup = $formModalConfig['redirect_cleanup'] ?? [];
            $fmExtraRestore = $formModalConfig['extra_restore'] ?? '';
            $fmExtraOpen = $formModalConfig['extra_open'] ?? '';
            $fmOnSaveExtra = $formModalConfig['on_save_redirect_extra'] ?? '';
            $fmAutoInitTables = $formModalConfig['autoInitTables'] ?? [];
            $fmAutoBindLookups = $formModalConfig['autoBindLookups'] ?? true;
            $fmAutoSetupTabs = $formModalConfig['autoSetupTabs'] ?? true;
            $fmUrlSep = strpos($fmBaseUrl, '?') === false ? '?' : '&';
            $fmFormAction = $formModalConfig['form_action'] ?? ($fmFormPrefix . '.php');
            $fmCleanupJs = json_encode($fmCleanup);
            $fmLookupTablesJs = json_encode($fmLookupTables);
            $fmFormActionJs = json_encode($fmFormAction, JSON_UNESCAPED_SLASHES);
            $fmBaseUrlJs = json_encode($fmBaseUrl, JSON_UNESCAPED_SLASHES);
            $fmFormPrefixJs = json_encode($fmFormPrefix);

            $autoInitChunks = [];
            if ($fmAutoBindLookups) {
                $autoInitChunks[] = "
            root.querySelectorAll('[data-lookup]:not([data-lookup-bound])').forEach(function(el){
                el.setAttribute('data-lookup-bound','1');
                if(!el.__lookupBound){
                    try{var _d=JSON.parse(el.getAttribute('data-countries')||'[]');
                    var _ro=el.hasAttribute('data-readonly');
                    window.bindLookup({root:el,data:_d,readonly:_ro});}catch(ex){}
                }
            });";
            }
            if ($fmAutoSetupTabs) {
                $autoInitChunks[] = "
            var _tc=root.querySelector('.tab-container');
            if(_tc&&!_tc.__fmTabs){
                _tc.__fmTabs=true;
                var _hds=_tc.querySelectorAll('.tab-header'),_pns=_tc.querySelectorAll('.tab-pane');
                _hds.forEach(function(h){
                    h.addEventListener('click',function(){
                        var _i=parseInt(h.dataset.tabIndex,10);
                        _hds.forEach(function(t){t.classList.remove('active');});
                        _pns.forEach(function(p){p.classList.remove('active');});
                        h.classList.add('active');
                        var _p=_tc.querySelector('.tab-pane[data-tab-index=\"'+_i+'\"]');
                        if(_p)_p.classList.add('active');
                    });
                });
            }";
            }
            foreach ($fmAutoInitTables as $p) {
                $fn = 'init' . ucfirst($p) . 'Table';
                $autoInitChunks[] = "if(typeof $fn==='function')$fn();";
            }
            $fmAutoInitJs = empty($autoInitChunks) ? '' : implode("\n", $autoInitChunks);
    ?>
    <script>
    (function () {
      var backdrop = document.getElementById('formModal');
      var body     = document.getElementById('formModalBody');
      if (!backdrop || !body) return;

      var stashed = null;
      var _fmDirty = false;

      function autoInitFormBody(root) {
        if (typeof root === 'undefined') root = body;
        root.querySelectorAll('script:not([src])').forEach(function(s){
          try{ eval(s.textContent); }catch(ex){ console.error('[FM] script error', ex); }
        });
        <?= $fmAutoInitJs ?>
        try {
          if (typeof window.ColumnResize !== 'undefined') {
            root.querySelectorAll('.data-table').forEach(function(t) {
              t.querySelectorAll('.col-resize-handle').forEach(function(h) { h.remove(); });
              t.removeAttribute('data-col-resize-inited');
              var opts = { selector: '#' + t.id };
              if (t.dataset.colResizeUrl) opts.saveUrl = t.dataset.colResizeUrl;
              if (t.dataset.colResizeTbl) opts.tbl = t.dataset.colResizeTbl;
              window.ColumnResize.init(opts);
            });
          }
        } catch(ex) { console.error('[FM] ColumnResize re-init error', ex); }
      }

      function stashCurrentForm(onRestore, estSelectedId) {
        var form = body.querySelector('form[data-form-modal]');
        if (!form) return;
        var active = document.activeElement;
        body.querySelectorAll('input, textarea, select').forEach(function (el) {
          if (el.type === 'checkbox' || el.type === 'radio') {
            if (el.checked) el.setAttribute('checked', ''); else el.removeAttribute('checked');
          } else if (el.tagName === 'SELECT') {
            Array.from(el.options).forEach(function (o) { o.removeAttribute('selected'); });
            var sel = el.options[el.selectedIndex];
            if (sel) sel.setAttribute('selected', '');
          } else {
            el.setAttribute('value', el.value);
          }
        });
        var savedTableSelections = {};
        Object.keys(window).forEach(function(k) {
          if (k.indexOf('__') === 0 && k.indexOf('Table') === k.length - 5 && window[k] && typeof window[k].selectedId === 'number') {
            savedTableSelections[k] = window[k].selectedId;
          }
        });
        if (estSelectedId && !Object.keys(savedTableSelections).length) {
          savedTableSelections['__estFallback'] = estSelectedId;
        }
        stashed = {
          html: body.innerHTML,
          onRestore: onRestore || function () {},
          activeId: active && active.id ? active.id : null,
          _tableSelections: savedTableSelections
        };
      }

      function restoreStashedForm(data) {
        if (!stashed) return false;
        var savedTableSelections = stashed._tableSelections || {};
        body.innerHTML = stashed.html;
        var old = stashed;
        stashed = null;
        var form = body.querySelector('form[data-form-modal]');
        bindForm(form);
        <?php foreach ($fmLookupTables as $t): ?>
        body.querySelectorAll('[data-lookup="<?= $t ?>"]').forEach(function (el) { bindLookup(el, <?= json_encode($t) ?>); });
        <?php endforeach; ?>
            FormModalCore.bindFormTabTrap(form);
            <?= $fmExtraRestore ?>
            try { <?= $fmExtraOpen ?> } catch(ex) { console.error('[FM] extraOpen error', ex); }
        autoInitFormBody(body);
        if (savedTableSelections && Object.keys(savedTableSelections).length) {
          var restoreFn = function() {
            console.log('[FM] restoreFn running, savedTableSelections=', JSON.stringify(savedTableSelections), 'window.__sgTable=', !!window.__sgTable);
            Object.keys(savedTableSelections).forEach(function(k) {
              if (k === '__estFallback') return;
              var tbl = window[k];
              if (tbl && typeof tbl.selectedId === 'number' && savedTableSelections[k]) {
                var id = savedTableSelections[k];
                var found = false;
                if (tbl.data) { for (var i = 0; i < tbl.data.length; i++) { if (tbl.data[i].id == id) { found = true; break; } } }
                if (found) {
                  tbl.selectedId = id;
                  tbl.render();
                  if (tbl.tbody) {
                    var row = tbl.tbody.querySelector('tr[data-id="' + id + '"]');
                    if (row) row.scrollIntoView({ block: 'nearest' });
                  }
                }
              }
            });
            if (savedTableSelections['__estFallback']) {
              var fallbackId = savedTableSelections['__estFallback'];
              Object.keys(window).forEach(function(k) {
                if (k.indexOf('__') === 0 && k.indexOf('Table') === k.length - 5 && window[k] && typeof window[k].selectedId === 'number' && window[k].data) {
                  var tbl = window[k];
                  var found = false;
                  for (var i = 0; i < tbl.data.length; i++) { if (tbl.data[i].id == fallbackId) { found = true; break; } }
                  if (found) {
                    tbl.selectedId = fallbackId;
                    tbl.render();
                    if (tbl.tbody) {
                      var row = tbl.tbody.querySelector('tr[data-id="' + fallbackId + '"]');
                      if (row) row.scrollIntoView({ block: 'nearest' });
                    }
                  }
                }
              });
            }
          };
          setTimeout(restoreFn, 50);
          setTimeout(restoreFn, 200);
        }
        if (data) { try { old.onRestore(data, body); } catch (e) { console.error('[FM] onRestore error', e); } }
        if (old.activeId) {
          var el = body.querySelector('[id="' + old.activeId.replace(/"/g, '\\"') + '"]');
          if (el && !el.readOnly) { el.focus(); if (el.select) el.select(); }
        } else {
          FormModalCore.focusFirstField(body);
        }
        return true;
      }

      function openFormModal(url, stash) {
        stashCurrentForm(stash && stash.onRestore, stash && stash._estSelectedId);
        body.innerHTML = '<div style="padding:20px;color:var(--muted);">Загрузка…</div>';
        backdrop.classList.add('open');
        document.body.style.overflow = 'hidden';
        fetch(FormModalCore.appendAjax(url), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
          .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
          .then(function (data) {
            if (!data || typeof data.html !== 'string') throw new Error('bad response');
            body.innerHTML = data.html;
            var form = body.querySelector('form[data-form-modal]');
            bindForm(form);
            <?php foreach ($fmLookupTables as $t): ?>
            body.querySelectorAll('[data-lookup="<?= $t ?>"]').forEach(function (el) { bindLookup(el, <?= json_encode($t) ?>); });
            <?php endforeach; ?>
            FormModalCore.bindFormTabTrap(form);
            FormModalCore.focusFirstField(body);
            <?= $fmExtraOpen ?>
            autoInitFormBody(body);
          })
          .catch(function (err) {
            body.innerHTML = '<div class="flash flash--error">Ошибка загрузки: ' + (err && err.message ? err.message : 'неизвестная ошибка') + '</div>';
            if (console && console.error) console.error('[form-modal]', err);
          });
      }

      function closeFormModal() {
        backdrop.classList.remove('open');
        document.body.style.overflow = '';
        body.innerHTML = '';
        stashed = null;
      }

      function bindForm(form) {
        if (!form) return;
        // Enter в форме = нажать основную submit-кнопку (в т.ч. "Удалить"),
        // даже если фокус на readonly-поле (tabindex=-1).
        form.addEventListener('keydown', function (e) {
          if (e.key !== 'Enter' || e.shiftKey || e.ctrlKey || e.altKey || e.metaKey) return;
          var t = e.target;
          if (!t || t.tagName === 'TEXTAREA') return;
          if (t.tagName === 'SELECT') return;
          if (t.closest && t.closest('.lookup-pop')) return;
          if (t.closest && t.closest('.col-filter-panel, .search-cond-panel, .columns-panel')) return;
          if (t.type === 'button' || t.type === 'submit' || t.type === 'reset') return;
          var submitBtn = form.querySelector('button[type="submit"]:not([disabled])');
          if (!submitBtn) return;
          e.preventDefault();
          submitBtn.click();
        });
        form.addEventListener('submit', function (e) {
          e.preventDefault();
          var fd = new FormData(form);
          var submitBtn = e.submitter || form.querySelector('button[type="submit"]');
          if (submitBtn && submitBtn.name) fd.set(submitBtn.name, submitBtn.value || '1');
          fetch(form.getAttribute('action') || <?= $fmFormActionJs ?>, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
          })
          .then(function (r) { return r.json(); })
           .then(function (data) { if (data && data.ok) {
              _fmDirty = true;
              if (stashed) {
                restoreStashedForm(data);
              } else if (fd.get('action') === 'apply') {
                body.innerHTML = data.html;
                var f = body.querySelector('form[data-form-modal]');
                bindForm(f);
                <?php foreach ($fmLookupTables as $t): ?>
                body.querySelectorAll('[data-lookup="<?= $t ?>"]').forEach(function (el) { bindLookup(el, <?= json_encode($t) ?>); });
                <?php endforeach; ?>
                FormModalCore.bindFormTabTrap(f);
                <?= $fmExtraOpen ?>
                autoInitFormBody(body);
                if (data && data.focusField) { var el = body.querySelector('[name="' + data.focusField + '"]'); if (el) { el.focus(); if (el.select) el.select(); } }
                else { FormModalCore.focusFirstField(body); }
              } else {
                closeFormModal();
                var params = new URLSearchParams(location.search);
                <?php if (!empty($fmCleanup)): ?>
                <?= json_encode($fmCleanup) ?>.forEach(function (k) { params.delete(k); });
                <?php endif; ?>
                if (typeof window.__focusAfterSave === 'function') window.__focusAfterSave(params, form, data); else FormModalCore.setFocusAfterSave(params, form, data);
                <?= $fmOnSaveExtra ?>
                location.href = <?= $fmBaseUrlJs ?> + '<?= $fmUrlSep ?>' + params.toString();
              }
            } else {
              body.innerHTML = (data && data.html) || '<div class="flash flash--error">Ошибка подключения к БД</div>';
              var f = body.querySelector('form[data-form-modal]');
              bindForm(f);
              <?php foreach ($fmLookupTables as $t): ?>
              body.querySelectorAll('[data-lookup="<?= $t ?>"]').forEach(function (el) { bindLookup(el, <?= json_encode($t) ?>); });
              <?php endforeach; ?>
              FormModalCore.bindFormTabTrap(f);
              <?= $fmExtraOpen ?>
              autoInitFormBody(body);
              if (data && data.focusField) { var el = body.querySelector('[name="' + data.focusField + '"]'); if (el) { el.focus(); if (el.select) el.select(); } }
              else { FormModalCore.focusFirstField(body); }
            }
          })
          .catch(function (err) {
            var flash = document.createElement('div');
            flash.className = 'flash flash--error';
            flash.textContent = 'Ошибка подключения к БД: ' + (err && err.message ? err.message : 'unknown');
            form.insertBefore(flash, form.firstChild);
          });
        });
      }

      function bindLookup(root, tableName) {
        if (!root) return;
        <?php if (!empty($fmLookupData)): ?>
        var dataVar = <?= json_encode($fmLookupData) ?>;
        var list = window[dataVar[tableName]] || [];
        <?php else: ?>
        var list = JSON.parse(root.getAttribute('data-countries') || '[]');
        <?php endif; ?>
        var isReadonly = root.hasAttribute('data-readonly');
        window.bindLookup({
          root: root,
          data: list,
          readonly: isReadonly
        });
      }

      function closeOrReload() {
        if (_fmDirty) {
          _fmDirty = false;
          var params = new URLSearchParams(location.search);
          var idEl = body.querySelector('input[name="id"]');
          var focusId = idEl ? parseInt(idEl.value, 10) : 0;
          if (focusId > 0) params.set('focus', String(focusId));
          closeFormModal();
          location.href = <?= $fmBaseUrlJs ?> + '<?= $fmUrlSep ?>' + params.toString();
        } else {
          closeFormModal();
        }
      }

      document.addEventListener('click', function (e) {
        if (backdrop.classList.contains('open')) {
          var cancelA = e.target.closest('a.btn-secondary');
          if (cancelA && cancelA.closest('.form-actions')) {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (stashed) { restoreStashedForm(null); } else { closeOrReload(); }
            return;
          }
        }
        if (backdrop.classList.contains('open')) {
          var addLink = e.target.closest('a[data-lookup-add]');
          if (addLink) {
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            var target = addLink.getAttribute('data-lookup-add');
            if (!target) return;
            openFormModal(addLink.getAttribute('href') || (target + '_form.php?mode=new'), {
              onRestore: function (data, bodyEl) {
                if (data && data.id && data.name) {
                  var container = bodyEl.querySelector('[data-lookup="' + target + '"]');
                  if (!container) return;
                  var idEl   = container.querySelector('[data-lookup-id]');
                  var nameEl = container.querySelector('.lookup-input');
                  if (idEl)   idEl.value   = String(data.id);
                  if (nameEl) nameEl.value = data.name;
                  var items = [];
                  try { items = JSON.parse(container.getAttribute('data-countries') || '[]'); } catch (e) {}
                  if (!items.find(function (c) { return c.id === data.id; })) {
                    items.push({ id: data.id, name: data.name });
                    items.sort(function (a, b) { return a.name.localeCompare(b.name, 'ru'); });
                    container.setAttribute('data-countries', JSON.stringify(items));
                  }
                }
              }
            });
            return;
          }
        }
        var a = e.target.closest('a[href*="<?= $fmFormPrefix ?>.php"]');
        if (a) {
          if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1) return;
          e.preventDefault();
          e.stopImmediatePropagation();
          openFormModal(a.getAttribute('href'));
          return;
        }
        var b = e.target.closest('button[data-form-open], button[onclick*="<?= $fmFormPrefix ?>.php"]');
        if (b) {
          var url = b.getAttribute('data-form-open');
          if (!url && b.getAttribute('onclick')) {
            var m = b.getAttribute('onclick').match(/['"]([^'"]*<?= $fmFormPrefix ?>\.php[^'"]*)['"]/);
            if (m) url = m[1];
          }
          if (url) {
            e.preventDefault();
            e.stopImmediatePropagation();
            openFormModal(url);
          }
        }
      }, true);

      backdrop.addEventListener('click', function (e) {
        if (e.target.closest('[data-form-close]')) {
          if (stashed) restoreStashedForm(null); else { closeOrReload(); }
        }
      });

      document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (!backdrop.classList.contains('open')) return;
        var popOpen = body.querySelector('.lookup-pop.open');
        if (popOpen) return;
        e.preventDefault();
        if (stashed) restoreStashedForm(null); else { closeOrReload(); }
      });

      // Enter в открытой модалке = нажать основную submit-кнопку формы
      // (в т.ч. "Удалить"), даже если фокус не внутри формы (readonly-поля).
      document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || e.shiftKey || e.ctrlKey || e.altKey || e.metaKey) return;
        if (!backdrop.classList.contains('open')) return;
        if (e.defaultPrevented) return;
        var t = e.target;
        if (!t) return;
        if (t.tagName === 'TEXTAREA' || t.tagName === 'SELECT') return;
        if (t.closest && t.closest('.lookup-pop, .col-filter-panel, .search-cond-panel, .columns-panel')) return;
        if (t.closest && t.closest('table')) return;
        if (t.closest && t.closest('[data-form-close], [data-lookup-add], [type="reset"], .lookup-tool, .col-filter-btn, .lookup-tool')) return;
        var form = body.querySelector('form[data-form-modal]');
        if (!form) return;
        var submitBtn = form.querySelector('button[type="submit"]:not([disabled])');
        if (!submitBtn) return;
        e.preventDefault();
        submitBtn.click();
      });

      window.__openFormModal = openFormModal;
    })();
    </script>
    <script>
    ColumnsPanel.init({
      btn: document.getElementById('columnsBtn'),
      saveUrl: '<?= h($columnsSaveUrl) ?>',
      tbl: '<?= h($tableKey) ?>',
      closeAllPanels: closeAllPanels,
      initialColumns: <?= $initialColumnsJson ?>,
      defaultColumns: <?= $defaultColumnsJson ?>
    });

    (function () {
      const toolbar = document.querySelector('.toolbar');
      if (!toolbar) return;
      const tbody = document.querySelector('table tbody');
      const currentPage  = parseInt(toolbar.dataset.page  || '1', 10);
      const currentPages = parseInt(toolbar.dataset.pages || '1', 10);

      const openBtn   = document.getElementById('rowOpenBtn');
      const copyBtn   = document.getElementById('rowCopyBtn');
      const deleteBtn = document.getElementById('rowDeleteBtn');
      const tableWrapEl = document.querySelector('.table-wrap');
<?php if (!empty($extraRowSelectBtns)): ?>
      const extraBtns = [<?= implode(',', array_map(function($id) { return "document.getElementById(" . json_encode($id, JSON_UNESCAPED_UNICODE) . ")"; }, $extraRowSelectBtns)) ?>];
<?php endif; ?>

      const rowSel = RowSelect.init({
        tbody: tbody,
        rowClass: 'selected',
        onChange: function (id) {
          const enabled = id > 0;
          if (openBtn)   openBtn.disabled   = !enabled;
          if (copyBtn)   copyBtn.disabled   = !enabled;
          if (deleteBtn) deleteBtn.disabled = !enabled;
<?php if (!empty($extraRowSelectBtns)): ?>
          extraBtns.forEach(function(b) { if (b) b.disabled = !enabled; });
<?php endif; ?>
        },
        currentPage: currentPage,
        totalPages: currentPages,
        navigate: navigate
      });
      window.rowSel = rowSel;

      (function applyInitialFocus() {
        const rows = rowSel.getRows();
        if (rows.length === 0) return;
        const raw = (toolbar.dataset.focus || '').toString();
        if (raw === 'first') { rowSel.selectByIndex(0); return; }
        if (raw === 'last')  { rowSel.selectByIndex(rows.length - 1); return; }
        const id = parseInt(raw, 10);
        if (id > 0 && rowSel.selectById(id, false)) return;
        rowSel.selectByIndex(0);
      })();

      if (tbody) {
        tbody.addEventListener('dblclick', function (e) {
          if (e.target.closest('input[type="checkbox"]')) return;
          const tr = e.target.closest('tr[data-row-id]');
          if (!tr) return;
          const id = parseInt(tr.dataset.rowId, 10) || 0;
          if (!id) return;
          rowSel.selectById(id, true);
          if (typeof window.__openFormModal === 'function') {
            window.__openFormModal('<?= h($formPrefix) ?>.php?mode=edit&id=' + id);
          }
        });
      }

      function openForm(mode) {
        const id = rowSel.getSelectedId();
        if (!id) return;
        if (typeof window.__openFormModal === 'function') {
          window.__openFormModal('<?= h($formPrefix) ?>.php?mode=' + mode + '&id=' + id);
        }
      }
      if (openBtn)   openBtn  .addEventListener('click', function (e) { e.stopPropagation(); openForm('edit'); });
      if (copyBtn)   copyBtn  .addEventListener('click', function (e) { e.stopPropagation(); openForm('copy'); });
      if (deleteBtn) deleteBtn.addEventListener('click', function (e) { e.stopPropagation(); openForm('delete'); });

      function navigate(apply) {
        const p = new URLSearchParams(location.search);
        apply(p);
        location.href = '<?= h($pageUrl) ?>?' + p.toString();
      }

      bindTableKeyboardShortcuts({
        formPrefix: '<?= h($formPrefix) ?>',
        rowSel: rowSel,
        currentPage: currentPage,
        currentPages: currentPages,
        navigate: navigate,
        tableWrapEl: tableWrapEl,
        onOpenForm: window.__openFormModal
      });
    })();

    (function () {
      const tbody = document.querySelector('table tbody');
      if (!tbody) return;

      <?php if ($skipInlineEdit): ?>
      // InlineEdit skipped (custom version in extraCode)
      <?php else: ?>
      InlineEdit.init({
        tbody: tbody,
        saveUrl: '<?= h($fieldSaveUrl) ?>',
        fields: <?= $inlineFieldsJson ?>,
        getLookupData: function (field) {
<?php if (!empty($lookupData)): ?>
          var map = <?= json_encode($lookupData, JSON_UNESCAPED_UNICODE) ?>;
          return map[field] || [];
<?php else: ?>
          return [];
<?php endif; ?>
        },
        validate: function (field, value) {
          switch (field) {
<?= $valCases ?>
          }
          return null;
        },
        onOpenForm: window.__openFormModal
      });
    <?php endif; ?>
    })();

<?php if ($columnResizeUrl): ?>
    ColumnResize.init({ saveUrl: '<?= h($columnResizeUrl) ?>', tbl: '<?= h($tableKey) ?>', selector: 'table.data-table' });
<?php endif; ?>
    </script>
<?php
    if ($extraCode) {
        echo '<script>' . $extraCode . '</script>';
    }
        }

        public function renderFooter(): void {
            ?><script>
(function(){
  var kaTimer = null;
  function ka() {
    fetch('keepalive.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'})
      .then(function(r){return r.json()})
      .then(function(d){if(d&&!d.ok)location.reload();})
      .catch(function(){});
  }
  function rk() { if(kaTimer)clearTimeout(kaTimer); kaTimer=setTimeout(ka,120000); }
  document.addEventListener('mousedown',rk); document.addEventListener('keydown',rk); rk();
  var lb = document.getElementById('logout-btn');
  if(lb) lb.addEventListener('click',function(e){e.preventDefault();fetch('logout_handler.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'}).then(function(){location.reload();});});
})();
</script>
</body>
</html>
<?php
        }
    }

    function str_placeholder($sql, $whereFull) {
        if ($whereFull === '') return $sql;
        $whereBody = preg_replace('/^WHERE\s+/i', '', $whereFull);
        $depth = 0;
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            if ($sql[$i] === '(') { $depth++; continue; }
            if ($sql[$i] === ')') { $depth--; continue; }
            if ($depth === 0 && $i + 7 <= $len && stripos(substr($sql, $i), ' WHERE ') === 0) {
                return substr($sql, 0, $i + 7) . '(' . substr($sql, $i + 7) . ') AND (' . $whereBody . ')';
            }
        }
        return $sql . ' ' . $whereFull;
    }
}
