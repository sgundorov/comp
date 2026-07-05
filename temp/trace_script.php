<?php
$content = file_get_contents(__DIR__ . '/../client_form.php');
preg_match_all('#<script>(.*?)</script>#s', $content, $m);
$script = $m[1][2];
$lines = explode("\n", $script);
$pd = 0; $bd = 0;
foreach ($lines as $i => $line) {
    $pd += substr_count($line, '(') - substr_count($line, ')');
    $bd += substr_count($line, '{') - substr_count($line, '}');
    if ($pd < 0 || $bd < 0) {
        echo "Line " . ($i+1) . " pd=$pd bd=$bd: " . trim(substr($line, 0, 80)) . "\n";
    }
}
echo "Final: pd=$pd bd=$bd\n";
