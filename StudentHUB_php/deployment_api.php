<?php
declare(strict_types=1);

/**
 * ============================================================================
 * CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY (CHARUSAT)
 * FACULTY OF TECHNOLOGY AND ENGINEERING (FTE)
 * SUBJECT: WEB DEVELOPMENT FRAMEWORKS (ITUE203)
 * Practical 15: Deployment & System Diagnostics API Controller
 * ============================================================================
 * File: deployment_api.php
 * Description: Controller handling asynchronous execution of master verification
 *              test suites and server deployment diagnostics.
 * Security: Guarded strictly by requireRole('admin') and CSRF verification.
 * ============================================================================
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/db.php';

initSession();

$currentUser = getCurrentUser();

function getPhpCliPath(): string
{
    // If PHP_BINARY is already a valid php executable, use it
    if (PHP_BINARY && preg_match('/php(?:\.exe)?$/i', PHP_BINARY)) {
        return PHP_BINARY;
    }

    $candidates = [
        'D:\\System-File\\XAMPP\\php\\php.exe',
        'C:\\xampp\\php\\php.exe',
        dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'php.exe',
        dirname(PHP_BINARY, 3) . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'php.exe',
        PHP_BINDIR . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php')
    ];

    foreach ($candidates as $candidate) {
        if (file_exists($candidate)) {
            return $candidate;
        }
    }

    return 'php';
}

function sendDeployJson(bool $success, string $message, $data = null, array $errors = [], int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'   => $success,
        'message'   => $message,
        'data'      => $data,
        'errors'    => $errors,
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// RBAC Guard: Only 'admin' role may access the deployment diagnostics API
if ($currentUser === null || ($currentUser['role'] ?? '') !== 'admin') {
    sendDeployJson(false, 'Forbidden: Administrator credentials required.', null, ['Access Denied'], 403);
}

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'info'));
$db = getDatabaseConnection();

// ----------------------------------------------------------------------------
// ACTION 1: RUN ALL MASTER TEST SUITES
// ----------------------------------------------------------------------------
if ($action === 'run_tests') {
    // CSRF Guard on POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!empty($_SESSION['csrf_token']) && !empty($token)) {
            if (!validateCsrfToken($token)) {
                sendDeployJson(false, 'Security token expired. Please refresh.', null, ['CSRF token mismatch'], 403);
            }
        }
    }

    $runnerScript = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'test_all_practicals.php';
    if (!file_exists($runnerScript)) {
        sendDeployJson(false, 'Master test runner file not found.', null, [], 404);
    }

    $startTime = microtime(true);
    $phpCli = getPhpCliPath();
    $cmd = sprintf('"%s" "%s" 2>&1', $phpCli, $runnerScript);
    $output = shell_exec($cmd);
    $duration = round(microtime(true) - $startTime, 2);

    $isAllPass = ($output !== null)
        && str_contains($output, '100% ALL PRACTICALS VERIFIED')
        && !str_contains($output, 'BUILD VERIFICATION FAILED');

    sendDeployJson(true, 'Master test suite executed.', [
        'all_passed' => $isAllPass,
        'duration'   => $duration,
        'output'     => $output,
        'timestamp'  => date('Y-m-d H:i:s')
    ]);
}

// ----------------------------------------------------------------------------
// ACTION 2: RETRIEVE SYSTEM DIAGNOSTICS & TABLE STATS
// ----------------------------------------------------------------------------
if ($action === 'info') {
    // Table inventory
    $tables = [
        'users', 'remember_tokens', 'students', 'courses', 'assignments',
        'submissions', 'events', 'registrations', 'audit_logs'
    ];
    $tableStats = [];
    foreach ($tables as $tbl) {
        $res = $db->query("SELECT COUNT(*) AS total FROM `{$tbl}`");
        $cnt = $res ? (int)$res->fetch_assoc()['total'] : 0;
        $tableStats[$tbl] = $cnt;
    }

    // View Summary
    $viewRes = $db->query("SELECT * FROM vw_admin_dashboard_summary LIMIT 1");
    $viewSummary = $viewRes ? $viewRes->fetch_assoc() : [];

    // File Storage checks
    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'events';
    $csvFile   = __DIR__ . DIRECTORY_SEPARATOR . 'registered_users.csv';
    $jsonFile  = __DIR__ . DIRECTORY_SEPARATOR . 'contact_messages.json';

    $storageInfo = [
        'uploads_events' => [
            'path'     => 'uploads/events',
            'exists'   => is_dir($uploadDir),
            'writable' => is_dir($uploadDir) && is_writable($uploadDir),
            'count'    => is_dir($uploadDir) ? count(glob($uploadDir . '/*.*')) : 0
        ],
        'users_csv' => [
            'path'     => 'StudentHUB_php/registered_users.csv',
            'exists'   => file_exists($csvFile),
            'writable' => file_exists($csvFile) ? is_writable($csvFile) : false,
            'size_kb'  => file_exists($csvFile) ? round(filesize($csvFile) / 1024, 2) : 0
        ],
        'contact_json' => [
            'path'     => 'StudentHUB_php/contact_messages.json',
            'exists'   => file_exists($jsonFile),
            'writable' => file_exists($jsonFile) ? is_writable($jsonFile) : false,
            'size_kb'  => file_exists($jsonFile) ? round(filesize($jsonFile) / 1024, 2) : 0
        ]
    ];

    sendDeployJson(true, 'Diagnostics info retrieved.', [
        'environment' => [
            'php_version'        => PHP_VERSION,
            'server_software'    => $_SERVER['SERVER_SOFTWARE'] ?? 'Apache/2.4 (XAMPP)',
            'database_version'   => $db->server_info,
            'database_name'      => DB_NAME,
            'memory_limit'       => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time') . 's',
            'upload_max_filesize'=> ini_get('upload_max_filesize'),
            'post_max_size'      => ini_get('post_max_size'),
            'os'                 => PHP_OS_FAMILY
        ],
        'tables'  => $tableStats,
        'views'   => $viewSummary,
        'storage' => $storageInfo
    ]);
}

sendDeployJson(false, 'Invalid action specified.', null, [], 400);
