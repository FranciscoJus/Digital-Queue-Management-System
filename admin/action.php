<?php
require_once __DIR__ . '/../config/database.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

verify_csrf();

$action = $_POST['action'] ?? '';
$id = filter_input(INPUT_POST, 'queue_id', FILTER_VALIDATE_INT);

try {

    // OPEN QUEUE
    if ($action === 'open_queue') {

        $pdo->beginTransaction();

        $existing = $pdo->query(
            "SELECT id FROM queue_sessions
             WHERE status = 'Open'
             LIMIT 1 FOR UPDATE"
        )->fetch();

        if ($existing) {
            throw new RuntimeException('The queue is already open.');
        }

        $stmt = $pdo->prepare(
            "INSERT INTO queue_sessions (status, opened_at)
             VALUES ('Open', ?)"
        );

        $stmt->execute([(int)$_SESSION['admin_id']]);

        $pdo->commit();

        $_SESSION['flash'] = 'Queue opened successfully.';

    // CLOSE QUEUE
    } elseif ($action === 'close_queue') {

        $pdo->beginTransaction();

        $session = $pdo->query(
            "SELECT id FROM queue_sessions
             WHERE status = 'Open'
             LIMIT 1 FOR UPDATE"
        )->fetch();

        if (!$session) {
            throw new RuntimeException('There is no open queue session.');
        }

        $sessionId = (int)$session['id'];

        // Cancel unfinished tickets.
        $stmt = $pdo->prepare(
            "UPDATE queue_records
             SET status = 'Cancelled'
             WHERE session_id = ?
             AND status IN ('Waiting', 'Serving', 'Skipped')"
        );

        $stmt->execute([$sessionId]);

        // Mark the session as closed.
        $stmt = $pdo->prepare(
            "UPDATE queue_sessions
             SET status = 'Closed',
                 ended_at = NOW(),
                 closed_at = ?
             WHERE id = ? AND status = 'Open'"
        );

        $stmt->execute([
            (int)$_SESSION['admin_id'],
            $sessionId
        ]);

        $pdo->commit();

        $_SESSION['flash'] = 'Queue closed successfully. Unfinished tickets were cancelled.';

    // CALL NEXT
    } elseif ($action === 'call_next') {

        $pdo->beginTransaction();

        $session = $pdo->query(
            "SELECT id FROM queue_sessions
             WHERE status = 'Open'
             LIMIT 1 FOR UPDATE"
        )->fetch();

        if (!$session) {
            throw new RuntimeException('The queue is currently closed.');
        }

        $sessionId = (int)$session['id'];

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM queue_records
             WHERE session_id = ? AND status = 'Serving'"
        );

        $stmt->execute([$sessionId]);

        if ((int)$stmt->fetchColumn() > 0) {
            throw new RuntimeException(
                'Complete or skip the currently serving student before calling the next number.'
            );
        }

        $stmt = $pdo->prepare(
            "SELECT id FROM queue_records
             WHERE session_id = ?
             AND status = 'Waiting'
             ORDER BY created_at ASC, id ASC
             LIMIT 1 FOR UPDATE"
        );

        $stmt->execute([$sessionId]);
        $next = $stmt->fetch();

        if (!$next) {
            throw new RuntimeException('There are no waiting students.');
        }

        $stmt = $pdo->prepare(
            "UPDATE queue_records
             SET status = 'Serving', called_at = NOW()
             WHERE id = ? AND session_id = ?"
        );

        $stmt->execute([(int)$next['id'], $sessionId]);

        $pdo->commit();

        $_SESSION['flash'] = 'Next student called.';

    // COMPLETE, SKIP, OR REQUEUE
    } elseif (in_array($action, ['complete', 'skip', 'requeue'], true) && $id) {

        $pdo->beginTransaction();

        $session = $pdo->query(
            "SELECT id FROM queue_sessions
             WHERE status = 'Open'
             LIMIT 1 FOR UPDATE"
        )->fetch();

        if (!$session) {
            throw new RuntimeException('The queue is currently closed.');
        }

        $sessionId = (int)$session['id'];

        if ($action === 'complete') {

            $stmt = $pdo->prepare(
                "UPDATE queue_records
                 SET status = 'Completed', completed_at = NOW()
                 WHERE id = ? AND session_id = ? AND status = 'Serving'"
            );

            $stmt->execute([$id, $sessionId]);

            $_SESSION['flash'] = $stmt->rowCount()
                ? 'Transaction marked completed.'
                : 'Only a currently serving queue can be completed.';

        } elseif ($action === 'skip') {

            $stmt = $pdo->prepare(
                "UPDATE queue_records
                 SET status = 'Skipped', skipped_at = NOW()
                 WHERE id = ? AND session_id = ?
                 AND status IN ('Waiting', 'Serving')"
            );

            $stmt->execute([$id, $sessionId]);

            $_SESSION['flash'] = $stmt->rowCount()
                ? 'Queue marked as skipped.'
                : 'That queue can no longer be skipped.';

        } else {

            $stmt = $pdo->prepare(
                "UPDATE queue_records
                 SET status = 'Waiting',
                     created_at = NOW(6),
                     called_at = NULL,
                     skipped_at = NULL
                 WHERE id = ? AND session_id = ?
                 AND status = 'Skipped'"
            );

            $stmt->execute([$id, $sessionId]);

            $_SESSION['flash'] = $stmt->rowCount()
                ? 'Student added to the end of the waiting queue.'
                : 'Only skipped queues can be requeued.';
        }

        $pdo->commit();

    } else {
        throw new RuntimeException('Invalid queue action.');
    }

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['flash'] = $e instanceof RuntimeException
        ? $e->getMessage()
        : 'Something went wrong while updating the queue.';
}

header('Location: dashboard.php');
exit;
