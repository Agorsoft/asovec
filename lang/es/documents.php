<?php

return [
    'variables' => [
        'association' => [
            'name'                => 'Nombre de la asociación',
            'tax_id'              => 'CIF/NIF de la asociación',
            'registration_entity' => 'Entidad de registro',
            'registration_number' => 'Número de registro',
            'email'               => 'Correo electrónico',
            'phone'               => 'Teléfono',
            'address'             => 'Dirección',
            'postal_code'         => 'Código postal',
            'city'                => 'Localidad',
            'province'            => 'Provincia',
            'country'             => 'País',
            'website'             => 'Web',
        ],
        'member' => [
            'member_number' => 'Número de socio',
            'first_name'    => 'Nombre',
            'last_name'     => 'Apellidos',
            'full_name'     => 'Nombre completo',
            'dni'           => 'DNI/NIF',
            'email'         => 'Correo electrónico',
            'phone'         => 'Teléfono',
            'address'       => 'Dirección',
            'city'          => 'Localidad',
            'province'      => 'Provincia',
            'postal_code'   => 'Código postal',
        ],
        'quota' => [
            'amount'       => 'Importe',
            'due_date'     => 'Fecha de vencimiento',
            'period_start' => 'Inicio del período',
            'period_end'   => 'Fin del período',
            'status'       => 'Estado',
        ],
        'quota_type' => [
            'name' => 'Tipo de cuota',
        ],
        'date' => [
            'today'      => 'Fecha de hoy (dd/mm/aaaa)',
            'today_long' => 'Fecha de hoy (texto)',
            'year'       => 'Año actual',
        ],
    ],
];
