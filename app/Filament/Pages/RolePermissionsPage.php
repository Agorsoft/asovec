<?php

namespace App\Filament\Pages;

use App\Enums\UserType;
use App\Models\Association;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class RolePermissionsPage extends Page implements HasForms
{
    use InteractsWithForms;

    // protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Permisos por rol';

    protected static ?string $title = 'Permisos por rol';

    protected static UnitEnum|string|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.role-permissions';

    public ?array $data = [];

    public function mount(): void
    {
        $permissions = Association::first()?->role_permissions ?? [];
        $this->form->fill($permissions);
    }

    public function form(Schema $schema): Schema
    {
        $catalog = UserType::permissionCatalog();

        // Build flat options list for CheckboxList: "Sección → Etiqueta"
        $options = [];
        foreach ($catalog as $section => $perms) {
            foreach ($perms as $key => $label) {
                $options[$key] = "{$section} · {$label}";
            }
        }

        $tabs = array_map(
            fn (UserType $role) => Tab::make($role->label())
                ->schema([
                    CheckboxList::make($role->value)
                        ->label('')
                        ->options($options)
                        ->columns(3)
                        ->bulkToggleable()
                        ->gridDirection('row'),
                ]),
            UserType::configurableRoles()
        );

        return $schema->components([
            Section::make('Permisos por rol')
                ->description('Selecciona qué puede hacer cada rol. Los Administradores siempre tienen acceso total. Imprimir etiquetas y documentos está disponible para todos los roles.')
                ->schema([
                    Tabs::make('roles')
                        ->contained(false)
                        ->tabs($tabs),
                ]),
        ])->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar permisos')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $association = Association::first();

        if (! $association) {
            Notification::make()
                ->title('No existe ninguna asociación configurada.')
                ->danger()
                ->send();

            return;
        }

        $association->update(['role_permissions' => $this->form->getState()]);

        Notification::make()
            ->title('Permisos guardados correctamente.')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->is_admin
            || auth()->user()?->user_type?->value === 'Administrador'
            ?? false;
    }
}
