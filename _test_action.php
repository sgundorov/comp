<?php
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['ajax' => '1', 'mode' => 'new', 'kind' => 'offer'];
$_POST = [];
$_SESSION = ['invoice_kind' => 'offer'];

ob_start();
require __DIR__ . '/invoice_form.php';
$out = ob_get_clean();

if (preg_match('/action="([^"]+)"/', $out, $m)) {
    echo "Form action: " . $m[1] . "\n";
}
if (preg_match('/name="kind"\s+value="([^"]*)"/', $out, $m)) {
    echo "Hidden kind: " . $m[1] . "\n";
}
echo "JSON: " . (preg_match('/^\{/', $out) ? 'yes' : 'no') . "\n";
