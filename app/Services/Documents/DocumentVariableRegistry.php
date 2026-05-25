<?php

namespace App\Services\Documents;

use App\Models\Association;
use App\Models\Member;
use App\Models\Quota;
use App\Models\QuotaType;
use Illuminate\Support\Carbon;

/**
 * Catálogo centralizado de variables disponibles para plantillas DOCX.
 *
 * Cada variable tiene:
 *  - key:      identificador único usado en ${key}
 *  - label:    etiqueta traducible para mostrar al usuario
 *  - group:    agrupación visual
 *  - resolver: closure que recibe el contexto y devuelve el valor string
 *
 * Para añadir soporte a un nuevo modelo basta con registrar sus variables aquí.
 */
class DocumentVariableRegistry
{
    /** @var array<string, array{key: string, label: string, group: string, resolver: \Closure}> */
    private array $variables = [];

    public function __construct()
    {
        $this->registerAssociationVariables();
        $this->registerMemberVariables();
        $this->registerQuotaVariables();
        $this->registerQuotaTypeVariables();
        $this->registerDateVariables();
    }

    private function registerAssociationVariables(): void
    {
        $vars = [
            'association.name'                => __('documents.variables.association.name'),
            'association.tax_id'              => __('documents.variables.association.tax_id'),
            'association.registration_entity' => __('documents.variables.association.registration_entity'),
            'association.registration_number' => __('documents.variables.association.registration_number'),
            'association.email'               => __('documents.variables.association.email'),
            'association.phone'               => __('documents.variables.association.phone'),
            'association.address'             => __('documents.variables.association.address'),
            'association.postal_code'         => __('documents.variables.association.postal_code'),
            'association.city'                => __('documents.variables.association.city'),
            'association.province'            => __('documents.variables.association.province'),
            'association.country'             => __('documents.variables.association.country'),
            'association.website'             => __('documents.variables.association.website'),
        ];

        foreach ($vars as $key => $label) {
            $field = str_replace('association.', '', $key);
            $this->register($key, $label, 'Asociación', fn (array $ctx) => (string) (Association::first()?->{$field} ?? ''));
        }
    }

    private function registerMemberVariables(): void
    {
        $vars = [
            'member.member_number' => __('documents.variables.member.member_number'),
            'member.first_name'    => __('documents.variables.member.first_name'),
            'member.last_name'     => __('documents.variables.member.last_name'),
            'member.full_name'     => __('documents.variables.member.full_name'),
            'member.dni'           => __('documents.variables.member.dni'),
            'member.email'         => __('documents.variables.member.email'),
            'member.phone'         => __('documents.variables.member.phone'),
            'member.address'       => __('documents.variables.member.address'),
            'member.city'          => __('documents.variables.member.city'),
            'member.province'      => __('documents.variables.member.province'),
            'member.postal_code'   => __('documents.variables.member.postal_code'),
        ];

        foreach ($vars as $key => $label) {
            $field = str_replace('member.', '', $key);
            $this->register($key, $label, 'Socio', function (array $ctx) use ($field): string {
                /** @var Member|null $member */
                $member = $ctx['member'] ?? null;
                if ($member === null) {
                    return '';
                }

                return match ($field) {
                    'full_name'  => mb_convert_case(trim($member->first_name . ' ' . $member->last_name), MB_CASE_TITLE),
                    'first_name' => mb_convert_case((string) ($member->first_name ?? ''), MB_CASE_TITLE),
                    'last_name'  => mb_convert_case((string) ($member->last_name ?? ''), MB_CASE_TITLE),
                    'email'      => mb_strtolower((string) ($member->email ?? '')),
                    default      => (string) ($member->{$field} ?? ''),
                };
            });
        }
    }

    private function registerQuotaVariables(): void
    {
        $vars = [
            'quota.amount'       => __('documents.variables.quota.amount'),
            'quota.due_date'     => __('documents.variables.quota.due_date'),
            'quota.period_start' => __('documents.variables.quota.period_start'),
            'quota.period_end'   => __('documents.variables.quota.period_end'),
            'quota.status'       => __('documents.variables.quota.status'),
        ];

        foreach ($vars as $key => $label) {
            $field = str_replace('quota.', '', $key);
            $this->register($key, $label, 'Cuota', function (array $ctx) use ($field): string {
                /** @var Quota|null $quota */
                $quota = $ctx['quota'] ?? null;
                if ($quota === null) {
                    return '';
                }

                return match ($field) {
                    'amount'       => number_format((float) $quota->amount, 2, ',', '.') . ' €',
                    'due_date'     => $quota->due_date ? Carbon::parse($quota->due_date)->format('d/m/Y') : '',
                    'period_start' => $quota->period_start ? Carbon::parse($quota->period_start)->format('d/m/Y') : '',
                    'period_end'   => $quota->period_end ? Carbon::parse($quota->period_end)->format('d/m/Y') : '',
                    default        => (string) ($quota->{$field} ?? ''),
                };
            });
        }
    }

    private function registerQuotaTypeVariables(): void
    {
        $this->register(
            'quota_type.name',
            __('documents.variables.quota_type.name'),
            'Tipo de Cuota',
            function (array $ctx): string {
                /** @var QuotaType|null $quotaType */
                $quotaType = $ctx['quota_type'] ?? ($ctx['quota'] ?? null)?->quotaType ?? null;

                return (string) ($quotaType?->name ?? '');
            }
        );
    }

    private function registerDateVariables(): void
    {
        $this->register('date.today', __('documents.variables.date.today'), 'Fecha', fn (array $ctx) => Carbon::today()->format('d/m/Y'));
        $this->register('date.today_long', __('documents.variables.date.today_long'), 'Fecha', fn (array $ctx) => Carbon::today()->isoFormat('D [de] MMMM [de] YYYY'));
        $this->register('date.year', __('documents.variables.date.year'), 'Fecha', fn (array $ctx) => (string) Carbon::today()->year);
    }

    /**
     * Registra una variable en el catálogo.
     */
    public function register(string $key, string $label, string $group, \Closure $resolver): void
    {
        $this->variables[$key] = compact('key', 'label', 'group', 'resolver');
    }

    /**
     * Devuelve todas las variables registradas.
     *
     * @return array<string, array{key: string, label: string, group: string, resolver: \Closure}>
     */
    public function all(): array
    {
        return $this->variables;
    }

    /**
     * Devuelve todas las claves válidas registradas.
     *
     * @return array<string>
     */
    public function validKeys(): array
    {
        return array_keys($this->variables);
    }

    /**
     * Variables agrupadas por grupo para mostrar en formularios.
     *
     * @return array<string, array<string, string>>
     */
    public function groupedForDisplay(): array
    {
        $grouped = [];
        foreach ($this->variables as $variable) {
            $grouped[$variable['group']][$variable['key']] = $variable['label'];
        }

        return $grouped;
    }

    /**
     * Resuelve un mapa clave → valor para el contexto dado.
     *
     * @param  array<string>                 $keys
     * @param  array<string, mixed>          $context
     * @return array<string, string>
     */
    public function resolve(array $keys, array $context): array
    {
        $values = [];
        foreach ($keys as $key) {
            $variable = $this->variables[$key] ?? null;
            $values[$key] = $variable !== null ? ($variable['resolver'])($context) : '';
        }

        return $values;
    }
}
