<?php

namespace App\Enums;

enum PdfEngine
{
    /** Dompdf: mejor soporte de maquetado HTML/CSS (recibos, documentos generales). */
    case Dompdf;

    /** TCPDF (tc-lib-pdf): posicionamiento absoluto por coordenadas (etiquetas). */
    case Tcpdf;
}
