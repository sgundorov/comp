<?php
$text = file_get_contents(__DIR__ . '/lib/controls.php');

if (strpos($text, 'Поиск') !== false) {
    echo "FOUND 'Поиск' in controls.php\n";
} else {
    echo "'Поиск' NOT FOUND\n";
}

$pos = strpos($text, 'placeholder');
if ($pos !== false) {
    $start = max(0, $pos - 20);
    $end = min(strlen($text), $pos + 60);
    echo "Context: " . substr($text, $start, $end - $start) . "\n";
}

$lines = explode("\n", $text);
$count = 0;
foreach ($lines as $ln => $line) {
    if (preg_match('/[^\x00-\x7F]/', $line)) {
        $chars = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);
        $hasCyr = false;
        $hasGarbled = false;
        foreach ($chars as $ch) {
            $code = mb_ord($ch);
            if ($code >= 0x0400 && $code <= 0x04FF) $hasCyr = true;
            elseif ($code === 0x2014 || $code === 0x00AB || $code === 0x00BB) { /* OK */ }
            elseif ($code > 0x7F && $code < 0x0400) $hasGarbled = true;
        }
        if ($hasCyr && $hasGarbled) {
            $count++;
            if ($count <= 5) echo "GARBLED Line " . ($ln+1) . ": " . mb_substr(trim($line), 0, 100) . "\n";
        }
    }
}
echo "Garbled lines: $count\n";

// Also check groupserv_form.php and group_form.php
foreach (['groupserv_form.php', 'group_form.php'] as $f) {
    $text2 = file_get_contents(__DIR__ . '/' . $f);
    $lines2 = explode("\n", $text2);
    $gcount = 0;
    foreach ($lines2 as $ln => $line) {
        if (preg_match('/[^\x00-\x7F]/', $line)) {
            $chars = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);
            $hasCyr = false;
            $hasGarbled = false;
            foreach ($chars as $ch) {
                $code = mb_ord($ch);
                if ($code >= 0x0400 && $code <= 0x04FF) $hasCyr = true;
                elseif ($code === 0x2014 || $code === 0x00AB || $code === 0x00BB) { /* OK */ }
                elseif ($code > 0x7F && $code < 0x0400) $hasGarbled = true;
            }
            if ($hasCyr && $hasGarbled) {
                $gcount++;
                if ($gcount <= 3) echo "$f Line " . ($ln+1) . ": " . mb_substr(trim($line), 0, 100) . "\n";
            }
        }
    }
    echo "$f: $gcount garbled lines\n";
}

echo "\n=== All files check ===\n";
$dir = __DIR__;
$rdi = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$clean = true;
foreach ($rdi as $f) {
    if ($f->getExtension() !== 'php') continue;
    $b = file_get_contents($f->getPathname());
    $name = $f->getFilename();
    if (strpos($b, "\xEF\xBF\xBD") !== false) { echo "$name: HAS FFFD\n"; $clean = false; }
    if (strncmp($b, "\xD0\xBF\xC2\xBB\xD1\x97", 6) === 0) { echo "$name: HAS PREFIX\n"; $clean = false; }
    if (strlen($b) >= 3 && ord($b[0]) === 0xEF && ord($b[1]) === 0xBB && ord($b[2]) === 0xBF) { echo "$name: HAS BOM\n"; $clean = false; }
}
if ($clean) echo "ALL CLEAN\n";
