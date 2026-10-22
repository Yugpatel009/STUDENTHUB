<?php
declare(strict_types=1);

/**
 * ============================================================================
 * CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY (CHARUSAT)
 * FACULTY OF TECHNOLOGY AND ENGINEERING (FTE)
 * SUBJECT: WEB DEVELOPMENT FRAMEWORKS (ITUE203)
 * Practical 9: Secure User Registration with Database Insert,
 *              Duplicate Email Check, and Password Hashing
 * ============================================================================
 * File: register.php
 * Description: Handles POST-submitted registration form.
 *   - Server-side input sanitization & multi-rule validation
 *   - Duplicate email check via MySQLi prepared statement
 *   - Password hashing with password_hash(PASSWORD_BCRYPT)
 *   - INSERT INTO users using MySQLi prepared statement
 *   - JSON responses for AJAX / HTML page responses for standard POST
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';

/* ── helpers ──────────────────────────────────────────────────────────────── */

/** Detect AJAX / Fetch calls that accept JSON */
function isAjax(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr    = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    return $xhr === 'xmlhttprequest' || str_contains($accept, 'application/json');
}

/**
 * Terminate with a JSON payload (AJAX) or a styled HTML page (standard POST).
 *
 * @param bool        $success
 * @param string      $message  Primary message
 * @param string[]    $errors   Field-level error list
 * @param string|null $redirect URL to redirect to on success (HTML mode)
 */
function sendResponse(bool $success, string $message, array $errors = [], ?string $redirect = null): never
{
    $code = $success ? 200 : 422;
    http_response_code($code);

    if (isAjax()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'  => $success,
            'message'  => $message,
            'errors'   => $errors,
            'redirect' => $redirect,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Inline HTML response for non-AJAX ──────────────────────────────────
    $safeMsg     = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $icon        = $success ? '✅' : '❌';
    $cardColor   = $success ? '#064e3b' : '#7f1d1d';
    $borderColor = $success ? '#059669' : '#b91c1c';
    $textColor   = $success ? '#34d399' : '#fca5a5';
    $backPage    = $success ? 'login.html' : 'register.html';
    $backLabel   = $success ? 'Go to Login' : 'Back to Registration';

    $errorHtml = '';
    if (!empty($errors)) {
        $items = implode('', array_map(
            static fn($e) => '<li>' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</li>',
            $errors
        ));
        $errorHtml = "<ul style=\"text-align:left;margin:12px 0 0 18px;padding:0;font-size:13px;\">$items</ul>";
    }

    $redirectHtml = '';
    if ($success && $redirect) {
        $safeRedir   = htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8');
        $redirectHtml = "<meta http-equiv=\"refresh\" content=\"3;url={$safeRedir}\">"
                      . "<p style=\"font-size:12px;color:#94a3b8;margin-top:8px;\">Redirecting to login in 3 seconds…</p>";
    }

    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>StudentHub – Registration</title>
        {$redirectHtml}
        <link rel="stylesheet" href="../css/style.css">
        <style>
            body{display:flex;align-items:center;justify-content:center;min-height:100vh;
                 background:#0f172a;font-family:'Inter',system-ui,sans-serif;margin:0;padding:20px;}
            .card{background:{$cardColor};border:1px solid {$borderColor};border-radius:16px;
                  padding:36px 40px;max-width:480px;width:100%;text-align:center;
                  box-shadow:0 10px 30px rgba(0,0,0,.5);}
            .icon{font-size:44px;margin-bottom:12px;}
            h2{margin:0 0 10px;color:#f1f5f9;font-size:22px;}
            p{color:{$textColor};font-size:15px;margin:0;}
            .actions{margin-top:26px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap;}
            .btn{padding:10px 22px;border-radius:8px;text-decoration:none;font-weight:600;
                 font-size:14px;transition:.2s;display:inline-block;}
            .btn-primary{background:#3b82f6;color:#fff;}
            .btn-secondary{background:#1e293b;color:#e2e8f0;border:1px solid #334155;}
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon">{$icon}</div>
            <h2>{$safeMsg}</h2>
            {$errorHtml}
            <div class="actions">
                <a href="{$backPage}" class="btn btn-secondary">{$backLabel}</a>
                <a href="../index.html" class="btn btn-primary">Home</a>
            </div>
        </div>
    </body>
    </html>
    HTML;
    exit;
}

/* ── 1. Request method guard ──────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Use the registration form to create an account.');
}

/* ── 2. CSRF validation ─────────────────────────────────────────────────── */
$submittedToken = (string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!empty($_SESSION['csrf_token']) && !empty($submittedToken)) {
    if (!validateCsrfToken($submittedToken)) {
        sendResponse(false, 'Security token mismatch. Please refresh and try again.', [
            'CSRF token was invalid or expired. Please refresh the page and try again.',
        ]);
    }
}

/* ── 3. Collect & sanitize inputs ─────────────────────────────────────────── */
$name            = trim(strip_tags((string) ($_POST['name']             ?? '')));
$email           = filter_var(trim((string) ($_POST['email']            ?? '')), FILTER_SANITIZE_EMAIL);
$password        = (string) ($_POST['password']         ?? '');
$confirmPassword = (string) ($_POST['confirm-password'] ?? '');
$role            = (string) ($_POST['role']             ?? '');
$course          = trim((string) ($_POST['course']      ?? ''));

/* ── 4. Server-side validation ────────────────────────────────────────────── */
$errors     = [];
$validRoles = ['student', 'teacher', 'admin'];

// Name
if (mb_strlen($name) < 2) {
    $errors[] = 'Full name must be at least 2 characters long.';
} elseif (mb_strlen($name) > 100) {
    $errors[] = 'Full name cannot exceed 100 characters.';
}

// Email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address (e.g. student@example.com).';
}

// Password strength
if (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters long.';
} elseif (!preg_match('/[A-Z]/', $password)) {
    $errors[] = 'Password must contain at least one uppercase letter.';
} elseif (!preg_match('/[0-9]/', $password)) {
    $errors[] = 'Password must contain at least one number.';
}

// Password confirmation
if ($password !== $confirmPassword) {
    $errors[] = 'Passwords do not match. Please try again.';
}

// Role
if (!in_array($role, $validRoles, true)) {
    $errors[] = 'Please select a valid role (Student, Teacher, or Admin).';
}

// Course (required for students)
$validCourses = ['bca', 'bsc-it', 'mca'];
if ($role === 'student' && !in_array($course, $validCourses, true)) {
    $errors[] = 'Students must select a valid course (BCA, BSc IT, or MCA).';
}

// Terms acceptance
if (!isset($_POST['terms'])) {
    $errors[] = 'You must accept the portal terms to register.';
}

if (!empty($errors)) {
    sendResponse(false, 'Registration failed. Please fix the issues below.', $errors);
}

/* ── 5. Database operations (MySQLi) ─────────────────────────────────────── */
$db = getDatabaseConnection(); // MySQLi singleton from db.php

/* 5a. Duplicate email check via prepared statement */
$checkStmt = $db->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
if ($checkStmt === false) {
    error_log('[StudentHub Register] Prepare failed (dup-check): ' . $db->error);
    sendResponse(false, 'A server error occurred. Please try again later.');
}
$checkStmt->bind_param('s', $email);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    $checkStmt->close();
    sendResponse(false, 'An account with this email already exists.', [
        'This email address is already registered. Please log in or use a different email.',
    ]);
}
$checkStmt->close();

/* 5b. Hash the password (Bcrypt via PASSWORD_DEFAULT ≥ PHP 5.5) */
$passwordHash    = password_hash($password, PASSWORD_DEFAULT);
$selectedCourse  = ($role === 'student') ? $course : null;

/* 5c. INSERT INTO users using MySQLi prepared statement */
$insertStmt = $db->prepare(
    'INSERT INTO users (full_name, email, password_hash, role, course) VALUES (?, ?, ?, ?, ?)'
);
if ($insertStmt === false) {
    error_log('[StudentHub Register] Prepare failed (insert): ' . $db->error);
    sendResponse(false, 'A server error occurred. Please try again later.');
}

$insertStmt->bind_param('sssss', $name, $email, $passwordHash, $role, $selectedCourse);

try {
    $insertStmt->execute();
} catch (mysqli_sql_exception $e) {
    // Catch race-condition duplicate (error code 1062)
    if ($e->getCode() === 1062) {
        sendResponse(false, 'An account with this email already exists.', [
            'This email address is already registered. Please log in.',
        ]);
    }
    error_log('[StudentHub Register] Execute failed: ' . $e->getMessage());
    sendResponse(false, 'Unable to create your account. Please try again.');
}

$newUserId = (int) $db->insert_id;
$insertStmt->close();

/* ── 5d. Save registration to CSV file (Dual Persistence: DB + CSV) ───────── */
$csvFile = __DIR__ . DIRECTORY_SEPARATOR . 'registered_users.csv';
$csvExists = file_exists($csvFile) && filesize($csvFile) > 0;
$csvHandle = fopen($csvFile, 'a+');

if ($csvHandle !== false) {
    if (flock($csvHandle, LOCK_EX)) {
        try {
            // Write CSV headers if file is newly created or empty
            if (!$csvExists) {
                fputcsv($csvHandle, [
                    'User ID',
                    'Full Name',
                    'Email',
                    'Role',
                    'Course',
                    'Registered At',
                    'IP Address',
                    'User Agent'
                ]);
            }

            // Write user record row
            fputcsv($csvHandle, [
                $newUserId,
                $name,
                $email,
                $role,
                $selectedCourse ?? 'N/A',
                date('Y-m-d H:i:s'),
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 150)
            ]);

            fflush($csvHandle);
        } catch (Throwable $t) {
            error_log('[StudentHub Register CSV Storage Error] ' . $t->getMessage());
        } finally {
            flock($csvHandle, LOCK_UN);
            fclose($csvHandle);
        }
    } else {
        fclose($csvHandle);
    }
}

/* ── 6. Regenerate CSRF token post-registration ──────────────────────────── */
if (function_exists('regenerateCsrfToken')) {
    regenerateCsrfToken();
}

/* ── 7. Success ───────────────────────────────────────────────────────────── */
sendResponse(
    true,
    'Account created successfully and saved to database & CSV! Welcome to StudentHub.',
    [],
    '../Pages/login.html'
);

