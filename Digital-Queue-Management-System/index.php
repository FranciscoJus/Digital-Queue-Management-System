<?php
require_once __DIR__ . '/config/database.php';

$error = '';
$ticket = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Student chooses to leave the queue.
    if (isset($_POST['leave_queue'])) {
        $token = $_POST['ticket_token'] ?? '';

        if (is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token)) {
            $cancel = $pdo->prepare(
                "UPDATE queue_records
                 SET status = 'Cancelled'
                 WHERE ticket_token = ? AND status = 'Waiting'"
            );
            $cancel->execute([$token]);
            header('Location: index.php?ticket=' . urlencode($token));
            exit;
        }

        $error = 'Invalid ticket. Please return to the Student Portal.';
    } else {

// Student generates a new queue ticket.
$typeId = filter_input(INPUT_POST, 'transaction_type_id', FILTER_VALIDATE_INT);
$studentName = trim($_POST['student_name'] ?? '');
$section = trim($_POST['section'] ?? '');

$check = $pdo->prepare(
    'SELECT id FROM transaction_types WHERE id = ? AND is_active = 1'
);
$check->execute([$typeId]);

if (!$typeId || !$check->fetch() || $studentName === '' || $section === '') {
    $error = 'Please fill in all required fields and select a transaction type.';
} else {
    try {
        $pdo->beginTransaction();

        // Find and lock the currently open queue session.
        $sessionStmt = $pdo->query(
            "SELECT id FROM queue_sessions
             WHERE status = 'Open'
             ORDER BY id DESC
             LIMIT 1 FOR UPDATE"
        );

        $session = $sessionStmt->fetch();

        if (!$session) {
            throw new RuntimeException(
                'The queue is currently closed. Please try again later.'
            );
        }

        $sessionId = (int)$session['id'];

        // Generate the next number within this session.
        $numberStmt = $pdo->prepare(
            'SELECT COALESCE(MAX(queue_number), 0) + 1
             FROM queue_records
             WHERE session_id = ?'
        );
        $numberStmt->execute([$sessionId]);

        $queueNumber = (int)$numberStmt->fetchColumn();

        // Create the ticket.
        $token = bin2hex(random_bytes(32));

        $insert = $pdo->prepare(
            'INSERT INTO queue_records
                (transaction_type_id, ticket_token, session_id, queue_number, student_name, section)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        $insert->execute([
            $typeId,
            $token,
            $sessionId,
            $queueNumber,
            $studentName,
            $section
        ]);

        $pdo->commit();

        header('Location: index.php?ticket=' . urlencode($token));
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Unable to generate your queue ticket. Please try again.';
    }
}
    
    }
}

$types = $pdo->query('SELECT id, type_name FROM transaction_types WHERE is_active = 1 ORDER BY type_name')->fetchAll();

// Is a queue session currently open?
$queueOpen = (bool)$pdo->query(
    "SELECT id FROM queue_sessions WHERE status = 'Open' LIMIT 1"
)->fetch();

if (isset($_GET['ticket']) && preg_match('/^[a-f0-9]{64}$/', $_GET['ticket'])) {
    $stmt = $pdo->prepare(
        'SELECT q.*, t.type_name, s.status AS session_status
         FROM queue_records q
         JOIN transaction_types t ON t.id = q.transaction_type_id
         LEFT JOIN queue_sessions s ON s.id = q.session_id
         WHERE q.ticket_token = ?'
    );
    $stmt->execute([$_GET['ticket']]);
    $ticket = $stmt->fetch() ?: null;
}

$currentStmt = $pdo->query(
    "SELECT q.id, q.queue_number, t.type_name
     FROM queue_records q
     JOIN transaction_types t ON t.id = q.transaction_type_id
     WHERE q.status = 'Serving'
     ORDER BY q.called_at ASC
     LIMIT 1"
);
$current = $currentStmt->fetch();

$waitingCount = (int)$pdo->query("SELECT COUNT(*) FROM queue_records WHERE status = 'Waiting'")->fetchColumn();

$estimate = null;
$peopleAhead = null;
if ($ticket && $ticket['status'] === 'Waiting') {
    $aheadStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM queue_records
         WHERE status = 'Waiting'
           AND (created_at < ? OR (created_at = ? AND id < ?))"
    );
    $aheadStmt->execute([$ticket['created_at'], $ticket['created_at'], $ticket['id']]);
    $peopleAhead = (int)$aheadStmt->fetchColumn();
    $servingCount = (int)$pdo->query("SELECT COUNT(*) FROM queue_records WHERE status = 'Serving'")->fetchColumn();
    $estimate = ($peopleAhead + $servingCount) * AVERAGE_SERVICE_MINUTES;
}

function queue_label(array $row): string {
    return 'Q-' . str_pad((string)$row['queue_number'], 4, '0', STR_PAD_LEFT);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Queue Portal</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <div>
            <div class="brand">Digital Queue</div>
            <div class="subbrand">Student Services Portal</div>
        </div>
    </div>
</header>

<main class="container page">
    <section class="hero">
        <span class="eyebrow">STUDENT PORTAL</span>
        <h1>Skip the line, not your turn.</h1>
        <p>Generate a queue number and check your estimated waiting time.</p>
    </section>

    <?php if ($ticket): ?>
        <section class="card ticket-card">
            <span class="eyebrow">YOUR QUEUE TICKET</span>
            <div class="ticket-number"><?= e(queue_label($ticket)) ?></div>
            <h2><?= e($ticket['type_name']) ?></h2>
            <div class="status-pill status-<?= strtolower($ticket['status']) ?>"><?= e($ticket['status']) ?></div>

            <?php if ($ticket['status'] === 'Waiting'): ?>
                <div class="stats-grid">
                    <div class="stat">
                        <span>People ahead</span>
                        <strong><?= (int)$peopleAhead ?></strong>
                    </div>
                    <div class="stat">
                        <span>Estimated wait</span>
                        <strong><?= (int)$estimate ?> min</strong>
                    </div>
                </div>
                <p class="muted">This is an estimate based on <?= AVERAGE_SERVICE_MINUTES ?> minutes per transaction. Actual waiting time may vary.</p>
                <form method="post" onsubmit="return confirm('Are you sure you want to leave the queue?');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="ticket_token" value="<?= e($ticket['ticket_token']) ?>">
                    <button class="button button-danger full" type="submit" name="leave_queue">Leave Queue</button>
                </form>
            <?php elseif ($ticket['status'] === 'Serving'): ?>
                <p class="success-text">It's your turn. Please proceed to the service counter.</p>
            <?php elseif ($ticket['status'] === 'Skipped'): ?>
                <p class="warning-text">Your number was skipped. Please speak with staff if you have arrived.</p>
            <?php elseif ($ticket['status'] === 'Cancelled'): ?>
                <?php if (($ticket['session_status'] ?? '') === 'Closed'): ?>
                    <p class="warning-text">The queue has been closed by staff, so this ticket is no longer active. Please try again when the queue reopens.</p>
                <?php else: ?>
                    <p class="warning-text">You have left the queue. Your ticket has been cancelled.</p>
                <?php endif; ?>
            <?php else: ?>
                <p class="success-text">Your transaction has been completed. Thank you!</p>
            <?php endif; ?>
            <p class="muted small">Keep this page open to check your queue status. It refreshes automatically.</p>
            <?php if
            (in_array($ticket['status'], ['Completed', 'Skipped', 'Cancelled'], true)): ?>
            <a class="button button-secondary full" href="index.php">Generate another ticket</a>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="card">
            <?php if (!$queueOpen): ?>
                <h2>The queue is currently closed</h2>
                <p class="muted">Staff are not accepting new students right now. This page will update automatically when the queue opens.</p>
            <?php else: ?>
            <h2>Get a queue number</h2>
            <p class="muted">Choose the service you need to begin.</p>
            <?php if ($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label for="student_name">Full Name</label>
                <input
                    type="text"
                    id="student_name"
                    name="student_name"
                    placeholder="Enter your full name"
                    maxlength="50"
                    required
                    >

                <label for="section">Section</label>
                <input
                    type="text"
                    id="section"
                    name="section"
                    placeholder="e.g. BSIT 2A"
                    maxlength="50"
                    required
                    >

                <label for="transaction_type_id">Transaction Type</label>
                <select id="transaction_type_id" name="transaction_type_id" required>
                    <option value="">Select a transaction</option>
                    <?php foreach ($types as $type): ?>
                        <option value="<?= (int)$type['id'] ?>"><?= e($type['type_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button full" type="submit">Generate Queue Number</button>
            </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="card current-card">
        <div>
            <span class="eyebrow">NOW SERVING</span>
            <div class="current-number"><?= $current ? 'Q-' . str_pad((string)$current['queue_number'], 4, '0', STR_PAD_LEFT) : '—' ?></div>
            <p class="muted"><?= $current ? e($current['type_name']) : 'No student is currently being served.' ?></p>
        </div>
        <div class="queue-total">
            <strong><?= $waitingCount ?></strong>
            <span>waiting</span>
        </div>
    </section>
    <p class="footer-note">Estimated waiting times are not guaranteed and may change as transactions are completed.</p>
</main>
<?php if ($ticket || !$queueOpen): ?>
<script>
setTimeout(() => window.location.reload(), 15000);
</script>
<?php endif; ?>
</body>
</html>
