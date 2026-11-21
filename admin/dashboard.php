<?php
require_once __DIR__ . '/../config/database.php';
require_admin();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// Get the currently open session.
$session = $pdo->query(
    "SELECT * FROM queue_sessions
     WHERE status = 'Open'
     ORDER BY id DESC
     LIMIT 1"
)->fetch();

$sessionId = $session ? (int)$session['id'] : 0;

$queues = [];
$serving = null;

$waitingCount = 0;
$completedCount = 0;
$skippedCount = 0;
$cancelledCount = 0;

if ($session) {

    // Get tickets belonging to the current session.
    $stmt = $pdo->prepare(
        "SELECT q.id, q.queue_number, q.student_name, q.section, q.status,
                q.created_at, q.called_at, t.type_name
         FROM queue_records q
         JOIN transaction_types t
             ON t.id = q.transaction_type_id
         WHERE q.session_id = ?
         AND q.status IN ('Waiting', 'Serving', 'Skipped')
         ORDER BY
             CASE q.status
                 WHEN 'Serving' THEN 0
                 WHEN 'Waiting' THEN 1
                 ELSE 2
             END,
             q.created_at ASC, q.id ASC"
    );

    $stmt->execute([$sessionId]);
    $queues = $stmt->fetchAll();

    // Get the currently serving ticket.
    $stmt = $pdo->prepare(
        "SELECT q.id, q.student_name, q.section, q.queue_number, t.type_name
         FROM queue_records q
         JOIN transaction_types t
             ON t.id = q.transaction_type_id
         WHERE q.session_id = ?
         AND q.status = 'Serving'
         ORDER BY q.called_at ASC
         LIMIT 1"
    );

    $stmt->execute([$sessionId]);
    $serving = $stmt->fetch();

    // Get session statistics.
    $stmt = $pdo->prepare(
        "SELECT
            SUM(status = 'Waiting') AS waiting_count,
            SUM(status = 'Completed') AS completed_count,
            SUM(status = 'Skipped') AS skipped_count,
            SUM(status = 'Cancelled') AS cancelled_count
         FROM queue_records
         WHERE session_id = ?"
    );

    $stmt->execute([$sessionId]);
    $stats = $stmt->fetch();

    $waitingCount = (int)($stats['waiting_count'] ?? 0);
    $completedCount = (int)($stats['completed_count'] ?? 0);
    $skippedCount = (int)($stats['skipped_count'] ?? 0);
    $cancelledCount = (int)($stats['cancelled_count'] ?? 0);
}

function queue_label_admin(array $row): string {
    return 'Q-' . str_pad(
        (string)$row['queue_number'],
        4,
        '0',
        STR_PAD_LEFT
    );
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Queue Admin Dashboard</title>
<link rel="stylesheet" href="../assets/style.css">
</head>

<body>

<header class="topbar">
    <div class="container topbar-inner">
        <div>
            <div class="brand">Queue Admin</div>
            <div class="subbrand">
                Signed in as <?= e($_SESSION['admin_username'] ?? 'Staff') ?>
            </div>
        </div>

        <a class="text-link" href="logout.php">Sign out</a>
    </div>
</header>

<main class="container page">

    <div class="page-heading">
        <div>
            <span class="eyebrow">STAFF DASHBOARD</span>
            <h1>Queue management</h1>
            <p class="muted">Manage queue sessions and student transactions.</p>
        </div>

        <div>
            <a class="button button-secondary" href="history.php">History</a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert"><?= e($flash) ?></div>
    <?php endif; ?>

    <!-- QUEUE SESSION CONTROLS -->
    <section class="card">
        <span class="eyebrow">QUEUE STATUS</span>

        <?php if ($session): ?>

            <h2>Queue is Open</h2>

            <p class="muted">
                Session #<?= (int)$session['id'] ?>
                · Started <?= e($session['started_at']) ?>
            </p>

            <form method="post"
                  action="action.php"
                  onsubmit="return confirm('Close the queue? All Waiting, Serving, and Skipped tickets in this session will be cancelled.');">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= e(csrf_token()) ?>">

                <input type="hidden"
                       name="action"
                       value="close_queue">

                <button class="button button-danger" type="submit">
                    Close Queue
                </button>
            </form>

        <?php else: ?>

            <h2>Queue is Closed</h2>

            <p class="muted">
                Students cannot generate tickets until staff opens a new session.
            </p>

            <form method="post" action="action.php">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= e(csrf_token()) ?>">

                <input type="hidden"
                       name="action"
                       value="open_queue">

                <button class="button" type="submit">
                    Open Queue
                </button>
            </form>

        <?php endif; ?>
    </section>

    <!-- SESSION STATISTICS -->
    <?php if ($session): ?>

        <section class="stats-grid three">
            <div class="stat card">
                <span>Waiting</span>
                <strong><?= $waitingCount ?></strong>
            </div>

            <div class="stat card">
                <span>Completed</span>
                <strong><?= $completedCount ?></strong>
            </div>

            <div class="stat card">
                <span>Skipped</span>
                <strong><?= $skippedCount ?></strong>
            </div>
        </section>

        <!-- CURRENTLY SERVING -->
        <section class="card serving-panel">
            <div>
                <span class="eyebrow">CURRENTLY SERVING</span>

                <div class="current-number">
                    <?= $serving ? e(queue_label_admin($serving)) : '—' ?>
                </div>

                <p class="muted">
                    <?= $serving
                        ? e($serving['type_name'])
                        : 'No active student' ?>
                </p>
            </div>

            <form method="post" action="action.php">
                <input type="hidden"
                       name="csrf_token"
                       value="<?= e(csrf_token()) ?>">

                <input type="hidden"
                       name="action"
                       value="call_next">

                <button class="button"
                        type="submit"
                        <?= $serving ? 'disabled' : '' ?>>
                    Call Next
                </button>
            </form>
        </section>

        <!-- QUEUE LIST -->
        <section class="card">
            <h2>Active and skipped queues</h2>

            <?php if (!$queues): ?>

                <p class="muted">
                    There are no active or skipped queues.
                </p>

            <?php else: ?>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Queue</th>
                                <th>Transaction</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($queues as $q): ?>

                            <tr>
                                <td>
                                    <strong>
                                        <?= e(queue_label_admin($q)) ?>
                                        <div class="student-details">
                                            <span>
                                                <?=
                                        e($q['student_name'] ?? 'Unknown Student') ?>
                                            <span>
                                        <br>
                                        <small>
                                            <?=
                                        e($q['section'] ?? 'No Section') ?>
                                    </small>
                        </div>
                                </td>

                                <td><?= e($q['type_name']) ?></td>

                                <td>
                                    <span class="status-pill status-<?= strtolower($q['status']) ?>">
                                        <?= e($q['status']) ?>
                                    </span>
                                </td>

                                <td class="actions">

                                    <?php if ($q['status'] === 'Serving'): ?>

                                        <form method="post" action="action.php">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="complete">
                                            <input type="hidden" name="queue_id" value="<?= (int)$q['id'] ?>">

                                            <button class="button button-small" type="submit">
                                                Complete
                                            </button>
                                        </form>

                                        <form method="post"
                                              action="action.php"
                                              onsubmit="return confirm('Skip this queue?');">

                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="skip">
                                            <input type="hidden" name="queue_id" value="<?= (int)$q['id'] ?>">

                                            <button class="button button-small button-danger" type="submit">
                                                Skip
                                            </button>
                                        </form>

                                    <?php elseif ($q['status'] === 'Waiting'): ?>

                                        <form method="post"
                                              action="action.php"
                                              onsubmit="return confirm('Mark this waiting queue as skipped?');">

                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="skip">
                                            <input type="hidden" name="queue_id" value="<?= (int)$q['id'] ?>">

                                            <button class="button button-small button-danger" type="submit">
                                                Skip
                                            </button>
                                        </form>

                                    <?php elseif ($q['status'] === 'Skipped'): ?>

                                        <form method="post" action="action.php">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="requeue">
                                            <input type="hidden" name="queue_id" value="<?= (int)$q['id'] ?>">

                                            <button class="button button-small button-secondary" type="submit">
                                                Requeue
                                            </button>
                                        </form>

                                    <?php endif; ?>

                                </td>
                            </tr>

                        <?php endforeach; ?>

                        </tbody>
                    </table>
                </div>

            <?php endif; ?>
        </section>

    <?php else: ?>

        <section class="card">
            <h2>No active queue session</h2>
            <p class="muted">
                Open a queue session to begin accepting students.
            </p>
        </section>

    <?php endif; ?>

    <p class="footer-note">
        Only one queue can be in Serving status at a time.
        Call Next becomes available after the current queue is completed or skipped.
    </p>

</main>

<script>
setTimeout(() => window.location.reload(), 15000);
</script>

</body>
</html>
