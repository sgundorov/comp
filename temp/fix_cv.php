<?php
require 'C:\xa\htdocs\comp\config.php';
$conn->query("INSERT INTO column_visibility (tbl, column_name, visible, sort_order) VALUES ('group', 'pos', 1, 2) ON DUPLICATE KEY UPDATE visible=1, sort_order=2");
echo 'done';
