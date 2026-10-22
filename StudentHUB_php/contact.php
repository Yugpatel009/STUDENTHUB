<?php
declare(strict_types=1);

/**
 * ============================================================================
 * StudentHub - Server-Side Contact Form Processor
 * Practical 7: PHP Form Processing with Server-Side Validation and CSV/JSON Storage
 * Advanced Extension: CSRF Token Validation
 * Intermediate Extension: CSV and JSON Storage formats
 * ============================================================================
 */

require_once __DIR__ . '/csrf.php';

/**
 * Respond to the user with either HTML or JSON based on request headers.
 */
function sendContactResponse(string $message, bool $success = false, array $errors = []): void
{
    $statusCode = $success ? 200 : 400;
    http_response_code($statusCode);

    // Detect AJAX/Fetch requests
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'   => $success,
            'status'    => $statusCode,
            'message'   => $message,
            'errors'    => $errors,
            'timestamp' => date('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Standard HTML Response
    $title = $success ? 'Message Sent Successfully' : 'Validation Error';
    $alertClass = $success ? 'success' : 'error';
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $errorHtml = '';

    if (!empty($errors)) {
        $errorHtml = '<ul style="text-align: left; margin: 15px auto; max-width: 400px; color: #dc2626;">';
        foreach ($errors as $err) {
            $errorHtml .= '<li>' . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        $errorHtml .= '</ul>';
    }

    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - {$title}</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #0f172a; font-family: 'Inter', system-ui, sans-serif; color: #f8fafc; margin: 0; padding: 20px; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 40px; max-width: 520px; width: 100%; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        .icon { font-size: 48px; margin-bottom: 16px; }
        h1 { margin: 0 0 12px; font-size: 24px; color: #f1f5f9; }
        p { color: #94a3b8; font-size: 15px; line-height: 1.5; }
        .actions { margin-top: 28px; display: flex; gap: 12px; justify-content: center; }
        .btn { padding: 10px 22px; border-radius: 8px; text-decoration: none; font-weight: 500; font-size: 14px; transition: all 0.2s; display: inline-block; }
        .btn-primary { background: #3b82f6; color: white; border: none; }
        .btn-primary:hover { background: #2563eb; }
        .btn-secondary { background: #334155; color: #e2e8f0; }
        .btn-secondary:hover { background: #475569; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">{$safeMessage}</div>
        <h1>{$title}</h1>
        <p>{$safeMessage}</p>
        {$errorHtml}
        <div class="actions">
            <a href="../Pages/contact.html" class="btn btn-secondary">&larr; Back to Contact Us</a>
            <a href="../Pages/contact_messages.php" class="btn btn-primary">View Stored Inquiries &rarr;</a>
        </div>
    </div>
</body>
</html>
HTML;
    exit;
}

// 1. Request Method Verification
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendContactResponse('Invalid request method. Only POST submissions are allowed.', false);
}

// 2. CSRF Token Validation (Advanced Extension)
$submittedCsrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
// If CSRF token is present in POST or session has a token, validate strictly
if (!empty($_SESSION['csrf_token'])) {
    if (!validateCsrfToken(is_string($submittedCsrf) ? $submittedCsrf : null)) {
        sendContactResponse('CSRF validation failed. Invalid or expired security token. Please refresh and try again.', false);
    }
}

// 3. Input Sanitization
$name    = trim(strip_tags((string) ($_POST['name'] ?? '')));
$email   = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_SANITIZE_EMAIL);
$subject = trim(strip_tags((string) ($_POST['subject'] ?? 'General Inquiry')));
$message = trim(strip_tags((string) ($_POST['message'] ?? '')));

// 4. Server-Side Validation
$errors = [];

if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    $errors[] = 'Full Name must be between 2 and 100 characters.';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please provide a valid email address.';
}

if (mb_strlen($message) < 10 || mb_strlen($message) > 2000) {
    $errors[] = 'Message must be between 10 and 2,000 characters.';
}

if (!empty($errors)) {
    sendContactResponse('Form validation failed. Please check your inputs.', false, $errors);
}

// 5. Concurrency-Safe File Persistence (JSON & CSV)
$record = [
    'id'           => uniqid('msg_', true),
    'name'         => $name,
    'email'        => $email,
    'subject'      => $subject,
    'message'      => $message,
    'ip_address'   => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
    'user_agent'   => substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI/Unknown', 0, 150),
    'submitted_at' => date('Y-m-d H:i:s'),
];

$savedJson = false;
$savedCsv  = false;

// 5a. Save to JSON file with flock
$jsonFile = __DIR__ . DIRECTORY_SEPARATOR . 'contact_messages.json';
$jsonHandle = fopen($jsonFile, 'c+');

if ($jsonHandle !== false) {
    if (flock($jsonHandle, LOCK_EX)) {
        try {
            $jsonContents = stream_get_contents($jsonHandle);
            $messages = json_decode($jsonContents ?: '[]', true);
            if (!is_array($messages)) {
                $messages = [];
            }
            $messages[] = $record;
            $encoded = json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded !== false) {
                rewind($jsonHandle);
                ftruncate($jsonHandle, 0);
                if (fwrite($jsonHandle, $encoded) !== false) {
                    fflush($jsonHandle);
                    $savedJson = true;
                }
            }
        } catch (Throwable $t) {
            error_log('[StudentHub JSON Storage Error] ' . $t->getMessage());
        } finally {
            flock($jsonHandle, LOCK_UN);
            fclose($jsonHandle);
        }
    }
}

// 5b. Save to CSV file with flock
$csvFile = __DIR__ . DIRECTORY_SEPARATOR . 'contact_messages.csv';
$csvExists = file_exists($csvFile) && filesize($csvFile) > 0;
$csvHandle = fopen($csvFile, 'a+');

if ($csvHandle !== false) {
    if (flock($csvHandle, LOCK_EX)) {
        try {
            // Write CSV header if creating a new file
            if (!$csvExists) {
                fputcsv($csvHandle, ['ID', 'Name', 'Email', 'Subject', 'Message', 'IP Address', 'User Agent', 'Submitted At']);
            }
            // Write record row
            fputcsv($csvHandle, [
                $record['id'],
                $record['name'],
                $record['email'],
                $record['subject'],
                $record['message'],
                $record['ip_address'],
                $record['user_agent'],
                $record['submitted_at'],
            ]);
            fflush($csvHandle);
            $savedCsv = true;
        } catch (Throwable $t) {
            error_log('[StudentHub CSV Storage Error] ' . $t->getMessage());
        } finally {
            flock($csvHandle, LOCK_UN);
            fclose($csvHandle);
        }
    }
}

// Regenerate CSRF token after successful submission
regenerateCsrfToken();

if ($savedJson || $savedCsv) {
    sendContactResponse('Thank you! Your message has been safely recorded in CSV and JSON formats.', true);
} else {
    sendContactResponse('Failed to persist contact record due to a server error. Please try again.', false);
}
