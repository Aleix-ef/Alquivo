<?php

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Models\AiConversation;
use App\Domain\Assistant\Models\AiRun;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;

/** A model must never silently turn a user's negative amount into a positive write. */
final class AssistantMoneyInput
{
    public function clarification(Portfolio $portfolio, User $user, AiRun $run, string $tool, array $arguments): ?array
    {
        if (! in_array($tool, ['propose_expense', 'propose_rent_payment', 'propose_property', 'propose_lease'], true)
            || $run->user_message_id === null) {
            return null;
        }
        $conversation = AiConversation::whereKey($run->conversation_id)->where('portfolio_id', $portfolio->id)
            ->where('user_id', $user->id)->firstOrFail();
        $text = $conversation->messages()->whereKey($run->user_message_id)->where('role', 'user')->firstOrFail()->content;
        // Do not mistake date/phone separators (2026-09-20, 611-222-333) for negative money.
        $negative = $this->hasNegativeAmount($text);
        $last = $conversation->messages()->where('role', 'assistant')->latest('id')->first();
        $previousRun = $last ? AiRun::where('portfolio_id', $portfolio->id)->where('user_id', $user->id)
            ->where('conversation_id', $conversation->id)->where('assistant_message_id', $last->id)->where('status', 'completed')->first() : null;
        $previousText = $previousRun ? $conversation->messages()->whereKey($previousRun->user_message_id)->where('role', 'user')->first()?->content : null;
        // Also cover a provider that declined the previous negative request without invoking a tool.
        $blockedBefore = $previousRun && (($last->metadata['clarification_code'] ?? null) === 'negative_money'
            || ($previousText !== null && $this->hasNegativeAmount($previousText)));
        if (! $negative && (! $blockedBefore || $this->explicitCorrection($text, $arguments))) {
            return null;
        }

        return ['clarification' => 'No voy a convertir un importe negativo en positivo. Indícame el importe correcto con su signo; estas propuestas no permiten gastos, cobros ni precios negativos. Si es una devolución o una corrección, revísala en el formulario correspondiente. No he guardado ningún dato.',
            'clarification_code' => 'negative_money'];
    }

    private function hasNegativeAmount(string $text): bool
    {
        return preg_match('/(?<![\p{L}\p{N}])(?:[-−–－]\s*(?:€\s*)?|menos\s+)\d/iu', $text) === 1
            || preg_match('/(?:importe|gasto|cobro|precio|renta|valor(?:ación)?)\s+negativ[oa]/iu', $text) === 1;
    }

    private function explicitCorrection(string $text, array $arguments): bool
    {
        // Only used after a known blocked negative request; no general-purpose intent parser.
        $amounts = array_filter(array_intersect_key($arguments, array_flip(['amount', 'purchase_price', 'current_value', 'monthly_rent', 'deposit_amount'])),
            fn ($amount) => is_string($amount) && preg_match('/^\d+(?:\.\d{1,2})?$/D', $amount));
        preg_match_all('/(?<![\p{L}\p{N}])([0-9]+(?:[.,][0-9]{1,2})?)\s*(?:€|euros?)(?!\p{L})/iu', $text, $matches);
        $mentions = $matches[1];
        if (preg_match('/^\s*(\d+(?:[.,]\d{1,2})?)\s*$/D', $text, $single)) {
            $mentions[] = $single[1];
        }
        foreach ($mentions as $mention) {
            foreach ($amounts as $amount) {
                if (bccomp(str_replace(',', '.', $mention), $amount, 2) === 0 && bccomp($amount, '0', 2) > 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
