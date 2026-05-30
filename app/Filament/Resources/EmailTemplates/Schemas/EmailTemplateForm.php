<?php

namespace App\Filament\Resources\EmailTemplates\Schemas;

use App\Enums\EmailTemplateType;
use App\Services\Documents\DocumentVariableRegistry;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\ToolbarButtonGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class EmailTemplateForm
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
                        ->label('Concepto')
                        ->options(EmailTemplateType::class)
                        ->required()
                        ->native(false)
                        ->live()
                        ->columnSpanFull(),
                    Toggle::make('is_active')
                        ->label('Plantilla activa para este concepto')
                        ->helperText('Solo puede haber una plantilla activa por concepto. Al activar ésta se desactivarán las demás del mismo tipo.')
                        ->columnSpanFull(),
                ])
                ->columnSpan(1),

            Section::make('Contenido del email')
                ->schema([
                    TextInput::make('subject')
                        ->label('Asunto')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Puedes usar variables como ${member.first_name} en el asunto.')
                        ->columnSpanFull(),
                    RichEditor::make('body_html')
                        ->label('Cuerpo del mensaje')
                        ->required()
                        ->toolbarButtons([
                            ['bold', 'italic', 'underline', 'link'],
                            ['blockquote', 'bulletList', 'orderedList'],
                            [ToolbarButtonGroup::make('Alignment', ['alignStart', 'alignCenter', 'alignEnd', 'alignJustify'])],
                            ['textColor'],
                            [ToolbarButtonGroup::make('Heading', ['paragraph', 'h2', 'h3'])->icon('fi-o-heading')],
                            ['table'],
                            ['undo', 'redo'],
                        ])
                        ->extraInputAttributes(['class' => 'min-h-[300px]'])
                        ->columnSpanFull(),
                ])
                ->columnSpan(1),

            Section::make('Variables disponibles')
                ->schema([
                    Placeholder::make('_variable_catalog')
                        ->label('')
                        ->content(function (Get $get): HtmlString {
                            $raw = $get('type');
                            $value = $raw instanceof EmailTemplateType ? $raw->value : (string) ($raw ?? '');

                            return static::buildVariableCatalogHtml(EmailTemplateType::tryFrom($value));
                        }),
                ])
                ->collapsed()
                ->collapsible()
                ->columnSpanFull(),
        ])->columns(2);
    }

    private static function buildVariableCatalogHtml(?EmailTemplateType $type): HtmlString
    {
        $registry = app(DocumentVariableRegistry::class);
        $grouped = $registry->groupedForDisplay();

        $allowedGroups = $type?->availableVariableGroups();

        if ($allowedGroups !== null) {
            $grouped = array_filter($grouped, fn (string $group) => in_array($group, $allowedGroups, true), ARRAY_FILTER_USE_KEY);
        }

        if (empty($grouped)) {
            return new HtmlString('<p class="text-sm text-gray-500">Selecciona un concepto para ver las variables disponibles.</p>');
        }

        $intro = '<p class="text-sm text-gray-600 dark:text-gray-400 mb-4">'
            .'Escribe estas variables en el asunto o cuerpo del email exactamente como se muestran '
            .'(incluyendo <code class="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded text-xs">${</code> y '
            .'<code class="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded text-xs">}</code>). '
            .'Al enviar el email cada placeholder se sustituirá por el valor real del destinatario.'
            .'</p>';

        $html = $intro.'<div class="overflow-x-auto"><table class="w-full text-sm border-collapse">'
            .'<thead><tr>'
            .'<th class="text-left py-1.5 px-2 bg-gray-100 dark:bg-gray-800 font-semibold text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 w-1/2">Variable</th>'
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
}
