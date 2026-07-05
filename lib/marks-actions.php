<?php
if (defined('MARKS_ACTIONS_LOADED')) return;
define('MARKS_ACTIONS_LOADED', true);

/**
 * Обрабатывает POST/GET-запросы управления отметками (marks) для таблицы.
 * Вызывается в начале файла страницы таблицы после require конфигов и создания TablePage.
 *
 * Пример:
 *   require_once __DIR__ . '/lib/marks-actions.php';
 *   handle_marks_actions($conn, $tp, $TBL);
 *
 * @param mysqli    $conn  Соединение с БД
 * @param TablePage $tp    Объект TablePage (уже инициализирован)
 * @param string    $tbl   Имя таблицы для marks (например 'city', 'client')
 * @param callable  $extraActions  Опциональный callable(array $action): bool|null
 *                                 Вернуть true если обработано, null если нет — тогда выдаётся 404
 */
function handle_marks_actions(
    mysqli    $conn,
    TablePage $tp,
    string    $tbl,
    ?callable $extraActions = null
): void {
    $action = (string)($_GET['action'] ?? '');

    switch ($action) {
        case 'toggleSelect':
            handle_toggle_select($conn, $tbl);
            break;
        case 'invertSelection':
            handle_invert_selection($conn, $tp, $tbl);
            break;
        case 'clearSelection':
            handle_clear_selection($conn, $tbl);
            break;
        case 'toggleShowOnly':
            handle_toggle_show_only($tp, $tbl);
            break;
        case 'toggleSelectAll':
            handle_toggle_select_all($conn, $tp, $tbl);
            break;
        default:
            if ($extraActions !== null) {
                $handled = $extraActions($action);
                if ($handled === true) return;
            }
            // not a marks action — do nothing, let page continue
            return;
    }
    exit;
}

function handle_toggle_select(mysqli $conn, string $tbl): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('HTTP/1.0 405 Method Not Allowed');
        echo json_encode(['ok' => false, 'error' => 'POST required']);
        exit;
    }
    $id = (int)($_POST['id'] ?? 0);
    $to = ((string)($_POST['to'] ?? '0')) === '1';
    if ($id > 0) {
        if ($to) {
            $stmt = @mysqli_prepare($conn, "INSERT IGNORE INTO marks (tbl, row_id) VALUES (?, ?)");
            if ($stmt) { stmt_bind($stmt, 'si', [$tbl, $id]); $stmt->execute(); $stmt->close(); }
        } else {
            $stmt = @mysqli_prepare($conn, "DELETE FROM marks WHERE tbl = ? AND row_id = ?");
            if ($stmt) { stmt_bind($stmt, 'si', [$tbl, $id]); $stmt->execute(); $stmt->close(); }
        }
    }
    $newCount = count_marks($conn, $tbl);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'count' => $newCount], JSON_UNESCAPED_UNICODE);
    exit;
}

function handle_invert_selection(mysqli $conn, TablePage $tp, string $tbl): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('HTTP/1.0 405 Method Not Allowed');
        echo json_encode(['ok' => false, 'error' => 'POST required']);
        exit;
    }
    $filteredIds = $tp->getFilteredIds($conn);
    $marks = load_marks_set($conn, $tbl);
    $conn->begin_transaction();
    try {
        foreach ($filteredIds as $id) {
            if (isset($marks[$id])) {
                $stmt = $conn->prepare("DELETE FROM marks WHERE tbl = ? AND row_id = ?");
                stmt_bind($stmt, 'si', [$tbl, $id]);
            } else {
                $stmt = $conn->prepare("INSERT IGNORE INTO marks (tbl, row_id) VALUES (?, ?)");
                stmt_bind($stmt, 'si', [$tbl, $id]);
            }
            $stmt->execute();
            $stmt->close();
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log("$tbl invertSelection: " . $e->getMessage());
    }
    $newCount = count_marks($conn, $tbl);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'count' => $newCount], JSON_UNESCAPED_UNICODE);
    exit;
}

function handle_clear_selection(mysqli $conn, string $tbl): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('HTTP/1.0 405 Method Not Allowed');
        echo json_encode(['ok' => false, 'error' => 'POST required']);
        exit;
    }
    $stmt = @mysqli_prepare($conn, "DELETE FROM marks WHERE tbl = ?");
    if ($stmt) { stmt_bind($stmt, 's', [$tbl]); $stmt->execute(); $stmt->close(); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'count' => 0], JSON_UNESCAPED_UNICODE);
    exit;
}

function handle_toggle_show_only(TablePage $tp, string $tbl): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('HTTP/1.0 405 Method Not Allowed');
        echo json_encode(['ok' => false, 'error' => 'POST required']);
        exit;
    }
    $sessionKey = $tp->marksSessionKey ?? ($tbl . '_select');
    $newVal = !$tp->showOnly;
    $_SESSION[$sessionKey]['show_only'] = $newVal;
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'show_only' => $newVal], JSON_UNESCAPED_UNICODE);
    exit;
}

function handle_toggle_select_all(mysqli $conn, TablePage $tp, string $tbl): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        header('HTTP/1.0 405 Method Not Allowed');
        echo json_encode(['ok' => false, 'error' => 'GET required']);
        exit;
    }
    $filteredIds = $tp->getFilteredIds($conn);
    $totalInFilter = count($filteredIds);
    $markedInFilter = 0;
    if ($totalInFilter > 0) {
        $place = implode(',', array_fill(0, $totalInFilter, '?'));
        $cSql = "SELECT COUNT(*) AS cnt FROM marks WHERE tbl = ? AND row_id IN ($place)";
        $cStmt = @$conn->prepare($cSql);
        if ($cStmt) {
            $cParams = array_merge([$tbl], $filteredIds);
            $cTypes = 's' . str_repeat('i', $totalInFilter);
            stmt_bind($cStmt, $cTypes, $cParams);
            $cStmt->execute();
            $cr = $cStmt->get_result()->fetch_assoc();
            $markedInFilter = (int)($cr['cnt'] ?? 0);
            $cStmt->close();
        }
    }
    $conn->begin_transaction();
    try {
        if ($totalInFilter > 0) {
            $place = implode(',', array_fill(0, $totalInFilter, '?'));
            $params = array_merge([$tbl], $filteredIds);
            $types  = 's' . str_repeat('i', $totalInFilter);
            if ($markedInFilter === $totalInFilter) {
                $stmt = $conn->prepare("DELETE FROM marks WHERE tbl = ? AND row_id IN ($place)");
            } else {
                $stmt = $conn->prepare("INSERT IGNORE INTO marks (tbl, row_id) SELECT ?, `{$tp->key}` FROM `{$tp->table}` WHERE `{$tp->key}` IN ($place)");
            }
            stmt_bind($stmt, $types, $params);
            $stmt->execute();
            $stmt->close();
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log("$tbl toggleSelectAll: " . $e->getMessage());
    }
    // Redirect back to the page preserving query params
    $preserve = $_GET;
    unset($preserve['action']);
    $qs = http_build_query($preserve);
    $sep = strpos($tp->baseUrl, '?') === false ? '?' : '&';
    header('Location: ' . $tp->baseUrl . ($qs !== '' ? $sep . $qs : ''));
    exit;
}
