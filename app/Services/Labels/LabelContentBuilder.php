<?php

namespace App\Services\Labels;

use App\Enums\LabelField;
use App\Models\Member;

/**
 * Construye las líneas de texto que se imprimirán en cada etiqueta
 * a partir de un socio y la lista de campos seleccionados.
 */
final class LabelContentBuilder
{
    /**
     * @param  string[]  $fieldValues  Valores de LabelField::value seleccionados
     * @return string[] Líneas no vacías en orden canónico
     */
    public function buildLines(Member $member, array $fieldValues): array
    {
        $fields = array_map(fn (string $v) => LabelField::from($v), $fieldValues);

        // Orden canónico fijo independientemente del orden de selección
        usort($fields, fn (LabelField $a, LabelField $b) => $a->sortOrder() <=> $b->sortOrder());

        $lines = [];

        foreach ($fields as $field) {
            $line = $this->resolveField($member, $field);

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function resolveField(Member $member, LabelField $field): string
    {
        return match ($field) {
            LabelField::MemberNumber => $member->member_number
                ? 'Nº '.$member->member_number_formatted
                : '',
            LabelField::FullName => trim($member->full_name),
            LabelField::Address => trim((string) $member->address),
            LabelField::PostalCodeCity => $this->formatPostalCodeCity($member),
            LabelField::Province => trim((string) $member->province),
        };
    }

    /** Combina CP y ciudad en una sola línea: "28001 - Madrid". */
    private function formatPostalCodeCity(Member $member): string
    {
        $cp = trim((string) $member->postal_code);
        $city = trim((string) $member->city);

        if ($cp !== '' && $city !== '') {
            return "{$cp} - {$city}";
        }

        return $cp.$city;
    }
}
