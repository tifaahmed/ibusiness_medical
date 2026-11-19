@php
    $ar = $lang === 'ar';
    $t = fn ($a, $e) => $ar ? $a : $e;
    $money = fn ($n) => number_format((float) $n, 2).' '.$t('ج.م', 'EGP');
    $name = function ($line) use ($lang) { $n = (array) $line->getTranslations('name'); return $n[$lang] ?? $n['ar'] ?? $n['en'] ?? ''; };
    $goods = 0; $delivery = 0;
@endphp
<!doctype html>
<html lang="{{ $lang }}" dir="{{ $dir }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $t('فاتورة', 'Invoice') }} {{ $order->order_code }}</title>
<style>
    body{font-family:Tahoma,Arial,sans-serif;color:#111;max-width:820px;margin:24px auto;padding:0 16px}
    h1{font-size:22px;margin:0}h2{font-size:16px;margin:24px 0 8px;padding:6px 10px;background:#f2f2f2;border-radius:6px}
    table{width:100%;border-collapse:collapse;font-size:14px}th,td{padding:7px 8px;border-bottom:1px solid #ddd;text-align:start}
    .num{text-align:end;white-space:nowrap}.meta{color:#555;font-size:13px;line-height:1.7}
    .total td{font-weight:700;border-top:2px solid #111}.bar{display:flex;gap:8px;flex-wrap:wrap;margin:16px 0}
    .bar a,.bar button{padding:8px 14px;border:1px solid #999;border-radius:6px;background:#fff;color:#111;text-decoration:none;font-size:14px;cursor:pointer}
    @media print{.bar{display:none}body{margin:0}}
</style>
</head>
<body>
<div class="bar">
    <button type="button" onclick="window.print()">{{ $t('طباعة / حفظ PDF', 'Print / Save as PDF') }}</button>
    @if (! $whole)
        <a href="{{ route('invoice.show', ['orderCode' => $order->order_code, 'lang' => $lang]) }}">{{ $t('فاتورة الطلب كله', 'Invoice for the whole order') }}</a>
    @elseif ($order->shipments->count() > 1)
        @foreach ($order->shipments as $s)
            <a href="{{ route('invoice.show', ['orderCode' => $order->order_code, 'shipment' => $s->id, 'lang' => $lang]) }}">{{ $s->storeLabel($lang) }}</a>
        @endforeach
    @endif
</div>

<h1>{{ $siteName }} — {{ $t('فاتورة', 'Invoice') }} #{{ $order->order_code }}</h1>
<p class="meta">
    {{ $t('التاريخ', 'Date') }}: {{ $order->created_at?->format('Y-m-d H:i') }}<br>
    {{ $t('العميل', 'Customer') }}: {{ $order->customer_full_name }} — {{ $order->customer_phone }}<br>
    {{ $t('العنوان', 'Address') }}: {{ collect([$order->customer_address, $order->customer_city, $order->customer_governorate])->filter()->implode('، ') }}
</p>

@foreach ($blocks as $block)
    @php $sub = $block['lines']->sum(fn ($l) => (float) $l->line_total); $goods += $sub; $delivery += $block['delivery']; @endphp
    @if ($block['title'])<h2>{{ $block['title'] }}</h2>@endif
    <table>
        <thead><tr><th>{{ $t('المنتج', 'Product') }}</th><th class="num">{{ $t('السعر', 'Price') }}</th><th class="num">{{ $t('الكمية', 'Qty') }}</th><th class="num">{{ $t('الإجمالي', 'Total') }}</th></tr></thead>
        <tbody>
        @foreach ($block['lines'] as $line)
            <tr><td>{{ $name($line) }}</td><td class="num">{{ $money($line->new_price) }}</td><td class="num">{{ $line->quantity }}</td><td class="num">{{ $money($line->line_total) }}</td></tr>
        @endforeach
        <tr><td colspan="3">{{ $t('الشحن', 'Shipping') }}@if($block['free']) ({{ $t('مجاني — تخطّى الحد', 'free — threshold reached') }})@endif</td><td class="num">{{ $money($block['delivery']) }}</td></tr>
        @if (count($blocks) > 1 || ! $whole)
            <tr class="total"><td colspan="3">{{ $t('إجمالي الشحنة', 'Parcel total') }}</td><td class="num">{{ $money($sub + $block['delivery']) }}</td></tr>
        @endif
        </tbody>
    </table>
@endforeach

@if ($whole)
    <h2>{{ $t('الملخص', 'Summary') }}</h2>
    <table><tbody>
        <tr><td>{{ $t('المنتجات', 'Goods') }}</td><td class="num">{{ $money($goods) }}</td></tr>
        <tr><td>{{ $t('الشحن', 'Shipping') }}</td><td class="num">{{ $money($delivery) }}</td></tr>
        @if ((float) $order->cod_fee > 0)<tr><td>{{ $t('رسوم الدفع عند الاستلام', 'Cash on delivery fee') }}</td><td class="num">{{ $money($order->cod_fee) }}</td></tr>@endif
        <tr class="total"><td>{{ $t('الإجمالي', 'Total') }}</td><td class="num">{{ $money($order->total_amount) }}</td></tr>
        @if ((float) $order->wallet_amount > 0)<tr><td>{{ $t('مدفوع من المحفظة', 'Paid from wallet') }}</td><td class="num">{{ $money($order->wallet_amount) }}</td></tr>@endif
        @if ((float) $order->points_amount > 0)<tr><td>{{ $t('خصم النقاط', 'Points discount') }}</td><td class="num">{{ $money($order->points_amount) }}</td></tr>@endif
    </tbody></table>
@endif
</body>
</html>
