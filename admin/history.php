<?php
require_once __DIR__ . '/../config/database.php';
require_admin();

// Which session to show (default: the most recent one).
$sessions = $pdo->query(
    "SELECT s.id, s.status, s.started_at, s.ended_at,
            COUNT(q.id) AS total,
            SUM(q.status = 'Completed') AS completed,
            SUM(q.status = 'Skipped')   AS skipped,
            SUM(q.status = 'Cancelled') AS cancelled
     FROM queue_sessions s
     LEFT JOIN queue_records q ON q.session_id = s.id
     GROUP BY s.id, s.status, s.started_at, s.ended_at
     ORDER BY s.id DESC"
)->fetchAll();

$selected = filter_input(INPUT_GET, 'session', FILTER_VALIDATE_INT);
if (!$selected && $sessions) {
    $selected = (int)$sessions[0]['id'];
}

$tickets = [];
if ($selected) {
    $stmt = $pdo->prepare(
        "SELECT q.queue_number, q.student_name, q.section, q.status,
                q.created_at, q.called_at, q.completed_at, q.skipped_at,
                t.type_name
         FROM queue_records q
         JOIN transaction_types t ON t.id = q.transaction_type_id
         WHERE q.session_id = ?
         ORDER BY q.queue_number ASC"
    );
    $stmt->execute([$selected]);
    $tickets = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Queue History</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <div>
            <div class="brand">Queue Admin</div>
            <div class="subbrand">Signed in as <?= e($_SESSION['admin_username'] ?? 'Staff') ?></div>
        </div>
        <a class="text-link" href="logout.php">Sign out</a>
    </div>
</header>

<main class="container page">
    <div class="page-heading">
        <div>
            <span class="eyebrow">STAFF DASHBOARD</span>
            <h1>Queue history</h1>
            <p class="muted">Every ticket from every queue session is kept here.</p>
        </div>
        <div>
            <a class="button button-secondary" href="dashboard.php">Back to dashboard</a>
        </div>
    </div>

    <section class="card">
        <h2>Sessions</h2>
        <?php if (!$sessions): ?>
            <p class="muted">No queue sessions yet.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Session</th><th>Opened</th><th>Closed</th>
                        <th>Tickets</th><th>Completed</th><th>Skipped</th><th>Cancelled</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($sessions as $s): ?>
                    <tr>
                        <td><strong>#<?= (int)$s['id'] ?></strong><?= $s['status'] === 'Open' ? ' (open)' : '' ?></td>
                        <td><?= e($s['started_at'] ?? '') ?></td>
                        <td><?= e($s['ended_at'] ?? '—') ?></td>
                        <td><?= (int)$s['total'] ?></td>
                        <td><?= (int)$s['completed'] ?></td>
                        <td><?= (int)$s['skipped'] ?></td>
                        <td><?= (int)$s['cancelled'] ?></td>
                        <td><a class="button button-small button-secondary" href="history.php?session=<?= (int)$s['id'] ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <?php if ($selected): ?>
    <section class="card">
        <h2>Tickets in session #<?= (int)$selected ?></h2>
        <?php if (!$tickets): ?>
            <p class="muted">No tickets in this session.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Queue</th><th>Student</th><th>Transaction</th>
                        <th>Status</th><th>Joined</th><th>Called</th><th>Finished</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($tickets as $t): ?>
                    <tr>
                        <td><strong>Q-<?= e(str_pad((string)$t['queue_number'], 4, '0', STR_PAD_LEFT)) ?></strong></td>
                        <td><?= e($t['student_name'] ?? 'Unknown') ?><br><small><?= e($t['section'] ?? '') ?></small></td>
                        <td><?= e($t['type_name']) ?></td>
                        <td><span class="status-pill status-<?= strtolower($t['status']) ?>"><?= e($t['status']) ?></span></td>
                        <td><?= e($t['created_at']) ?></td>
                        <td><?= e($t['called_at'] ?? '—') ?></td>
                        <td><?= e($t['completed_at'] ?? $t['skipped_at'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</main>
</body>
</html>
