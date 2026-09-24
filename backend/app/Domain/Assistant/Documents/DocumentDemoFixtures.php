<?php

namespace App\Domain\Assistant\Documents;

use App\Domain\Assistant\Providers\FakeAIProvider;

/** Synthetic fixtures only. This is NOT OCR or an alternate inference provider. */
final class DocumentDemoFixtures
{
    public function pdf(string $kind): string
    {
        $lines = $kind === 'invoice' ? [
            'ALQUIVO - FACTURA FICTICIA DE PRUEBA', 'Sin validez comercial. Datos simulados.', '',
            'Emisor: Fontaneria Demo SL', 'Factura: DEMO-2026-001', 'Fecha: 2026-09-01',
            'Concepto: Reparacion de grifo', 'Inmueble: Piso de prueba', '',
            'Base: 100.00 EUR', 'IVA: 21.00 EUR', 'Total: 121.00 EUR', '',
            'No consta si esta pagada. Comprueba el inmueble antes de confirmar.',
        ] : [
            'ALQUIVO - CONTRATO FICTICIO DE PRUEBA', 'Sin validez juridica. Datos simulados.', '',
            'Inmueble: Piso de prueba', 'Inquilina: Ana Ejemplo',
            'Inicio: 2026-10-01', 'Fin: 2027-09-30', 'Renta mensual: 750.00 EUR',
            'Fianza: 750.00 EUR', 'Dia de pago: 5', '',
            'Revision de renta: no consta una clausula de actualizacion.',
            'Este ejemplo no ofrece interpretacion ni asesoramiento legal.',
        ];
        $commands = "BT /F1 13 Tf 50 780 Td 25 TL\n";
        foreach ($lines as $line) {
            $commands .= '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line).") Tj T*\n";
        }
        $commands .= 'ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($commands).">>\nstream\n".$commands."\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }

    public function provider(string $kind, string $bytes): FakeAIProvider
    {
        $data = app(DocumentExtractionSchema::class)->empty($kind);
        if ($kind === 'invoice' && hash_equals(hash('sha256', $this->pdf($kind)), hash('sha256', $bytes))) {
            $data = [...$data, 'issuer' => 'Fontaneria Demo SL', 'invoice_number' => 'DEMO-2026-001',
                'date' => '2026-09-01', 'description' => 'Reparacion de grifo', 'subtotal' => '100.00',
                'vat' => '21.00', 'total' => '121.00', 'category' => 'maintenance',
                'property_hint' => 'Piso de prueba', 'currency' => 'EUR',
                'evidence' => [
                    ['field' => 'total', 'quote' => 'Total: 121.00 EUR', 'page' => 1],
                    ['field' => 'property_hint', 'quote' => 'Inmueble: Piso de prueba', 'page' => 1],
                ],
                'warnings' => ['Ejemplo simulado. No se ha consultado a OpenAI. El pago no está acreditado.']];
        } elseif ($kind === 'contract' && hash_equals(hash('sha256', $this->pdf($kind)), hash('sha256', $bytes))) {
            $data = [...$data, 'property_hint' => 'Piso de prueba', 'start_date' => '2026-10-01', 'end_date' => '2027-09-30',
                'monthly_rent' => '750.00', 'deposit_amount' => '750.00', 'periodicity' => 'monthly', 'payment_day' => '5', 'currency' => 'EUR',
                'participants' => [['name' => 'Ana Ejemplo', 'role' => 'tenant']],
                'evidence' => [
                    ['field' => 'monthly_rent', 'quote' => 'Renta mensual: 750.00 EUR', 'page' => 1],
                    ['field' => 'participants', 'quote' => 'Inquilina: Ana Ejemplo', 'page' => 1],
                    ['field' => 'property_hint', 'quote' => 'Inmueble: Piso de prueba', 'page' => 1],
                ], 'warnings' => ['Contrato ficticio: datos simulados. Revisa las partes; no ofrece interpretación jurídica.']];
        } else {
            $data['warnings'] = ['Simulación local: este archivo no se ha analizado. Los campos se dejan vacíos; complétalos consultando el original.'];
        }

        return new FakeAIProvider([self::response($data)]);
    }

    public static function response(array $data): array
    {
        return ['status' => 'completed', 'usage' => ['input_tokens' => 0, 'output_tokens' => 0],
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($data, JSON_THROW_ON_ERROR)]]]]];
    }
}
