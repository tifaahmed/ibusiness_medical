<!DOCTYPE html>
<html lang="en" dir="ltr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Membership receipt</title></head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;line-height:1.5;">
@php $membership = $subscription->membership; @endphp
<div style="max-width:600px;margin:0 auto;background:#fff;">
    <div style="background:#5B2C8F;padding:28px 24px;text-align:center;">
        <h1 style="margin:0;color:#F1C13E;font-size:24px;">Your membership is active</h1>
        <p style="margin:6px 0 0;color:#fff;font-size:14px;">Card <strong>{{ $membership?->membership_number }}</strong></p>
    </div>
    <div style="padding:24px;">
        <p>Hello {{ $membership?->user?->name }},</p>
        <p>Thank you — we received your payment. Here is your receipt.</p>
        <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-size:14px;">
            <tr><td style="border-bottom:1px solid #eee;">Amount paid</td><td align="right" style="border-bottom:1px solid #eee;"><strong>{{ number_format((float) $subscription->amount, 2) }} EGP</strong></td></tr>
            <tr><td style="border-bottom:1px solid #eee;">Paid by</td><td align="right" style="border-bottom:1px solid #eee;">{{ str_replace('_', ' ', $subscription->method) }}</td></tr>
            @if ($subscription->paymob_transaction_id)
                <tr><td style="border-bottom:1px solid #eee;">Transaction</td><td align="right" style="border-bottom:1px solid #eee;">{{ $subscription->paymob_transaction_id }}</td></tr>
            @endif
            <tr><td style="border-bottom:1px solid #eee;">Period</td><td align="right" style="border-bottom:1px solid #eee;">{{ $subscription->months }} months</td></tr>
            <tr><td>Valid until</td><td align="right"><strong>{{ $membership?->expiration_date?->format('Y-m-d') }}</strong></td></tr>
        </table>
        <p style="font-size:12px;color:#777;margin-top:20px;">Keep this email as your receipt.</p>
    </div>
</div>
</body>
</html>
