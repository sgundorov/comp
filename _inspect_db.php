<?php
$db = new PDO('sqlite:C:/Users/0/.local/share/mimocode/mimocode.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Find user messages with rule/decision patterns in last 7 days
$cutoff = (time() - 7*86400) * 1000;
$keywords = ['правило', 'всегда', 'никогда', 'запомни', 'не нужно', 'решили', 'решение', 'важно', 'обязательно', 'rule', 'always', 'never', 'remember', 'must', 'important'];

echo "=== USER MESSAGES WITH RULES/DECISIONS (last 7 days) ===\n";
foreach ($keywords as $kw) {
    $stmt = $db->prepare("
        SELECT m.id, m.session_id, m.time_created, json_extract(m.data, '$.content') as content
        FROM message m
        JOIN session s ON s.id = m.session_id
        WHERE json_extract(m.data, '$.role') = 'user'
          AND s.time_created > ?
          AND s.directory = 'C:\\xa\\htdocs\\comp'
          AND s.title NOT LIKE 'checkpoint-writer:%'
          AND LOWER(json_extract(m.data, '$.content')) LIKE ?
        ORDER BY m.time_created DESC
        LIMIT 5
    ");
    $stmt->execute([$cutoff, '%' . $kw . '%']);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $date = date('Y-m-d H:i', intval($row['time_created']) / 1000);
        $content = substr($row['content'], 0, 300);
        if (strlen($content) > 10) {
            echo "  [$kw] {$row['session_id']} | $date | $content\n---\n";
        }
    }
}
