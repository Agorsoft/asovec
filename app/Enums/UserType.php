<?php

namespace App\Enums;

enum UserType: string
{
    case Administrador = 'Administrador';
    case Presidente = 'Presidente';
    case Secretario = 'Secretario';
    case Vocal = 'Vocal';

    public function label(): string
    {
        return $this->value;
    }

    /** Roles cuyos permisos son configurables por el administrador. */
    public static function configurableRoles(): array
    {
        return [self::Presidente, self::Secretario, self::Vocal];
    }

    /**
     * Catálogo completo de permisos agrupados por sección.
     * La clave es el permission string; el valor es la etiqueta en UI.
     *
     * @return array<string, array<string, string>>
     */
    public static function permissionCatalog(): array
    {
        return [
            'Socios' => [
                'members.view' => 'Ver',
                'members.create' => 'Crear',
                'members.edit' => 'Editar',
            ],
            'Eventos' => [
                'events.view' => 'Ver',
                'events.create' => 'Crear',
                'events.edit' => 'Editar',
            ],
            'Cuotas' => [
                'quotas.view' => 'Ver',
                'quotas.create' => 'Crear',
                'quotas.edit' => 'Editar',
            ],
            'Inscripciones' => [
                'registrations.view' => 'Ver',
                'registrations.create' => 'Crear',
                'registrations.edit' => 'Editar',
            ],
            'Transacciones' => [
                'transactions.view' => 'Ver',
                'transactions.create' => 'Crear',
                'transactions.edit' => 'Editar',
            ],
            'Etiquetas' => [
                'labels.manage' => 'Crear y editar plantillas',
            ],
            'Documentos' => [
                'documents.manage' => 'Crear y editar plantillas',
            ],
        ];
    }

    /** Lista plana de todos los permission strings del catálogo. */
    public static function allPermissionKeys(): array
    {
        return array_keys(array_merge(...array_values(self::permissionCatalog())));
    }
}
