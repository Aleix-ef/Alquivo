<?php

namespace App\Domain\Fiscality\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

final class TaxReportRenderer
{
    public function pdf(array $payload): string
    {
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setChroot(resource_path('views/fiscality'));
        $options->setAllowedProtocols(['file://']);
        $options->setTempDir(storage_path('framework/cache'));
        $options->setFontCache(storage_path('framework/cache'));
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('fiscality.dossier-v1', ['report' => $payload])->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();
        $pdf->getCanvas()->page_text(42, 808, 'Alquivo | Borrador fiscal | {PAGE_NUM} / {PAGE_COUNT}', 'Helvetica', 8, [0.35, 0.4, 0.45]);

        return $pdf->output();
    }

    public function csv(array $payload): string
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, "\xEF\xBB\xBF");
        $write = function (array $row) use ($stream) {
            fputcsv($stream, array_map(function ($value) {
                $value = (string) ($value ?? '');

                return preg_match('/^[\s\x00-\x1f]*[=+@\-]/u', $value) ? "'".$value : $value;
            }, $row), ';', '"', '');
        };
        $write(['Ejercicio', 'Contribuyente', 'Inmueble', 'Estado', 'Sección', 'Concepto', 'Importe EUR / valor', 'Detalle']);
        foreach ($payload['properties'] as $item) {
            $prefix = [$payload['year'], $payload['profile']['name'], $item['property']['name'], $item['result']['status'] === 'calculated' ? 'Calculado, pendiente de revisión' : 'Incompleto'];
            foreach ($payload['figure_labels'] as $key => $label) {
                $cents = $item['result']['figures'][$key] ?? null;
                $write([...$prefix, 'Resultado atribuible', $label, $cents === null ? '' : $this->decimal($cents), '']);
            }
            foreach ($item['result']['issues'] as $issue) {
                $write([...$prefix, 'Pendiente', $issue, '', '']);
            }
            foreach ($item['inputs']['expenses'] ?? [] as $expense) {
                $write([...$prefix, 'Gasto registrado al 100 %', $expense['description'], $expense['amount'], $expense['category'].'; '.$expense['allocation'].'; documento: '.($expense['document_id'] ?? 'sin vincular')]);
            }
            foreach ($item['inputs']['periods'] ?? [] as $period) {
                $write([...$prefix, 'Uso', $period['use'], $period['start'], $period['end']]);
            }
            foreach (['building_cost' => 'Coste de construcción', 'cadastral_building' => 'Catastro construcción', 'cadastral_total' => 'Catastro total', 'prior_depreciation' => 'Amortización anterior', 'income' => 'Ingreso exigible confirmado'] as $key => $label) {
                $write([...$prefix, 'Dato registrado al 100 %', $label, $item['inputs'][$key] ?? '', '']);
            }
            foreach ($item['result']['lines'] as $line) {
                $write([...$prefix, 'Gasto atribuible', $line['description'], $this->decimal($line['attributable_cents']), $line['limited'] ? 'Sujeto al límite conjunto de financiación y reparaciones' : '']);
            }
            foreach ($item['result']['carryforwards'] as $carry) {
                $write([...$prefix, 'Arrastre '.$carry['year'], 'Aplicado / pendiente / caducado', $this->decimal($carry['used_cents']), 'Pendiente: '.$this->decimal($carry['pending_cents']).'; caducado: '.$this->decimal($carry['expired_cents']).'; último ejercicio: '.$carry['last_year']]);
            }
            $write([...$prefix, 'Titularidad', 'Porcentaje', $item['inputs']['ownership_percent'] ?? '', 'Todos los resultados se atribuyen a este porcentaje.']);
        }
        $write(['', '', '', '', 'Alcance', $payload['notice'], '', $payload['rules_version']]);
        foreach ($payload['sources'] as $source) {
            $write(['', '', '', '', 'Fuente', $source['label'], '', $source['url']]);
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content;
    }

    private function decimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }
}
