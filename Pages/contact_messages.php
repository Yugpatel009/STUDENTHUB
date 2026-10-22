<?php
declare(strict_types=1);

/**
 * ============================================================================
 * StudentHub - Contact Inquiries Records Viewer
 * Practical 7: Intermediate Extension (Display stored CSV/JSON records on webpage)
 * ============================================================================
 */

$jsonFile = __DIR__ . '/../StudentHUB_php/contact_messages.json';
$csvFile  = __DIR__ . '/../StudentHUB_php/contact_messages.csv';

$messages = [];
$storageMode = 'JSON';

// Load from JSON if available
if (file_exists($jsonFile)) {
    $raw = @file_get_contents($jsonFile);
    $decoded = json_decode($raw ?: '[]', true);
    if (is_array($decoded)) {
        $messages = $decoded;
    }
}

// Fallback or verify CSV
$csvRecordCount = 0;
if (file_exists($csvFile)) {
    if (($h = fopen($csvFile, 'r')) !== false) {
        $header = fgetcsv($h); // skip header
        while (fgetcsv($h) !== false) {
            $csvRecordCount++;
        }
        fclose($h);
    }
}

// Optional format toggle via query string ?source=csv
if (isset($_GET['source']) && $_GET['source'] === 'csv' && file_exists($csvFile)) {
    $storageMode = 'CSV';
    $messages = [];
    if (($h = fopen($csvFile, 'r')) !== false) {
        $header = fgetcsv($h);
        while (($row = fgetcsv($h)) !== false) {
            if (count($row) >= 5) {
                $messages[] = [
                    'id'           => $row[0] ?? 'N/A',
                    'name'         => $row[1] ?? '',
                    'email'        => $row[2] ?? '',
                    'subject'      => $row[3] ?? 'General Inquiry',
                    'message'      => $row[4] ?? '',
                    'ip_address'   => $row[5] ?? '127.0.0.1',
                    'user_agent'   => $row[6] ?? '',
                    'submitted_at' => $row[7] ?? '',
                ];
            }
        }
        fclose($h);
    }
}

// Reverse to show newest first
$messages = array_reverse($messages);
$totalCount = count($messages);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Stored Inquiries (Practical 7)</title>
    <link rel="stylesheet" href="../css/style.css">
    <script>
        (function() {
            var saved = localStorage.getItem('studenthub_theme');
            var theme = saved || ((window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.style.colorScheme = theme;
        })();
    </script>
    <style>
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .stat-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; box-shadow: var(--shadow-sm); }
        .stat-card .label { font-size: 13px; color: var(--text-muted); margin-bottom: 6px; }
        .stat-card .val { font-size: 26px; font-weight: 700; color: var(--text-primary); }
        .toolbar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 20px; }
        .search-box { flex: 1; max-width: 380px; padding: 10px 16px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-subtle); color: var(--text-primary); font-size: 14px; }
        .search-box:focus { outline: none; border-color: var(--brand-primary); }
        .table-wrap { overflow-x: auto; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; box-shadow: var(--shadow-sm); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        th { background: var(--bg-subtle); padding: 14px 16px; font-weight: 600; color: var(--text-muted); border-bottom: 1px solid var(--border-color); text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; }
        td { padding: 14px 16px; border-bottom: 1px solid var(--border-color); vertical-align: top; color: var(--text-primary); }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: var(--bg-subtle); }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; }
        .badge-json { background: rgba(16, 185, 129, 0.15); color: #059669; }
        [data-theme="dark"] .badge-json { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .badge-csv { background: rgba(59, 130, 246, 0.15); color: #2563eb; }
        [data-theme="dark"] .badge-csv { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
        .btn-tab { padding: 8px 16px; border-radius: 8px; font-size: 13px; text-decoration: none; border: 1px solid var(--border-color); color: var(--text-muted); transition: all 0.2s; background: var(--bg-card); }
        .btn-tab.active { background: var(--brand-primary); color: #fff; border-color: var(--brand-primary); }
    </style>
</head>
<body>
    <header class="site-header">
        <a class="brand" href="../index.html">
            <img src="../img/logo.png" alt="Student Hub Logo">
        </a>
        <nav class="main-nav">
            <a href="../index.html">Home</a>
            <a href="dashboard.php">Dashboard</a>
            <a href="contact.html">Contact</a>
            <a href="contact_messages.php" class="active">Stored Inquiries</a>
        </nav>
        <div class="header-right">
            <a href="contact.html" class="btn btn-secondary">&larr; Back to Form</a>
        </div>
    </header>

    <main class="container">
        <section style="margin-bottom: 28px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 16px;">
                <div>
                    <p style="text-transform: uppercase; font-size: 12px; letter-spacing: 0.1em; color: #3b82f6; font-weight: 700; margin: 0 0 6px;">
                        Practical 7 Intermediate Extension
                    </p>
                    <h1 style="margin: 0; font-size: 28px;">Submitted Contact Inquiries</h1>
                    <p style="margin: 6px 0 0; color: var(--text-muted, #94a3b8); font-size: 14px;">
                        Live record viewer displaying submissions safely stored with file locking (<span class="badge badge-json">JSON</span> & <span class="badge badge-csv">CSV</span>).
                    </p>
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="contact_messages.php?source=json" class="btn-tab <?= $storageMode === 'JSON' ? 'active' : '' ?>">Source: JSON</a>
                    <a href="contact_messages.php?source=csv" class="btn-tab <?= $storageMode === 'CSV' ? 'active' : '' ?>">Source: CSV</a>
                </div>
            </div>
        </section>

        <!-- Stats Bar -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="label">Total Inquiries (Loaded via <?= $storageMode ?>)</div>
                <div class="val"><?= $totalCount ?></div>
            </div>
            <div class="stat-card">
                <div class="label">JSON Records Stored</div>
                <div class="val" style="color: #34d399;"><?= count(json_decode(@file_get_contents($jsonFile) ?: '[]', true) ?: []) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">CSV Records Stored</div>
                <div class="val" style="color: #60a5fa;"><?= $csvRecordCount ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Locking Mechanism</div>
                <div class="val" style="font-size: 18px; color: #f59e0b; padding-top: 4px;">flock(LOCK_EX) Safe</div>
            </div>
        </section>

        <!-- Toolbar -->
        <div class="toolbar">
            <input type="text" id="searchInput" class="search-box" placeholder="🔍 Search by name, email, or keyword..." onkeyup="filterInquiries()">
            <div style="font-size: 13px; color: var(--text-muted, #94a3b8);">
                Showing <strong><span id="filteredCount"><?= $totalCount ?></span></strong> records
            </div>
        </div>

        <!-- Table View -->
        <div class="table-wrap">
            <table id="inquiriesTable">
                <thead>
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Subject</th>
                        <th>Message</th>
                        <th>IP Address</th>
                        <th>Submitted At</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($messages)): ?>
                    <tr>
                        <td colspan="7" class="empty-state">
                            <h3>No submissions recorded yet</h3>
                            <p>Submit an inquiry through the <a href="contact.html" style="color: #3b82f6;">Contact Form</a> to see it appear here.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($messages as $index => $msg): ?>
                        <tr class="inquiry-row">
                            <td style="color: var(--text-muted, #94a3b8); font-family: monospace; font-size: 12px;">
                                <?= $index + 1 ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars((string)($msg['name'] ?? 'Anonymous'), ENT_QUOTES, 'UTF-8') ?></strong>
                            </td>
                            <td>
                                <a href="mailto:<?= htmlspecialchars((string)($msg['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="color: #3b82f6;">
                                    <?= htmlspecialchars((string)($msg['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </td>
                            <td>
                                <span class="badge badge-json" style="font-size: 11px;">
                                    <?= htmlspecialchars((string)($msg['subject'] ?? 'General Inquiry'), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td style="max-width: 320px; word-break: break-word;">
                                <?= nl2br(htmlspecialchars((string)($msg['message'] ?? ''), ENT_QUOTES, 'UTF-8')) ?>
                            </td>
                            <td style="font-family: monospace; font-size: 12px; color: var(--text-muted, #94a3b8);">
                                <?= htmlspecialchars((string)($msg['ip_address'] ?? '127.0.0.1'), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td style="font-size: 12px; white-space: nowrap; color: var(--text-muted, #94a3b8);">
                                <?= htmlspecialchars((string)($msg['submitted_at'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <footer class="site-footer" style="margin-top: 60px;">
        <p>&copy; 2026 Student Hub. CHARUSAT ITUE203 - Practical 7 Extension.</p>
    </footer>

    <script>
        function filterInquiries() {
            const query = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('.inquiry-row');
            let visible = 0;

            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                    visible++;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('filteredCount').innerText = visible;
        }
    </script>
</body>
</html>
