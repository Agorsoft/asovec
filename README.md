# MiAsovec — Gestión de Asociaciones

Aplicación web para la gestión integral de asociaciones: socios, cuotas, eventos, transacciones y documentación.

Construida con **Laravel 13**, **Filament 5**, **Livewire 4** y **Flux UI 2**.

---

## Características principales

- **Socios** — Ficha completa, estados, historial y número de socio.
- **Cuotas** — Tipos de cuota configurables, generación por periodos y recibos PDF.
- **Eventos e inscripciones** — Gestión de eventos con control de aforo, inscripciones y recibos PDF.
- **Transacciones** — Registro de cobros y pagos con referencia a cuota o inscripción.
- **Mandatos SEPA** — Gestión de mandatos de domiciliación bancaria con PDF descargable.
- **Plantillas de documentos** — Carga de plantillas `.docx` con variables sustituibles y exportación PDF.
- **Impresión de etiquetas** — Generación de etiquetas postales configurables (TCPDF).
- **Importación de socios** — Importación masiva desde CSV/Excel con Filament Imports.
- **Autenticación** — Laravel Fortify con soporte para autenticación de dos factores (2FA).
- **Instalador** — Asistente de primera instalación integrado en la propia web.

---

## Requisitos

| Requisito | Versión mínima |
|---|---|
| PHP | 8.3 |
| MySQL | 8.0 |
| Node.js | 18 |
| Composer | 2 |

---

## Instalación

```bash
# 1. Clonar el repositorio
git clone https://github.com/Agorsoft/asovec.git
cd asovec

# 2. Instalar dependencias PHP
composer install --no-dev --optimize-autoloader

# 3. Instalar dependencias JS y compilar assets
npm install && npm run build

# 4. Copiar y configurar el entorno
cp .env.example .env
php artisan key:generate

# 5. Configurar la base de datos en .env y migrar
php artisan migrate --seed

# 6. Enlazar el almacenamiento público
php artisan storage:link
```

A continuación, accede a la URL de la aplicación en tu navegador. El **asistente de instalación** se mostrará automáticamente para configurar los datos de la asociación y crear el usuario administrador.

---

## Desarrollo local

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
# Configura DB_* en .env, luego:
php artisan migrate --seed
composer run dev   # Inicia servidor Vite + PHP + queue
```

---

## Comandos artisan relevantes

| Comando | Descripción |
|---|---|
| `php artisan events:update-statuses` | Actualiza el estado de los eventos según su fecha |
| `php artisan migrate --seed` | Migra y carga los datos iniciales (plantillas de etiquetas) |

---

## Stack técnico

- **Backend**: Laravel 13, PHP 8.3
- **Panel de administración**: Filament 5
- **Frontend reactivo**: Livewire 4, Flux UI 2, Alpine.js, Tailwind CSS 4
- **Autenticación**: Laravel Fortify (con 2FA)
- **PDF**: dompdf 3, TCPDF / tc-lib-pdf
- **Documentos DOCX**: PhpWord (TemplateProcessor)
- **Tests**: Pest 4

---

## Licencia

Uso privado. Todos los derechos reservados.
