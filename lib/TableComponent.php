<?php
if (!defined('TABLECOMPONENT_LOADED')) {
define('TABLECOMPONENT_LOADED', true);

class TableComponent {
    public $columns = [];
    public $colMeta = [];
    public $columnsConfig = [];
    public $visibleColumns = [];
    public $columnWidths = [];
    public $defaultColumnWidths = [];
    public $lookupData = [];
    public $columnVisibilityTbl = '';

    public function __construct(array $config) {
        $this->columns = $config['columns'] ?? [];
        foreach ($this->columns as $c) {
            $n = $c['name'] ?? $c['key'] ?? '';
            if ($n === '') continue;
            $this->colMeta[$n] = $c;
        }
        $this->columnVisibilityTbl = $config['column_visibility_tbl'] ?? '';
        if (!empty($config['lookupData'])) $this->lookupData = $config['lookupData'];
        if (!empty($config['defaultColumnWidths'])) $this->defaultColumnWidths = $config['defaultColumnWidths'];
    }

    public function loadColumnWidths(mysqli $conn): void {
        if ($this->columnVisibilityTbl) {
            $this->columnWidths = array_merge(
                $this->columnWidths,
                load_columns_widths($conn, $this->columnVisibilityTbl)
            );
        }
    }

    public function loadColumnsConfig(mysqli $conn): void {
        $defaults = array_values(array_map(function ($c) {
            $out = $c;
            if (!isset($out['name']) && isset($out['key'])) $out['name'] = $out['key'];
            return $out;
        }, $this->columns));
        if ($this->columnVisibilityTbl) {
            $this->columnsConfig = load_columns_config($conn, $this->columnVisibilityTbl, $defaults);
            $this->visibleColumns = array_values(array_filter($this->columnsConfig, function ($c) { return !empty($c['visible']); }));
        } else {
            $this->columnsConfig = $defaults;
            $this->visibleColumns = $defaults;
        }
    }

    protected function buildInlineFields(): array {
        $fields = [];
        foreach ($this->visibleColumns as $vc) {
            $cn = $vc['name'] ?? $vc['key'] ?? '';
            if ($cn === '') continue;
            if (!empty($vc['readonly']) || $cn === 'id') continue;
            $isLookup = !empty($vc['param']);
            $fields[$cn] = [
                'dbField' => $isLookup ? $vc['param'] : $cn,
                'type'    => $isLookup ? 'lookup' : 'text',
                'label'   => $vc['label'],
            ];
        }
        return $fields;
    }

    protected function buildInlineValCases(): string {
        $cases = '';
        foreach ($this->visibleColumns as $vc) {
            $cn = $vc['name'] ?? $vc['key'] ?? '';
            if ($cn === '') continue;
            if (!empty($vc['readonly']) || $cn === 'id') continue;
            if (!empty($vc['param'])) {
                $cnEnc = json_encode($cn, JSON_UNESCAPED_UNICODE);
                $cases .= "    case $cnEnc: if (parseInt(value,10)<=0) return 'Выберите значение из списка'; break;\n";
            }
        }
        return $cases;
    }

    public function getVisibleColumns(): array {
        return $this->visibleColumns ?: $this->columns;
    }

    public function getColumnMeta(): array {
        return $this->colMeta;
    }

    protected function renderColgroup(bool $hasCheckbox = true, ?array $columns = null): void {
        $cols = $columns ?? $this->visibleColumns;
        ?><colgroup>
    <col class="col-check" style="width: 32px;" />
    <?php foreach ($cols as $vc):
        $cn = $vc['name'] ?? $vc['key'] ?? '';
        if ($cn === '') continue;
        $savedW = $this->columnWidths[$cn] ?? null;
        $dflt = $this->defaultColumnWidths[$cn] ?? '150px';
        $w = $savedW !== null ? $savedW . 'px' : (is_numeric($dflt) ? $dflt . 'px' : $dflt);
    ?>
      <col class="col-<?= h($cn) ?>" style="width: <?= $w ?>;" />
    <?php endforeach; ?>
  </colgroup><?php
    }
}

} // endif TABLECOMPONENT_LOADED
