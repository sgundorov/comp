<?php
require __DIR__ . '/../config.php';
$conn->query("ALTER TABLE docum2 ALTER COLUMN guarant_unit SET DEFAULT ''");
echo 'done';
