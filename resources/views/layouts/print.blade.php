<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Impresion')</title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            background: #f3f4f6;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        .print-actions {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 12px;
            gap: 8px;
        }

        .print-button {
            border: 1px solid #111827;
            background: #111827;
            color: #fff;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        .close-button {
            border: 1px solid #d1d5db;
            background: #fff;
            color: #111827;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .print-actions {
                display: none;
            }
        }
    </style>
</head>
<body>
<div class="print-actions">
    <button class="close-button" type="button" onclick="window.close()">Cerrar</button>
    <button class="print-button" type="button" onclick="window.print()">Imprimir</button>
</div>

@yield('content')
</body>
</html>
