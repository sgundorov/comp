<?php
require 'C:\xa\htdocs\comp\config.php';
$rs = $conn->query('SHOW CREATE TABLE sgroup');
$r = $rs->fetch_assoc();
echo $r['Create Table'] . "\n\n";
$rs2 = $conn->query('SHOW CREATE TABLE `group`');
$r2 = $rs2->fetch_assoc();
echo $r2['Create Table'];
