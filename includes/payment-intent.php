<?php
declare(strict_types=1);

// Shared payment-intent signing for DevOrbit.
//
// Used by payments/create-intent.php (the normal Pay flow) and
// payments/recover.php (recovery of an already-submitted transaction, where
// the browser never handles the intent or attestation by hand). The HMAC
// secret never leaves the server and no token value is ever logged.
//
// Token format (mirrored by the Next.js signer in lib/openbounty-intent.ts):
//   base64url(UTF-8 JSON payload) . "." . hex(HMAC_SHA256(secret, encoded))

// The secret path can be configured per environment.
// Keep the existing Laragon path as the default.
function ob_payment_secret_path(): string
{
    $configured = getenv('OPENBOUNTY_PAYMENT_SECRET_FILE');

    return is_string($configured) && $configured !== ''
        ? $configured
        : 'C:\\laragon\\secrets\\openbounty-payment.secret';
}

function ob_payment_intent_secret(): string
{
    $secret = trim((string)@file_get_contents(ob_payment_secret_path()));

    if (strlen($secret) < 40) {
        throw new RuntimeException('Payment signing secret is unavailable.');
    }

    return $secret;
}

/**
 * @param array{payment_id:int,bounty_id:int,submission_id:int,payer_id:int,from:string,to:string,amount:string} $fields
 */
function ob_mint_payment_intent(array $fields): string
{
    $payload = [
        'payment_id' => (int)$fields['payment_id'],
        'bounty_id' => (int)$fields['bounty_id'],
        'submission_id' => (int)$fields['submission_id'],
        'payer_id' => (int)$fields['payer_id'],
        'from' => (string)$fields['from'],
        'to' => (string)$fields['to'],
        'amount' => (string)$fields['amount'],
        'network' => 'stellar_testnet',
        'exp' => time() + 600,
    ];

    if (!preg_match('/^[0-9]+\.[0-9]{7}$/', $payload['amount'])) {
        throw new RuntimeException('Invalid payment intent amount.');
    }

    $encoded = rtrim(
        strtr(
            base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            '+/',
            '-_'
        ),
        '='
    );

    return $encoded . '.' . hash_hmac('sha256', $encoded, ob_payment_intent_secret());
}
