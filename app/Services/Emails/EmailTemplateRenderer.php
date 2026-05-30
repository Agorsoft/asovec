<?php

namespace App\Services\Emails;

use App\Enums\EmailTemplateType;
use App\Models\EmailTemplate;
use App\Services\Documents\DocumentVariableRegistry;

/**
 * Renderiza plantillas de email sustituyendo variables ${key} por sus valores reales.
 *
 * El contexto acepta las mismas claves que DocumentVariableRegistry:
 *   - 'member'     → App\Models\Member
 *   - 'quota'      → App\Models\Quota
 *   - 'quota_type' → App\Models\QuotaType  (opcional, se resuelve desde quota si no se pasa)
 */
class EmailTemplateRenderer
{
    public function __construct(
        private readonly DocumentVariableRegistry $registry
    ) {}

    /**
     * Renderiza la plantilla activa para el tipo dado y devuelve asunto + cuerpo HTML resueltos.
     *
     * @param  array<string, mixed>  $context
     * @return array{subject: string, body_html: string}|null Null si no hay plantilla activa.
     */
    public function renderActive(EmailTemplateType $type, array $context): ?array
    {
        $template = EmailTemplate::activeForType($type);

        if ($template === null) {
            return null;
        }

        return $this->render($template, $context);
    }

    /**
     * Renderiza una plantilla concreta con el contexto dado.
     *
     * @param  array<string, mixed>  $context
     * @return array{subject: string, body_html: string}
     */
    public function render(EmailTemplate $template, array $context): array
    {
        $allKeys = $this->registry->validKeys();
        $resolved = $this->registry->resolve($allKeys, $context);

        return [
            'subject' => $this->substitute($template->subject, $resolved),
            'body_html' => $this->substitute($template->body_html, $resolved),
        ];
    }

    /**
     * Sustituye los placeholders ${key} en el texto por los valores resueltos.
     *
     * @param  array<string, string>  $values
     */
    private function substitute(string $text, array $values): string
    {
        foreach ($values as $key => $value) {
            $text = str_replace('${'.$key.'}', $value, $text);
        }

        return $text;
    }
}
