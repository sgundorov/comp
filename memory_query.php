<?php
$db = new PDO('sqlite:C:/Users/0/.local/share/mimocode/mimocode.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get assistant text summaries from "Переключатель товаров и услуг" session
$sid = 'ses_08e626e77ffeaFe7fojSt9HsSV';
echo "=== SESSION: Переключатель товаров и услуг - TEXT PARTS ===\n";
$stmt = $db->prepare("SELECT m.time_created, substr(json_extract(p.data, '$.text'), 1, 500) as text
    FROM part p 
    JOIN message m ON p.message_id = m.id 
    WHERE m.session_id = :sid 
    AND json_extract(p.data, '$.type') = 'text'
    AND json_extract(m.data, '$.role') = 'assistant'
    ORDER BY m.time_created, p.time_created");
$stmt->execute([':sid' => $sid]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!empty(trim($row['text']))) {
        echo "\n[" . date('H:i:s', $row['time_created'] / 1000) . "] " . $row['text'] . "\n";
    }
}

// Get assistant text summaries from "Видимость контекста" 
$sid2 = 'ses_0c2078c9dffeS59OuEpI2RwKU2';
echo "\n\n=== SESSION: Видимость контекста - TEXT PARTS ===\n";
$stmt2 = $db->prepare("SELECT m.time_created, substr(json_extract(p.data, '$.text'), 1, 500) as text
    FROM part p 
    JOIN message m ON p.message_id = m.id 
    WHERE m.session_id = :sid 
    AND json_extract(p.data, '$.type') = 'text'
    AND json_extract(m.data, '$.role') = 'assistant'
    ORDER BY m.time_created, p.time_created");
$stmt2->execute([':sid' => $sid2]);
while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    if (!empty(trim($row['text']))) {
        echo "\n[" . date('H:i:s', $row['time_created'] / 1000) . "] " . $row['text'] . "\n";
    }
}

// Get user messages with actual text content from those sessions
$sids = [$sid, $sid2];
foreach ($sids as $s) {
    echo "\n\n=== SESSION: $s - USER TEXT ===\n";
    $stmt3 = $db->prepare("SELECT m.time_created, substr(json_extract(m.data, '$.content'), 1, 500) as content
        FROM message m 
        WHERE m.session_id = :sid 
        AND json_extract(m.data, '$.role') = 'user'
        AND json_extract(m.data, '$.content') IS NOT NULL
        AND json_extract(m.data, '$.content') != ''
        ORDER BY m.time_created ASC");
    $stmt3->execute([':sid' => $s]);
    $found = false;
    while ($row = $stmt3->fetch(PDO::FETCH_ASSOC)) {
        $found = true;
        echo "[" . date('H:i:s', $row['time_created'] / 1000) . "] " . $row['content'] . "\n";
    }
    if (!$found) echo "(no user text content found)\n";
}

// Check task summaries
foreach ($sids as $s) {
    echo "\n=== TASKS for $s ===\n";
    $stmt4 = $db->prepare("SELECT id, status, summary, created_at, ended_at FROM task WHERE session_id = :sid");
    $stmt4->execute([':sid' => $s]);
    while ($row = $stmt4->fetch(PDO::FETCH_ASSOC)) {
        echo $row['status'] . ': ' . $row['summary'] . " (". date('Y-m-d H:i:s', $row['created_at']/1000) . ")\n";
    }
    // task events
    $stmt5 = $db->prepare("SELECT te.kind, te.summary, te.at FROM task_event te WHERE te.session_id = :sid ORDER BY te.at DESC LIMIT 5");
    $stmt5->execute([':sid' => $s]);
    while ($row = $stmt5->fetch(PDO::FETCH_ASSOC)) {
        echo '  event: ' . $row['kind'] . ' - ' . $row['summary'] . "\n";
    }
}
