<?php
declare(strict_types=1);

require_once __DIR__ . '/csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

echo json_encode([
    'csrf_token' => generateCsrfToken(),
    'timestamp'  => time()
]);
