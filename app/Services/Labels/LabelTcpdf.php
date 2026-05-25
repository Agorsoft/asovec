<?php

namespace App\Services\Labels;

use Com\Tecnick\Pdf\Tcpdf;

/**
 * Subclase de Tcpdf que expone getStringWidth() como método público
 * para permitir la medición exacta del ancho de cadenas de texto antes
 * de renderizarlas, evitando saltos de línea inesperados en las etiquetas.
 */
final class LabelTcpdf extends Tcpdf
{
    public function measureStringWidth(string $str): float
    {
        return $this->getStringWidth($str);
    }
}
