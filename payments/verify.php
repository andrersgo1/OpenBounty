<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/attestation.php';
require_once __DIR__ . '/../includes/horizon-evidence.php';

require_login();

$user = current_user();
$userId = (int)($user['id'] ?? 0);

function ob_verify_page(string $title, string $message, int $status = 200): never
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

// Token validators live in includes/attestation.php (ob_verify_intent,
// ob_verify_attestation, ob_amount_normalize) so the exact production code is
// exercised by the offline regression suite.

function ob_horizon_get(string $url): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Could not initialize Stellar verification.');
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'DevOrbit-Payment-Verifier/1.0',
    ]);

    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($body) || $status < 200 || $status >= 300) {
        throw new RuntimeException(
            $status === 404
                ? 'Stellar Testnet has not found this transaction yet.'
                : 'Could not verify the transaction with Stellar Testnet. Try again later.'
        );
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        throw new RuntimeException('Stellar returned an unreadable verification response.');
    }

    return $data;
}

if ($userId < 1) {
    ob_verify_page('Sign in required', 'Sign in to the payer account before verifying this payment.', 401);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $intentToken = (string)($_GET['intent'] ?? '');
    $txHash = strtolower(trim((string)($_GET['txHash'] ?? '')));
    // Optional Paiflow evidence attestation issued by the payment signer
    // after finality + 90/10 event proof. POST re-verifies every field.
    $attestationToken = trim((string)($_GET['attestation'] ?? ''));

    if ($intentToken === '' || !preg_match('/^[a-f0-9]{64}$/', $txHash)) {
        ob_verify_page('Invalid verification link', 'Return to the payment page and open the verification link again.', 400);
    }

    try {
        $intent = ob_verify_intent($intentToken);
    } catch (Throwable $ex) {
        ob_verify_page('Cannot verify payment', $ex->getMessage(), 400);
    }

    if ($intent['payer_id'] !== $userId) {
        ob_verify_page('Wrong account', 'Sign in with the DevOrbit account that created this bounty.', 403);
    }

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');

    echo '<!doctype html><html lang="en"><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Verify payment - DevOrbit</title>';
    echo '<body style="font-family:system-ui;max-width:720px;margin:40px auto;padding:20px;background:#151329;color:#eeeaff">';
    echo '<h1>Verify Stellar Testnet payment</h1>';
    echo '<p>DevOrbit will independently check the transaction, sender, recipient, native XLM asset, amount, and payment record. A transaction hash by itself is not proof of payment.</p>';
    if ($attestationToken !== '') {
        echo '<p>Paiflow payout evidence is attached. DevOrbit will re-verify the attestation signature, expiry, payment record, transaction hash, and the locally recomputed 90/10 amounts before marking anything paid.</p>';
    }
    echo '<p><strong>Amount:</strong> ' . e($intent['amount']) . ' XLM</p>';
    echo '<p><strong>Recipient:</strong> ' . e($intent['to']) . '</p>';
    echo '<p><strong>Transaction hash:</strong><br>' . e($txHash) . '</p>';
    echo '<form method="post">';
    echo '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
    echo '<input type="hidden" name="intent" value="' . e($intentToken) . '">';
    echo '<input type="hidden" name="txHash" value="' . e($txHash) . '">';
    if ($attestationToken !== '') {
        echo '<input type="hidden" name="attestation" value="' . e($attestationToken) . '">';
    }
    echo '<button style="padding:12px 18px;border:0;border-radius:8px;background:#8b78ff;color:white;font-weight:700" type="submit">Verify transaction</button>';
    echo '</form></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    ob_verify_page('Method not allowed', 'Use the payment verification form.', 405);
}

check_csrf();

try {
    $intentToken = (string)($_POST['intent'] ?? '');
    $txHash = strtolower(trim((string)($_POST['txHash'] ?? '')));

    if (!preg_match('/^[a-f0-9]{64}$/', $txHash)) {
        throw new RuntimeException('Transaction hash format is invalid.');
    }

    $intent = ob_verify_intent($intentToken);

    if ($intent['payer_id'] !== $userId) {
        ob_verify_page('Wrong account', 'Sign in with the DevOrbit account that created this bounty.', 403);
    }

    $horizon = 'https://horizon-testnet.stellar.org';
    $transaction = ob_horizon_get($horizon . '/transactions/' . rawurlencode($txHash));

    if (($transaction['successful'] ?? false) !== true ||
        strtolower((string)($transaction['hash'] ?? '')) !== $txHash ||
        (string)($transaction['source_account'] ?? '') !== $intent['from']) {
        throw new RuntimeException('The transaction failed or its hash/source does not match the payment intent.');
    }

    // Convert the signed seven-decimal XLM amount to exact stroops.
    // Keep the total within PHP's signed integer range before converting.
    $amount = $intent['amount'];

    if (!preg_match('/^([0-9]+)\.([0-9]{7})$/', $amount, $amountParts)) {
        throw new RuntimeException('Payment intent amount must have exactly seven decimal places.');
    }

    $maxWholeXlm = intdiv(PHP_INT_MAX, 10000000);
    $wholeText = ltrim($amountParts[1], '0');
    $wholeText = $wholeText === '' ? '0' : $wholeText;
    $maxWholeText = (string)$maxWholeXlm;

    if (strlen($wholeText) > strlen($maxWholeText) ||
        (strlen($wholeText) === strlen($maxWholeText) && strcmp($wholeText, $maxWholeText) > 0) ||
        ($wholeText === $maxWholeText && (int)$amountParts[2] > (PHP_INT_MAX % 10000000))) {
        throw new RuntimeException('Payment intent amount exceeds the supported integer range.');
    }

    $whole = (int)$wholeText;
    $fraction = (int)$amountParts[2];
    $totalStroops = ($whole * 10000000) + $fraction;

    // Compute floor(total * 90 / 100) without multiplying the full total by 90.
    $winnerStroops = intdiv($totalStroops, 10) * 9
        + intdiv(($totalStroops % 10) * 9, 10);
    $communityStroops = $totalStroops - $winnerStroops;

    if ($winnerStroops <= 0 || $communityStroops <= 0) {
        throw new RuntimeException('The bounty amount is too small to split into two positive payments.');
    }

    $formatStroops = static function (int $stroops): string {
        $whole = intdiv($stroops, 10000000);
        $fraction = str_pad((string)($stroops % 10000000), 7, '0', STR_PAD_LEFT);
        return $whole . '.' . $fraction;
    };

    $expectedWinnerAmount = $formatStroops($winnerStroops);
    $expectedCommunityAmount = $formatStroops($communityStroops);
    // Community treasury wallet. This literal MUST stay identical to
    // COMMUNITY_WALLET in the Next.js signer's lib/openbounty-split.ts;
    // both sides sign and verify against it independently.
    $communityWallet = 'GBAUZRASWWFTCNPCJ5TURWDVTE5AGRHUA6LRR7HS24MCUOBJ7MCWKIJJ';

    if ($intent['to'] === $communityWallet) {
        throw new RuntimeException('Winner and community wallet must be different.');
    }

    $verificationMode = 'horizon_operations';
    $winnerMatches = 0;
    $communityMatches = 0;

    $attestationToken = trim((string)($_POST['attestation'] ?? ''));

    if ($attestationToken !== '') {
        // Paiflow path: the attestation is issued only after the payment
        // signer confirms finality AND decoded RECEIVE/PAYOUT events proving
        // the exact 90/10 split. DevOrbit still re-verifies the signature,
        // expiry, intent linkage, transaction hash, and recomputed amounts.
        ob_verify_attestation(
            $attestationToken,
            $intent,
            $txHash,
            $expectedWinnerAmount,
            $expectedCommunityAmount,
            $communityWallet
        );
        $verificationMode = 'paiflow_attestation';
        $winnerMatches = 1;
        $communityMatches = 1;
    } else {
        $operations = ob_horizon_get(
            $horizon . '/transactions/' . rawurlencode($txHash) . '/operations?limit=200&order=asc'
        );

        foreach (($operations['_embedded']['records'] ?? []) as $operation) {
            if (($operation['type'] ?? '') !== 'payment') {
                continue;
            }

            $source = (string)($operation['source_account'] ?? $transaction['source_account'] ?? '');
            $destination = (string)($operation['to'] ?? '');
            $assetType = (string)($operation['asset_type'] ?? '');
            $amount = (string)($operation['amount'] ?? '');

            if ($source !== $intent['from'] || $assetType !== 'native') {
                continue;
            }

            $normalizedAmount = ob_amount_normalize($amount);

            if ($destination === $intent['to'] &&
                $normalizedAmount === ob_amount_normalize($expectedWinnerAmount)) {
                $winnerMatches++;
            }

            if ($destination === $communityWallet &&
                $normalizedAmount === ob_amount_normalize($expectedCommunityAmount)) {
                $communityMatches++;
            }
        }

        if ($winnerMatches !== 1 || $communityMatches !== 1) {
            // No matching classic payment operations. A Paiflow (Soroban)
            // payout executes as an invoke_host_function contract call, so it
            // has NO payment operations — the actual recipient transfers
            // appear on Horizon as account_credited EFFECTS. Fall back to the
            // authoritative on-chain effects (the same evidence standard the
            // Next.js signer applies before issuing an attestation). This is
            // fail-closed: exactly one native credit per recipient with the
            // exact expected 90/10 amounts is required.
            $effects = ob_horizon_get(
                $horizon . '/transactions/' . rawurlencode($txHash) . '/effects?limit=200&order=asc'
            );
            $effectRecords = $effects['_embedded']['records'] ?? [];
            if (count($effectRecords) >= 200) {
                throw new RuntimeException(
                    'Verification failed: the transaction has too many ledger effects to verify safely.'
                );
            }
            ob_verify_payout_effects(
                $effectRecords,
                $intent['to'],
                $communityWallet,
                $expectedWinnerAmount,
                $expectedCommunityAmount
            );
            $verificationMode = 'horizon_effects';
            $winnerMatches = 1;
            $communityMatches = 1;
        }
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'SELECT p.id, p.bounty_id, p.submission_id, p.payer_id,
                    p.recipient_wallet, p.amount_xlm, p.network, p.status,
                    p.transaction_hash, b.status AS bounty_status,
                    s.status AS submission_status
             FROM payments p
             JOIN bounties b ON b.id = p.bounty_id
             JOIN submissions s ON s.id = p.submission_id
             WHERE p.id = ? AND p.bounty_id = ? AND p.submission_id = ?
             FOR UPDATE'
        );
        $stmt->execute([
            $intent['payment_id'],
            $intent['bounty_id'],
            $intent['submission_id'],
        ]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment ||
            (int)$payment['payer_id'] !== $userId ||
            (int)$payment['payer_id'] !== $intent['payer_id'] ||
            (string)$payment['recipient_wallet'] !== $intent['to'] ||
            (string)$payment['network'] !== 'stellar_testnet' ||
            ob_amount_normalize((string)$payment['amount_xlm']) !== ob_amount_normalize($intent['amount']) ||
            !in_array($payment['submission_status'], ['approved'], true)) {
            throw new RuntimeException('The current DevOrbit payment record does not match this intent.');
        }

        if ($payment['status'] === 'verified' &&
            strtolower((string)$payment['transaction_hash']) === $txHash) {
            $pdo->commit();
            ob_verify_page('Payment already verified', 'This exact transaction was already verified by DevOrbit.');
        }

        if (!in_array($payment['status'], ['pending', 'prepared', 'submitted', 'confirmed'], true)) {
            throw new RuntimeException('This payment record is not eligible for verification.');
        }

        if (!in_array($payment['bounty_status'], ['payment_pending'], true)) {
            throw new RuntimeException('The bounty is no longer waiting for this payment.');
        }

        $update = $pdo->prepare(
            "UPDATE payments
             SET status = 'verified', transaction_hash = ?, verification_json = ?,
                 updated_at = NOW()
             WHERE id = ? AND status IN ('pending','prepared','submitted','confirmed')"
        );

        $verification = json_encode([
            'network' => 'stellar_testnet',
            'verification_mode' => $verificationMode,
            'verified_at' => gmdate('c'),
            'transaction_hash' => $txHash,
            'source_account' => $intent['from'],
            'total_amount_xlm' => $intent['amount'],
            'winner_destination' => $intent['to'],
            'winner_amount_xlm' => $expectedWinnerAmount,
            'community_destination' => $communityWallet,
            'community_amount_xlm' => $expectedCommunityAmount,
            'asset_type' => 'native',
            'verified_winner_payments' => $winnerMatches,
            'verified_community_payments' => $communityMatches,
            'stellar_successful' => true,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $update->execute([$txHash, $verification, $payment['id']]);

        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Payment status changed during verification. Refresh and check the payment record.');
        }

        $bountyUpdate = $pdo->prepare(
            "UPDATE bounties SET status = 'paid' WHERE id = ? AND status = 'payment_pending'"
        );
        $bountyUpdate->execute([$payment['bounty_id']]);

        if ($bountyUpdate->rowCount() !== 1) {
            throw new RuntimeException('Bounty status changed during verification. No verification update was committed.');
        }

        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }

    ob_verify_page('Payment verified', 'Stellar Testnet confirms the matching native XLM transaction. DevOrbit marked the payment verified and the bounty paid.');
} catch (Throwable $ex) {
    ob_verify_page('Payment not verified', $ex->getMessage(), 400);
}
