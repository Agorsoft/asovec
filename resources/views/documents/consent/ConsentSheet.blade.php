@extends('layouts.print')

@section('title', 'Hoja de Consentimiento — ' . $member->full_name)

@section('content')
<style>
    .consent-page {
        max-width: 800px;
        margin: 0 auto;
        background: #fff;
        padding: 40px 50px;
        border: 1px solid #e5e7eb;
    }

    .consent-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 2px solid #111827;
        padding-bottom: 16px;
        margin-bottom: 24px;
        gap: 16px;
    }

    .consent-header-logo img {
        max-height: 60px;
        max-width: 160px;
        object-fit: contain;
    }

    .consent-header-info {
        text-align: right;
        flex: 1;
    }

    .consent-header-info h1 {
        font-size: 14pt;
        font-weight: bold;
        text-transform: uppercase;
        margin: 0 0 4px;
    }

    .consent-header-info .assoc-name {
        font-size: 10pt;
        color: #374151;
    }

    .consent-title {
        text-align: center;
        font-size: 13pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 28px;
        padding: 10px;
        border: 1px solid #d1d5db;
        background: #f9fafb;
    }

    .consent-section {
        margin-bottom: 22px;
    }

    .consent-section h2 {
        font-size: 10pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #111827;
        border-left: 3px solid #111827;
        padding-left: 8px;
        margin-bottom: 12px;
    }

    .consent-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px 20px;
    }

    .consent-field label {
        font-size: 8pt;
        color: #6b7280;
        display: block;
        margin-bottom: 2px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .consent-field .value {
        font-size: 10pt;
        border-bottom: 1px solid #d1d5db;
        padding-bottom: 3px;
        min-height: 18px;
    }

    .consent-field.full {
        grid-column: 1 / -1;
    }

    .opt-in-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .opt-in-item {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 10pt;
    }

    .opt-in-check {
        width: 16px;
        height: 16px;
        border: 1.5px solid #374151;
        border-radius: 3px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 12px;
        font-weight: bold;
        line-height: 1;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .opt-in-check.checked {
        background: #111827;
        color: #fff;
    }

    .opt-in-label {
        flex: 1;
    }

    .opt-in-label strong {
        display: block;
        font-size: 10pt;
    }

    .opt-in-label span {
        font-size: 8.5pt;
        color: #6b7280;
    }

    .gdpr-box {
        font-size: 8pt;
        color: #374151;
        border: 1px solid #d1d5db;
        padding: 12px 14px;
        background: #f9fafb;
        line-height: 1.5;
    }

    .gdpr-box p {
        margin-bottom: 6px;
    }

    .gdpr-box p:last-child {
        margin-bottom: 0;
    }

    .gdpr-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px 20px;
        margin-top: 8px;
    }

    .gdpr-row .gdpr-item label {
        font-weight: bold;
        display: block;
        margin-bottom: 2px;
    }

    .consent-footer {
        margin-top: 30px;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 40px;
    }

    .consent-footer .date-block {
        font-size: 10pt;
    }

    .consent-footer .date-block .date-label {
        font-size: 8pt;
        color: #6b7280;
        text-transform: uppercase;
        margin-bottom: 4px;
    }

    .consent-footer .date-line {
        border-bottom: 1px solid #374151;
        width: 160px;
        min-height: 22px;
    }

    .consent-signature-block {
        flex: 1;
        text-align: center;
    }

    .consent-signature-block .sig-line {
        border-bottom: 1px solid #374151;
        min-height: 50px;
        margin-bottom: 8px;
    }

    .consent-signature-block .sig-label {
        font-size: 8pt;
        color: #6b7280;
        text-transform: uppercase;
    }

    @media print {
        .consent-page {
            border: none;
            padding: 20px 0;
            max-width: 100%;
        }
    }
</style>

<main class="consent-page">

    {{-- Cabecera --}}
    <header class="consent-header">
        <div class="consent-header-logo">
            @php
                $logoUrl = null;
                if ($association && $association->logo_path) {
                    $logoUrl = asset('storage/' . ltrim($association->logo_path, '/'));
                }
            @endphp
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="Logo {{ $association->name }}">
            @endif
        </div>
        <div class="consent-header-info">
            <h1>Hoja de consentimiento</h1>
            <div class="assoc-name">{{ $association?->name ?? '' }}</div>
        </div>
    </header>

    {{-- Título --}}
    <div class="consent-title">Consentimiento para el envío de comunicaciones</div>

    {{-- Datos del interesado --}}
    <section class="consent-section">
        <h2>Datos del interesado</h2>
        <div class="consent-grid">
            <div class="consent-field">
                <label>Nombre completo</label>
                <div class="value">{{ $member->full_name }}</div>
            </div>
            <div class="consent-field">
                <label>DNI / NIE / Pasaporte</label>
                <div class="value">{{ $member->dni ?? '' }}</div>
            </div>
            @if ($member->member_number)
                <div class="consent-field">
                    <label>N.º de socio</label>
                    <div class="value">{{ $member->member_number_formatted }}</div>
                </div>
            @endif
            <div class="consent-field">
                <label>Correo electrónico</label>
                <div class="value">{{ $member->email ?? '' }}</div>
            </div>
            <div class="consent-field">
                <label>Teléfono</label>
                <div class="value">{{ $member->phone ?? '' }}</div>
            </div>
        </div>
    </section>

    {{-- Consentimientos de comunicación --}}
    <section class="consent-section">
        <h2>Consentimientos de comunicación</h2>
        <p style="font-size:9pt; color:#374151; margin-bottom:12px;">
            Por favor, marque los canales por los que desea recibir comunicaciones de
            <strong>{{ $association?->name ?? 'la asociación' }}</strong>:
        </p>
        <div class="opt-in-list">
            <div class="opt-in-item">
                <div class="opt-in-check {{ $member->whatsapp_opt_in ? 'checked' : '' }}">@if($member->whatsapp_opt_in)✓@endif</div>
                <div class="opt-in-label">
                    <strong>WhatsApp</strong>
                    <span>Acepto recibir comunicaciones a través de WhatsApp al número de teléfono indicado.</span>
                </div>
            </div>
            <div class="opt-in-item">
                <div class="opt-in-check {{ $member->sms_opt_in ? 'checked' : '' }}">@if($member->sms_opt_in)✓@endif</div>
                <div class="opt-in-label">
                    <strong>SMS</strong>
                    <span>Acepto recibir mensajes SMS al número de teléfono indicado.</span>
                </div>
            </div>
            <div class="opt-in-item">
                <div class="opt-in-check {{ $member->email_opt_in ? 'checked' : '' }}">@if($member->email_opt_in)✓@endif</div>
                <div class="opt-in-label">
                    <strong>Correo electrónico</strong>
                    <span>Acepto recibir comunicaciones por correo electrónico a la dirección indicada.</span>
                </div>
            </div>
        </div>
    </section>

    {{-- Información sobre protección de datos --}}
    <section class="consent-section">
        <h2>Información básica sobre protección de datos</h2>
        <div class="gdpr-box">
            <div class="gdpr-row">
                <div class="gdpr-item">
                    <label>Responsable:</label>
                    {{ $association?->name ?? '________________' }}
                </div>
                <div class="gdpr-item">
                    <label>NIF/CIF:</label>
                    {{ $association?->tax_id ?? '________________' }}
                </div>
                <div class="gdpr-item">
                    <label>Finalidad:</label>
                    Envío de comunicaciones e información relacionada con las actividades de la asociación.
                </div>
                <div class="gdpr-item">
                    <label>Legitimación:</label>
                    Consentimiento del interesado (art. 6.1.a RGPD).
                </div>
                <div class="gdpr-item">
                    <label>Derechos:</label>
                    Acceso, rectificación, supresión, limitación, portabilidad y oposición, dirigiéndose a
                    {{ $association?->email ?? $association?->address ?? '________________' }}.
                </div>
                <div class="gdpr-item">
                    <label>Más información:</label>
                    Puede retirar su consentimiento en cualquier momento sin que ello afecte a la licitud del tratamiento basado en el consentimiento previo.
                </div>
            </div>
        </div>
    </section>

    {{-- Firma --}}
    <footer class="consent-footer">
        <div class="date-block">
            <div class="date-label">Lugar y fecha</div>
            <div class="date-line">
                {{ $association?->city ? $association->city . ', ' : '' }}{{ now()->format('d/m/Y') }}
            </div>
        </div>
        <div class="consent-signature-block">
            <div class="sig-line"></div>
            <div class="sig-label">Firma del interesado</div>
        </div>
    </footer>

</main>
@endsection
