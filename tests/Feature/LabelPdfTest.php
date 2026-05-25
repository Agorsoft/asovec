<?php

use App\DataTransferObjects\Labels\PrintJobConfig;
use App\Enums\LabelField;
use App\Models\LabelTemplate;
use App\Models\Member;
use App\Services\Labels\LabelGeometryCalculator;
use App\Services\Labels\LabelPdfGenerator;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function makeTemplate(array $overrides = []): LabelTemplate
{
    return LabelTemplate::factory()->make(array_merge([
        'id' => 1,
        'name' => 'Test Template',
        'page_width_mm' => 210.0,
        'page_height_mm' => 297.0,
        'columns' => 3,
        'rows' => 8,
        'label_width_mm' => 64.0,
        'label_height_mm' => 33.9,
        'margin_top_mm' => 13.5,
        'margin_left_mm' => 7.2,
        'horizontal_gap_mm' => 2.5,
        'vertical_gap_mm' => 0.0,
        'font_size' => 10,
        'line_height' => 1.3,
        'inner_padding_mm' => 2.0,
    ], $overrides));
}

// ──────────────────────────────────────────────────────────────────────────────
// LabelGeometryCalculator
// ──────────────────────────────────────────────────────────────────────────────

describe('LabelGeometryCalculator', function () {
    it('calcula la posición de la primera etiqueta correctamente', function () {
        $template = makeTemplate();
        $calc = new LabelGeometryCalculator($template);

        $pos = $calc->position(0);

        expect($pos->x)->toBe(round($template->margin_left_mm, 4))
            ->and($pos->y)->toBe(round($template->margin_top_mm, 4))
            ->and($pos->page)->toBe(0)
            ->and($pos->column)->toBe(0)
            ->and($pos->row)->toBe(0);
    });

    it('calcula columna correctamente para la segunda etiqueta', function () {
        $template = makeTemplate();
        $calc = new LabelGeometryCalculator($template);

        $pos = $calc->position(1);

        expect($pos->column)->toBe(1)
            ->and($pos->row)->toBe(0)
            ->and($pos->x)->toBe(round($template->margin_left_mm + $template->label_width_mm + $template->horizontal_gap_mm, 4));
    });

    it('calcula fila correctamente al pasar a segunda fila', function () {
        $template = makeTemplate();
        $calc = new LabelGeometryCalculator($template);

        $pos = $calc->position(3); // columnas=3 → índice 3 = fila 1, columna 0

        expect($pos->row)->toBe(1)
            ->and($pos->column)->toBe(0);
    });

    it('detecta el cambio de página correctamente', function () {
        $template = makeTemplate(); // 3×8 = 24 por página
        $calc = new LabelGeometryCalculator($template);

        $posLastFirstPage = $calc->position(23);
        $posFirstSecondPage = $calc->position(24);

        expect($posLastFirstPage->page)->toBe(0)
            ->and($posFirstSecondPage->page)->toBe(1)
            ->and($posFirstSecondPage->column)->toBe(0)
            ->and($posFirstSecondPage->row)->toBe(0);
    });

    it('calcula el total de páginas necesarias', function () {
        $template = makeTemplate(); // 24 por página
        $calc = new LabelGeometryCalculator($template);

        expect($calc->totalPages(24, 0))->toBe(1)
            ->and($calc->totalPages(25, 0))->toBe(2)
            ->and($calc->totalPages(1, 23))->toBe(1)
            ->and($calc->totalPages(1, 24))->toBe(2)
            ->and($calc->totalPages(0, 0))->toBe(0);
    });

    it('aplica el startOffset correctamente', function () {
        $template = makeTemplate();
        $calc = new LabelGeometryCalculator($template);

        // Empezar en posición 5 (offset=4): el índice global 4 → columna 1, fila 1
        $pos = $calc->position(4);

        expect($pos->row)->toBe(1)
            ->and($pos->column)->toBe(1);
    });
});

// ──────────────────────────────────────────────────────────────────────────────
// LabelPdfGenerator — integración real con tc-lib-pdf
// ──────────────────────────────────────────────────────────────────────────────

describe('LabelPdfGenerator', function () {
    beforeEach(function () {
        $this->template = makeTemplate();
        $this->generator = new LabelPdfGenerator();
    });

    it('genera un PDF válido con socios reales', function () {
        $members = Member::factory()->count(3)->make();

        $config = new PrintJobConfig(
            template: $this->template,
            members: collect($members),
            fields: LabelField::defaultSelection(),
        );

        $pdf = $this->generator->generate($config);

        expect($pdf)->toStartWith('%PDF-');
    });

    it('genera una hoja de prueba válida', function () {
        $config = new PrintJobConfig(
            template: $this->template,
            members: collect([]),
            fields: LabelField::defaultSelection(),
            testSheet: true,
        );

        $pdf = $this->generator->generate($config);

        expect($pdf)->toStartWith('%PDF-');
    });

    it('genera múltiples páginas cuando hay más socios que etiquetas por hoja', function () {
        $members = Member::factory()->count(30)->make(); // 30 > 24 etiquetas

        $config = new PrintJobConfig(
            template: $this->template,
            members: collect($members),
            fields: LabelField::defaultSelection(),
        );

        $pdf = $this->generator->generate($config);

        expect($pdf)->toStartWith('%PDF-');
    });

    it('respeta la posición de inicio', function () {
        $members = Member::factory()->count(2)->make();

        $config = new PrintJobConfig(
            template: $this->template,
            members: collect($members),
            fields: LabelField::defaultSelection(),
            startPosition: 10,
        );

        $pdf = $this->generator->generate($config);

        expect($pdf)->toStartWith('%PDF-');
    });
});

// ──────────────────────────────────────────────────────────────────────────────
// HTTP — ruta de descarga
// ──────────────────────────────────────────────────────────────────────────────

describe('LabelPrintController', function () {
    beforeEach(function () {
        $this->actingAs(\App\Models\User::factory()->create());
        $this->template = LabelTemplate::factory()->create(makeTemplate()->toArray());
    });

    it('devuelve un PDF al llamar a la ruta con socios existentes', function () {
        Member::factory()->count(3)->create();

        $response = $this->get(route('labels.print.generate', [
            'template_id' => $this->template->id,
            'fields' => 'full_name,address',
            'start' => 1,
            'test' => 0,
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    });

    it('genera hoja de prueba correctamente', function () {
        $response = $this->get(route('labels.print.generate', [
            'template_id' => $this->template->id,
            'fields' => 'full_name',
            'test' => 1,
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    });

    it('falla la validación si no se proporciona template_id', function () {
        $response = $this->get(route('labels.print.generate', [
            'fields' => 'full_name',
        ]));

        $response->assertSessionHasErrors('template_id');
    });
});
