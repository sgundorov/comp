<?php
$content = file_get_contents(__DIR__ . '/../client_form.php');
preg_match_all('#<script>(.*?)</script>#s', $content, $m);
foreach ($m[1] as $i => $script) {
    $parens = substr_count($script, '(') - substr_count($script, ')');
    $braces = substr_count($script, '{') - substr_count($script, '}');
    $first = trim(substr($script, 0, 60));
    echo "Script $i: parens=$parens braces=$braces len=" . strlen($script) . " start: $first\n";
}
