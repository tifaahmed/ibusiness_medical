<?php

namespace App\Support;

use App\Enums\Order\PaymentTypeEnum;
use App\Models\MembershipSubscription;
use App\Models\Setting;
use App\Services\Paymob\PaymobClient;

/**
 * Which ways of paying are SHOWN to customers, switched on and off at /admin/setting.
 *
 * One list serves every client — the website, the mobile app and the QR card
 * page — for shop orders AND membership subscriptions, so hiding a method here
 * hides it everywhere at once:
 *
 *   payment_paymob_card_enabled    pay online with Paymob by bank card
 *   payment_paymob_wallet_enabled  pay online with Paymob from a mobile wallet
 *                                  (Vodafone Cash…). Each is its own Paymob
 *                                  integration, so each can be shown alone.
 *   payment_cod_enabled      cash on delivery
 *   payment_wallet_enabled   wallet transfer with a receipt (orders) /
 *                            Vodafone Cash with a receipt (subscriptions)
 *   payment_instapay_enabled Instapay with a receipt (subscriptions)
 *
 * A switch only HIDES. Paymob additionally needs its gateway keys and the
 * integration id of that channel, and the
 * wallet transfer needs the shop wallet number, or there is nowhere for the
 * money to go. The same list is enforced when an order or a payment is
 * started, so a stale client cannot use a method that has been hidden.
 */
final class PaymentMethods
{
    public const ONLINE_CARD = 'payment_paymob_card_enabled';

    public const ONLINE_WALLET = 'payment_paymob_wallet_enabled';

    public const COD = 'payment_cod_enabled';

    public const WALLET = 'payment_wallet_enabled';

    public const INSTAPAY = 'payment_instapay_enabled';

    /** What a fresh install shows: Paymob only. Anything else is opened by hand. */
    private const DEFAULTS = [
        self::ONLINE_CARD => true,
        self::ONLINE_WALLET => true,
        self::COD => false,
        self::WALLET => false,
        self::INSTAPAY => false,
    ];

    private static function on(string $slug): bool
    {
        return (bool) SiteSettings::get($slug, self::DEFAULTS[$slug]);
    }

    /** Paying online by bank card: switched on AND Paymob has a card integration. */
    public static function onlineCard(): bool
    {
        return self::on(self::ONLINE_CARD) && PaymobClient::channelConfigured(PaymobClient::CHANNEL_CARD);
    }

    /** Paying online from a mobile wallet: switched on AND Paymob has a wallet integration. */
    public static function onlineWallet(): bool
    {
        return self::on(self::ONLINE_WALLET) && PaymobClient::channelConfigured(PaymobClient::CHANNEL_WALLET);
    }

    /** Any way of paying online. */
    public static function online(): bool
    {
        return self::onlineCard() || self::onlineWallet();
    }

    /**
     * The online channels on offer, card first.
     *
     * @return list<string>
     */
    public static function onlineChannels(): array
    {
        return array_values(array_filter([
            self::onlineCard() ? PaymobClient::CHANNEL_CARD : null,
            self::onlineWallet() ? PaymobClient::CHANNEL_WALLET : null,
        ]));
    }

    /** Whether a client's `paymob_channel` (absent = "whatever is on") may be used now. */
    public static function channelAllowed(?string $channel): bool
    {
        return $channel === null || $channel === ''
            ? self::online()
            : in_array($channel, self::onlineChannels(), true);
    }

    public static function cod(): bool
    {
        return self::on(self::COD);
    }

    /** Wallet transfer for orders: on, and a wallet number to send to. */
    public static function walletTransfer(): bool
    {
        return self::on(self::WALLET) && ShopDelivery::wallet() !== null;
    }

    public static function instapay(): bool
    {
        return self::on(self::INSTAPAY);
    }

    /**
     * The payment types a shop order may use now.
     *
     * @return list<string>
     */
    public static function orderTypes(): array
    {
        return array_values(array_filter([
            self::online() ? PaymentTypeEnum::PAYMOB->value : null,
            self::cod() ? PaymentTypeEnum::COD->value : null,
            self::walletTransfer() ? PaymentTypeEnum::TRANSFER_WALLET->value : null,
        ]));
    }

    /**
     * The methods a membership may be paid with now. Paymob also needs a price.
     *
     * @return list<string>
     */
    public static function subscriptionMethods(): array
    {
        return array_values(array_filter([
            MembershipPlan::canPayOnline() ? MembershipSubscription::METHOD_PAYMOB : null,
            self::cod() ? MembershipSubscription::METHOD_COD : null,
            self::on(self::WALLET) ? MembershipSubscription::METHOD_VODAFONE : null,
            self::instapay() ? MembershipSubscription::METHOD_INSTAPAY : null,
        ]));
    }

    /**
     * What the shop endpoints hand to clients: draw exactly these.
     *
     * `online` is "any online way"; `online_card` / `online_wallet` are the
     * two Paymob buttons; `wallet` is the manual wallet transfer.
     *
     * @return array{online: bool, online_card: bool, online_wallet: bool, cod: bool, wallet: bool, instapay: bool}
     */
    public static function flags(): array
    {
        return [
            'online' => self::online(),
            'online_card' => self::onlineCard(),
            'online_wallet' => self::onlineWallet(),
            'cod' => self::cod(),
            'wallet' => self::walletTransfer(),
            'instapay' => self::instapay(),
        ];
    }

    /** @return array<int, array{slug: string, name: array<string, string>, value: string, value_type: string}> */
    public static function seedRows(): array
    {
        $row = fn (string $slug, string $en, string $ar) => [
            'slug' => $slug,
            'name' => ['en' => $en, 'ar' => $ar],
            'value' => self::DEFAULTS[$slug] ? '1' : '0',
            'value_type' => Setting::TYPE_BOOLEAN,
        ];

        return [
            $row(self::ONLINE_CARD, 'Show: pay online by bank card (Paymob)', 'إظهار: الدفع الإلكتروني بالبطاقة البنكية (Paymob)'),
            $row(self::ONLINE_WALLET, 'Show: pay online from a mobile wallet (Paymob, Vodafone Cash…)', 'إظهار: الدفع الإلكتروني بالمحفظة (Paymob، فودافون كاش…)'),
            $row(self::COD, 'Show: cash on delivery', 'إظهار: الدفع عند الاستلام'),
            $row(self::WALLET, 'Show: wallet transfer / Vodafone Cash with receipt', 'إظهار: تحويل محفظة / فودافون كاش مع إيصال'),
            $row(self::INSTAPAY, 'Show: Instapay with receipt (memberships)', 'إظهار: إنستاباي مع إيصال (الاشتراكات)'),
        ];
    }
}
