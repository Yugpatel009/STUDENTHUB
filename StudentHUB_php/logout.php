<?php
declare(strict_types=1);

/**
 * ============================================================================
 * StudentHub - Secure Logout Controller
 * Practical 10: Session destruction, cookie invalidation, and token revocation
 * ============================================================================
 */

require_once __DIR__ . '/auth.php';

performLogout();

header('Location: ../Pages/login.html?logged_out=1');
exit;
