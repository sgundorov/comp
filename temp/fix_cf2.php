<?php
$file = __DIR__ . '/../client_form.php';
$text = file_get_contents($file);
$old = mb_convert_encoding('??????? ? Excel', 'UTF-8', 'UTF-8');
$text = str_replace($old, 'Контрагент', $text);
// Also try replacing raw FFFD bytes
$text = str_replace("\xEF\xBF\xBD\xEF\xBF\xBD\xEF\xBF\xBD\xEF\xBF\xBD\xEF\xBF\xBD\xEF\xBF\xBD\xEF\xBF\xBD \xEF\xBF\xBD Excel", 'Контрагент', $text);
file_put_contents($file, $text);
echo "Done\n";
$check = file_get_contents($file);
echo "Has Контрагент: " . (substr_count($check, 'Контрагент') > 0 ? 'YES' : 'NO') . "\n";
echo "Has FFFD: " . (strpos($check, "\xEF\xBF\xBD") !== false ? 'YES' : 'NO') . "\n";
