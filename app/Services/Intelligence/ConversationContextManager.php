<?php

namespace App\Services\Intelligence;

use App\Models\AssistantConversation;

class ConversationContextManager
{
    public const VERSION = 1;

    public function state(AssistantConversation $conversation): array
    {
        return array_replace([
            'active_topic' => null,
            'metric' => null,
            'time_range' => null,
            'outlet_ids' => [],
            'selected_entities' => [],
            'comparison_basis' => null,
            'visual_preference' => 'auto',
            'last_tool' => null,
            'last_arguments' => [],
            'last_evidence_ref' => null,
            'pending_action_id' => null,
        ], $conversation->context ?? []);
    }

    public function recentMessages(AssistantConversation $conversation): array
    {
        return $conversation->messages()->orderByDesc('id')
            ->limit(max(4, min(12, config('services.yoka_ai.max_history', 8))))
            ->get()->reverse()->values()
            ->map(fn ($message) => ['role' => $message->role, 'content' => $message->content])->all();
    }

    public function update(AssistantConversation $conversation, array $decision, ?array $evidence = null): array
    {
        $state = $this->state($conversation);
        if (($decision['intent'] ?? null) !== 'conversation') {
            $state['active_topic'] = $decision['tool'] ?? $state['active_topic'];
            $state['last_tool'] = $decision['tool'] ?? $state['last_tool'];
            $state['last_arguments'] = $decision['arguments'] ?? $state['last_arguments'];
            $state['time_range'] = array_intersect_key($decision['arguments'] ?? [], array_flip(['period', 'date_from', 'date_to']));
            $state['visual_preference'] = $decision['visual'] ?? 'auto';
            $state['last_evidence_ref'] = $evidence['id'] ?? $state['last_evidence_ref'];
        }

        $conversation->update([
            'context_version' => self::VERSION,
            'context' => $state,
            'summary' => $this->summary($state),
            'last_message_at' => now(),
        ]);

        return $state;
    }

    public function setPendingAction(AssistantConversation $conversation, int $proposalId): void
    {
        $state = $this->state($conversation);
        $state['pending_action_id'] = $proposalId;
        $conversation->update(['context_version' => self::VERSION, 'context' => $state, 'summary' => $this->summary($state), 'last_message_at' => now()]);
    }

    private function summary(array $state): string
    {
        $parts = array_filter([
            $state['active_topic'] ? 'Topik aktif: '.$state['active_topic'] : null,
            ! empty($state['time_range']) ? 'Periode: '.json_encode($state['time_range']) : null,
            $state['comparison_basis'] ? 'Perbandingan: '.$state['comparison_basis'] : null,
        ]);

        return implode('. ', $parts);
    }
}
