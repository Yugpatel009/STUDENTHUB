<?php
declare(strict_types=1);

/**
 * ============================================================================
 * StudentHub - CSRF (Cross-Site Request Forgery) Protection Utility
 * Practical 7: Advanced Extension
 * ============================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate a cryptographically secure CSRF token and store it in session.
 *
 * @return string The generated CSRF token.
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token against the session token.
 *
 * @param string|null $token The submitted token from POST or headers.
 * @return bool True if valid, false otherwise.
 */
function validateCsrfToken(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals((string) $_SESSION['csrf_token'], (string) $token);
}

/**
 * Regenerate the CSRF token after sensitive operations.
 *
 * @return string The new token.
 */
function regenerateCsrfToken(): string
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return (string) $_SESSION['csrf_token'];
}
