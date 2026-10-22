<?php
declare(strict_types=1);

/**
 * ============================================================================
 * CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY (CHARUSAT)
 * FACULTY OF TECHNOLOGY AND ENGINEERING (FTE)
 * SUBJECT: WEB DEVELOPMENT FRAMEWORKS (ITUE203)
 * ============================================================================
 * File: test_all_practicals.php
 * Description: Master Automated Test Runner for StudentHub:
 *              Executes the end-to-end test suite across all laboratory
 *              practicals (Practicals 7 through 15) and compiles an aggregated
 *              verification and quality assurance report.
 * ============================================================================
 */

$startTime = microtime(true);
$phpCli = PHP_BINARY;

$suites = [
    [
        'id'       => 'P7',
        'title'    => 'Practical 7: Form Sanitization, CSRF & File Storage (JSON/CSV)',
        'file'     => __DIR__ . '/test_practical7.php'
    ],
    [
        'id'       => 'P8',
        'title'    => 'Practical 8: Relational Schema, PDO & Stored Procedures',
        'file'     => __DIR__ . '/test_practical8.php'
    ],
    [
        'id'       => 'P9',
        'title'    => 'Practical 9: User Registration, Bcrypt Hashing & Duplicate Email Guard',
        'file'     => __DIR__ . '/test_practical9.php'
    ],
    [
        'id'       => 'P10',
        'title'    => 'Practical 10: Secure Login, Sessions, Inactivity Timeout & Remember-Me',
        'file'     => __DIR__ . '/test_practical10.php'
    ],
    [
        'id'       => 'P11',
        'title'    => 'Practical 11: Student Management CRUD & Soft-Delete Architecture',
        'file'     => __DIR__ . '/test_practical11.php'
    ],
    [
        'id'       => 'FP',
        'title'    => 'Faculty Portal: Course & Student Academic Record Management',
        'file'     => __DIR__ . '/test_faculty_crud.php'
    ],
    [
        'id'       => 'P12',
        'title'    => 'Practical 12: Campus Event CRUD & Verified File/Poster Upload',
        'file'     => __DIR__ . '/test_practical12.php'
    ],
    [
        'id'       => 'P13',
        'title'    => 'Practical 13: RESTful JSON API & Asynchronous Fetch CRUD',
        'file'     => __DIR__ . '/test_practical13.php'
    ],
    [
        'id'       => 'P14',
        'title'    => 'Practical 14: Admin Command Center, Telemetry, RBAC & Audit Trail',
        'file'     => __DIR__ . '/test_practical14.php'
    ],
    [
        'id'       => 'P15',
        'title'    => 'Practical 15: Final System Integration, Views & Deployment Verification',
        'file'     => __DIR__ . '/test_practical15.php'
    ]
];

echo "====================================================================\n";
echo " STUDENT HUB: MASTER AUTOMATED VERIFICATION TEST RUNNER\n";
echo " Charotar University of Science and Technology (CHARUSAT)\n";
echo " Faculty of Technology and Engineering (FTE) | Web Frameworks (ITUE203)\n";
echo "====================================================================\n\n";

$suiteResults = [];
$totalSuitesPassed = 0;
$totalSuitesFailed = 0;

foreach ($suites as $s) {
    echo "Running Suite [{$s['id']}] {$s['title']}...\n";
    $cmd = sprintf('"%s" "%s" 2>&1', $phpCli, $s['file']);
    $output = shell_exec($cmd);
    $exitCode = 0;

    $isPass = false;
    if ($output !== null) {
        $hasPassMarker = str_contains($output, 'PASSED!')
            || str_contains($output, 'PASS')
            || str_contains($output, 'ALL PRACTICAL');
        $hasFailMarker = str_contains($output, 'FAILED!')
            || str_contains($output, 'SOME TESTS FAILED');

        $isPass = $hasPassMarker && !$hasFailMarker;
    }

    if ($isPass) {
        $totalSuitesPassed++;
        $suiteResults[] = [
            'id'     => $s['id'],
            'title'  => $s['title'],
            'status' => 'PASS',
            'badge'  => '[PASS]'
        ];
        echo "   -> [PASS] Suite {$s['id']} Completed Successfully.\n\n";
    } else {
        $totalSuitesFailed++;
        $suiteResults[] = [
            'id'     => $s['id'],
            'title'  => $s['title'],
            'status' => 'FAIL',
            'badge'  => '[FAIL]'
        ];
        echo "   -> [FAIL] Suite {$s['id']} Failed! Log Snippet:\n";
        $lines = explode("\n", (string)$output);
        $tail = array_slice($lines, -8);
        echo "      " . implode("\n      ", $tail) . "\n\n";
    }
}

$duration = round(microtime(true) - $startTime, 2);

echo "====================================================================\n";
echo " MASTER TEST EXECUTION SUMMARY REPORT\n";
echo "====================================================================\n";
printf("%-8s | %-6s | %s\n", "Suite", "Status", "Module Description");
echo "--------------------------------------------------------------------\n";
foreach ($suiteResults as $r) {
    printf("%-8s | %-6s | %s\n", $r['id'], $r['status'], $r['title']);
}
echo "--------------------------------------------------------------------\n";
printf("Total Test Suites: %d | Suites Passed: %d | Suites Failed: %d\n", count($suites), $totalSuitesPassed, $totalSuitesFailed);
printf("Execution Duration: %.2f seconds\n", $duration);
echo "Final Build Verdict: " . ($totalSuitesFailed === 0 ? "100% ALL PRACTICALS VERIFIED! READY FOR SUBMISSION 🚀" : "BUILD VERIFICATION FAILED ❌") . "\n";
echo "====================================================================\n";

exit($totalSuitesFailed === 0 ? 0 : 1);
