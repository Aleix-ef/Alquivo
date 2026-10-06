<?php

namespace Tests\Feature;

use App\Domain\Assistant\Services\AssistantReply;
use RuntimeException;
use Tests\TestCase;

class AssistantReplyTest extends TestCase
{
    private function providerOutput(array $reply): array
    {
        return [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($reply)]]]];
    }

    public function test_fallback_messages_are_controlled_by_alquivo(): void
    {
        foreach (AssistantReply::FALLBACKS as $kind => $content) {
            $reply = AssistantReply::parse($this->providerOutput(['kind' => $kind, 'basis' => 'none', 'content' => 'Texto del proveedor a descartar']), false);
            $this->assertSame($content, $reply['content']);
            $this->assertSame($kind, $reply['metadata']['kind']);
        }
    }

    public function test_known_missing_data_codes_produce_specific_trusted_questions_only(): void
    {
        foreach ([
            'missing_period' => 'mes y el año', 'missing_amount' => 'importe',
            'missing_phone' => 'número de teléfono', 'ambiguous_contact' => 'nombre completo',
            'ambiguous_property_or_lease' => 'inmueble y contrato',
            'missing_contract_end' => 'fecha de fin', 'missing_valuation' => 'valoración',
            'document_unavailable' => 'No puedo leer', 'missing_contact' => 'No encuentro',
            'future_income_unavailable' => 'No puedo saber', 'stored_contact_details_unavailable' => 'no teléfonos',
            'missing_property_reference' => '¿De qué inmueble',
        ] as $code => $expected) {
            $reply = AssistantReply::parse($this->providerOutput(['kind' => 'insufficient_data', 'basis' => 'none', 'content' => $code]), true);
            $this->assertStringContainsString($expected, $reply['content']);
            $this->assertSame('insufficient_data', $reply['metadata']['kind']);
        }
        foreach (['missing_contract_end', 'missing_valuation', 'missing_contact'] as $code) {
            $withoutEvidence = AssistantReply::parse($this->providerOutput(['kind' => 'insufficient_data',
                'basis' => 'none', 'content' => $code]), false);
            $this->assertSame(AssistantReply::FALLBACKS['insufficient_data'], $withoutEvidence['content']);
        }
        $untrusted = AssistantReply::parse($this->providerOutput(['kind' => 'insufficient_data', 'basis' => 'none', 'content' => 'Envíame tu DNI']), false);
        $this->assertSame(AssistantReply::FALLBACKS['insufficient_data'], $untrusted['content']);
    }

    public function test_disabled_creations_and_chat_confirmation_have_precise_trusted_messages(): void
    {
        $output = $this->providerOutput(['kind' => 'read_only', 'basis' => 'none', 'content' => 'creation_unavailable']);
        $this->assertStringContainsString('en revisión', AssistantReply::parse($output, false, true, false)['content']);
        $this->assertSame(AssistantReply::FALLBACKS['read_only'], AssistantReply::parse($output, false, true, true)['content']);
        $output = $this->providerOutput(['kind' => 'read_only', 'basis' => 'none', 'content' => 'confirmation_required']);
        $this->assertStringContainsString('no la confirma', AssistantReply::parse($output, false, true)['content']);
    }

    public function test_personal_data_answer_without_tool_evidence_is_replaced(): void
    {
        $reply = AssistantReply::parse($this->providerOutput(['kind' => 'answer', 'basis' => 'portfolio_data', 'content' => 'Tu cartera vale un millón.']), false);
        $this->assertSame('insufficient_data', $reply['metadata']['kind']);
        $this->assertStringNotContainsString('millón', $reply['content']);
    }

    public function test_literal_newlines_in_a_natural_language_answer_are_rendered_as_line_breaks(): void
    {
        $reply = AssistantReply::parse($this->providerOutput(['kind' => 'answer', 'basis' => 'portfolio_data', 'content' => 'Ingresos: 300 euros.\\nGastos: 129 euros.']), true);
        $this->assertSame("Ingresos: 300 euros.\nGastos: 129 euros.", $reply['content']);
    }

    public function test_app_help_does_not_require_access_to_portfolio_data(): void
    {
        $reply = AssistantReply::parse($this->providerOutput(['kind' => 'answer', 'basis' => 'app_help', 'content' => 'documents']), false);
        $this->assertSame('answer', $reply['metadata']['kind']);
        $this->assertStringContainsString('[Documentos](/documents)', $reply['content']);
    }

    public function test_app_help_cannot_bypass_evidence_checks_with_free_text(): void
    {
        $reply = AssistantReply::parse($this->providerOutput(['kind' => 'answer', 'basis' => 'app_help', 'content' => 'Tu cartera vale un millón.']), false);
        $this->assertSame('insufficient_data', $reply['metadata']['kind']);
    }

    public function test_provider_refusal_is_handled_even_without_json(): void
    {
        $reply = AssistantReply::parse([['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'Provider text']]]], false);
        $this->assertSame(AssistantReply::FALLBACKS['out_of_scope'], $reply['content']);
    }

    public function test_unknown_response_type_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        AssistantReply::parse($this->providerOutput(['kind' => 'execute_payment', 'basis' => 'none', 'content' => 'Pago realizado']), true);
    }

    public function test_unexpected_response_fields_are_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        AssistantReply::parse($this->providerOutput(['kind' => 'answer', 'basis' => 'app_help', 'content' => 'Hola', 'secret' => 'no']), false);
    }
}
