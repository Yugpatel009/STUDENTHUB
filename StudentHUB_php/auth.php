<?php
declare(strict_types=1);

/**
 * ============================================================================
 * CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY (CHARUSAT)
 * FACULTY OF TECHNOLOGY AND ENGINEERING (FTE)
 * SUBJECT: WEB DEVELOPMENT FRAMEWORKS (ITUE203)
 * ============================================================================
 * File: auth.php
 * Description: Practical 10 - Secure Authentication & Session Management Module
 * Features:
 *   - Session Fixation Protection (session_regenerate_id)
 *   - Session Inactivity Timeout (configurable, default 15-30 min)
 *   - Role-Based Access Control (RBAC: student, teacher, admin)
 *   - Persistent Remember-Me Token using OWASP dual-token pattern
 *     (selector + SHA-256 validator)
 *   - Safe Logout with Token Invalidation
 * ============================================================================
 */

require_once __DIR__ . '/db.php';

// Session configuration constants
defined('SESSION_TIMEOUT_SECONDS') || define('SESSION_TIMEOUT_SECONDS', 1800); // 30 minutes
defined('REMEMBER_ME_COOKIE_NAME') || define('REMEMBER_ME_COOKIE_NAME', 'studenthub_remember');
defined('REMEMBER_ME_EXPIRY_DAYS') || define('REMEMBER_ME_EXPIRY_DAYS', 30);

/**
 * Initialize session with security flags (HttpOnly, SameSite)
 */
function initSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_start();
    }
}

/**
 * Check and enforce session inactivity timeout.
 * If inactivity period exceeded, destroys session and returns false.
 *
 * @param int $timeoutSeconds Maximum idle seconds allowed
 * @return bool True if session is active and not timed out, False if expired
 */
function checkSessionTimeout(int $timeoutSeconds = SESSION_TIMEOUT_SECONDS): bool
{
    initSession();

    if (!empty($_SESSION['user'])) {
        $now = time();
        if (isset($_SESSION['last_activity']) && ($now - (int)$_SESSION['last_activity']) > $timeoutSeconds) {
            // Session expired due to inactivity
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            return false;
        }

        // Update last activity timestamp
        $_SESSION['last_activity'] = $now;
    }

    return true;
}

/**
 * Create a persistent "Remember Me" token for the authenticated user.
 * Follows the OWASP Selector/Validator pattern:
 * - Cookie stores "selector:validator"
 * - Database stores selector and hash('sha256', validator)
 *
 * @param int $userId
 * @return bool
 */
function createRememberMeToken(int $userId): bool
{
    try {
        $selector = bin2hex(random_bytes(8));     // 16 chars
        $validator = bin2hex(random_bytes(16));   // 32 chars
        $tokenHash = hash('sha256', $validator); // 64 chars
        $expiresAt = date('Y-m-d H:i:s', time() + (REMEMBER_ME_EXPIRY_DAYS * 86400));

        $db = getDatabaseConnection();
        $stmt = $db->prepare(
            'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)'
        );
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('isss', $userId, $selector, $tokenHash, $expiresAt);
        $stmt->execute();
        $stmt->close();

        // Set secure HTTP-only cookie
        $cookieValue = $selector . ':' . $validator;
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

        setcookie(
            REMEMBER_ME_COOKIE_NAME,
            $cookieValue,
            [
                'expires'  => time() + (REMEMBER_ME_EXPIRY_DAYS * 86400),
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );

        $_COOKIE[REMEMBER_ME_COOKIE_NAME] = $cookieValue;
        return true;
    } catch (Throwable $e) {
        error_log('[Remember-Me Error] ' . $e->getMessage());
        return false;
    }
}

/**
 * Validate a remember-me cookie and automatically log the user in.
 *
 * @return bool True if auto-login succeeded, False otherwise
 */
function attemptRememberMeLogin(): bool
{
    initSession();

    if (!empty($_SESSION['user'])) {
        return true;
    }

    $cookie = $_COOKIE[REMEMBER_ME_COOKIE_NAME] ?? null;
    if (empty($cookie) || !is_string($cookie) || !str_contains($cookie, ':')) {
        return false;
    }

    [$selector, $validator] = explode(':', $cookie, 2);

    if (empty($selector) || empty($validator) || strlen($selector) !== 16) {
        return false;
    }

    try {
        $db = getDatabaseConnection();
        $stmt = $db->prepare(
            'SELECT rt.token_id, rt.user_id, rt.token_hash, rt.expires_at,
                    u.full_name, u.email, u.role, u.course
             FROM remember_tokens rt
             INNER JOIN users u ON rt.user_id = u.user_id
             WHERE rt.selector = ? AND rt.expires_at > NOW()
             LIMIT 1'
        );

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('s', $selector);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row) {
            revokeRememberMeToken();
            return false;
        }

        // Verify SHA-256 hash using timing-safe comparison
        $expectedHash = hash('sha256', $validator);
        if (!hash_equals($row['token_hash'], $expectedHash)) {
            revokeRememberMeToken();
            return false;
        }

        // Token is valid! Regenerate session ID to prevent fixation
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'     => (int) $row['user_id'],
            'name'   => $row['full_name'],
            'email'  => $row['email'],
            'role'   => $row['role'],
            'course' => $row['course']
        ];
        $_SESSION['last_activity'] = time();

        // Update last login timestamp in DB
        $upd = $db->prepare('UPDATE users SET last_login_at = NOW() WHERE user_id = ?');
        $upd->bind_param('i', $row['user_id']);
        $upd->execute();
        $upd->close();

        return true;
    } catch (Throwable $e) {
        error_log('[Remember-Me Auto-Login Error] ' . $e->getMessage());
        return false;
    }
}

/**
 * Revoke and delete the remember-me token from database and browser cookie.
 */
function revokeRememberMeToken(): void
{
    $cookie = $_COOKIE[REMEMBER_ME_COOKIE_NAME] ?? null;
    if (!empty($cookie) && is_string($cookie) && str_contains($cookie, ':')) {
        [$selector] = explode(':', $cookie, 2);
        try {
            $db = getDatabaseConnection();
            $stmt = $db->prepare('DELETE FROM remember_tokens WHERE selector = ?');
            if ($stmt) {
                $stmt->bind_param('s', $selector);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Throwable $e) {
            error_log('[Remember-Me Revoke Error] ' . $e->getMessage());
        }
    }

    // Expire browser cookie
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    setcookie(
        REMEMBER_ME_COOKIE_NAME,
        '',
        [
            'expires'  => time() - 86400,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );

    unset($_COOKIE[REMEMBER_ME_COOKIE_NAME]);
}

/**
 * Safely log out the user, clear session, and revoke remember-me tokens.
 */
function performLogout(): void
{
    initSession();
    revokeRememberMeToken();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

/**
 * Primary Guard: Enforce authenticated login with inactivity timeout check
 * and automatic remember-me recovery.
 *
 * @param int $timeoutSeconds Maximum idle seconds allowed
 * @return array The authenticated user's session data
 */
function requireLogin(int $timeoutSeconds = SESSION_TIMEOUT_SECONDS): array
{
    initSession();

    // 1. Check inactivity timeout
    if (!checkSessionTimeout($timeoutSeconds)) {
        header('Location: ../Pages/login.html?timeout=1');
        exit;
    }

    // 2. If session empty, attempt Remember-Me auto-login
    if (empty($_SESSION['user'])) {
        if (!attemptRememberMeLogin()) {
            header('Location: ../Pages/login.html');
            exit;
        }
    }

    return $_SESSION['user'];
}

/**
 * Role-Based Access Control Guard: Enforces that the current authenticated user
 * possesses one of the allowed roles.
 *
 * @param string|array $allowedRoles Single role string or array of roles
 * @return array The verified user session data
 */
function requireRole(string|array $allowedRoles): array
{
    $user = requireLogin();
    $roles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];

    if (!in_array($user['role'] ?? '', $roles, true)) {
        // Forbidden: Role unauthorized
        http_response_code(403);
        $roleStr = htmlspecialchars(implode(', ', $roles), ENT_QUOTES, 'UTF-8');
        $userRole = htmlspecialchars((string)($user['role'] ?? 'Guest'), ENT_QUOTES, 'UTF-8');

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Forbidden - StudentHub</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #0f172a; font-family: 'Inter', system-ui, sans-serif; color: #f8fafc; margin: 0; padding: 20px; }
        .card { background: #1e293b; border: 1px solid #7f1d1d; border-radius: 16px; padding: 40px; max-width: 500px; width: 100%; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .icon { font-size: 52px; margin-bottom: 12px; }
        h1 { margin: 0 0 10px; font-size: 24px; color: #f87171; }
        p { color: #94a3b8; font-size: 14px; line-height: 1.6; }
        .badge { background: #7f1d1d; color: #fca5a5; padding: 3px 8px; border-radius: 4px; font-weight: bold; }
        .actions { margin-top: 26px; display: flex; gap: 10px; justify-content: center; }
        .btn { padding: 10px 20px; border-radius: 8px; font-size: 14px; text-decoration: none; font-weight: 600; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-secondary { background: #334155; color: #e2e8f0; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🚫</div>
        <h1>403 - Access Denied</h1>
        <p>Your current role is <span class="badge">{$userRole}</span>.<br>This page requires one of the following permissions: <strong>{$roleStr}</strong>.</p>
        <div class="actions">
            <a href="dashboard.php" class="btn btn-primary">&larr; Back to Dashboard</a>
            <a href="../StudentHUB_php/logout.php" class="btn btn-secondary">Switch Account</a>
        </div>
    </div>
</body>
</html>
HTML;
        exit;
    }

    return $user;
}

/**
 * Retrieve current user array without forcing redirect, or null if unauthenticated.
 *
 * @return array|null
 */
function getCurrentUser(): ?array
{
    initSession();
    checkSessionTimeout();

    if (empty($_SESSION['user'])) {
        attemptRememberMeLogin();
    }

    return $_SESSION['user'] ?? null;
}
