<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo de cuota de socio</title>
</head>
<body>
    <p>Estimado/a {{ mb_convert_case($memberName, MB_CASE_TITLE, 'UTF-8') }},</p>

    <p>Le enviamos su copia del recibo de cuota de socio correspondiente a:</p>

    <ul>
        <li><strong>Tipo de cuota:</strong> {{ $quotaTypeName }}</li>
        <li><strong>Período:</strong> {{ $periodStart }} - {{ $periodEnd }}</li>
        <li><strong>Importe:</strong> {{ $amount }} EUR</li>
        @if ($paidAt)
            <li><strong>Fecha de pago:</strong> {{ $paidAt }}</li>
        @endif
    </ul>

    <p>Adjunto encontrará el recibo en formato PDF para sus archivos.</p>

    <br>
    <p>Atentamente,<br>
    {{ $associationName }}</p>
</body>
</html>
