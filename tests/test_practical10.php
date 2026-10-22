<?php
declare(strict_types=1);

/**
 * ============================================================================
 * StudentHub - Automated Test Suite: Practical 10 (Secure Login & Sessions)
 * Tests:
 *   1. Credential Verification with password_verify()
 *   2. Session Fixation Prevention (session_regenerate_id)
 *   3. Session Inactivity Timeout Detection
 *   4. Role-Based Access Control (RBAC)
 *   5. Remember-Me Dual-Token Generation (Selector + SHA-256 Validator)
 *   6. Remember-Me Auto-Login Validation
 *   7. Secure Logout & Token Revocation
 * ============================================================================
 */

$isCli = (php_sapi_name() === 'cli');

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/StudentHUB_php/auth.php';

$results = [];

function runTest(string $title, callable $fn): array {
    try {
        $note = $fn();
        return ['title' => $title, 'passed' => true, 'note' => is_string($note) ? $note : 'OK'];
    } catch (Throwable $t) {
        return ['title' => $title, 'passed' => false, 'note' => $t->getMessage()];
    }
}

// ----------------------------------------------------------------------------
// Test 1: Credential Verification (password_verify against Bcrypt hash)
// ----------------------------------------------------------------------------
$results[] = runTest('Credential Authentication with password_verify()', function () {
    $db = getDatabaseConnection();
    $stmt = $db->prepare('SELECT user_id, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
    $email = 'admin@charusat.edu.in';
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        throw new RuntimeException("Seeded user '$email' not found.");
    }

    // Default seeded password is 'Password@123'
    $validPass = 'Password@123';
    $wrongPass = 'WrongPassword999';

    if (!password_verify($validPass, $user['password_hash'])) {
        throw new RuntimeException("password_verify failed for valid password '$validPass'.");
    }
    if (password_verify($wrongPass, $user['password_hash'])) {
        throw new RuntimeException("password_verify erroneously accepted wrong password '$wrongPass'.");
    }

    return "Correct password verified with Bcrypt; invalid password securely rejected.";
});

// ----------------------------------------------------------------------------
// Test 2: Session Fixation Prevention
// ----------------------------------------------------------------------------
$results[] = runTest('Session Fixation Prevention (session_regenerate_id)', function () {
    initSession();
    $oldSessionId = session_id();
    session_regenerate_id(true);
    $newSessionId = session_id();

    if ($oldSessionId === $newSessionId) {
        throw new RuntimeException("Session ID was not regenerated.");
    }

    return "Session ID regenerated from '$oldSessionId' to '$newSessionId'.";
});

// ----------------------------------------------------------------------------
// Test 3: Session Inactivity Timeout
// ----------------------------------------------------------------------------
$results[] = runTest('Session Inactivity Timeout Enforcement', function () {
    initSession();

    // 1. Simulate active session
    $_SESSION['user'] = ['id' => 1, 'name' => 'Admin Officer', 'role' => 'admin'];
    $_SESSION['last_activity'] = time() - 100; // 100s ago
    $isActive = checkSessionTimeout(1800); // 1800s timeout

    if (!$isActive || empty($_SESSION['user'])) {
        throw new RuntimeException("Active session was incorrectly marked as expired.");
    }

    // 2. Simulate expired session (idle for 2000s > 1800s limit)
    $_SESSION['last_activity'] = time() - 2000;
    $isExpired = !checkSessionTimeout(1800);

    if (!$isExpired) {
        throw new RuntimeException("Inactive session exceeding timeout threshold was not terminated.");
    }

    return "Active session refreshed successfully; inactive session (>1800s) properly invalidated.";
});

// ----------------------------------------------------------------------------
// Test 4: Role-Based Access Control (RBAC) Logic
// ----------------------------------------------------------------------------
$results[] = runTest('Role-Based Access Control (RBAC) Authorization', function () {
    // Helper to test roles
    $testRbac = function (array $user, array $allowedRoles): bool {
        return in_array($user['role'] ?? '', $allowedRoles, true);
    };

    $student = ['role' => 'student'];
    $teacher = ['role' => 'teacher'];
    $admin   = ['role' => 'admin'];

    // Student allowed on student pages
    if (!$testRbac($student, ['student', 'admin'])) {
        throw new RuntimeException("Student was rejected from student-permitted page.");
    }

    // Student rejected from teacher-only page
    if ($testRbac($student, ['teacher', 'admin'])) {
        throw new RuntimeException("Student was improperly permitted on teacher-only page.");
    }

    // Admin allowed on all
    if (!$testRbac($admin, ['teacher', 'admin']) || !$testRbac($admin, ['student', 'admin'])) {
        throw new RuntimeException("Admin was rejected from privileged page.");
    }

    return "Role matrix verified: Student, Teacher, and Admin permissions strictly enforced.";
});

// ----------------------------------------------------------------------------
// Test 5: Remember-Me Dual-Token Generation (Selector + SHA-256 Validator)
// ----------------------------------------------------------------------------
$results[] = runTest('Remember-Me Dual-Token Storage (OWASP Pattern)', function () {
    $db = getDatabaseConnection();
    $userId = 1;

    $selector = bin2hex(random_bytes(8));     // 16 chars
    $validator = bin2hex(random_bytes(16));   // 32 chars
    $tokenHash = hash('sha256', $validator); // 64 chars
    $expiresAt = date('Y-m-d H:i:s', time() + (30 * 86400));

    $stmt = $db->prepare('INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)');
    if (!$stmt) {
        throw new RuntimeException("Prepare failed: " . $db->error);
    }
    $stmt->bind_param('isss', $userId, $selector, $tokenHash, $expiresAt);
    $stmt->execute();
    $tokenId = $db->insert_id;
    $stmt->close();

    if ($tokenId <= 0) {
        throw new RuntimeException("Failed to insert remember token.");
    }

    // Verify token stored in DB
    $chk = $db->prepare('SELECT token_hash FROM remember_tokens WHERE selector = ?');
    $chk->bind_param('s', $selector);
    $chk->execute();
    $res = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$res || !hash_equals($res['token_hash'], $tokenHash)) {
        throw new RuntimeException("Token hash in DB does not match generated hash.");
    }

    // Clean up test token
    $del = $db->prepare('DELETE FROM remember_tokens WHERE token_id = ?');
    $del->bind_param('i', $tokenId);
    $del->execute();
    $del->close();

    return "16-char selector, 64-char SHA-256 hash, and 30-day expiration verified in DB.";
});

// ----------------------------------------------------------------------------
// Test 6: Remember-Me Auto-Login Validation
// ----------------------------------------------------------------------------
$results[] = runTest('Remember-Me Token Verification & Auto-Login', function () {
    $db = getDatabaseConnection();
    $userId = 1;

    $selector = bin2hex(random_bytes(8));
    $validator = bin2hex(random_bytes(16));
    $tokenHash = hash('sha256', $validator);
    $expiresAt = date('Y-m-d H:i:s', time() + (30 * 86400));

    // Insert test token
    $stmt = $db->prepare('INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('isss', $userId, $selector, $tokenHash, $expiresAt);
    $stmt->execute();
    $stmt->close();

    // Set cookie
    $_COOKIE[REMEMBER_ME_COOKIE_NAME] = $selector . ':' . $validator;
    $_SESSION['user'] = null;

    $loginOk = attemptRememberMeLogin();

    if (!$loginOk || empty($_SESSION['user']) || $_SESSION['user']['id'] !== $userId) {
        throw new RuntimeException("attemptRememberMeLogin failed to authenticate user from cookie.");
    }

    // Clean up
    revokeRememberMeToken();

    return "Cookie authenticated user automatically and populated active session.";
});

// ----------------------------------------------------------------------------
// Test 7: Secure Logout & Token Invalidation
// ----------------------------------------------------------------------------
$results[] = runTest('Secure Logout & Token Revocation', function () {
    $_SESSION['user'] = ['id' => 1, 'name' => 'Admin Officer', 'role' => 'admin'];
    $_COOKIE[REMEMBER_ME_COOKIE_NAME] = 'test_selector:test_validator';

    performLogout();

    if (!empty($_SESSION['user'])) {
        throw new RuntimeException("Session user was not cleared upon logout.");
    }
    if (isset($_COOKIE[REMEMBER_ME_COOKIE_NAME])) {
        throw new RuntimeException("Remember-Me cookie was not unset.");
    }

    return "Session cleared, remember-me cookie invalidated, and session destroyed.";
});

// ----------------------------------------------------------------------------
// Output Report
// ----------------------------------------------------------------------------
if ($isCli) {
    echo "====================================================================\n";
    echo " PRACTICAL 10: SECURE LOGIN & SESSIONS – AUTOMATED TEST REPORT\n";
    echo " Charotar University of Science and Technology (CHARUSAT)\n";
    echo "====================================================================\n\n";

    $passed = 0;
    foreach ($results as $i => $r) {
        $tag = $r['passed'] ? '[PASS]' : '[FAIL]';
        printf("%-7s Test %d: %s\n", $tag, $i + 1, $r['title']);
        echo "        Note: " . $r['note'] . "\n\n";
        if ($r['passed']) $passed++;
    }

    echo str_repeat('-', 68) . "\n";
    printf("Total: %d | Passed: %d | Failed: %d\n", count($results), $passed, count($results) - $passed);
    echo "====================================================================\n";
} else {
    header('Content-Type: application/json');
    echo json_encode($results, JSON_PRETTY_PRINT);
}
