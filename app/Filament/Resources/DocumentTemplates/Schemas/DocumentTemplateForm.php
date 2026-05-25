<?php

namespace App\Filament\Resources\DocumentTemplates\Schemas;

use App\Enums\DocumentType;
use App\Services\Documents\DocumentVariableRegistry;
use App\Services\Documents\DocxTemplateParser;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class DocumentTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Información general')
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Textarea::make('description')
                        ->label('Descripción')
                        ->rows(2)
                        ->columnSpanFull(),
                    Select::make('type')
                        ->label('Tipo')
                        ->options(DocumentType::class)
                        ->required()
                        ->native(false)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpan(1),

            Section::make('Archivo de plantilla')
                ->schema([
                    FileUpload::make('file_path')
                        ->label('Archivo DOCX')
                        ->required(fn ($record) => $record === null)
                        ->dehydrated(fn ($state) => ! empty($state))
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                        ->maxSize(10240) // 10 MB
                        ->directory('documents/templates')
                        ->disk('local')
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set, $state) {
                            if ($state === null) {
                                $set('detected_variables', []);

                                return;
                            }

                            $file = is_array($state) ? reset($state) : $state;

                            $path = null;
                            $tmpPath = null;

                            if ($file instanceof TemporaryUploadedFile) {
                                // En Livewire 4, TemporaryUploadedFile puede estar en el disco
                                // livewire-tmp; usamos el path real si existe, o escribimos a tmp.
                                $realPath = $file->getRealPath();

                                if ($realPath !== false && file_exists($realPath)) {
                                    $path = $realPath;
                                } else {
                                    // Fallback: copiar contenido a un fichero temporal real.
                                    try {
                                        $tmpPath = tempnam(sys_get_temp_dir(), 'docx_parse_');
                                        file_put_contents(
                                            $tmpPath,
                                            Storage::disk('local')->get('livewire-tmp/'.$file->getFilename())
                                        );
                                        $path = $tmpPath;
                                    } catch (\Throwable) {
                                        // No se pudo leer el archivo temporal.
                                    }
                                }
                            } elseif (is_string($file)) {
                                $path = Storage::disk('local')->path($file);
                            }

                            if ($path === null || ! file_exists($path)) {
                                if ($tmpPath) {
                                    @unlink($tmpPath);
                                }

                                return;
                            }

                            try {
                                $parser = app(DocxTemplateParser::class);
                                $registry = app(DocumentVariableRegistry::class);
                                $result = $parser->parse($path, $registry);
                                $set('detected_variables', $result['valid']);
                            } catch (\Throwable) {
                                // Si el parseo falla no bloqueamos el formulario.
                            } finally {
                                if ($tmpPath) {
                                    @unlink($tmpPath);
                                }
                            }
                        })
                        ->helperText('Solo se admiten archivos .docx. Tamaño máximo: 10 MB.'),
                    Hidden::make('detected_variables'),
                ])
                ->columns(1)
                ->columnSpan(1),

            Section::make('Variables detectadas')
                ->schema([
                    TextInput::make('detected_variables_display')
                        ->label('Variables reconocidas')
                        ->dehydrated(false)
                        ->disabled()
                        ->placeholder('Se detectarán automáticamente al subir el archivo.')
                        ->formatStateUsing(fn ($record) => $record
                            ? implode(', ', array_map(fn ($k) => '${'.$k.'}', $record->detected_variables ?? []))
                            : ''
                        )
                        ->helperText('Lista de variables válidas encontradas en la plantilla.')
                        ->columnSpanFull()
                        ->visible(fn ($record) => $record !== null && ! empty($record->detected_variables)),
                ])
                ->collapsible()
                ->visible(fn ($record) => $record !== null)
                ->columnSpanFull(),

            Section::make('Variables disponibles')
                ->schema([
                    Placeholder::make('_variable_info')
                        ->label('')
                        ->content(static::buildVariableCatalogHtml()),
                ])
                ->collapsed()
                ->collapsible()
                ->columnSpanFull(),
        ])->columns(2);
    }

    private static function buildVariableCatalogHtml(): HtmlString
    {
        $registry = app(DocumentVariableRegistry::class);
        $grouped = $registry->groupedForDisplay();

        $intro = '<p class="text-sm text-gray-600 dark:text-gray-400 mb-4">'
            .'Escribe estos placeholders en tu documento Word exactamente como se muestran (incluyendo <code class="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded text-xs">${</code> y <code class="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded text-xs">}</code>). '
            .'Al generar el documento cada placeholder se sustituirá por el valor real del socio seleccionado.'
            .'</p>';

        $html = $intro.'<div class="overflow-x-auto"><table class="w-full text-sm border-collapse">'
            .'<thead><tr>'
            .'<th class="text-left py-1.5 px-2 bg-gray-100 dark:bg-gray-800 font-semibold text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 w-1/2">Placeholder a insertar en Word</th>'
            .'<th class="text-left py-1.5 px-2 bg-gray-100 dark:bg-gray-800 font-semibold text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700">Descripción</th>'
            .'</tr></thead><tbody>';

        $rowIndex = 0;
        foreach ($grouped as $group => $variables) {
            $html .= '<tr>'
                .'<td colspan="2" class="py-1 px-2 text-xs font-semibold uppercase tracking-wide bg-primary-50 dark:bg-primary-950 text-primary-700 dark:text-primary-300 border border-gray-200 dark:border-gray-700">'
                .htmlspecialchars($group)
                .'</td></tr>';

            foreach ($variables as $key => $label) {
                $bg = $rowIndex % 2 === 0 ? '' : 'bg-gray-50 dark:bg-gray-900/40';
                $html .= '<tr class="'.$bg.'">'
                    .'<td class="py-1.5 px-2 border border-gray-200 dark:border-gray-700 font-mono text-xs text-primary-700 dark:text-primary-300 select-all">${'.htmlspecialchars($key).'}</td>'
                    .'<td class="py-1.5 px-2 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300">'.htmlspecialchars($label).'</td>'
                    .'</tr>';
                $rowIndex++;
            }
        }

        $html .= '</tbody></table></div>';

        return new HtmlString($html);
    }

    private static function renderParseResult(array $result, DocumentVariableRegistry $registry): HtmlString
    {
        $validKeys = $result['valid'] ?? [];
        $invalidKeys = $result['invalid'] ?? [];

        $html = '';

        if (! empty($validKeys)) {
            $html .= '<div class="mb-3"><p class="text-sm font-semibold text-success-700 dark:text-success-400 mb-1">✓ Variables reconocidas ('.count($validKeys).')</p>';
            $html .= '<div class="flex flex-wrap gap-1">';
            foreach ($validKeys as $key) {
                $label = $registry->all()[$key]['label'] ?? $key;
                $html .= '<span class="inline-flex items-center rounded bg-success-100 px-2 py-0.5 text-xs font-mono text-success-800">${'.$key.'}</span>';
            }
            $html .= '</div></div>';
        }

        if (! empty($invalidKeys)) {
            $html .= '<div><p class="text-sm font-semibold text-danger-700 dark:text-danger-400 mb-1">✗ Variables no reconocidas ('.count($invalidKeys).')</p>';
            $html .= '<p class="text-xs text-gray-500 mb-1">Estas variables no están en el catálogo y no serán sustituidas.</p>';
            $html .= '<div class="flex flex-wrap gap-1">';
            foreach ($invalidKeys as $key) {
                $html .= '<span class="inline-flex items-center rounded bg-danger-100 px-2 py-0.5 text-xs font-mono text-danger-800">${'.$key.'}</span>';
            }
            $html .= '</div></div>';
        }

        if (empty($validKeys) && empty($invalidKeys)) {
            $html = '<p class="text-sm text-gray-500">No se detectaron variables en la plantilla. Usa placeholders como <code class="font-mono">${member.first_name}</code>.</p>';
        }

        return new HtmlString($html);
    }

    private static function renderSavedVariables(array $keys, DocumentVariableRegistry $registry): HtmlString
    {
        if (empty($keys)) {
            return new HtmlString('<p class="text-sm text-gray-500">Sin variables guardadas.</p>');
        }

        $html = '<div class="flex flex-wrap gap-1">';
        foreach ($keys as $key) {
            $html .= '<span class="inline-flex items-center rounded bg-success-100 px-2 py-0.5 text-xs font-mono text-success-800">${'.$key.'}</span>';
        }
        $html .= '</div>';

        return new HtmlString($html);
    }

    private static function renderVariableCatalog(DocumentVariableRegistry $registry): HtmlString
    {
        $grouped = $registry->groupedForDisplay();
        $html = '<div class="space-y-3">';

        foreach ($grouped as $group => $variables) {
            $html .= '<div><p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">'.htmlspecialchars($group).'</p>';
            $html .= '<div class="flex flex-wrap gap-1">';
            foreach ($variables as $key => $label) {
                $html .= '<span title="'.htmlspecialchars($label).'" class="inline-flex items-center rounded bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs font-mono text-gray-700 dark:text-gray-300 cursor-help">${'.$key.'}</span>';
            }
            $html .= '</div></div>';
        }

        $html .= '</div>';

        return new HtmlString($html);
    }
}
