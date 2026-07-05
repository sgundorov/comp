<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$dbConfig = [];
$localFile = __DIR__ . '/config.local.php';
if (file_exists($localFile)) {
    $dbConfig = (array)include $localFile;
}
define('DB_HOST', $dbConfig['DB_HOST'] ?? 'localhost');
define('DB_USER', $dbConfig['DB_USER'] ?? 'root');
define('DB_PASS', $dbConfig['DB_PASS'] ?? '1439');
define('DB_NAME', $dbConfig['DB_NAME'] ?? 'comp');

$isAjax = (
    (string)($_GET['ajax'] ?? '') === '1' ||
    (string)($_POST['ajax'] ?? '') === '1' ||
    (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    if ($isAjax) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok' => false, 'html' => '<div class="flash flash--error">Ошибка подключения к БД</div>']); exit; }
    die('Ошибка подключения к БД: ' . htmlspecialchars(mysqli_connect_error(), ENT_QUOTES, 'UTF-8'));
}
mysqli_set_charset($conn, 'utf8mb4');
mysqli_report(MYSQLI_REPORT_OFF);

ensure_app_settings_table($conn);
ensure_plat_doc_type($conn);
$appSettings = load_app_settings($conn);
define('PAGE_SIZE', max(1, (int)($appSettings['page_size'] ?? 20)));
$GLOBALS['pageWidth'] = max(800, (int)($appSettings['page_width'] ?? 1100));

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function stmt_bind(mysqli_stmt $stmt, string $types, array $params): void {
    if ($types === '') return;
    $refs = [];
    foreach ($params as $k => $v) { $refs[$k] = &$params[$k]; }
    call_user_func_array([$stmt, 'bind_param'], array_merge([$types], $refs));
}

function bind_auto(mysqli_stmt $stmt, array $params): void {
    $types = '';
    foreach ($params as $v) {
        if (is_int($v)) $types .= 'i';
        elseif (is_float($v)) $types .= 'd';
        else $types .= 's';
    }
    $stmt->bind_param($types, ...$params);
}

function hilight(string $text, string $search, string $cond = 'contains'): string {
    if ($search === '') return h($text);
    $safe = h($text);
    $needle = preg_quote($search, '/');
    $pattern = match ($cond) {
        'contains'     => '/' . $needle . '/iu',
        'not_contains' => '/' . $needle . '/iu',
        'starts_with'  => '/^' . $needle . '/iu',
        'ends_with'    => '/' . $needle . '$/iu',
        'equals'       => '/^' . $needle . '$/iu',
        'not_equals'   => '/^' . $needle . '$/iu',
        default        => '/' . $needle . '/iu',
    };
    return preg_replace_callback(
        $pattern,
        function ($m) { return '<span class="hl">' . $m[0] . '</span>'; },
        $safe
    );
}

// --- Employee authentication ---
$r = @$conn->query("SHOW COLUMNS FROM sotr LIKE 'user_status'");
if ($r && $r->num_rows === 0) {
    @$conn->query("ALTER TABLE sotr ADD COLUMN user_status TINYINT NOT NULL DEFAULT 0");
}
$r2 = @$conn->query("SELECT COUNT(*) AS cnt FROM column_visibility WHERE tbl = 'sotr' AND column_name = 'user_status'");
if ($r2 && ($r2->fetch_assoc()['cnt'] ?? 0) == 0) {
    @$conn->query("UPDATE column_visibility SET sort_order = sort_order + 1 WHERE tbl = 'sotr' AND sort_order >= 8");
    @$conn->query("INSERT INTO column_visibility (tbl, column_name, visible, sort_order) VALUES ('sotr', 'user_status', 1, 8)");
}
$r3 = @$conn->query("SHOW COLUMNS FROM docum LIKE 'zakaz_id'");
if ($r3 && $r3->num_rows === 0) {
    @$conn->query("ALTER TABLE docum ADD COLUMN zakaz_id INT NOT NULL DEFAULT 0");
}
$r4 = @$conn->query("SHOW COLUMNS FROM docum LIKE 'zakaz_type'");
if ($r4 && $r4->num_rows === 0) {
    @$conn->query("ALTER TABLE docum ADD COLUMN zakaz_type TINYINT NOT NULL DEFAULT 0");
}

$CurStoreID = 1;
$CurSotrID  = 0;
$inactivityTimeout = 60 * 60; // 60 minutes

if (isset($_SESSION['sotr_id']) && (int)$_SESSION['sotr_id'] > 0) {
    $sid = (int)$_SESSION['sotr_id'];
    if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > $inactivityTimeout) {
        @$conn->query("UPDATE sotr SET user_status = 0 WHERE sotr_id = $sid");
        unset($_SESSION['sotr_id'], $_SESSION['last_activity']);
    } else {
        $CurSotrID = $sid;
    }
}
if ($CurSotrID > 0) {
    $_SESSION['last_activity'] = time();
}

require_once __DIR__ . '/lib/TablePage.php';

function ensure_marks_table(mysqli $conn): void {
    static $done = [];
    $key = $conn->thread_id ?? 0;
    if (!empty($done[$key])) return;
    $done[$key] = true;
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS marks (
        mark_id INT AUTO_INCREMENT PRIMARY KEY,
        tbl VARCHAR(64) NOT NULL,
        row_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_tbl_row (tbl, row_id),
        KEY idx_tbl (tbl)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function load_marks_set(mysqli $conn, string $tbl): array {
    $set = [];
    $stmt = @mysqli_prepare($conn, "SELECT row_id FROM marks WHERE tbl = ?");
    if (!$stmt) return $set;
    $stmt->bind_param('s', $tbl);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) while ($r = $res->fetch_assoc()) $set[(int)$r['row_id']] = true;
    $stmt->close();
    return $set;
}

function count_marks(mysqli $conn, string $tbl): int {
    $stmt = @mysqli_prepare($conn, "SELECT COUNT(*) AS cnt FROM marks WHERE tbl = ?");
    if (!$stmt) return 0;
    $stmt->bind_param('s', $tbl);
    $stmt->execute();
    $res = $stmt->get_result();
    $cnt = 0;
    if ($res) {
        $r = $res->fetch_assoc();
        $cnt = (int)($r['cnt'] ?? 0);
    }
    $stmt->close();
    return $cnt;
}

function ensure_app_settings_table(mysqli $conn): void {
    static $done = [];
    $key = $conn->thread_id ?? 0;
    if (!empty($done[$key])) return;
    $done[$key] = true;
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS app_settings (
        `key` VARCHAR(64) NOT NULL PRIMARY KEY,
        value TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function load_app_settings(mysqli $conn): array {
    ensure_app_settings_table($conn);
    $out = [];
    $rs = @$conn->query("SELECT `key`, value FROM app_settings");
    if ($rs) {
        while ($r = $rs->fetch_assoc()) {
            $out[$r['key']] = $r['value'];
        }
    }
    return $out;
}

function save_app_setting(mysqli $conn, string $key, string $value): bool {
    ensure_app_settings_table($conn);
    $stmt = @$conn->prepare("INSERT INTO app_settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
    if (!$stmt) return false;
    $stmt->bind_param('ss', $key, $value);
    return $stmt->execute();
}

function ensure_columns_config_table(mysqli $conn): void {
    static $done = [];
    $key = $conn->thread_id ?? 0;
    if (!empty($done[$key])) return;
    $done[$key] = true;
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS column_visibility (
        tbl VARCHAR(64) NOT NULL,
        column_name VARCHAR(64) NOT NULL,
        visible TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        width INT DEFAULT NULL,
        PRIMARY KEY (tbl, column_name),
        KEY idx_tbl (tbl)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    @mysqli_query($conn, "ALTER TABLE column_visibility ADD COLUMN width INT DEFAULT NULL AFTER sort_order");
}

function load_columns_config(mysqli $conn, string $tbl, array $defaults): array {
    ensure_columns_config_table($conn);
    $byName = [];
    foreach ($defaults as $i => $col) {
        $byName[$col['name']] = array_merge([
            'visible' => 1,
            'order'   => $i,
        ], $col);
    }
    $stmt = @mysqli_prepare($conn, "SELECT column_name, visible, sort_order, width FROM column_visibility WHERE tbl = ?");
    if ($stmt) {
        $stmt->bind_param('s', $tbl);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $n = $r['column_name'];
                if (isset($byName[$n])) {
                    $byName[$n]['visible'] = (int)$r['visible'] ? 1 : 0;
                    $byName[$n]['order']   = (int)$r['sort_order'];
                    if (isset($r['width']) && $r['width'] !== null) {
                        $byName[$n]['width'] = (int)$r['width'];
                    }
                }
            }
        }
        $stmt->close();
    }
    $out = array_values($byName);
    usort($out, function ($a, $b) {
        if ($a['order'] === $b['order']) return strcmp($a['name'], $b['name']);
        return $a['order'] - $b['order'];
    });
    return $out;
}

function save_columns_config(mysqli $conn, string $tbl, array $columns): bool {
    ensure_columns_config_table($conn);
    $mysqli_begin = function () {};
    if (method_exists($conn, 'begin_transaction')) { $conn->begin_transaction(); }
    else { $conn->query('START TRANSACTION'); }
    try {
        $existingWidths = [];
        $stmt = $conn->prepare("SELECT column_name, width FROM column_visibility WHERE tbl = ? AND width IS NOT NULL");
        if ($stmt) {
            $stmt->bind_param('s', $tbl);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $existingWidths[$r['column_name']] = (int)$r['width'];
                }
            }
            $stmt->close();
        }

        $stmt = $conn->prepare("DELETE FROM column_visibility WHERE tbl = ?");
        if (!$stmt) throw new Exception('prepare delete failed');
        $stmt->bind_param('s', $tbl);
        $stmt->execute();
        $stmt->close();

        $stmtW = $conn->prepare("INSERT INTO column_visibility (tbl, column_name, visible, sort_order, width) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE visible=VALUES(visible), sort_order=VALUES(sort_order), width=VALUES(width)");
        $stmtN = $conn->prepare("INSERT INTO column_visibility (tbl, column_name, visible, sort_order) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE visible=VALUES(visible), sort_order=VALUES(sort_order)");
        $n = ''; $v = 0; $o = 0; $w = 0;
        if ($stmtW) $stmtW->bind_param('ssiii', $tbl, $n, $v, $o, $w);
        if ($stmtN) $stmtN->bind_param('ssii', $tbl, $n, $v, $o);
        foreach ($columns as $c) {
            $n = (string)$c['name'];
            $v = !empty($c['visible']) ? 1 : 0;
            $o = (int)$c['order'];
            if (isset($existingWidths[$n]) && $stmtW) {
                $w = $existingWidths[$n];
                $stmtW->execute();
            } else if ($stmtN) {
                $stmtN->execute();
            }
        }
        if ($stmtW) $stmtW->close();
        if ($stmtN) $stmtN->close();
        if (method_exists($conn, 'commit')) $conn->commit();
        else $conn->query('COMMIT');
        return true;
    } catch (Throwable $e) {
        if (method_exists($conn, 'rollback')) $conn->rollback();
        else $conn->query('ROLLBACK');
        return false;
    }
}

function load_columns_widths(mysqli $conn, string $tbl): array {
    $out = [];
    $stmt = @mysqli_prepare($conn, "SELECT column_name, width FROM column_visibility WHERE tbl = ? AND width IS NOT NULL");
    if ($stmt) {
        $stmt->bind_param('s', $tbl);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $out[$r['column_name']] = (int)$r['width'];
            }
        }
        $stmt->close();
    }
    return $out;
}

function ensure_plat_doc_type(mysqli $conn): void {
    static $done = [];
    $key = $conn->thread_id ?? 0;
    if (!empty($done[$key])) return;
    $done[$key] = true;
    @mysqli_query($conn, "ALTER TABLE plat ADD COLUMN doc_type INT DEFAULT NULL AFTER doc_id");
}

function ensure_client_tag_table(mysqli $conn): void {
    static $done = [];
    $key = $conn->thread_id ?? 0;
    if (!empty($done[$key])) return;
    $done[$key] = true;
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS client_tag (
        client_id INT NOT NULL,
        tag_id INT NOT NULL,
        PRIMARY KEY (client_id, tag_id),
        KEY idx_client (client_id),
        KEY idx_tag (tag_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function save_column_width(mysqli $conn, string $tbl, string $column_name, ?int $width): bool {
    ensure_columns_config_table($conn);
    if ($width !== null && $width < 40) $width = 40;
    $stmt = @mysqli_prepare($conn,
        "INSERT INTO column_visibility (tbl, column_name, visible, sort_order, width)
         VALUES (?, ?, 1, 0, ?)
         ON DUPLICATE KEY UPDATE width = VALUES(width)"
    );
    if (!$stmt) return false;
    $stmt->bind_param('ssi', $tbl, $column_name, $width);
    return $stmt->execute();
}
