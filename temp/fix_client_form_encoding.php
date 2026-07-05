<?php
$file = __DIR__ . '/../client_form.php';
$bytes = file_get_contents($file);
$text = mb_convert_encoding($bytes, 'UTF-8', 'UTF-8');
// Reverse double encoding: UTF-8 bytes were treated as CP1251
$fixed = mb_convert_encoding($text, 'UTF-8', 'CP1251');
file_put_contents($file . '.bak2', $bytes);
file_put_contents($file, $fixed);
$check = file_get_contents($file);
echo "Has Контрагент: " . (strpos($check, 'Контрагент') !== false ? 'YES' : 'NO') . "\n";
echo "Has РљРѕРЅ: " . (strpos($check, 'РљРѕРЅ') !== false ? 'YES' : 'NO') . "\n";
echo "Has Добавить: " . (strpos($check, 'Добавить') !== false ? 'YES' : 'NO') . "\n";
