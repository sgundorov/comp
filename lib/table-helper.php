<?php
/**
 * Вычисляет номер страницы, на которой окажется новая запись
 * после применения сортировки.
 */
function computePageOfNew(
    mysqli $conn,
    string $table,
    string $pkCol,
    string $sortCol,
    string $sortDir,
    $sortVal,
    int $newPk,
    string $where = ''
): int {
    $pageSize = defined('PAGE_SIZE') && PAGE_SIZE > 0 ? PAGE_SIZE : 30;

    $rawSortCol = trim($sortCol);
    $table = '`' . str_replace('`', '``', $table) . '`';
    $pkCol = '`' . str_replace('`', '``', $pkCol) . '`';
    $sortCol = '`' . str_replace('`', '``', $sortCol) . '`';
    $sortDir = strtoupper($sortDir);

    // Получаем общее количество записей
    $whereClause = '';
    if ($where !== '') {
        $trimmed = trim($where);
        $upper = strtoupper(substr($trimmed, 0, 6));
        if ($upper === 'WHERE ') {
            $whereClause = ' ' . $trimmed;
        } else {
            $whereClause = ' WHERE ' . $trimmed;
        }
    }
    
    $totalSql = "SELECT COUNT(*) AS total FROM $table$whereClause";
    $totalRes = $conn->query($totalSql);
    if (!$totalRes || !($totalRow = $totalRes->fetch_assoc())) {
        return 1;
    }
    $totalRecords = (int)$totalRow['total'];
    $totalPages = ceil($totalRecords / $pageSize);

    // Для сортировки по ID:
    if ($rawSortCol === 'id' || $rawSortCol === $pkCol || $rawSortCol === str_replace('`', '', $pkCol)) {
        if ($sortDir === 'ASC') {
            // Новые записи с большим ID → в конце → последняя страница
            return $totalPages;
        } else {
            // Новые записи с большим ID → в начале → первая страница
            return 1;
        }
    }

    // Для других полей считаем позицию
    if (is_numeric($sortVal)) {
        $sortValLit = (string)(int)$sortVal;
        if ($sortDir === 'DESC') {
            $condition = "$sortCol > $sortValLit OR ($sortCol = $sortValLit AND $pkCol < $newPk)";
        } else {
            $condition = "$sortCol < $sortValLit OR ($sortCol = $sortValLit AND $pkCol < $newPk)";
        }
    } else {
        $escaped = "'" . $conn->real_escape_string((string)$sortVal) . "'";
        if ($sortDir === 'DESC') {
            $condition = "$sortCol > $escaped OR ($sortCol = $escaped AND $pkCol < $newPk)";
        } else {
            $condition = "$sortCol < $escaped OR ($sortCol = $escaped AND $pkCol < $newPk)";
        }
    }

    $countSql = "SELECT COUNT(*) AS pos FROM $table$whereClause AND ($condition)";
    $countRes = $conn->query($countSql);
    if ($countRes && ($countRow = $countRes->fetch_assoc())) {
        $pos = (int)$countRow['pos'];
        return (int)ceil(($pos + 1) / $pageSize);
    }

    return 1;
}

/**
 * Получает текущую сортировку из URL-параметров.
 */
function get_current_sort(array $defaultSort): array {
    $sortParam = $_GET['sort'] ?? '[]';
    
    // JSON формат
    $sortLevels = json_decode($sortParam, true);
    if (is_array($sortLevels) && count($sortLevels) > 0 && isset($sortLevels[0]['col'])) {
        return [
            'col' => (string)$sortLevels[0]['col'],
            'dir' => strtoupper((string)($sortLevels[0]['dir'] ?? 'asc')),
        ];
    }
    
    // Простой формат col:dir
    if (strpos($sortParam, ':') !== false) {
        $parts = explode(':', $sortParam);
        if (count($parts) === 2) {
            return [
                'col' => trim($parts[0]),
                'dir' => strtoupper(trim($parts[1])),
            ];
        }
    }
    
    return $defaultSort;
}
