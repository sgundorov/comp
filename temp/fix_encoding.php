<?php
$file = __DIR__ . '/../client_form.php';
$bytes = file_get_contents($file);
// File is UTF-8 but Cyrillic was double-encoded (UTF-8→Win1251→UTF-8)
// Decode as UTF-8, then re-interpret the chars as raw bytes, decode as UTF-8
$text = mb_convert_encoding($bytes, 'UTF-8', 'UTF-8');
// Double-encoded: each Cyrillic char's UTF-8 bytes were treated as CP1251 chars
$fixed = mb_convert_encoding($text, 'UTF-8', 'CP1251');
file_put_contents($file . '.bak', $bytes);
file_put_contents($file, $fixed);
echo "Done. Checking...\n";
$check = file_get_contents($file);
echo "Has Контрагент: " . (strpos($check, 'Контрагент') !== false ? 'YES' : 'NO') . "\n";
echo "Has РљРѕРЅ: " . (strpos($check, 'РљРѕРЅ') !== false ? 'YES (still corrupted)' : 'NO (fixed)') . "\n";
