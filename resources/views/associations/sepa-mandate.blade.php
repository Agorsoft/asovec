@extends('layouts.print')

@section('title', __('Mandato de Domiciliacion SEPA'))

@section('content')
<main class="sepa-page">
    <header class="sepa-header">
        <div aria-label="Logo acreedor">
            @if ($creditorLogoUrl)
                <img src="{{ $creditorLogoUrl }}" alt="{{ __('Logo del acreedor') }}" class="sepa-logo-img">
            @else
                <div class="sepa-logo-fallback">
                    <strong>{{ __('LOGO') }}</strong>
                    <div>{{ __('Acreedor') }}</div>
                </div>
            @endif
        </div>

        <div class="sepa-header-copy">
            <h1>{{ __('MANDATO DE DOMICILIACION') }}</h1>
            <p>{{ __('Autorizacion para adeudo directo SEPA') }}</p>
        </div>
    </header>

    <section class="sepa-section">
        <h2>{{ __('1. ACREEDOR') }}</h2>

        <div class="sepa-grid two">
            <div>
                <p class="sepa-label">{{ __('Nombre / Razon social') }}</p>
                <p class="sepa-value">{{ $creditorName ?: '____________________________' }}</p>
            </div>
            <div>
                <p class="sepa-label">{{ __('NIF / CIF') }}</p>
                <p class="sepa-value">{{ $creditorTaxId ?: '____________________________' }}</p>
            </div>
        </div>

        <div class="sepa-grid two">
            <div>
                <p class="sepa-label">{{ __('Identificador de acreedor SEPA') }}</p>
                <p class="sepa-value">{{ $creditorIdentifier ?: '____________________________' }}</p>
            </div>
            <div>
                <p class="sepa-label">{{ __('Direccion') }}</p>
                <p class="sepa-value">{{ $creditorAddress ?: '____________________________' }}</p>
            </div>
        </div>

        <div class="sepa-grid two">
            <div>
                <p class="sepa-label">{{ __('CP / Localidad / Provincia') }}</p>
                <p class="sepa-value">{{ ($creditorPostalCode ?: '_____') . ' - ' . ($creditorCity ?: '____________') . ' - ' . ($creditorProvince ?: '____________') }}</p>
            </div>
            <div>
                <p class="sepa-label">{{ __('Pais') }}</p>
                <p class="sepa-value">{{ $creditorCountry ?: 'ES' }}</p>
            </div>
        </div>
    </section>

    <section class="sepa-section">
        <h2>{{ __('2. ORDENANTE (DEUDOR)') }}</h2>

        <div class="sepa-grid two">
            <div>
                <p class="sepa-label">{{ __('Nombre completo') }}</p>
                <div class="sepa-line"></div>
            </div>
            <div>
                <p class="sepa-label">{{ __('DNI / Pasaporte') }}</p>
                <div class="sepa-line"></div>
            </div>
        </div>

        <div class="sepa-grid two">
            <div>
                <p class="sepa-label">{{ __('Direccion') }}</p>
                <div class="sepa-line"></div>
            </div>
            <div>
                <p class="sepa-label">{{ __('CP / Localidad / Provincia') }}</p>
                <div class="sepa-line"></div>
            </div>
        </div>

        <div class="sepa-grid two">
            <div>
                <p class="sepa-label">{{ __('Pais') }}</p>
                <div class="sepa-line"></div>
            </div>
            <div>
                <p class="sepa-label">{{ __('BIC') }}</p>
                <div class="sepa-line"></div>
            </div>
        </div>

        <div>
            <p class="sepa-label">{{ __('IBAN') }}</p>
            <div class="sepa-iban-box">{{ __('ES__ ____ ____ ____ ____ ____') }}</div>
        </div>
    </section>

    <section class="sepa-section">
        <h2>{{ __('3. TIPO DE PAGO Y AUTORIZACION') }}</h2>

        <p class="sepa-text">
            {{ __('Mediante la firma de este formulario de mandato, usted autoriza al acreedor a enviar instrucciones a su entidad para adeudar su cuenta y a su entidad para efectuar los adeudos conforme a dichas instrucciones.') }}
        </p>

        <div class="sepa-grid two compact">
            <div>
                <p class="sepa-label">{{ __('Tipo de pago') }}</p>
                <div class="sepa-checkbox-row">
                    <span>[ ] {{ __('Recurrente (RCUR)') }}</span>
                    <span>[ ] {{ __('Unico (OOFF)') }}</span>
                </div>
            </div>
            <div>
                <p class="sepa-label">{{ __('Referencia de mandato (UMR)') }}</p>
                <div class="sepa-line"></div>
            </div>
        </div>
    </section>

    <section class="sepa-signature">
        <div>
            <p class="sepa-label">{{ __('Lugar y fecha de firma') }}</p>
            <div class="sepa-line"></div>
        </div>
        <div>
            <p class="sepa-label">{{ __('Firma del ordenante') }}</p>
            <div class="sepa-sign-line"></div>
        </div>
    </section>

    <footer class="sepa-footer">
        {{ __('Documento generado el :date', ['date' => $issuedAt->format('d/m/Y')]) }}
    </footer>
</main>

<style>
    .sepa-page {
        width: 100%;
        max-width: 186mm;
        margin: 0 auto;
        background: #fff;
        color: #111827;
        font-size: 11pt;
        line-height: 1.35;
    }

    .sepa-header {
        display: grid;
        grid-template-columns: 120px 1fr;
        gap: 16px;
        margin-bottom: 20px;
        align-items: center;
    }

    .sepa-logo-img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .sepa-logo-fallback {
        text-align: center;
        color: #6b7280;
        font-size: 10pt;
    }

    .sepa-header-copy h1 {
        margin: 0;
        font-size: 18pt;
        letter-spacing: 0.02em;
    }

    .sepa-header-copy p {
        margin: 6px 0 0;
        color: #4b5563;
        font-size: 10.5pt;
    }

    .sepa-section {
        border: 1px solid #d1d5db;
        padding: 12px;
        margin-bottom: 10px;
        break-inside: avoid;
    }

    .sepa-section h2 {
        margin: 0 0 10px;
        font-size: 11.5pt;
    }

    .sepa-grid {
        display: grid;
        gap: 10px;
        margin-bottom: 8px;
    }

    .sepa-grid.two {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .sepa-grid.compact {
        margin-bottom: 0;
    }

    .sepa-label {
        margin: 0 0 3px;
        font-size: 9.5pt;
        color: #4b5563;
    }

    .sepa-value {
        margin: 0;
        font-size: 10.5pt;
        min-height: 18px;
    }

    .sepa-line {
        border-bottom: 1px solid #6b7280;
        min-height: 18px;
    }

    .sepa-iban-box {
        border: 1px dashed #6b7280;
        padding: 7px 10px;
        letter-spacing: 0.08em;
        font-size: 10.5pt;
        min-height: 20px;
    }

    .sepa-text {
        margin: 0 0 8px;
        font-size: 9.8pt;
    }

    .sepa-checkbox-row {
        display: flex;
        gap: 16px;
        font-size: 10pt;
    }

    .sepa-signature {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
        margin-top: 14px;
        margin-bottom: 10px;
    }

    .sepa-sign-line {
        border-bottom: 1px solid #111827;
        min-height: 30px;
    }

    .sepa-footer {
        border-top: 1px solid #d1d5db;
        padding-top: 7px;
        text-align: center;
        font-size: 9pt;
        color: #6b7280;
    }

    @media (max-width: 640px) {
        .sepa-grid.two,
        .sepa-signature,
        .sepa-header {
            grid-template-columns: 1fr;
        }
    }

    @media print {
        html,
        body {
            width: 100%;
            height: auto;
            background: #fff;
        }

        .sepa-page {
            max-width: 100%;
        }
    }
</style>
@endsection
