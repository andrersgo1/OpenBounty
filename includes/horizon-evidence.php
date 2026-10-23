<?php
declare(strict_types=1);

// On-chain payout proof for Soroban (Paiflow) contract transactions, which
// carry no classic `payment` operations. A Paiflow payout executes as a
// single invoke_host_function operation; the actual recipient transfers
// appear on Horizon as account_credited EFFECTS. This is the same evidence
// standard as the Next.js signer's assessPayoutEffects
// (lib/openbounty-paiflow.ts): the exact native-XLM credits are read from
// the authoritative ledger effects, never from Paiflow PAYOUT event amounts
// (that deployment reports "0" there on purpose).
//
// Rules, all fail-closed:
//   - exactly ONE account_credited/native effect to the winner, amount ==
//     floor(total * 90/100);
//   - exactly ONE account_credited/native effect to the community wallet,
//     amount == total - winner (the 10% remainder);
//   - contract_credited / contract_debited are contract-internal ledger
//     movements, NOT account transfers: they never count (no double count);
//   - duplicate credits, unreadable amounts, or missing credits throw.

require_once __DIR__ . '/attestation.php'; // ob_amount_normalize

function ob_verify_payout_effects(
    array $effects,
    string $expectedWinner,
    string $expectedCommunity,
    string $expectedWinnerAmount,
    string $expectedCommunityAmount
): void {
    $winnerCredits = 0;
    $communityCredits = 0;

    foreach ($effects as $effect) {
        if (!is_array($effect)) {
            continue;
        }
        if (($effect['type'] ?? '') !== 'account_credited' ||
            ($effect['asset_type'] ?? '') !== 'native') {
            continue; // includes contract_credited/contract_debited and fees
        }

        $account = (string)($effect['account'] ?? '');
        $amount = (string)($effect['amount'] ?? '');

        if ($account !== $expectedWinner && $account !== $expectedCommunity) {
            continue; // an unrelated transfer does not fund this bounty
        }

        if (!preg_match('/^[0-9]+\.[0-9]{7}$/', $amount)) {
            throw new RuntimeException(
                'Verification failed: a recipient credit amount is unreadable.'
            );
        }

        if ($account === $expectedWinner) {
            $winnerCredits++;
            if (ob_amount_normalize($amount) !== ob_amount_normalize($expectedWinnerAmount)) {
                throw new RuntimeException(
                    'Verification failed: the on-chain winner credit does not match exactly 90% of the bounty.'
                );
            }
        } else {
            $communityCredits++;
            if (ob_amount_normalize($amount) !== ob_amount_normalize($expectedCommunityAmount)) {
                throw new RuntimeException(
                    'Verification failed: the on-chain community credit does not match exactly 10% of the bounty.'
                );
            }
        }
    }

    if ($winnerCredits !== 1 || $communityCredits !== 1) {
        throw new RuntimeException(
            'Verification failed: the transaction must contain exactly one on-chain 90% winner credit and one 10% community credit with the expected amounts.'
        );
    }
}
