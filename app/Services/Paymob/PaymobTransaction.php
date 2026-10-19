<?php

namespace App\Services\Paymob;

/**
 * A Paymob transaction, however it reached us — the `obj` of a processed
 * callback, the flat query of the browser redirect, or an inquiry answer.
 */
final class PaymobTransaction
{
    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const PENDING = 'pending';

    public const REFUNDED = 'refunded';

    public const VOIDED = 'voided';

    /** @param array<string, mixed> $raw */
    public function __construct(
        public readonly ?string $id,
        public readonly bool $success,
        public readonly bool $pending,
        public readonly bool $isRefunded,
        public readonly bool $isVoided,
        public readonly ?int $amountCents,
        public readonly ?string $currency,
        public readonly ?string $paymobOrderId,
        public readonly ?string $merchantOrderId,
        public readonly ?string $parentTransaction,
        public readonly ?string $responseCode,
        public readonly ?string $message,
        public readonly array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $data  nested (callback / inquiry) or flat (browser redirect)
     */
    public static function fromArray(array $data): self
    {
        $get = fn (string ...$keys) => self::pick($data, ...$keys);

        return new self(
            id: self::str($get('id')),
            success: self::bool($get('success')),
            pending: self::bool($get('pending')),
            isRefunded: self::bool($get('is_refunded')),
            isVoided: self::bool($get('is_voided')),
            amountCents: is_numeric($amount = $get('amount_cents')) ? (int) $amount : null,
            currency: self::str($get('currency')),
            paymobOrderId: self::str($get('order.id', 'order_id', 'order')),
            merchantOrderId: self::str($get('order.merchant_order_id', 'merchant_order_id')),
            parentTransaction: self::str($get('parent_transaction')),
            responseCode: self::str($get('data.txn_response_code', 'txn_response_code')),
            message: self::str($get('data.message', 'data_message', 'message')),
            raw: $data,
        );
    }

    /** What this transaction means for the order. */
    public function outcome(): string
    {
        return match (true) {
            $this->isVoided => self::VOIDED,
            $this->isRefunded => self::REFUNDED,
            $this->pending => self::PENDING,
            $this->success => self::PAID,
            default => self::FAILED,
        };
    }

    /** The order code behind `CODE` or a retry's `CODE-2` reference. */
    public function orderCode(): ?string
    {
        if ($this->merchantOrderId === null) {
            return null;
        }

        return preg_replace('/-\d+$/', '', $this->merchantOrderId);
    }

    /** The subscription this transaction pays for, when its reference is a `SUB…` one. */
    public function subscriptionId(): ?int
    {
        return \App\Models\MembershipSubscription::idFromReference($this->merchantOrderId);
    }

    /**
     * A dotted key read from a nested array, or flat as `a.b` / `a_b` — a
     * browser redirect arrives flat, and PHP rewrites dots in query keys.
     *
     * @param  array<string, mixed>  $data
     */
    public static function pick(array $data, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            $value = $data;
            $found = true;

            foreach (explode('.', $key) as $segment) {
                if (is_array($value) && array_key_exists($segment, $value)) {
                    $value = $value[$segment];
                } else {
                    $found = false;
                    break;
                }
            }

            if ($found && ! is_array($value)) {
                return $value;
            }

            foreach ([$key, str_replace('.', '_', $key)] as $flat) {
                if (array_key_exists($flat, $data) && ! is_array($data[$flat])) {
                    return $data[$flat];
                }
            }
        }

        return null;
    }

    private static function bool(mixed $value): bool
    {
        return $value === true || $value === 1 || in_array(strtolower((string) $value), ['true', '1'], true);
    }

    private static function str(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
