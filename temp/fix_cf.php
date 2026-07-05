<?php
$file = __DIR__ . '/../client_form.php';
$text = file_get_contents($file);
$fixed = mb_convert_encoding($text, 'Windows-1251', 'UTF-8');
file_put_contents($file, $fixed);
$check = file_get_contents($file);
echo "Has Контрагент: " . (strpos($check, 'Контрагент') !== false ? 'YES' : 'NO') . "\n";
echo "Has РљРѕРЅ: " . (strpos($check, "\xD0\x9A") !== false ? 'checking...' : 'NO mojibake') . "\n";
echo "Lines: " . substr_count($check, "\n") . "\n";
