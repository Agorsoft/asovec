<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual de usuario · MiAsovec</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.5;
            color: #1a1a2e;
            background: #fff;
        }
        .page { padding: 30px 40px; }

        /* Portada */
        .cover {
            text-align: center;
            padding-top: 120px;
            page-break-after: always;
        }
        .cover h1 {
            font-size: 26pt;
            font-weight: bold;
            color: #0369a1;
            margin-bottom: 12px;
        }
        .cover h2 {
            font-size: 16pt;
            color: #475569;
            font-weight: normal;
            margin-bottom: 40px;
        }
        .cover .version {
            font-size: 9pt;
            color: #94a3b8;
            margin-top: 60px;
        }

        /* Cabecera de sección */
        h2 {
            font-size: 14pt;
            font-weight: bold;
            color: #0369a1;
            border-bottom: 2px solid #0369a1;
            padding-bottom: 4px;
            margin-top: 24px;
            margin-bottom: 10px;
        }
        h3 {
            font-size: 11pt;
            font-weight: bold;
            color: #1e3a5f;
            margin-top: 14px;
            margin-bottom: 6px;
        }

        /* Párrafos */
        p { margin-bottom: 8px; }

        /* Listas */
        ul, ol {
            margin-left: 20px;
            margin-bottom: 8px;
        }
        li { margin-bottom: 3px; }

        /* Código */
        code {
            font-family: 'DejaVu Sans Mono', Courier, monospace;
            font-size: 8.5pt;
            background: #f1f5f9;
            padding: 1px 4px;
            border-radius: 3px;
        }

        /* Índice */
        .toc {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 6px;
            padding: 16px 20px;
            margin-bottom: 20px;
            page-break-after: always;
        }
        .toc h2 { color: #0369a1; border-bottom: 1px solid #7dd3fc; font-size: 12pt; }
        .toc ol { margin-top: 8px; }
        .toc li { margin-bottom: 4px; }

        /* Salto de página antes de cada sección principal */
        section { page-break-inside: avoid; }
        section + section { margin-top: 24px; }

        /* Pie de página */
        .footer {
            margin-top: 40px;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
            font-size: 8pt;
            color: #94a3b8;
            text-align: center;
        }

        strong { font-weight: bold; }
        em { font-style: italic; }
    </style>
</head>
<body>

{{-- PORTADA --}}
<div class="cover">
    <h1>MiAsovec</h1>
    <h2>Manual de usuario</h2>
    <p style="color: #64748b; font-size: 11pt;">Sistema de gestión integral para asociaciones</p>
    <div class="version">Versión {{ now()->format('Y') }}</div>
</div>

<div class="page">

{{-- ÍNDICE --}}
<div class="toc">
    <h2>Índice de contenidos</h2>
    <ol>
        <li>Introducción</li>
        <li>Primeros pasos</li>
        <li>Gestión de socios</li>
        <li>Cuotas</li>
        <li>Gestión de eventos</li>
        <li>Inscripciones</li>
        <li>Contabilidad</li>
        <li>Reportes</li>
        <li>Configuración</li>
    </ol>
</div>

{{-- 1. INTRODUCCIÓN --}}
<section>
    <h2>1. Introducción</h2>
    <p>
        <strong>MiAsovec</strong> es un sistema de gestión integral para asociaciones. Permite administrar socios,
        cuotas, eventos e inscripciones, contabilidad básica, generación de documentos personalizados
        e impresión de etiquetas postales, todo desde una interfaz web accesible desde cualquier dispositivo.
    </p>
    <p>La aplicación está organizada en las siguientes secciones principales accesibles desde el menú lateral:</p>
    <ul>
        <li><strong>Gestión de Socios</strong> — Socios, cuotas y primera importación.</li>
        <li><strong>Gestión de Eventos</strong> — Eventos e inscripciones.</li>
        <li><strong>Contabilidad</strong> — Registro de transacciones.</li>
        <li><strong>Reportes</strong> — Impresión de etiquetas y generación de documentos.</li>
        <li><strong>Configuración</strong> — Datos de la asociación, tipos de cuota y plantillas.</li>
    </ul>
</section>

{{-- 2. PRIMEROS PASOS --}}
<section>
    <h2>2. Primeros pasos</h2>
    <p>
        Cuando acceda por primera vez a la aplicación, se le redirigirá automáticamente al asistente de
        configuración inicial. Siga los pasos para completar los datos básicos de la asociación antes de
        comenzar a usar el resto de funcionalidades.
    </p>

    <h3>2.1 Configurar la asociación</h3>
    <p>
        Acceda a <strong>Configuración → Asociación</strong> para revisar o actualizar los datos en cualquier momento.
        Los campos más importantes son:
    </p>
    <ul>
        <li><strong>Nombre, CIF y dirección</strong> — Aparecen en recibos y documentos generados.</li>
        <li><strong>Métodos de pago aceptados</strong> — Efectivo, transferencia bancaria o domiciliación bancaria (SEPA).</li>
        <li><strong>Periodo de cobro</strong> — Mensual, trimestral, semestral o anual.</li>
        <li><strong>Máscara de número de recibo</strong> — Formato del número correlativo de recibos (ej. <code>{Y:2}-{N:4}</code>).</li>
        <li><strong>Datos bancarios</strong> — Necesarios para emitir mandatos SEPA y adeudos directos.</li>
        <li><strong>Identificador de acreedor SEPA</strong> — Código asignado por el banco para la domiciliación.</li>
    </ul>

    <h3>2.2 Crear tipos de cuota</h3>
    <p>
        Antes de registrar socios, cree al menos un tipo de cuota en
        <strong>Configuración → Tipos de cuota</strong>. Cada tipo define el importe, el periodo de cobro
        y las fechas de disponibilidad. La disponibilidad se calcula automáticamente.
    </p>

    <h3>2.3 Importar socios existentes</h3>
    <p>
        Si la asociación ya tiene socios en un sistema anterior, utilice
        <strong>Gestión de Socios → Primera Importación</strong> para cargar un fichero CSV.
        Descargue primero la plantilla de ejemplo para conocer el formato exacto de cada columna.
    </p>
</section>

{{-- 3. SOCIOS --}}
<section>
    <h2>3. Gestión de socios</h2>
    <p>
        La sección <strong>Gestión de Socios → Socios</strong> centraliza toda la información relativa a las
        personas vinculadas a la asociación.
    </p>

    <h3>3.1 Alta de un socio</h3>
    <p>
        Pulse <strong>Nuevo socio</strong> en la lista de socios. El número de socio se asigna automáticamente
        de forma correlativa. Campos relevantes:
    </p>
    <ul>
        <li><strong>Nombre y apellidos, DNI, email, teléfono</strong> — Datos básicos de contacto.</li>
        <li><strong>Dirección postal</strong> — Necesaria para la impresión de etiquetas.</li>
        <li><strong>Fecha de nacimiento y género</strong> — Opcionales, usados en documentos personalizados.</li>
        <li><strong>Tipo de socio</strong> — <em>Socio</em> (pertenece a la asociación) o <em>No Socio</em> (persona externa).</li>
        <li><strong>Método de pago</strong> — Efectivo, transferencia o domiciliación (SEPA).</li>
        <li><strong>Datos SEPA</strong> — Si el método de pago es domiciliación, rellene el IBAN, BIC, titular, referencia del mandato y fecha de firma.</li>
        <li><strong>Tipo de cuota</strong> — El tipo de cuota asignado al socio al darse de alta.</li>
        <li><strong>Fecha de alta</strong> — Por defecto la fecha actual.</li>
    </ul>

    <h3>3.2 Estados de un socio</h3>
    <ul>
        <li><strong>Pendiente</strong> — Recién registrado, pendiente de activar.</li>
        <li><strong>Activo</strong> — Socio en regla. El sistema le generará cuotas periódicamente.</li>
        <li><strong>Baja</strong> — Socio que ha causado baja, voluntaria o por morosidad.</li>
    </ul>

    <h3>3.3 Mandato SEPA</h3>
    <p>
        Para socios con domiciliación bancaria, el sistema lleva el control del mandato SEPA:
        estado (Activo, Cancelado, Caducado), tipo de secuencia (First, Recurrent, Final) y fecha de firma.
        Puede imprimir el mandato desde el botón <strong>Imprimir mandato SEPA</strong> en la ficha del socio.
    </p>
</section>

{{-- 4. CUOTAS --}}
<section>
    <h2>4. Cuotas</h2>
    <p>
        Las cuotas representan los pagos periódicos de los socios activos.
        Se gestionan desde <strong>Gestión de Socios → Cuotas</strong>.
    </p>

    <h3>4.1 Generación de cuotas</h3>
    <p>
        Las cuotas se generan automáticamente según el tipo de cuota asignado al socio.
        También es posible crearlas manualmente desde el listado o desde la ficha del socio.
    </p>

    <h3>4.2 Estados de una cuota</h3>
    <ul>
        <li><strong>Pendiente</strong> — Generada pero aún no pagada.</li>
        <li><strong>Pagada</strong> — Pago recibido. Se genera un recibo automáticamente.</li>
        <li><strong>Vencida</strong> — No fue pagada en plazo.</li>
        <li><strong>Cancelada</strong> — Anulada (ej. baja del socio a mitad del periodo).</li>
    </ul>

    <h3>4.3 Marcar una cuota como pagada</h3>
    <p>
        Desde la ficha de la cuota, pulse <strong>Registrar pago</strong>.
        Se solicitará la fecha y el método de pago. Al confirmar, se registra automáticamente
        una transacción de ingreso en contabilidad.
    </p>

    <h3>4.4 Tipos de cuota</h3>
    <p>
        Los tipos de cuota se configuran en <strong>Configuración → Tipos de cuota</strong>.
        Cada tipo define: nombre, importe, periodo de cobro, fecha de referencia del periodo,
        días de devolución, alineación de periodo y orden de presentación.
    </p>
</section>

{{-- 5. EVENTOS --}}
<section>
    <h2>5. Gestión de eventos</h2>
    <p>
        Los eventos permiten organizar actividades y gestionar inscripciones de socios y acompañantes.
        Se accede desde <strong>Gestión de Eventos → Eventos</strong>.
    </p>

    <h3>5.1 Crear un evento</h3>
    <p>Pulse <strong>Nuevo evento</strong>. Campos principales: nombre, tipo, fechas de celebración,
    fecha límite de inscripción, precio base, recargos para acompañantes y no socios,
    máximo de acompañantes por socio y notas.</p>

    <h3>5.2 Estados de un evento</h3>
    <ul>
        <li><strong>Borrador</strong> — En preparación, no visible para inscripciones.</li>
        <li><strong>Abierto</strong> — Se aceptan inscripciones.</li>
        <li><strong>Cerrado</strong> — Finalizado el periodo de inscripción.</li>
        <li><strong>Realizado</strong> — El evento se ha celebrado.</li>
        <li><strong>Cancelado</strong> — Al cancelar, todas las inscripciones activas se cancelan y reembolsan automáticamente.</li>
    </ul>
</section>

{{-- 6. INSCRIPCIONES --}}
<section>
    <h2>6. Inscripciones</h2>
    <p>
        Se gestionan desde <strong>Gestión de Eventos → Inscripciones</strong>
        o desde la ficha del evento.
    </p>

    <h3>6.1 Tipos de inscripción</h3>
    <ul>
        <li><strong>Socio</strong> — Socio activo de la asociación.</li>
        <li><strong>No Socio</strong> — Persona externa; se le aplica el recargo correspondiente.</li>
        <li><strong>Acompañante</strong> — Acompañante de un socio, vinculado a su inscripción.</li>
    </ul>

    <h3>6.2 Registrar el pago de una inscripción</h3>
    <p>
        Desde la ficha de la inscripción, pulse <strong>Registrar pago</strong>.
        La inscripción pasa a <em>Confirmada</em> y se genera un recibo descargable.
    </p>

    <h3>6.3 Cancelar una inscripción</h3>
    <p>
        Use el botón <strong>Cancelar inscripción</strong>. Si el pago ya se había realizado,
        el sistema registra automáticamente un reembolso en contabilidad.
    </p>
</section>

{{-- 7. CONTABILIDAD --}}
<section>
    <h2>7. Contabilidad</h2>
    <p>
        <strong>Contabilidad → Transacciones</strong> registra todos los movimientos económicos.
        Las transacciones se generan automáticamente al pagar cuotas e inscripciones o al reembolsarlas.
        También puede añadir transacciones manuales (ingresos o gastos extraordinarios) pulsando
        <strong>Nueva transacción</strong>. Use el botón <strong>Exportar</strong> para descargar
        las transacciones en Excel o CSV.
    </p>
</section>

{{-- 8. REPORTES --}}
<section>
    <h2>8. Reportes</h2>

    <h3>8.1 Imprimir etiquetas postales</h3>
    <p>
        <strong>Reportes → Imprimir etiquetas</strong> genera un PDF con etiquetas postales para los socios seleccionados.
        Configure la plantilla de etiqueta (formato físico), los campos a incluir, los filtros de socios,
        la posición inicial en la hoja y si desea una hoja de prueba antes de imprimir en real.
    </p>

    <h3>8.2 Generar documentos desde plantilla</h3>
    <p>
        <strong>Reportes → Imprimir plantilla</strong> genera documentos Word (DOCX) personalizados
        para uno o varios socios. Seleccione la plantilla y los socios; la aplicación descargará un DOCX
        con los datos de cada socio sustituidos en las variables de la plantilla.
    </p>
</section>

{{-- 9. CONFIGURACIÓN --}}
<section>
    <h2>9. Configuración</h2>

    <h3>9.1 Plantillas de documentos</h3>
    <p>
        En <strong>Configuración → Plantillas de documentos</strong> suba plantillas Word (DOCX)
        con variables en formato <code>@{{nombre_variable}}</code>. Las variables disponibles se detectan
        automáticamente. Pulse <strong>Vista previa</strong> para ver el resultado antes de usarla.
    </p>
    <p>Ejemplos de variables: <code>@{{socio_nombre}}</code>, <code>@{{socio_apellidos}}</code>,
    <code>@{{socio_dni}}</code>, <code>@{{socio_numero_socio}}</code>, <code>@{{socio_email}}</code>,
    <code>@{{socio_direccion_completa}}</code>, <code>@{{asociacion_nombre}}</code>, <code>@{{fecha_actual}}</code>.</p>

    <h3>9.2 Plantillas de etiquetas</h3>
    <p>
        En <strong>Configuración → Plantillas de etiquetas</strong> configure los formatos físicos
        de hojas de etiquetas: marca, modelo, dimensiones, márgenes, número de columnas y filas.
        Puede crear formatos personalizados si su etiqueta no está disponible de serie.
    </p>

    <h3>9.3 Tipos de cuota</h3>
    <p>
        Configurados en <strong>Configuración → Tipos de cuota</strong>. La disponibilidad
        se calcula automáticamente en base a fechas, sin necesidad de activar o desactivar manualmente.
    </p>

    <h3>9.4 Datos de la asociación</h3>
    <p>
        <strong>Configuración → Asociación</strong>: nombre, CIF, dirección, métodos de pago,
        periodo de cobro, datos bancarios e identificador de acreedor SEPA.
    </p>
</section>

<div class="footer">
    Manual de usuario · MiAsovec · {{ now()->format('Y') }}
</div>

</div>
</body>
</html>
