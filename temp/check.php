<?php
$t = file_get_contents(__DIR__ . '/../client_form.php');
echo 'FFFD:' . substr_count($t, "\xEF\xBF\xBD") . PHP_EOL;
echo 'Lines:' . substr_count($t, "\n") . PHP_EOL;
