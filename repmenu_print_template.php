<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/template-engine.php';

$templateFile = (string)($_GET['template'] ?? '');
$rpId = (int)($_GET['id'] ?? 0);

if ($rpId <= 0) {
    echo '<p>Не указан ID записи.</p>';
    exit;
}

$stmt = $conn->prepare("SELECT m.number, m.gr_id, m.name, m.fname, m.HIDE_FLAG, m.note, rg.name AS group_name FROM repmenu m LEFT JOIN repgroup rg ON rg.gr_id = m.gr_id WHERE m.rp_id = ?");
$stmt->bind_param('i', $rpId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo '<p>Запись не найдена.</p>';
    exit;
}

$fname = $templateFile !== '' ? $templateFile : (string)($row['fname'] ?? '');
$templatePath = __DIR__ . '/' . ltrim($fname, '/');

if (!file_exists($templatePath)) {
    echo '<p>Файл шаблона не найден: ' . htmlspecialchars($fname) . '</p>';
    exit;
}

$vars = [
    'Number'    => (string)$row['number'],
    'Name'      => (string)$row['name'],
    'GroupName' => (string)$row['group_name'],
    'Note'      => (string)$row['note'],
    'PrintDate' => date('d.m.Y'),
];

$details = [
    'D1' => [],
];

$output = render_template($templatePath, $vars, $details);

echo $output;
