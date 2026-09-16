<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>{{ $heading }}</title>
</head>
<body style="margin:0;padding:24px;background:#f7f6f4;font-family:Arial,Helvetica,sans-serif;color:#1a1918;">
<div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e0ddd8;border-radius:12px;padding:24px;">
    <h1 style="margin:0 0 16px;font-size:20px;">{{ $heading }}</h1>

    <p style="margin:0 0 20px;font-size:15px;line-height:1.5;">{{ $intro }}</p>

    <table style="width:100%;border-collapse:collapse;font-size:14px;">
        <tbody>
        @foreach ($facts as $label => $value)
            <tr>
                <td style="padding:6px 12px 6px 0;color:#6d6963;vertical-align:top;white-space:nowrap;">{{ $label }}</td>
                <td style="padding:6px 0;">{{ $value }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h2 style="margin:24px 0 8px;font-size:16px;">Состав заказа</h2>

    <table style="width:100%;border-collapse:collapse;font-size:14px;">
        <tbody>
        @foreach ($order->items as $item)
            <tr>
                <td style="padding:8px 12px 8px 0;border-bottom:1px solid #efedea;">{{ $item->product_name }}</td>
                <td style="padding:8px 12px 8px 0;border-bottom:1px solid #efedea;white-space:nowrap;color:#6d6963;">
                    {{ $item->quantity }} шт.
                </td>
                <td style="padding:8px 0;border-bottom:1px solid #efedea;text-align:right;white-space:nowrap;">
                    {{ $money($item->total_minor) }}
                </td>
            </tr>
        @endforeach
        @if ($order->delivery_cost_minor > 0)
            <tr>
                <td style="padding:8px 12px 8px 0;border-bottom:1px solid #efedea;" colspan="2">
                    Доставка — {{ $order->delivery_method->label() }}
                </td>
                <td style="padding:8px 0;border-bottom:1px solid #efedea;text-align:right;white-space:nowrap;">
                    {{ $money($order->delivery_cost_minor) }}
                </td>
            </tr>
        @endif
        <tr>
            <td style="padding:12px 12px 0 0;font-weight:bold;" colspan="2">Итого</td>
            <td style="padding:12px 0 0;text-align:right;font-weight:bold;white-space:nowrap;">
                {{ $money($order->total_minor) }}
            </td>
        </tr>
        </tbody>
    </table>

    @if ($order->comment)
        <p style="margin:20px 0 0;font-size:14px;color:#6d6963;">Комментарий: {{ $order->comment }}</p>
    @endif

    <p style="margin:24px 0 0;font-size:12px;color:#86827b;">{{ $footer }}</p>
</div>
</body>
</html>
