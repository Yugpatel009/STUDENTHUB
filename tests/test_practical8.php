<?php
declare(strict_types=1);

/**
 * ============================================================================
 * CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY (CHARUSAT)
 * FACULTY OF TECHNOLOGY AND ENGINEERING (FTE)
 * SUBJECT: WEB DEVELOPMENT FRAMEWORKS (ITUE203)
 * Practical 8: MySQL Schema, ER Model, PDO Connectivity & Prepared Statements
 * ============================================================================
 * File: test_practical8.php
 * Description: Automated Verification Test Suite for Practical 8:
 *                1. PDO Secure Connection & Stored Procedure Execution
 *                2. sp_GetEventSummary IN/OUT Parameter Binding
 *                3. sp_RegisterStudentForEvent Execution
 *                4. sp_RegisterStudentForEvent Duplicate Registration Error Handling
 *                5. sp_GetStudentRegistrations Schedule Query Execution
 * ============================================================================
 */

require_once __DIR__ . '/../db.php';

$passed = 0;
$failed = 0;
$results = [];

function recordTest(string $name, bool $isPass, string $note = ''): void {
    global $passed, $failed, $results;
    if ($isPass) {
        $passed++;
        $results[] = "[PASS]  {$name}\n        Note: {$note}";
    } else {
        $failed++;
        $results[] = "[FAIL]  {$name}\n        Note: {$note}";
    }
}

echo "====================================================================\n";
echo " PRACTICAL 8: SCHEMA, PDO & STORED PROCEDURES – TEST REPORT\n";
echo " Charotar University of Science and Technology (CHARUSAT)\n";
echo "====================================================================\n\n";

try {
    $pdo = Database::getPDO();
} catch (Throwable $e) {
    echo "Fatal: Could not connect to database: " . $e->getMessage() . "\n";
    exit(1);
}

// ----------------------------------------------------------------------------
// TEST 1: sp_GetEventSummary for Event 1
// ----------------------------------------------------------------------------
try {
    $summary = Database::callProcedure(
        'sp_GetEventSummary',
        [1],
        ['p_event_title', 'p_capacity', 'p_registered_count', 'p_seats_available']
    );

    $isPass = !empty($summary['p_event_title']) && isset($summary['p_capacity'], $summary['p_registered_count']);
    recordTest(
        "Test 1: Stored Procedure sp_GetEventSummary",
        $isPass,
        $isPass ? sprintf('Event: "%s" | Capacity: %d | Registered: %d | Available: %d',
            $summary['p_event_title'], $summary['p_capacity'], $summary['p_registered_count'], $summary['p_seats_available'])
            : "Invalid output values from sp_GetEventSummary."
    );
} catch (Throwable $e) {
    recordTest("Test 1: Stored Procedure sp_GetEventSummary", false, "Exception: " . $e->getMessage());
}

// ----------------------------------------------------------------------------
// TEST 2: sp_RegisterStudentForEvent
// ----------------------------------------------------------------------------
try {
    $regResult = Database::callProcedure(
        'sp_RegisterStudentForEvent',
        [21, 1],
        ['p_result_code', 'p_result_msg']
    );

    $isPass = in_array($regResult['p_result_code'] ?? '', ['SUCCESS', 'ERR_DUPLICATE'], true);
    recordTest(
        "Test 2: Stored Procedure sp_RegisterStudentForEvent",
        $isPass,
        sprintf("Result code: %s | Message: %s", $regResult['p_result_code'] ?? 'N/A', $regResult['p_result_msg'] ?? 'N/A')
    );
} catch (Throwable $e) {
    recordTest("Test 2: Stored Procedure sp_RegisterStudentForEvent", false, "Exception: " . $e->getMessage());
}

// ----------------------------------------------------------------------------
// TEST 3: Duplicate Registration Error Handling
// ----------------------------------------------------------------------------
try {
    $dupResult = Database::callProcedure(
        'sp_RegisterStudentForEvent',
        [21, 1],
        ['p_result_code', 'p_result_msg']
    );

    $isPass = (($dupResult['p_result_code'] ?? '') === 'ERR_DUPLICATE');
    recordTest(
        "Test 3: Duplicate Registration Prevention Guard",
        $isPass,
        $isPass ? "Correctly blocked duplicate registration with result code ERR_DUPLICATE." : "Did not block duplicate registration."
    );
} catch (Throwable $e) {
    recordTest("Test 3: Duplicate Registration Prevention Guard", false, "Exception: " . $e->getMessage());
}

// ----------------------------------------------------------------------------
// TEST 4: sp_GetStudentRegistrations
// ----------------------------------------------------------------------------
try {
    $schedule = Database::callProcedure('sp_GetStudentRegistrations', [21]);
    $isPass = is_array($schedule) && count($schedule) >= 1;
    recordTest(
        "Test 4: Schedule Query Procedure sp_GetStudentRegistrations",
        $isPass,
        $isPass ? sprintf("Found %d active registrations for student 21.", count($schedule)) : "No registrations found."
    );
} catch (Throwable $e) {
    recordTest("Test 4: Schedule Query Procedure sp_GetStudentRegistrations", false, "Exception: " . $e->getMessage());
}

// ----------------------------------------------------------------------------
// FINAL SUMMARY
// ----------------------------------------------------------------------------
echo "\n====================================================================\n";
echo " VERIFICATION RESULTS SUMMARY\n";
echo "====================================================================\n";
foreach ($results as $res) {
    echo $res . "\n\n";
}
echo "--------------------------------------------------------------------\n";
echo " Total Tests: " . ($passed + $failed) . " | Passed: {$passed} | Failed: {$failed}\n";
echo " Status: " . ($failed === 0 ? "ALL PRACTICAL 8 TESTS PASSED! 🚀" : "SOME TESTS FAILED! ❌") . "\n";
echo "====================================================================\n";

exit($failed === 0 ? 0 : 1);
