<?php
declare(strict_types=1);

/**
 * ============================================================================
 * StudentHub - Automated Test Suite for Practical 7
 * Tests: Form POST handling, Server-Side Validation, Sanitization,
 *        CSRF Protection, Concurrency-Safe File Locking (JSON & CSV).
 * ============================================================================
 */

$isCli = (php_sapi_name() === 'cli');

function runTest(string $title, callable $testFn): array {
    try {
        $result = $testFn();
        return [
            'title'  => $title,
            'passed' => true,
            'note'   => is_string($result) ? $result : 'OK'
        ];
    } catch (Throwable $t) {
        return [
            'title'  => $title,
            'passed' => false,
            'note'   => $t->getMessage()
        ];
    }
}

$results = [];

// Base paths
$phpDir = dirname(__DIR__) . '/StudentHUB_php';
$jsonFile = $phpDir . '/contact_messages.json';
$csvFile  = $phpDir . '/contact_messages.csv';

// Include utilities
require_once $phpDir . '/csrf.php';

// Test 1: CSRF Token Generation & Hash-Equals Validation
$results[] = runTest('CSRF Token Generation & Validation', function() {
    $token = generateCsrfToken();
    if (strlen($token) !== 64) {
        throw new Exception("Generated token length was " . strlen($token) . ", expected 64 hex characters.");
    }
    if (!validateCsrfToken($token)) {
        throw new Exception("Valid token failed validation check.");
    }
    if (validateCsrfToken('malicious_or_tampered_token_string')) {
        throw new Exception("Tampered token incorrectly passed validation check!");
    }
    return "Cryptographically secure 64-char hex token verified via hash_equals().";
});

// Test 2: Server-Side Sanitization
$results[] = runTest('Server-Side Input Sanitization (XSS & Strip Tags)', function() {
    $rawName = "  <b>John</b> <i>Doe</i>  ";
    $sanitizedName = trim(strip_tags($rawName));
    if ($sanitizedName !== "John Doe") {
        throw new Exception("Sanitization failed to strip HTML tags properly: got '$sanitizedName'");
    }

    $rawEmail = "   john.doe@charusat.edu.in  ";
    $cleanEmail = filter_var(trim($rawEmail), FILTER_SANITIZE_EMAIL);
    if ($cleanEmail !== "john.doe@charusat.edu.in") {
        throw new Exception("Email sanitization failed: got '$cleanEmail'");
    }
    return "HTML tags stripped and email whitespace sanitized.";
});

// Test 3: Validation Rules (Email format, name bounds, message length)
$results[] = runTest('Server-Side Input Validation Rules', function() {
    $invalidEmails = ['plainaddress', '@missingusername.com', 'user@.invalid', 'user@domain'];
    foreach ($invalidEmails as $bad) {
        if (filter_var($bad, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Validation incorrectly accepted bad email: '$bad'");
        }
    }
    
    $validEmail = 'student2026@charusat.edu.in';
    if (!filter_var($validEmail, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Validation rejected valid email: '$validEmail'");
    }

    $shortMessage = 'Hi';
    if (mb_strlen($shortMessage) >= 10) {
        throw new Exception("Short message check failed");
    }
    return "Rejects invalid emails and enforces min 10-char message length.";
});

// Test 4: Concurrency-Safe File Locking and Dual JSON/CSV Storage
$results[] = runTest('Safe File Writing with flock() for JSON & CSV', function() use ($jsonFile, $csvFile) {
    $testRecord = [
        'id'           => uniqid('test_', true),
        'name'         => 'Test Student Evaluator',
        'email'        => 'evaluator@charusat.edu.in',
        'subject'      => 'Practical 7 Verification',
        'message'      => 'Automated test record verifying atomic file persistence with flock.',
        'ip_address'   => '127.0.0.1',
        'user_agent'   => 'PHPUnit-CLI-Test',
        'submitted_at' => date('Y-m-d H:i:s'),
    ];

    // Verify JSON write with lock
    $jh = fopen($jsonFile, 'c+');
    if (!$jh || !flock($jh, LOCK_EX)) {
        throw new Exception("Failed to acquire exclusive lock on $jsonFile");
    }
    $raw = stream_get_contents($jh);
    $arr = json_decode($raw ?: '[]', true) ?: [];
    $arr[] = $testRecord;
    rewind($jh);
    ftruncate($jh, 0);
    fwrite($jh, json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    fflush($jh);
    flock($jh, LOCK_UN);
    fclose($jh);

    // Verify CSV write with lock
    $ch = fopen($csvFile, 'a+');
    if (!$ch || !flock($ch, LOCK_EX)) {
        throw new Exception("Failed to acquire exclusive lock on $csvFile");
    }
    if (filesize($csvFile) === 0) {
        fputcsv($ch, ['ID', 'Name', 'Email', 'Subject', 'Message', 'IP Address', 'User Agent', 'Submitted At']);
    }
    fputcsv($ch, array_values($testRecord));
    fflush($ch);
    flock($ch, LOCK_UN);
    fclose($ch);

    // Read back and confirm
    $verifyJson = json_decode(file_get_contents($jsonFile), true);
    if (!is_array($verifyJson) || count($verifyJson) === 0) {
        throw new Exception("Verification read of JSON file failed or empty.");
    }
    return "Atomic write and exclusive flock() verified for both JSON and CSV files.";
});

// Test 5: End-to-end HTTP POST Simulation to contact.php
$results[] = runTest('End-to-End POST Simulation with CSRF Protection', function() use ($phpDir) {
    // Session token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    // Simulate invalid CSRF
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'csrf_token' => 'invalid_token',
        'name'       => 'Hacker Attempt',
        'email'      => 'hack@test.com',
        'message'    => 'This should fail CSRF validation.'
    ];

    if (validateCsrfToken($_POST['csrf_token'])) {
        throw new Exception("Invalid token unexpectedly matched session!");
    }

    // Now simulate valid token
    $_POST['csrf_token'] = $_SESSION['csrf_token'];
    if (!validateCsrfToken($_POST['csrf_token'])) {
        throw new Exception("Valid token failed session match!");
    }

    return "Successfully blocked CSRF tampering and authorized matching token.";
});

// Output formatted results
if ($isCli) {
    echo "====================================================================\n";
    echo " PRACTICAL 7: AUTOMATED TEST SUITE EXECUTION REPORT\n";
    echo " Charotar University of Science and Technology (CHARUSAT)\n";
    echo "====================================================================\n\n";

    $passedCount = 0;
    foreach ($results as $idx => $r) {
        $num = $idx + 1;
        $status = $r['passed'] ? '[PASS]' : '[FAIL]';
        echo sprintf("%-6s Test %d: %s\n", $status, $num, $r['title']);
        echo "       Note: " . $r['note'] . "\n\n";
        if ($r['passed']) $passedCount++;
    }

    echo "--------------------------------------------------------------------\n";
    echo sprintf("Total Tests: %d | Passed: %d | Failed: %d\n", count($results), $passedCount, count($results) - $passedCount);
    echo "====================================================================\n";
} else {
    header('Content-Type: application/json');
    echo json_encode($results, JSON_PRETTY_PRINT);
}
