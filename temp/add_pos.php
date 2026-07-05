<?php
require 'C:\xa\htdocs\comp\config.php';
$conn->query("ALTER TABLE `group` ADD COLUMN pos INT NOT NULL DEFAULT 0 AFTER note");
echo 'done';
