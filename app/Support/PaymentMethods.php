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
 * Each method also has a switch PER CONTEXT — where it is offered: shop `products`, `cards` (physical card orders) and
 * `membership` (buying a subscription). A method shows in a context only when its main switch AND that context's switch
 * are on (`payment_<context>_<method>_enabled`, all on by default, so nothing changes until the admin turns one off).
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

    /** Where a payment method can be offered. */
    public const CONTEXT_PRODUCTS = 'products';

    public const CONTEXT_CARDS = 'cards';

    public const CONTEXT_MEMBERSHIP = 'membership';

    public const CONTEXTS = [self::CONTEXT_PRODUCTS, self::CONTEXT_CARDS, self::CONTEXT_MEMBERSHIP];

    /** The context's own switch of each method: main switch slug => the middle part of `payment_<context>_<part>_enabled`. */
    private const PARTS = [
        self::ONLINE_CARD => 'paymob_card',
        self::ONLINE_WALLET => 'paymob_wallet',
        self::COD => 'cod',
        self::WALLET => 'wallet',
        self::INSTAPAY => 'instapay',
    ];

    /** The context an order belongs to: a physical-card order is `cards`, any other is a shop order. */
    public static function contextOfOrder(\App\Models\Order $order): string
    {
        return $order->source === PhysicalCard::SOURCE ? self::CONTEXT_CARDS : self::CONTEXT_PRODUCTS;
    }

    public static function contextSlug(string $context, string $mainSlug): string
    {
        return 'payment_'.$context.'_'.self::PARTS[$mainSlug].'_enabled';
    }

    /** The method's main switch is on AND (given a context) that context shows it too. */
    private static function shown(string $slug, ?string $context): bool
    {
        return self::on($slug) && ($context === null || (bool) SiteSettings::get(self::contextSlug($context, $slug), true));
    }

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
    public static function onlineCard(?string $context = null): bool
    {
        return self::shown(self::ONLINE_CARD, $context) && PaymobClient::channelConfigured(PaymobClient::CHANNEL_CARD);
    }

    /** Paying online from a mobile wallet: switched on AND Paymob has a wallet integration. */
    public static function onlineWallet(?string $context = null): bool
    {
        return self::shown(self::ONLINE_WALLET, $context) && PaymobClient::channelConfigured(PaymobClient::CHANNEL_WALLET);
    }

    /** Any way of paying online. */
    public static function online(?string $context = null): bool
    {
        return self::onlineCard($context) || self::onlineWallet($context);
    }

    /**
     * The online channels on offer, card first.
     *
     * @return list<string>
     */
    public static function onlineChannels(?string $context = null): array
    {
        return array_values(array_filter([
            self::onlineCard($context) ? PaymobClient::CHANNEL_CARD : null,
            self::onlineWallet($context) ? PaymobClient::CHANNEL_WALLET : null,
        ]));
    }

    /** Whether a client's `paymob_channel` (absent = "whatever is on") may be used now. */
    public static function channelAllowed(?string $channel, ?string $context = null): bool
    {
        return $channel === null || $channel === ''
            ? self::online($context)
            : in_array($channel, self::onlineChannels($context), true);
    }

    public static function cod(?string $context = null): bool
    {
        return self::shown(self::COD, $context);
    }

    /** Wallet transfer for orders: on, and a wallet number to send to. */
    public static function walletTransfer(?string $context = null): bool
    {
        return self::shown(self::WALLET, $context) && ShopDelivery::wallet() !== null;
    }

    public static function instapay(?string $context = null): bool
    {
        return self::shown(self::INSTAPAY, $context);
    }

    /**
     * The payment types a shop order may use now.
     *
     * @return list<string>
     */
    public static function orderTypes(?string $context = null): array
    {
        return array_values(array_filter([
            self::online($context) ? PaymentTypeEnum::PAYMOB->value : null,
            self::cod($context) ? PaymentTypeEnum::COD->value : null,
            self::walletTransfer($context) ? PaymentTypeEnum::TRANSFER_WALLET->value : null,
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
            self::cod(self::CONTEXT_MEMBERSHIP) ? MembershipSubscription::METHOD_COD : null,
            self::shown(self::WALLET, self::CONTEXT_MEMBERSHIP) ? MembershipSubscription::METHOD_VODAFONE : null,
            self::instapay(self::CONTEXT_MEMBERSHIP) ? MembershipSubscription::METHOD_INSTAPAY : null,
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
    public static function flags(?string $context = null): array
    {
        return [
            'online' => self::online($context),
            'online_card' => self::onlineCard($context),
            'online_wallet' => self::onlineWallet($context),
            'cod' => self::cod($context),
            'wallet' => self::walletTransfer($context),
            'instapay' => self::instapay($context),
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

        $names = [
            self::CONTEXT_PRODUCTS => ['Products (shop)', 'المنتجات (المتجر)'],
            self::CONTEXT_CARDS => ['Physical card orders', 'طلب الكارت الفيزيكال'],
            self::CONTEXT_MEMBERSHIP => ['Buying a membership', 'شراء العضوية'],
        ];
        $methods = [
            self::ONLINE_CARD => ['bank card (Paymob)', 'البطاقة البنكية (Paymob)'],
            self::ONLINE_WALLET => ['mobile wallet (Paymob)', 'المحفظة الإلكترونية (Paymob)'],
            self::COD => ['cash on delivery', 'الدفع عند الاستلام'],
            self::WALLET => ['wallet transfer / Vodafone Cash', 'تحويل محفظة / فودافون كاش'],
            self::INSTAPAY => ['Instapay', 'إنستاباي'],
        ];
        $perContext = [];

        foreach (self::CONTEXTS as $context) {
            foreach ($methods as $main => [$methodEn, $methodAr]) {
                $perContext[] = [
                    'slug' => self::contextSlug($context, $main),
                    'name' => ['en' => "{$names[$context][0]} — show {$methodEn}", 'ar' => "{$names[$context][1]} — إظهار {$methodAr}"],
                    'value' => '1',
                    'value_type' => Setting::TYPE_BOOLEAN,
                ];
            }
        }

        return [
            ...$perContext,
            $row(self::ONLINE_CARD, 'Show: pay online by bank card (Paymob)', 'إظهار: الدفع الإلكتروني بالبطاقة البنكية (Paymob)'),
            $row(self::ONLINE_WALLET, 'Show: pay online from a mobile wallet (Paymob, Vodafone Cash…)', 'إظهار: الدفع الإلكتروني بالمحفظة (Paymob، فودافون كاش…)'),
            $row(self::COD, 'Show: cash on delivery', 'إظهار: الدفع عند الاستلام'),
            $row(self::WALLET, 'Show: wallet transfer / Vodafone Cash with receipt', 'إظهار: تحويل محفظة / فودافون كاش مع إيصال'),
            $row(self::INSTAPAY, 'Show: Instapay with receipt (memberships)', 'إظهار: إنستاباي مع إيصال (الاشتراكات)'),
        ];
    }
}
