<?php

namespace App\Filament\Resources\DocumentTemplates\Pages;

use App\Filament\Resources\DocumentTemplates\DocumentTemplateResource;
use App\Services\Documents\DocumentVariableRegistry;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListDocumentTemplates extends ListRecords
{
    protected static string $resource = DocumentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_sample')
                ->label('Descargar plantilla de ejemplo')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): StreamedResponse {
                    return response()->streamDownload(
                        fn () => print ($this->generateSampleDocx()),
                        'plantilla-ejemplo-variables.docx',
                        ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
                    );
                }),

            CreateAction::make(),
        ];
    }

    private function generateSampleDocx(): string
    {
        /** @var DocumentVariableRegistry $registry */
        $registry = app(DocumentVariableRegistry::class);
        $grouped = $registry->groupedForDisplay();

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(10);

        $section = $phpWord->addSection();

        // Título
        $section->addText(
            'Plantilla de ejemplo — Variables disponibles',
            ['bold' => true, 'size' => 14, 'color' => '374151'],
            ['spaceAfter' => 200]
        );

        $section->addText(
            'Copia cualquiera de los placeholders de la columna "Placeholder" y pégalo en tu documento Word. '
            .'Al generar el documento, cada placeholder será reemplazado automáticamente por el valor del socio seleccionado.',
            ['size' => 10, 'color' => '6B7280'],
            ['spaceAfter' => 300]
        );

        // Tabla de variables
        $table = $section->addTable([
            'borderSize' => 6,
            'borderColor' => 'D1D5DB',
            'cellMargin' => 80,
        ]);

        // Cabecera de tabla
        $table->addRow();
        $headerFont = ['bold' => true, 'size' => 9, 'color' => '1F2937'];
        $headerCell = ['bgColor' => 'F3F4F6'];

        $table->addCell(3000, $headerCell)->addText('Placeholder', $headerFont);
        $table->addCell(4500, $headerCell)->addText('Descripción', $headerFont);
        $table->addCell(2000, $headerCell)->addText('Grupo', $headerFont);

        // Filas de variables
        foreach ($grouped as $group => $variables) {
            foreach ($variables as $key => $label) {
                $table->addRow();
                $table->addCell(3000)->addText('${'.$key.'}', ['name' => 'Courier New', 'size' => 9, 'color' => '1D4ED8']);
                $table->addCell(4500)->addText($label, ['size' => 9]);
                $table->addCell(2000)->addText($group, ['size' => 9, 'color' => '6B7280']);
            }
        }

        $section->addTextBreak(1);

        // Ejemplo de uso
        $section->addText(
            'Ejemplo de documento usando variables:',
            ['bold' => true, 'size' => 11, 'color' => '374151'],
            ['spaceAfter' => 100]
        );

        $example = $section->addTextRun(['spaceAfter' => 100]);
        $example->addText('Estimado/a ', ['size' => 10]);
        $example->addText('${member.full_name}', ['size' => 10, 'name' => 'Courier New', 'color' => '1D4ED8']);
        $example->addText(':', ['size' => 10]);

        $example2 = $section->addTextRun(['spaceAfter' => 100]);
        $example2->addText('Le informamos que su cuota de ', ['size' => 10]);
        $example2->addText('${quota.amount}', ['size' => 10, 'name' => 'Courier New', 'color' => '1D4ED8']);
        $example2->addText(' con vencimiento el ', ['size' => 10]);
        $example2->addText('${quota.due_date}', ['size' => 10, 'name' => 'Courier New', 'color' => '1D4ED8']);
        $example2->addText(' está pendiente de pago.', ['size' => 10]);

        $section->addText(
            'Atentamente,',
            ['size' => 10],
            ['spaceAfter' => 100]
        );
        $section->addText(
            '${association.name}',
            ['size' => 10, 'name' => 'Courier New', 'color' => '1D4ED8']
        );

        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $tmp = tempnam(sys_get_temp_dir(), 'sample_docx_');
        $objWriter->save($tmp);

        $bytes = file_get_contents($tmp);
        @unlink($tmp);

        return $bytes ?: '';
    }
}
