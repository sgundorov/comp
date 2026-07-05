<?php
/**
 * Вычисляет номер страницы, на которой окажется новая запись
 * после применения сортировки. Используется для перехода к
 * выделенной записи после создания/копирования.
 *
 * @param mysqli $conn  Объект соединения с БД
 * @param string $table Имя таблицы (без обратных кавычек)
 * @param string $pkCol Имя первичного ключа (без кавычек)
 * @param string $sortCol Имя колонки сортировки
 * @param string $sortDir Направление: 'ASC' или 'DESC'
 * @param int|string $sortVal Значение колонки сортировки новой записи
 * @param int    $newPk  Значение первичного ключа новой записи
 * @param string $where  Доп. условие фильтрации (без WHERE)
 *                       Например: "doctype_id = 10" или ""
 *
 * @return int Номер страницы (1-based) или 1 если не удалось вычислить
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

    $table = '`' . str_replace('`', '``', $table) . '`';
    $pkCol = '`' . str_replace('`', '``', $pkCol) . '`';
    $sortCol = '`' . str_replace('`', '``', $sortCol) . '`';
    $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

    $cmpOp  = $sortDir === 'DESC' ? '>' : '<';
    $pkCmp  = $sortDir === 'DESC' ? '>' : '<';

    if (is_numeric($sortVal)) {
        $sortValLit = (string)(int)$sortVal;
        $condition = "$sortCol $cmpOp $sortValLit OR ($sortCol = $sortValLit AND $pkCol $pkCmp $newPk)";
    } else {
        $escaped = "'" . $conn->real_escape_string((string)$sortVal) . "'";
        $condition = "$sortCol $cmpOp $escaped OR ($sortCol = $escaped AND $pkCol $pkCmp $newPk)";
    }

    $whereClause = '';
    if ($where !== '') {
        $trimmed = trim($where);
        $upper = strtoupper(substr($trimmed, 0, 6));
        if ($upper === 'WHERE ') {
            $whereClause = ' ' . $trimmed . ' AND (' . $condition . ')';
        } else {
            $whereClause = ' WHERE ' . $trimmed . ' AND (' . $condition . ')';
        }
    } else {
        $whereClause = ' WHERE (' . $condition . ')';
    }

    $sql = "SELECT COUNT(*) + 1 AS pos FROM $table$whereClause";
    $res = @$conn->query($sql);
    if ($res && ($row = $res->fetch_assoc())) {
        $pos = (int)$row['pos'];
        if ($pos > 0) {
            return (int)ceil($pos / $pageSize);
        }
    }
    return 1;
}
