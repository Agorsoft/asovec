<?php

use App\Filament\Resources\DocumentTemplates\Pages\CreateDocumentTemplate;
use App\Filament\Resources\DocumentTemplates\Pages\EditDocumentTemplate;
use App\Filament\Resources\DocumentTemplates\Pages\ListDocumentTemplates;
use App\Models\DocumentTemplate;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;

covers(DocumentTemplate::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('lista plantillas de documentos', function () {
    $templates = DocumentTemplate::factory()->count(3)->create();

    Livewire::test(ListDocumentTemplates::class)
        ->assertCanSeeTableRecords($templates);
});


test('valida campos requeridos al crear', function () {
    Livewire::test(CreateDocumentTemplate::class)
        ->fillForm([
            'name'      => null,
            'type'      => null,
            'file_path' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'type' => 'required', 'file_path' => 'required'])
        ->assertNotNotified();
});

test('edita nombre de plantilla', function () {
    $template = DocumentTemplate::factory()->create(['name' => 'Original']);

    Livewire::test(EditDocumentTemplate::class, ['record' => $template->id])
        ->fillForm(['name' => 'Actualizada'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    assertDatabaseHas(DocumentTemplate::class, ['id' => $template->id, 'name' => 'Actualizada']);
});
