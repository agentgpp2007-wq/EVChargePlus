<?php
require __DIR__ . '/config/bootstrap.php';
header('Content-Type: application/json');
try { db()->query('SELECT 1'); echo json_encode(['ok'=>true,'database'=>'connected']); }
catch(Throwable $e){ http_response_code(500); echo json_encode(['ok'=>false,'database'=>'failed','error'=>$e->getMessage()]); }
