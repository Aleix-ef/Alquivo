<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dossier fiscal Alquivo {{ $report['year'] }}</title>
    <style>
        @page { margin: 42px 42px 52px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.5; color: #203344; }
        h1 { font-size: 29px; font-weight: normal; margin: 18px 0 6px; }
        h2 { font-size: 18px; margin: 0 0 8px; }
        h3 { font-size: 12px; margin: 18px 0 6px; page-break-after: avoid; }
        p { margin: 5px 0; }
        .brand { font-size: 20px; color: #087f74; font-weight: bold; }
        .muted { color: #5d6f80; }
        .note { background: #edf5f3; border-left: 3px solid #087f74; padding: 12px; margin: 16px 0; }
        .warning { background: #fff6e8; padding: 10px; margin: 8px 0; }
        .property { page-break-before: always; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0; table-layout: fixed; }
        th { text-align: left; background: #edf2f6; font-size: 9px; }
        td, th { padding: 7px; border-bottom: 1px solid #dce4e9; vertical-align: top; overflow-wrap: break-word; }
        .number { text-align: right; }
        .small { font-size: 8px; }
        .source { margin: 8px 0; }
        a { color: #087f74; text-decoration: none; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
@php($money = fn ($cents) => $cents === null ? 'Pendiente' : number_format($cents / 100, 2, ',', '.').' €')
<div class="brand">alquivo</div>
<h1>Tu dossier fiscal {{ $report['year'] }}</h1>
<p>{{ $report['profile']['name'] }} · {{ $report['property_count'] }} inmueble(s)</p>
<p class="muted">Informe {{ $report['report_id'] }} · Generado {{ $report['generated_at'] }}</p>
<div class="note">{{ $report['notice'] }}</div>
<h2>{{ $report['partial'] ? 'Resumen parcial' : 'Resumen de tus inmuebles' }}</h2>
<p>{{ $report['calculated_count'] }} de {{ $report['property_count'] }} inmuebles calculados. Importes correspondientes a tu porcentaje de titularidad.</p>
@if($report['partial'])
<p class="warning">Los totales solo incluyen inmuebles calculados. Los datos pendientes aparecen en las fichas siguientes y no se han convertido en cero.</p>
@endif
<table>
@foreach($report['figure_labels'] as $key => $label)
<tr><td>{{ $label }}</td><td class="number">{{ $money($report['totals'][$key]) }}</td></tr>
@endforeach
</table>
<p class="muted">El rendimiento fiscal y la renta imputada se muestran por separado. El impuesto personal depende de tus demás rentas y circunstancias; esta versión no lo estima.</p>
<h3>Revisión y trazabilidad</h3>
<p>Ejercicio {{ $report['year'] }} · Reglas {{ $report['rules_version'] }} · Versión beta pendiente de revisión profesional.</p>
<p>Este informe conserva los datos utilizados al generarlo. Los cambios posteriores de la cartera requieren una nueva versión.</p>
@foreach($report['properties'] as $item)
<section class="property">
    <div class="brand">alquivo <span class="muted">/ {{ $report['year'] }}</span></div>
    <h1>{{ $item['property']['name'] }}</h1>
    <p>{{ $item['property']['address_line'] }} · {{ $item['property']['city'] }}</p>
    <p>Titularidad: {{ $item['inputs']['ownership_percent'] ?? 'Pendiente' }} % · {{ $item['inputs']['owned_from'] ?? 'Pendiente' }} a {{ $item['inputs']['owned_to'] ?? 'Pendiente' }}</p>
    <p>Uso: {{ $item['result']['days']['rented'] }} días alquilados · {{ $item['result']['days']['available'] }} disponibles · {{ $item['result']['days']['habitual'] }} de vivienda habitual.</p>
    @if(count($item['inputs']['periods'] ?? []))
    <table><thead><tr><th>Desde</th><th>Hasta</th><th>Uso</th></tr></thead>
    @foreach($item['inputs']['periods'] as $period)
    <tr><td>{{ $period['start'] }}</td><td>{{ $period['end'] }}</td><td>{{ ['rented' => 'Alquilado', 'available' => 'A disposición', 'habitual' => 'Vivienda habitual'][$period['use']] }}</td></tr>
    @endforeach
    </table>
    @endif
    @foreach($item['result']['issues'] as $issue)
    <p class="warning">{{ $issue }}</p>
    @endforeach
    <table>
    @foreach($report['figure_labels'] as $key => $label)
    <tr><td>{{ $label }}</td><td class="number">{{ $money($item['result']['figures'][$key] ?? null) }}</td></tr>
    @endforeach
    </table>
    @if($item['result']['figures'])
    <p>Reducción ordinaria: {{ $item['result']['reduction_percent'] }} % del rendimiento neto positivo. Fecha del contrato: {{ $item['inputs']['contract_start'] ?? 'Sin alquiler' }}.</p>
    @endif
    <h3>Conciliación con la gestión diaria (100 % del inmueble)</h3>
    <p>Cargos exigibles registrados: {{ $money($item['source']['charge_income_cents']) }} · Ingresos cobrados: {{ $money($item['source']['paid_income_cents']) }} · Gastos pagados: {{ $money($item['source']['paid_expenses_cents']) }}.</p>
    <p>Ingreso fiscal confirmado: {{ isset($item['inputs']['income']) ? $money((int) bcmul($item['inputs']['income'], '100', 0)) : 'Pendiente' }}. Los cargos y sus cobros no se suman entre sí. Revisa diferencias, anticipos e impagos con tu gestor.</p>
    <h3>Bases de amortización (100 % del inmueble)</h3>
    <p>Coste de construcción: {{ $item['inputs']['building_cost'] ?? 'Pendiente' }} EUR · Catastro construcción: {{ $item['inputs']['cadastral_building'] ?? 'Pendiente' }} EUR.</p>
    <p>Amortización previa: {{ $item['inputs']['prior_depreciation'] ?? 'Pendiente' }} EUR · Porcentaje anual: {{ $item['inputs']['depreciation_rate'] ?? 'Pendiente' }} %. Se excluye el suelo y se aplican días alquilados y titularidad.</p>
    @if($item['result']['days']['available'] > 0)
    <p>Imputación: catastro total {{ $item['inputs']['cadastral_total'] ?? 'Pendiente' }} EUR. Valoración colectiva: {{ ['since_2012' => 'con efectos desde 2012 (1,1 %)', 'before_2012' => 'anterior a 2012 (2 %)'][$item['inputs']['cadastral_revision'] ?? ''] ?? 'Pendiente' }}. Base anual prorrateada por días disponibles y titularidad.</p>
    @endif
    <h3>Gastos registrados</h3>
    <table>
        <thead><tr><th style="width:48%">Concepto / justificante</th><th>Importe completo</th><th>Asignación</th></tr></thead>
        @forelse($item['inputs']['expenses'] ?? [] as $expense)
        <tr><td>{{ $expense['description'] }}<br><span class="small">Documento: {{ $expense['document_id'] ?? 'No vinculado' }}</span></td><td>{{ $expense['amount'] }} EUR</td><td>{{ $expense['allocation'] === 'annual' ? 'Anual, prorrateado' : 'Solo alquiler' }}</td></tr>
        @empty
        <tr><td colspan="3">No se han registrado gastos fiscales en esta ficha.</td></tr>
        @endforelse
    </table>
    @if(count($item['result']['carryforwards']))
    <h3>Financiación y reparaciones: aplicación y arrastres atribuibles</h3>
    <table><thead><tr><th>Origen / último año</th><th>Aplicado</th><th>Pendiente</th><th>Caducado</th></tr></thead>
    @foreach($item['result']['carryforwards'] as $carry)
    <tr><td>{{ $carry['year'] }} / {{ $carry['last_year'] }}</td><td>{{ $money($carry['used_cents']) }}</td><td>{{ $money($carry['pending_cents']) }}</td><td>{{ $money($carry['expired_cents']) }}</td></tr>
    @endforeach
    </table>
    <p class="small">Los saldos anteriores se aplican primero. Estos saldos son el resultado de este borrador; confirma su uso en la declaración antes de trasladarlos a otro ejercicio.</p>
    @endif
    @if(!empty($item['inputs']['notes']))
    <h3>Notas para revisión</h3><p>{{ $item['inputs']['notes'] }}</p>
    @endif
    <h3>Justificantes disponibles al generar el informe</h3>
    @forelse($item['source']['documents'] as $document)
    <p class="small">#{{ $document['id'] }} · {{ $document['name'] }}</p>
    @empty
    <p class="muted">Sin documentos asociados. Conserva los justificantes que respalden los importes.</p>
    @endforelse
</section>
@endforeach
<section class="property">
    <h2>Criterios y fuentes</h2>
    <p>Redondeo a céntimos por partida. Gastos marcados como anuales: importe del año natural completo, prorrateado por días alquilados. Gastos de solo alquiler: importe ya asignado a ese uso. Todos se atribuyen después según la titularidad indicada.</p>
    <p>La revisión confirma los datos aportados por el usuario. El dossier no acredita por sí solo que un gasto sea deducible ni que un justificante sea suficiente.</p>
    @foreach($report['sources'] as $source)
    <p class="source"><a href="{{ $source['url'] }}">{{ $source['label'] }}</a></p>
    @endforeach
    <p class="note">Los casos de no residentes, sociedades, regímenes forales, actividad económica, alquiler temporal o por habitaciones y otros supuestos especiales requieren un cálculo específico.</p>
</section>
</body>
</html>
