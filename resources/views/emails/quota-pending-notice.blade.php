<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Aviso de cuota pendiente</title>
</head>
<body>
    <p>Estimado/a {{ mb_convert_case($memberName, MB_CASE_TITLE, 'UTF-8') }},</p>

    <p>Le comunicamos que tiene una cuota pendiente de pago correspondiente a:</p>

    <ul>
        <li><strong>Tipo de cuota:</strong> {{ $quotaTypeName }}</li>
        <li><strong>Período:</strong> {{ $periodStart }} - {{ $periodEnd }}</li>
        <li><strong>Importe:</strong> {{ $amount }} EUR</li>
        @if ($dueDate)
            <li><strong>Fecha límite de pago:</strong> {{ $dueDate }}</li>
        @endif
    </ul>

    <p>Adjunto encontrará el aviso de cuota en formato PDF.</p>

    <p>En caso de que ya haya realizado el pago, puede ignorar este mensaje.</p>

    <br>
    <p>Atentamente,<br>
    {{ $associationName }}</p>
</body>
</html>
