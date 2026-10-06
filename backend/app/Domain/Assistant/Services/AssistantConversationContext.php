<?php

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Models\AiActionProposal;
use App\Domain\Assistant\Models\AiConversation;
use App\Domain\Assistant\Models\AiRun;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;

/** Short-lived entity references, never remembered balances or implicit confirmation. */
final class AssistantConversationContext
{
    public function previousProperty(Portfolio $portfolio, User $user, AiRun $run): ?int
    {
        $conversation = $this->conversation($portfolio, $user, $run);
        $message = $conversation->messages()->where('role', 'assistant')
            ->where('created_at', '>=', now()->subMinutes(30))->latest('id')->first();
        if (! $message || ! AiRun::where('portfolio_id', $portfolio->id)->where('user_id', $user->id)
            ->where('conversation_id', $conversation->id)->where('assistant_message_id', $message->id)->where('status', 'completed')->exists()) {
            return null;
        }
        // A failed/interrupted turn has no assistant message to carry an invalidation.
        // Never skip that newer user turn to recover an older property reference.
        $newerUserTurn = $conversation->messages()->where('role', 'user')->where('id', '>', $message->id);
        if ($run->user_message_id !== null) {
            $newerUserTurn->where('id', '!=', $run->user_message_id);
        }
        if ($newerUserTurn->exists()) {
            return null;
        }
        $id = $message->metadata['property_reference_id'] ?? null;
        if ($id === null) {
            // A new property did not exist when its preview was sent. Only the separate,
            // successful confirmation receipt can supply its ID on a later chat turn.
            $proposalIds = array_column($message->metadata['proposals'] ?? [], 'id');
            $proposals = AiActionProposal::whereIn('id', $proposalIds)->where('portfolio_id', $portfolio->id)
                ->where('user_id', $user->id)->where('type', 'property_create')->where('status', 'executed')
                ->where('confirmed_by', $user->id)->whereHas('run', fn ($query) => $query
                ->where('conversation_id', $conversation->id)->where('assistant_message_id', $message->id)->where('status', 'completed'))
                ->limit(2)->get();
            $id = $proposals->count() === 1 ? ($proposals->first()->result['property_id'] ?? null) : null;
        }

        return is_int($id) && $portfolio->properties()->whereKey($id)->exists() ? $id : null;
    }

    public function observe(Portfolio $portfolio, User $user, AiRun $run, string $tool, array $arguments, array $result): void
    {
        $this->conversation($portfolio, $user, $run);
        if (isset($result['error'])) {
            $ids = [];
        } elseif (isset($result['proposal'])) {
            $ids = isset($result['proposal']['preview']['property']['id']) ? [$result['proposal']['preview']['property']['id']] : [];
        } elseif ($tool === 'search_properties' || ($result['clarification_domain'] ?? null) === 'property') {
            $ids = ($result['count'] ?? 0) === 1 ? array_column($result['properties'], 'id') : [];
        } elseif (! empty($arguments['property_id'])) {
            $ids = [$arguments['property_id']];
        } elseif (in_array($tool, ['get_financial_summary', 'list_rent_charges'], true) && ! empty($arguments['property_query'])) {
            $ids = $run->steps()->where('kind', 'resolution')->where('tool', 'search_properties')->latest('id')->first()?->metadata['property_ids'] ?? [];
        } elseif ($tool === 'get_lease_details') {
            $ids = [data_get($result, 'lease.property.id')];
        } elseif (in_array($tool, ['list_rent_charges', 'list_leases'], true) && ! ($result['truncated'] ?? true)) {
            $rows = $result[$tool === 'list_rent_charges' ? 'charges' : 'leases'] ?? [];
            $ids = array_values(array_unique(array_column(array_column($rows, 'property'), 'id')));
        } else {
            // A general/ambiguous/new-entity question must not keep an older "last property".
            $ids = [];
        }
        $id = count($ids) === 1 && is_int($ids[0]) && $portfolio->properties()->whereKey($ids[0])->exists() ? $ids[0] : null;
        $run->steps()->create(['kind' => 'context', 'status' => 'completed', 'tool' => isset($result['error']) ? 'unregistered' : $tool,
            'metadata' => ['property_reference_id' => $id]]);
    }

    public function currentProperty(AiRun $run): ?int
    {
        $id = $run->steps()->where('kind', 'context')->latest('id')->first()?->metadata['property_reference_id'] ?? null;

        return is_int($id) ? $id : null;
    }

    private function conversation(Portfolio $portfolio, User $user, AiRun $run): AiConversation
    {
        abort_unless($run->portfolio_id === $portfolio->id && $run->user_id === $user->id, 404);

        return AiConversation::whereKey($run->conversation_id)->where('portfolio_id', $portfolio->id)->where('user_id', $user->id)->firstOrFail();
    }
}
