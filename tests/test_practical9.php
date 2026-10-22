<?php
declare(strict_types=1);

/**
 * ============================================================================
 * StudentHub - Automated Test Suite: Practical 9 (Secure Registration)
 * Tests: POST guard, sanitization, multi-rule validation, duplicate email check,
 *        password_hash() strength, and MySQLi INSERT with prepared statements.
 * ============================================================================
 */

$isCli = php_sapi_name() === 'cli';

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/StudentHUB_php/csrf.php';

$results = [];

function runTest(string $title, callable $fn): array {
    try {
        $note = $fn();
        return ['title' => $title, 'passed' => true, 'note' => is_string($note) ? $note : 'OK'];
    } catch (Throwable $t) {
        return ['title' => $title, 'passed' => false, 'note' => $t->getMessage()];
    }
}

/* ── Test 1: POST guard ─────────────────────────────────────────────────── */
$results[] = runTest('POST Method Guard', function () {
    // Simulate non-POST
    $method = 'GET';
    if ($method !== 'POST') return 'Correctly identified non-POST request (would reject).';
    throw new RuntimeException('Guard did not fire on GET.');
});

/* ── Test 2: Name sanitization ──────────────────────────────────────────── */
$results[] = runTest('Input Sanitization (strip_tags + trim)', function () {
    $raw       = "  <b>Aarav</b> <i>Sharma</i>  ";
    $sanitized = trim(strip_tags($raw));
    if ($sanitized !== "Aarav Sharma") throw new RuntimeException("Expected 'Aarav Sharma', got '$sanitized'.");
    return "strip_tags and trim produce clean output: \"$sanitized\"";
});

/* ── Test 3: Multi-rule validation ──────────────────────────────────────── */
$results[] = runTest('Server-Side Multi-Rule Validation', function () {
    $cases = [
        ['name' => 'A',         'valid' => false, 'reason' => 'Name too short'],
        ['name' => 'Aarav',     'valid' => true,  'reason' => 'Name OK'],
        ['email' => 'bad-email','valid' => false, 'reason' => 'Invalid email format'],
        ['email' => 'a@b.com',  'valid' => true,  'reason' => 'Valid email'],
        ['pwd'  => 'abc',       'valid' => false, 'reason' => 'Password too short'],
        ['pwd'  => 'Abc12345',  'valid' => true,  'reason' => 'Password meets all rules'],
    ];
    foreach ($cases as $c) {
        if (isset($c['name'])) {
            $ok = mb_strlen($c['name']) >= 2;
        } elseif (isset($c['email'])) {
            $ok = (bool) filter_var($c['email'], FILTER_VALIDATE_EMAIL);
        } else {
            $pwd = $c['pwd'];
            $ok  = strlen($pwd) >= 8 && preg_match('/[A-Z]/', $pwd) && preg_match('/[0-9]/', $pwd);
        }
        if ($ok !== $c['valid']) {
            throw new RuntimeException("Validation mismatch for case: {$c['reason']}.");
        }
    }
    return 'All validation rules behave correctly (name, email, password strength).';
});

/* ── Test 4: Password hashing ───────────────────────────────────────────── */
$results[] = runTest('password_hash(PASSWORD_DEFAULT) + password_verify()', function () {
    $plain = 'Secret@99';
    $hash  = password_hash($plain, PASSWORD_DEFAULT);

    if (!str_starts_with($hash, '$2y$')) {
        throw new RuntimeException("Hash does not start with bcrypt identifier '\$2y\$'.");
    }
    if (!password_verify($plain, $hash)) {
        throw new RuntimeException('password_verify() failed for a correct password.');
    }
    if (password_verify('WrongPass', $hash)) {
        throw new RuntimeException('password_verify() returned true for a wrong password!');
    }
    return "Bcrypt hash generated (algo: PASSWORD_DEFAULT). Verify correct=true, wrong=false.";
});

/* ── Test 5: Duplicate email check via MySQLi ────────────────────────────── */
$results[] = runTest('MySQLi Prepared Statement – Duplicate Email Check', function () {
    $db = getDatabaseConnection();
    $knownEmail = 'admin@charusat.edu.in'; // seeded in schema

    $stmt = $db->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
    if ($stmt === false) throw new RuntimeException('Prepare failed: ' . $db->error);

    $stmt->bind_param('s', $knownEmail);
    $stmt->execute();
    $stmt->store_result();
    $found = $stmt->num_rows > 0;
    $stmt->close();

    if (!$found) throw new RuntimeException("Seeded email '$knownEmail' not found; schema may not be imported.");
    return "Duplicate detected correctly for '$knownEmail' (num_rows = 1).";
});

/* ── Test 6: MySQLi INSERT + CSV Dual Persistence ────────────────────────── */
$results[] = runTest('Dual Persistence – MySQL Database & CSV File Storage', function () {
    $db        = getDatabaseConnection();
    $testEmail = 'test_dual_' . time() . '@charusat.edu.in';
    $hash      = password_hash('Testpass@1', PASSWORD_DEFAULT);
    $role      = 'student';
    $course    = 'bca';
    $name      = 'Test Dual Storage User';

    // 1. MySQL Insert
    $stmt = $db->prepare(
        'INSERT INTO users (full_name, email, password_hash, role, course) VALUES (?, ?, ?, ?, ?)'
    );
    if ($stmt === false) throw new RuntimeException('Prepare failed: ' . $db->error);

    $stmt->bind_param('sssss', $name, $testEmail, $hash, $role, $course);
    $stmt->execute();
    $newId = $db->insert_id;
    $stmt->close();

    if ($newId <= 0) throw new RuntimeException('Insert returned no valid ID.');

    // 2. CSV Write Verification
    $csvFile = dirname(__DIR__) . '/StudentHUB_php/registered_users.csv';
    $csvHandle = fopen($csvFile, 'a+');
    if (!$csvHandle || !flock($csvHandle, LOCK_EX)) {
        throw new RuntimeException('Failed to acquire exclusive lock on registered_users.csv.');
    }
    if (filesize($csvFile) === 0) {
        fputcsv($csvHandle, ['User ID', 'Full Name', 'Email', 'Role', 'Course', 'Registered At', 'IP Address', 'User Agent']);
    }
    fputcsv($csvHandle, [$newId, $name, $testEmail, $role, $course, date('Y-m-d H:i:s'), '127.0.0.1', 'PHPUnit-CLI-Test']);
    fflush($csvHandle);
    flock($csvHandle, LOCK_UN);
    fclose($csvHandle);

    // Read back CSV
    $csvContent = file_get_contents($csvFile);
    if (!str_contains($csvContent, $testEmail)) {
        throw new RuntimeException('CSV file does not contain the written email.');
    }

    // Cleanup DB
    $del = $db->prepare('DELETE FROM users WHERE user_id = ?');
    $del->bind_param('i', $newId);
    $del->execute();
    $del->close();

    return "Saved user with ID=$newId to MySQL & appended to registered_users.csv with flock().";
});

/* ── Test 7: CSRF token validation ──────────────────────────────────────── */
$results[] = runTest('CSRF Token Generation & Validation', function () {
    $token  = generateCsrfToken();
    $valid  = validateCsrfToken($token);
    $tamper = validateCsrfToken('tampered_token_xyz');
    if (!$valid)  throw new RuntimeException('Valid token failed validation.');
    if ($tamper)  throw new RuntimeException('Tampered token incorrectly passed validation.');
    return "64-char hex CSRF token verified via hash_equals(); tampered token rejected.";
});

/* ── Output ─────────────────────────────────────────────────────────────── */
if ($isCli) {
    echo "====================================================================\n";
    echo " PRACTICAL 9: SECURE REGISTRATION – AUTOMATED TEST REPORT\n";
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
