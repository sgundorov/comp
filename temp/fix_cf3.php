<?php
$file = __DIR__ . '/../client_form.php';
$t = file_get_contents($file);

// Fix line 360: pageTitle default
$t = str_replace("??\xC3\x90??? ? Excel", 'Контрагент', $t);
// Try another pattern for FFFD
$lines = explode("\n", $t);
foreach ($lines as &$line) {
    // Replace any line with FFFD characters that has Excel
    if (strpos($line, 'Excel') !== false && preg_match('/\xEF\xBF\xBD/', $line)) {
        $line = preg_replace("/[^\\n]*Excel[^\\n]*/", "Контрагент", $line);
    }
    // Replace form labels with FFFD
    $line = str_replace("\xEF\xBF\xBD\xEF\xBF\xBD\xEF\xBF\xBD", '', $line);
}
$t = implode("\n", $lines);

// Specific replacements for remaining FFFD strings
$t = str_replace('???????? ??? ????????????', 'Выберите вид деятельности', $t);
$t = str_replace('?????????', 'Применить', $t);

file_put_contents($file, $t);
echo "Done. FFFD count: " . substr_count(file_get_contents($file), "\xEF\xBF\xBD") . "\n";
