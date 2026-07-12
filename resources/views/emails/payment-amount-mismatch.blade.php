<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a; line-height: 1.6; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h1 style="color: #b91c1c; font-size: 20px;">PayPal-Betragsabweichung — Bestellung #{{ $order->id }}</h1>

    <p>
        Der bei PayPal eingezogene Betrag weicht vom Bestellwert ab. Die
        Bestellung wurde auf <strong>fehlgeschlagen</strong> gesetzt, das Geld
        ist aber <strong>bereits eingezogen</strong> — bitte im PayPal-Konto
        prüfen und manuell erstatten oder korrigieren.
    </p>

    <table style="border-collapse: collapse; width: 100%; margin: 16px 0;">
        <tr>
            <td style="padding: 6px 12px 6px 0; color: #555;">Bestellwert</td>
            <td style="padding: 6px 0;"><strong>{{ number_format((float) $order->total_amount, 2, ',', '.') }} €</strong></td>
        </tr>
        <tr>
            <td style="padding: 6px 12px 6px 0; color: #555;">Eingezogener Betrag</td>
            <td style="padding: 6px 0;"><strong style="color: #b91c1c;">{{ number_format((float) $capturedAmount, 2, ',', '.') }} €</strong></td>
        </tr>
        <tr>
            <td style="padding: 6px 12px 6px 0; color: #555;">PayPal-Order</td>
            <td style="padding: 6px 0;">{{ $payment->paypal_order_id }}</td>
        </tr>
    </table>

    <p>
        <a href="{{ $adminUrl }}" style="color: #1a6bbf;">Bestellung im Admin öffnen</a>
    </p>
</body>
</html>
