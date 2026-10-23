<?php
declare(strict_types=1);

// Shared signed-token verification for DevOrbit payments, extracted from
// payments/verify.php so the exact production validators can be exercised by
// the CLI regression suite (tests/php/attestation-checks.php in the signer
// repo) without a web server or database.
//
// Covers both token kinds that cross the PHP <-> Next.js boundary:
//   - the payment intent (minted here in PHP, verified by the Next.js signer)
//   - the payout attestation (minted by the Next.js signer after finality +
//     90/10 evidence, re-verified here before anything is marked paid)
//
// The optional $secretOverride parameter exists ONLY for the offline test
// suite (dummy secret); production callers omit it and the shared secret file
// is used. Never log or echo token values anywhere.

function ob_verify_secret(?string $secretOverride = null): string
{
    if ($secretOverride !== null) {
        if (strlen($secretOverride) < 40) {
            throw new RuntimeException('Payment verification is not configured.');
        }
        return $secretOverride;
    }

    $secret = trim((string)@file_get_contents(
        'C:\\laragon\\secrets\\openbounty-payment.secret'
    ));

    if (strlen($secret) < 40) {
        throw new RuntimeException('Payment verification is not configured.');
    }

    return $secret;
}

function ob_verify_intent(string $token, ?string $secretOverride = null): array
{
    $secret = ob_verify_secret($secretOverride);

    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        throw new RuntimeException('Invalid payment intent.');
    }

    [$encoded, $providedSignature] = $parts;

    if (!preg_match('/^[A-Za-z0-9_-]+$/', $encoded) ||
        !preg_match('/^[a-f0-9]{64}$/i', $providedSignature)) {
        throw new RuntimeException('Invalid payment intent.');
    }

    $expectedSignature = hash_hmac('sha256', $encoded, $secret);
    if (!hash_equals($expectedSignature, $providedSignature)) {
        throw new RuntimeException('Payment intent signature is invalid.');
    }

    $base64 = strtr($encoded, '-_', '+/');
    $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);
    $decoded = base64_decode($base64, true);

    if ($decoded === false) {
        throw new RuntimeException('Invalid payment intent encoding.');
    }

    $payload = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);

    foreach (['payment_id', 'bounty_id', 'submission_id', 'payer_id'] as $key) {
        if (!isset($payload[$key]) || !is_int($payload[$key]) || $payload[$key] < 1) {
            throw new RuntimeException('Payment intent fields are invalid.');
        }
    }

    if (($payload['network'] ?? null) !== 'stellar_testnet' ||
        !isset($payload['exp']) || !is_int($payload['exp']) ||
        ($payload['exp'] + 2592000) <= time() ||
        !isset($payload['from'], $payload['to'], $payload['amount']) ||
        !preg_match('/^G[A-Z2-7]{55}$/', $payload['from']) ||
        !preg_match('/^G[A-Z2-7]{55}$/', $payload['to']) ||
        !preg_match('/^[0-9]+\.[0-9]{7}$/', $payload['amount']) ||
        (float)$payload['amount'] <= 0) {
        throw new RuntimeException('Payment intent is invalid or expired.');
    }

    return $payload;
}

function ob_amount_normalize(string $amount): string
{
    if (!preg_match('/^([0-9]+)(?:\.([0-9]{1,7}))?$/', $amount, $m)) {
        throw new RuntimeException('Invalid XLM amount returned by Stellar.');
    }

    $whole = ltrim($m[1], '0');
    $whole = $whole === '' ? '0' : $whole;
    $fraction = rtrim($m[2] ?? '', '0');

    return $whole . ($fraction !== '' ? '.' . $fraction : '');
}

// Verifies a short-lived HMAC-signed attestation issued by the Next.js
// payment signer AFTER Paiflow confirms the payout transaction succeeded
// and its decoded RECEIVE/PAYOUT events plus on-chain effects prove the exact
// 90/10 split. This path exists because a Paiflow payout transaction is not
// guaranteed to appear as two plain Horizon payment operations, so the
// operation scan cannot verify it. DevOrbit never trusts the attestation
// alone: every field is re-checked against the intent and the locally
// recomputed split.
function ob_verify_attestation(
    string $token,
    array $intent,
    string $txHash,
    string $expectedWinnerAmount,
    string $expectedCommunityAmount,
    string $communityWallet,
    ?string $secretOverride = null
): void {
    $secret = ob_verify_secret($secretOverride);

    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        throw new RuntimeException('Payment attestation is invalid.');
    }

    [$encoded, $providedSignature] = $parts;

    if (!preg_match('/^[A-Za-z0-9_-]+$/', $encoded) ||
        !preg_match('/^[a-f0-9]{64}$/i', $providedSignature)) {
        throw new RuntimeException('Payment attestation is invalid.');
    }

    $expectedSignature = hash_hmac('sha256', $encoded, $secret);
    if (!hash_equals($expectedSignature, $providedSignature)) {
        throw new RuntimeException('Payment attestation signature is invalid.');
    }

    $base64 = strtr($encoded, '-_', '+/');
    $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);
    $decoded = base64_decode($base64, true);

    if ($decoded === false) {
        throw new RuntimeException('Payment attestation encoding is invalid.');
    }

    $payload = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);

    if (($payload['v'] ?? null) !== 1 ||
        ($payload['network'] ?? null) !== 'stellar_testnet' ||
        !isset($payload['exp']) || !is_int($payload['exp']) ||
        $payload['exp'] <= time()) {
        throw new RuntimeException('Payment attestation is invalid or expired.');
    }

    foreach (['payment_id', 'bounty_id', 'submission_id', 'payer_id'] as $key) {
        if (($payload[$key] ?? null) !== $intent[$key]) {
            throw new RuntimeException('Payment attestation does not match this payment intent.');
        }
    }

    if (strtolower((string)($payload['tx_hash'] ?? '')) !== $txHash) {
        throw new RuntimeException('Payment attestation does not match this transaction hash.');
    }

    if (($payload['community_wallet'] ?? null) !== $communityWallet ||
        !is_string($payload['winner_amount_xlm'] ?? null) ||
        !is_string($payload['community_amount_xlm'] ?? null) ||
        ob_amount_normalize($payload['winner_amount_xlm']) !== ob_amount_normalize($expectedWinnerAmount) ||
        ob_amount_normalize($payload['community_amount_xlm']) !== ob_amount_normalize($expectedCommunityAmount)) {
        throw new RuntimeException('Payment attestation amounts do not match the expected 90/10 split.');
    }
}
