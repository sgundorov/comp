<?php
chdir(__DIR__ . '/..');
file_put_contents(__DIR__ . '/regcod_log.txt', date('Y-m-d H:i:s') . " Method:" . ($_SERVER['REQUEST_METHOD'] ?? '?') . " POST:" . json_encode($_POST) . "\n", FILE_APPEND);
require 'config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'error'=>'not POST']); exit; }
$mode = (string)($_POST['mode'] ?? 'new');
$id = (int)($_POST['id'] ?? 0);
$product_id = (int)($_POST['product_id'] ?? 0);
file_put_contents(__DIR__ . '/regcod_log.txt', date('Y-m-d H:i:s') . " mode=$mode id=$id product_id=$product_id\n", FILE_APPEND);
echo json_encode(['ok'=>true,'debug'=>['mode'=>$mode,'id'=>$id,'product_id'=>$product_id]]);
