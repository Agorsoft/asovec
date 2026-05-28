<x-filament-panels::page>
    <div class="prose prose-sm max-w-none dark:prose-invert space-y-8">

        {{-- ÍNDICE --}}
        <div class="bg-sky-50 dark:bg-sky-950 border border-sky-200 dark:border-sky-800 rounded-xl p-6 not-prose">
            <h2 class="text-lg font-semibold text-sky-900 dark:text-sky-100 mb-4">Índice de contenidos</h2>
            <ol class="list-decimal list-inside space-y-1 text-sm text-sky-800 dark:text-sky-200 columns-2 gap-x-8">
                <li><a href="#introduccion" class="hover:underline">Introducción</a></li>
                <li><a href="#primeros-pasos" class="hover:underline">Primeros pasos</a></li>
                <li><a href="#socios" class="hover:underline">Gestión de socios</a></li>
                <li><a href="#cuotas" class="hover:underline">Cuotas</a></li>
                <li><a href="#eventos" class="hover:underline">Gestión de eventos</a></li>
                <li><a href="#inscripciones" class="hover:underline">Inscripciones</a></li>
                <li><a href="#contabilidad" class="hover:underline">Contabilidad</a></li>
                <li><a href="#reportes" class="hover:underline">Reportes</a></li>
                <li><a href="#configuracion" class="hover:underline">Configuración</a></li>
            </ol>
        </div>

        {{-- 1. INTRODUCCIÓN --}}
        <section id="introduccion">
            <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">
                1. Introducción
            </h2>
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
        <section id="primeros-pasos">
            <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">
                2. Primeros pasos
            </h2>
            <p>
                Cuando acceda por primera vez a la aplicación, se le redirigirá automáticamente al asistente de
                configuración inicial. Siga los pasos para completar los datos básicos de la asociación antes de
                comenzar a usar el resto de funcionalidades.
            </p>

            <h3 class="text-lg font-semibold mt-4">2.1 Configurar la asociación</h3>
            <p>
                Acceda a <strong>Configuración → Asociación</strong> para revisar o actualizar los datos en cualquier momento.
                Los campos más importantes son:
            </p>
            <ul>
                <li><strong>Nombre, CIF y dirección</strong> — Aparecen en recibos y documentos generados.</li>
                <li><strong>Métodos de pago aceptados</strong> — Efectivo, transferencia bancaria o domiciliación bancaria (SEPA).</li>
                <li><strong>Periodo de cobro</strong> — Mensual, trimestral, semestral o anual. Define cuándo se generan las cuotas.</li>
                <li><strong>Máscara de número de recibo</strong> — Formato del número correlativo de recibos (ej. <code>{Y:2}-{N:4}</code>).</li>
                <li><strong>Datos bancarios</strong> — Necesarios para emitir mandatos SEPA y adeudos directos.</li>
                <li><strong>Identificador de acreedor SEPA</strong> — Código asignado por el banco para la domiciliación.</li>
            </ul>

            <h3 class="text-lg font-semibold mt-4">2.2 Crear tipos de cuota</h3>
            <p>
                Antes de registrar socios, cree al menos un tipo de cuota en
                <strong>Configuración → Tipos de cuota</strong>. Cada tipo define el importe, el periodo de cobro
                y las fechas de disponibilidad. La disponibilidad se calcula automáticamente:
                un tipo de cuota está disponible cuando la fecha de referencia del periodo
                es igual o posterior al umbral de activación configurado.
            </p>

            <h3 class="text-lg font-semibold mt-4">2.3 Importar socios existentes</h3>
            <p>
                Si la asociación ya tiene socios en un sistema anterior, utilice
                <strong>Gestión de Socios → Primera Importación</strong> para cargar un fichero CSV con todos los socios
                de forma masiva. Descargue primero la plantilla de ejemplo para conocer el formato exacto de cada columna.
            </p>
        </section>

        {{-- 3. SOCIOS --}}
        <section id="socios">
            <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">
                3. Gestión de socios
            </h2>
            <p>
                La sección <strong>Gestión de Socios → Socios</strong> centraliza toda la información relativa a las
                personas vinculadas a la asociación.
            </p>

            <h3 class="text-lg font-semibold mt-4">3.1 Alta de un socio</h3>
            <p>
                Pulse <strong>Nuevo socio</strong> en la lista de socios. El número de socio se asigna automáticamente de
                forma correlativa. Campos relevantes:
            </p>
            <ul>
                <li><strong>Nombre y apellidos, DNI, email, teléfono</strong> — Datos básicos de contacto.</li>
                <li><strong>Dirección postal</strong> — Necesaria para la impresión de etiquetas.</li>
                <li><strong>Fecha de nacimiento y género</strong> — Opcionales, usados en documentos personalizados.</li>
                <li><strong>Tipo de socio</strong> — <em>Socio</em> (pertenece a la asociación) o <em>No Socio</em> (persona externa, por ejemplo para inscripciones en eventos).</li>
                <li><strong>Método de pago</strong> — Efectivo, transferencia o domiciliación (SEPA).</li>
                <li><strong>Datos SEPA</strong> — Si el método de pago es domiciliación, rellene el IBAN, BIC y titular. La referencia del mandato y la fecha de firma son obligatorias para emitir adeudos directos.</li>
                <li><strong>Tipo de cuota</strong> — El tipo de cuota asignado al socio al darse de alta.</li>
                <li><strong>Fecha de alta</strong> — Por defecto la fecha actual.</li>
            </ul>

            <h3 class="text-lg font-semibold mt-4">3.2 Estados de un socio</h3>
            <p>Un socio puede estar en uno de los siguientes estados:</p>
            <ul>
                <li><strong>Pendiente</strong> — Recién registrado, pendiente de activar.</li>
                <li><strong>Activo</strong> — Socio en regla. El sistema le generará cuotas periódicamente.</li>
                <li><strong>Baja</strong> — Socio que ha causado baja, voluntaria o por morosidad.</li>
            </ul>
            <p>
                El estado se puede cambiar manualmente desde la ficha del socio (botón <strong>Activar</strong>,
                <strong>Dar de baja</strong>, etc.) o automáticamente cuando el sistema detecta impagos acumulados
                según la configuración de la asociación.
            </p>

            <h3 class="text-lg font-semibold mt-4">3.3 Mandato SEPA</h3>
            <p>
                Para socios con domiciliación bancaria, el sistema lleva el control del mandato SEPA:
                estado (Activo, Cancelado, Caducado), tipo de secuencia (First, Recurrent, Final) y fecha de firma.
                Puede imprimir el mandato para que el socio lo firme desde el botón
                <strong>Imprimir mandato SEPA</strong> en la ficha del socio.
            </p>

            <h3 class="text-lg font-semibold mt-4">3.4 Foto del socio</h3>
            <p>
                Puede adjuntar una fotografía a la ficha de cada socio. La imagen se almacena de forma privada
                en el servidor y solo es visible dentro de la aplicación.
            </p>
        </section>

        {{-- 4. CUOTAS --}}
        <section id="cuotas">
            <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">
                4. Cuotas
            </h2>
            <p>
                Las cuotas representan los pagos periódicos que deben realizar los socios activos.
                Se gestionan desde <strong>Gestión de Socios → Cuotas</strong>.
            </p>

            <h3 class="text-lg font-semibold mt-4">4.1 Generación de cuotas</h3>
            <p>
                Las cuotas se generan automáticamente según el tipo de cuota asignado al socio y el periodo
                de cobro de la asociación. También es posible crear cuotas manualmente desde el listado de cuotas
                o desde la ficha de un socio.
            </p>

            <h3 class="text-lg font-semibold mt-4">4.2 Estados de una cuota</h3>
            <ul>
                <li><strong>Pendiente</strong> — La cuota ha sido generada pero aún no se ha pagado.</li>
                <li><strong>Pagada</strong> — El socio ha realizado el pago. Se genera automáticamente un recibo.</li>
                <li><strong>Vencida</strong> — La cuota no fue pagada en plazo.</li>
                <li><strong>Cancelada</strong> — La cuota ha sido anulada (ej. baja del socio a mitad del periodo).</li>
            </ul>

            <h3 class="text-lg font-semibold mt-4">4.3 Marcar una cuota como pagada</h3>
            <p>
                Desde el listado de cuotas o desde la ficha de la cuota, pulse <strong>Registrar pago</strong>.
                Se solicitará la fecha de pago y el método utilizado. Al confirmar, la cuota pasa a estado
                <em>Pagada</em> y se registra automáticamente una transacción de ingreso en contabilidad.
            </p>

            <h3 class="text-lg font-semibold mt-4">4.4 Recibo de cuota</h3>
            <p>
                Una vez pagada, puede generar e imprimir el recibo en PDF desde el botón
                <strong>Imprimir</strong> en la ficha de la cuota.
            </p>

            <h3 class="text-lg font-semibold mt-4">4.5 Tipos de cuota</h3>
            <p>
                Los tipos de cuota se configuran en <strong>Configuración → Tipos de cuota</strong>.
                Cada tipo define:
            </p>
            <ul>
                <li><strong>Nombre</strong> — Identificador del tipo (ej. "Cuota anual general").</li>
                <li><strong>Importe</strong> — Cantidad a cobrar.</li>
                <li><strong>Periodo de cobro</strong> — Mensual, trimestral, semestral o anual.</li>
                <li><strong>Fecha de referencia del periodo</strong> — Fecha a partir de la cual el tipo está disponible para asignar a socios.</li>
                <li><strong>Días de devolución</strong> — Días desde el alta del socio en que se permite cancelar y devolver la cuota.</li>
                <li><strong>Alinear inicio de periodo</strong> — Si está activado, el primer periodo del socio se ajusta al inicio del periodo natural.</li>
                <li><strong>Orden</strong> — Posición en el selector de tipos para la creación de socios.</li>
            </ul>
        </section>

        {{-- 5. EVENTOS --}}
        <section id="eventos">
            <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">
                5. Gestión de eventos
            </h2>
            <p>
                Los eventos permiten organizar actividades de la asociación (excursiones, cenas, asambleas, etc.)
                y gestionar las inscripciones de socios y acompañantes.
                Se accede desde <strong>Gestión de Eventos → Eventos</strong>.
            </p>

            <h3 class="text-lg font-semibold mt-4">5.1 Crear un evento</h3>
            <p>Pulse <strong>Nuevo evento</strong>. Los campos principales son:</p>
            <ul>
                <li><strong>Nombre y descripción</strong> — Información del evento.</li>
                <li><strong>Tipo de evento</strong> — Clasifica el evento (excursión, cena, asamblea, etc.).</li>
                <li><strong>Fechas</strong> — Fecha y hora de inicio y fin del evento.</li>
                <li><strong>Fecha límite de inscripción</strong> — Fecha hasta la que se aceptan inscripciones.</li>
                <li><strong>Precio base</strong> — Precio por participante socio.</li>
                <li><strong>Recargo por acompañante</strong> — Precio adicional para acompañantes.</li>
                <li><strong>Recargo para no socios</strong> — Precio adicional para inscritos externos.</li>
                <li><strong>Máximo de acompañantes por socio</strong> — Límite de acompañantes que puede traer cada socio.</li>
                <li><strong>Notas</strong> — Información interna sobre el evento.</li>
            </ul>

            <h3 class="text-lg font-semibold mt-4">5.2 Estados de un evento</h3>
            <ul>
                <li><strong>Borrador</strong> — El evento está en preparación, no visible para inscripciones.</li>
                <li><strong>Abierto</strong> — Se aceptan inscripciones.</li>
                <li><strong>Cerrado</strong> — El evento ha finalizado el periodo de inscripción.</li>
                <li><strong>Realizado</strong> — El evento se ha celebrado.</li>
                <li><strong>Cancelado</strong> — El evento fue cancelado. Al cancelar un evento, todas las inscripciones activas se cancelan automáticamente y se reembolsan los pagos correspondientes.</li>
            </ul>
            <p>
                La transición de estado <em>Abierto → Cerrado</em> puede realizarse automáticamente
                cuando se supera la fecha límite de inscripción (requiere tarea programada activa).
            </p>
        </section>

        {{-- 6. INSCRIPCIONES --}}
        <section id="inscripciones">
            <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">
                6. Inscripciones
            </h2>
            <p>
                Las inscripciones se gestionan desde <strong>Gestión de Eventos → Inscripciones</strong>
                o directamente desde la ficha de un evento (pestaña <em>Inscripciones</em>).
            </p>

            <h3 class="text-lg font-semibold mt-4">6.1 Tipos de inscripción</h3>
            <ul>
                <li><strong>Socio</strong> — El inscrito es un socio activo de la asociación.</li>
                <li><strong>No Socio</strong> — Persona externa, no socia. Se le aplica el recargo correspondiente.</li>
                <li><strong>Acompañante</strong> — Acompañante de un socio, vinculado a su inscripción principal.</li>
            </ul>

            <h3 class="text-lg font-semibold mt-4">6.2 Crear una inscripción</h3>
            <p>
                Desde la lista de inscripciones, pulse <strong>Nueva inscripción</strong>.
                Seleccione el evento, el tipo de inscripción, el socio o nombre del participante
                y el método de pago. Si el tipo es <em>Acompañante</em>, debe indicar también
                la inscripción del socio titular al que acompaña.
            </p>

            <h3 class="text-lg font-semibold mt-4">6.3 Estados de una inscripción</h3>
            <ul>
                <li><strong>Pendiente</strong> — Inscripción registrada, pendiente de pago.</li>
                <li><strong>Confirmada</strong> — Pago recibido. Se genera un recibo.</li>
                <li><strong>Cancelada</strong> — La inscripción ha sido anulada.</li>
            </ul>

            <h3 class="text-lg font-semibold mt-4">6.4 Registrar el pago de una inscripción</h3>
            <p>
                Desde la ficha de la inscripción, pulse <strong>Registrar pago</strong>.
                Al confirmar, la inscripción pasa a estado <em>Confirmada</em> y se registra
                una transacción de ingreso en contabilidad. Puede imprimir el recibo desde el botón
                <strong>Imprimir</strong>.
            </p>

            <h3 class="text-lg font-semibold mt-4">6.5 Cancelar una inscripción</h3>
            <p>
                Si el socio cancela su participación, use el botón <strong>Cancelar inscripción</strong>.
                Si el pago ya había sido realizado, el sistema registrará automáticamente un reembolso
                en contabilidad.
            </p>
        </section>

        {{-- 7. CONTABILIDAD --}}
        <section id="contabilidad">
            <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">
                7. Contabilidad
            </h2>
            <p>
                La sección <strong>Contabilidad → Transacciones</strong> registra todos los movimientos
                económicos de la asociación.
            </p>

            <h3 class="text-lg font-semibold mt-4">7.1 Transacciones automáticas</h3>
            <p>
                El sistema genera transacciones automáticamente cuando ocurren eventos económicos:
            </p>
            <ul>
                <li><strong>Pago de cuota</strong> — Se registra un ingreso por el importe de la cuota.</li>
                <li><strong>Reembolso de cuota</strong> — Se registra un gasto de devolución.</li>
                <li><strong>Pago de inscripción a evento</strong> — Se registra el ingreso correspondiente (precio base, recargo de acompañante o recargo de no socio).</li>
                <li><strong>Reembolso de inscripción</strong> — Se registra la devolución al cancelar una inscripción ya pagada.</li>
            </ul>

            <h3 class="text-lg font-semibold mt-4">7.2 Transacciones manuales</h3>
            <p>
                Puede registrar cualquier ingreso o gasto adicional pulsando <strong>Nueva transacción</strong>.
                Indique la descripción, el importe, la fecha y el tipo (ingreso o gasto).
            </p>

            <h3 class="text-lg font-semibold mt-4">7.3 Exportar transacciones</h3>
            <p>
                Use el botón <strong>Exportar</strong> para descargar las transacciones filtradas en formato Excel o CSV,
                para su análisis o archivo externo.
            </p>
        </section>

        {{-- 8. REPORTES --}}
        <section id="reportes">
            <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">
                8. Reportes
            </h2>

            <h3 class="text-lg font-semibold mt-4">8.1 Imprimir etiquetas postales</h3>
            <p>
                Acceda a <strong>Reportes → Imprimir etiquetas</strong> para generar un PDF con etiquetas
                postales de los socios seleccionados. Opciones disponibles:
            </p>
            <ul>
                <li><strong>Plantilla de etiqueta</strong> — Seleccione el formato físico de la hoja de etiquetas (ej. Apli 01277).</li>
                <li><strong>Campos a incluir</strong> — Elija qué información aparece: nombre, apellidos, dirección, ciudad, código postal, provincia.</li>
                <li><strong>Filtros de socios</strong> — Filtre por estado, tipo, provincia o búsqueda libre.</li>
                <li><strong>Posición inicial</strong> — Indique desde qué posición de la hoja empezar a imprimir (útil para aprovechar hojas parcialmente usadas).</li>
                <li><strong>Hoja de prueba</strong> — Genera una hoja de muestra con datos ficticios para comprobar el ajuste antes de imprimir en real.</li>
            </ul>

            <h3 class="text-lg font-semibold mt-4">8.2 Generar documentos desde plantilla</h3>
            <p>
                Acceda a <strong>Reportes → Imprimir plantilla</strong> para generar documentos Word (DOCX)
                personalizados para uno o varios socios. Funciona así:
            </p>
            <ol>
                <li>Seleccione la <strong>plantilla de documento</strong> previamente configurada.</li>
                <li>Seleccione los <strong>socios</strong> para los que generar el documento.</li>
                <li>Pulse <strong>Generar documento</strong>. La aplicación descargará un fichero DOCX con un documento por socio, con los datos de cada uno sustituidos en las variables de la plantilla.</li>
            </ol>
        </section>

        {{-- 9. CONFIGURACIÓN --}}
        <section id="configuracion">
            <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">
                9. Configuración
            </h2>

            <h3 class="text-lg font-semibold mt-4">9.1 Plantillas de documentos</h3>
            <p>
                En <strong>Configuración → Plantillas de documentos</strong> puede subir y gestionar
                plantillas Word (DOCX) que se usarán para generar documentos personalizados para los socios.
            </p>
            <p>
                Las plantillas utilizan <strong>variables</strong> en el formato <code>@{{nombre_variable}}</code>
                que el sistema sustituye por los datos reales de cada socio. Las variables disponibles se muestran
                automáticamente al visualizar la plantilla. Ejemplos de variables:
            </p>
            <ul>
                <li><code>@{{socio_nombre}}</code> — Nombre del socio.</li>
                <li><code>@{{socio_apellidos}}</code> — Apellidos del socio.</li>
                <li><code>@{{socio_dni}}</code> — DNI del socio.</li>
                <li><code>@{{socio_numero_socio}}</code> — Número de socio.</li>
                <li><code>@{{socio_email}}</code> — Email del socio.</li>
                <li><code>@{{socio_direccion_completa}}</code> — Dirección completa.</li>
                <li><code>@{{asociacion_nombre}}</code> — Nombre de la asociación.</li>
                <li><code>@{{fecha_actual}}</code> — Fecha de generación del documento.</li>
            </ul>
            <p>
                Pulse <strong>Vista previa</strong> en la ficha de una plantilla para ver cómo quedará renderizada
                antes de usarla.
            </p>

            <h3 class="text-lg font-semibold mt-4">9.2 Plantillas de etiquetas</h3>
            <p>
                En <strong>Configuración → Plantillas de etiquetas</strong> se configuran los formatos
                físicos de hojas de etiquetas compatibles (marca, modelo, dimensiones, márgenes,
                número de columnas y filas, etc.). Puede crear plantillas personalizadas si su
                formato de etiqueta no está disponible de serie.
            </p>

            <h3 class="text-lg font-semibold mt-4">9.3 Tipos de cuota</h3>
            <p>
                Ya explicados en la sección <a href="#cuotas">4.5 Tipos de cuota</a>.
                Recuerde que la disponibilidad de cada tipo se calcula automáticamente en base a fechas,
                sin necesidad de activar/desactivar manualmente.
            </p>

            <h3 class="text-lg font-semibold mt-4">9.4 Datos de la asociación</h3>
            <p>
                Ya explicados en la sección <a href="#primeros-pasos">2.1 Configurar la asociación</a>.
            </p>
        </section>

        {{-- PIE --}}
        <div class="mt-12 pt-6 border-t border-gray-200 dark:border-gray-700 text-sm text-gray-500 dark:text-gray-400 text-center not-prose">
            Manual de usuario · MiAsovec · {{ now()->format('Y') }}
        </div>
    </div>
</x-filament-panels::page>
