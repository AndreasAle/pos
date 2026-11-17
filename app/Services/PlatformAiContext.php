<?php

namespace App\Services;

use App\Models\PlatformAdmin;
use App\Models\PlatformAiAgent;
use App\Models\PlatformAiConversationState;
use App\Models\PlatformAiDecision;
use App\Models\PlatformAiMemory;
use App\Models\PlatformAiMessage;
use App\Models\PlatformAiOwnerContext;
use Illuminate\Support\Str;

class PlatformAiContext
{
    /** Context is data, never an instruction layer. Current verified tool data remains authoritative. */
    public function package(PlatformAiAgent $agent, PlatformAdmin $admin, string $question): array
    {
        $owner = PlatformAiOwnerContext::query()->orderBy('key')->get(['key', 'label', 'value', 'confirmed']);
        $state = PlatformAiConversationState::query()
            ->whereBelongsTo($admin, 'admin')->whereBelongsTo($agent, 'agent')->first();
        $memories = $this->relevantMemories($agent, $admin, $question);
        $decisions = PlatformAiDecision::query()->where('status', 'active')->latest()->limit(8)->get();

        return [
            'current_signal' => $this->conversationSignal($question),
            'owner_context' => $owner->map(fn ($item) => [
                'key' => $item->key, 'label' => $item->label, 'value' => $item->value,
                'authority' => $item->confirmed ? 'confirmed_by_owner' : 'inferred',
            ])->all(),
            'conversation_state' => $state ? [
                'active_topic' => $state->active_topic, 'mood' => $state->mood,
                'state' => $state->state, 'revised_at' => $state->context_revised_at?->toIso8601String(),
            ] : null,
            'memories' => $memories->map(fn ($memory) => [
                'id' => $memory->id, 'scope' => $memory->scope, 'category' => $memory->category,
                'content' => $memory->content, 'confirmed' => $memory->confirmed,
                'importance' => $memory->importance,
            ])->all(),
            'active_decisions' => $decisions->map(fn ($decision) => [
                'id' => $decision->id, 'area' => $decision->business_area,
                'statement' => $decision->statement, 'reason' => $decision->reason,
                'conditions' => $decision->conditions,
            ])->all(),
        ];
    }

    public function rememberExplicitOwnerStatement(
        PlatformAiAgent $agent,
        PlatformAdmin $admin,
        PlatformAiMessage $message,
        string $text
    ): ?PlatformAiMemory {
        $candidate = $this->explicitCandidate($text);
        if (! $candidate) {
            return null;
        }

        $existing = PlatformAiMemory::query()
            ->where('scope', 'owner')->where('platform_admin_id', $admin->id)
            ->where('category', $candidate['category'])->where('memory_key', $candidate['key'])
            ->where('status', 'active')->latest()->first();

        if ($existing && data_get($existing->content, 'statement') === $candidate['statement']) {
            $existing->update(['last_confirmed_at' => now(), 'confirmed' => true, 'confidence' => 1]);

            return $existing;
        }

        $memory = PlatformAiMemory::create([
            'scope' => 'owner', 'platform_admin_id' => $admin->id,
            'platform_ai_agent_id' => $agent->id, 'category' => $candidate['category'],
            'memory_key' => $candidate['key'], 'content' => ['statement' => $candidate['statement']],
            'source_message_id' => $message->id, 'source_type' => 'explicit_owner_statement',
            'confidence' => 1, 'importance' => $candidate['importance'], 'confirmed' => true,
            'last_confirmed_at' => now(), 'version' => ($existing?->version ?? 0) + 1,
        ]);
        if ($existing) {
            $existing->update(['status' => 'superseded', 'superseded_by' => $memory->id]);
        }
        if ($candidate['category'] === 'decision') {
            PlatformAiDecision::create([
                'statement' => $candidate['statement'], 'business_area' => $agent->key,
                'decided_by' => $admin->id, 'platform_ai_agent_id' => $agent->id,
                'source_message_id' => $message->id, 'status' => 'active', 'version' => 1,
            ]);
        }

        return $memory;
    }

    public function reviseConversationState(
        PlatformAiAgent $agent,
        PlatformAdmin $admin,
        string $question,
        array $createdTaskIds = [],
        ?string $assistantResponse = null
    ): PlatformAiConversationState {
        $state = PlatformAiConversationState::firstOrNew([
            'platform_admin_id' => $admin->id,
            'platform_ai_agent_id' => $agent->id,
        ]);
        $current = (array) $state->state;
        $current['last_user_message'] = Str::limit($question, 500, '');
        $current['recent_task_ids'] = array_values(array_unique(array_merge($createdTaskIds, $current['recent_task_ids'] ?? [])));
        $current['recent_task_ids'] = array_slice($current['recent_task_ids'], 0, 10);
        if ($assistantResponse !== null) {
            $current['last_assistant_response'] = Str::limit($assistantResponse, 2000, '');
            $current['recent_options'] = $this->extractOptions($assistantResponse);
        }
        $state->fill([
            'active_topic' => $this->topic($question, $state->active_topic),
            'mood' => $this->mood($question),
            'state' => $current,
            'context_revised_at' => now(),
        ])->save();

        return $state;
    }

    private function extractOptions(string $response): array
    {
        preg_match_all('/^(?:\s*)(?:\d+[.)]|[-*])\s+(.+)$/mu', $response, $matches);

        return collect($matches[1] ?? [])->map(fn ($option, $index) => [
            'position' => $index + 1, 'text' => Str::limit(trim($option), 300, ''),
        ])->take(8)->values()->all();
    }

    private function relevantMemories(PlatformAiAgent $agent, PlatformAdmin $admin, string $question)
    {
        $terms = collect(preg_split('/[^\pL\pN]+/u', Str::lower($question)))
            ->filter(fn ($term) => mb_strlen($term) >= 4)->unique()->take(6);

        return PlatformAiMemory::query()
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(function ($query) use ($agent, $admin) {
                $query->where(fn ($q) => $q->where('scope', 'owner')->where('platform_admin_id', $admin->id))
                    ->orWhere(fn ($q) => $q->where('scope', 'shared'))
                    ->orWhere(fn ($q) => $q->where('scope', 'agent')->where('platform_ai_agent_id', $agent->id));
            })
            ->when($terms->isNotEmpty(), function ($query) use ($terms) {
                $query->where(function ($q) use ($terms) {
                    foreach ($terms as $term) {
                        $q->orWhere('content', 'like', '%'.$term.'%');
                    }
                    $q->orWhere('importance', '>=', 80);
                });
            })
            ->orderByDesc('confirmed')->orderByDesc('importance')->latest('last_confirmed_at')->limit(12)->get();
    }

    private function explicitCandidate(string $text): ?array
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $text));
        $patterns = [
            'preference' => '/^(?:aku|saya)\s+(?:lebih\s+)?(?:suka|ingin|mau|tidak suka|nggak suka|gak suka)\b/iu',
            'decision' => '/^(?:putuskan|keputusannya|mulai sekarang|jangan|prioritaskan|fokus(?:nya)?|budget jangan)\b/iu',
            'correction' => '/^(?:koreksi|ralat|bukan begitu|yang benar|itu salah)\b/iu',
        ];
        foreach ($patterns as $category => $pattern) {
            if (preg_match($pattern, $normalized)) {
                return [
                    'category' => $category,
                    'key' => hash('sha256', $category.'|'.Str::lower(Str::limit($normalized, 120, ''))),
                    'statement' => Str::limit($normalized, 1000, ''),
                    'importance' => $category === 'decision' ? 95 : 85,
                ];
            }
        }

        return null;
    }

    private function topic(string $text, ?string $fallback): ?string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', $text));

        return mb_strlen($clean) >= 12 ? Str::limit($clean, 140, '') : $fallback;
    }

    private function mood(string $text): string
    {
        return match (true) {
            preg_match('/\b(cepat|urgent|darurat|segera)\b/iu', $text) === 1 => 'urgent',
            preg_match('/\b(kesal|kecewa|salah lagi|kok begini|parah)\b/iu', $text) === 1 => 'frustrated',
            preg_match('/\b(ide|brainstorm|coba bayangkan|eksplor)\b/iu', $text) === 1 => 'exploratory',
            preg_match('/\b(bro|wkwk|haha|santai)\b/iu', $text) === 1 => 'casual',
            default => 'serious',
        };
    }

    private function conversationSignal(string $text): array
    {
        $mood = $this->mood($text);
        $correction = preg_match('/\b(salah|bukan itu|maksudku|koreksi|ralat|keliru)\b/iu', $text) === 1;
        $guidance = match ($mood) {
            'urgent' => 'Berikan keputusan atau jawaban inti lebih dulu; singkat dan tanpa pembuka ramah yang tidak perlu.',
            'frustrated' => 'Akui masalah secara singkat, jangan defensif atau terlalu ceria, lalu fokus pada koreksi konkret.',
            'exploratory' => 'Boleh mengeksplorasi beberapa alternatif tanpa terlalu cepat mengunci satu pilihan.',
            'casual' => 'Balas santai dan natural, tetap profesional dan tidak berlebihan.',
            default => 'Jawab profesional, langsung, dan proporsional terhadap kompleksitas pertanyaan.',
        };
        if ($correction) {
            $guidance .= ' Ini koreksi: akui bila jawabanmu sebelumnya keliru, ambil ulang fakta lewat tool, dan jangan mempertahankan klaim lama.';
        }

        return ['mood' => $mood, 'correction' => $correction, 'response_guidance' => $guidance];
    }
}
