<?php
if (!defined('TABLEPAGE_LOADED')) {
    define('TABLEPAGE_LOADED', true);

    class TablePage {
        public $table;
        public $key;
        public $columns = [];
        public $colMeta = [];
        public $searchColExprs = [];
        public $defaultSearchCols = [];
        public $defaultSort = ['col' => 'id', 'dir' => 'asc'];
        public $marksSessionKey;
        public $marksTbl;
        public $columnVisibilityTbl;
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
        public $columnsConfig = [];
        public $visibleColumns = [];
        public $offset = 0;
        public $showOnly = false;
        public $marks = [];
        public $marksCount = 0;
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

        public function __construct(mysqli $conn, array $config) {
            $this->table           = $config['table'];
            $this->key             = $config['key'];
            $this->columns         = $config['columns'] ?? [];
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

            foreach ($this->columns as $c) $this->colMeta[$c['name']] = $c;

            $this->parseRequest();
            $this->parseSort();
            $this->loadColumnsConfig($conn);
            $this->loadMarks($conn);
            $this->loadSession();
            if ($this->showOnly && $this->marksCount === 0) {
                $this->showOnly = false;
                $_SESSION[$this->marksSessionKey]['show_only'] = false;
            }
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

        protected function loadColumnsConfig(mysqli $conn) {
            $this->columnsConfig  = load_columns_config($conn, $this->columnVisibilityTbl, $this->columns);
            $this->visibleColumns = array_values(array_filter($this->columnsConfig, function ($c) { return !empty($c['visible']); }));
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
        }

        public function appendWhere($extra, array $addParams, string $addTypes) {
            $this->params = array_merge($this->params, $addParams);
            $this->types  = $this->types . $addTypes;
            $this->where  = $this->where === '' ? $extra : $this->where . ' AND ' . $extra;
        }

        public function appendWhereRaw($extra) {
            $this->where = $this->where === '' ? $extra : $this->where . ' AND ' . $extra;
        }

        public function whereSql() {
            return $this->where === '' ? '' : 'WHERE ' . $this->where;
        }

        public function buildFilters() {
            $this->filters = [];
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
            if (count($this->countryIds) > 0) {
                $names = [];
                foreach ($this->countryIds as $cid) {
                    $names[] = $this->countryNames[$cid] ?? ('#' . $cid);
                }
                $this->filters[] = ['kind' => 'country', 'text' => 'Страна = ' . implode(', ', $names), 'clear' => $this->countryFilterField];
            }
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
            return $s !== '' ? $this->baseUrl . '?' . $s : $this->baseUrl;
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
            foreach ($extraParams as $k => $v) {
                $params[$k] = ($v !== null && $v !== '') ? $v : null;
            }
            return http_build_query(array_filter($params, function ($v) { return $v !== null && $v !== ''; }));
        }

        public function colFilterOptions(mysqli $conn, string $col) {
            $cfg = $this->colFilters[$col] ?? null;
            if (!$cfg) return [];
            [$colExpr, $colTable, $idCol, $labelExpr] = $cfg + [null, null, null, null];
            if (!$colTable || !$idCol || !$labelExpr) return [];
            $sql = "SELECT $idCol AS id, $labelExpr AS name FROM $colTable ORDER BY $labelExpr";
            $out = [];
            $res = @$conn->query($sql);
            if ($res) while ($r = $res->fetch_assoc()) {
                $out[] = ['id' => (int)$r['id'], 'name' => (string)$r['name']];
            }
            return $out;
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
