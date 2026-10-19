<?php

namespace App\Services\Paymob;

/**
 * Verifies that a callback really came from Paymob.
 *
 * Without this anyone could POST "success" to the callback URL and mark an
 * order paid. SHA-512 over the documented fields, concatenated in the
 * documented order, keyed with the account's HMAC secret.
 */
final class PaymobHmac
{
    /** The order Paymob signs in. Booleans are the strings "true" / "false". */
    private const FIELDS = [
        'amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction',
        'id', 'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded',
        'is_standalone_payment', 'is_voided', 'order.id', 'owner', 'pending',
        'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success',
    ];

    /** @param array<string, mixed> $data nested `obj`, or the flat browser query */
    public static function compute(array $data, string $secret): string
    {
        $message = '';

        foreach (self::FIELDS as $field) {
            $value = PaymobTransaction::pick($data, $field, $field === 'order.id' ? 'order' : $field);
            $message .= is_bool($value) ? ($value ? 'true' : 'false') : (string) ($value ?? '');
        }

        return hash_hmac('sha512', $message, $secret);
    }

    /** @param array<string, mixed> $data */
    public static function valid(array $data, ?string $given, ?string $secret): bool
    {
        if (blank($given) || blank($secret)) {
            return false;
        }

        return hash_equals(self::compute($data, $secret), strtolower($given));
    }
}
