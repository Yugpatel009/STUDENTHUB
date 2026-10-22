<?php
declare(strict_types=1);

/**
 * ============================================================================
 * CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY (CHARUSAT)
 * FACULTY OF TECHNOLOGY AND ENGINEERING (FTE)
 * SUBJECT: WEB DEVELOPMENT FRAMEWORKS (ITUE203)
 * Practical 10: Secure Login/Logout with Sessions, Role-Based Access, Timeout,
 *               and Remember-Me Token
 * ============================================================================
 * File: login.php
 * Description: Secure Login Controller
 * Features:
 *   - Prepared statement authentication against MySQL users table
 *   - Secure password verification with password_verify()
 *   - Session Fixation Prevention (session_regenerate_id(true))
 *   - Last activity initialization for inactivity timeout
 *   - Persistent Remember-Me token generation and cookie handling
 *   - Dual JSON & HTML response support
 * ============================================================================
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

initSession();

/**
 * Respond to login requests via JSON or HTML redirect
 */
function sendLoginResponse(bool $success, string $message, ?string $redirect = null): never
{
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

    if ($isAjax) {
        http_response_code($success ? 200 : 401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'  => $success,
            'message'  => $message,
            'redirect' => $redirect,
            'timestamp' => date('c')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($success && $redirect) {
        header("Location: {$redirect}");
        exit;
    }

    // Standard HTML error response
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    http_response_code(401);
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Failed - StudentHub</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #0f172a; font-family: 'Inter', system-ui, sans-serif; color: #f8fafc; margin: 0; padding: 20px; }
        .card { background: #1e293b; border: 1px solid #7f1d1d; border-radius: 16px; padding: 36px 40px; max-width: 480px; width: 100%; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .icon { font-size: 44px; margin-bottom: 12px; }
        h2 { margin: 0 0 10px; color: #f87171; font-size: 22px; }
        p { color: #cbd5e1; font-size: 14px; margin: 0 0 20px; }
        .actions { display: flex; gap: 10px; justify-content: center; }
        .btn { padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-secondary { background: #334155; color: #e2e8f0; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">❌</div>
        <h2>Authentication Failed</h2>
        <p>{$safeMessage}</p>
        <div class="actions">
            <a href="../Pages/login.html" class="btn btn-primary">&larr; Back to Login</a>
            <a href="../Pages/register.html" class="btn btn-secondary">Create Account</a>
        </div>
    </div>
</body>
</html>
HTML;
    exit;
}

// 1. Guard Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendLoginResponse(false, 'Please use the login form to authenticate.');
}

// 2. CSRF Token Validation (if provided)
$submittedToken = (string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!empty($_SESSION['csrf_token']) && !empty($submittedToken)) {
    if (!validateCsrfToken($submittedToken)) {
        sendLoginResponse(false, 'Security token mismatch. Please refresh and try again.');
    }
}

// 3. Collect and Sanitize Inputs
$email      = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_SANITIZE_EMAIL);
$password   = (string) ($_POST['password'] ?? '');
$role       = trim((string) ($_POST['role'] ?? ''));
$rememberMe = !empty($_POST['remember_me']);

$validRoles = ['student', 'teacher', 'admin'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendLoginResponse(false, 'Please provide a valid email address.');
}

if ($password === '') {
    sendLoginResponse(false, 'Password cannot be blank.');
}

if (!in_array($role, $validRoles, true)) {
    sendLoginResponse(false, 'Please select a valid role (Student, Teacher, or Admin).');
}

// 4. Query User via MySQLi Prepared Statement
try {
    $db = getDatabaseConnection();
    $stmt = $db->prepare(
        'SELECT user_id, full_name, email, role, course, password_hash FROM users WHERE email = ? AND role = ? LIMIT 1'
    );

    if (!$stmt) {
        error_log('[StudentHub Login] Prepare error: ' . $db->error);
        sendLoginResponse(false, 'A server database error occurred. Please try again.');
    }

    $stmt->bind_param('ss', $email, $role);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log('[StudentHub Login Error] ' . $e->getMessage());
    sendLoginResponse(false, 'Unable to process login right now. Please try again later.');
}

// 5. Verify Password Hash using password_verify()
if (!$user || !password_verify($password, (string)$user['password_hash'])) {
    sendLoginResponse(false, 'Invalid email, password, or selected role. Please double-check your credentials.');
}

// 6. Prevent Session Fixation
session_regenerate_id(true);

// 7. Store Authenticated User in Session
$_SESSION['user'] = [
    'id'     => (int) $user['user_id'],
    'name'   => $user['full_name'],
    'email'  => $user['email'],
    'role'   => $user['role'],
    'course' => $user['course']
];
$_SESSION['last_activity'] = time();

// 8. Update Last Login Timestamp
try {
    $lastLoginStmt = $db->prepare('UPDATE users SET last_login_at = NOW() WHERE user_id = ?');
    if ($lastLoginStmt) {
        $lastLoginStmt->bind_param('i', $_SESSION['user']['id']);
        $lastLoginStmt->execute();
        $lastLoginStmt->close();
    }
} catch (Throwable $e) {
    error_log('[StudentHub Login] Last login update error: ' . $e->getMessage());
}

// 9. Handle "Remember Me" Token Generation
if ($rememberMe) {
    createRememberMeToken((int) $user['user_id']);
} else {
    // If not checked, ensure any old remember token is revoked
    revokeRememberMeToken();
}

// 10. Regenerate CSRF Token post-login
if (function_exists('regenerateCsrfToken')) {
    regenerateCsrfToken();
}

// 11. Success Response
sendLoginResponse(
    true,
    'Login successful! Redirecting to your dashboard...',
    '../Pages/dashboard.php'
);
