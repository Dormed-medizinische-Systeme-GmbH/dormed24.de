<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neue Bestellung #{{ $order->id }}</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;color:#111827;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:32px 16px;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;">

    <tr>
        <td style="padding:0 0 24px 0;" align="center">
            <img src="https://dormed24.de/media/5d/8f/b3/1741900744/dormed24-logo.svg?ts=1741900744" alt="dormed24" height="36" style="height:36px;display:block;">
        </td>
    </tr>

    <tr>
        <td style="background:#ffffff;border-radius:8px;border:1px solid #e5e7eb;overflow:hidden;">
            <table width="100%" cellpadding="0" cellspacing="0">

                <tr>
                    <td style="padding:20px 24px;border-bottom:1px solid #f3f4f6;">
                        <p style="margin:0;font-size:13px;color:#6b7280;">Bestellnummer</p>
                        <p style="margin:4px 0 0;font-size:18px;font-weight:700;color:#111827;">#{{ $order->id }}</p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:16px 24px;border-bottom:1px solid #f3f4f6;background:#f9fafb;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="font-size:13px;font-weight:600;color:#374151;padding-bottom:6px;">Kunde</td>
                            </tr>
                            <tr>
                                <td style="font-size:13px;color:#111827;">{{ $customer->name }}</td>
                            </tr>
                            <tr>
                                <td style="font-size:13px;color:#6b7280;">{{ $customer->email }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:20px 24px 0;">
                        <p style="margin:0 0 16px;font-size:14px;font-weight:600;color:#111827;">Bestellübersicht</p>
                    </td>
                </tr>

                @foreach($cart['items'] as $i => $item)
                <tr>
                    <td style="padding:0 24px;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="{{ $i > 0 ? 'border-top:1px solid #f3f4f6;' : '' }}padding:12px 0;">
                            <tr>
                                <td width="48" valign="top">
                                    <div style="width:44px;height:44px;background:#f3f4f6;border-radius:4px;border:1px solid #e5e7eb;"></div>
                                </td>
                                <td style="padding:0 12px;" valign="top">
                                    <p style="margin:0;font-size:14px;font-weight:600;color:#111827;">{{ $item['name'] }}</p>
                                    <p style="margin:2px 0 0;font-size:12px;color:#9ca3af;">Nr. {{ $item['product_number'] }}</p>
                                </td>
                                <td align="right" valign="top" style="white-space:nowrap;">
                                    <p style="margin:0;font-size:14px;font-weight:600;color:#111827;">{{ number_format((float) $item['line_total'], 2, ',', '.') }} €</p>
                                    <p style="margin:2px 0 0;font-size:12px;color:#9ca3af;">{{ $item['quantity'] }} × {{ number_format((float) $item['unit_price'], 2, ',', '.') }} €</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                @endforeach

                <tr><td style="padding:8px 24px 0;"><div style="border-top:1px solid #e5e7eb;"></div></td></tr>

                <tr>
                    <td style="padding:16px 24px;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding:4px 0;font-size:14px;color:#6b7280;">Zwischensumme</td>
                                <td align="right" style="padding:4px 0;font-size:14px;color:#374151;">{{ number_format((float) $cart['subtotal'], 2, ',', '.') }} €</td>
                            </tr>
                            <tr>
                                <td style="padding:4px 0;font-size:14px;color:#6b7280;">Versandkosten{{ filled($cart['shipping_method']) ? ' – '.$cart['shipping_method'] : '' }}</td>
                                <td align="right" style="padding:4px 0;font-size:14px;color:#374151;">{{ number_format((float) $cart['shipping_total'], 2, ',', '.') }} €</td>
                            </tr>
                            <tr>
                                <td style="padding:4px 0;font-size:14px;color:#6b7280;">zzgl. {{ $cart['vat_rate'] }} % MwSt.</td>
                                <td align="right" style="padding:4px 0;font-size:14px;color:#374151;">{{ number_format((float) $cart['vat_amount'], 2, ',', '.') }} €</td>
                            </tr>
                            <tr>
                                <td style="padding-top:12px;border-top:1px solid #e5e7eb;font-size:15px;font-weight:700;color:#111827;">Gesamt</td>
                                <td align="right" style="padding-top:12px;border-top:1px solid #e5e7eb;font-size:15px;font-weight:700;color:#111827;">{{ number_format((float) $cart['total'], 2, ',', '.') }} €</td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr><td><div style="border-top:1px solid #e5e7eb;"></div></td></tr>

                <tr>
                    <td style="padding:20px 24px;" align="center">
                        
                        <a href="{{ $adminUrl }}"
                           style="display:inline-block;background:#111827;color:#ffffff;font-size:13px;font-weight:600;text-decoration:none;padding:10px 24px;border-radius:6px;">
                            Zum Admin-Dashboard →
                        </a>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</td></tr>
</table>

</body>
</html>
