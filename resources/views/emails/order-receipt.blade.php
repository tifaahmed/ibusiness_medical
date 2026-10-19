<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt {{ $order->order_code }}</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;line-height:1.5;">
@php
    $money = fn ($v) => number_format((float) $v, 2).' EGP';
    $paid = $order->payment_status === \App\Enums\Order\PaymentStatusEnum::ACCEPTED;
    $name = fn ($line) => is_array($line->name) ? ($line->name['en'] ?? reset($line->name)) : $line->name;
@endphp
<div style="max-width:600px;margin:0 auto;background:#fff;">
    <div style="background:#5B2C8F;padding:28px 24px;text-align:center;">
        <h1 style="margin:0;color:#F1C13E;font-size:24px;">Thank you for your purchase</h1>
        <p style="margin:6px 0 0;color:#fff;font-size:14px;">Order <strong>{{ $order->order_code }}</strong> · {{ $order->created_at?->format('Y-m-d H:i') }}</p>
    </div>
    <div style="padding:24px;">
        <p>Hello {{ $order->customer_full_name }},</p>
        <p>
            @if ($paid)
                We received your payment. Here is your receipt.
            @elseif ($order->payment_type === \App\Enums\Order\PaymentTypeEnum::COD)
                We received your order. You pay in cash when it arrives.
            @else
                Here are the details of your order.
            @endif
        </p>
        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;">
            <tr style="background:#f3f0f8;">
                <th align="left" style="padding:8px;">Item</th>
                <th align="center" style="padding:8px;">Qty</th>
                <th align="right" style="padding:8px;">Total</th>
            </tr>
            @foreach ($order->products as $line)
                <tr>
                    <td style="padding:8px;border-bottom:1px solid #eee;">{{ $name($line) }}</td>
                    <td align="center" style="padding:8px;border-bottom:1px solid #eee;">{{ $line->quantity }}</td>
                    <td align="right" style="padding:8px;border-bottom:1px solid #eee;">{{ $money($line->line_total) }}</td>
                </tr>
            @endforeach
            @if ((float) $order->delivery_price > 0)
                <tr><td colspan="2" style="padding:8px;">Delivery</td><td align="right" style="padding:8px;">{{ $money($order->delivery_price) }}</td></tr>
            @endif
            @if ((float) $order->cod_fee > 0)
                <tr><td colspan="2" style="padding:8px;">Cash on delivery fee</td><td align="right" style="padding:8px;">{{ $money($order->cod_fee) }}</td></tr>
            @endif
            <tr>
                <td colspan="2" style="padding:10px 8px;font-weight:bold;border-top:2px solid #5B2C8F;">Total</td>
                <td align="right" style="padding:10px 8px;font-weight:bold;border-top:2px solid #5B2C8F;">{{ $money($order->total_amount) }}</td>
            </tr>
            @if ($paid)
                <tr><td colspan="2" style="padding:4px 8px;color:#047857;">Paid online</td><td align="right" style="padding:4px 8px;color:#047857;">{{ $money($order->total_paid) }}</td></tr>
            @endif
        </table>
        <p style="font-size:14px;margin-top:20px;">
            <strong>Payment:</strong> {{ $order->payment_type->label() }}<br>
            @if ($paid && $order->paymob_transaction_id)<strong>Transaction:</strong> {{ $order->paymob_transaction_id }}<br>@endif
            <strong>Delivering to:</strong> {{ $order->customer_address }}
        </p>
        <p style="font-size:12px;color:#777;">Keep this email — your order code {{ $order->order_code }} is how you track the order.</p>
    </div>
</div>
</body>
</html>
