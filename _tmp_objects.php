<?php
require __DIR__ . '/config.php';
$r = $conn->query('SELECT object_id, object, name, type FROM object ORDER BY object_id');
while ($row = $r->fetch_assoc()) {
    echo $row['object'] . ' | ' . $row['name'] . ' | ' . $row['type'] . "\n";
}
