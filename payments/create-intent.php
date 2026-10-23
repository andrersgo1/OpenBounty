<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/payment-intent.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

check_csrf();

function payment_intent_fail(string $message, int $status = 400): never {
    http_response_code($status);
    exit(e($message));
}

try {
    $submissionId = filter_input(INPUT_POST, 'submission_id', FILTER_VALIDATE_INT);

    if (!$submissionId || $submissionId < 1) {
        payment_intent_fail('Invalid submission.');
    }

    $user = current_user();
    $payerId = (int)($user['id'] ?? 0);

    if ($payerId < 1) {
        payment_intent_fail('Please sign in again.', 401);
    }

    // Fail fast before any database write if the signing secret is missing,
    // so a configuration problem cannot leave a half-created payment behind.
    ob_payment_intent_secret();

    $pdo = db();
    $pdo->beginTransaction();

    $query = $pdo->prepare(
        "SELECT
            b.id AS bounty_id,
            b.creator_id,
            b.title,
            b.reward_xlm,
            b.status AS bounty_status,
            s.id AS submission_id,
            s.contributor_id,
            s.status AS submission_status,
            recipient.wallet_address AS recipient_wallet,
            payer.wallet_address AS payer_wallet
         FROM submissions s
         JOIN bounties b ON b.id = s.bounty_id
         JOIN users recipient ON recipient.id = s.contributor_id
         JOIN users payer ON payer.id = b.creator_id
         WHERE s.id = ?
         FOR UPDATE"
    );
    $query->execute([$submissionId]);
    $row = $query->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $pdo->rollBack();
        payment_intent_fail('Submission not found.', 404);
    }

    if ((int)$row['creator_id'] !== $payerId) {
        $pdo->rollBack();
        payment_intent_fail('Only the bounty creator can initiate this payment.', 403);
    }

    if ($row['submission_status'] !== 'approved') {
        $pdo->rollBack();
        payment_intent_fail('The submission must be approved before payment.');
    }

    if (!in_array($row['bounty_status'], ['approved', 'payment_pending'], true)) {
        $pdo->rollBack();
        payment_intent_fail('This bounty is not eligible for payment.');
    }

    if (!function_exists('ob_valid_stellar_key') ||
        !ob_valid_stellar_key((string)$row['payer_wallet']) ||
        !ob_valid_stellar_key((string)$row['recipient_wallet'])) {
        $pdo->rollBack();
        payment_intent_fail(
            'The creator and contributor must each save a valid Stellar public address.'
        );
    }

    // Convert the database DECIMAL to an exact seven-decimal XLM string.
    $amount = number_format((float)$row['reward_xlm'], 7, '.', '');

    if (!preg_match('/^[0-9]+\.[0-9]{7}$/', $amount) ||
        (float)$amount <= 0 ||
        (float)$amount > 922337203685.4775807) {
        $pdo->rollBack();
        payment_intent_fail('Invalid bounty reward.');
    }

    $existingQuery = $pdo->prepare(
        "SELECT id, submission_id, payer_id, recipient_wallet, amount_xlm,
                status, transaction_hash
         FROM payments
         WHERE bounty_id = ?
         FOR UPDATE"
    );
    $existingQuery->execute([(int)$row['bounty_id']]);
    $existing = $existingQuery->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $samePayment =
            (int)$existing['submission_id'] === (int)$row['submission_id'] &&
            (int)$existing['payer_id'] === $payerId &&
            hash_equals((string)$existing['recipient_wallet'], (string)$row['recipient_wallet']) &&
            number_format((float)$existing['amount_xlm'], 7, '.', '') === $amount;

        if (!$samePayment) {
            $pdo->rollBack();
            payment_intent_fail('Existing payment details differ. Manual review is required.', 409);
        }

        if ($existing['status'] !== 'pending' || !empty($existing['transaction_hash'])) {
            $pdo->rollBack();
            payment_intent_fail(
                'A payment already exists in a non-retryable state. Check its status before proceeding.',
                409
            );
        }

        $paymentId = (int)$existing['id'];
    } else {
        $insert = $pdo->prepare(
            "INSERT INTO payments
                (bounty_id, submission_id, payer_id, recipient_wallet, amount_xlm, network, status)
             VALUES (?, ?, ?, ?, ?, 'stellar_testnet', 'pending')"
        );
        $insert->execute([
            (int)$row['bounty_id'],
            (int)$row['submission_id'],
            $payerId,
            (string)$row['recipient_wallet'],
            $amount
        ]);
        $paymentId = (int)$pdo->lastInsertId();
    }

    $pdo->prepare(
        "UPDATE bounties SET status = 'payment_pending'
         WHERE id = ? AND status = 'approved'"
    )->execute([(int)$row['bounty_id']]);

    $pdo->commit();

    // Token format and signing are shared with the recovery flow via
    // includes/payment-intent.php. The secret never leaves the server.
    $token = ob_mint_payment_intent([
        'payment_id' => $paymentId,
        'bounty_id' => (int)$row['bounty_id'],
        'submission_id' => (int)$row['submission_id'],
        'payer_id' => $payerId,
        'from' => (string)$row['payer_wallet'],
        'to' => (string)$row['recipient_wallet'],
        'amount' => $amount,
    ]);

    // Origin comes from config (OPENBOUNTY_SIGNER_URL); defaults to the local demo signer.
    $destination = rtrim((string)$config['signer_url'], '/') . '?intent=' . rawurlencode($token);

    header('Cache-Control: no-store');
    header('Location: ' . $destination, true, 303);
    exit;
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('DevOrbit payment intent failed: ' . $error->getMessage());
    payment_intent_fail('Could not prepare the payment. Check the application logs.', 500);
}