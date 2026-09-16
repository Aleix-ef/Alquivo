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

    public function test_personal_data_answer_without_tool_evidence_is_replaced(): void
    {
        $reply = AssistantReply::parse($this->providerOutput(['kind' => 'answer', 'basis' => 'portfolio_data', 'content' => 'Tu cartera vale un millón.']), false);
        $this->assertSame('insufficient_data', $reply['metadata']['kind']);
        $this->assertStringNotContainsString('millón', $reply['content']);
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
