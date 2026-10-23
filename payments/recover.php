<?php
declare(strict_types=1);

// Recovery for an ALREADY-SUBMITTED bounty payment.
//
// The normal flow ends on the Next.js payment page, which polls the signer
// and then links back here with a signed attestation. If that page session
// was lost (refresh, expired tab, browser restart), the payer lands here
// instead. This endpoint:
//
//   1. requires an authenticated session and a valid CSRF token,
//   2. re-checks that the payment row belongs to this payer and is still
//      eligible (no status is ever changed here),
//   3. mints a FRESH signed payment intent server-side (shared code with
//      create-intent.php) — creating no transaction and touching no funds,
//   4. asks the Next.js signer server-to-server to verify the EXISTING
//      transaction hash and return its short-lived attestation,
//   5. redirects the browser to payments/verify.php with intent + txHash +
//      attestation, where the normal confirmation form runs unchanged.
//
// The intent and attestation are machine-assembled and URL-encoded; the user
// never copies a token by hand. Token values are never logged or echoed.

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/payment-intent.php';

require_login();

$user = current_user();
$userId = (int)($user['id'] ?? 0);

function ob_recover_page(string $title, string $message, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');

    echo '<!doctype html><html lang="en"><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . e($title) . ' - DevOrbit</title>';
    echo '<body style="font-family:system-ui;max-width:720px;margin:40px auto;padding:20px;background:#151329;color:#eeeaff">';
    echo '<h1>' . e($title) . '</h1>';
    echo '<p style="line-height:1.7;overflow-wrap:anywhere">' . e($message) . '</p>';
    echo '<p><a style="color:#b7a8ff" href="/OpenBounty/payments/">Return to DevOrbit payments</a></p>';
    echo '</body></html>';
    exit;
}

function ob_recoverable_payments(int $payerId): array
{
    $stmt = db()->prepare(
        "SELECT p.id, p.bounty_id, p.submission_id, p.amount_xlm, p.status,
                p.transaction_hash, p.recipient_wallet, b.title
         FROM payments p
         JOIN bounties b ON b.id = p.bounty_id
         JOIN submissions s ON s.id = p.submission_id
         WHERE p.payer_id = ?
           AND p.status IN ('pending','prepared','submitted','confirmed')
           AND b.status = 'payment_pending'
           AND s.status = 'approved'
         ORDER BY p.id DESC"
    );
    $stmt->execute([$payerId]);
    return $stmt->fetchAll();
}

function ob_recover_signer_api_base(): string
{
    global $config;
    $parts = parse_url((string)$config['signer_url']);

    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        throw new RuntimeException('The payment signer is not configured.');
    }

    return $parts['scheme'] . '://' . $parts['host']
        . (isset($parts['port']) ? ':' . $parts['port'] : '');
}

// Read-only status lookup against the Next.js signer. The intent token is the
// credential; the response is either {status,txHash} or
// {status:'verified',txHash,attestation}.
function ob_recover_attestation(string $intentToken, string $txHash): array
{
    $url = ob_recover_signer_api_base() . '/api/openbounty/intent-status'
        . '?intent=' . rawurlencode($intentToken)
        . '&txHash=' . rawurlencode($txHash);

    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Could not initialize the recovery request.');
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'DevOrbit-Payment-Recovery/1.0',
    ]);

    $body = curl_exec($ch);
    curl_close($ch);

    if (!is_string($body) || $body === '') {
        throw new RuntimeException(
            'The payment signer did not respond. Confirm the Next.js app is running, then try again.'
        );
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        throw new RuntimeException('The payment signer returned an unreadable response.');
    }

    return $data;
}

if ($userId < 1) {
    ob_recover_page('Sign in required', 'Sign in to the payer account before recovering this payment.', 401);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $payments = ob_recoverable_payments($userId);

    $pageTitle = 'Recover payment';
    require __DIR__ . '/../includes/header.php';
    echo ob_page_head(
        'Recover payment',
        'Resume verification of a payment whose transaction was already submitted. No new transaction is created and no funds move here.',
        '<a class="ob-btn ghost" href="/OpenBounty/payments/">Back to payments</a>'
    );
    ?>
    <?php if (!$payments): ?>
      <?= ob_empty('card', 'Nothing to recover', 'Only payments that are still waiting on an already-submitted transaction appear here.') ?>
    <?php else: ?>
      <div class="ob-stack">
      <?php foreach ($payments as $p): ?>
        <section class="ob-card">
          <h3><?= e((string)$p['title']) ?></h3>
          <p class="ob-muted" style="margin:6px 0 0">
            Payment #<?= (int)$p['id'] ?> · <?= e(xlm($p['amount_xlm'])) ?> XLM ·
            <?= e(ob_label((string)$p['status'])) ?>
          </p>
          <form method="post" action="/OpenBounty/payments/recover.php" style="margin-top:14px">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
            <label class="ob-l" for="tx<?= (int)$p['id'] ?>">Existing transaction hash (64 hex characters, from Stellar Testnet)</label>
            <input class="ob-in" id="tx<?= (int)$p['id'] ?>" name="txHash" required
                   pattern="[a-fA-F0-9]{64}" maxlength="64" style="width:100%"
                   placeholder="23e59e83…"
                   value="<?= e((string)$p['transaction_hash']) ?>">
            <div style="margin-top:12px">
              <button class="ob-btn good" type="submit"
                      data-confirm="Check the existing transaction with the payment signer and continue to verification? This creates no new payment.">
                Check transaction &amp; continue
              </button>
            </div>
          </form>
        </section>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    ob_recover_page('Method not allowed', 'Use the recovery form.', 405);
}

check_csrf();

$paymentId = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT);
$txHash = strtolower(trim((string)($_POST['txHash'] ?? '')));

if (!$paymentId || $paymentId < 1) {
    ob_recover_page('Invalid request', 'Choose a payment to recover.', 400);
}

if (!preg_match('/^[a-f0-9]{64}$/', $txHash)) {
    ob_recover_page('Invalid transaction hash', 'The transaction hash must be exactly 64 hexadecimal characters.', 400);
}

try {
    $stmt = db()->prepare(
        "SELECT p.id, p.bounty_id, p.submission_id, p.payer_id, p.recipient_wallet,
                p.amount_xlm, p.status, b.status AS bounty_status, s.status AS submission_status
         FROM payments p
         JOIN bounties b ON b.id = p.bounty_id
         JOIN submissions s ON s.id = p.submission_id
         WHERE p.id = ? AND p.payer_id = ?
         FOR UPDATE"
    );
    $stmt->execute([$paymentId, $userId]);
    $row = $stmt->fetch();

    if (!$row) {
        throw new RuntimeException('Payment not found for this account.');
    }

    if (!in_array($row['status'], ['pending', 'prepared', 'submitted', 'confirmed'], true)) {
        throw new RuntimeException('This payment record is not eligible for recovery.');
    }

    if ($row['bounty_status'] !== 'payment_pending' || $row['submission_status'] !== 'approved') {
        throw new RuntimeException('The bounty or submission is no longer awaiting this payment.');
    }

    // The intent's `from` must be the payer's SAVED wallet: verify.php later
    // requires the Horizon transaction source to equal it. Read it fresh from
    // the users table — the session snapshot ($_SESSION['user'], set at login)
    // intentionally contains only id/full_name/email/role, so it can never be
    // used here.
    $payerRow = ob_user_row($userId);
    $payerWallet = trim((string)($payerRow['wallet_address'] ?? ''));
    if (!function_exists('ob_valid_stellar_key') || !ob_valid_stellar_key($payerWallet)) {
        throw new RuntimeException('Save a valid Stellar public address on your profile before recovering a payment.');
    }

    $recipientWallet = (string)$row['recipient_wallet'];
    if (!function_exists('ob_valid_stellar_key') || !ob_valid_stellar_key($recipientWallet)) {
        throw new RuntimeException('The recorded recipient wallet is invalid. Manual review is required.');
    }

    // Convert the database DECIMAL to an exact seven-decimal XLM string,
    // exactly as create-intent.php does.
    $amount = number_format((float)$row['amount_xlm'], 7, '.', '');
    if (!preg_match('/^[0-9]+\.[0-9]{7}$/', $amount) || (float)$amount <= 0) {
        throw new RuntimeException('Invalid recorded bounty reward.');
    }

    // Fresh signed intent, created server-side; ids bind it to this exact
    // payment row. No transaction is prepared, signed, or submitted anywhere
    // in this flow.
    $token = ob_mint_payment_intent([
        'payment_id' => (int)$row['id'],
        'bounty_id' => (int)$row['bounty_id'],
        'submission_id' => (int)$row['submission_id'],
        'payer_id' => $userId,
        'from' => $payerWallet,
        'to' => $recipientWallet,
        'amount' => $amount,
    ]);

    $result = ob_recover_attestation($token, $txHash);

    $status = (string)($result['status'] ?? '');
    if ($status === 'pending') {
        // SUCCESS on-chain, but the signer has not collected the deployment's
        // event records for this transaction yet (Paiflow collects events
        // asynchronously, typically 10-20 seconds after ledger close). This
        // is NOT a verification failure: nothing is marked paid, and retrying
        // with the same transaction hash is safe.
        ob_recover_page(
            'Verification still in progress',
            'The transaction is confirmed on Stellar Testnet, but the payment signer has not finished collecting its payout event records yet. This typically takes 10-20 seconds after the transaction closes. Wait a moment and submit the same transaction hash again. Nothing was marked paid and no new payment was created.',
            200
        );
    }

    if ($status === 'verified' &&
        isset($result['attestation']) &&
        is_string($result['attestation']) &&
        $result['attestation'] !== '') {
        // Hand off to the unchanged verification form. Every value is
        // rawurlencode'd and the attestation is re-verified there.
        $destination = '/OpenBounty/payments/verify.php'
            . '?intent=' . rawurlencode($token)
            . '&txHash=' . rawurlencode($txHash)
            . '&attestation=' . rawurlencode($result['attestation']);

        header('Cache-Control: no-store');
        header('Location: ' . $destination, true, 303);
        exit;
    }

    $reason = isset($result['reason']) && is_string($result['reason']) && $result['reason'] !== ''
        ? $result['reason']
        : 'The signer could not confirm payout evidence for this transaction.';

    ob_recover_page(
        'Payment not verified',
        'The payment signer reported: ' . $reason . ' Nothing was marked paid. You can retry with the same transaction hash; no new payment will be created.',
        400
    );
} catch (Throwable $ex) {
    ob_recover_page('Recovery failed', $ex->getMessage(), 400);
}
