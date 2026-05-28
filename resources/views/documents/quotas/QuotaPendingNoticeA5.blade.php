<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Aviso de Cuota Pendiente</title>
	<style>
		* {
			margin: 0;
			padding: 0;
			box-sizing: border-box;
		}
		@page {
			size: A5 landscape;
			margin: 0;
		}
		body {
			font-family: 'Poppins', sans-serif;
			color: #000;
		}
		.page {
			width: 210mm;
			height: 148mm;
			margin: 0 auto;
		}
		.ereceipt-content {
			width: 180mm;
			height: 118mm;
			margin: 14mm;
			table-layout: fixed;
			border-collapse: collapse;
		}
		.ereceipt-content td {
			padding: 0;
		}
		.ereceipt-header {
			height: 20mm;
			font-size: 8pt;
			line-height: 1;
			vertical-align: middle;
		}
		.ereceipt-header td {
			height: 20mm;
			vertical-align: middle;
		}
		.ereceipt-header img {
			max-height: 18mm;
		}
		.ereceipt-header .header-contact {
			text-align: right;
		}
		.ereceipt-header .asociacion {
			font-size: 10pt;
			font-weight: bold;
		}
		.ereceipt-titulo {
			height: 8mm;
			text-align: center;
			font-size: 12pt;
			font-weight: bold;
			vertical-align: middle;
		}
		.ereceipt-titulo td {
			height: 8mm;
			border-top: 0.5mm solid #000;
			border-bottom: 0.5mm solid #000;
			vertical-align: middle;
		}
		.ereceipt-nombre {
			height: 12mm;
			text-align: center;
			font-size: 12pt;
			font-weight: bold;
			vertical-align: bottom;
		}
		.ereceipt-nombre td {
			height: 12mm;
			vertical-align: bottom;
		}
		.ereceipt-datos {
			height: 48mm;
			font-size: 10pt;
			line-height: 2;
			vertical-align: middle;
		}
		.ereceipt-datos td {
			height: 48mm;
			vertical-align: middle;
		}
		.ereceipt-datos span {
			font-size: 12pt;
		}
		.ereceipt-footer {
			height: 30mm;
			vertical-align: bottom;
		}
		.ereceipt-footer td {
			height: 30mm;
			vertical-align: bottom;
		}
		.ereceipt-footer .footer-left {
			text-align: center;
			font-size: 8pt;
		}
		.ereceipt-footer .footer-right {
			text-align: right;
			font-size: 16pt;
			font-weight: bold;
		}
	</style>
</head>
<body>
	@php
		$logoPath = null;
		$periodStartDate = $quota->period_start
			? \Illuminate\Support\Carbon::parse($quota->period_start)->format('d/m/Y')
			: '-';
		$periodEndDate = $quota->period_end
			? \Illuminate\Support\Carbon::parse($quota->period_end)->format('d/m/Y')
			: '-';
		$dueDateFormatted = $quota->due_date
			? \Illuminate\Support\Carbon::parse($quota->due_date)->format('d/m/Y')
			: '-';

		if ($association->logo_path && file_exists(storage_path('app/public/' . ltrim($association->logo_path, '/')))) {
			$logoPath = storage_path('app/public/' . ltrim($association->logo_path, '/'));
		} elseif (file_exists(public_path('logo.png'))) {
			$logoPath = public_path('logo.png');
		}
	@endphp
	<div class="page">
		<div class="page-content">
			<table class="ereceipt-content">
				<tbody>
					<tr class="ereceipt-header">
						<td class="header-logo">
							@if ($logoPath)
								<img src="{{ $logoPath }}" alt="Logo {{ $association->name }}">
							@else
								<span class="logo-fallback">{{ $association->name }}</span>
							@endif
						</td>
						<td class="header-contact">
							<p class="asociacion">{{ $association->name }}</p>
							<p>CIF: {{ $association->tax_id }}</p>
							<p>{{ $association->address }}</p>
							<p>Tel: {{ $association->phone }} Email: {{ $association->email }}</p>
						</td>
					</tr>
					<tr class="ereceipt-titulo">
						<td colspan="2">
							AVISO DE CUOTA PENDIENTE
						</td>
					</tr>
					<tr class="ereceipt-nombre">
						<td colspan="2">{{ $quota->quotaType?->name ?? 'Cuota de socio' }}</td>
					</tr>
					<tr class="ereceipt-datos">
						<td class="event-left">
							<p>Período:<br>
								<span>{{ $periodStartDate }}</span> - <span>{{ $periodEndDate }}</span></p>
							<p>Vencimiento: <span>{{ $dueDateFormatted }}</span></p>
						</td>
						<td class="event-right">
							<p>Socio:<br>
								<span><strong>{{ $quota->member?->full_name ?? '-' }}</strong></span></p>
							<p>Nº socio: <span>{{ $quota->member?->member_number_formatted ?? '-' }}</span></p>
						</td>
					</tr>
					<tr class="ereceipt-footer">
						<td class="footer-left">
							<p>Fecha de aviso: {{ $noticeDate }}</p>
						</td>
						<td class="footer-right">
							<p class="total">TOTAL: {{ number_format((float) ($quota->amount ?? 0), 2, ',', '.') }} EUR</p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</body>
</html>
